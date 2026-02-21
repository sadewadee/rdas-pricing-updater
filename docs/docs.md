Berikut adalah solusi lengkap untuk membuat Domain Pricing Updater di WHMCS:

## 1. Structure File dan Database

### Buat Tabel Database untuk Settings dan Cache

```sql
-- Tabel untuk menyimpan settings
CREATE TABLE `rdash_domain_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `margin_type` enum('percentage','fixed') DEFAULT 'percentage',
  `profit_margin` decimal(10,2) DEFAULT 20.00,
  `rounding_rule` varchar(50) DEFAULT 'up_1000',
  `custom_rounding` int(11) DEFAULT 1000,
  `auto_update` tinyint(1) DEFAULT 1,
  `last_update` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Insert default settings
INSERT INTO `rdash_domain_settings` (margin_type, profit_margin, rounding_rule, custom_rounding, auto_update)
VALUES ('percentage', 20.00, 'up_1000', 1000, 1);

-- Tabel untuk cache data API
CREATE TABLE `rdash_domain_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tld` varchar(50) NOT NULL,
  `price_idr` decimal(10,2) NOT NULL,
  `cached_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tld` (`tld`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Tabel untuk log updates
CREATE TABLE `rdash_domain_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tld` varchar(50) NOT NULL,
  `old_price` decimal(10,2) DEFAULT NULL,
  `new_price` decimal(10,2) NOT NULL,
  `action` enum('manual','auto') DEFAULT 'auto',
  `updated_by` varchar(100) DEFAULT 'system',
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```


### File Helper Class (`includes/classes/RdashDomainUpdater.php`)

```php
<?php

class RdashDomainUpdater {
    private $api_url = 'https://api.rdash.id/api/domain-prices?currency=IDR';
    private $cache_duration = 14400; // 4 hours in seconds

    public function __construct() {
        // Constructor
    }

    // Get settings from database
    public function getSettings() {
        $result = Capsule::table('rdash_domain_settings')->first();
        return $result ? (array) $result : $this->getDefaultSettings();
    }

    private function getDefaultSettings() {
        return [
            'margin_type' => 'percentage',
            'profit_margin' => 20.00,
            'rounding_rule' => 'up_1000',
            'custom_rounding' => 1000,
            'auto_update' => 1
        ];
    }

    // Update settings
    public function updateSettings($settings) {
        $exists = Capsule::table('rdash_domain_settings')->first();
        if ($exists) {
            return Capsule::table('rdash_domain_settings')
                ->where('id', $exists->id)
                ->update($settings);
        } else {
            return Capsule::table('rdash_domain_settings')->insert($settings);
        }
    }

    // Get cached API data or fetch new
    public function getApiData($force_refresh = false) {
        if (!$force_refresh) {
            $cached = $this->getCachedData();
            if (!empty($cached)) {
                return $cached;
            }
        }

        return $this->fetchAndCacheApiData();
    }

    private function getCachedData() {
        $cached_data = Capsule::table('rdash_domain_cache')
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->get();

        if ($cached_data->count() > 0) {
            $result = [];
            foreach ($cached_data as $item) {
                $result[$item->tld] = $item->price_idr;
            }
            return $result;
        }

        return [];
    }

    private function fetchAndCacheApiData() {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'WHMCS-RDash-Updater/1.0');

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200 || !$response) {
            throw new Exception('Failed to fetch API data: HTTP ' . $http_code);
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from API');
        }

        // Cache the data
        $expires_at = date('Y-m-d H:i:s', time() + $this->cache_duration);

        foreach ($data as $tld => $price) {
            Capsule::table('rdash_domain_cache')
                ->updateOrInsert(
                    ['tld' => $tld],
                    [
                        'price_idr' => $price,
                        'cached_at' => date('Y-m-d H:i:s'),
                        'expires_at' => $expires_at
                    ]
                );
        }

        return $data;
    }

    // Get WHMCS TLDs that exist in database
    public function getWhmcsTlds() {
        $tlds = Capsule::table('tbldomainpricing')
            ->where('register', '!=', '-1.00')
            ->pluck('register', 'extension');

        return $tlds->toArray();
    }

    // Calculate new price based on settings
    public function calculatePrice($cost_price, $settings) {
        $margin = $settings['profit_margin'];

        if ($settings['margin_type'] === 'percentage') {
            $new_price = $cost_price * (1 + ($margin / 100));
        } else {
            $new_price = $cost_price + $margin;
        }

        // Apply rounding
        return $this->applyRounding($new_price, $settings);
    }

    private function applyRounding($price, $settings) {
        $increment = $settings['custom_rounding'];

        switch ($settings['rounding_rule']) {
            case 'up_1000':
                return ceil($price / $increment) * $increment;
            case 'down_1000':
                return floor($price / $increment) * $increment;
            case 'nearest_1000':
                return round($price / $increment) * $increment;
            default:
                return round($price, 2);
        }
    }

    // Update domain pricing in WHMCS
    public function updateDomainPricing($tld, $new_price, $action = 'auto', $user = 'system') {
        $current = Capsule::table('tbldomainpricing')
            ->where('extension', $tld)
            ->first();

        if (!$current) {
            return false;
        }

        $old_price = $current->register;

        // Update pricing
        $updated = Capsule::table('tbldomainpricing')
            ->where('extension', $tld)
            ->update([
                'register' => $new_price,
                'renew' => $new_price, // Optional: update renewal too
            ]);

        if ($updated) {
            // Log the update
            Capsule::table('rdash_domain_logs')
                ->insert([
                    'tld' => $tld,
                    'old_price' => $old_price,
                    'new_price' => $new_price,
                    'action' => $action,
                    'updated_by' => $user,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
        }

        return $updated;
    }

    // Sync all domains
    public function syncAllDomains($action = 'auto', $user = 'system') {
        $settings = $this->getSettings();
        $api_data = $this->getApiData();
        $whmcs_tlds = $this->getWhmcsTlds();

        $updates = [];

        foreach ($whmcs_tlds as $tld => $current_price) {
            if (isset($api_data[$tld])) {
                $new_price = $this->calculatePrice($api_data[$tld], $settings);

                if (abs($new_price - $current_price) > 0.01) { // Only update if different
                    $this->updateDomainPricing($tld, $new_price, $action, $user);
                    $updates[] = [
                        'tld' => $tld,
                        'old_price' => $current_price,
                        'new_price' => $new_price
                    ];
                }
            }
        }

        return $updates;
    }
}
```


## 2. Halaman Admin (`admin/rdash_domain_updater.php`)

```php
<?php

use WHMCS\Database\Capsule;

define('ADMINAREA', true);

require_once '../init.php';
require_once '../includes/classes/RdashDomainUpdater.php';

$whmcs->load_function('gateway');
$whmcs->load_function('invoice');

// Check admin authentication
if (!isset($_SESSION['adminid'])) {
    header('Location: login.php');
    exit;
}

$updater = new RdashDomainUpdater();
$message = '';
$messageType = 'info';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_settings':
                $settings = [
                    'margin_type' => $_POST['margin_type'],
                    'profit_margin' => floatval($_POST['profit_margin']),
                    'rounding_rule' => $_POST['rounding_rule'],
                    'custom_rounding' => intval($_POST['custom_rounding']),
                    'auto_update' => isset($_POST['auto_update']) ? 1 : 0
                ];

                if ($updater->updateSettings($settings)) {
                    $message = 'Settings updated successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to update settings.';
                    $messageType = 'error';
                }
                break;

            case 'manual_sync':
                try {
                    $updates = $updater->syncAllDomains('manual', $_SESSION['adminusername']);
                    $message = count($updates) . ' domain(s) updated successfully!';
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'Sync failed: ' . $e->getMessage();
                    $messageType = 'error';
                }
                break;

            case 'refresh_cache':
                try {
                    $updater->getApiData(true);
                    $message = 'API cache refreshed successfully!';
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'Cache refresh failed: ' . $e->getMessage();
                    $messageType = 'error';
                }
                break;
        }
    }
}

// Get current data
$settings = $updater->getSettings();
$api_data = [];
$whmcs_tlds = [];

try {
    $api_data = $updater->getApiData();
    $whmcs_tlds = $updater->getWhmcsTlds();
} catch (Exception $e) {
    $message = 'Error loading data: ' . $e->getMessage();
    $messageType = 'error';
}

// Prepare comparison data
$comparison_data = [];
foreach ($whmcs_tlds as $tld => $current_price) {
    if (isset($api_data[$tld])) {
        $new_price = $updater->calculatePrice($api_data[$tld], $settings);
        $comparison_data[] = [
            'tld' => $tld,
            'existing' => true,
            'reg_period' => '1 Year',
            'promo_base' => number_format($api_data[$tld], 2),
            'current_price' => number_format($current_price, 2),
            'calculated_price' => number_format($new_price, 2),
            'margin' => $settings['profit_margin'] . '%',
            'needs_update' => abs($new_price - $current_price) > 0.01
        ];
    }
}

$adminuser = isset($_SESSION['adminusername']) ? $_SESSION['adminusername'] : 'Admin';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RDASH Domain Price Updater - WHMCS Admin</title>

    <link href="templates/blend/css/all.min.css?v=<?php echo $versionHash ?>" rel="stylesheet">
    <link href="templates/blend/css/theme.min.css?v=<?php echo $versionHash ?>" rel="stylesheet">

    <style>
        .update-needed { background-color: #fff3cd !important; }
        .existing-tld { color: #28a745; }
        .price-comparison { font-weight: bold; }
        .settings-panel { background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>

<body class="blend">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">
                            <i class="fas fa-globe"></i>
                            RDASH Domain Price Updater
                        </h3>
                    </div>
                    <div class="panel-body">

                        <?php if ($message): ?>
                        <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : ($messageType === 'success' ? 'success' : 'info'); ?>">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                        <?php endif; ?>

                        <!-- Settings Panel -->
                        <div class="settings-panel">
                            <h4><i class="fas fa-cog"></i> Settings</h4>
                            <form method="post" class="form-horizontal">
                                <input type="hidden" name="action" value="update_settings">

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="col-sm-4 control-label">Margin Type</label>
                                            <div class="col-sm-8">
                                                <select name="margin_type" class="form-control">
                                                    <option value="percentage" <?php echo $settings['margin_type'] === 'percentage' ? 'selected' : ''; ?>>Percentage</option>
                                                    <option value="fixed" <?php echo $settings['margin_type'] === 'fixed' ? 'selected' : ''; ?>>Fixed Amount</option>
                                                </select>
                                                <small class="help-block">Type of margin calculation</small>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="col-sm-4 control-label">Profit Margin</label>
                                            <div class="col-sm-8">
                                                <input type="number" name="profit_margin" step="0.01" class="form-control"
                                                       value="<?php echo $settings['profit_margin']; ?>">
                                                <small class="help-block">Profit margin value (percentage or fixed amount)</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="col-sm-4 control-label">Rounding Rule</label>
                                            <div class="col-sm-8">
                                                <select name="rounding_rule" class="form-control">
                                                    <option value="up_1000" <?php echo $settings['rounding_rule'] === 'up_1000' ? 'selected' : ''; ?>>Round Up</option>
                                                    <option value="down_1000" <?php echo $settings['rounding_rule'] === 'down_1000' ? 'selected' : ''; ?>>Round Down</option>
                                                    <option value="nearest_1000" <?php echo $settings['rounding_rule'] === 'nearest_1000' ? 'selected' : ''; ?>>Round Nearest</option>
                                                </select>
                                                <small class="help-block">Price rounding rules</small>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="col-sm-4 control-label">Custom Rounding</label>
                                            <div class="col-sm-8">
                                                <input type="number" name="custom_rounding" class="form-control"
                                                       value="<?php echo $settings['custom_rounding']; ?>">
                                                <small class="help-block">Custom rounding increment (only if custom rounding selected)</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <div class="col-sm-offset-2 col-sm-10">
                                        <div class="checkbox">
                                            <label>
                                                <input type="checkbox" name="auto_update" <?php echo $settings['auto_update'] ? 'checked' : ''; ?>>
                                                Enable automatic daily price updates
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <div class="col-sm-offset-2 col-sm-10">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Save Settings
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Action Buttons -->
                        <div class="row" style="margin-bottom: 20px;">
                            <div class="col-md-12">
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="action" value="manual_sync">
                                    <button type="submit" class="btn btn-success" onclick="return confirm('Are you sure you want to sync all domain prices?')">
                                        <i class="fas fa-sync"></i> Manual Sync Now
                                    </button>
                                </form>

                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="action" value="refresh_cache">
                                    <button type="submit" class="btn btn-info">
                                        <i class="fas fa-refresh"></i> Refresh API Cache
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Domain Pricing Table -->
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" id="checkAll"></th>
                                        <th>TLD</th>
                                        <th>Existing TLD</th>
                                        <th>Reg Period</th>
                                        <th>Promo Base (IDR)</th>
                                        <th>Current Price (IDR)</th>
                                        <th>Calculated Price (IDR)</th>
                                        <th>Margin</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($comparison_data as $item): ?>
                                    <tr class="<?php echo $item['needs_update'] ? 'update-needed' : ''; ?>">
                                        <td><input type="checkbox" name="selected_tlds[]" value="<?php echo htmlspecialchars($item['tld']); ?>"></td>
                                        <td><strong><?php echo htmlspecialchars($item['tld']); ?></strong></td>
                                        <td>
                                            <i class="fas fa-check existing-tld" title="Exists in WHMCS"></i>
                                        </td>
                                        <td><?php echo $item['reg_period']; ?></td>
                                        <td><?php echo $item['promo_base']; ?></td>
                                        <td class="price-comparison"><?php echo $item['current_price']; ?></td>
                                        <td class="price-comparison"><?php echo $item['calculated_price']; ?></td>
                                        <td><?php echo $item['margin']; ?></td>
                                        <td>
                                            <?php if ($item['needs_update']): ?>
                                                <span class="label label-warning">Update Needed</span>
                                            <?php else: ?>
                                                <span class="label label-success">Up to Date</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="text-muted small">
                            <p><strong>Last API Update:</strong>
                                <?php
                                $last_cache = Capsule::table('rdash_domain_cache')->first();
                                echo $last_cache ? $last_cache->cached_at : 'Never';
                                ?>
                            </p>
                            <p><strong>Total TLDs in WHMCS:</strong> <?php echo count($whmcs_tlds); ?></p>
                            <p><strong>Total TLDs from API:</strong> <?php echo count($api_data); ?></p>
                            <p><strong>Matching TLDs:</strong> <?php echo count($comparison_data); ?></p>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Check all functionality
        document.getElementById('checkAll').addEventListener('change', function() {
            var checkboxes = document.querySelectorAll('input[name="selected_tlds[]"]');
            for (var i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = this.checked;
            }
        });
    </script>
</body>
</html>
```


## 3. Cron Job Setup

### Hook untuk Auto-Update (`includes/hooks/rdash_domain_cron.php`)

```php
<?php

use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once ROOTDIR . '/includes/classes/RdashDomainUpdater.php';

add_hook('AfterCronJob', 1, function($vars) {
    // Run every 4 hours (check if it's time)
    $last_run = get_query_val('tbladdonmodules', 'value', array('module' => 'rdash_domain', 'setting' => 'last_auto_update'));
    $current_time = time();

    // 4 hours = 14400 seconds
    if (!$last_run || ($current_time - $last_run) >= 14400) {
        try {
            $updater = new RdashDomainUpdater();
            $settings = $updater->getSettings();

            // Only run if auto update is enabled
            if ($settings['auto_update']) {
                $updates = $updater->syncAllDomains('auto', 'cron');

                // Log to WHMCS activity log
                logActivity('RDASH Domain Updater: ' . count($updates) . ' domain(s) updated automatically.');
            }

            // Update last run time
            if (!$last_run) {
                Capsule::table('tbladdonmodules')->insert([
                    'module' => 'rdash_domain',
                    'setting' => 'last_auto_update',
                    'value' => $current_time
                ]);
            } else {
                Capsule::table('tbladdonmodules')
                    ->where('module', 'rdash_domain')
                    ->where('setting', 'last_auto_update')
                    ->update(['value' => $current_time]);
            }

        } catch (Exception $e) {
            logActivity('RDASH Domain Updater Error: ' . $e->getMessage());
        }
    }
});
```


## 4. Menu Admin (`includes/hooks/rdash_admin_menu.php`)

```php
<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

add_hook('AdminAreaHeaderOutput', 1, function($vars) {
    return '
    <script>
    jQuery(document).ready(function() {
        jQuery("#Menu-Addons").append(\'<li><a href="rdash_domain_updater.php"><i class="fas fa-globe"></i> Domain Price Updater</a></li>\');
    });
    </script>';
});
```


## 5. Instalasi dan Setup

1. **Upload semua file** ke direktori WHMCS
2. **Jalankan SQL queries** untuk membuat tabel
3. **Set up cron job** WHMCS (jika belum ada) untuk jalan setiap 5 menit[^1][^2]
4. **Akses halaman** melalui `admin/rdash_domain_updater.php`

### Cron Command untuk Server

```bash
*/5 * * * * php -q /path/to/whmcs/crons/cron.php
```


## Features yang Sudah Include:

✅ **Layout sesuai gambar ke-2** - tabel dengan kolom TLD, Existing TLD, Reg Period, dll
✅ **Settings panel sesuai gambar ke-3** - margin type, profit margin, rounding rules
✅ **Auto update tiap 4 jam** - via WHMCS cron hook
✅ **Cache system** - API data di-cache untuk performance
✅ **Manual sync** - tombol untuk sync manual
✅ **Logging system** - track semua perubahan harga
✅ **Error handling** - handle API errors dengan baik
✅ **Admin integration** - masuk ke menu admin WHMCS

Sistem ini akan secara otomatis compare harga dari API RDASH dengan yang ada di WHMCS, dan hanya update domain yang memang berbeda harganya. Cache digunakan untuk mengurangi API calls, dan ada logging lengkap untuk tracking semua perubahan.
<span style="display:none">[^10][^11][^12][^13][^14][^15][^16][^17][^3][^4][^5][^6][^7][^8][^9]</span>

<div style="text-align: center">⁂</div>

[^1]: https://help.whmcs.com/m/installation/l/1075205-setting-up-the-whmcs-cron-job

[^2]: https://docs.whmcs.com/8-13/system/automation/system-cron/

[^3]: image.jpg

[^4]: image.jpg

[^5]: image.jpg

[^6]: https://docs.whmcs.com/8-13/system/automation/cron-tutorials/configure-the-system-cron-job/

[^7]: https://www.youtube.com/watch?v=IbarNnKvx0k

[^8]: https://help.whmcs.com/m/installation/l/678187-configuring-when-the-whmcs-cron-runs

[^9]: https://developers.whmcs.com/advanced/creating-pages/

[^10]: https://autovative.wordpress.com/2023/07/06/optimizing-whmcs-for-enhanced-performance/

[^11]: https://developers.whmcs.com/hooks-reference/cron/

[^12]: https://katamaze.com/docs/billing-extension/65/whmcs-auto-update-domain-pricing-based-on-registrars-039-costs

[^13]: https://www.liquidweb.com/blog/configuring-and-troubleshooting-whmcs-crons/

[^14]: https://www.inmotionhosting.com/support/edu/whm/how-to-create-a-custom-page-for-whmcs/

[^15]: https://requests.whmcs.com/idea/whmcs-to-use-caching

[^16]: https://requests.whmcs.com/idea/add-api-for-domain-pricing-updates

[^17]: https://docs.whmcs.com/8-13/troubleshooting/troubleshoot-php/opcache-warnings/


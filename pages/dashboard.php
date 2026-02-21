<?php
/**
 * RDAS Pricing Updater - Dashboard Page
 *
 * @package RDAS Pricing Updater
 * @version 2.0.0
 * @author Sadewa
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once dirname(__DIR__) . '/lib/functions.php';

// IDE compatibility - suppress undefined type warnings
if (false) {
    /** @var \WHMCS\Database\Capsule\Manager */
    class Manager {}
}

/**
 * Show Dashboard Page
 *
 * @param array $vars Template variables
 * @return string HTML output
 */
function showDashboardPage($vars) {
    try {
        // Get addon statistics
        $stats = getDashboardStatistics();

        // Get recent activity logs
        $recent_logs = rdasGetAddonLogs(1, 10, '');

        // Get API status
        $api_status = checkApiStatus();

        // Get pricing summary
        $pricing_summary = getPricingSummary();

        // Get system health
        $system_health = getSystemHealth();

        // Prepare template variables
        $template_vars = array(
            'modulelink' => $vars['modulelink'],
            'stats' => $stats,
            'recent_logs' => $recent_logs,
            'api_status' => $api_status,
            'pricing_summary' => $pricing_summary,
            'system_health' => $system_health,
            'csrf_token' => rdasGenerateCSRFToken('dashboard')
        );

        // Load and return template
        return renderDashboardTemplate($template_vars);

    } catch (Exception $e) {
        // Log error and show fallback dashboard
        if (function_exists('logActivity')) {
            logActivity('RDAS Pricing Updater Dashboard Error: ' . $e->getMessage());
        }

        // Return simple dashboard as fallback
        return showSimpleDashboard($vars);
    }
}

/**
 * Get dashboard statistics
 *
 * @return array Statistics data
 */
function getDashboardStatistics() {
    $stats = array(
        'total_domains' => 0,
        'updated_today' => 0,
        'pending_updates' => 0,
        'last_sync' => 'Never',
        'sync_status' => 'idle',
        'total_tlds' => 0,
        'active_registrars' => 0,
        'cache_size' => 0
    );

    try {
        // Get total domains from WHMCS
        if (class_exists('WHMCS\Database\Capsule\Manager')) {
            $stats['total_domains'] = \WHMCS\Database\Capsule\Manager::table('tbldomainpricing')->count();
            $stats['total_tlds'] = \WHMCS\Database\Capsule\Manager::table('tbldomainpricing')
                ->distinct()
                ->count('extension');
            $stats['active_registrars'] = \WHMCS\Database\Capsule\Manager::table('tblregistrars')
                ->where('setting', 'TestMode')
                ->where('value', '!=', 'on')
                ->count();
        } else {
            // Legacy fallback
            $result = select_query('tbldomainpricing', 'COUNT(*) as total', '');
            if ($result && function_exists('mysql_fetch_array')) {
                $data = mysql_fetch_array($result);
                $stats['total_domains'] = $data['total'];
            }
        }

        // Get updates today
        $today = date('Y-m-d');
        $log_result = select_query('mod_rdas_pricing_updater_log', 'COUNT(*) as total',
            "message LIKE '%price update%' AND DATE(date) = '$today'");
        if ($log_result && function_exists('mysql_fetch_array')) {
            $data = mysql_fetch_array($log_result);
            $stats['updated_today'] = ($data && isset($data['total'])) ? $data['total'] : 0;
        }

        // Get last sync time
        $last_sync_result = select_query('mod_rdas_pricing_updater_log', 'date',
            "message LIKE '%sync%'", 'date', 'DESC', '0,1');
        if ($last_sync_result && function_exists('mysql_fetch_array')) {
            $data = mysql_fetch_array($last_sync_result);
            $stats['last_sync'] = ($data && isset($data['date'])) ? date('Y-m-d H:i:s', strtotime($data['date'])) : 'Never';
        }

        // Get cache size
        $cache_result = select_query('mod_rdas_pricing_cache', 'COUNT(*) as total', '');
        if ($cache_result && function_exists('mysql_fetch_array')) {
            $data = mysql_fetch_array($cache_result);
            $stats['cache_size'] = ($data && isset($data['total'])) ? $data['total'] : 0;
        }

    } catch (Exception $e) {
        // Log error but continue with default stats
        if (function_exists('logActivity')) {
            logActivity('Error getting addon statistics: ' . $e->getMessage());
        }
    }

    return $stats;
}

/**
 * Check API status
 *
 * @return array API status information
 */
function checkApiStatus() {
    $status = array(
        'status' => 'unknown',
        'response_time' => 0,
        'last_check' => date('Y-m-d H:i:s'),
        'error_message' => ''
    );

    try {
        $start_time = microtime(true);

        // Get API configuration
        $config = rdasGetAddonConfig('rdas_pricing_updater');
        $api_url = $config['api_url'] ?? '';
        $api_key = $config['api_key'] ?? '';

        if (empty($api_url)) {
            $status['status'] = 'not_configured';
            $status['error_message'] = 'API URL not configured';
            return $status;
        }

        // Test API connection - just check if we can reach the API
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: WHMCS-RDAS-Pricing-Updater/2.1.9'
        ));

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        $end_time = microtime(true);
        $status['response_time'] = round(($end_time - $start_time) * 1000, 2); // in milliseconds

        if ($curl_error) {
            $status['status'] = 'error';
            $status['error_message'] = $curl_error;
        } elseif ($http_code == 200) {
            // Verify response is valid JSON with domain data
            $json = json_decode($response, true);
            if (is_array($json) && count($json) > 0) {
                $status['status'] = 'online';
            } else {
                $status['status'] = 'error';
                $status['error_message'] = 'Invalid API response';
            }
        } elseif ($http_code == 401 || $http_code == 403) {
            $status['status'] = 'unauthorized';
            $status['error_message'] = 'Access denied';
        } else {
            $status['status'] = 'error';
            $status['error_message'] = 'HTTP ' . $http_code;
        }

    } catch (Exception $e) {
        $status['status'] = 'error';
        $status['error_message'] = $e->getMessage();
    }

    return $status;
}

/**
 * Get pricing summary
 *
 * @return array Pricing summary data
 */
function getPricingSummary() {
    $summary = array(
        'total_tlds' => 0,
        'avg_register_price' => 0,
        'avg_renew_price' => 0,
        'highest_price' => 0,
        'lowest_price' => 0,
        'price_range' => array(),
        'popular_tlds' => array()
    );

    try {
        $register_prices = array();
        $renew_prices = array();
        $popular_tlds = array('.com', '.net', '.org', '.id', '.co.id');

        // WHMCS 8/9 - use tblpricing
        $domains = select_query('tbldomainpricing', 'id, extension', '', 'extension', 'ASC');

        while ($domain = mysql_fetch_array($domains)) {
            $domainId = intval($domain['id']);

            // Get register price from tblpricing
            $regResult = select_query('tblpricing', 'msetupfee', [
                'type' => 'domainregister',
                'currency' => 1,
                'relid' => $domainId
            ]);
            $register = 0;
            if ($regRow = mysql_fetch_array($regResult)) {
                $register = floatval($regRow['msetupfee']);
            }

            // Get renew price from tblpricing
            $renewResult = select_query('tblpricing', 'msetupfee', [
                'type' => 'domainrenew',
                'currency' => 1,
                'relid' => $domainId
            ]);
            $renew = 0;
            if ($renewRow = mysql_fetch_array($renewResult)) {
                $renew = floatval($renewRow['msetupfee']);
            }

            if ($register > 0) {
                $register_prices[] = $register;
                $renew_prices[] = $renew;

                if (in_array($domain['extension'], $popular_tlds)) {
                    $summary['popular_tlds'][$domain['extension']] = array(
                        'register' => $register,
                        'renew' => $renew
                    );
                }
            }
        }

        if (!empty($register_prices)) {
            $summary['total_tlds'] = count($register_prices);
            $summary['avg_register_price'] = array_sum($register_prices) / count($register_prices);
            $summary['avg_renew_price'] = array_sum($renew_prices) / count($renew_prices);
            $summary['highest_price'] = max($register_prices);
            $summary['lowest_price'] = min($register_prices);

            // Price range distribution
            $summary['price_range'] = array(
                '0-100k' => 0,
                '100k-500k' => 0,
                '500k-1m' => 0,
                '1m+' => 0
            );

            foreach ($register_prices as $price) {
                if ($price <= 100000) {
                    $summary['price_range']['0-100k']++;
                } elseif ($price <= 500000) {
                    $summary['price_range']['100k-500k']++;
                } elseif ($price <= 1000000) {
                    $summary['price_range']['500k-1m']++;
                } else {
                    $summary['price_range']['1m+']++;
                }
            }
        }
    } catch (Exception $e) {
        if (function_exists('logActivity')) {
            logActivity('Error getting pricing summary: ' . $e->getMessage());
        }
    }

    return $summary;
}

/**
 * Get system health information
 *
 * @return array System health data
 */
function getSystemHealth() {
    $health = array(
        'php_version' => PHP_VERSION,
        'curl_enabled' => function_exists('curl_init'),
        'database_connection' => true,
        'addon_tables' => array(),
        'permissions' => array(),
        'memory_usage' => memory_get_usage(true),
        'memory_limit' => ini_get('memory_limit')
    );

    try {
        // Check addon tables
        $required_tables = array(
            'mod_rdas_pricing_updater_log',
            'mod_rdas_pricing_cache'
        );

        foreach ($required_tables as $table) {
            $result = full_query("SHOW TABLES LIKE '$table'");
            $health['addon_tables'][$table] = ($result && mysql_fetch_array($result));
        }

        // Check file permissions
        $check_paths = array(
            dirname(__DIR__) . '/lib' => 'readable',
            dirname(__DIR__) . '/templates' => 'readable',
            dirname(__DIR__) . '/assets' => 'readable'
        );

        foreach ($check_paths as $path => $permission) {
            $health['permissions'][$path] = is_readable($path);
        }

    } catch (Exception $e) {
        if (function_exists('logActivity')) {
            logActivity('Error checking system health: ' . $e->getMessage());
        }
    }

    return $health;
}

/**
 * Render dashboard template
 *
 * @param array $vars Template variables
 * @return string Rendered HTML
 */
function renderDashboardTemplate($vars) {
    ob_start();
    ?>
    <div class="rdas-pricing-updater">
        <!-- Page Header -->
        <div class="rdas-page-header">
            <h1 class="rdas-page-title">
                <div class="rdas-page-title-icon">
                    <i class="fa fa-tags"></i>
                </div>
                Dashboard
            </h1>
            <div class="rdas-page-actions">
                <button class="rdas-btn rdas-btn-ghost rdas-theme-toggle" title="Toggle Dark Mode">
                    <i class="fa fa-moon"></i>
                </button>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <nav class="rdas-nav-tabs">
            <a href="<?php echo $vars['modulelink']; ?>&page=dashboard" class="rdas-nav-tab active">
                <i class="fa fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="<?php echo $vars['modulelink']; ?>&page=pricing" class="rdas-nav-tab">
                <i class="fa fa-list-alt"></i>
                <span>Pricing</span>
            </a>
            <a href="<?php echo $vars['modulelink']; ?>&page=settings" class="rdas-nav-tab">
                <i class="fa fa-cog"></i>
                <span>Settings</span>
            </a>
            <a href="<?php echo $vars['modulelink']; ?>&page=api_test" class="rdas-nav-tab">
                <i class="fa fa-plug"></i>
                <span>API Test</span>
            </a>
            <a href="<?php echo $vars['modulelink']; ?>&page=logs" class="rdas-nav-tab">
                <i class="fa fa-history"></i>
                <span>Logs</span>
            </a>
        </nav>

        <!-- Quick Actions -->
        <div class="rdas-quick-actions">
            <button class="rdas-btn rdas-btn-primary" id="sync-all-prices">
                <i class="fa fa-sync"></i>
                Sync All Prices
            </button>
            <button class="rdas-btn rdas-btn-secondary" id="test-api">
                <i class="fa fa-plug"></i>
                Test API Connection
            </button>
            <a href="<?php echo $vars['modulelink']; ?>&page=pricing" class="rdas-btn rdas-btn-outline">
                <i class="fa fa-list-alt"></i>
                Manage Pricing
            </a>
        </div>

        <!-- Statistics Grid -->
        <div class="rdas-stats-grid">
            <div class="rdas-stat-card">
                <div class="rdas-stat-icon">
                    <i class="fa fa-globe"></i>
                </div>
                <div class="rdas-stat-value" id="rdas-total-domains"><?php echo number_format($vars['stats']['total_domains']); ?></div>
                <div class="rdas-stat-label">Total Domains</div>
            </div>

            <div class="rdas-stat-card promo">
                <div class="rdas-stat-icon">
                    <i class="fa fa-tag"></i>
                </div>
                <div class="rdas-stat-value"><?php echo number_format($vars['stats']['active_promos'] ?? 0); ?></div>
                <div class="rdas-stat-label">Active Promos</div>
            </div>

            <div class="rdas-stat-card success">
                <div class="rdas-stat-icon">
                    <i class="fa fa-check-circle"></i>
                </div>
                <div class="rdas-stat-value"><?php echo number_format($vars['stats']['updated_today'] ?? 0); ?></div>
                <div class="rdas-stat-label">Updated Today</div>
            </div>

            <div class="rdas-stat-card">
                <div class="rdas-stat-icon">
                    <i class="fa fa-database"></i>
                </div>
                <div class="rdas-stat-value"><?php echo number_format($vars['stats']['cache_size'] ?? 0); ?></div>
                <div class="rdas-stat-label">Cached Entries</div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="rdas-flex rdas-gap-3" style="flex-wrap: wrap;">
            <!-- API Status Card -->
            <div class="rdas-card" style="flex: 1; min-width: 300px;">
                <div class="rdas-card-header">
                    <h3 class="rdas-card-title">
                        <i class="fa fa-cloud"></i>
                        API Status
                    </h3>
                    <button class="rdas-btn rdas-btn-sm rdas-btn-ghost" onclick="RDAS.loadDashboardStats()">
                        <i class="fa fa-refresh"></i>
                    </button>
                </div>
                <div class="rdas-card-body" id="rdas-api-status">
                    <?php
                    $status_class = $vars['api_status']['status'] === 'online' ? 'rdas-status-active' :
                                   ($vars['api_status']['status'] === 'error' ? 'rdas-status-error' : 'rdas-status-inactive');
                    ?>
                    <div class="rdas-flex-between rdas-mb-2">
                        <span>Status</span>
                        <span class="rdas-status <?php echo $status_class; ?>">
                            <?php echo ucfirst($vars['api_status']['status']); ?>
                        </span>
                    </div>
                    <?php if ($vars['api_status']['response_time'] > 0): ?>
                    <div class="rdas-flex-between rdas-mb-2">
                        <span>Response Time</span>
                        <span class="rdas-price"><?php echo $vars['api_status']['response_time']; ?>ms</span>
                    </div>
                    <?php endif; ?>
                    <div class="rdas-flex-between">
                        <span>Last Check</span>
                        <span class="rdas-text-muted"><?php echo $vars['api_status']['last_check']; ?></span>
                    </div>
                    <?php if (!empty($vars['api_status']['error_message'])): ?>
                    <div class="rdas-toast rdas-toast-error rdas-mt-2" style="position: static; animation: none;">
                        <div class="rdas-toast-message"><?php echo htmlspecialchars($vars['api_status']['error_message']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- System Health Card -->
            <div class="rdas-card" style="flex: 1; min-width: 300px;">
                <div class="rdas-card-header">
                    <h3 class="rdas-card-title">
                        <i class="fa fa-heartbeat"></i>
                        System Health
                    </h3>
                </div>
                <div class="rdas-card-body">
                    <div class="rdas-flex-between rdas-mb-2">
                        <span>PHP Version</span>
                        <span class="rdas-price"><?php echo $vars['system_health']['php_version']; ?></span>
                    </div>
                    <div class="rdas-flex-between rdas-mb-2">
                        <span>cURL Extension</span>
                        <span class="rdas-status <?php echo $vars['system_health']['curl_enabled'] ? 'rdas-status-active' : 'rdas-status-error'; ?>">
                            <?php echo $vars['system_health']['curl_enabled'] ? 'Enabled' : 'Disabled'; ?>
                        </span>
                    </div>
                    <div class="rdas-flex-between rdas-mb-2">
                        <span>Memory Usage</span>
                        <span class="rdas-price"><?php echo round($vars['system_health']['memory_usage'] / 1024 / 1024, 2); ?>MB</span>
                    </div>
                    <div class="rdas-mt-2">
                        <span class="rdas-text-muted" style="font-size: 0.75rem;">Database Tables</span>
                        <div class="rdas-flex rdas-gap-1 rdas-mt-1" style="flex-wrap: wrap;">
                            <?php foreach($vars['system_health']['addon_tables'] as $table => $exists): ?>
                            <span class="rdas-status <?php echo $exists ? 'rdas-status-active' : 'rdas-status-error'; ?>" style="font-size: 0.6875rem;">
                                <?php echo $exists ? '✓' : '✗'; ?> <?php echo str_replace('mod_rdas_', '', $table); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Last Sync Card -->
            <div class="rdas-card" style="flex: 1; min-width: 300px;">
                <div class="rdas-card-header">
                    <h3 class="rdas-card-title">
                        <i class="fa fa-clock"></i>
                        Last Sync
                    </h3>
                </div>
                <div class="rdas-card-body rdas-text-center">
                    <div class="rdas-stat-value" style="font-size: 1.25rem;">
                        <?php echo $vars['stats']['last_sync']; ?>
                    </div>
                    <div class="rdas-stat-label rdas-mt-1">
                        <?php
                        $total_tlds = $vars['stats']['total_tlds'] ?? 0;
                        echo $total_tlds . ' TLDs monitored';
                        ?>
                    </div>
                    <div class="rdas-mt-2">
                        <span class="rdas-status rdas-status-active">
                            <i class="fa fa-check"></i> System Ready
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pricing Summary & Popular TLDs -->
        <div class="rdas-flex rdas-gap-3 rdas-mt-3" style="flex-wrap: wrap;">
            <!-- Pricing Summary -->
            <div class="rdas-card" style="flex: 2; min-width: 400px;">
                <div class="rdas-card-header">
                    <h3 class="rdas-card-title">
                        <i class="fa fa-bar-chart"></i>
                        Pricing Summary
                    </h3>
                </div>
                <div class="rdas-card-body">
                    <?php if ($vars['pricing_summary']['total_tlds'] > 0): ?>
                    <div class="rdas-flex rdas-gap-3" style="flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 150px;">
                            <div class="rdas-text-muted rdas-mb-1">Avg Register</div>
                            <div class="rdas-price">Rp <?php echo number_format($vars['pricing_summary']['avg_register_price']); ?></div>
                        </div>
                        <div style="flex: 1; min-width: 150px;">
                            <div class="rdas-text-muted rdas-mb-1">Avg Renew</div>
                            <div class="rdas-price">Rp <?php echo number_format($vars['pricing_summary']['avg_renew_price']); ?></div>
                        </div>
                        <div style="flex: 1; min-width: 150px;">
                            <div class="rdas-text-muted rdas-mb-1">Highest</div>
                            <div class="rdas-price">Rp <?php echo number_format($vars['pricing_summary']['highest_price']); ?></div>
                        </div>
                        <div style="flex: 1; min-width: 150px;">
                            <div class="rdas-text-muted rdas-mb-1">Lowest</div>
                            <div class="rdas-price rdas-text-promo">Rp <?php echo number_format($vars['pricing_summary']['lowest_price']); ?></div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="rdas-empty">
                        <div class="rdas-empty-icon">
                            <i class="fa fa-bar-chart"></i>
                        </div>
                        <div class="rdas-empty-title">No Pricing Data</div>
                        <div class="rdas-empty-text">Sync prices from API to see summary statistics</div>
                        <button class="rdas-btn rdas-btn-primary" id="sync-prices-empty">
                            <i class="fa fa-sync"></i> Sync Now
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Popular TLDs -->
            <div class="rdas-card" style="flex: 1; min-width: 250px;">
                <div class="rdas-card-header">
                    <h3 class="rdas-card-title">
                        <i class="fa fa-star"></i>
                        Popular TLDs
                    </h3>
                </div>
                <div class="rdas-card-body">
                    <?php if (!empty($vars['pricing_summary']['popular_tlds'])): ?>
                    <?php foreach($vars['pricing_summary']['popular_tlds'] as $tld => $prices): ?>
                    <div class="rdas-flex-between rdas-mb-2" style="padding: var(--rdas-space-sm) 0; border-bottom: 1px solid var(--rdas-border-light);">
                        <span class="rdas-tld"><?php echo $tld; ?></span>
                        <div class="rdas-text-right">
                            <div class="rdas-price" style="font-size: 0.875rem;">Rp <?php echo number_format($prices['register']); ?></div>
                            <div class="rdas-text-muted" style="font-size: 0.6875rem;">/year</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div class="rdas-empty" style="padding: var(--rdas-space-lg);">
                        <div class="rdas-text-muted">No popular TLDs data</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="rdas-card rdas-mt-3">
            <div class="rdas-card-header">
                <h3 class="rdas-card-title">
                    <i class="fa fa-history"></i>
                    Recent Activity
                </h3>
                <a href="<?php echo $vars['modulelink']; ?>&page=logs" class="rdas-btn rdas-btn-sm rdas-btn-secondary">
                    <i class="fa fa-list"></i> View All
                </a>
            </div>
            <div class="rdas-table-wrapper">
                <table class="rdas-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Level</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($vars['recent_logs']['logs'])): ?>
                            <?php foreach($vars['recent_logs']['logs'] as $log): ?>
                            <tr>
                                <td><small><?php echo isset($log['date']) ? $log['date'] : date('Y-m-d H:i:s'); ?></small></td>
                                <td>
                                    <span class="rdas-status rdas-status-<?php echo $log['level'] ?? 'info'; ?>">
                                        <?php echo ucfirst($log['level'] ?? 'info'); ?>
                                    </span>
                                </td>
                                <td><?php echo isset($log['message']) ? htmlspecialchars($log['message']) : 'No message'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="rdas-text-center rdas-text-muted">No recent activity</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    // Initialize dashboard
    jQuery(document).ready(function($) {
        // Handle sync all prices
        $('#sync-all-prices, #sync-prices-empty').on('click', function() {
            var btn = $(this);
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Syncing...');

            $.ajax({
                url: 'rdas_pricing_updater.php',
                method: 'POST',
                data: {
                    action: 'sync_all_prices',
                    csrf_token: '<?php echo $vars['csrf_token']; ?>'
                },
                success: function(response) {
                    if (response.success) {
                        if (typeof RDAS !== 'undefined' && RDAS.showToast) {
                            RDAS.showToast('success', 'Sync Complete', response.message || 'All prices synced successfully');
                        } else {
                            alert(response.message || 'All prices synced successfully');
                        }
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        if (typeof RDAS !== 'undefined' && RDAS.showToast) {
                            RDAS.showToast('error', 'Sync Failed', response.message || 'Unable to sync prices');
                        } else {
                            alert(response.message || 'Unable to sync prices');
                        }
                    }
                },
                error: function() {
                    if (typeof RDAS !== 'undefined' && RDAS.showToast) {
                        RDAS.showToast('error', 'Error', 'Request failed. Please try again.');
                    } else {
                        alert('Request failed. Please try again.');
                    }
                },
                complete: function() {
                    btn.prop('disabled', false).html('<i class="fa fa-sync"></i> Sync All Prices');
                }
            });
        });

        // Handle test API
        $('#test-api').on('click', function() {
            var btn = $(this);
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Testing...');

            $.ajax({
                url: 'rdas_pricing_updater.php',
                method: 'POST',
                data: {
                    action: 'test_api',
                    csrf_token: '<?php echo $vars['csrf_token']; ?>'
                },
                success: function(response) {
                    if (response.success) {
                        if (typeof RDAS !== 'undefined' && RDAS.showToast) {
                            RDAS.showToast('success', 'API Connected', 'Response time: ' + response.response_time + 'ms');
                        } else {
                            alert('API Connected - Response time: ' + response.response_time + 'ms');
                        }
                    } else {
                        if (typeof RDAS !== 'undefined' && RDAS.showToast) {
                            RDAS.showToast('error', 'API Failed', response.message || 'Unable to connect to API');
                        } else {
                            alert(response.message || 'Unable to connect to API');
                        }
                    }
                },
                error: function() {
                    if (typeof RDAS !== 'undefined' && RDAS.showToast) {
                        RDAS.showToast('error', 'Error', 'Request failed. Please try again.');
                    } else {
                        alert('Request failed. Please try again.');
                    }
                },
                complete: function() {
                    btn.prop('disabled', false).html('<i class="fa fa-plug"></i> Test API Connection');
                }
            });
        });

        // Theme toggle
        $('.rdas-theme-toggle').on('click', function() {
            $('.rdas-pricing-updater').toggleClass('rdas-dark');
            var icon = $(this).find('i');
            if ($('.rdas-pricing-updater').hasClass('rdas-dark')) {
                icon.removeClass('fa-moon').addClass('fa-sun');
            } else {
                icon.removeClass('fa-sun').addClass('fa-moon');
            }
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Simple fallback dashboard
 *
 * @param array $vars Template variables
 * @return string HTML output
 */
function showSimpleDashboard($vars) {
    $simple_dashboard_file = dirname(__DIR__) . '/dashboard_simple.php';

    if (file_exists($simple_dashboard_file)) {
        ob_start();
        include $simple_dashboard_file;
        return ob_get_clean();
    }

    return '<div class="alert alert-warning">Dashboard temporarily unavailable. Please check your configuration.</div>';
}

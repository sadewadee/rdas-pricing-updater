<?php
/**
 * RDAS Domain Price Updater
 *
 * Automatically updates domain pricing in WHMCS based on RDASH.ID API
 *
 * @package    RDAS_Pricing_Updater
 * @author     Sadewadee
 * @copyright  2024 MORDEHOST.COM
 * @license    MIT
 * @version 2.2.0
 * @link       https://mordehost.com
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// Include functions library
require_once __DIR__ . '/lib/functions.php';

/**
 * Addon Configuration
 */
function rdas_pricing_updater_config() {
    return array(
        'name' => 'RDAS Domain Price Updater',
        'description' => 'Automatically updates domain pricing in WHMCS based on RDASH.ID API with configurable margin and rounding rules.',
        'version' => '2.2.0',
        'author' => 'Mordenhost',
        'language' => 'english',
        'fields' => array(
            'api_url' => array(
                'FriendlyName' => 'API URL',
                'Type' => 'text',
                'Size' => '50',
                'Default' => 'https://api.rdash.id/api/domain-prices?currency=IDR',
                'Description' => 'RDASH.ID API endpoint for domain prices'
            ),
            'margin_type' => array(
                'FriendlyName' => 'Margin Type',
                'Type' => 'dropdown',
                'Options' => 'percentage,fixed',
                'Default' => 'percentage',
                'Description' => 'Type of margin calculation'
            ),
            'profit_margin' => array(
                'FriendlyName' => 'Profit Margin',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '20',
                'Description' => 'Profit margin value (percentage or fixed amount)'
            ),
            'rounding_rule' => array(
                'FriendlyName' => 'Rounding Rule',
                'Type' => 'dropdown',
                'Options' => 'none,up_1000,up_5000,nearest_1000,custom',
                'Default' => 'up_1000',
                'Description' => 'Price rounding rules'
            ),
            'custom_rounding' => array(
                'FriendlyName' => 'Custom Rounding',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '1000',
                'Description' => 'Custom rounding increment (only if custom rounding selected)'
            ),
            'auto_update' => array(
                'FriendlyName' => 'Auto Update',
                'Type' => 'yesno',
                'Default' => 'on',
                'Description' => 'Enable automatic daily price updates'
            ),
            'log_level' => array(
                'FriendlyName' => 'Log Level',
                'Type' => 'dropdown',
                'Options' => 'error,warning,info,debug',
                'Default' => 'info',
                'Description' => 'Logging level for addon activities'
            )
        )
    );
}

/**
 * Addon Activation
 */
function rdas_pricing_updater_activate() {
    try {
        // Create log table
        $query = "CREATE TABLE IF NOT EXISTS `mod_rdas_pricing_updater_log` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `date` datetime NOT NULL,
            `level` varchar(20) NOT NULL,
            `message` text NOT NULL,
            `data` text,
            PRIMARY KEY (`id`),
            KEY `date` (`date`),
            KEY `level` (`level`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8";

        full_query($query);

        // Create pricing cache table
        $query = "CREATE TABLE IF NOT EXISTS `mod_rdas_pricing_cache` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `extension` varchar(20) NOT NULL,
            `api_data` text NOT NULL,
            `last_updated` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `extension` (`extension`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8";

        full_query($query);

        rdasLogToAddon('info', 'RDAS Pricing Updater activated successfully');

        return [
            'status' => 'success',
            'description' => 'RDAS Pricing Updater activated successfully. Database tables created.'
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'description' => 'Activation failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Addon Deactivation
 */
function rdas_pricing_updater_deactivate() {
    try {
        rdasLogToAddon('info', 'RDAS Pricing Updater deactivated');

        return [
            'status' => 'success',
            'description' => 'RDAS Pricing Updater deactivated successfully.'
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'description' => 'Deactivation failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Addon Output Function - With Page Routing
 */
function rdas_pricing_updater_output($vars) {
    // Handle form POST import request
    if (isset($_POST['action']) && $_POST['action'] === 'import_domains' && isset($_POST['import_selected'])) {
        $selectedTlds = $_POST['selected_tlds'] ?? [];
        if (!empty($selectedTlds)) {
            $result = rdasImportDomains($selectedTlds);
            if ($result['success']) {
                echo '<div class="alert alert-success">Successfully imported ' . $result['imported'] . ' domains</div>';
            } else {
                echo '<div class="alert alert-danger">Import failed: ' . $result['message'] . '</div>';
            }
        } else {
            echo '<div class="alert alert-warning">Please select at least one domain to import</div>';
        }
    }

    // Handle legacy AJAX import request (for backward compatibility)
    if (isset($_POST['action']) && $_POST['action'] === 'import_domains' && !isset($_POST['import_selected'])) {
        header('Content-Type: application/json');
        $domains = json_decode($_POST['domains'], true);
        if (!is_array($domains)) {
            echo json_encode(['success' => false, 'message' => 'Invalid domain data']);
            exit;
        }
        echo json_encode(rdasImportDomains($domains));
        exit;
    }

    // Page routing
    $page = $_REQUEST['page'] ?? 'dashboard';

    // Include and render appropriate page
    $pageFile = __DIR__ . '/pages/' . $page . '.php';

    if (file_exists($pageFile)) {
        require_once $pageFile;

        $pageFunction = 'show' . ucfirst($page) . 'Page';
        if (function_exists($pageFunction)) {
            echo $pageFunction($vars);
        } else {
            // Fallback to legacy output if no page function
            echo renderLegacyOutput($vars);
        }
    } else {
        // Fallback to legacy output
        echo renderLegacyOutput($vars);
    }
}

/**
 * Legacy Output - Direct pricing table
 */
function renderLegacyOutput($vars) {
    try {
        // Get addon configuration
        $config = rdasGetAddonConfig('rdas_pricing_updater');

        // Get WHMCS base currency - simplified approach
        $currency = 'IDR'; // Default currency

        // Fetch pricing data from API
        $apiUrl = $config['api_url'] ?? "https://api.rdash.id/api/domain-prices?currency=" . $currency;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            throw new Exception('Failed to fetch pricing data from API');
        }

        $pricingData = json_decode($response, true);
        if (!$pricingData || !is_array($pricingData)) {
            throw new Exception('Invalid API response format');
        }

        // Get margin and rounding settings
        $marginType = $config['margin_type'] ?? 'percentage';
        $marginValue = floatval($config['profit_margin'] ?? 20);
        $roundingRule = $config['rounding_rule'] ?? 'up_1000';
        $customRounding = floatval($config['custom_rounding'] ?? 1000);

        // Display pricing table with configuration info
        echo '<div class="container-fluid">';
        echo '<div class="row">';
        echo '<div class="col-md-12">';
        echo '<div class="panel panel-default">';
        echo '<div class="panel-heading">';
        echo '<h3 class="panel-title">Domain Pricing - ' . $currency . '</h3>';
        echo '<div class="pull-right">';
        echo '<small>Margin: ' . $marginValue . ($marginType === 'percentage' ? '%' : ' IDR') . ' | Rounding: ' . ucfirst(str_replace('_', ' ', $roundingRule)) . '</small>';
        echo '</div>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<div class="table-responsive">';
        echo '<table class="table table-striped table-bordered">';
        echo '<thead>';
        echo '<tr>';
        echo '<th><input type="checkbox" id="selectAll" title="Select All"> Select</th>';
        echo '<th>TLD</th>';
        echo '<th>Existing TLD</th>';
        echo '<th>Reg Period</th>';
        echo '<th>Promo<br><small>Base</small></th>';
        echo '<th>Margin</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        // Get existing TLDs from WHMCS database
        $existingTLDs = rdasGetExistingTLDs();

        foreach ($pricingData as $domain) {
            $extension = $domain['extension'] ?? '';

            // Only show TLDs that exist in tbldomainpricing
            if (!in_array($extension, $existingTLDs)) {
                continue;
            }

            $type = $domain['type'] ?? '';
            $description = $domain['description'] ?? '';

            // Parse prices from string format (e.g., "Rp45.000" -> 45000)
            $baseRegistration = rdasParsePrice($domain['registration'] ?? '0');
            $baseRenewal = rdasParsePrice($domain['renewal'] ?? '0');
            $baseTransfer = rdasParsePrice($domain['transfer'] ?? '0');
            $promoRegistration = rdasParsePrice($domain['promo']['registration'] ?? '0');

            // Apply margin
            if ($marginType === 'percentage') {
                $finalRegistration = $baseRegistration * (1 + ($marginValue / 100));
                $finalRenewal = $baseRenewal * (1 + ($marginValue / 100));
                $finalTransfer = $baseTransfer * (1 + ($marginValue / 100));
            } else {
                $finalRegistration = $baseRegistration + $marginValue;
                $finalRenewal = $baseRenewal + $marginValue;
                $finalTransfer = $baseTransfer + $marginValue;
            }

            // Apply rounding
            $finalRegistration = rdasApplyRounding($finalRegistration, $roundingRule, $customRounding);
            $finalRenewal = rdasApplyRounding($finalRenewal, $roundingRule, $customRounding);
            $finalTransfer = rdasApplyRounding($finalTransfer, $roundingRule, $customRounding);

            // Get terms from promo object, default to 1 if null
            $terms = $domain['promo']['terms'] ?? 1;

            // TLD exists in WHMCS
            $existsIcon = '<span class="text-success"><i class="fa fa-check"></i></span>';
            $registeredIcon = '<span class="text-success"><i class="fa fa-cog"></i></span>';

            echo '<tr>';
            echo '<td><input type="checkbox" name="selected_tlds[]" value="' . htmlspecialchars($extension) . '"></td>';
            echo '<td><strong>' . htmlspecialchars($extension) . '</strong></td>';
            echo '<td>' . $existsIcon . ' ' . $registeredIcon . '</td>';
            echo '<td>' . $terms . ' Year' . ($terms > 1 ? 's' : '') . '</td>';
            echo '<td>' . number_format($promoRegistration > 0 ? $promoRegistration : $baseRegistration, 2) . '<br><small>' . number_format($finalRegistration, 2) . '</small></td>';
            echo '<td>' . $marginValue . ($marginType === 'percentage' ? '%' : '') . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
    echo '</div>';

    // Add Import button with form submit
    echo '<div class="panel-footer">';
    echo '<form method="post" action="addonmodules.php?module=rdas_pricing_updater">';
    echo '<input type="hidden" name="action" value="import_domains">';
    echo '<button type="submit" name="import_selected" class="btn btn-primary">';
    echo '<i class="fa fa-download"></i> Import Selected Domains';
    echo '</button>';
    echo '</form>';
    echo '</div>';

    // Add simple JavaScript for Select All functionality only
    echo '<script>';
    echo 'document.addEventListener("DOMContentLoaded", function() {';
    echo '    const selectAllCheckbox = document.getElementById("selectAll");';
    echo '    if (selectAllCheckbox) {';
    echo '        selectAllCheckbox.addEventListener("change", function() {';
    echo '            const checkboxes = document.querySelectorAll("input[name=\"selected_tlds[]\"]")';
    echo '            checkboxes.forEach(function(checkbox) {';
    echo '                checkbox.checked = selectAllCheckbox.checked;';
    echo '            });';
    echo '        });';
    echo '    }';
    echo '});';
    echo '</script>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

    } catch (Exception $e) {
        echo '<div class="alert alert-danger">';
        echo '<h4>Error Loading Pricing Data</h4>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '</div>';
    }
}

/**
 * Handle AJAX Requests - Only when called from addon pages
 * This check ensures we don't interfere with WHMCS config saving
 */
if (isset($_POST['action']) || isset($_GET['action'])) {
    // Only process AJAX if we're on the addon module page (not configaddonmods.php)
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $isAddonPage = (strpos($requestUri, 'addonmodules.php') !== false &&
                   (strpos($requestUri, 'module=rdas_pricing_updater') !== false ||
                    strpos($requestUri, 'module=mordenpricingupdater') !== false));

    if ($isAddonPage) {
        $action = $_POST['action'] ?? $_GET['action'] ?? '';

        // Handle action=ajax with operation parameter (used by pricing page)
        if ($action === 'ajax' && isset($_POST['operation'])) {
            // Clean any previous output and start fresh
            ob_clean();
            header('Content-Type: application/json');

            try {
                $operation = $_POST['operation'];

                switch ($operation) {
                    case 'test_api_connection':
                        testApiConnection();
                        break;

                    case 'update_domain_price':
                        handleUpdateDomainPrice();
                        break;

                    case 'sync_domain_price':
                    case 'sync_domain':
                        handleSyncDomainPrice();
                        break;

                    case 'bulk_sync_prices':
                    case 'bulk_sync':
                        handleBulkSyncPrices();
                        break;

                    case 'bulk_apply_margin':
                        handleBulkApplyMargin();
                    break;

                case 'get_dashboard_stats':
                    handleGetDashboardStats();
                    break;

                case 'get_pricing_data':
                    handleGetPricingData();
                    break;

                case 'get_domain_pricing':
                    handleGetDomainPricing();
                    break;

                case 'save_settings':
                    handleSaveSettings();
                    break;

                case 'get_logs':
                    handleGetLogs();
                    break;

                default:
                    echo json_encode(['success' => false, 'message' => 'Unknown operation: ' . $operation]);
            }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
        }

        switch ($action) {
            case 'test_api':
                header('Content-Type: application/json');
                testApiConnection();
                break;

            case 'sync_domain_price':
                header('Content-Type: application/json');
                syncDomainPrice();
                break;

            case 'bulk_import':
                header('Content-Type: application/json');
                bulkImportDomains();
                break;

            case 'update_prices_now':
                header('Content-Type: application/json');
                updatePricesNow();
                break;

            case 'process_bulk_action':
                header('Content-Type: application/json');
                processBulkAction();
                break;

            case 'update_selected':
                header('Content-Type: application/json');
                handleUpdateSelected();
                break;

            case 'sync_all':
                header('Content-Type: application/json');
                handleSyncAll();
                break;

            case 'export_csv':
                handleExportCsv();
                break;
        }
        exit;
    }
}










/**
 * Test API Connection
 */
function testApiConnection() {
    try {
        $config = rdasGetAddonConfig('rdas_pricing_updater');
        $apiUrl = $config['api_url'] ?? 'https://api.rdash.id/api/domain-prices?currency=IDR';

        $response = rdasFetchDomainPrices($apiUrl);

        if ($response && is_array($response) && count($response) > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'API connection successful. Found ' . count($response) . ' domain extensions.',
                'data' => array_slice($response, 0, 5) // Show first 5 for preview
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'API connection failed or no data returned.'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'API test failed: ' . $e->getMessage()
        ]);
    }
}

/**
 * Sync Individual Domain Price
 */
function syncDomainPrice() {
    try {
        $extension = $_POST['extension'] ?? '';

        if (empty($extension)) {
            throw new Exception('Extension parameter required');
        }

        $config = rdasGetAddonConfig('rdas_pricing_updater');
        $apiUrl = $config['api_url'] ?? 'https://api.rdash.id/api/domain-prices?currency=IDR';

        $apiData = rdasFetchDomainPrices($apiUrl);

        if (!$apiData) {
            throw new Exception('Failed to fetch API data');
        }

        // Find extension in API data
        $domainData = null;
        foreach ($apiData as $domain) {
            if ($domain['extension'] === $extension) {
                $domainData = $domain;
                break;
            }
        }

        if (!$domainData) {
            throw new Exception('Extension not found in API data');
        }

        // Calculate prices
        $prices = rdasCalculateDomainPrices($domainData, $config);

        // Update database
        $result = rdasUpdateDomainPricing($extension, $prices);

        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Domain price updated successfully',
                'prices' => $prices
            ]);
        } else {
            throw new Exception('Failed to update database');
        }

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Sync failed: ' . $e->getMessage()
        ]);
    }
}

/**
 * Bulk Import Domains
 */
function bulkImportDomains() {
    try {
        $config = rdasGetAddonConfig('rdas_pricing_updater');
        $apiUrl = $config['api_url'] ?? 'https://api.rdash.id/api/domain-prices?currency=IDR';

        $apiData = rdasFetchDomainPrices($apiUrl);

        if (!$apiData) {
            throw new Exception('Failed to fetch API data');
        }

        $imported = 0;
        $errors = [];

        foreach ($apiData as $domain) {
            try {
                $prices = rdasCalculateDomainPrices($domain, $config);
                $result = rdasUpdateDomainPricing($domain['extension'], $prices);

                if ($result) {
                    $imported++;
                } else {
                    $errors[] = $domain['extension'] . ': Database update failed';
                }
            } catch (Exception $e) {
                $errors[] = $domain['extension'] . ': ' . $e->getMessage();
            }
        }

        echo json_encode([
            'success' => true,
            'message' => "Bulk import completed. Imported: {$imported}, Errors: " . count($errors),
            'imported' => $imported,
            'errors' => $errors
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Bulk import failed: ' . $e->getMessage()
        ]);
    }
}

/**
 * Update Prices Now
 */
function updatePricesNow() {
    try {
        $config = rdasGetAddonConfig('rdas_pricing_updater');
        $apiUrl = $config['api_url'] ?? 'https://api.rdash.id/api/domain-prices?currency=IDR';

        $apiData = rdasFetchDomainPrices($apiUrl);

        if (!$apiData) {
            throw new Exception('Failed to fetch API data');
        }

        $updated = 0;
        $errors = [];

        foreach ($apiData as $domain) {
            try {
                $prices = rdasCalculateDomainPrices($domain, $config);
                $result = rdasUpdateDomainPricing($domain['extension'], $prices);

                if ($result) {
                    $updated++;
                }
            } catch (Exception $e) {
                $errors[] = $domain['extension'] . ': ' . $e->getMessage();
            }
        }

        rdasLogToAddon('info', "Manual price update completed. Updated: {$updated}, Errors: " . count($errors));

        echo json_encode([
            'success' => true,
            'message' => "Price update completed. Updated: {$updated} domains",
            'updated' => $updated,
            'errors' => $errors
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Price update failed: ' . $e->getMessage()
        ]);
    }
}

/**
 * Process Bulk Actions
 */
function processBulkAction() {
    try {
        $action = $_POST['bulk_action'] ?? '';
        $extensions = $_POST['extensions'] ?? [];

        if (empty($action) || empty($extensions)) {
            throw new Exception('Action and extensions required');
        }

        $config = rdasGetAddonConfig('rdas_pricing_updater');
        $processed = 0;
        $errors = [];

        switch ($action) {
            case 'sync_selected':
                $apiUrl = $config['api_url'] ?? 'https://api.rdash.id/api/domain-prices?currency=IDR';
                $apiData = rdasFetchDomainPrices($apiUrl);

                if (!$apiData) {
                    throw new Exception('Failed to fetch API data');
                }

                foreach ($extensions as $extension) {
                    try {
                        // Find extension in API data
                        $domainData = null;
                        foreach ($apiData as $domain) {
                            if ($domain['extension'] === $extension) {
                                $domainData = $domain;
                                break;
                            }
                        }

                        if ($domainData) {
                            $prices = rdasCalculateDomainPrices($domainData, $config);
                            $result = rdasUpdateDomainPricing($extension, $prices);

                            if ($result) {
                                $processed++;
                            }
                        }
                    } catch (Exception $e) {
                        $errors[] = $extension . ': ' . $e->getMessage();
                    }
                }
                break;

            default:
                throw new Exception('Unknown bulk action');
        }

        echo json_encode([
            'success' => true,
            'message' => "Bulk action completed. Processed: {$processed}, Errors: " . count($errors),
            'processed' => $processed,
            'errors' => $errors
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Bulk action failed: ' . $e->getMessage()
        ]);
    }
}

/**
 * Handle Update Domain Price (from pricing page AJAX)
 */
function handleUpdateDomainPrice() {
    try {
        $domainId = intval($_POST['domain_id'] ?? 0);
        $prices = $_POST['prices'] ?? [];

        if ($domainId <= 0) {
            throw new Exception('Invalid domain ID');
        }

        // Update directly with provided prices
        if (class_exists('WHMCS\Database\Capsule\Manager')) {
            \WHMCS\Database\Capsule\Manager::table('tbldomainpricing')
                ->where('id', $domainId)
                ->update([
                    'register' => floatval($prices['register'] ?? 0),
                    'renew' => floatval($prices['renew'] ?? 0),
                    'transfer' => floatval($prices['transfer'] ?? 0),
                    'restore' => floatval($prices['restore'] ?? 0)
                ]);
        } else {
            $query = "UPDATE tbldomainpricing SET
                register = ?,
                renew = ?,
                transfer = ?,
                restore = ?
                WHERE id = ?";
            full_query($query, [
                floatval($prices['register'] ?? 0),
                floatval($prices['renew'] ?? 0),
                floatval($prices['transfer'] ?? 0),
                floatval($prices['restore'] ?? 0),
                $domainId
            ]);
        }

        rdasLogToAddon('info', "Updated domain price ID: {$domainId}");

        echo json_encode([
            'success' => true,
            'message' => 'Domain price updated successfully'
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Update failed: ' . $e->getMessage()
        ]);
    }
}

/**
 * Handle Sync Domain Price (from pricing page AJAX)
 */
function handleSyncDomainPrice() {
    try {
        $domainId = intval($_POST['domain_id'] ?? 0);
        $extension = $_POST['extension'] ?? '';

        if (empty($extension)) {
            throw new Exception('Extension parameter required');
        }

        $config = rdasGetAddonConfig('rdas_pricing_updater');
        $apiUrl = $config['api_url'] ?? 'https://api.rdash.id/api/domain-prices?currency=IDR';

        $apiData = rdasFetchDomainPrices($apiUrl);

        if (!$apiData) {
            throw new Exception('Failed to fetch API data');
        }

        // Find extension in API data
        $domainData = null;
        foreach ($apiData as $domain) {
            if ($domain['extension'] === $extension) {
                $domainData = $domain;
                break;
            }
        }

        if (!$domainData) {
            throw new Exception('Extension not found in API data');
        }

        // Calculate prices
        $prices = rdasCalculateDomainPrices($domainData, $config);

        // Update database
        $result = rdasUpdateDomainPricing($extension, $prices);

        if ($result) {
            rdasLogToAddon('info', "Synced domain price for {$extension}");
            echo json_encode([
                'success' => true,
                'message' => 'Domain price synced successfully',
                'prices' => $prices
            ]);
        } else {
            throw new Exception('Failed to update database');
        }

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Sync failed: ' . $e->getMessage()
        ]);
    }
}

/**
 * Handle Bulk Sync Prices (from pricing page AJAX)
 */
function handleBulkSyncPrices() {
    try {
        $domainIds = $_POST['domain_ids'] ?? [];

        if (empty($domainIds) || !is_array($domainIds)) {
            throw new Exception('No domains selected');
        }

        $config = rdasGetAddonConfig('rdas_pricing_updater');
        $apiUrl = $config['api_url'] ?? 'https://api.rdash.id/api/domain-prices?currency=IDR';

        $apiData = rdasFetchDomainPrices($apiUrl);

        if (!$apiData) {
            throw new Exception('Failed to fetch API data');
        }

        // Get extensions from domain IDs
        $extensions = [];
        if (class_exists('WHMCS\Database\Capsule\Manager')) {
            $domains = \WHMCS\Database\Capsule\Manager::table('tbldomainpricing')
                ->whereIn('id', $domainIds)
                ->get(['id', 'extension']);

            foreach ($domains as $domain) {
                $extensions[$domain->id] = $domain->extension;
            }
        } else {
            $ids = implode(',', array_map('intval', $domainIds));
            $result = select_query('tbldomainpricing', 'id,extension', "id IN ({$ids})");
            while ($row = mysql_fetch_array($result)) {
                $extensions[$row['id']] = $row['extension'];
            }
        }

        // Build API data lookup
        $apiLookup = [];
        foreach ($apiData as $domain) {
            $apiLookup[$domain['extension']] = $domain;
        }

        $updated = 0;
        $errors = [];

        foreach ($extensions as $id => $extension) {
            try {
                if (isset($apiLookup[$extension])) {
                    $prices = rdasCalculateDomainPrices($apiLookup[$extension], $config);
                    $result = rdasUpdateDomainPricing($extension, $prices);

                    if ($result) {
                        $updated++;
                    }
                }
            } catch (Exception $e) {
                $errors[] = $extension . ': ' . $e->getMessage();
            }
        }

        rdasLogToAddon('info', "Bulk sync completed. Updated: {$updated}, Errors: " . count($errors));

        echo json_encode([
            'success' => true,
            'message' => "Bulk sync completed. Updated: {$updated} domains",
            'updated' => $updated,
            'errors' => $errors
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Bulk sync failed: ' . $e->getMessage()
        ]);
    }
}

/**
 * Handle Bulk Apply Margin (from pricing page AJAX)
 */
function handleBulkApplyMargin() {
    try {
        $domainIds = $_POST['domain_ids'] ?? [];
        $marginType = $_POST['margin_type'] ?? 'percentage';
        $marginValue = floatval($_POST['margin_value'] ?? 0);
        $applyTo = $_POST['apply_to'] ?? [];

        if (empty($domainIds) || !is_array($domainIds)) {
            throw new Exception('No domains selected');
        }

        if ($marginValue <= 0) {
            throw new Exception('Invalid margin value');
        }

        $updated = 0;
        $errors = [];

        // Get current prices
        if (class_exists('WHMCS\Database\Capsule\Manager')) {
            $domains = \WHMCS\Database\Capsule\Manager::table('tbldomainpricing')
                ->whereIn('id', $domainIds)
                ->get();

            foreach ($domains as $domain) {
                try {
                    $updateData = [];

                    if (!empty($applyTo['register'])) {
                        $currentPrice = floatval($domain->register);
                        $updateData['register'] = $marginType === 'percentage'
                            ? $currentPrice * (1 + ($marginValue / 100))
                            : $currentPrice + $marginValue;
                    }

                    if (!empty($applyTo['renew'])) {
                        $currentPrice = floatval($domain->renew);
                        $updateData['renew'] = $marginType === 'percentage'
                            ? $currentPrice * (1 + ($marginValue / 100))
                            : $currentPrice + $marginValue;
                    }

                    if (!empty($applyTo['transfer'])) {
                        $currentPrice = floatval($domain->transfer);
                        $updateData['transfer'] = $marginType === 'percentage'
                            ? $currentPrice * (1 + ($marginValue / 100))
                            : $currentPrice + $marginValue;
                    }

                    if (!empty($applyTo['restore'])) {
                        $currentPrice = floatval($domain->restore);
                        $updateData['restore'] = $marginType === 'percentage'
                            ? $currentPrice * (1 + ($marginValue / 100))
                            : $currentPrice + $marginValue;
                    }

                    if (!empty($updateData)) {
                        // Apply rounding if configured
                        $config = rdasGetAddonConfig('rdas_pricing_updater');
                        $roundingRule = $config['rounding_rule'] ?? 'up_1000';
                        $customRounding = floatval($config['custom_rounding'] ?? 1000);

                        foreach ($updateData as $key => $value) {
                            $updateData[$key] = rdasApplyRounding($value, $roundingRule, $customRounding);
                        }

                        \WHMCS\Database\Capsule\Manager::table('tbldomainpricing')
                            ->where('id', $domain->id)
                            ->update($updateData);

                        $updated++;
                    }

                } catch (Exception $e) {
                    $errors[] = $domain->extension . ': ' . $e->getMessage();
                }
            }
        } else {
            // Legacy fallback
            $ids = implode(',', array_map('intval', $domainIds));
            $result = select_query('tbldomainpricing', '*', "id IN ({$ids})");

            while ($row = mysql_fetch_array($result)) {
                try {
                    $updateData = [];

                    if (!empty($applyTo['register'])) {
                        $currentPrice = floatval($row['register']);
                        $updateData['register'] = $marginType === 'percentage'
                            ? $currentPrice * (1 + ($marginValue / 100))
                            : $currentPrice + $marginValue;
                    }

                    if (!empty($applyTo['renew'])) {
                        $currentPrice = floatval($row['renew']);
                        $updateData['renew'] = $marginType === 'percentage'
                            ? $currentPrice * (1 + ($marginValue / 100))
                            : $currentPrice + $marginValue;
                    }

                    if (!empty($applyTo['transfer'])) {
                        $currentPrice = floatval($row['transfer']);
                        $updateData['transfer'] = $marginType === 'percentage'
                            ? $currentPrice * (1 + ($marginValue / 100))
                            : $currentPrice + $marginValue;
                    }

                    if (!empty($applyTo['restore'])) {
                        $currentPrice = floatval($row['restore']);
                        $updateData['restore'] = $marginType === 'percentage'
                            ? $currentPrice * (1 + ($marginValue / 100))
                            : $currentPrice + $marginValue;
                    }

                    if (!empty($updateData)) {
                        $config = rdasGetAddonConfig('rdas_pricing_updater');
                        $roundingRule = $config['rounding_rule'] ?? 'up_1000';
                        $customRounding = floatval($config['custom_rounding'] ?? 1000);

                        foreach ($updateData as $key => $value) {
                            $updateData[$key] = rdasApplyRounding($value, $roundingRule, $customRounding);
                        }

                        $setClauses = [];
                        foreach ($updateData as $key => $value) {
                            $setClauses[] = "{$key} = ?";
                        }

                        $query = "UPDATE tbldomainpricing SET " . implode(', ', $setClauses) . " WHERE id = ?";
                        $params = array_values($updateData);
                        $params[] = $row['id'];

                        full_query($query, $params);
                        $updated++;
                    }

                } catch (Exception $e) {
                    $errors[] = $row['extension'] . ': ' . $e->getMessage();
                }
            }
        }

        rdasLogToAddon('info', "Bulk margin applied. Updated: {$updated}, Errors: " . count($errors));

        echo json_encode([
            'success' => true,
            'message' => "Bulk margin applied successfully. Updated: {$updated} domains",
            'updated' => $updated,
            'errors' => $errors
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Bulk margin failed: ' . $e->getMessage()
        ]);
    }
}

/**
 * Handle Update Selected (for URL-based action)
 */
function handleUpdateSelected() {
    try {
        // Get selected domains from POST or GET
        $selectedTlds = $_POST['selected_tlds'] ?? $_GET['selected_tlds'] ?? [];
        if (!is_array($selectedTlds)) {
            $selectedTlds = explode(',', $selectedTlds);
        }

        if (empty($selectedTlds)) {
            throw new Exception('No domains selected for update');
        }

        $config = rdasGetAddonConfig('rdas_pricing_updater');
        $apiUrl = $config['api_url'] ?? 'https://api.rdash.id/api/domain-prices?currency=IDR';

        $apiData = rdasFetchDomainPrices($apiUrl);

        if (!$apiData) {
            throw new Exception('Failed to fetch API data');
        }

        // Build API data lookup
        $apiLookup = [];
        foreach ($apiData as $domain) {
            $apiLookup[$domain['extension']] = $domain;
        }

        $updated = 0;
        $errors = [];

        foreach ($selectedTlds as $extension) {
            try {
                if (isset($apiLookup[$extension])) {
                    $prices = rdasCalculateDomainPrices($apiLookup[$extension], $config);
                    $result = rdasUpdateDomainPricing($extension, $prices);

                    if ($result) {
                        $updated++;
                    }
                }
            } catch (Exception $e) {
                $errors[] = $extension . ': ' . $e->getMessage();
            }
        }

        rdasLogToAddon('info', "Update selected completed. Updated: {$updated}, Errors: " . count($errors));

        echo json_encode([
            'success' => true,
            'message' => "Update completed. Updated: {$updated} domains",
            'updated' => $updated,
            'errors' => $errors
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Update failed: ' . $e->getMessage()
        ]);
    }
}

/**
 * Handle Sync All (sync all existing TLDs)
 */
function handleSyncAll() {
    try {
        $config = rdasGetAddonConfig('rdas_pricing_updater');
        $apiUrl = $config['api_url'] ?? 'https://api.rdash.id/api/domain-prices?currency=IDR';

        $apiData = rdasFetchDomainPrices($apiUrl);

        if (!$apiData) {
            throw new Exception('Failed to fetch API data');
        }

        // Get existing TLDs in WHMCS
        $existingTLDs = rdasGetAllDomainExtensions();

        // Build API data lookup
        $apiLookup = [];
        foreach ($apiData as $domain) {
            $apiLookup[$domain['extension']] = $domain;
        }

        $updated = 0;
        $errors = [];

        foreach ($existingTLDs as $extension) {
            try {
                if (isset($apiLookup[$extension])) {
                    $prices = rdasCalculateDomainPrices($apiLookup[$extension], $config);
                    $result = rdasUpdateDomainPricing($extension, $prices);

                    if ($result) {
                        $updated++;
                    }
                }
            } catch (Exception $e) {
                $errors[] = $extension . ': ' . $e->getMessage();
            }
        }

        rdasLogToAddon('info', "Sync all completed. Updated: {$updated}, Errors: " . count($errors));

        echo json_encode([
            'success' => true,
            'message' => "Sync completed. Updated: {$updated} domains",
            'updated' => $updated,
            'errors' => $errors
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Sync failed: ' . $e->getMessage()
        ]);
    }
}

/**
 * Handle Export CSV
 */
function handleExportCsv() {
    try {
        // Get domain pricing data
        $domains = [];

        if (class_exists('WHMCS\Database\Capsule\Manager')) {
            $domains = \WHMCS\Database\Capsule\Manager::table('tbldomainpricing')
                ->selectRaw('MIN(id) as id, extension, MAX(autoreg) as autoreg')
                ->groupBy('extension')
                ->orderBy('extension')
                ->get();
        } else {
            $result = full_query(
                "SELECT MIN(id) as id, extension, MAX(autoreg) as autoreg, MAX(`group`) as `group` FROM tbldomainpricing GROUP BY extension ORDER BY extension ASC"
            );
            while ($row = mysql_fetch_array($result)) {
                $domains[] = (object) $row;
            }
        }

        // Generate CSV
        $filename = 'domain_pricing_' . date('Y-m-d_His') . '.csv';

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // Header row
        fputcsv($output, ['Extension', 'Register', 'Renew', 'Transfer', 'Restore', 'Group', 'Registrar']);

        // Data rows
        foreach ($domains as $domain) {
            fputcsv($output, [
                $domain->extension,
                $domain->register,
                $domain->renew,
                $domain->transfer,
                $domain->restore ?? 0,
                $domain->group ?? '',
                $domain->autoreg ?? 'Manual'
            ]);
        }

        fclose($output);
        exit;

    } catch (Exception $e) {
        echo 'Export failed: ' . $e->getMessage();
    }
}

// WHMCS Hooks
// Note: DailyCronJob hook is handled in hooks.php for better implementation
add_hook('WeeklyCronJob', 1, function($vars) {
    try {
        // Clean old logs (keep last 30 days)
        $query = "DELETE FROM mod_rdas_pricing_updater_log WHERE date < DATE_SUB(NOW(), INTERVAL 30 DAY)";
        full_query($query);

        rdasLogToAddon('info', 'Weekly cleanup completed - old logs removed');
    } catch (Exception $e) {
        rdasLogToAddon('error', 'Weekly cron job failed: ' . $e->getMessage());
    }
});

/**
 * Handle Get Dashboard Stats
 */
function handleGetDashboardStats() {
    try {
        $stats = [
            'total_domains' => 0,
            'active_promos' => 0,
            'last_sync' => 'Never',
            'api_status' => 'disconnected'
        ];

        // Get total domains
        if (class_exists('WHMCS\Database\Capsule\Manager')) {
            $stats['total_domains'] = \WHMCS\Database\Capsule\Manager::table('tbldomainpricing')->count();
        } else {
            $result = select_query('tbldomainpricing', 'COUNT(*) as total');
            $row = mysql_fetch_array($result);
            $stats['total_domains'] = $row['total'];
        }

        // Check API status
        $config = rdasGetAddonConfig('rdas_pricing_updater');
        $apiUrl = $config['api_url'] ?? 'https://api.rdash.id/api/domain-prices?currency=IDR';

        $apiData = rdasFetchDomainPrices($apiUrl);
        if ($apiData && is_array($apiData) && count($apiData) > 0) {
            $stats['api_status'] = 'connected';
        }

        // Get last sync from logs
        if (class_exists('WHMCS\Database\Capsule\Manager')) {
            $lastLog = \WHMCS\Database\Capsule\Manager::table('mod_rdas_pricing_updater_log')
                ->where('message', 'like', '%completed%')
                ->orderBy('date', 'desc')
                ->first();
            if ($lastLog) {
                $stats['last_sync'] = date('M j, Y H:i', strtotime($lastLog->date));
            }
        }

        echo json_encode([
            'success' => true,
            'data' => $stats
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to get stats: ' . $e->getMessage()
        ]);
    }
}

/**
 * Handle Get Pricing Data
 */
function handleGetPricingData() {
    // Ensure clean output
    if (ob_get_level()) {
        ob_clean();
    }

    try {
        $data = [];

        // Get addon config
        $config = rdasGetAddonConfig('rdas_pricing_updater');
        $marginType = $config['margin_type'] ?? 'percentage';
        $marginValue = floatval($config['profit_margin'] ?? $config['margin_value'] ?? 20);
        $roundingRule = $config['rounding_rule'] ?? 'up_1000';
        $customRounding = floatval($config['custom_rounding'] ?? 1000);

        // Fetch API prices
        $apiUrl = $config['api_url'] ?? 'https://api.rdash.id/api/domain-prices?currency=IDR';
        $apiPrices = rdasFetchDomainPrices($apiUrl);

        // Build API lookup
        $apiLookup = array();
        if ($apiPrices && is_array($apiPrices)) {
            foreach ($apiPrices as $apiDomain) {
                $apiLookup[$apiDomain['extension']] = $apiDomain;
            }
        }

        // WHMCS 8/9 - use tblpricing, GROUP BY to avoid duplicates
        $domains = full_query(
            "SELECT MIN(id) as id, extension, MAX(autoreg) as autoreg, MAX(`group`) as `group` FROM tbldomainpricing GROUP BY extension ORDER BY extension ASC"
        );
        while ($domain = mysql_fetch_array($domains)) {
            $domainId = intval($domain['id']);
            $extension = $domain['extension'];

            // Year column mapping for WHMCS 8/9
            $yearColumnMap = [
                1 => 'msetupfee',
                2 => 'qsetupfee',
                3 => 'ssetupfee',
                4 => 'asetupfee',
                5 => 'bsetupfee',
                6 => 'monthly',
                7 => 'quarterly',
                8 => 'semiannually',
                9 => 'annually',
                10 => 'biennially'
            ];

            // First, determine promo terms from API
            $promoTerms = 1;
            $promoActive = false;

            if (isset($apiLookup[$extension])) {
                $apiData = $apiLookup[$extension];
                $promoData = $apiData['promo'] ?? null;
                if ($promoData && isset($promoData['registration']) && !empty($promoData['registration'])) {
                    $now = time();
                    $startDate = isset($promoData['start_date']) ? strtotime($promoData['start_date']) : null;
                    $endDate = isset($promoData['end_date']) ? strtotime($promoData['end_date']) : null;

                    if ($startDate && $endDate && $now >= $startDate && $now <= $endDate) {
                        $promoActive = true;
                        $promoTerms = intval($promoData['terms'] ?? 1);
                    }
                }
            }

            // Determine which year column to use
            $yearColumn = $yearColumnMap[$promoTerms] ?? 'msetupfee';

            // Get current pricing from tblpricing - use the correct year column
            $currentRegister = 0;
            $currentRenew = 0;
            $currentTransfer = 0;

            $regResult = full_query(
                "SELECT {$yearColumn} FROM tblpricing WHERE type='domainregister' AND relid=" . $domainId . " LIMIT 1"
            );
            if ($regRow = mysql_fetch_array($regResult)) {
                $currentRegister = floatval($regRow[$yearColumn]);
            }

            $renewResult = full_query(
                "SELECT {$yearColumn} FROM tblpricing WHERE type='domainrenew' AND relid=" . $domainId . " LIMIT 1"
            );
            if ($renewRow = mysql_fetch_array($renewResult)) {
                $currentRenew = floatval($renewRow[$yearColumn]);
            }

            $transferResult = full_query(
                "SELECT {$yearColumn} FROM tblpricing WHERE type='domaintransfer' AND relid=" . $domainId . " LIMIT 1"
            );
            if ($transferRow = mysql_fetch_array($transferResult)) {
                $currentTransfer = floatval($transferRow[$yearColumn]);
            }

            // Get API pricing
            $apiRegister = 0;
            $promoRegister = 0;
            $promoEnd = null;
            $hasApiData = false;

            if (isset($apiLookup[$extension])) {
                $hasApiData = true;
                $apiData = $apiLookup[$extension];

                $apiRegister = rdasParsePrice($apiData['registration'] ?? 0);
                $apiRenew = rdasParsePrice($apiData['renewal'] ?? 0);
                $apiTransfer = rdasParsePrice($apiData['transfer'] ?? 0);

                // Check for promo - API structure has promo.registration directly
                $promoData = $apiData['promo'] ?? null;
                if ($promoData && isset($promoData['registration']) && !empty($promoData['registration'])) {
                    $now = time();
                    $startDate = isset($promoData['start_date']) ? strtotime($promoData['start_date']) : null;
                    $endDate = isset($promoData['end_date']) ? strtotime($promoData['end_date']) : null;

                    // Check if promo is currently active
                    if ($startDate && $endDate && $now >= $startDate && $now <= $endDate) {
                        $promoActive = true;
                        $promoTerms = intval($promoData['terms'] ?? 1);
                        $promoRegister = rdasParsePrice($promoData['registration']);
                        $promoEnd = $promoData['end_date'];
                    }
                }

                // Apply margin
                $apiRegister = rdasApplyMargin($apiRegister, $marginType, $marginValue);
                $apiRegister = rdasApplyRounding($apiRegister, $roundingRule, $customRounding);

                if ($promoActive && $promoRegister > 0) {
                    $promoRegister = rdasApplyMargin($promoRegister, $marginType, $marginValue);
                    $promoRegister = rdasApplyRounding($promoRegister, $roundingRule, $customRounding);
                }
            }

            // Compare promo price with current when promo is active
            $finalApiRegister = $promoActive ? $promoRegister : $apiRegister;
            $registerDiff = $hasApiData ? ($finalApiRegister - $currentRegister) : 0;

            $data[] = [
                'id' => $domainId,
                'extension' => $extension,
                'current_register' => $currentRegister,
                'current_renew' => $currentRenew,
                'current_transfer' => $currentTransfer,
                'api_register' => $apiRegister,
                'promo_active' => $promoActive,
                'promo_terms' => $promoTerms,
                'promo_register' => $promoRegister,
                'promo_end' => $promoEnd,
                'final_api_register' => $finalApiRegister,
                'register_diff' => $registerDiff,
                'has_api_data' => $hasApiData,
                'autoreg' => $domain['autoreg'] ?? '',
                'registrar_name' => $domain['autoreg'] ?: 'Manual',
                'domain_group' => $domain['group'] ?? ''
            ];
        }

        echo json_encode([
            'success' => true,
            'data' => $data
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to get pricing data: ' . $e->getMessage()
        ]);
    }
}

/**
 * Handle Get Domain Pricing
 */
function handleGetDomainPricing() {
    try {
        $extension = $_POST['tld'] ?? $_POST['extension'] ?? '';

        if (empty($extension)) {
            throw new Exception('Extension parameter required');
        }

        $data = null;

        // Get domain ID
        $domainResult = select_query('tbldomainpricing', 'id, extension, autoreg', ['extension' => $extension]);
        $domain = mysql_fetch_array($domainResult);

        if ($domain) {
            $domainId = intval($domain['id']);

            // Year column mapping for WHMCS 8/9
            $yearColumnMap = [
                1 => 'msetupfee',
                2 => 'qsetupfee',
                3 => 'ssetupfee',
                4 => 'asetupfee',
                5 => 'bsetupfee',
                6 => 'monthly',
                7 => 'quarterly',
                8 => 'semiannually',
                9 => 'annually',
                10 => 'biennially'
            ];

            // Get API pricing first to determine promo terms
            $config = rdasGetAddonConfig('rdas_pricing_updater');
            $apiUrl = $config['api_url'] ?? 'https://api.rdash.id/api/domain-prices?currency=IDR';
            $apiPrices = rdasFetchDomainPrices($apiUrl);

            $apiRegister = 0;
            $apiRenew = 0;
            $apiTransfer = 0;
            $promoActive = false;
            $promoTerms = 1;
            $promoRegister = 0;
            $promoEnd = null;
            $hasApiData = false;

            if ($apiPrices && is_array($apiPrices)) {
                foreach ($apiPrices as $apiDomain) {
                    if ($apiDomain['extension'] === $extension) {
                        $hasApiData = true;

                        $marginType = $config['margin_type'] ?? 'percentage';
                        $marginValue = floatval($config['profit_margin'] ?? $config['margin_value'] ?? 20);
                        $roundingRule = $config['rounding_rule'] ?? 'up_1000';
                        $customRounding = floatval($config['custom_rounding'] ?? 1000);

                        $apiRegister = rdasParsePrice($apiDomain['registration'] ?? 0);
                        $apiRenew = rdasParsePrice($apiDomain['renewal'] ?? 0);
                        $apiTransfer = rdasParsePrice($apiDomain['transfer'] ?? 0);

                        // Check for promo - API structure has promo.registration directly
                        $promoData = $apiDomain['promo'] ?? null;
                        if ($promoData && isset($promoData['registration']) && !empty($promoData['registration'])) {
                            $now = time();
                            $startDate = isset($promoData['start_date']) ? strtotime($promoData['start_date']) : null;
                            $endDate = isset($promoData['end_date']) ? strtotime($promoData['end_date']) : null;

                            // Check if promo is currently active (within date range)
                            if ($startDate && $endDate && $now >= $startDate && $now <= $endDate) {
                                $promoActive = true;
                                $promoTerms = intval($promoData['terms'] ?? 1);
                                $promoRegister = rdasParsePrice($promoData['registration']);
                                $promoEnd = $promoData['end_date'];
                            }
                        }

                        // Apply margin
                        $apiRegister = rdasApplyMargin($apiRegister, $marginType, $marginValue);
                        $apiRegister = rdasApplyRounding($apiRegister, $roundingRule, $customRounding);

                        if ($promoActive && $promoRegister > 0) {
                            $promoRegister = rdasApplyMargin($promoRegister, $marginType, $marginValue);
                            $promoRegister = rdasApplyRounding($promoRegister, $roundingRule, $customRounding);
                        }

                        break;
                    }
                }
            }

            // Determine which year column to use based on promo terms
            $yearColumn = $yearColumnMap[$promoTerms] ?? 'msetupfee';

            // Get current pricing from tblpricing - use the correct year column
            $currentRegister = 0;
            $currentRenew = 0;
            $currentTransfer = 0;

            $regResult = full_query(
                "SELECT {$yearColumn} FROM tblpricing WHERE type='domainregister' AND relid=" . $domainId . " LIMIT 1"
            );
            if ($regRow = mysql_fetch_array($regResult)) {
                $currentRegister = floatval($regRow[$yearColumn]);
            }

            $renewResult = full_query(
                "SELECT {$yearColumn} FROM tblpricing WHERE type='domainrenew' AND relid=" . $domainId . " LIMIT 1"
            );
            if ($renewRow = mysql_fetch_array($renewResult)) {
                $currentRenew = floatval($renewRow[$yearColumn]);
            }

            $transferResult = full_query(
                "SELECT {$yearColumn} FROM tblpricing WHERE type='domaintransfer' AND relid=" . $domainId . " LIMIT 1"
            );
            if ($transferRow = mysql_fetch_array($transferResult)) {
                $currentTransfer = floatval($transferRow[$yearColumn]);
            }

            // Compare promo price with current when promo is active
            $finalApiRegister = $promoActive ? $promoRegister : $apiRegister;

            $data = [
                'id' => $domainId,
                'extension' => $domain['extension'],
                'current_register' => $currentRegister,
                'current_renew' => $currentRenew,
                'current_transfer' => $currentTransfer,
                'api_register' => $apiRegister,
                'api_renew' => $apiRenew,
                'api_transfer' => $apiTransfer,
                'promo_active' => $promoActive,
                'promo_terms' => $promoTerms,
                'promo_register' => $promoRegister,
                'promo_end' => $promoEnd,
                'final_api_register' => $finalApiRegister,
                'has_api_data' => $hasApiData,
                'autoreg' => $domain['autoreg'] ?? '',
                'registrar_name' => $domain['autoreg'] ?: 'Manual'
            ];
        }

        if ($data) {
            echo json_encode([
                'success' => true,
                'data' => $data
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Domain not found'
            ]);
        }

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to get domain pricing: ' . $e->getMessage()
        ]);
    }
}

/**
 * Handle Save Settings
 */
function handleSaveSettings() {
    try {
        $settings = $_POST;
        unset($settings['action'], $settings['operation'], $settings['nonce']);

        $saved = 0;
        foreach ($settings as $key => $value) {
            $exists = select_query('tbladdonmodules', 'id', [
                'module' => 'rdas_pricing_updater',
                'setting' => $key
            ]);

            if (mysql_num_rows($exists) > 0) {
                update_query('tbladdonmodules', ['value' => $value], [
                    'module' => 'rdas_pricing_updater',
                    'setting' => $key
                ]);
            } else {
                insert_query('tbladdonmodules', [
                    'module' => 'rdas_pricing_updater',
                    'setting' => $key,
                    'value' => $value
                ]);
            }
            $saved++;
        }

        rdasLogToAddon('info', "Settings saved. Updated {$saved} settings");

        echo json_encode([
            'success' => true,
            'message' => 'Settings saved successfully'
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to save settings: ' . $e->getMessage()
        ]);
    }
}

/**
 * Handle Get Logs
 */
function handleGetLogs() {
    try {
        $page = intval($_POST['page'] ?? 1);
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $logs = [];
        $total = 0;

        if (class_exists('WHMCS\Database\Capsule\Manager')) {
            $total = \WHMCS\Database\Capsule\Manager::table('mod_rdas_pricing_updater_log')->count();

            $results = \WHMCS\Database\Capsule\Manager::table('mod_rdas_pricing_updater_log')
                ->orderBy('date', 'desc')
                ->offset($offset)
                ->limit($perPage)
                ->get();

            foreach ($results as $row) {
                $logs[] = [
                    'id' => $row->id,
                    'level' => $row->level,
                    'message' => $row->message,
                    'date' => $row->date
                ];
            }
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'logs' => $logs,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => ceil($total / $perPage),
                    'total' => $total
                ]
            ]
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to get logs: ' . $e->getMessage()
        ]);
    }
}

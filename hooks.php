<?php
/**
 * RDAS Pricing Updater - Hooks
 *
 * @package RDAS Pricing Updater
 * @version 2.2.0
 * @author Sadewa
 * @description WHMCS hooks untuk DailyCronJob dan AdminAreaPage integration
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// Require functions library
require_once __DIR__ . '/lib/functions.php';

/**
 * Daily Cron Job Hook
 * Runs automatic pricing updates daily
 */
add_hook('DailyCronJob', 1, function($vars) {
    try {
        // Load addon configuration
        $config = rdasGetAddonConfig('rdas_pricing_updater');

        // Check if auto-update is enabled
        if (!isset($config['auto_update']) || $config['auto_update'] !== 'on') {
            return;
        }

        // Log cron start
        rdasLogToAddon('info', 'Daily cron job started - checking for price updates');
        logActivity('RDAS Pricing Updater: Daily cron job started');

        // Get API URL from config
        $apiUrl = $config['api_url'] ?? 'https://api.rdash.id/api/domain-prices?currency=IDR';

        // Fetch pricing data from API
        $apiData = rdasFetchDomainPrices($apiUrl);

        if (!$apiData || !is_array($apiData)) {
            rdasLogToAddon('error', 'Failed to fetch API data in daily cron');
            logActivity('RDAS Pricing Updater: Failed to fetch API data');
            return;
        }

        // Get existing TLDs from WHMCS
        $existingTLDs = rdasGetAllDomainExtensions();

        $updatedCount = 0;
        $promoCount = 0;
        $errorCount = 0;

        foreach ($apiData as $domainData) {
            $extension = $domainData['extension'] ?? '';

            // Only update TLDs that exist in WHMCS
            if (!in_array($extension, $existingTLDs)) {
                continue;
            }

            try {
                // Calculate prices with promo handling
                $prices = rdasCalculateDomainPrices($domainData, $config);

                // Update WHMCS pricing
                $result = rdasUpdateDomainPricing($extension, $prices);

                if ($result) {
                    $updatedCount++;

                    // Track promo updates
                    if (!empty($prices['promo_active'])) {
                        $promoCount++;
                        rdasLogToAddon('info', "Promo price applied for {$extension}", [
                            'base_price' => $prices['register']['base'] ?? 0,
                            'final_price' => $prices['register']['final'] ?? 0,
                            'promo_end' => $prices['promo_end'] ?? null
                        ]);
                    }
                } else {
                    $errorCount++;
                }

            } catch (Exception $e) {
                $errorCount++;
                rdasLogToAddon('error', "Error updating {$extension}: " . $e->getMessage());
            }
        }

        // Clean old logs based on retention setting
        $retentionDays = intval($config['log_retention_days'] ?? 30);
        if ($retentionDays > 0) {
            rdasCleanOldLogs($retentionDays);
        }

        // Log completion
        $message = "Daily cron completed. Updated: {$updatedCount} (Promo: {$promoCount}), Errors: {$errorCount}";
        rdasLogToAddon('info', $message);
        logActivity("RDAS Pricing Updater: {$message}");

    } catch (Exception $e) {
        rdasLogToAddon('error', 'Daily cron job failed: ' . $e->getMessage());
        logActivity('RDAS Pricing Updater: Daily cron job failed - ' . $e->getMessage());
    }
});

/**
 * Admin Area Page Hook
 * Adds menu item in admin area
 */
add_hook('AdminAreaPage', 1, function($vars) {
    // Check for both module names for compatibility
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, 'rdas_pricing_updater') === false && strpos($uri, 'mordenpricingupdater') === false) {
        return;
    }

    return [
        'breadcrumb' => [
            'index.php?m=rdas_pricing_updater' => 'RDAS Pricing Updater'
        ],
        'templatefile' => 'rdas_pricing_updater'
    ];
});

/**
 * Admin Area Head Output Hook
 * Adds custom CSS and JS for addon
 */
add_hook('AdminAreaHeadOutput', 1, function($vars) {
    // Check for both module names for compatibility
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, 'rdas_pricing_updater') === false && strpos($uri, 'mordenpricingupdater') === false) {
        return '';
    }

    $addonUrl = rtrim($GLOBALS['CONFIG']['SystemURL'] ?? '', '/') . '/modules/addons/rdas_pricing_updater';

    return '<link rel="stylesheet" type="text/css" href="' . $addonUrl . '/assets/css/style.css?v=2.2.0" />' . "\n" .
           '<script type="text/javascript" src="' . $addonUrl . '/assets/js/script.js?v=2.2.0"></script>' . "\n";
});

/**
 * Admin Area Footer Output Hook
 * Adds JavaScript initialization
 */
add_hook('AdminAreaFooterOutput', 1, function($vars) {
    // Check for both module names for compatibility
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, 'rdas_pricing_updater') === false && strpos($uri, 'mordenpricingupdater') === false) {
        return '';
    }

    $nonce = rdasGenerateCSRFToken('admin');

    // Build the AJAX URL
    $module = 'rdas_pricing_updater';
    if (strpos($uri, 'mordenpricingupdater') !== false) {
        $module = 'mordenpricingupdater';
    }

    // Get the admin URL path
    $adminPath = 'addonmodules.php';
    if (preg_match('#(/[^/]*admin[^/]*/)addonmodules\.php#i', $uri, $matches)) {
        $adminPath = $matches[1] . 'addonmodules.php';
    } elseif (strpos($uri, 'addonmodules.php') !== false) {
        $adminPath = 'addonmodules.php';
    }

    $ajaxUrl = $adminPath . '?module=' . $module . '&action=ajax';

    return '<script type="text/javascript">' . "\n" .
           'jQuery(document).ready(function($) {' . "\n" .
           '    if (typeof RDAS !== "undefined") {' . "\n" .
           '        RDAS.nonce = "' . $nonce . '";' . "\n" .
           '        RDAS.ajaxUrl = "' . $ajaxUrl . '";' . "\n" .
           '    }' . "\n" .
           '});' . "\n" .
           '</script>' . "\n";
});

/**
 * After Module Config Save Hook
 * Sync pricing when addon settings are saved
 */
add_hook('AfterModuleConfigSave', 1, function($vars) {
    // Only for our addon
    if (($vars['module'] ?? '') !== 'rdas_pricing_updater') {
        return;
    }

    rdasLogToAddon('info', 'Addon settings updated');
});

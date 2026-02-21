<?php
/**
 * RDAS Pricing Updater - Core Functions
 *
 * @package    WHMCS
 * @author     Morden Team
 * @copyright  Copyright (c) 2025, Morden Team
 * @license    https://www.morden.com/license/
 * @version    2.1.6
 * @link       https://github.com/sadewadee
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Parse price string to numeric value
 * Converts "Rp45.000" to 45000
 *
 * @param string $priceString Price string from API
 * @return float Numeric price value
 */
function rdasParsePrice($priceString) {
    if (empty($priceString) || $priceString === null) {
        return 0;
    }
    
    // Remove currency symbol and formatting
    $cleanPrice = preg_replace('/[^0-9,.]/', '', $priceString);
    
    // Handle Indonesian number format (45.000 = 45000)
    if (strpos($cleanPrice, '.') !== false && strpos($cleanPrice, ',') === false) {
        // If only dots, treat as thousands separator
        $cleanPrice = str_replace('.', '', $cleanPrice);
    } elseif (strpos($cleanPrice, ',') !== false) {
        // If comma exists, treat as decimal separator
        $cleanPrice = str_replace('.', '', $cleanPrice);
        $cleanPrice = str_replace(',', '.', $cleanPrice);
    }
    
    return floatval($cleanPrice);
}

/**
 * Import selected domains to WHMCS using TLD Sync API
 *
 * @param array $tlds Array of TLD extensions to import
 * @return array Result with success status and message
 */
function rdasImportDomains($tlds) {
    try {
        $imported = 0;
        $errors = [];
        
        foreach ($tlds as $tld) {
            // Use WHMCS TLD Sync API to import domain
            $result = rdasImportDomainViaTLDSync($tld);
            if ($result['success']) {
                $imported++;
            } else {
                $errors[] = $result['message'];
            }
        }
        
        $message = "Imported {$imported} domains";
        if (!empty($errors)) {
            $message .= ". Errors: " . implode(', ', array_slice($errors, 0, 3));
            if (count($errors) > 3) {
                $message .= " and " . (count($errors) - 3) . " more";
            }
        }
        
        return [
            'success' => $imported > 0,
            'imported' => $imported,
            'message' => $message
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Import failed: ' . $e->getMessage()];
    }
}

/**
 * Import domain via WHMCS TLD Sync API
 *
 * @param string $tld Domain extension (e.g., '.com')
 * @return array Result with success status and message
 */
function rdasImportDomainViaTLDSync($tld) {
    try {
        // Get addon configuration
        $config = rdasGetAddonConfig('rdas_pricing_updater');
        $marginType = $config['margin_type'] ?? 'percentage';
        $marginValue = floatval($config['profit_margin'] ?? $config['margin_value'] ?? 20);
        
        // Get WHMCS admin token
        $adminToken = rdasGetWHMCSAdminToken();
        if (!$adminToken) {
            return ['success' => false, 'message' => 'Unable to get WHMCS admin token'];
        }
        
        // Prepare TLD Sync API parameters
        $params = [
            'token' => $adminToken,
            'tld' => $tld,
            'margin_type' => $marginType,
            'margin' => $marginValue,
            'rounding_value' => '',
            'registrar' => 'rdash',
            'sync_redemption' => '0',
            'set_auto_register' => '0'
        ];
        
        // Make request to WHMCS TLD Sync API
        $url = rtrim(rdasGetWHMCSBaseURL(), '/') . '/admin/utilities/tools/tldsync/do-import';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return ['success' => true, 'message' => "Successfully imported {$tld}"];
        } else {
            return ['success' => false, 'message' => "Failed to import {$tld} (HTTP {$httpCode})"];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => "Error importing {$tld}: " . $e->getMessage()];
    }
}

/**
 * Get WHMCS admin token from session
 *
 * @return string|false Admin token or false if not found
 */
function rdasGetWHMCSAdminToken() {
    if (isset($_SESSION['adminid']) && isset($_SESSION['token'])) {
        return $_SESSION['token'];
    }
    return false;
}
 
 /**
  * Get WHMCS base URL
  *
  * @return string WHMCS base URL
  */
 function rdasGetWHMCSBaseURL() {
     // Get from configuration or use current domain
     $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
     $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
     return $protocol . '://' . $host;
 }
 
 /**
  * Check if domain exists in WHMCS domain configuration
  *
 * @param string $tld Domain extension (e.g., '.com')
 * @return bool True if domain exists in WHMCS
 */
function rdasDomainExistsInWHMCS($tld) {
    try {
        $result = select_query('tbldomainpricing', 'extension', ['extension' => $tld]);
        $data = mysql_fetch_array($result);
        return !empty($data);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Fetch domain prices from RDASH.ID API
 *
 * @param string $apiUrl API endpoint URL
 * @return array|false API response data or false on failure
 */
function rdasFetchDomainPrices($apiUrl) {
    try {
        // Initialize cURL
        $ch = curl_init();
        
        // Set cURL options
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'WHMCS-RDAS-Pricing-Updater/2.0.0',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'X-Requested-With: XMLHttpRequest'
            ]
        ]);
        
        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        // Check for cURL errors
        if ($error) {
            rdasLogToAddon('error', 'cURL error: ' . $error);
            return false;
        }
        
        // Check HTTP status code
        if ($httpCode !== 200) {
            rdasLogToAddon('error', 'API returned HTTP ' . $httpCode . ': ' . $response);
            return false;
        }
        
        // Decode JSON response
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            rdasLogToAddon('error', 'JSON decode error: ' . json_last_error_msg());
            return false;
        }
        
        // Validate response structure
        if (!is_array($data) || empty($data)) {
            rdasLogToAddon('error', 'Invalid API response structure');
            return false;
        }
        
        rdasLogToAddon('info', 'Successfully fetched ' . count($data) . ' domain prices from API');

        return $data;

    } catch (Exception $e) {
        rdasLogToAddon('error', 'fetchDomainPrices error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Apply margin to a price
 *
 * @param float $price Original price
 * @param string $marginType 'percentage' or 'fixed'
 * @param float $marginValue Margin value
 * @return float Price with margin applied
 */
function rdasApplyMargin($price, $marginType, $marginValue) {
    if ($price <= 0) {
        return 0;
    }

    if ($marginType === 'fixed') {
        return $price + $marginValue;
    } else {
        // Percentage margin
        return $price * (1 + ($marginValue / 100));
    }
}

/**
 * Calculate domain prices with margin and rounding
 * Handles promo prices with date validation
 *
 * @param array $domainData Raw domain data from API
 * @param array $config Addon configuration
 * @return array Calculated prices
 */
function rdasCalculateDomainPrices($domainData, $config) {
    try {
        $marginType = $config['margin_type'] ?? 'percentage';
        $marginValue = floatval($config['profit_margin'] ?? $config['default_margin'] ?? 20);
        $roundingRule = $config['rounding_rule'] ?? 'up_1000';
        $customRounding = floatval($config['custom_rounding'] ?? $config['custom_rounding_value'] ?? 1000);

        $prices = [];
        $now = time();

        // Check if promo is active
        $promoActive = false;
        $promoTerms = 1; // Default to year 1
        $promoData = $domainData['promo'] ?? null;

        if ($promoData && isset($promoData['registration']) && !empty($promoData['registration'])) {
            $startDate = isset($promoData['start_date']) ? strtotime($promoData['start_date']) : null;
            $endDate = isset($promoData['end_date']) ? strtotime($promoData['end_date']) : null;

            // Validate promo dates
            if ($startDate && $endDate && $now >= $startDate && $now <= $endDate) {
                $promoActive = true;
                // Get promo terms (year) - default to 1 if not specified
                $promoTerms = intval($promoData['terms'] ?? 1);
            }
        }

        // Map API price keys to WHMCS price keys
        $priceMapping = [
            'register' => 'registration',
            'renew' => 'renewal',
            'transfer' => 'transfer',
            'redemption' => 'redemption'
        ];

        foreach ($priceMapping as $whmcsKey => $apiKey) {
            $basePrice = rdasParsePrice($domainData[$apiKey] ?? 0);

            // Apply margin
            if ($marginType === 'percentage') {
                $finalPrice = $basePrice * (1 + ($marginValue / 100));
            } else {
                $finalPrice = $basePrice + $marginValue;
            }

            // Apply rounding
            $finalPrice = rdasApplyRounding($finalPrice, $roundingRule, $customRounding);

            $prices[$whmcsKey] = [
                'base' => $basePrice,
                'margin' => $marginValue,
                'final' => $finalPrice,
                'currency' => $domainData['currency'] ?? 'IDR'
            ];
        }

        // Handle promo price for specific terms (year)
        if ($promoActive && isset($promoData['registration'])) {
            $promoBasePrice = rdasParsePrice($promoData['registration']);

            // Apply margin to promo price
            if ($marginType === 'percentage') {
                $promoFinalPrice = $promoBasePrice * (1 + ($marginValue / 100));
            } else {
                $promoFinalPrice = $promoBasePrice + $marginValue;
            }

            // Apply rounding to promo price
            $promoFinalPrice = rdasApplyRounding($promoFinalPrice, $roundingRule, $customRounding);

            // Store promo info separately
            $prices['promo'] = [
                'terms' => $promoTerms,
                'base_price' => $promoBasePrice,
                'final_price' => $promoFinalPrice,
                'start_date' => $promoData['start_date'],
                'end_date' => $promoData['end_date']
            ];

            $prices['promo_applied'] = true;
            $prices['promo_start'] = $promoData['start_date'];
            $prices['promo_end'] = $promoData['end_date'];
        }

        // Add metadata
        $prices['extension'] = $domainData['extension'] ?? '';
        $prices['last_updated'] = date('Y-m-d H:i:s');
        $prices['promo_active'] = $promoActive;
        $prices['promo_terms'] = $promoTerms;

        return $prices;

    } catch (Exception $e) {
        rdasLogToAddon('error', 'calculateDomainPrices error: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Apply rounding rules to price
 *
 * @param float $price Original price
 * @param string $rule Rounding rule
 * @param float $customValue Custom rounding value
 * @return float Rounded price
 */
function rdasApplyRounding($price, $rule, $customValue = 1000) {
    switch ($rule) {
        case 'none':
            return $price;
            
        case 'up_1000':
            return ceil($price / 1000) * 1000;
            
        case 'up_5000':
            return ceil($price / 5000) * 5000;
            
        case 'nearest_1000':
            return round($price / 1000) * 1000;
            
        case 'custom':
            return ceil($price / $customValue) * $customValue;
            
        default:
            return $price;
    }
}

/**
 * Update domain pricing in WHMCS database
 *
 * @param string $extension Domain extension
 * @param array $prices Calculated prices
 * @param string|null $group Optional group name (auto-set based on promo if not provided)
 * @return bool Success status
 */
function rdasUpdateDomainPricing($extension, $prices, $group = null) {
    try {
        // Get existing domain pricing
        $domainId = rdasGetDomainId($extension);

        if (!$domainId) {
            // Create new domain entry
            $domainId = rdasCreateDomainEntry($extension);

            if (!$domainId) {
                throw new Exception('Failed to create domain entry for ' . $extension);
            }
        }

        // Get promo info if available
        $promoActive = !empty($prices['promo_active']);
        $promoTerms = intval($prices['promo_terms'] ?? 1);
        $promoData = $prices['promo'] ?? null;

        // Determine group - auto-set based on promo status if not provided
        if ($group === null) {
            $group = $promoActive ? 'Promo' : '';
        }

        // Update group in tbldomainpricing
        rdasUpdateDomainGroup($domainId, $group);

        // Update pricing for each year (1-10)
        $success = true;

        for ($year = 1; $year <= 10; $year++) {
            foreach (['register', 'renew', 'transfer'] as $type) {
                if (isset($prices[$type])) {
                    // Determine the price to use for this year
                    $priceToUse = $prices[$type]['final'];

                    // If promo is active and this is the promo year for registration
                    if ($promoActive && $year === $promoTerms && $type === 'register' && $promoData) {
                        $priceToUse = $promoData['final_price'];
                    }

                    $result = rdasUpdateDomainPriceEntry($domainId, $type, $year, $priceToUse);

                    if (!$result) {
                        $success = false;
                        rdasLogToAddon('warning', "Failed to update {$type} price for {$extension} year {$year}");
                    }
                }
            }
        }

        // Cache the pricing data
        rdasCachePricingData($extension, $prices);

        if ($success) {
            $promoInfo = $promoActive ? " (promo for year {$promoTerms}, group: {$group})" : "";
            rdasLogToAddon('info', "Successfully updated pricing for {$extension}{$promoInfo}");
        }

        return $success;

    } catch (Exception $e) {
        rdasLogToAddon('error', 'updateDomainPricing error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get domain ID from WHMCS database
 * Returns the ID that has pricing entries (to handle duplicates)
 *
 * @param string $extension Domain extension
 * @return int|false Domain ID or false if not found
 */
function rdasGetDomainId($extension) {
    try {
        // Sanitize extension to prevent SQL injection
        $extension = mysql_real_escape_string(trim($extension));

        // First, try to get the ID that already has pricing entries
        // This handles cases where there are duplicate extensions
        $query = "SELECT d.id FROM tbldomainpricing d
                  INNER JOIN tblpricing p ON p.relid = d.id AND p.type = 'domainregister'
                  WHERE d.extension = '{$extension}'
                  LIMIT 1";
        $result = full_query($query);

        if ($result && mysql_num_rows($result) > 0) {
            $row = mysql_fetch_array($result);
            return intval($row['id']);
        }

        // Fallback: just get the first ID found
        $query = "SELECT id FROM tbldomainpricing WHERE extension = '{$extension}' LIMIT 1";
        $result = full_query($query);

        if ($result && mysql_num_rows($result) > 0) {
            $row = mysql_fetch_array($result);
            return intval($row['id']);
        }

        return false;

    } catch (Exception $e) {
        rdasLogToAddon('error', 'getDomainId error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Create new domain entry in WHMCS
 * Checks for existing entry first to avoid duplicates
 *
 * @param string $extension Domain extension
 * @return int|false Domain ID (existing or new) or false on failure
 */
function rdasCreateDomainEntry($extension) {
    try {
        // Check if extension already exists
        $existingId = rdasGetDomainId($extension);
        if ($existingId) {
            return $existingId;
        }

        $insertId = insert_query('tbldomainpricing', [
            'extension' => $extension,
            'dnsmanagement' => '0',
            'emailforwarding' => '0',
            'idprotection' => '0',
            'eppcode' => '0'
        ]);
        return $insertId ? (int)$insertId : false;
    } catch (Exception $e) {
        rdasLogToAddon('error', 'createDomainEntry error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Update domain group in WHMCS
 * Updates ALL entries with the same extension (handles duplicates)
 *
 * @param int $domainId Domain ID
 * @param string $group Group name (e.g., 'Promo', 'Sale', or empty to clear)
 * @return bool Success status
 */
function rdasUpdateDomainGroup($domainId, $group) {
    try {
        $domainId = intval($domainId);
        $group = mysql_real_escape_string(trim($group));

        // First get the extension for this domain
        $getExtensionQuery = "SELECT extension FROM tbldomainpricing WHERE id = {$domainId} LIMIT 1";
        $extResult = full_query($getExtensionQuery);

        if ($extResult && mysql_num_rows($extResult) > 0) {
            $extRow = mysql_fetch_array($extResult);
            $extension = mysql_real_escape_string($extRow['extension']);

            // Update ALL entries with this extension (handles duplicates)
            $query = "UPDATE tbldomainpricing SET `group` = '{$group}' WHERE extension = '{$extension}'";
            $result = full_query($query);

            if ($result) {
                rdasLogToAddon('info', "Updated group to '{$group}' for {$extension}");
                return true;
            }
        }

        return false;

    } catch (Exception $e) {
        rdasLogToAddon('error', 'updateDomainGroup error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Update individual domain price entry
 *
 * @param int $domainId Domain ID
 * @param string $type Price type (register, renew, transfer)
 * @param int $year Number of years
 * @param float $price Price value
 * @return bool Success status
 */
function rdasUpdateDomainPriceEntry($domainId, $type, $year, $price) {
    try {
        // WHMCS 8/9 structure - use tblpricing table
        // Map year to column name
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

        $column = $yearColumnMap[$year] ?? 'msetupfee';
        $domainId = intval($domainId);
        $price = floatval($price);
        $currency = 1; // Default currency

        // Map type to tblpricing type - validate to prevent SQL injection
        $allowedTypes = ['domainregister', 'domainrenew', 'domaintransfer'];
        $pricingType = 'domain' . $type;
        if (!in_array($pricingType, $allowedTypes)) {
            throw new Exception('Invalid pricing type');
        }

        // Check if pricing entry exists - include currency in check
        $checkQuery = "SELECT id FROM tblpricing WHERE type = '{$pricingType}' AND relid = {$domainId} AND currency = {$currency} LIMIT 1";
        $existing = full_query($checkQuery);

        if ($existing && mysql_num_rows($existing) > 0) {
            // Update existing
            $row = mysql_fetch_array($existing);
            $pricingId = intval($row['id']);

            $updateQuery = "UPDATE tblpricing SET {$column} = {$price} WHERE id = {$pricingId}";
            full_query($updateQuery);

            rdasLogToAddon('info', "Updated {$pricingType} year {$year} ({$column}) = {$price} for domain ID {$domainId}");
        } else {
            // Insert new pricing entry
            $insertQuery = "INSERT INTO tblpricing (type, currency, relid, {$column}) VALUES ('{$pricingType}', {$currency}, {$domainId}, {$price})";
            full_query($insertQuery);

            rdasLogToAddon('info', "Inserted {$pricingType} year {$year} ({$column}) = {$price} for domain ID {$domainId}");
        }

        return true;

    } catch (Exception $e) {
        rdasLogToAddon('error', 'updateDomainPriceEntry error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Cache pricing data for quick access
 *
 * @param string $extension Domain extension
 * @param array $prices Pricing data
 * @return bool Success status
 */
function rdasCachePricingData($extension, $prices) {
    try {
        $data = json_encode($prices);
        $query = "INSERT INTO mod_rdas_pricing_cache (extension, api_data, last_updated) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE api_data = ?, last_updated = NOW()";
        $result = full_query($query, [$extension, $data, $data]);
        
        return $result !== false;
        
    } catch (Exception $e) {
        rdasLogToAddon('error', 'cachePricingData error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get cached pricing data
 *
 * @param string $extension Domain extension
 * @return array|false Cached data or false if not found
 */
function rdasGetCachedPricingData($extension) {
    try {
        $query = "SELECT api_data, last_updated FROM mod_rdas_pricing_cache WHERE extension = ?";
        $result = full_query($query, [$extension]);
        
        if ($result) {
            $row = mysql_fetch_array($result);
            $data = json_decode($row['api_data'], true);
            $data['cached_at'] = $row['last_updated'];
            
            return $data;
        }
        
        return false;
        
    } catch (Exception $e) {
        rdasLogToAddon('error', 'getCachedPricingData error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get all domain extensions from WHMCS
 *
 * @return array List of domain extensions
 */
function rdasGetAllDomainExtensions() {
    try {
        $query = "SELECT DISTINCT extension FROM tbldomainpricing ORDER BY extension";
        $result = full_query($query);
        
        $extensions = [];
        
        if ($result) {
            while ($row = mysql_fetch_array($result)) {
                $extensions[] = $row['extension'];
            }
        }
        
        return $extensions;
        
    } catch (Exception $e) {
        rdasLogToAddon('error', 'getAllDomainExtensions error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get domain pricing comparison data
 *
 * @param string $extension Domain extension
 * @return array Comparison data
 */
function rdasGetDomainPricingComparison($extension) {
    try {
        // Get current WHMCS pricing
        $query = "SELECT * FROM tbldomainpricing WHERE extension = ?";
        $result = full_query($query, [$extension]);
        
        $currentPricing = [];
        if ($result) {
            $currentPricing = mysql_fetch_array($result);
        }
        
        // Get cached API data
        $cachedData = rdasGetCachedPricingData($extension);
        
        return [
            'extension' => $extension,
            'current' => $currentPricing,
            'api_data' => $cachedData,
            'last_sync' => $cachedData['cached_at'] ?? null
        ];
        
    } catch (Exception $e) {
        rdasLogToAddon('error', 'getDomainPricingComparison error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Format currency amount
 *
 * @param float $amount Amount to format
 * @param string $currency Currency code
 * @return string Formatted currency
 */
function rdasFormatCurrency($amount, $currency = 'IDR') {
    switch ($currency) {
        case 'IDR':
            return 'Rp ' . number_format($amount, 0, ',', '.');
        case 'USD':
            return '$' . number_format($amount, 2, '.', ',');
        default:
            return $currency . ' ' . number_format($amount, 2, '.', ',');
    }
}

/**
 * Log message to addon log table
 *
 * @param string $level Log level (error, warning, info, debug)
 * @param string $message Log message
 * @param array $data Additional data (optional)
 * @return bool Success status
 */
function rdasLogToAddon($level, $message, $data = null) {
    try {
        $config = rdasGetAddonConfig('rdas_pricing_updater');
        $logLevel = $config['log_level'] ?? 'info';
        
        // Check if we should log this level
        $levels = ['error' => 0, 'warning' => 1, 'info' => 2, 'debug' => 3];
        $currentLevel = $levels[$logLevel] ?? 2;
        $messageLevel = $levels[$level] ?? 2;
        
        if ($messageLevel > $currentLevel) {
            return true; // Skip logging
        }
        
        $dataJson = $data ? json_encode($data) : null;
        $result = insert_query('mod_rdas_pricing_updater_log', array(
            'date' => 'NOW()',
            'level' => $level,
            'message' => $message,
            'data' => $dataJson
        ));
        
        return $result !== false;
        
    } catch (Exception $e) {
        // Fallback to WHMCS activity log
        if (function_exists('logActivity')) {
            logActivity('RDAS Pricing Updater [' . $level . ']: ' . $message);
        }
        return false;
    }
}

/**
 * Get addon logs with pagination
 *
 * @param int $page Page number
 * @param int $limit Records per page
 * @param string $level Filter by log level
 * @param string $search Search term (optional)
 * @return array Log data with total count
 */
function rdasGetAddonLogs($page = 1, $limit = 50, $level = '', $search = '') {
    try {
        $offset = ($page - 1) * $limit;

        // Build condition
        $conditions = [];
        if (!empty($level)) {
            $conditions[] = "level = '" . db_escape_string($level) . "'";
        }
        if (!empty($search)) {
            $conditions[] = "message LIKE '%" . db_escape_string($search) . "%'";
        }
        $condition = !empty($conditions) ? implode(' AND ', $conditions) : '';

        // Get total count
        $countResult = select_query('mod_rdas_pricing_updater_log', 'COUNT(*) as total', $condition);
        $totalRecords = 0;
        if ($countResult) {
            $row = mysql_fetch_array($countResult);
            $totalRecords = intval($row['total']);
        }

        // Get logs
        $result = select_query('mod_rdas_pricing_updater_log', '*', $condition, 'date', 'DESC', $offset . ',' . $limit);

        $logs = [];
        if ($result) {
            while ($row = mysql_fetch_array($result)) {
                $logs[] = [
                    'id' => $row['id'],
                    'date' => $row['date'],
                    'level' => $row['level'],
                    'message' => $row['message'],
                    'data' => $row['data'] ? json_decode($row['data'], true) : null
                ];
            }
        }

        return [
            'logs' => $logs,
            'total' => $totalRecords
        ];

    } catch (Exception $e) {
        rdasLogToAddon('error', 'getAddonLogs error: ' . $e->getMessage());
        return ['logs' => [], 'total' => 0];
    }
}

/**
 * Clean old log entries
 *
 * @param int $days Number of days to keep
 * @return int Number of deleted entries
 */
function rdasCleanOldLogs($days = 30) {
    try {
        $days = max(1, intval($days));
        $query = "DELETE FROM mod_rdas_pricing_updater_log WHERE date < DATE_SUB(NOW(), INTERVAL {$days} DAY)";
        full_query($query);

        // Get affected rows using MySQL function
        $countResult = select_query('mod_rdas_pricing_updater_log', 'COUNT(*) as total', '');
        $remaining = 0;
        if ($countResult) {
            $row = mysql_fetch_array($countResult);
            $remaining = intval($row['total']);
        }

        rdasLogToAddon('info', "Cleaned old log entries (older than {$days} days), {$remaining} remaining");
        return $remaining;

    } catch (Exception $e) {
        rdasLogToAddon('error', 'cleanOldLogs error: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Validate API response structure
 *
 * @param array $data API response data
 * @return bool Validation result
 */
function rdasValidateApiResponse($data) {
    if (!is_array($data) || empty($data)) {
        return false;
    }
    
    foreach ($data as $domain) {
        if (!isset($domain['extension']) || !isset($domain['register'])) {
            return false;
        }
    }
    
    return true;
}

/**
 * Get addon statistics
 *
 * @return array Statistics data
 */
function rdasGetAddonStatistics() {
    try {
        $stats = [];
        
        // Total domains in WHMCS
        $query = "SELECT COUNT(*) as total FROM tbldomainpricing";
        $result = full_query($query);
        if ($result) {
            $row = mysql_fetch_array($result);
            $stats['total_domains'] = intval($row['total']);
        }
        
        // Cached domains
        $query = "SELECT COUNT(*) as total FROM mod_rdas_pricing_cache";
        $result = full_query($query);
        if ($result) {
            $row = mysql_fetch_array($result);
            $stats['cached_domains'] = intval($row['total']);
        }
        
        // Recent updates (last 24 hours)
        $query = "SELECT COUNT(*) as total FROM mod_rdas_pricing_cache WHERE last_updated >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $result = full_query($query);
        if ($result) {
            $row = mysql_fetch_array($result);
            $stats['recent_updates'] = intval($row['total']);
        }
        
        // Log entries (last 7 days)
        $query = "SELECT COUNT(*) as total FROM mod_rdas_pricing_updater_log WHERE date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $result = full_query($query);
        if ($result) {
            $row = mysql_fetch_array($result);
            $stats['recent_logs'] = intval($row['total']);
        }
        
        // Error count (last 24 hours)
        $query = "SELECT COUNT(*) as total FROM mod_rdas_pricing_updater_log WHERE level = 'error' AND date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $result = full_query($query);
        if ($result) {
            $row = mysql_fetch_array($result);
            $stats['recent_errors'] = intval($row['total']);
        }
        
        return $stats;
        
    } catch (Exception $e) {
        rdasLogToAddon('error', 'getAddonStatistics error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Test database connection
 *
 * @return bool Connection status
 */
function rdasTestDatabaseConnection() {
    try {
        $query = "SELECT 1";
        $result = full_query($query);
        
        return $result !== false;
        
    } catch (Exception $e) {
        rdasLogToAddon('error', 'testDatabaseConnection error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get existing TLDs from WHMCS database
 *
 * @return array List of existing TLD extensions
 */
function rdasGetExistingTLDs() {
    try {
        $query = "SELECT DISTINCT extension FROM tbldomainpricing WHERE extension != ''";
        $result = full_query($query);
        
        $tlds = [];
        while ($row = mysql_fetch_array($result)) {
            $tlds[] = $row['extension'];
        }
        
        return $tlds;
        
    } catch (Exception $e) {
        rdasLogToAddon('error', 'getExistingTLDs error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get addon configuration values
 *
 * @param string $addonName Addon module name
 * @return array Configuration array
 */
function rdasGetAddonConfig($addonName) {
    $config = [];

    $result = select_query('tbladdonmodules', 'setting,value', array('module' => $addonName));
    while ($data = mysql_fetch_array($result)) {
        $config[$data['setting']] = $data['value'];
    }

    return $config;
}

/**
 * Get addon version
 *
 * @return string Version number
 */
function rdasGetAddonVersion() {
    return '2.1.9';
}

/**
 * Check if addon tables exist
 *
 * @return array Table existence status
 */
function rdasCheckAddonTables() {
    try {
        $tables = [
            'mod_rdas_pricing_updater_log',
            'mod_rdas_pricing_cache'
        ];

        $status = [];

        foreach ($tables as $table) {
            $query = "SHOW TABLES LIKE '{$table}'";
            $result = full_query($query);
            $status[$table] = (bool)$result;
        }

        return $status;

    } catch (Exception $e) {
        rdasLogToAddon('error', 'checkAddonTables error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Generate CSRF token (centralized function)
 *
 * @param string $context Optional context for different tokens
 * @return string CSRF token
 */
function rdasGenerateCSRFToken($context = 'default') {
    $key = 'rdas_csrf_token_' . $context;
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = bin2hex(random_bytes(32));
    }
    return $_SESSION[$key];
}

/**
 * Validate CSRF token
 *
 * @param string $token Token to validate
 * @param string $context Context used when generating token
 * @return bool True if valid
 */
function rdasValidateCSRFToken($token, $context = 'default') {
    $key = 'rdas_csrf_token_' . $context;
    return isset($_SESSION[$key]) && hash_equals($_SESSION[$key], $token);
}

/**
 * Make HTTP request using cURL (centralized helper)
 *
 * @param string $url Request URL
 * @param array $options Request options
 * @return array Response with success, http_code, data, error
 */
function rdasHttpRequest($url, $options = []) {
    $defaults = [
        'method' => 'GET',
        'timeout' => 30,
        'connect_timeout' => 10,
        'headers' => [],
        'data' => null,
        'user_agent' => 'WHMCS-RDAS-Pricing-Updater/2.1.9'
    ];
    $options = array_merge($defaults, $options);

    try {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $options['timeout'],
            CURLOPT_CONNECTTIMEOUT => $options['connect_timeout'],
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => $options['user_agent'],
            CURLOPT_HTTPHEADER => $options['headers']
        ]);

        if ($options['method'] === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($options['data'] !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($options['data'])
                    ? http_build_query($options['data'])
                    : $options['data']);
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'http_code' => $httpCode, 'data' => null, 'error' => $error];
        }

        $decoded = json_decode($response, true);
        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'data' => $decoded ?: $response,
            'error' => null
        ];

    } catch (Exception $e) {
        return ['success' => false, 'http_code' => 0, 'data' => null, 'error' => $e->getMessage()];
    }
}

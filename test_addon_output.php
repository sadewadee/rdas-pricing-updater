<?php
/**
 * Test RDAS Pricing Updater Output Function with Real Implementation
 */

echo "Testing actual rdas_pricing_updater_output function...\n";

try {
    // Define WHMCS constant
    if (!defined('WHMCS')) {
        define('WHMCS', true);
    }
    
    // Mock WHMCS functions
    if (!function_exists('add_hook')) {
        function add_hook($hookPoint, $priority, $function) {
            return true;
        }
    }
    
    if (!function_exists('select_query')) {
        function select_query($table, $fields, $where = []) {
            return 'mock_result';
        }
    }
    
    if (!function_exists('mysql_fetch_array')) {
        function mysql_fetch_array($result) {
            static $configIndex = 0;
            $mockConfig = [
                ['setting' => 'api_url', 'value' => 'https://api.rdash.id/api/domain-prices?currency=IDR'],
                ['setting' => 'margin_type', 'value' => 'percentage'],
                ['setting' => 'profit_margin', 'value' => '20'],
                ['setting' => 'rounding_rule', 'value' => 'up_1000'],
                ['setting' => 'custom_rounding', 'value' => '1000'],
                ['setting' => 'log_level', 'value' => 'info']
            ];
            
            if ($configIndex < count($mockConfig)) {
                return $mockConfig[$configIndex++];
            }
            return false;
        }
    }
    
    if (!function_exists('full_query')) {
        function full_query($query, $params = []) {
            return true;
        }
    }
    
    if (!function_exists('insert_query')) {
        function insert_query($table, $data) {
            return true;
        }
    }
    
    // Include the actual addon files
    echo "Including addon files...\n";
    require_once 'rdas_pricing_updater.php';
    require_once 'lib/functions.php';
    
    echo "Testing rdas_pricing_updater_output function...\n";
    
    // Capture output
    ob_start();
    $result = rdas_pricing_updater_output([]);
    $output = ob_get_clean();
    
    echo "Function executed successfully!\n";
    echo "Output length: " . strlen($output) . " characters\n";
    
    // Check for key elements that should be present with margin implementation
    $checks = [
        'Domain Pricing title' => strpos($output, 'Domain Pricing') !== false,
        'Margin information' => strpos($output, 'Margin:') !== false,
        'Rounding information' => strpos($output, 'Rounding:') !== false,
        'Base Registration column' => strpos($output, 'Base Registration') !== false,
        'Final Registration column' => strpos($output, 'Final Registration') !== false,
        'Base Renewal column' => strpos($output, 'Base Renewal') !== false,
        'Final Renewal column' => strpos($output, 'Final Renewal') !== false,
        'Base Transfer column' => strpos($output, 'Base Transfer') !== false,
        'Final Transfer column' => strpos($output, 'Final Transfer') !== false,
        'Table structure' => strpos($output, '<table') !== false,
        'Panel structure' => strpos($output, 'panel-default') !== false,
        'Bootstrap classes' => strpos($output, 'container-fluid') !== false,
        'TLD column' => strpos($output, '<th>TLD</th>') !== false,
        'Type column' => strpos($output, '<th>Type</th>') !== false,
        'Description column' => strpos($output, '<th>Description</th>') !== false
    ];
    
    echo "\nChecking output content:\n";
    $passCount = 0;
    foreach ($checks as $check => $result) {
        $status = $result ? 'PASS' : 'FAIL';
        echo "- {$check}: {$status}\n";
        if ($result) $passCount++;
    }
    
    echo "\nTest Results: {$passCount}/" . count($checks) . " checks passed\n";
    
    // Show preview of output
    echo "\nOutput preview (first 1000 characters):\n";
    echo substr($output, 0, 1000) . "...\n";
    
    // Check if margin calculation is working
    if (strpos($output, 'Margin: 20%') !== false) {
        echo "\n✅ Margin configuration (20%) detected in output!\n";
    } else {
        echo "\n❌ Margin configuration not found in output\n";
    }
    
    if (strpos($output, 'Rounding: Up 1000') !== false) {
        echo "✅ Rounding configuration (up_1000) detected in output!\n";
    } else {
        echo "❌ Rounding configuration not found in output\n";
    }
    
    echo "\nTest completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error during test: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "Fatal error during test: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\nTest script finished.\n";
?>
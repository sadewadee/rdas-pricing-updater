<?php
/**
 * Test Dashboard Functionality
 * 
 * Script untuk menguji apakah dashboard bisa berjalan tanpa error
 */

// Simulate WHMCS environment
define("WHMCS", true);

// Include compatibility layer first
require_once __DIR__ . '/lib/whmcs_compatibility.php';

// Include required files
require_once __DIR__ . '/lib/functions.php';
require_once __DIR__ . '/pages/dashboard.php';

echo "<h2>Testing Dashboard Functions</h2>";

try {
    echo "<h3>1. Testing getDashboardStatistics()</h3>";
    if (function_exists('getDashboardStatistics')) {
        $stats = getDashboardStatistics();
        echo "<pre>" . print_r($stats, true) . "</pre>";
        echo "<span style='color: green;'>✓ getDashboardStatistics() works</span><br>";
    } else {
        echo "<span style='color: red;'>✗ getDashboardStatistics() not found</span><br>";
    }
    
    echo "<h3>2. Testing checkApiStatus()</h3>";
    if (function_exists('checkApiStatus')) {
        $api_status = checkApiStatus();
        echo "<pre>" . print_r($api_status, true) . "</pre>";
        echo "<span style='color: green;'>✓ checkApiStatus() works</span><br>";
    } else {
        echo "<span style='color: red;'>✗ checkApiStatus() not found</span><br>";
    }
    
    echo "<h3>3. Testing getPricingSummary()</h3>";
    if (function_exists('getPricingSummary')) {
        $pricing_summary = getPricingSummary();
        echo "<pre>" . print_r($pricing_summary, true) . "</pre>";
        echo "<span style='color: green;'>✓ getPricingSummary() works</span><br>";
    } else {
        echo "<span style='color: red;'>✗ getPricingSummary() not found</span><br>";
    }
    
    echo "<h3>4. Testing getSystemHealth()</h3>";
    if (function_exists('getSystemHealth')) {
        $system_health = getSystemHealth();
        echo "<pre>" . print_r($system_health, true) . "</pre>";
        echo "<span style='color: green;'>✓ getSystemHealth() works</span><br>";
    } else {
        echo "<span style='color: red;'>✗ getSystemHealth() not found</span><br>";
    }
    
    echo "<h3>5. Testing rdasGetAddonLogs()</h3>";
    if (function_exists('rdasGetAddonLogs')) {
        $logs = rdasGetAddonLogs(1, 5, '');
        echo "<pre>" . print_r($logs, true) . "</pre>";
        echo "<span style='color: green;'>✓ rdasGetAddonLogs() works</span><br>";
    } else {
        echo "<span style='color: red;'>✗ rdasGetAddonLogs() not found</span><br>";
    }
    
    echo "<h3>6. Testing showDashboardPage()</h3>";
    if (function_exists('showDashboardPage')) {
        $vars = array('modulelink' => 'test_link');
        $output = showDashboardPage($vars);
        echo "<span style='color: green;'>✓ showDashboardPage() executed successfully</span><br>";
        echo "<p>Output length: " . strlen($output) . " characters</p>";
    } else {
        echo "<span style='color: red;'>✗ showDashboardPage() not found</span><br>";
    }
    
} catch (Exception $e) {
    echo "<span style='color: red;'>✗ Error: " . $e->getMessage() . "</span><br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<h2>Test Complete</h2>";
?>
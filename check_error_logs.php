<?php
/**
 * RDAS Pricing Updater - Check Error Logs Script
 * 
 * Script untuk memeriksa error logs di database mod_rdas_pricing_updater_log
 * untuk mengidentifikasi penyebab error "An error occurred. Please try again."
 *
 * @package    WHMCS
 * @author     Morden Team
 * @version    1.0.0
 */

// Include WHMCS configuration if available
if (file_exists('../../../configuration.php')) {
    require_once '../../../configuration.php';
} elseif (file_exists('../../configuration.php')) {
    require_once '../../configuration.php';
} elseif (file_exists('../configuration.php')) {
    require_once '../configuration.php';
}

// Include WHMCS functions if available
if (file_exists('../../../includes/functions.php')) {
    require_once '../../../includes/functions.php';
} elseif (file_exists('../../includes/functions.php')) {
    require_once '../../includes/functions.php';
} elseif (file_exists('../includes/functions.php')) {
    require_once '../includes/functions.php';
}

echo "=== RDAS Pricing Updater Error Log Check ===\n\n";

try {
    // Check if log table exists
    $tableCheck = "SHOW TABLES LIKE 'mod_rdas_pricing_updater_log'";
    $result = full_query($tableCheck);
    
    if (!$result) {
        echo "❌ Log table 'mod_rdas_pricing_updater_log' tidak ditemukan!\n";
        echo "   Addon mungkin belum diaktivasi dengan benar.\n\n";
        exit;
    }
    
    echo "✅ Log table ditemukan\n\n";
    
    // Get recent error logs (last 24 hours)
    echo "=== ERROR LOGS (24 jam terakhir) ===\n";
    $errorQuery = "SELECT date, level, message, data 
                   FROM mod_rdas_pricing_updater_log 
                   WHERE level = 'error' 
                   AND date >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
                   ORDER BY date DESC 
                   LIMIT 20";
    
    $errorResult = full_query($errorQuery);
    
    if (!$errorResult) {
        echo "✅ Tidak ada error logs dalam 24 jam terakhir\n\n";
    } else {
        $errorCount = 0;
        while ($row = mysql_fetch_array($errorResult)) {
            $errorCount++;
            echo "[{$row['date']}] {$row['level']}: {$row['message']}\n";
            if (!empty($row['data'])) {
                echo "   Data: {$row['data']}\n";
            }
            echo "\n";
        }
        echo "Total errors: {$errorCount}\n\n";
    }
    
    // Get recent warning logs
    echo "=== WARNING LOGS (24 jam terakhir) ===\n";
    $warningQuery = "SELECT date, level, message, data 
                     FROM mod_rdas_pricing_updater_log 
                     WHERE level = 'warning' 
                     AND date >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
                     ORDER BY date DESC 
                     LIMIT 10";
    
    $warningResult = full_query($warningQuery);
    
    if (!$warningResult) {
        echo "✅ Tidak ada warning logs dalam 24 jam terakhir\n\n";
    } else {
        $warningCount = 0;
        while ($row = mysql_fetch_array($warningResult)) {
            $warningCount++;
            echo "[{$row['date']}] {$row['level']}: {$row['message']}\n";
            if (!empty($row['data'])) {
                echo "   Data: {$row['data']}\n";
            }
            echo "\n";
        }
        echo "Total warnings: {$warningCount}\n\n";
    }
    
    // Check addon configuration
    echo "=== ADDON CONFIGURATION ===\n";
    $configQuery = "SELECT setting, value FROM tbladdonmodules WHERE module = 'rdas_pricing_updater'";
    $configResult = full_query($configQuery);
    
    if (!$configResult) {
        echo "❌ Addon configuration tidak ditemukan di tbladdonmodules!\n";
        echo "   Addon mungkin belum diaktivasi.\n\n";
    } else {
        echo "✅ Addon configuration ditemukan:\n";
        while ($row = mysql_fetch_array($configResult)) {
            $value = strlen($row['value']) > 50 ? substr($row['value'], 0, 50) . '...' : $row['value'];
            echo "   {$row['setting']}: {$value}\n";
        }
        echo "\n";
    }
    
    // Check required tables
    echo "=== TABLE CHECK ===\n";
    $tables = [
        'mod_rdas_pricing_updater_log',
        'mod_rdas_pricing_cache',
        'tbldomainpricing',
        'tbladdonmodules'
    ];
    
    foreach ($tables as $table) {
        $tableQuery = "SHOW TABLES LIKE '{$table}'";
        $tableResult = full_query($tableQuery);
        
        if ($tableResult) {
            echo "✅ {$table}\n";
        } else {
            echo "❌ {$table} - MISSING!\n";
        }
    }
    
    echo "\n=== SUMMARY ===\n";
    echo "Script completed successfully.\n";
    echo "Jika tidak ada error logs yang ditemukan, masalah mungkin ada di:\n";
    echo "1. PHP error logs (check server error logs)\n";
    echo "2. WHMCS activity logs\n";
    echo "3. Browser console errors (JavaScript)\n";
    echo "4. Missing file permissions\n";
    echo "5. Database connection issues\n\n";
    
} catch (Exception $e) {
    echo "❌ Error saat menjalankan script: " . $e->getMessage() . "\n";
    echo "\nPossible causes:\n";
    echo "1. WHMCS configuration file tidak ditemukan\n";
    echo "2. Database connection error\n";
    echo "3. Missing permissions\n";
}

echo "=== END OF LOG CHECK ===\n";
?>
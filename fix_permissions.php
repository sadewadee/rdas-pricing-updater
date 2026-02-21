<?php
/**
 * RDAS Pricing Updater - Fix Permissions Script
 * 
 * Script untuk memperbaiki masalah "Can't change Addon Module Access Permissions"
 * dengan menambahkan entry 'access' yang hilang di database tbladdonmodules
 *
 * @package    WHMCS
 * @author     Morden Team
 * @version    1.0.0
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Fix addon permissions by adding missing 'access' entry
 * 
 * Fungsi ini akan:
 * 1. Memeriksa apakah entry 'access' sudah ada
 * 2. Jika belum ada, menambahkan entry dengan nilai default (semua admin roles)
 * 3. Memberikan feedback hasil operasi
 */
function fixAddonPermissions() {
    $moduleName = 'rdas_pricing_updater';
    
    try {
        // Check if access entry already exists
        $existingAccess = select_query('tbladdonmodules', 'value', array(
            'module' => $moduleName,
            'setting' => 'access'
        ));
        
        $accessData = mysql_fetch_array($existingAccess);
        if ($accessData) {
            return array(
                'success' => true,
                'message' => 'Access control entry already exists',
                'current_value' => $accessData['value']
            );
        }
        
        // Get all admin roles to set as default access
        $adminRoles = select_query('tbladminroles', 'id,name', array(), 'name', 'ASC');
        $roleIds = array();
        
        while ($role = mysql_fetch_array($adminRoles)) {
            $roleIds[] = $role['id'];
        }
        
        // Create access control entry with all admin roles
        $accessValue = implode(',', $roleIds);
        
        $insertResult = insert_query('tbladdonmodules', array(
            'module' => $moduleName,
            'setting' => 'access',
            'value' => $accessValue
        ));
        
        if ($insertResult) {
            return array(
                'success' => true,
                'message' => 'Access control entry created successfully',
                'access_value' => $accessValue,
                'admin_roles' => $roleIds
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Failed to create access control entry'
            );
        }
        
    } catch (Exception $e) {
        return array(
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        );
    }
}

/**
 * Display current addon permissions status
 */
function displayPermissionsStatus() {
    $moduleName = 'rdas_pricing_updater';
    
    echo "=== RDAS Pricing Updater - Permissions Status ===\n\n";
    
    try {
        // Get all addon settings
        $settings = select_query('tbladdonmodules', '*', array('module' => $moduleName), 'setting', 'ASC');
        
        $hasAccess = false;
        $accessValue = '';
        $totalSettings = 0;
        
        echo "Current addon configuration:\n";
        echo "============================\n";
        
        while ($setting = mysql_fetch_array($settings)) {
            echo $setting['setting'] . ' = ' . $setting['value'] . "\n";
            $totalSettings++;
            
            if ($setting['setting'] == 'access') {
                $hasAccess = true;
                $accessValue = $setting['value'];
            }
        }
        
        echo "\nTotal settings: " . $totalSettings . "\n";
        
        if ($hasAccess) {
            echo "\n✅ Access control setting found\n";
            echo "Access value: " . $accessValue . "\n";
            
            // Decode admin roles
            if (!empty($accessValue)) {
                $roleIds = explode(',', $accessValue);
                echo "Allowed admin role IDs: " . implode(', ', $roleIds) . "\n";
                
                // Get role names
                foreach ($roleIds as $roleId) {
                    $roleQuery = select_query('tbladminroles', 'name', array('id' => $roleId));
                    if ($roleData = mysql_fetch_array($roleQuery)) {
                        echo "  - Role ID " . $roleId . ": " . $roleData['name'] . "\n";
                    }
                }
            }
        } else {
            echo "\n❌ Access control setting NOT found\n";
            echo "This is the cause of the permissions issue.\n";
        }
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

// If running directly (for testing)
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    echo "RDAS Pricing Updater - Permissions Fix Tool\n";
    echo "==========================================\n\n";
    
    echo "Note: This script should be run from WHMCS admin area or with proper WHMCS context.\n\n";
    
    // Display current status
    displayPermissionsStatus();
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "To fix permissions, call fixAddonPermissions() function\n";
    echo "from WHMCS admin context or addon activation.\n";
}

?>
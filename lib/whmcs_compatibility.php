<?php
/**
 * WHMCS Compatibility Layer
 * Provides function and class stubs for IDE compatibility
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// WHMCS Core Functions
if (!function_exists('add_hook')) {
    function add_hook($hookPoint, $priority, $function) {
        // Stub for IDE compatibility
    }
}

if (!function_exists('logActivity')) {
    function logActivity($message) {
        // Stub for IDE compatibility
    }
}

if (!function_exists('full_query')) {
    function full_query($query) {
        // Mock implementation for testing
        if (strpos($query, 'COUNT') !== false) {
            return array(array('total' => 5));
        }
        return array(
            array('id' => 1, 'date' => date('Y-m-d H:i:s'), 'level' => 'info', 'message' => 'Test log', 'data' => null)
        );
    }
}

if (!function_exists('select_query')) {
    function select_query($table, $fields, $where = '', $orderby = '', $order = 'ASC', $limit = '') {
        // Mock implementation for testing
        if ($fields === 'COUNT(*) as total') {
            return array(array('total' => 5));
        }
        return array(
            array('id' => 1, 'date' => date('Y-m-d H:i:s'), 'level' => 'info', 'message' => 'Test log', 'data' => null),
            array('id' => 2, 'date' => date('Y-m-d H:i:s'), 'level' => 'info', 'message' => 'Another test log', 'data' => null)
        );
    }
}

if (!function_exists('insert_query')) {
    function insert_query($table, $values) {
        // Mock implementation for testing
        return true;
    }
}

if (!function_exists('mysql_fetch_array')) {
    function mysql_fetch_array($result) {
        static $fetch_counter = array();
        $result_id = spl_object_hash((object)$result);
        
        if (!isset($fetch_counter[$result_id])) {
            $fetch_counter[$result_id] = 0;
        }
        
        if (is_array($result) && $fetch_counter[$result_id] < count($result)) {
            $row = $result[$fetch_counter[$result_id]];
            $fetch_counter[$result_id]++;
            return $row;
        }
        
        // Reset counter when done
        unset($fetch_counter[$result_id]);
        return false;
    }
}

if (!function_exists('getAddonConfig')) {
    function getAddonConfig($module) {
        return [];
    }
}

// WHMCS Database Capsule Mock
if (!class_exists('WHMCS\Database\Capsule\Manager')) {
    class WHMCS_Database_Capsule_Manager {
        public static function table($table) {
            return new WHMCS_Database_Query_Builder();
        }
        
        public static function schema() {
            return new WHMCS_Database_Schema_Builder();
        }
    }
    
    class WHMCS_Database_Query_Builder {
        public function where($column, $value) {
            return $this;
        }
        
        public function count() {
            return 0;
        }
        
        public function pluck($value, $key = null) {
            return new WHMCS_Database_Collection();
        }
        
        public function insert($values) {
            return true;
        }
        
        public function get() {
            return new WHMCS_Database_Collection();
        }
        
        public function first() {
            return null;
        }
    }
    
    class WHMCS_Database_Schema_Builder {
        public function hasTable($table) {
            return false;
        }
        
        public function create($table, $callback) {
            return true;
        }
    }
    
    class WHMCS_Database_Collection {
        public function toArray() {
            return [];
        }
        
        public function isEmpty() {
            return true;
        }
    }
    
    // Create namespace alias
    if (!class_exists('WHMCS\Database\Capsule')) {
        class_alias('WHMCS_Database_Capsule_Manager', 'WHMCS\Database\Capsule');
    }
}
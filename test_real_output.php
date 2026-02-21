<?php
/**
 * Test script for real addon output with API data
 */

// Define WHMCS constant
define('WHMCS', true);

// Mock WHMCS functions
function add_hook($hookPoint, $priority, $function) {
    // Mock function
}

function select_query($table, $fields, $where = []) {
    // Mock function - return resource for mysql_fetch_array
    return 'mock_resource';
}

function mysql_fetch_array($resource) {
    // Mock function - return addon config
    static $called = false;
    if (!$called) {
        $called = true;
        return [
            'value' => json_encode([
                'margin_type' => 'percentage',
                'margin_value' => '20',
                'rounding_rule' => 'up_1000'
            ])
        ];
    }
    return false;
}

function Capsule() {
    return new class {
        public static function table($table) {
            return new class {
                public function where($column, $value) {
                    return $this;
                }
                public function first() {
                    // Mock addon configuration
                    return (object) [
                        'value' => json_encode([
                            'margin_type' => 'percentage',
                            'margin_value' => '20',
                            'rounding_rule' => 'up_1000'
                        ])
                    ];
                }
            };
        }
    };
}

// Include required files
require_once 'lib/functions.php';
require_once 'rdas_pricing_updater.php';

echo "Testing real addon output with API data:\n";
echo "========================================\n\n";

// Capture output
ob_start();
rdas_pricing_updater_output([]);
$output = ob_get_clean();

// Check if output contains expected elements
$checks = [
    'table' => strpos($output, '<table') !== false,
    'checkbox_header' => strpos($output, 'selectAll') !== false,
    'tld_column' => strpos($output, '<th>TLD</th>') !== false,
    'existing_tld_column' => strpos($output, '<th>Existing TLD</th>') !== false,
    'reg_period_column' => strpos($output, '<th>Reg Period</th>') !== false,
    'promo_base_column' => strpos($output, '<th>Promo<br><small>Base</small></th>') !== false,
    'margin_column' => strpos($output, '<th>Margin</th>') !== false,
    'javascript' => strpos($output, 'selectAll') !== false,
    'non_zero_prices' => preg_match('/\d{2,}/', $output) // Look for numbers with 2+ digits
];

$passed = 0;
$total = count($checks);

foreach ($checks as $check => $result) {
    $status = $result ? 'PASS' : 'FAIL';
    if ($result) $passed++;
    
    echo sprintf("%-20s: %s\n", ucfirst(str_replace('_', ' ', $check)), $status);
}

echo "\n========================================\n";
echo "Results: {$passed}/{$total} checks passed\n";

// Show sample of output for debugging
echo "\nSample output (first 500 chars):\n";
echo substr($output, 0, 500) . "...\n";

if ($passed === $total) {
    echo "\n✅ All checks passed!\n";
    exit(0);
} else {
    echo "\n❌ Some checks failed!\n";
    exit(1);
}
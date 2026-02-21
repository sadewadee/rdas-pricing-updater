<?php
/**
 * Test script for price parsing functionality
 */

// Define WHMCS constant to avoid die() in functions.php
define('WHMCS', true);

// Include the functions file
require_once 'lib/functions.php';

// Test cases for price parsing
$testCases = [
    'Rp45.000' => 45000,
    'Rp238.800' => 238800,
    'Rp1.500.000' => 1500000,
    'Rp199.000,50' => 199000.50,
    '45000' => 45000,
    '0' => 0,
    '' => 0,
    null => 0,
    'Rp0' => 0
];

echo "Testing rdasParsePrice function:\n";
echo "================================\n\n";

$passed = 0;
$total = count($testCases);

foreach ($testCases as $input => $expected) {
    $result = rdasParsePrice($input);
    $status = ($result == $expected) ? 'PASS' : 'FAIL';
    
    if ($status === 'PASS') {
        $passed++;
    }
    
    echo sprintf("Input: %-15s | Expected: %-10s | Result: %-10s | %s\n", 
        var_export($input, true), 
        $expected, 
        $result, 
        $status
    );
}

echo "\n================================\n";
echo "Results: {$passed}/{$total} tests passed\n";

if ($passed === $total) {
    echo "✅ All tests passed!\n";
    exit(0);
} else {
    echo "❌ Some tests failed!\n";
    exit(1);
}
<?php
/**
 * Test API Structure and Data
 */

echo "Testing API structure and data...\n";

try {
    $currency = 'IDR';
    $apiUrl = "https://api.rdash.id/api/domain-prices?currency=" . $currency;
    
    echo "Making API call to: " . $apiUrl . "\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Code: " . $httpCode . "\n";
    
    if ($httpCode === 200 && $response) {
        $pricingData = json_decode($response, true);
        if ($pricingData && is_array($pricingData)) {
            echo "API data received successfully!\n";
            echo "Number of domains: " . count($pricingData) . "\n\n";
            
            // Show structure of first few domains
            echo "=== FIRST 3 DOMAINS STRUCTURE ===\n";
            for ($i = 0; $i < min(3, count($pricingData)); $i++) {
                echo "Domain #" . ($i + 1) . ":\n";
                echo "Raw data: " . json_encode($pricingData[$i], JSON_PRETTY_PRINT) . "\n";
                
                $domain = $pricingData[$i];
                echo "Parsed values:\n";
                echo "- Extension: " . ($domain['extension'] ?? 'NOT_FOUND') . "\n";
                echo "- Type: " . ($domain['type'] ?? 'NOT_FOUND') . "\n";
                echo "- Registration: " . ($domain['registration'] ?? 'NOT_FOUND') . " (" . gettype($domain['registration'] ?? null) . ")\n";
                echo "- Renewal: " . ($domain['renewal'] ?? 'NOT_FOUND') . " (" . gettype($domain['renewal'] ?? null) . ")\n";
                echo "- Transfer: " . ($domain['transfer'] ?? 'NOT_FOUND') . " (" . gettype($domain['transfer'] ?? null) . ")\n";
                echo "- Description: " . substr($domain['description'] ?? 'NOT_FOUND', 0, 50) . "...\n";
                echo "\n";
            }
            
            // Check for domains with non-zero prices
            echo "=== CHECKING FOR NON-ZERO PRICES ===\n";
            $nonZeroCount = 0;
            $sampleNonZero = [];
            
            foreach ($pricingData as $domain) {
                $reg = floatval($domain['registration'] ?? 0);
                $ren = floatval($domain['renewal'] ?? 0);
                $trans = floatval($domain['transfer'] ?? 0);
                
                if ($reg > 0 || $ren > 0 || $trans > 0) {
                    $nonZeroCount++;
                    if (count($sampleNonZero) < 3) {
                        $sampleNonZero[] = [
                            'extension' => $domain['extension'] ?? '',
                            'registration' => $reg,
                            'renewal' => $ren,
                            'transfer' => $trans
                        ];
                    }
                }
            }
            
            echo "Domains with non-zero prices: {$nonZeroCount}/" . count($pricingData) . "\n";
            
            if (count($sampleNonZero) > 0) {
                echo "\nSample domains with prices:\n";
                foreach ($sampleNonZero as $sample) {
                    echo "- {$sample['extension']}: Reg={$sample['registration']}, Ren={$sample['renewal']}, Trans={$sample['transfer']}\n";
                }
            } else {
                echo "\n❌ NO DOMAINS WITH NON-ZERO PRICES FOUND!\n";
                echo "This explains why all prices show as 0.\n";
            }
            
            // Check all available keys in the data
            echo "\n=== AVAILABLE KEYS IN API RESPONSE ===\n";
            if (count($pricingData) > 0) {
                $allKeys = array_keys($pricingData[0]);
                echo "Keys in first domain: " . implode(', ', $allKeys) . "\n";
                
                // Look for price-related keys
                $priceKeys = array_filter($allKeys, function($key) {
                    return stripos($key, 'price') !== false || 
                           stripos($key, 'cost') !== false || 
                           stripos($key, 'amount') !== false ||
                           stripos($key, 'fee') !== false;
                });
                
                if (count($priceKeys) > 0) {
                    echo "Price-related keys found: " . implode(', ', $priceKeys) . "\n";
                    
                    // Show values for these keys
                    echo "\nValues for price-related keys in first domain:\n";
                    foreach ($priceKeys as $key) {
                        echo "- {$key}: " . ($pricingData[0][$key] ?? 'NULL') . "\n";
                    }
                }
            }
            
        } else {
            echo "Failed to parse API response\n";
            echo "Raw response: " . substr($response, 0, 500) . "...\n";
        }
    } else {
        echo "API call failed\n";
        echo "Response: " . substr($response, 0, 200) . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nTest completed!\n";
?>
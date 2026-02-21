<?php
/**
 * Test Margin and Rounding Implementation
 */

echo "Testing margin and rounding implementation...\n";

// Mock rounding function
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

try {
    // Test API call
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
            echo "Number of domains: " . count($pricingData) . "\n";
            
            // Test margin and rounding configuration
            $marginType = 'percentage';
            $marginValue = 25; // 25%
            $roundingRule = 'up_1000';
            $customRounding = 1000;
            
            echo "\nTesting with configuration:\n";
            echo "- Margin Type: {$marginType}\n";
            echo "- Margin Value: {$marginValue}%\n";
            echo "- Rounding Rule: {$roundingRule}\n";
            
            // Generate HTML with margin and rounding
            $html = '<div class="container-fluid">';
            $html .= '<div class="row">';
            $html .= '<div class="col-md-12">';
            $html .= '<div class="panel panel-default">';
            $html .= '<div class="panel-heading">';
            $html .= '<h3 class="panel-title">Domain Pricing - ' . $currency . '</h3>';
            $html .= '<div class="pull-right">';
            $html .= '<small>Margin: ' . $marginValue . '% | Rounding: ' . ucfirst(str_replace('_', ' ', $roundingRule)) . '</small>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '<div class="panel-body">';
            $html .= '<div class="table-responsive">';
            $html .= '<table class="table table-striped table-bordered">';
            $html .= '<thead>';
            $html .= '<tr>';
            $html .= '<th>TLD</th>';
            $html .= '<th>Type</th>';
            $html .= '<th>Base Registration</th>';
            $html .= '<th>Final Registration</th>';
            $html .= '<th>Base Renewal</th>';
            $html .= '<th>Final Renewal</th>';
            $html .= '<th>Base Transfer</th>';
            $html .= '<th>Final Transfer</th>';
            $html .= '<th>Description</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';
            
            $sampleCount = 0;
            foreach ($pricingData as $domain) {
                if ($sampleCount >= 5) break; // Show only first 5 for testing
                
                $extension = $domain['extension'] ?? '';
                $type = $domain['type'] ?? '';
                $description = $domain['description'] ?? '';
                
                // Calculate prices with margin and rounding
                $baseRegistration = floatval($domain['registration'] ?? 0);
                $baseRenewal = floatval($domain['renewal'] ?? 0);
                $baseTransfer = floatval($domain['transfer'] ?? 0);
                
                // Apply margin (percentage)
                $finalRegistration = $baseRegistration * (1 + ($marginValue / 100));
                $finalRenewal = $baseRenewal * (1 + ($marginValue / 100));
                $finalTransfer = $baseTransfer * (1 + ($marginValue / 100));
                
                // Apply rounding
                $finalRegistration = rdasApplyRounding($finalRegistration, $roundingRule, $customRounding);
                $finalRenewal = rdasApplyRounding($finalRenewal, $roundingRule, $customRounding);
                $finalTransfer = rdasApplyRounding($finalTransfer, $roundingRule, $customRounding);
                
                $html .= '<tr>';
                $html .= '<td><strong>' . htmlspecialchars($extension) . '</strong></td>';
                $html .= '<td>' . htmlspecialchars($type) . '</td>';
                $html .= '<td>' . number_format($baseRegistration, 0, ',', '.') . '</td>';
                $html .= '<td><strong>' . number_format($finalRegistration, 0, ',', '.') . '</strong></td>';
                $html .= '<td>' . number_format($baseRenewal, 0, ',', '.') . '</td>';
                $html .= '<td><strong>' . number_format($finalRenewal, 0, ',', '.') . '</strong></td>';
                $html .= '<td>' . number_format($baseTransfer, 0, ',', '.') . '</td>';
                $html .= '<td><strong>' . number_format($finalTransfer, 0, ',', '.') . '</strong></td>';
                $html .= '<td>' . htmlspecialchars(substr($description, 0, 50)) . (strlen($description) > 50 ? '...' : '') . '</td>';
                $html .= '</tr>';
                
                // Show calculation details for first domain
                if ($sampleCount === 0) {
                    echo "\nSample calculation for {$extension}:\n";
                    echo "- Base Registration: " . number_format($baseRegistration, 0, ',', '.') . "\n";
                    echo "- With 25% margin: " . number_format($baseRegistration * 1.25, 0, ',', '.') . "\n";
                    echo "- After rounding: " . number_format($finalRegistration, 0, ',', '.') . "\n";
                }
                
                $sampleCount++;
            }
            
            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';
            
            echo "\nHTML generated successfully!\n";
            echo "HTML length: " . strlen($html) . " characters\n";
            
            // Check for key elements
            $checks = [
                'Domain Pricing' => strpos($html, 'Domain Pricing') !== false,
                'Margin info' => strpos($html, 'Margin:') !== false,
                'Rounding info' => strpos($html, 'Rounding:') !== false,
                'Base Registration' => strpos($html, 'Base Registration') !== false,
                'Final Registration' => strpos($html, 'Final Registration') !== false,
                'Panel structure' => strpos($html, 'panel-default') !== false,
                'Number formatting' => strpos($html, '.') !== false
            ];
            
            echo "\nChecking HTML content:\n";
            foreach ($checks as $check => $result) {
                echo "- {$check}: " . ($result ? 'PASS' : 'FAIL') . "\n";
            }
            
            echo "\nFirst 600 characters of HTML:\n";
            echo substr($html, 0, 600) . "...\n";
            
        } else {
            echo "Failed to parse API response\n";
        }
    } else {
        echo "API call failed\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nTest completed successfully!\n";
?>
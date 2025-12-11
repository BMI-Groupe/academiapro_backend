<?php

// Test script to check request-docs main endpoint
$url = 'http://127.0.0.1:8000/request-docs';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: " . $httpCode . "\n\n";

// Check if response contains expected content
if (strpos($response, 'request-docs') !== false || strpos($response, 'LRD') !== false) {
    echo "✓ Page loads successfully\n";
    
    // Check if it's trying to load /api endpoint
    if (strpos($response, '/request-docs/api') !== false || strpos($response, 'request-docs/api') !== false) {
        echo "✓ Page references /api endpoint\n";
    } else {
        echo "✗ Page does NOT reference /api endpoint\n";
    }
} else {
    echo "✗ Unexpected response\n";
    echo "First 500 chars:\n" . substr($response, 0, 500) . "\n";
}

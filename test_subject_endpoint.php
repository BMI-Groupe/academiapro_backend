<?php

// Test the subject endpoint
$url = 'http://127.0.0.1:8000/api/v1.0.0/subjects/4';

// Get auth token (you'll need to replace this with a valid token)
// For now, let's just test without auth to see the error
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: " . $httpCode . "\n\n";

if ($httpCode == 200) {
    echo "✓ Success!\n";
    $data = json_decode($response, true);
    echo "Response:\n";
    print_r($data);
} else {
    echo "✗ Error\n";
    echo "Response:\n";
    echo substr($response, 0, 500) . "\n";
}

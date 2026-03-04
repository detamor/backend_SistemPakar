<?php
/**
 * Script untuk test koneksi ke Python engine
 * Jalankan: php test_python_connection.php
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Http;

// Load .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$pythonUrl = $_ENV['PYTHON_API_URL'] ?? 'http://127.0.0.1:8001';

echo "Testing connection to Python engine...\n";
echo "URL: {$pythonUrl}\n\n";

// Test 1: Health check
echo "1. Testing /health endpoint...\n";
try {
    $response = Http::timeout(5)->get("{$pythonUrl}/health");
    echo "   Status: {$response->status()}\n";
    echo "   Response: " . $response->body() . "\n";
} catch (\Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Test diagnose endpoint dengan data minimal
echo "2. Testing /api/diagnose endpoint...\n";
$testData = [
    'diagnosis_id' => 999,
    'plant_id' => 1,
    'symptoms' => [
        ['symptom_id' => 1, 'user_cf' => 0.7]
    ],
    'diseases_data' => [
        [
            'id' => 1,
            'name' => 'Test Disease',
            'description' => 'Test',
            'cause' => 'Test',
            'solution' => 'Test',
            'prevention' => 'Test',
            'symptoms' => [
                ['symptom_id' => 1, 'certainty_factor' => 0.8]
            ]
        ]
    ]
];

try {
    $startTime = microtime(true);
    $response = Http::withOptions([
        'timeout' => 120,
        'connect_timeout' => 10,
        'verify' => false,
    ])->post("{$pythonUrl}/api/diagnose", $testData);
    
    $elapsed = microtime(true) - $startTime;
    echo "   Status: {$response->status()}\n";
    echo "   Elapsed time: " . round($elapsed, 2) . " seconds\n";
    echo "   Response: " . substr($response->body(), 0, 200) . "...\n";
} catch (\Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
    echo "   Error type: " . get_class($e) . "\n";
}

echo "\nDone!\n";


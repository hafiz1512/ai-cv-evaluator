<?php
require "config.php";

// Prompt sederhana untuk cek koneksi
$prompt = "Say 'AI connection OK' in JSON format with key {\"status\": \"ok\"}";

$payload = [
    "model" => $api_model,   // ambil dari config.php
    "messages" => [["role" => "user", "content" => $prompt]],
    "temperature" => 0.0     // bisa pakai $api_temp kalau mau ikut config
];

$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $api_token",
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
$response = curl_exec($ch);
curl_close($ch);

echo "<h3>🔍 API Connectivity Test</h3>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";
?>

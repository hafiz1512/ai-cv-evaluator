<?php
// Konfigurasi database
$host = "localhost";
$username = "roy41712_admin";
$password = "@Royal2025";
$db_name = "roy41712_main";

// API LLM
$api_token = "your token";
$api_url   = "https://openrouter.ai/api/v1/chat/completions";
$api_model = "google/gemma-3-27b-it:free";  // default model
$api_temp  = 0.3; // default temperature

// koneksi database
$conn = new mysqli($host, $username, $password, $db_name);
if ($conn->connect_errno) {
    echo "Error: Tidak terhubung ke database";
    exit;
}
?>

<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require "config.php";
require __DIR__ . "/vendor/autoload.php";

use Smalot\PdfParser\Parser;

header("Content-Type: application/json");

$job_id = uniqid("job_");

if (!isset($_FILES["cv"]) || $_FILES["cv"]["error"] !== UPLOAD_ERR_OK) {
    echo json_encode(["error" => "File CV tidak diterima"]);
    exit;
}

$tmpPath = $_FILES["cv"]["tmp_name"];
$mime    = mime_content_type($tmpPath);

// longgarkan validasi MIME
$allowed = ['application/pdf', 'application/x-pdf', 'application/octet-stream'];
if (!in_array($mime, $allowed)) {
    echo json_encode(["error" => "File bukan PDF, terdeteksi: $mime"]);
    exit;
}

try {
    $parser = new Parser();
    $pdf    = $parser->parseFile($tmpPath);
    $cv_text = $pdf->getText();
} catch (Exception $e) {
    echo json_encode(["error" => "Gagal mengekstrak teks: " . $e->getMessage()]);
    exit;
}

// --- Simpan ke database ---
$stmt = $conn->prepare(
    "INSERT INTO uploads (job_id, cv_filename, cv_text, status) VALUES (?, ?, ?, 'queued')"
);
$stmt->bind_param("sss", $job_id, $_FILES["cv"]["name"], $cv_text);

if (!$stmt->execute()) {
    echo json_encode(["error" => "DB error: " . $stmt->error]);
    exit;
}

// --- Simpan hasil parsing ke file txt ---
$saveDir = __DIR__ . "/uploads/cv/";
if (!is_dir($saveDir)) {
    mkdir($saveDir, 0777, true); // buat folder kalau belum ada
}

$txtFilename = $saveDir . $job_id . ".txt";
file_put_contents($txtFilename, $cv_text);

// --- Respons ke frontend ---
echo json_encode([
    "id"     => $job_id,
    "status" => "queued",
    "txt"    => "uploads/cv/" . $job_id . ".txt"
]);
?>

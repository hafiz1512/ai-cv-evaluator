<?php
require "config.php";
header("Content-Type: application/json");

$job_id = $_GET["id"] ?? null;
if (!$job_id) {
    echo json_encode(["error" => "id required"]);
    exit;
}

// Ambil status job
$stmt = $conn->prepare("SELECT id, status FROM uploads WHERE job_id=?");
$stmt->bind_param("s", $job_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    echo json_encode(["status" => "not_found"]);
    exit;
}
$upload = $res->fetch_assoc();

// Jika belum selesai, kirim status saat ini
if ($upload["status"] !== "completed") {
    echo json_encode([
        "id"     => $job_id,
        "status" => $upload["status"]
    ]);
    exit;
}

// Jika sudah selesai, ambil result_json terakhir
$stmt2 = $conn->prepare("
    SELECT e.result_json
    FROM evaluations e
    WHERE e.upload_id = ?
    ORDER BY e.id DESC
    LIMIT 1
");
$stmt2->bind_param("i", $upload["id"]);
$stmt2->execute();
$res2 = $stmt2->get_result();
$row = $res2->fetch_assoc();

// Decode ke array agar langsung jadi JSON PHP
$resultData = [];
if ($row && $row["result_json"]) {
    $decoded = json_decode($row["result_json"], true);
    $resultData = is_array($decoded) ? $decoded : ["raw_result" => $row["result_json"]];
}

// Output akhir: id, status, dan seluruh field evaluasi (technical_skills, dll.)
echo json_encode([
    "id"     => $job_id,
    "status" => "completed",
    "result" => $resultData
], JSON_UNESCAPED_UNICODE);
?>

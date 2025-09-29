<?php
require "config.php";
header("Content-Type: application/json");

$job_id = $_POST["job_id"] ?? null;
if (!$job_id) {
    echo json_encode(["error" => "job_id required"]);
    exit;
}

// Ambil data upload
$res = $conn->prepare("SELECT * FROM uploads WHERE job_id=?");
$res->bind_param("s", $job_id);
$res->execute();
$resultUpload = $res->get_result();
if ($resultUpload->num_rows === 0) {
    echo json_encode(["error" => "job not found"]);
    exit;
}
$upload = $resultUpload->fetch_assoc();

// Update status menjadi processing
$conn->query("UPDATE uploads SET status='processing' WHERE job_id='$job_id'");

// Ambil teks CV langsung dari kolom cv_text
$cv_text = $upload["cv_text"] ?? "";
if (trim($cv_text) === "") {
    $cv_text = "No text extracted from CV.";
}

// Prompt dengan rubric evaluasi
$prompt = <<<EOT
You are an AI CV evaluator. Analyze the following CV text and return a JSON object
with these fields only:

{
  "cv_match_rate": float (0 to 1),
  "technical_skills": short analysis string,
  "experience_level": short analysis string,
  "achievements": short analysis string,
  "cultural_fit": short analysis string,
  "overall_summary": short summary string
}

Consider these evaluation criteria:
- Technical Skills Match (backend, databases, APIs, cloud, AI/LLM exposure)
- Experience Level (years, project complexity)
- Relevant Achievements (impact, scale)
- Cultural Fit (communication, learning attitude)

CV Text:
$cv_text
EOT;

// Panggil API LLM
$payload = [
    "model" => $api_model,   // diambil dari config.php
    "messages" => [["role" => "user", "content" => $prompt]],
    "temperature" => $api_temp
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

// Parsing respons LLM
$data = json_decode($response, true);
$content = $data["choices"][0]["message"]["content"] ?? "{}";

// --- Clean JSON output dari AI (hapus ```json ... ``` ) ---
$clean = preg_replace('/```json|```/i', '', $content);
$clean = trim($clean);

// Coba parse JSON dari output LLM
$evalResult = json_decode($clean, true);
if (!$evalResult || !is_array($evalResult)) {
    $evalResult = [
        "cv_match_rate"   => 0.0,
        "technical_skills"=> "Parsing failed. Raw: $content",
        "experience_level"=> "",
        "achievements"    => "",
        "cultural_fit"    => "",
        "overall_summary" => "Failed to parse valid JSON."
    ];
}

// Simpan hasil evaluasi
$stmt = $conn->prepare("
    INSERT INTO evaluations
        (upload_id, cv_match_rate, cv_feedback, overall_summary, result_json)
    VALUES (
        (SELECT id FROM uploads WHERE job_id=?),
        ?, ?, ?, ?
    )
");
$json_str = json_encode($evalResult, JSON_UNESCAPED_UNICODE);
$cv_feedback = ($evalResult["technical_skills"] ?? '') . " | "
             . ($evalResult["experience_level"] ?? '') . " | "
             . ($evalResult["achievements"] ?? '') . " | "
             . ($evalResult["cultural_fit"] ?? '');
$stmt->bind_param(
    "sdsss",
    $job_id,
    $evalResult["cv_match_rate"],
    $cv_feedback,
    $evalResult["overall_summary"],
    $json_str
);
$stmt->execute();

// Update status menjadi completed
$conn->query("UPDATE uploads SET status='completed' WHERE job_id='$job_id'");

// Kirim respons
echo json_encode([
    "id"     => $job_id,
    "status" => "completed",
    "result" => $evalResult
]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>AI CV Evaluator</title>
  <style>
    body {
      font-family: "Segoe UI", Tahoma, sans-serif;
      margin: 0; padding: 0;
      background: #f8fafc;
      color: #333;
    }
    header {
      background: #2563eb;
      color: white;
      padding: 1.5rem 2rem;
      text-align: center;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    h1 { margin: 0; font-size: 1.8rem; }
    main { max-width: 800px; margin: 2rem auto; padding: 0 1rem; }

    .card {
      background: white;
      border-radius: 10px;
      padding: 1.5rem;
      margin-bottom: 1.5rem;
      box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    }
    h2 { margin-top: 0; font-size: 1.2rem; color: #2563eb; }
    pre {
      background: #f4f4f4;
      padding: 1rem;
      border-radius: 6px;
      overflow-x: auto;
      font-size: 0.9rem;
    }
    label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
    input[type="file"] { margin-bottom: 1rem; }
    button {
      background: #2563eb;
      color: white;
      border: none;
      padding: 0.6rem 1.2rem;
      border-radius: 6px;
      cursor: pointer;
      font-size: 1rem;
    }
    button:hover { background: #1e40af; }

    #status {
      margin-top: 1rem;
      font-weight: 600;
      display: flex; align-items: center;
    }
    .spinner {
      width: 18px; height: 18px;
      border: 3px solid #ccc; border-top-color: #2563eb;
      border-radius: 50%;
      margin-right: 0.5rem;
      animation: spin 1s linear infinite;
      display: none;
    }
    @keyframes spin {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }

    .result-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 1rem;
    }
    .result-item {
      background: #f9fafb;
      padding: 1rem;
      border-radius: 8px;
      border-left: 4px solid #2563eb;
    }
    .result-item h3 {
      margin: 0 0 0.5rem;
      font-size: 1rem;
      color: #2563eb;
    }
  </style>
</head>
<body>
  <header>
    <h1>AI CV Evaluator</h1>
  </header>

  <main>
    <!-- Upload Section -->
    <div class="card">
      <h2>Upload CV</h2>
      <form id="uploadForm" enctype="multipart/form-data">
        <label>Select CV (PDF, DOCX, TXT):</label>
        <input type="file" name="cv" accept=".pdf,.doc,.docx,.txt" required>
        <button type="submit">Submit</button>
      </form>

      <div id="status"><div class="spinner"></div> Waiting for upload...</div>
    </div>

    <!-- Result Section -->
    <div class="card" id="resultSection" style="display:none;">
      <h2>Evaluation Result</h2>
      <div class="result-grid" id="resultGrid"></div>

      <h3>Raw JSON (debug)</h3>
      <pre id="resultRaw"></pre>
    </div>
  </main>

  <script>
  document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("uploadForm");
    const statusDiv = document.getElementById("status");
    const spinner = statusDiv.querySelector(".spinner");
    const resultSection = document.getElementById("resultSection");
    const resultGrid = document.getElementById("resultGrid");
    const resultRaw = document.getElementById("resultRaw");

    form.onsubmit = async (e) => {
      e.preventDefault();
      const formData = new FormData(form);

      try {
        // 1. Upload CV
        statusDiv.innerHTML = '<div class="spinner" style="display:inline-block"></div> Uploading...';
        const uploadRes = await fetch("upload.php", { method: "POST", body: formData });
        if (!uploadRes.ok) throw new Error("Upload failed");
        const uploadData = await uploadRes.json();
        const jobId = uploadData.id;
        statusDiv.innerHTML = '<div class="spinner" style="display:inline-block"></div> Job queued: ' + jobId;

        // 2. Trigger evaluation
        await fetch("evaluate.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: new URLSearchParams({ job_id: jobId })
        });

        // 3. Poll result
        const interval = setInterval(async () => {
          const r = await fetch(`result.php?id=${encodeURIComponent(jobId)}`);
          if (!r.ok) throw new Error("Result fetch failed");
          const result = await r.json();

          if (result.status === "completed") {
            clearInterval(interval);
            statusDiv.innerHTML = "✅ Job Completed!";
            renderResult(result);
            resultSection.style.display = "block";
            resultSection.scrollIntoView({ behavior: "smooth" });
          } else {
            statusDiv.innerHTML = '<div class="spinner" style="display:inline-block"></div> Status: ' + result.status;
          }
        }, 3000);
      } catch (err) {
        statusDiv.textContent = "Error: " + err.message;
      }
    };

    function renderResult(result) {
      resultGrid.innerHTML = "";
      const fields = ["cv_match_rate","technical_skills","experience_level","achievements","cultural_fit","overall_summary"];
      fields.forEach(f => {
        const val = result.result[f] ?? "";
        const div = document.createElement("div");
        div.className = "result-item";
        div.innerHTML = `<h3>${f.replace("_"," ").toUpperCase()}</h3><p>${val}</p>`;
        resultGrid.appendChild(div);
      });
      resultRaw.textContent = JSON.stringify(result, null, 2);
    }
  });
  </script>
</body>
</html>

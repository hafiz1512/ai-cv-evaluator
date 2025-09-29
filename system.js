document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("uploadForm");
  const statusDiv = document.getElementById("status");
  const resultPre = document.getElementById("result");

  form.onsubmit = async (e) => {
    e.preventDefault();
    const formData = new FormData(form);

    statusDiv.innerText = "Uploading...";
    let res = await fetch("upload.php", { method: "POST", body: formData });
    let data = await res.json();
    const jobId = data.id;
    statusDiv.innerText = "Job queued: " + jobId;

    await fetch("evaluate.php", {
      method: "POST",
      body: new URLSearchParams({ job_id: jobId })
    });

    let interval = setInterval(async () => {
      let r = await fetch("result.php?id=" + jobId);
      let result = await r.json();
      if (result.status === "completed") {
        clearInterval(interval);
        statusDiv.innerText = "Job Completed!";
        resultPre.innerText = JSON.stringify(result, null, 2);
      } else {
        statusDiv.innerText = "Status: " + result.status;
      }
    }, 3000);
  };
});

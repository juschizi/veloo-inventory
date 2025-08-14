<?php
// public/api/explorer.php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: ../login.php'); exit; }

require_once '../../src/db.php';

// ADMIN-ONLY
$roleStmt = $pdo->prepare("SELECT role FROM admins WHERE id=?");
$roleStmt->execute([$_SESSION['admin_id']]);
$role = $roleStmt->fetchColumn();
if ($role !== 'admin') { http_response_code(403); exit('Forbidden'); }
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>API Explorer — Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: Poppins, sans-serif; max-width: 1000px; margin: 24px auto; padding: 0 16px; }
    h2 { margin: 0 0 12px; }
    .row { display:grid; grid-template-columns: 1fr 120px; gap:.5rem; margin-bottom:.5rem; }
    .row-3 { display:grid; grid-template-columns: 2fr 1fr 120px; gap:.5rem; margin-bottom:.5rem; }
    input, select { width:100%; padding:.5rem .6rem; border:1px solid #d1d5db; border-radius:6px; }
    .btn { background:#1e40af; color:#fff; border:none; border-radius:6px; padding:.45rem .8rem; cursor:pointer; }
    pre { background:#0b1020; color:#e5e7eb; padding:12px; border-radius:8px; overflow:auto; }
    code { color:#a7f3d0; }
    .muted { color:#6b7280; }
  </style>
</head>
<body>
  <h2>API Explorer</h2>
  <p class="muted">Paste your API key and try calls to the read-only endpoints.</p>

  <div class="row">
    <input id="apiKey" type="text" placeholder="X-API-Key (paste here)">
    <button class="btn" onclick="saveKey()">Save Key</button>
  </div>

  <div class="row-3">
    <input id="endpoint" type="text" value="/api/v1/health.php" placeholder="/api/v1/stores.php?limit=10&q=mart">
    <select id="method">
      <option>GET</option>
    </select>
    <button class="btn" onclick="send()">Send</button>
  </div>

  <pre id="result"><code>// Response will appear here</code></pre>

  <script>
    const keyInput = document.getElementById('apiKey');
    const epInput  = document.getElementById('endpoint');
    const resBox   = document.getElementById('result');

    // persist in localStorage for convenience
    keyInput.value = localStorage.getItem('veloo_api_key') || '';

    function saveKey(){ localStorage.setItem('veloo_api_key', keyInput.value.trim()); alert('Saved.'); }

    async function send(){
      const key = (localStorage.getItem('veloo_api_key') || keyInput.value).trim();
      if(!key){ alert('Paste API key first'); return; }
      const url = epInput.value.trim();
      try{
        const r = await fetch(url, { headers: { 'X-API-Key': key } });
        const text = await r.text();
        resBox.innerText = text;
      }catch(e){
        resBox.innerText = 'Error: ' + e.message;
      }
    }
  </script>

  <p class="muted" style="margin-top:12px;">
    Try: <code>/api/v1/stores.php?limit=5</code>,
    <code>/api/v1/store.php?id=1</code>,
    <code>/api/v1/store-items.php?store_id=1&limit=10&stock=low</code>
  </p>
</body>
</html>

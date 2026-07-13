<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>URL Encoder - tRPC</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans+Arabic:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root {
  --color-bg: #0e0e10;
  --color-surface: #16161a;
  --color-surface-2: #1c1c22;
  --color-border: #2a2a35;
  --color-text: #e8e8f0;
  --color-text-muted: #888899;
  --color-primary: #5b8dee;
  --color-primary-hover: #7aa3f2;
  --color-success: #4caf7d;
  --color-success-bg: #1a2e22;
  --color-error: #e05c5c;
  --color-error-bg: #2e1a1a;
  --color-accent: #a78bfa;
  --radius-sm: 6px;
  --radius-md: 10px;
  --radius-lg: 16px;
  --font-body: 'IBM Plex Sans Arabic', sans-serif;
  --font-mono: 'IBM Plex Mono', monospace;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: 16px; }
body {
  font-family: var(--font-body);
  background: var(--color-bg);
  color: var(--color-text);
  min-height: 100dvh;
  padding: 2rem 1rem;
}

.container {
  max-width: 860px;
  margin-inline: auto;
}

header {
  margin-bottom: 2.5rem;
}

header h1 {
  font-size: clamp(1.4rem, 3vw, 2rem);
  font-weight: 600;
  letter-spacing: -0.02em;
  color: var(--color-text);
  margin-bottom: 0.4rem;
}

header p {
  color: var(--color-text-muted);
  font-size: 0.9rem;
}

.badge {
  display: inline-block;
  background: oklch(from var(--color-primary) l c h / 0.15);
  color: var(--color-primary);
  border: 1px solid oklch(from var(--color-primary) l c h / 0.3);
  padding: 0.2rem 0.7rem;
  border-radius: 99px;
  font-size: 0.78rem;
  font-family: var(--font-mono);
  margin-bottom: 0.8rem;
}

.card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: 1.5rem;
  margin-bottom: 1.5rem;
}

.card-title {
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--color-text-muted);
  margin-bottom: 1rem;
}

label {
  display: block;
  font-size: 0.85rem;
  color: var(--color-text-muted);
  margin-bottom: 0.4rem;
  margin-top: 1rem;
}
label:first-child { margin-top: 0; }

input[type="text"], textarea {
  width: 100%;
  background: var(--color-surface-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  color: var(--color-text);
  font-family: var(--font-mono);
  font-size: 0.82rem;
  padding: 0.65rem 0.85rem;
  outline: none;
  transition: border-color 0.15s ease;
  resize: vertical;
  direction: ltr;
}
input[type="text"]:focus, textarea:focus {
  border-color: var(--color-primary);
}

textarea { min-height: 130px; line-height: 1.6; }
textarea.output { min-height: 80px; color: var(--color-success); }

.row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}
@media (max-width: 600px) { .row { grid-template-columns: 1fr; } }

.btn-row {
  display: flex;
  gap: 0.75rem;
  margin-top: 1.25rem;
  flex-wrap: wrap;
}

button {
  cursor: pointer;
  font-family: var(--font-body);
  font-size: 0.88rem;
  font-weight: 500;
  border: none;
  border-radius: var(--radius-sm);
  padding: 0.6rem 1.2rem;
  transition: background 0.15s ease, transform 0.1s ease;
}
button:active { transform: scale(0.97); }

.btn-primary {
  background: var(--color-primary);
  color: #fff;
}
.btn-primary:hover { background: var(--color-primary-hover); }

.btn-secondary {
  background: var(--color-surface-2);
  color: var(--color-text);
  border: 1px solid var(--color-border);
}
.btn-secondary:hover { border-color: var(--color-primary); color: var(--color-primary); }

.btn-copy {
  background: transparent;
  border: 1px solid var(--color-border);
  color: var(--color-text-muted);
  font-family: var(--font-mono);
  font-size: 0.78rem;
  padding: 0.4rem 0.8rem;
  margin-right: auto;
}
.btn-copy:hover { border-color: var(--color-success); color: var(--color-success); }

.output-block {
  margin-top: 1.5rem;
  border-top: 1px solid var(--color-border);
  padding-top: 1.5rem;
}

.output-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.5rem;
}

.output-label {
  font-size: 0.8rem;
  color: var(--color-text-muted);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.07em;
}

.url-box {
  background: var(--color-surface-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  padding: 0.8rem 1rem;
  font-family: var(--font-mono);
  font-size: 0.78rem;
  color: var(--color-success);
  word-break: break-all;
  direction: ltr;
  line-height: 1.7;
  white-space: pre-wrap;
}

.url-box .highlight { color: var(--color-accent); }

.status {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.78rem;
  padding: 0.3rem 0.7rem;
  border-radius: 99px;
  font-weight: 500;
  opacity: 0;
  transition: opacity 0.2s ease;
}
.status.show { opacity: 1; }
.status.ok { background: var(--color-success-bg); color: var(--color-success); }
.status.err { background: var(--color-error-bg); color: var(--color-error); }

.tabs {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1rem;
  border-bottom: 1px solid var(--color-border);
  padding-bottom: 0;
}

.tab {
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  color: var(--color-text-muted);
  font-size: 0.85rem;
  padding: 0.5rem 1rem;
  border-radius: 0;
  margin-bottom: -1px;
  transition: color 0.15s, border-color 0.15s;
}
.tab.active {
  color: var(--color-primary);
  border-bottom-color: var(--color-primary);
}
.tab:hover:not(.active) { color: var(--color-text); }

.tab-content { display: none; }
.tab-content.active { display: block; }
</style>
</head>
<body>
<?php if (session()->has('impersonator_user_id')): ?>
  <div style="background: linear-gradient(90deg, #f59e0b, #d97706); color: white; padding: 10px 20px; text-align: center; font-weight: bold; display: flex; justify-content: center; align-items: center; gap: 15px; z-index: 9999; font-size: 0.9rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); width: 100%; margin-bottom: 20px;">
    <span>⚠️ أنت تتصفح النظام حالياً بصفتك: <strong><?= esc(auth()->user()->username) ?></strong> (محاكاة حساب)</span>
    <a href="<?= base_url('admin/users/stop-impersonating') ?>" style="background: white; color: #b45309; padding: 4px 12px; border-radius: 4px; text-decoration: none; font-size: 0.8rem; font-weight: 700; transition: all 0.2s;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='white'">العودة لحساب المسؤول 🚪</a>
  </div>
<?php endif; ?>
<div class="container">
  <header>
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
      <div>
        <div class="badge">tRPC / JSON URL Encoder</div>
        <h1>URL Parameter Encoder</h1>
        <p>أداة لتشفير JSON كـ query parameter لـ tRPC requests</p>
      </div>
      <div style="display: flex; gap: 8px;">
        <a href="<?= base_url('/') ?>" style="text-decoration: none; background: var(--color-surface); border: 1px solid var(--color-border); color: var(--color-text); padding: 0.5rem 1rem; border-radius: var(--radius-sm); font-size: 0.85rem; font-weight: 500; transition: border-color 0.15s ease;" onmouseover="this.style.borderColor='var(--color-primary)'" onmouseout="this.style.borderColor='var(--color-border)'">🏠 العودة للوحة التحكم</a>
        <a href="<?= base_url('logout') ?>" style="text-decoration: none; background: var(--color-error-bg); border: 1px solid var(--color-error); color: var(--color-error); padding: 0.5rem 1rem; border-radius: var(--radius-sm); font-size: 0.85rem; font-weight: 500; transition: opacity 0.15s ease;" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">🚪 تسجيل الخروج</a>
      </div>
    </div>
  </header>

  <div class="card">
    <div class="tabs">
      <button class="tab active" onclick="switchTab('simple')">وضع بسيط</button>
      <button class="tab" onclick="switchTab('advanced')">وضع متقدم</button>
    </div>

    <!-- Simple Mode -->
    <div id="tab-simple" class="tab-content active">
      <label>Base URL</label>
      <input type="text" id="base-url" value="https://www.overviewdata.io/api/trpc/data.insights" dir="ltr">

      <label>batch</label>
      <input type="text" id="batch" value="1" dir="ltr" style="width:80px">

      <label>JSON Input (the "json" object content)</label>
      <textarea id="json-input">{
  "title": "one step",
  "category": "Popular;Electronics;Home & Garden;Health & Beauty;Apparel & Accessories;Tools;Baby & Toddler",
  "priceFrom": -1,
  "priceTo": -1,
  "weeks": 12,
  "country": "DZ;TN;MA;LY;EG;SA;QA;EA;OM;BH;KW;GB;IE;FR;BE;LU;CH;DE;AT;ES;IT;NL;PT;NG;CI;SN;KE",
  "transformation": "market-reaction",
  "v": "1.3--5"
}</textarea>

      <div class="btn-row">
        <button class="btn-primary" onclick="encode()">⚡ Encode URL</button>
        <button class="btn-secondary" onclick="validateJSON()">✓ Validate JSON</button>
        <span class="status" id="status-simple"></span>
      </div>
    </div>

    <!-- Advanced Mode -->
    <div id="tab-advanced" class="tab-content">
      <label>Full input object (الـ input كاملاً)</label>
      <textarea id="full-input" style="min-height:180px">{
  "0": {
    "json": {
      "title": "one step",
      "category": "Popular;Electronics;Home & Garden",
      "priceFrom": -1,
      "priceTo": -1,
      "weeks": 12,
      "country": "DZ;MA;EG",
      "transformation": "market-reaction",
      "v": "1.3--5"
    }
  }
}</textarea>

      <label>Base URL</label>
      <input type="text" id="adv-base-url" value="https://www.overviewdata.io/api/trpc/data.insights" dir="ltr">

      <div class="btn-row">
        <button class="btn-primary" onclick="encodeAdvanced()">⚡ Encode URL</button>
        <span class="status" id="status-adv"></span>
      </div>
    </div>
  </div>

  <!-- Output -->
  <div class="card" id="output-card" style="display:none">
    <div class="output-header">
      <span class="output-label">✓ Encoded URL</span>
      <button class="btn-copy" onclick="copyURL()" id="copy-btn">Copy</button>
    </div>
    <div class="url-box" id="url-output"></div>

    <div class="output-block">
      <div class="output-label" style="margin-bottom:0.75rem">JS Code</div>
      <textarea class="output" id="code-output" readonly></textarea>
    </div>
  </div>
</div>

<script>
function switchTab(name) {
  document.querySelectorAll('.tab').forEach((t,i) => {
    t.classList.toggle('active', ['simple','advanced'][i] === name)
  });
  document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
  document.getElementById('tab-'+name).classList.add('active');
}

function showStatus(id, msg, type) {
  const el = document.getElementById(id);
  el.textContent = msg;
  el.className = 'status show ' + type;
  setTimeout(() => el.classList.remove('show'), 2500);
}

function validateJSON() {
  const raw = document.getElementById('json-input').value.trim();
  try {
    JSON.parse(raw);
    showStatus('status-simple', '✓ JSON صحيح', 'ok');
  } catch(e) {
    showStatus('status-simple', '✗ ' + e.message, 'err');
  }
}

function encode() {
  const baseUrl = document.getElementById('base-url').value.trim();
  const batch = document.getElementById('batch').value.trim() || '1';
  const raw = document.getElementById('json-input').value.trim();

  try {
    const json = JSON.parse(raw);
    const inputObj = { "0": { json } };
    buildOutput(baseUrl, batch, inputObj);
    showStatus('status-simple', '✓ تم التشفير', 'ok');
  } catch(e) {
    showStatus('status-simple', '✗ JSON Error: ' + e.message, 'err');
  }
}

function encodeAdvanced() {
  const baseUrl = document.getElementById('adv-base-url').value.trim();
  const raw = document.getElementById('full-input').value.trim();

  // Extract batch from object keys count
  try {
    const inputObj = JSON.parse(raw);
    const batch = Object.keys(inputObj).length;
    buildOutput(baseUrl, batch, inputObj);
    showStatus('status-adv', '✓ تم التشفير', 'ok');
  } catch(e) {
    showStatus('status-adv', '✗ JSON Error: ' + e.message, 'err');
  }
}

function buildOutput(baseUrl, batch, inputObj) {
  const encoded = encodeURIComponent(JSON.stringify(inputObj));
  const fullUrl = `${baseUrl}?batch=${batch}&input=${encoded}`;

  // Display with highlighted parts
  const card = document.getElementById('output-card');
  const urlOut = document.getElementById('url-output');
  const codeOut = document.getElementById('code-output');

  const inputStr = JSON.stringify(inputObj, null, 2);
  const code = `const input = ${inputStr};\n\nconst url = \`${baseUrl}?batch=${batch}&input=\${encodeURIComponent(JSON.stringify(input))}\`;\n\n// مثال مع fetch:\nconst res = await fetch(url);\nconst data = await res.json();`;

  urlOut.innerHTML = escapeHtml(baseUrl) + 
    '<span class="highlight">?batch=' + batch + '&input=</span>' + 
    escapeHtml(encoded);
  
  codeOut.value = code;
  card.style.display = 'block';
  card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

  // Store full URL for copy
  card.dataset.url = fullUrl;
}

function escapeHtml(str) {
  return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function copyURL() {
  const url = document.getElementById('output-card').dataset.url;
  if (!url) return;
  navigator.clipboard.writeText(url).then(() => {
    const btn = document.getElementById('copy-btn');
    btn.textContent = '✓ Copied!';
    btn.style.color = 'var(--color-success)';
    setTimeout(() => { btn.textContent = 'Copy'; btn.style.color = ''; }, 1500);
  });
}
</script>
</body>
</html>

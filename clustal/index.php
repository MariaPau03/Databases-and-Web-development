<?php
/**
 * index.php — ClustalOmega Web Application (PHP version)
 * Main page: checks clustalo availability, renders the UI.
 */

// ─── Check clustalo ───────────────────────────────────────────────────────────
$CLUSTALO_PATH = getenv('CLUSTALO_PATH') ?: 'clustalo';

$clustalo_available = false;
$clustalo_version   = '';

$test_output = [];
$test_ret    = 0;
exec(escapeshellcmd($CLUSTALO_PATH) . ' --version 2>&1', $test_output, $test_ret);
if ($test_ret === 0 && !empty($test_output)) {
    $clustalo_available = true;
    $clustalo_version   = trim($test_output[0]);
}

$output_formats = [
    'clustal'   => 'Clustal (.aln)',
    'fasta'     => 'FASTA (.fasta)',
    'msf'       => 'MSF (.msf)',
    'phylip'    => 'PHYLIP (.phy)',
    'selex'     => 'SELEX (.slx)',
    'stockholm' => 'Stockholm (.sto)',
    'vienna'    => 'Vienna (.vienna)',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ClustalΩ — MSA</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:ital,wght@0,300;0,400;0,500;1,300&family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,400&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #0b0f1a; --bg2: #111827; --bg3: #1a2235;
      --border: #1e2d47; --border2: #263650;
      --accent: #a78bfa; --accent2: #8b5cf6; --accent3: #c4b5fd;
      --green: #4ade80; --yellow: #facc15; --red: #f87171;
      --text: #e2eaf5; --text2: #8fa3be; --text3: #4a6080;
      --mono: 'DM Mono', monospace;
      --sans: 'DM Sans', sans-serif;
      --display: 'Syne', sans-serif;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      background: var(--bg); color: var(--text);
      font-family: var(--sans); font-size: 14px;
      min-height: 100vh; line-height: 1.6;
    }

   

    header {
      position: relative; z-index: 1;
      border-bottom: 1px solid var(--border);
      padding: 0 2rem; display: flex; align-items: center;
      justify-content: space-between; height: 64px;
      background: rgba(11,15,26,0.9); backdrop-filter: blur(12px);
    }

    .logo {
      font-family: var(--display); font-size: 1.4rem; font-weight: 800;
      letter-spacing: -0.03em; color: var(--text);
      display: flex; align-items: center; gap: 0.5rem;
    }
    .logo span { color: var(--accent); }

    .version-badge {
      font-family: var(--mono); font-size: 0.7rem;
      background: rgba(56,189,248,0.1); color: var(--accent3);
      border: 1px solid rgba(56,189,248,0.2);
      padding: 2px 8px; border-radius: 99px;
    }

    .status-pill { display: flex; align-items: center; gap: 6px; font-family: var(--mono); font-size: 0.72rem; color: var(--text2); }
    .status-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--green); box-shadow: 0 0 6px var(--green); animation: pulse 2s infinite; }
    .status-dot.offline { background: var(--red); box-shadow: 0 0 6px var(--red); animation: none; }
    @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.5} }

    .app-container {
      position: relative; z-index: 1; max-width: 1280px;
      margin: 0 auto; padding: 2rem;
      display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;
    }
    @media (max-width: 900px) { .app-container { grid-template-columns: 1fr; } }

    .card { background: var(--bg2); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
    .card-header { display: flex; align-items: center; gap: 10px; padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); background: rgba(255,255,255,0.01); }
    .card-icon { width: 30px; height: 30px; border-radius: 7px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; flex-shrink: 0; }
    .card-icon.blue   { background: rgba(56,189,248,0.15); }
    .card-icon.green  { background: rgba(74,222,128,0.15); }
    .card-icon.purple { background: rgba(167,139,250,0.15); }
    .card-title { font-family: var(--display); font-size: 0.85rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; }
    .card-body { padding: 1.25rem; }

    /* ── Input tabs ── */
    .input-tabs { display: flex; gap: 3px; margin-bottom: 1rem; background: var(--bg); border-radius: 8px; padding: 4px; }
    .tab-btn {
      flex: 1; padding: 7px 6px; border: none; background: transparent;
      color: var(--text2); cursor: pointer; border-radius: 6px;
      font-family: var(--sans); font-size: 0.75rem; font-weight: 500;
      transition: all 0.15s; white-space: nowrap;
    }
    .tab-btn:hover { color: var(--text); background: rgba(255,255,255,0.04); }
    .tab-btn.active { background: var(--bg3); color: var(--accent); }

    .input-panel { display: none; }
    .input-panel.active { display: block; }

    label { display: block; font-size: 0.75rem; font-weight: 500; color: var(--text2); margin-bottom: 5px; letter-spacing: 0.03em; }
    textarea, input[type="text"], select {
      width: 100%; background: var(--bg); border: 1px solid var(--border2);
      border-radius: 8px; color: var(--text); font-family: var(--mono);
      font-size: 0.78rem; padding: 10px 12px; outline: none; transition: border-color 0.15s; resize: vertical;
    }
    textarea:focus, input[type="text"]:focus, select:focus {
      border-color: var(--accent2); box-shadow: 0 0 0 3px rgba(56,189,248,0.08);
    }
    textarea { min-height: 180px; line-height: 1.55; }
    select option { background: var(--bg2); }

    .hint { margin-top: 5px; font-size: 0.7rem; color: var(--text3); font-family: var(--mono); line-height: 1.5; }
    .hint a { color: var(--accent3); }

    /* ── File drop ── */
    .file-drop {
      border: 1.5px dashed var(--border2); border-radius: 8px; padding: 2rem 1rem;
      text-align: center; cursor: pointer; transition: all 0.2s; background: var(--bg);
    }
    .file-drop:hover, .file-drop.drag-over { border-color: var(--accent2); background: rgba(56,189,248,0.03); }
    .file-drop input[type="file"] { display: none; }
    .file-drop-icon { font-size: 2rem; margin-bottom: .5rem; opacity: .5; }
    .file-drop-text { color: var(--text2); font-size: .8rem; }
    .file-drop-text strong { color: var(--accent3); }
    .file-name { color: var(--green); font-family: var(--mono); font-size: .8rem; margin-top: .5rem; }

    /* ── Segmented control (sequence type) ── */
    .seg-control { display: flex; background: var(--bg); border: 1px solid var(--border2); border-radius: 8px; padding: 3px; gap: 3px; }
    .seg-btn {
      flex: 1; padding: 7px 6px; border: 1px solid transparent; border-radius: 5px;
      background: transparent; color: var(--text2); font-family: var(--mono);
      font-size: 0.75rem; font-weight: 500; cursor: pointer; transition: all 0.15s;
      display: flex; align-items: center; justify-content: center; gap: 5px;
    }
    .seg-btn:hover:not(:disabled) { color: var(--text); background: rgba(255,255,255,0.04); }
    .seg-btn.active[data-type="protein"] { background: rgba(96,165,250,0.15); color: #93c5fd; border-color: rgba(96,165,250,0.25); }
    .seg-btn.active[data-type="dna"]     { background: rgba(74,222,128,0.15); color: #86efac; border-color: rgba(74,222,128,0.25); }
    .seg-btn.active[data-type="rna"]     { background: rgba(250,204,21,0.15);  color: #fde68a; border-color: rgba(250,204,21,0.25); }
    .seg-btn:disabled { opacity: 0.35; cursor: not-allowed; }

    .compat-warning {
      display: none; margin-top: .6rem; padding: 6px 10px;
      background: rgba(250,204,21,0.08); border: 1px solid rgba(250,204,21,0.25);
      border-radius: 6px; color: #fde047; font-size: .72rem; font-family: var(--mono); line-height: 1.5;
    }
    .compat-warning.show { display: block; }

    /* ── Options grid ── */
    .options-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    @media (max-width: 500px) { .options-grid { grid-template-columns: 1fr; } }
    .form-group { display: flex; flex-direction: column; gap: 5px; }

    .range-label { display: flex; justify-content: space-between; align-items: center; }
    .range-label span { font-family: var(--mono); font-size: 0.75rem; color: var(--accent3); }
    input[type="range"] { -webkit-appearance: none; width: 100%; height: 4px; background: var(--border2); border-radius: 2px; outline: none; }
    input[type="range"]::-webkit-slider-thumb { -webkit-appearance: none; width: 14px; height: 14px; background: var(--accent); border-radius: 50%; cursor: pointer; }

    /* ── Alerts ── */
    .alert {
      border-radius: 8px; padding: .75rem 1rem; font-size: .8rem;
      font-family: var(--mono); line-height: 1.5; white-space: pre-wrap;
      display: none; margin-bottom: 1rem;
    }
    .alert.show { display: block; }
    .alert-error   { background: rgba(248,113,113,0.08); border: 1px solid rgba(248,113,113,0.3); color: #fca5a5; }
    .alert-warning { background: rgba(250,204,21,0.08);  border: 1px solid rgba(250,204,21,0.25);  color: #fde047; }

    /* ── Run button ── */
    .btn-run {
      display: flex; align-items: center; justify-content: center; gap: 8px;
      width: 100%; margin-top: 1.25rem; padding: 12px 20px;
      background: linear-gradient(135deg, var(--accent2) 0%, #0369a1 100%);
      color: white; border: none; border-radius: 8px;
      font-family: var(--display); font-size: .88rem; font-weight: 700;
      letter-spacing: .05em; text-transform: uppercase; cursor: pointer; transition: all .2s;
    }
    .btn-run:hover { transform: translateY(-1px); box-shadow: 0 4px 20px rgba(14,165,233,.3); }
    .btn-run:disabled { opacity: .5; cursor: not-allowed; transform: none; }
    .spinner { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,.3); border-top-color: white; border-radius: 50%; animation: spin .7s linear infinite; display: none; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ── Progress ── */
    .progress-bar-wrap { background: var(--bg); border-radius: 99px; height: 3px; overflow: hidden; margin-top: .5rem; display: none; }
    .progress-bar-wrap.show { display: block; }
    .progress-bar { height: 100%; background: linear-gradient(90deg, var(--accent2), var(--accent)); width: 0%; transition: width .3s; }

    /* ── Results ── */
    .results-placeholder { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 300px; color: var(--text3); text-align: center; gap: .75rem; }
    .results-placeholder-icon { font-size: 3rem; opacity: .3; }
    .results-placeholder-text { font-size: .8rem; font-family: var(--mono); }
    .results-content { display: none; }
    .results-content.show { display: block; }

    .stats-bar { display: flex; flex-wrap: wrap; gap: .75rem; margin-bottom: 1rem; }
    .stat-chip { display: flex; flex-direction: column; background: var(--bg3); border: 1px solid var(--border2); border-radius: 8px; padding: 8px 12px; min-width: 80px; }
    .stat-label { font-size: .65rem; color: var(--text3); text-transform: uppercase; letter-spacing: .08em; font-family: var(--mono); }
    .stat-value { font-size: 1.05rem; font-weight: 600; color: var(--accent3); font-family: var(--mono); }

    .output-tabs { display: flex; gap: 4px; margin-bottom: 1rem; border-bottom: 1px solid var(--border); }
    .out-tab { padding: 6px 12px; border: none; background: transparent; color: var(--text3); cursor: pointer; font-family: var(--sans); font-size: .78rem; border-bottom: 2px solid transparent; margin-bottom: -1px; transition: all .15s; }
    .out-tab:hover { color: var(--text); }
    .out-tab.active { color: var(--accent); border-bottom-color: var(--accent); }
    .out-panel { display: none; }
    .out-panel.active { display: block; }

    pre.alignment { background: var(--bg); border: 1px solid var(--border); border-radius: 8px; padding: 1rem; overflow: auto; font-family: var(--mono); font-size: .72rem; line-height: 1.55; color: var(--text); max-height: 450px; white-space: pre; }
    .align-star  { color: var(--green); }
    .align-colon { color: #a3e635; }
    .align-dot   { color: var(--yellow); }
    .align-header{ color: #93c5fd; }

    .btn-download { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: rgba(74,222,128,0.1); border: 1px solid rgba(74,222,128,0.3); color: var(--green); border-radius: 6px; font-family: var(--mono); font-size: .75rem; cursor: pointer; text-decoration: none; transition: all .15s; margin-top: .75rem; }
    .btn-download:hover { background: rgba(74,222,128,0.18); box-shadow: 0 0 12px rgba(74,222,128,.15); }

    footer { position: relative; z-index: 1; text-align: center; padding: 2rem; color: var(--text3); font-size: .72rem; font-family: var(--mono); border-top: 1px solid var(--border); }
    footer a { color: var(--text2); text-decoration: none; }
    footer a:hover { color: var(--accent); }
  </style>
</head>
<body>

<!-- ─── Header ─── -->
<header>
  <div class="logo">
    Clustal<span>Ω</span>
    <div class="version-badge">Multiple Sequence Alignment</div>
  </div>
  <div class="status-pill">
    <?php if ($clustalo_available): ?>
      <div class="status-dot"></div>
      clustalo <?= htmlspecialchars($clustalo_version) ?> ready
    <?php else: ?>
      <div class="status-dot offline"></div>
      clustalo not found
    <?php endif; ?>
  </div>
</header>

<!-- ─── Main ─── -->
<main class="app-container">

  <!-- ─ INPUT CARD ─ -->
  <div class="card" style="grid-row: span 2;">
    <div class="card-header">
      <div class="card-icon blue">⌨</div>
      <div class="card-title">Input Sequences</div>
    </div>
    <div class="card-body">

      <div class="input-tabs">
        <button class="tab-btn active" data-tab="fasta">FASTA</button>
        <button class="tab-btn" data-tab="uniprot">UniProt IDs</button>
        <button class="tab-btn" data-tab="pdb">PDB IDs</button>
        <button class="tab-btn" data-tab="file">File Upload</button>
      </div>

      <!-- FASTA -->
      <div class="input-panel active" id="panel-fasta">
        <label>Paste FASTA sequences — each must start with a <code style="color:var(--accent3)">&gt;header</code> line</label>
        <textarea id="seq-fasta" placeholder=">hemoglobin_alpha
MVLSPADKTNVKAAWGKVGAHAGEYGAEALERMFLSFPTTKTYFPHF
DLSHGSAQVKGHGKKVADALTNAVAHVDDMPNALSALSDLHAHKLRV
>hemoglobin_beta
MVHLTPEEKSAVTALWGKVNVDEVGGEALGRLLVVYPWTQRFFESF
GDLSTPDAVMGNPKVKAHGKKVLGAFSDGLAHLDNLKGTFATLSEL"></textarea>
        <div class="hint">Minimum 2 sequences. Set Protein / DNA / RNA in Alignment Options.</div>
      </div>

      <!-- UniProt -->
      <div class="input-panel" id="panel-uniprot">
        <label>UniProt accession IDs — one per line</label>
        <textarea id="seq-uniprot" placeholder="P69905
P68871
P02042
P01942" style="min-height:120px;"></textarea>
        <div class="hint">
          Find IDs at <a href="https://www.uniprot.org" target="_blank">uniprot.org</a> —
          the short code at the top of each entry (e.g. P69905).
          Sequences are fetched automatically. Always protein.
        </div>
      </div>

      <!-- PDB -->
      <div class="input-panel" id="panel-pdb">
        <label>RCSB PDB entry IDs — one per line</label>
        <textarea id="seq-pdb" placeholder="1HHO
2HHB
1A3N
1GZX" style="min-height:120px;"></textarea>
        <div class="hint">
          Find IDs at <a href="https://www.rcsb.org" target="_blank">rcsb.org</a> —
          the 4-character code at the top of each entry (e.g. 1HHO).
          Chain suffixes stripped automatically (e.g. 1SBIA → 1SBI). Always protein.
        </div>
      </div>

      <!-- File upload -->
      <div class="input-panel" id="panel-file">
        <label>Upload FASTA file</label>
        <div class="file-drop" id="file-drop" onclick="document.getElementById('fasta-file').click()">
          <div class="file-drop-icon">📄</div>
          <div class="file-drop-text">Drop FASTA file here or <strong>click to browse</strong></div>
          <div class="file-drop-text" style="margin-top:4px;font-size:.68rem;color:var(--text3)">.fasta .fa .fas .txt .seq · max 16 MB</div>
          <div class="file-name" id="file-name-display"></div>
          <input type="file" id="fasta-file" accept=".fasta,.fa,.fas,.txt,.seq">
        </div>
      </div>

    </div>
  </div>

  <!-- ─ OPTIONS CARD ─ -->
  <div class="card">
    <div class="card-header">
      <div class="card-icon purple">⚙</div>
      <div class="card-title">Alignment Options</div>
    </div>
    <div class="card-body">

      <div class="form-group" style="margin-bottom:1rem;">
        <label>Sequence Type</label>
        <div class="seg-control">
          <button class="seg-btn active" data-type="protein" onclick="setSeqType('protein')">Protein</button>
          <button class="seg-btn"        data-type="dna"     onclick="setSeqType('dna')">DNA</button>
          <button class="seg-btn"        data-type="rna"     onclick="setSeqType('rna')">RNA</button>
        </div>
        <div class="compat-warning" id="seqtype-compat-warn">
          ⚠ UniProt and PDB always return protein sequences. Sequence type locked to Protein.
        </div>
      </div>

      <div class="options-grid">
        <div class="form-group">
          <label>Output Format</label>
          <select id="out-format">
            <?php foreach ($output_formats as $key => $label): ?>
              <option value="<?= $key ?>"<?= $key === 'clustal' ? ' selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Guide Tree Iterations</label>
          <div class="range-label">
            <span>Refine alignment (0 = default)</span>
            <span id="iter-val">0</span>
          </div>
          <input type="range" id="iterations" min="0" max="5" value="0"
            oninput="document.getElementById('iter-val').textContent=this.value">
        </div>
      </div>

      <div class="form-group" style="margin-top:1rem;">
        <label>Extra ClustalOmega Options <span style="color:var(--text3)">(advanced)</span></label>
        <input type="text" id="extra-opts" placeholder="e.g. --threads=2 --use-kimura">
        <div class="hint">Additional flags passed directly to clustalo. Use with care.</div>
      </div>

    </div>
  </div>

  <!-- ─ RUN CARD ─ -->
  <div class="card">
    <div class="card-header">
      <div class="card-icon green">▶</div>
      <div class="card-title">Run Alignment</div>
    </div>
    <div class="card-body">

      <div id="alert-error"   class="alert alert-error"></div>
      <div id="alert-warning" class="alert alert-warning"></div>

      <div style="font-size:.78rem;color:var(--text2);line-height:1.6;margin-bottom:1rem;">
        ClustalOmega performs progressive multiple sequence alignment using HMM profile techniques.
        Supports protein, DNA, and RNA sequences.
      </div>

      <?php if (!$clustalo_available): ?>
      <div class="alert alert-warning show">
        ⚠ ClustalOmega not detected. Set the <code>CLUSTALO_PATH</code> environment variable
        or ensure <code>clustalo</code> is in the server's PATH.
      </div>
      <?php endif; ?>

      <button class="btn-run" id="run-btn" onclick="runAlignment()"
        <?= !$clustalo_available ? 'disabled' : '' ?>>
        <div class="spinner" id="spinner"></div>
        <span id="run-btn-text">▶ Run Alignment</span>
      </button>
      <div class="progress-bar-wrap" id="progress-wrap">
        <div class="progress-bar" id="progress-bar"></div>
      </div>

    </div>
  </div>

  <!-- ─ RESULTS CARD ─ -->
  <div class="card" style="grid-column: 1/-1;">
    <div class="card-header">
      <div class="card-icon blue">≡</div>
      <div class="card-title">Alignment Results</div>
    </div>
    <div class="card-body">

      <div class="results-placeholder" id="results-placeholder">
        <div class="results-placeholder-icon">🧬</div>
        <div class="results-placeholder-text">Submit sequences to run alignment</div>
      </div>

      <div class="results-content" id="results-content">
        <div class="stats-bar" id="stats-bar"></div>

        <div class="output-tabs">
          <button class="out-tab active" onclick="showOutPanel('formatted')">Formatted</button>
          <button class="out-tab"        onclick="showOutPanel('raw')">Raw</button>
        </div>

        <div class="out-panel active" id="out-formatted">
          <pre class="alignment" id="alignment-formatted"></pre>
        </div>
        <div class="out-panel" id="out-raw">
          <pre class="alignment" id="alignment-raw"></pre>
        </div>

        <a class="btn-download" id="download-link" href="#">⬇ Download Alignment File</a>
      </div>
    </div>
  </div>

</main>

<footer>
  Powered by <a href="https://www.clustal.org/omega/" target="_blank">Clustal Omega</a> ·
  Sievers et al., 2011 · <a href="https://doi.org/10.1038/msb.2011.75" target="_blank">doi:10.1038/msb.2011.75</a> ·
  Sequences from <a href="https://www.uniprot.org" target="_blank">UniProt</a> &amp;
  <a href="https://www.rcsb.org" target="_blank">RCSB PDB</a>
</footer>

<script>
  // ─── Sequence type ──────────────────────────────────────────────────────
  let currentSeqType = 'protein';
  const PROTEIN_ONLY_TABS = ['uniprot', 'pdb'];

  function setSeqType(type) {
    currentSeqType = type;
    document.querySelectorAll('.seg-btn').forEach(b => b.classList.remove('active'));
    document.querySelector(`.seg-btn[data-type="${type}"]`).classList.add('active');
  }

  // ─── Tab switching ──────────────────────────────────────────────────────
  function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.input-panel').forEach(p => p.classList.remove('active'));
    document.querySelector(`.tab-btn[data-tab="${tab}"]`).classList.add('active');
    document.getElementById('panel-' + tab).classList.add('active');

    const proteinOnly = PROTEIN_ONLY_TABS.includes(tab);
    document.querySelectorAll('.seg-btn').forEach(b => {
      b.disabled = proteinOnly && b.dataset.type !== 'protein';
    });
    const warn = document.getElementById('seqtype-compat-warn');
    if (proteinOnly) { setSeqType('protein'); warn.classList.add('show'); }
    else             { warn.classList.remove('show'); }
  }

  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => switchTab(btn.dataset.tab));
  });

  // ─── File drag & drop ───────────────────────────────────────────────────
  const fileDrop = document.getElementById('file-drop');
  const fileInput = document.getElementById('fasta-file');
  const fileNameDisplay = document.getElementById('file-name-display');

  fileDrop.addEventListener('dragover', e => { e.preventDefault(); fileDrop.classList.add('drag-over'); });
  fileDrop.addEventListener('dragleave', () => fileDrop.classList.remove('drag-over'));
  fileDrop.addEventListener('drop', e => {
    e.preventDefault(); fileDrop.classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    if (file) { fileInput.files = e.dataTransfer.files; fileNameDisplay.textContent = '📎 ' + file.name; }
  });
  fileInput.addEventListener('change', () => {
    if (fileInput.files[0]) fileNameDisplay.textContent = '📎 ' + fileInput.files[0].name;
  });

  // ─── Output tabs ────────────────────────────────────────────────────────
  function showOutPanel(id) {
    document.querySelectorAll('.out-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.out-panel').forEach(p => p.classList.remove('active'));
    document.querySelector(`.out-tab[onclick*="${id}"]`).classList.add('active');
    document.getElementById('out-' + id).classList.add('active');
  }

  // ─── Alerts ─────────────────────────────────────────────────────────────
  function showAlert(type, msg) {
    hideAlerts();
    const el = document.getElementById('alert-' + type);
    if (el) { el.textContent = msg; el.classList.add('show'); }
  }
  function hideAlerts() {
    document.querySelectorAll('.alert').forEach(a => a.classList.remove('show'));
  }

  // ─── Conservation line colouring (Clustal format) ───────────────────────
  function formatClustal(text) {
    return text.split('\n').map(line => {
      if (line.match(/^[\s\*\:\.]/) && !line.startsWith('CLUSTAL')) {
        return line
          .replace(/\*/g, '<span class="align-star">*</span>')
          .replace(/:/g,  '<span class="align-colon">:</span>')
          .replace(/\./g, '<span class="align-dot">.</span>');
      } else if (line.startsWith('>')) {
        return `<span class="align-header">${line}</span>`;
      }
      return line;
    }).join('\n');
  }

  // ─── Progress bar ────────────────────────────────────────────────────────
  let progressInterval = null;
  function startProgress() {
    const bar = document.getElementById('progress-bar');
    document.getElementById('progress-wrap').classList.add('show');
    bar.style.width = '0%';
    let pct = 0;
    progressInterval = setInterval(() => {
      pct = Math.min(pct + Math.random() * 8, 90);
      bar.style.width = pct + '%';
    }, 300);
  }
  function stopProgress() {
    clearInterval(progressInterval);
    const bar = document.getElementById('progress-bar');
    bar.style.width = '100%';
    setTimeout(() => { document.getElementById('progress-wrap').classList.remove('show'); bar.style.width = '0%'; }, 600);
  }

  // ─── Run alignment ───────────────────────────────────────────────────────
  async function runAlignment() {
    hideAlerts();
    const runBtn    = document.getElementById('run-btn');
    const spinner   = document.getElementById('spinner');
    const btnText   = document.getElementById('run-btn-text');
    const activeTab = document.querySelector('.tab-btn.active').dataset.tab;

    const fd = new FormData();
    fd.append('out_format',  document.getElementById('out-format').value);
    fd.append('seq_type',    currentSeqType);
    fd.append('extra_opts',  document.getElementById('extra-opts').value);
    fd.append('iterations',  document.getElementById('iterations').value);

    if (activeTab === 'file') {
      const f = fileInput.files[0];
      if (!f) { showAlert('error', 'Please select a FASTA file to upload.'); return; }
      fd.append('input_mode', 'file');
      fd.append('fasta_file', f);
    } else if (activeTab === 'fasta') {
      const txt = document.getElementById('seq-fasta').value.trim();
      if (!txt) { showAlert('error', 'Please paste at least 2 FASTA sequences.'); return; }
      fd.append('input_mode', 'fasta');
      fd.append('sequences',  txt);
    } else if (activeTab === 'uniprot') {
      const txt = document.getElementById('seq-uniprot').value.trim();
      if (!txt) { showAlert('error', 'Please enter at least 2 UniProt accession IDs.'); return; }
      fd.append('input_mode', 'uniprot');
      fd.append('sequences',  txt);
    } else if (activeTab === 'pdb') {
      const txt = document.getElementById('seq-pdb').value.trim();
      if (!txt) { showAlert('error', 'Please enter at least 2 PDB entry IDs.'); return; }
      fd.append('input_mode', 'pdb');
      fd.append('sequences',  txt);
    }

    runBtn.disabled = true;
    spinner.style.display = 'block';
    btnText.textContent = 'Running alignment…';
    startProgress();
    document.getElementById('results-placeholder').style.display = 'flex';
    document.getElementById('results-content').classList.remove('show');

    try {
      const resp = await fetch('./align.php', { method: 'POST', body: fd });
      const text = await resp.text();
      let data;
      try {
        data = JSON.parse(text);
      } catch(e) {
        showAlert('error', '✗ Server returned unexpected response:\n' + text.substring(0, 600));
        return;
      }
      stopProgress();

      if (!data.success) {
        showAlert('error', '✗ ' + data.error);
      } else {
        if (data.warnings && data.warnings.length > 0) {
          showAlert('warning', '⚠ ' + data.warnings.join('\n'));
        }

        const stats = data.stats;
        const seqTypeColors = { protein: '#93c5fd', dna: '#86efac', rna: '#fde68a' };
        const seqTypeColor  = seqTypeColors[data.seq_type] || 'var(--accent3)';

        document.getElementById('stats-bar').innerHTML = `
          <div class="stat-chip"><div class="stat-label">Sequences</div><div class="stat-value">${stats.sequences}</div></div>
          <div class="stat-chip"><div class="stat-label">Seq type</div><div class="stat-value" style="font-size:.85rem;color:${seqTypeColor}">${stats.seq_type}</div></div>
          <div class="stat-chip"><div class="stat-label">Input</div><div class="stat-value" style="font-size:.8rem">${data.input_type.toUpperCase()}</div></div>
          <div class="stat-chip"><div class="stat-label">Min length</div><div class="stat-value">${stats.min_length}</div></div>
          <div class="stat-chip"><div class="stat-label">Max length</div><div class="stat-value">${stats.max_length}</div></div>
          <div class="stat-chip"><div class="stat-label">Avg length</div><div class="stat-value">${stats.avg_length}</div></div>
          <div class="stat-chip"><div class="stat-label">Format</div><div class="stat-value" style="font-size:.72rem">${stats.format}</div></div>
        `;

        document.getElementById('alignment-raw').textContent = data.result;
        if (data.out_format === 'clustal') {
          document.getElementById('alignment-formatted').innerHTML = formatClustal(data.result);
        } else {
          document.getElementById('alignment-formatted').textContent = data.result;
        }

        document.getElementById('download-link').href     = 'download.php?file=' + data.result_file;
        document.getElementById('download-link').download = data.result_file;

        document.getElementById('results-placeholder').style.display = 'none';
        document.getElementById('results-content').classList.add('show');
      }

    } catch (err) {
      stopProgress();
      showAlert('error', '✗ Network error: ' + err.message + '\n\nMake sure the page is served by a PHP-enabled web server, not opened directly as a file.');
    } finally {
      runBtn.disabled = false;
      spinner.style.display = 'none';
      btnText.textContent = '▶ Run Alignment';
    }
  }
</script>
</body>
</html>

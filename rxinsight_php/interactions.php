<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/foot.php';

$found_drugs         = [];
$interactions_by_drug = [];
$query               = '';
$submitted           = false;

// Prefill from GET (linked from conditions page)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['prefill_drug'])) {
    $query = trim($_GET['prefill_drug']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $query     = trim($_POST['drug_name'] ?? '');
    $submitted = true;
}

if ($query !== '') {
    $drug_names = array_filter(array_map('trim', explode(',', $query)));

    foreach ($drug_names as $name) {
        $stmt = $pdo->prepare(
            'SELECT * FROM Drugs WHERE Name LIKE ? OR Brand LIKE ? LIMIT 1'
        );
        $like = '%' . $name . '%';
        $stmt->execute([$like, $like]);
        $drug = $stmt->fetch();
        if ($drug) {
            $drug['Name'] = ucwords(strtolower($drug['Name']));
            $found_drugs[] = $drug;
        }
    }

    foreach ($found_drugs as $drug) {
        $stmt = $pdo->prepare(
            'SELECT di.*, 
                    d1.Name AS drug1_name, 
                    d2.Name AS drug2_name,
                    di.Level AS severity
             FROM DrugInteractions di
             JOIN Drugs d1 ON d1.idDrug = di.Drugs_idDrugA
             JOIN Drugs d2 ON d2.idDrug = di.Drugs_idDrugB
             WHERE di.Drugs_idDrugA = ? OR di.Drugs_idDrugB = ?'
        );
        $stmt->execute([$drug['idDrug'], $drug['idDrug']]);
        $conflicts = $stmt->fetchAll();

        $interactions = [];
        foreach ($conflicts as $c) {
            $interactions[] = [
                'drug1_name' => ucwords(strtolower($c['drug1_name'])),
                'drug2_name' => ucwords(strtolower($c['drug2_name'])),
                'severity'   => $c['severity'],
            ];
        }
        $interactions_by_drug[$drug['Name']] = $interactions;
    }
}

// Categorise severity
function categorise(array $interactions): array {
    $buckets = ['major' => [], 'moderate' => [], 'minor' => [], 'unknown' => []];
    foreach ($interactions as $i) {
        $s = strtolower(trim($i['severity'] ?? ''));
        if (in_array($s, ['major','x','severe','high','3','contraindicated'])) {
            $buckets['major'][] = $i;
        } elseif (in_array($s, ['moderate','d','medium','2','significant'])) {
            $buckets['moderate'][] = $i;
        } elseif (in_array($s, ['minor','c','low','1','minimal','mild'])) {
            $buckets['minor'][] = $i;
        } else {
            $buckets['unknown'][] = $i;
        }
    }
    return $buckets;
}

render_head('Interaction Checker');
?>

<div class="quick-container">

    <?php if ($submitted && empty($found_drugs)): ?>
        <div class="interaction-empty" style="margin-bottom:20px">
            ⚠️ No medication found with the name: <strong><?= htmlspecialchars($_POST['drug_name'] ?? '') ?></strong>.
        </div>
    <?php endif; ?>

    <div class="quick-header">
        <h1 class="quick-title">Quick Interaction Tool</h1>
        <p class="quick-subtitle">Check for dangerous drug combinations instantly. No login required.</p>
    </div>

    <div class="quick-card">
        <form method="post" action="">
            <?= csrf_field() ?>
            <div class="form-group autocomplete-wrapper">
                <label class="form-label">Enter Medications (e.g. Aspirin, Warfarin)</label>
                <input type="text" name="drug_name" id="drug_name" class="form-input"
                       placeholder="Type drug names here..."
                       value="<?= htmlspecialchars($query) ?>" autocomplete="off">
                <ul id="autocomplete-list" class="autocomplete-list"></ul>
            </div>
            <div class="center">
                <button type="submit" class="quick-button">Search Interactions</button>
            </div>
        </form>
    </div>

    <hr class="neon-divider">

    <?php if (!empty($interactions_by_drug)): ?>

        <div class="severity-filters" id="severity-filters">
            <span class="filter-label2">Show:</span>
            <button class="filter-btn2 filter-major active"    data-target="severity-major"    onclick="toggleFilter(this)">🔴 Major</button>
            <button class="filter-btn2 filter-moderate active" data-target="severity-moderate" onclick="toggleFilter(this)">🟠 Moderate</button>
            <button class="filter-btn2 filter-minor active"    data-target="severity-minor"    onclick="toggleFilter(this)">🟢 Minor</button>
            <button class="filter-btn2 filter-unknown active"  data-target="severity-unknown"  onclick="toggleFilter(this)">⚪ Unknown</button>
        </div>

        <?php foreach ($interactions_by_drug as $drug_name => $interactions): ?>
            <?php $buckets = categorise($interactions); ?>

            <div class="drug-section">
                <h3 class="drug-section-title"><?= htmlspecialchars($drug_name) ?> — Interactions</h3>

                <?php if (empty($interactions)): ?>
                    <div class="success-box"><p>✅ No known interactions for <?= htmlspecialchars($drug_name) ?></p></div>
                <?php else: ?>

                    <div class="severity-grid">
                        <?php foreach (['major' => 'Major', 'moderate' => 'Moderate', 'minor' => 'Minor'] as $key => $label): ?>
                        <div class="severity-box severity-<?= $key ?>">
                            <div class="severity-box-header">
                                <span class="severity-dot"></span>
                                <?= $label ?>
                                <span class="severity-count"><?= count($buckets[$key]) ?></span>
                            </div>
                            <div class="severity-box-body">
                                <?php if (!empty($buckets[$key])): ?>
                                    <?php foreach ($buckets[$key] as $inter): ?>
                                        <div class="severity-item">
                                            <span class="sev-drug"><?= htmlspecialchars($inter['drug1_name']) ?></span>
                                            <span class="sev-plus">+</span>
                                            <span class="sev-drug"><?= htmlspecialchars($inter['drug2_name']) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="severity-none">No <?= $key ?> interactions</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!empty($buckets['unknown'])): ?>
                    <div class="severity-box severity-unknown" style="margin-top:15px">
                        <div class="severity-box-header">
                            <span class="severity-dot"></span>
                            Unknown Severity
                            <span class="severity-count"><?= count($buckets['unknown']) ?></span>
                        </div>
                        <div class="severity-box-body">
                            <?php foreach ($buckets['unknown'] as $inter): ?>
                                <div class="severity-item">
                                    <span class="sev-drug"><?= htmlspecialchars($inter['drug1_name']) ?></span>
                                    <span class="sev-plus">+</span>
                                    <span class="sev-drug"><?= htmlspecialchars($inter['drug2_name']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                <?php endif; ?>
            </div>

        <?php endforeach; ?>

    <?php elseif (!empty($found_drugs)): ?>
        <div class="success-box">
            <h2>✅ No Interactions Found</h2>
            <p>Based on our database, no known interactions were found between:</p>
            <div class="drug-tags">
                <?php foreach ($found_drugs as $d): ?>
                    <span class="drug-tag"><?= htmlspecialchars($d['Name']) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="quick-footer">
        <p>Need a detailed analysis with side effects based on your age and gender?</p>
        <a href="<?= BASE_URL ?>/login.php" class="login-link">Log in for Full Analysis</a>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('drug_name');
    var list  = document.getElementById('autocomplete-list');
    var timer;
    if (!input) return;

    input.addEventListener('input', function () {
        clearTimeout(timer);
        var parts = this.value.split(/[,\s]+/);
        var query = parts[parts.length - 1].trim();
        if (query.length < 2) { list.style.display = 'none'; return; }

        timer = setTimeout(function () {
            fetch('<?= BASE_URL ?>/api/drugs.php?q=' + encodeURIComponent(query))
                .then(r => r.json())
                .then(suggestions => {
                    list.innerHTML = '';
                    if (!suggestions.length) { list.style.display = 'none'; return; }
                    suggestions.forEach(name => {
                        var li = document.createElement('li');
                        li.textContent = name;
                        li.addEventListener('mousedown', e => {
                            e.preventDefault();
                            parts[parts.length - 1] = name;
                            input.value = parts.filter(Boolean).join(', ') + ', ';
                            list.style.display = 'none';
                            input.focus();
                        });
                        list.appendChild(li);
                    });
                    list.style.display = 'block';
                });
        }, 200);
    });

    document.addEventListener('click', e => { if (e.target !== input) list.style.display = 'none'; });
});

function toggleFilter(btn) {
    btn.classList.toggle('active');
    var target = btn.getAttribute('data-target');
    document.querySelectorAll('.' + target).forEach(box => {
        box.classList.toggle('hidden', !btn.classList.contains('active'));
    });
}
</script>

<?php render_foot(); ?>

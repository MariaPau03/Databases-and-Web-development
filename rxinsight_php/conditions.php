<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/foot.php';

// ── Route normalisation ───────────────────────────────────────
$ROUTE_NORMALIZATION = [
    'ORAL' => 'Oral', 'SUBCUTANEOUS' => 'Subcutaneous',
    'INTRAMUSCULAR' => 'Intramuscular', 'INTRAVENOUS' => 'Intravenous',
    'TOPICAL' => 'Topical', 'INHALATION' => 'Respiratory (Inhalation)',
    'NASAL' => 'Nasal', 'RECTAL' => 'Rectal', 'VAGINAL' => 'Vaginal',
    'OPHTHALMIC' => 'Ophthalmic', 'SUBLINGUAL' => 'Sublingual',
    'BUCCAL' => 'Buccal', 'INTRADERMAL' => 'Intradermal',
    'INTRACAMERAL' => 'Intracameral', 'INTRAVITREAL' => 'Intravitreal',
    'INTRAPERITONEAL' => 'Intraperitoneal', 'INTRA-ARTICULAR' => 'Intra-Articular',
    'INTRAUTERINE' => 'Intrauterine', 'INTRACARDIAC' => 'Intracardiac',
    'AURICULAR (OTIC)' => 'Auricular (Otic)', 'UNKNOWN' => 'Unknown', 'UNK' => 'Unknown',
];

function normalize_routes(string $route_string, array $map): array {
    if (!$route_string) return ['Unknown'];
    $parts = preg_split('/[;\/,]/', $route_string);
    $out   = [];
    foreach ($parts as $p) {
        $clean = strtoupper(trim($p));
        $out[] = $map[$clean] ?? ucwords(strtolower($clean));
    }
    return array_unique($out);
}

// Build dropdown options
$raw_routes = $pdo->query('SELECT DISTINCT Route FROM Drugs WHERE Route IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN);
$all_routes = [];
foreach ($raw_routes as $r) {
    foreach (normalize_routes($r, $ROUTE_NORMALIZATION) as $nr) {
        $all_routes[$nr] = true;
    }
}
ksort($all_routes);
$dropdown_options = array_keys($all_routes);

// ── Handle form ───────────────────────────────────────────────
$submitted         = false;
$condition_query   = '';
$selected_route    = '';
$drugs_for_condition = null;
$matching_drugs    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $submitted       = true;
    $condition_query = trim($_POST['condition_name'] ?? '');
    $selected_route  = $_POST['route_filter'] ?? '';

    $stmt = $pdo->prepare('SELECT idDisease FROM Diseases WHERE Name LIKE ? LIMIT 1');
    $stmt->execute(['%' . $condition_query . '%']);
    $disease = $stmt->fetch();

    if ($disease) {
        $disease_id = $disease['idDisease'];
        $stmt2 = $pdo->prepare(
            'SELECT d.* FROM Drugs d
             JOIN Diseases_has_Drugs dd ON dd.Drugs_idDrug = d.idDrug
             WHERE dd.Diseases_idDisease = ?'
        );
        $stmt2->execute([$disease_id]);
        $drugs_for_condition = $stmt2->fetchAll();

        foreach ($drugs_for_condition as &$drug) {
            $drug['Name']  = ucwords(strtolower($drug['Name']));
            $drug['Brand'] = $drug['Brand'] ? ucwords(strtolower($drug['Brand'])) : '';
            $drug['normalized_routes'] = normalize_routes($drug['Route'] ?? '', $ROUTE_NORMALIZATION);

            // Top indications
            $si = $pdo->prepare(
                'SELECT dis.Name FROM Diseases dis
                 JOIN Diseases_has_Drugs dd2 ON dd2.Diseases_idDisease = dis.idDisease
                 WHERE dd2.Drugs_idDrug = ? LIMIT 5'
            );
            $si->execute([$drug['idDrug']]);
            $drug['top_indications'] = $si->fetchAll(PDO::FETCH_COLUMN);

            // Apply route filter
            if (!$selected_route || $selected_route === '' ||
                in_array($selected_route, $drug['normalized_routes'])) {
                $matching_drugs[] = $drug;
            }
        }
        unset($drug);
    }
}

render_head('Find Medication');
?>

<div class="med-container">

    <?php if ($submitted && $drugs_for_condition !== null && count($drugs_for_condition) > 0): ?>
        <div class="success-box" style="margin-bottom:20px">
            ✅ Medications found for: <strong><?= htmlspecialchars($condition_query) ?></strong>
        </div>
    <?php elseif ($submitted && ($drugs_for_condition === null || count($drugs_for_condition) === 0)): ?>
        <div class="interaction-empty" style="margin-bottom:20px">
            ⚠️ No medications found for: <strong><?= htmlspecialchars($condition_query) ?></strong>
        </div>
    <?php endif; ?>

    <div class="med-header">
        <h1 class="med-title">Find Medication</h1>
        <p class="med-subtitle">Enter a disease or condition to see available treatments.</p>
    </div>

    <div class="med-form-card">
        <form method="post" action="">
            <?= csrf_field() ?>

            <div class="med-field autocomplete-wrapper">
                <label class="med-label">Medical Condition</label>
                <input type="text" name="condition_name" id="condition_name" class="med-input"
                       placeholder="e.g. Hypertension, Diabetes"
                       value="<?= htmlspecialchars($condition_query) ?>" autocomplete="off">
                <ul id="autocomplete-list" class="autocomplete-list"></ul>
            </div>

            <div class="med-field">
                <label class="med-label">Route</label>
                <select name="route_filter" class="med-input">
                    <option value="">All</option>
                    <?php foreach ($dropdown_options as $opt): ?>
                        <option value="<?= htmlspecialchars($opt) ?>"
                            <?= ($selected_route === $opt) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($opt) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>Select a route to filter drugs, or "All" to see all.</small>
            </div>

            <div class="med-submit-wrapper">
                <button type="submit" class="med-submit">Find Medications</button>
            </div>
        </form>
    </div>

    <hr class="med-divider">

    <?php if (!empty($matching_drugs)): ?>
        <h2 class="med-results-title">
            Treatments found for: <span class="med-highlight">"<?= htmlspecialchars($condition_query) ?>"</span>
        </h2>

        <div class="med-results-grid">
            <?php foreach ($matching_drugs as $drug): ?>
            <div class="drug-card">
                <h3 class="drug-name"><?= htmlspecialchars($drug['Name']) ?></h3>
                <?php if ($drug['Brand']): ?>
                    <p><strong>Brand:</strong> <?= htmlspecialchars($drug['Brand']) ?></p>
                <?php endif; ?>
                <?php if ($drug['DosageForm']): ?>
                    <p><strong>Dosage Form:</strong> <?= htmlspecialchars($drug['DosageForm']) ?></p>
                <?php endif; ?>
                <?php if ($drug['Route']): ?>
                    <p><strong>Route:</strong> <?= htmlspecialchars($drug['Route']) ?></p>
                <?php endif; ?>
                <?php if ($drug['PharmClass']): ?>
                    <p><strong>Pharmacological Class:</strong> <?= htmlspecialchars($drug['PharmClass']) ?></p>
                <?php endif; ?>
                <?php if (!empty($drug['top_indications'])): ?>
                    <p><strong>Common Indications:</strong> <?= htmlspecialchars(implode(', ', $drug['top_indications'])) ?></p>
                <?php endif; ?>
                <div class="drug-link-wrapper">
                    <a href="<?= BASE_URL ?>/interactions.php?prefill_drug=<?= urlencode($drug['Name']) ?>" class="btn btn-info">
                        Check interactions for this drug
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    <?php elseif ($submitted && $drugs_for_condition !== null && !empty($drugs_for_condition) && empty($matching_drugs)): ?>
        <div class="interaction-empty">
            ⚠️ No medications found for the selected route: <strong><?= htmlspecialchars($selected_route) ?></strong>. Please try another.
        </div>
    <?php endif; ?>

    <div class="med-back">
        <a href="<?= BASE_URL ?>/index.php" class="med-back-link">&larr; Back to Home</a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('condition_name');
    var list  = document.getElementById('autocomplete-list');
    var timer;
    if (!input) return;

    input.addEventListener('input', function () {
        clearTimeout(timer);
        var query = this.value.trim();
        if (query.length < 2) { list.style.display = 'none'; return; }
        timer = setTimeout(function () {
            fetch('<?= BASE_URL ?>/api/conditions.php?q=' + encodeURIComponent(query))
                .then(r => r.json())
                .then(suggestions => {
                    list.innerHTML = '';
                    if (!suggestions.length) { list.style.display = 'none'; return; }
                    suggestions.forEach(name => {
                        var li = document.createElement('li');
                        li.textContent = name;
                        li.addEventListener('mousedown', e => {
                            e.preventDefault();
                            input.value = name;
                            list.style.display = 'none';
                        });
                        list.appendChild(li);
                    });
                    list.style.display = 'block';
                });
        }, 200);
    });
    document.addEventListener('click', e => { if (e.target !== input) list.style.display = 'none'; });
});
</script>

<?php render_foot(); ?>

<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/foot.php';

$BLACKLIST = ['Wrong technique in product usage process'];

$submitted    = false;
$drug_query   = '';
$effects      = null;
$top_effects  = [];
$total_reports = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $submitted  = true;
    $drug_query = trim($_POST['drug_name'] ?? '');

    $stmt = $pdo->prepare('SELECT * FROM Drugs WHERE Name LIKE ? LIMIT 1');
    $stmt->execute(['%' . $drug_query . '%']);
    $drug = $stmt->fetch();

    if ($drug) {
        $placeholders = implode(',', array_fill(0, count($BLACKLIST), '?'));

        // Frequency from Entries
        $freq_sql = "
            SELECT se.idSideEffect, se.Name, COUNT(e.idEntry) AS cnt
            FROM SideEffects se
            JOIN Drugs_has_SideEffects dse ON dse.SideEffects_idSideEffect = se.idSideEffect
            JOIN Entries e ON e.SideEffects_idSideEffect = se.idSideEffect
            WHERE dse.Drugs_idDrug = ?
            AND se.Name NOT IN ($placeholders)
            GROUP BY se.idSideEffect
            ORDER BY cnt DESC
        ";
        $params = array_merge([$drug['idDrug']], $BLACKLIST);
        $stmt2  = $pdo->prepare($freq_sql);
        $stmt2->execute($params);
        $freq_rows = $stmt2->fetchAll();

        $freq_map     = [];
        $total_reports = 0;
        foreach ($freq_rows as $row) {
            $freq_map[$row['idSideEffect']] = $row['cnt'];
            $total_reports += $row['cnt'];
        }

        // All side effects for this drug
        $all_sql = "
            SELECT se.*
            FROM SideEffects se
            JOIN Drugs_has_SideEffects dse ON dse.SideEffects_idSideEffect = se.idSideEffect
            WHERE dse.Drugs_idDrug = ?
            AND se.Name NOT IN ($placeholders)
            ORDER BY se.Name
        ";
        $stmtAll = $pdo->prepare($all_sql);
        $stmtAll->execute(array_merge([$drug['idDrug']], $BLACKLIST));
        $effects = $stmtAll->fetchAll();

        // Build top 5
        $seen = [];
        foreach (array_slice($freq_rows, 0, 5) as $fr) {
            $top_effects[] = ['name' => $fr['Name'], 'count' => $fr['cnt']];
            $seen[$fr['idSideEffect']] = true;
        }
        foreach ($effects as $se) {
            if (count($top_effects) >= 5) break;
            if (!isset($seen[$se['idSideEffect']])) {
                $top_effects[] = ['name' => $se['Name'], 'count' => 0];
                $seen[$se['idSideEffect']] = true;
            }
        }
    }
}

render_head('Side Effects');
?>

<div class="med-container">

    <?php if ($submitted && $effects === null): ?>
        <div class="interaction-empty" style="margin-bottom:20px">
            ⚠️ No medication found with the name: <strong><?= htmlspecialchars($drug_query) ?></strong>.
        </div>
    <?php endif; ?>

    <div class="med-header">
        <h1 class="med-title">Drug Side Effects Lookup</h1>
        <p class="med-subtitle">Search for a medication to see its common side effects.</p>
    </div>

    <div class="med-form-card">
        <form method="post" action="">
            <?= csrf_field() ?>
            <div class="med-field autocomplete-wrapper">
                <label class="med-label">Drug Name</label>
                <input type="text" name="drug_name" id="drug_name" class="med-input"
                       placeholder="e.g. Aspirin, Warfarin"
                       value="<?= htmlspecialchars($drug_query) ?>" autocomplete="off">
                <ul id="autocomplete-list" class="autocomplete-list"></ul>
            </div>
            <div class="med-submit-wrapper">
                <button type="submit" class="med-submit">Search Side Effects</button>
            </div>
        </form>
    </div>

    <hr class="med-divider">

    <?php if (is_array($effects)): ?>
        <h2 class="med-results-title">
            Side effects found for: <span class="med-highlight">"<?= htmlspecialchars(ucwords(strtolower($drug_query))) ?>"</span>
        </h2>

        <?php if (!empty($top_effects)): ?>
        <div class="se-section-label">
            Most reported by patients
            <?php if ($total_reports > 0): ?>
                <span class="se-total-badge"><?= $total_reports ?> total reports</span>
            <?php endif; ?>
        </div>
        <div class="se-top-grid">
            <?php foreach ($top_effects as $i => $se): ?>
            <div class="se-top-card">
                <span class="se-rank">#<?= $i + 1 ?></span>
                <span class="se-name"><?= htmlspecialchars($se['name']) ?></span>
                <?php if ($se['count'] > 0): ?>
                    <span class="se-count-badge"><?= $se['count'] ?></span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (count($effects) > 5): ?>
        <div class="se-expand-wrapper">
            <button type="button" class="se-expand-btn" id="expand-btn" onclick="toggleAll()">
                <span id="expand-icon">&#9660;</span>
                View all <?= count($effects) ?> side effects
            </button>
        </div>

        <div id="all-effects" class="se-all-grid" style="display:none">
            <?php foreach ($effects as $se): ?>
                <div class="se-all-item"><?= htmlspecialchars($se['Name']) ?></div>
            <?php endforeach; ?>
        </div>

        <script>
            var totalCount = <?= count($effects) ?>;
            function toggleAll() {
                var box  = document.getElementById('all-effects');
                var btn  = document.getElementById('expand-btn');
                var icon = document.getElementById('expand-icon');
                if (box.style.display === 'none') {
                    box.style.display = 'grid';
                    btn.innerHTML = '<span id="expand-icon">&#9650;</span> Hide side effects';
                } else {
                    box.style.display = 'none';
                    btn.innerHTML = '<span id="expand-icon">&#9660;</span> View all ' + totalCount + ' side effects';
                }
            }
        </script>
        <?php endif; ?>

    <?php endif; ?>

    <div class="med-back">
        <a href="<?= BASE_URL ?>/index.php" class="med-back-link">&larr; Back to Home</a>
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
        var query = this.value.trim();
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

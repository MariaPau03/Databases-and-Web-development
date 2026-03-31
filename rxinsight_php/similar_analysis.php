<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/foot.php';

require_login();

$age        = (int)($_GET['age'] ?? 0);
$gender     = trim($_GET['gender'] ?? '');
$disease_id = (int)($_GET['disease_id'] ?? 0);
$min_age    = $age - 10;
$max_age    = $age + 10;

// Disease name
$stmt = $pdo->prepare('SELECT Name FROM Diseases WHERE idDisease = ?');
$stmt->execute([$disease_id]);
$disease = $stmt->fetch();
$disease_name = $disease['Disease_name'] ?? ($disease['Name'] ?? null);

// Similar entries (exclude current user)
$stmt2 = $pdo->prepare(
    'SELECT e.*, d.Name AS drug_name, se.Name AS side_effect_name
     FROM Entries e
     LEFT JOIN Drugs d ON d.idDrug = e.Drugs_idDrug
     LEFT JOIN SideEffects se ON se.idSideEffect = e.SideEffects_idSideEffect
     WHERE e.Gender = ? AND e.Diseases_idDisease = ?
       AND e.Age BETWEEN ? AND ?
       AND e.Users_idUsers != ?'
);
$stmt2->execute([$gender, $disease_id, $min_age, $max_age, current_user_id()]);
$similar_entries = $stmt2->fetchAll();

$total_similar = count($similar_entries);

// Count drugs and side effects
$drug_counter   = [];
$side_counter   = [];
$scores         = [];

foreach ($similar_entries as $e) {
    $dn = $e['drug_name'] ?? 'Unknown';
    $sn = $e['side_effect_name'] ?? 'Unknown';
    $drug_counter[$dn] = ($drug_counter[$dn] ?? 0) + 1;
    $side_counter[$sn] = ($side_counter[$sn] ?? 0) + 1;
    if ($e['ImprovementScore']) $scores[] = (float)$e['ImprovementScore'];
}

arsort($drug_counter);
arsort($side_counter);
$top_drugs        = array_slice($drug_counter, 0, 5, true);
$top_side_effects = array_slice($side_counter, 0, 5, true);
$avg_score        = !empty($scores) ? round(array_sum($scores) / count($scores), 1) : null;

render_head('Similar Patients');
?>

<div class="similar-container" style="max-width:900px;margin:0 auto;padding:0 0 32px">

    <div class="similar-header" style="text-align:center;margin-bottom:32px">
        <h1 class="similar-title" style="font-size:clamp(1.6rem,3.5vw,2.2rem);font-weight:700;font-style:italic">Patients Similar to You</h1>
        <p class="similar-subtitle" style="color:var(--t-1)">
            Gender: <strong><?= htmlspecialchars($gender) ?></strong> |
            Age range: <strong><?= $min_age ?>–<?= $max_age ?></strong>
            <?php if ($disease_name): ?> | Condition: <strong><?= htmlspecialchars($disease['Name']) ?></strong><?php endif; ?>
        </p>
    </div>

    <div class="similar-summary-card" style="text-align:center;background:var(--surface-2);border:1px solid var(--b-1);border-radius:var(--r16);padding:32px;margin-bottom:28px">
        <div style="font-family:var(--serif);font-size:3.5rem;font-weight:700;color:var(--teal);letter-spacing:-2px"><?= $total_similar ?></div>
        <div style="color:var(--t-1);font-size:.88rem;margin-top:6px">Matching patient profiles found</div>
        <?php if ($total_similar < 5): ?>
            <div style="color:var(--saffron);font-size:.8rem;margin-top:10px">Limited data — results may not be representative.</div>
        <?php endif; ?>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px">

        <div style="background:var(--surface-2);border:1px solid var(--b-1);border-radius:var(--r12);padding:22px 26px">
            <h3 style="color:var(--t-2);text-transform:uppercase;letter-spacing:1.8px;font-size:.62rem;margin-bottom:18px">Most Used Medications</h3>
            <?php if (!empty($top_drugs)): ?>
                <canvas id="drugsChart" height="220"></canvas>
            <?php else: ?>
                <p style="color:var(--t-2);text-align:center;padding:32px">No medication data available.</p>
            <?php endif; ?>
        </div>

        <div style="background:var(--surface-2);border:1px solid var(--b-1);border-radius:var(--r12);padding:22px 26px">
            <h3 style="color:var(--t-2);text-transform:uppercase;letter-spacing:1.8px;font-size:.62rem;margin-bottom:18px">Most Reported Side Effects</h3>
            <?php if (!empty($top_side_effects)): ?>
                <canvas id="effectsChart" height="220"></canvas>
            <?php else: ?>
                <p style="color:var(--t-2);text-align:center;padding:32px">No side effect data available.</p>
            <?php endif; ?>
        </div>
    </div>

    <div style="background:var(--surface-2);border:1px solid var(--b-1);border-radius:var(--r12);padding:22px 26px;text-align:center;margin-bottom:28px">
        <h3 style="color:var(--t-2);text-transform:uppercase;letter-spacing:1.8px;font-size:.62rem;margin-bottom:14px">Treatment Outcome</h3>
        <?php if ($avg_score): ?>
            <div style="font-family:var(--serif);font-size:3rem;font-weight:700;color:var(--teal)"><?= $avg_score ?> / 10</div>
            <div style="color:var(--t-1);font-size:.84rem;margin-top:6px">Average improvement score</div>
        <?php else: ?>
            <p style="color:var(--t-2)">No outcome data available.</p>
        <?php endif; ?>
    </div>

    <div style="text-align:center;margin-top:32px">
        <a href="<?= BASE_URL ?>/entry.php" style="color:var(--t-2);font-size:.76rem;font-family:var(--mono)">&larr; Back to Profile</a>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
Chart.defaults.color = '#aaa';
const NEON = '#03e9f4';
const GRID = 'rgba(255,255,255,0.05)';

<?php if (!empty($top_drugs)): ?>
new Chart(document.getElementById('drugsChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_keys($top_drugs)) ?>,
        datasets: [{ data: <?= json_encode(array_values($top_drugs)) ?>, backgroundColor: 'rgba(3,233,244,0.7)', borderRadius: 6 }]
    },
    options: { plugins: { legend: { display: false } }, scales: { x: { grid: { color: GRID } }, y: { grid: { color: GRID }, beginAtZero: true } } }
});
<?php endif; ?>

<?php if (!empty($top_side_effects)): ?>
new Chart(document.getElementById('effectsChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_keys($top_side_effects)) ?>,
        datasets: [{ data: <?= json_encode(array_values($top_side_effects)) ?>, backgroundColor: 'rgba(255,77,77,0.7)', borderRadius: 6 }]
    },
    options: { plugins: { legend: { display: false } }, scales: { x: { grid: { color: GRID } }, y: { grid: { color: GRID }, beginAtZero: true } } }
});
<?php endif; ?>
</script>

<?php render_foot(); ?>

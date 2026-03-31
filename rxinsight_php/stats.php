<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/foot.php';

require_login();

// Global stats
$total_drugs           = (int)$pdo->query('SELECT COUNT(*) FROM Drugs')->fetchColumn();
$total_interactions_db = (int)$pdo->query('SELECT COUNT(*) FROM DrugInteractions')->fetchColumn();
$total_sideeffects_db  = (int)$pdo->query('SELECT COUNT(*) FROM SideEffects')->fetchColumn();

// User stats
$uid = current_user_id();
$total_user_entries = (int)$pdo->prepare('SELECT COUNT(*) FROM Entries WHERE Users_idUsers = ?')
    ->execute([$uid]) ? $pdo->prepare('SELECT COUNT(*) FROM Entries WHERE Users_idUsers = ?') : 0;

$stmt = $pdo->prepare('SELECT COUNT(*) FROM Entries WHERE Users_idUsers = ?');
$stmt->execute([$uid]);
$total_user_entries = (int)$stmt->fetchColumn();

// Last 7 days
$labels = [];
$data   = [];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $stmt2 = $pdo->prepare('SELECT COUNT(*) FROM Entries WHERE Users_idUsers = ? AND DATE(Date) = ?');
    $stmt2->execute([$uid, $day]);
    $count   = (int)$stmt2->fetchColumn();
    $labels[] = date('M d', strtotime($day));
    $data[]   = $count;
}

// Top 5 drugs used by this user
$drug_stmt = $pdo->prepare(
    'SELECT d.Name, COUNT(e.idEntry) AS cnt
     FROM Drugs d
     JOIN Entries e ON e.Drugs_idDrug = d.idDrug
     WHERE e.Users_idUsers = ?
     GROUP BY d.idDrug
     ORDER BY cnt DESC
     LIMIT 5'
);
$drug_stmt->execute([$uid]);
$drug_rows   = $drug_stmt->fetchAll();
$drug_labels = array_column($drug_rows, 'Name');
$drug_data   = array_column($drug_rows, 'cnt');

render_head('Statistics');
?>

<div class="stats-container">

    <div class="stats-header">
        <h1 class="stats-title">Statistics</h1>
    </div>

    <div class="stats-header">
        <h2 class="stats-subtitle">Global Statistics</h2>
    </div>

    <div class="kpi-grid">
        <?php
        $kpis = [
            ['Side Effects in Database', $total_sideeffects_db,  '👤', '#a78bfa'],
            ['Drugs in Database',        $total_drugs,           '💊', '#34d399'],
            ['Known Interactions',       $total_interactions_db, '⚠️', '#ff4d4d'],
        ];
        foreach ($kpis as [$label, $value, $icon, $color]):
        ?>
        <div class="kpi-card" style="--accent:<?= $color ?>">
            <div class="kpi-icon"><?= $icon ?></div>
            <div class="kpi-value" data-target="<?= $value ?>">0</div>
            <div class="kpi-label"><?= htmlspecialchars($label) ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="stats-header">
        <h2 class="stats-subtitle">Personal Statistics</h2>
    </div>

    <div class="kpi-grid2">
        <div class="kpi-card" style="--accent:#f59e0b">
            <div class="kpi-icon">👤</div>
            <div class="kpi-value" data-target="<?= $total_user_entries ?>">0</div>
            <div class="kpi-label">Total Entries</div>
        </div>
    </div>

    <div class="charts-grid">
        <div class="chart-card">
            <h3 class="chart-title">Entries — Last 7 Days</h3>
            <canvas id="searchChart" height="200"></canvas>
        </div>
        <div class="chart-card">
            <h3 class="chart-title">Top Used Drugs</h3>
            <canvas id="drugsChart" height="200"></canvas>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
document.querySelectorAll('.kpi-value').forEach(el => {
    const target = parseInt(el.dataset.target) || 0;
    const duration = 1200;
    const start = performance.now();
    function step(now) {
        const progress = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        el.textContent = Math.floor(eased * target).toLocaleString();
        if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
});

Chart.defaults.color = '#666';
const NEON = '#03e9f4';
const GRID = 'rgba(255,255,255,0.05)';

new Chart(document.getElementById('searchChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [{ data: <?= json_encode($data) ?>, borderColor: NEON, backgroundColor: 'rgba(3,233,244,0.08)', tension: 0.4, fill: true }]
    },
    options: { plugins: { legend: { display: false } }, scales: { x: { grid: { color: GRID } }, y: { grid: { color: GRID }, beginAtZero: true } } }
});

new Chart(document.getElementById('drugsChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($drug_labels) ?>,
        datasets: [{ data: <?= json_encode($drug_data) ?>, backgroundColor: 'rgba(3,233,244,0.7)', borderRadius: 6 }]
    },
    options: { indexAxis: 'y', plugins: { legend: { display: false } } }
});
</script>

<?php render_foot(); ?>

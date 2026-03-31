<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/foot.php';

require_login();

$drug_filter       = trim($_GET['drug'] ?? '');
$disease_filter    = trim($_GET['disease'] ?? '');
$side_effect_filter = trim($_GET['side_effect'] ?? '');

// All entries for user
$all_stmt = $pdo->prepare(
    'SELECT e.*, d.Name AS drug_name, dis.Name AS disease_name, se.Name AS side_effect_name
     FROM Entries e
     LEFT JOIN Drugs d ON d.idDrug = e.Drugs_idDrug
     LEFT JOIN Diseases dis ON dis.idDisease = e.Diseases_idDisease
     LEFT JOIN SideEffects se ON se.idSideEffect = e.SideEffects_idSideEffect
     WHERE e.Users_idUsers = ?
     ORDER BY e.idEntry DESC'
);
$all_stmt->execute([current_user_id()]);
$all_entries = $all_stmt->fetchAll();

// Apply filters in PHP
$filtered_entries = array_filter($all_entries, function ($e) use ($drug_filter, $disease_filter, $side_effect_filter) {
    if ($drug_filter && stripos($e['drug_name'] ?? '', $drug_filter) === false) return false;
    if ($disease_filter && stripos($e['disease_name'] ?? '', $disease_filter) === false) return false;
    if ($side_effect_filter && stripos($e['side_effect_name'] ?? '', $side_effect_filter) === false) return false;
    return true;
});

$no_entries        = count($all_entries) === 0;
$no_filter_results = count($all_entries) > 0 && count($filtered_entries) === 0;

render_head('My Entries');
?>

<div class="dashboard-container">

    <div class="dashboard-header">
        <h1 class="dashboard-title">Welcome, <?= htmlspecialchars(current_user_email()) ?></h1>
        <p class="dashboard-subtitle">Manage and analyze your medication entries.</p>
    </div>

    <div class="dashboard-filters">
        <form method="get" action="">
            <input type="text" name="drug"         placeholder="Filter by Drug"        value="<?= htmlspecialchars($drug_filter) ?>">
            <input type="text" name="disease"      placeholder="Filter by Disease"     value="<?= htmlspecialchars($disease_filter) ?>">
            <input type="text" name="side_effect"  placeholder="Filter by Side Effect" value="<?= htmlspecialchars($side_effect_filter) ?>">
            <button type="submit" class="filter-btn">Apply Filters</button>
            <a href="<?= BASE_URL ?>/user.php" class="filter-btn reset-btn">Reset</a>
        </form>
    </div>

    <?php if (!empty($filtered_entries)): ?>
        <div class="dashboard-grid">
        <?php foreach ($filtered_entries as $entry): ?>
        <div class="entry-card" onclick="toggleActions(this)">
            <div class="entry-card-header">
                <h3 class="entry-drug"><?= htmlspecialchars($entry['drug_name'] ?? '-') ?></h3>
                <span class="entry-score">Score: <?= $entry['ImprovementScore'] ?? '-' ?></span>
            </div>
            <div class="entry-card-body">
                <p><strong>Disease:</strong> <?= htmlspecialchars($entry['disease_name'] ?? '-') ?></p>
                <p><strong>Side Effect:</strong> <?= htmlspecialchars($entry['side_effect_name'] ?? '-') ?></p>
                <p><strong>Age:</strong> <?= $entry['Age'] ?? '-' ?> years | <strong>Gender:</strong> <?= htmlspecialchars($entry['Gender'] ?? '-') ?></p>
                <p><strong>Dose:</strong> <?= $entry['Dose'] ?? '-' ?> mg/ml | <strong>Duration:</strong> <?= $entry['DurationDays'] ?? '-' ?> days</p>
            </div>
            <div class="entry-card-footer">
                <a href="<?= BASE_URL ?>/edit_entry.php?id=<?= $entry['idEntry'] ?>" class="entry-btn edit-btn">Edit</a>
                <form method="POST" action="<?= BASE_URL ?>/delete_entry.php"
                      onsubmit="return confirm('Are you sure you want to delete this entry?');" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="entry_id" value="<?= $entry['idEntry'] ?>">
                    <button type="submit" class="entry-btn delete-btn">Delete</button>
                </form>
            </div>
            <div class="entry-card-actions" style="display:none">
                <a href="<?= BASE_URL ?>/export_pdf.php?entry_id=<?= $entry['idEntry'] ?>" class="entry-btn download-btn" style="background:var(--teal-10);color:var(--teal);border:1px solid var(--b-t)">
                    Download PDF
                </a>
                <a href="<?= BASE_URL ?>/similar_analysis.php?age=<?= $entry['Age'] ?>&gender=<?= urlencode($entry['Gender'] ?? '') ?>&disease_id=<?= $entry['Diseases_idDisease'] ?>"
                   class="entry-btn" style="background:var(--emerald-10);color:var(--emerald);border:1px solid rgba(48,209,88,.22)">
                    Similar Analysis
                </a>
            </div>
        </div>
        <?php endforeach; ?>
        </div>

    <?php elseif ($no_filter_results): ?>
        <div class="dashboard-empty">
            <p>No entries match your filter.
                <?php if ($drug_filter): ?> Drug: "<?= htmlspecialchars($drug_filter) ?>"<?php endif; ?>
                <?php if ($disease_filter): ?> Disease: "<?= htmlspecialchars($disease_filter) ?>"<?php endif; ?>
                <?php if ($side_effect_filter): ?> Side Effect: "<?= htmlspecialchars($side_effect_filter) ?>"<?php endif; ?>
            </p>
            <p>Try adjusting your filters.</p>
        </div>

    <?php elseif ($no_entries): ?>
        <div class="dashboard-empty">
            <p>You haven't added any entries yet. Start by creating your first medication profile!</p>
        </div>
    <?php endif; ?>

    <div style="display:flex;justify-content:center;margin-top:30px">
        <a href="<?= BASE_URL ?>/entry.php" class="profile-card profile-new-entry-card">
            <div class="profile-card-content">
                <h3 style="color:var(--primary-neon)">+ Make a New Entry</h3>
                <p style="color:#aaa">Click here to add a new medication entry</p>
            </div>
        </a>
    </div>
</div>

<script>
function toggleActions(card) {
    var actions = card.querySelector('.entry-card-actions');
    if (!actions) return;
    actions.style.display = (actions.style.display === 'none' || actions.style.display === '') ? 'flex' : 'none';
}
</script>

<?php render_foot(); ?>

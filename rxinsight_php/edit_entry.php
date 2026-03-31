<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/foot.php';

require_login();

$entry_id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    'SELECT e.*, d.Name AS drug_name, dis.Name AS disease_name, se.Name AS side_effect_name
     FROM Entries e
     LEFT JOIN Drugs d ON d.idDrug = e.Drugs_idDrug
     LEFT JOIN Diseases dis ON dis.idDisease = e.Diseases_idDisease
     LEFT JOIN SideEffects se ON se.idSideEffect = e.SideEffects_idSideEffect
     WHERE e.idEntry = ?'
);
$stmt->execute([$entry_id]);
$entry = $stmt->fetch();

if (!$entry || (int)$entry['Users_idUsers'] !== current_user_id()) {
    flash('error', 'Entry not found or access denied.');
    header('Location: ' . BASE_URL . '/user.php');
    exit;
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $drug_name_raw = trim($_POST['drug_name'] ?? '');
    $age           = (int)($_POST['age'] ?? 0);
    $gender        = $_POST['gender'] ?? '';
    $condition     = trim($_POST['condition'] ?? '');
    $side_effects  = trim($_POST['side_effects'] ?? '');
    $dose          = $_POST['dose'] !== '' ? (float)$_POST['dose'] : null;
    $duration      = $_POST['duration'] !== '' ? (int)$_POST['duration'] : null;
    $score         = $_POST['treatment_score'] !== '' ? (float)$_POST['treatment_score'] : null;

    $drug_names  = array_filter(array_map('trim', explode(',', $drug_name_raw)));
    $found_drug  = null;
    foreach ($drug_names as $name) {
        $s = $pdo->prepare('SELECT * FROM Drugs WHERE LOWER(Name) LIKE ? LIMIT 1');
        $s->execute(['%' . strtolower($name) . '%']);
        $found_drug = $s->fetch();
        if ($found_drug) break;
    }

    $stmt2 = $pdo->prepare('SELECT * FROM Diseases WHERE LOWER(Name) LIKE ? LIMIT 1');
    $stmt2->execute(['%' . strtolower($condition) . '%']);
    $disease = $stmt2->fetch();

    $stmt3 = $pdo->prepare('SELECT * FROM SideEffects WHERE LOWER(Name) LIKE ? LIMIT 1');
    $stmt3->execute(['%' . strtolower($side_effects) . '%']);
    $side_effect = $stmt3->fetch();

    if (!$found_drug || !$disease || !$side_effect) {
        $error_message = 'Could not find matching drug, disease, or side effect.';
    } else {
        $upd = $pdo->prepare(
            'UPDATE Entries SET Age=?, Gender=?, Dose=?, DurationDays=?, ImprovementScore=?,
             Drugs_idDrug=?, Diseases_idDisease=?, SideEffects_idSideEffect=?
             WHERE idEntry=?'
        );
        $upd->execute([
            $age, $gender, $dose, $duration, $score,
            $found_drug['idDrug'], $disease['idDisease'], $side_effect['idSideEffect'],
            $entry_id
        ]);
        flash('success', 'Entry modified successfully.');
        header('Location: ' . BASE_URL . '/user.php');
        exit;
    }
}

render_head('Edit Entry');
?>

<div class="interaction-container">

    <?php if ($error_message): ?>
        <div class="interaction-empty" style="margin-bottom:20px">⚠️ <?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <div class="interaction-header">
        <h1 class="interaction-title">Edit Entry</h1>
    </div>

    <hr class="interaction-divider">

    <form method="post" action="">
        <?= csrf_field() ?>

        <div class="interaction-row">
            <div class="interaction-col">
                <label class="interaction-label">Age</label>
                <input type="number" name="age" class="interaction-input"
                       value="<?= htmlspecialchars($_POST['age'] ?? $entry['Age']) ?>" min="0" max="120" required>
            </div>
            <div class="interaction-col">
                <label class="interaction-label">Gender</label>
                <select name="gender" class="interaction-input interaction-select">
                    <?php foreach (['not_specified' => 'Not specified', 'male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $v => $l): ?>
                        <?php $cur = $_POST['gender'] ?? $entry['Gender']; ?>
                        <option value="<?= $v ?>" <?= ($cur === $v) ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="interaction-field">
            <label class="interaction-label">Medical Condition</label>
            <input type="text" name="condition" class="interaction-input"
                   value="<?= htmlspecialchars($_POST['condition'] ?? $entry['disease_name']) ?>" required>
        </div>

        <div class="interaction-field">
            <label class="interaction-label">Medication</label>
            <input type="text" name="drug_name" class="interaction-input interaction-drug-input"
                   value="<?= htmlspecialchars($_POST['drug_name'] ?? $entry['drug_name']) ?>" required>
        </div>

        <div class="interaction-field">
            <label class="interaction-label">Side Effects Experienced</label>
            <input type="text" name="side_effects" class="interaction-input"
                   value="<?= htmlspecialchars($_POST['side_effects'] ?? $entry['side_effect_name']) ?>" required>
        </div>

        <div class="interaction-row">
            <div class="interaction-col">
                <label class="interaction-label">Dose in mg/ml (optional)</label>
                <input type="number" step="0.01" name="dose" class="interaction-input"
                       value="<?= htmlspecialchars($_POST['dose'] ?? $entry['Dose']) ?>">
            </div>
            <div class="interaction-col">
                <label class="interaction-label">Duration days (optional)</label>
                <input type="number" name="duration" class="interaction-input"
                       value="<?= htmlspecialchars($_POST['duration'] ?? $entry['DurationDays']) ?>">
            </div>
            <div class="interaction-col">
                <label class="interaction-label">Treatment Score (optional)</label>
                <input type="number" step="0.1" min="1" max="10" name="treatment_score"
                       class="interaction-input"
                       value="<?= htmlspecialchars($_POST['treatment_score'] ?? $entry['ImprovementScore']) ?>">
            </div>
        </div>

        <div class="interaction-submit-wrapper">
            <button type="submit" class="interaction-submit">Save Changes</button>
        </div>
    </form>
</div>

<?php render_foot(); ?>

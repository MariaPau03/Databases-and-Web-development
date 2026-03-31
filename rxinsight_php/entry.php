<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/foot.php';

require_login();

$success_message = '';
$error_message   = '';
$show_analysis   = false;
$last_entry_id   = null;
$last_entry      = null;

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

    if ($gender === 'not_specified') {
        $error_message = 'Please select a gender';
    } else {
        $drug_names  = array_filter(array_map('trim', explode(',', $drug_name_raw)));
        $found_drugs = [];
        foreach ($drug_names as $name) {
            $stmt = $pdo->prepare('SELECT * FROM Drugs WHERE LOWER(Name) LIKE ? LIMIT 1');
            $stmt->execute(['%' . strtolower($name) . '%']);
            $drug = $stmt->fetch();
            if ($drug) $found_drugs[] = $drug;
        }

        if (empty($found_drugs)) {
            $error_message = "No medication found with name: $drug_name_raw";
        } else {
            $stmt = $pdo->prepare('SELECT * FROM Diseases WHERE LOWER(Name) LIKE ? LIMIT 1');
            $stmt->execute(['%' . strtolower($condition) . '%']);
            $disease = $stmt->fetch();

            if (!$disease) {
                $error_message = "No disease found: $condition";
            } else {
                $stmt = $pdo->prepare('SELECT * FROM SideEffects WHERE LOWER(Name) LIKE ? LIMIT 1');
                $stmt->execute(['%' . strtolower($side_effects) . '%']);
                $side_effect = $stmt->fetch();

                if (!$side_effect) {
                    $error_message = "No side effect found: $side_effects";
                } else {
                    $ins = $pdo->prepare(
                        'INSERT INTO Entries 
                         (Age, Gender, Dose, DurationDays, ImprovementScore,
                          Drugs_idDrug, Diseases_idDisease, SideEffects_idSideEffect, Users_idUsers)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    foreach ($found_drugs as $drug) {
                        $ins->execute([
                            $age, $gender, $dose, $duration, $score,
                            $drug['idDrug'], $disease['idDisease'],
                            $side_effect['idSideEffect'], current_user_id()
                        ]);
                    }
                    $last_entry_id   = $pdo->lastInsertId();
                    $success_message = 'Profile saved successfully and analyzed.';
                    $show_analysis   = true;

                    // Fetch last entry for buttons
                    $stmt = $pdo->prepare('SELECT * FROM Entries WHERE idEntry = ?');
                    $stmt->execute([$last_entry_id]);
                    $last_entry = $stmt->fetch();
                }
            }
        }
    }
}

render_head('New Entry');
?>

<div class="interaction-container">

    <?php if ($success_message): ?>
        <div class="success-box" style="margin-bottom:20px">✅ <?= htmlspecialchars($success_message) ?></div>
    <?php elseif ($error_message): ?>
        <div class="interaction-empty" style="margin-bottom:20px">⚠️ <?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <div class="interaction-header">
        <h1 class="interaction-title">Patient Medication Profile</h1>
        <p class="interaction-subtitle">Enter your information to analyze medication safety and store your profile.</p>
    </div>

    <hr class="interaction-divider">

    <form method="post" action="">
        <?= csrf_field() ?>

        <div class="interaction-row">
            <div class="interaction-col">
                <label class="interaction-label">Age</label>
                <input type="number" name="age" class="interaction-input" placeholder="e.g. 45"
                       value="<?= htmlspecialchars($_POST['age'] ?? '') ?>" min="0" max="120" required>
            </div>
            <div class="interaction-col">
                <label class="interaction-label">Gender</label>
                <select name="gender" class="interaction-input interaction-select">
                    <?php foreach (['not_specified' => 'Not specified', 'male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $v => $l): ?>
                        <option value="<?= $v ?>" <?= (($_POST['gender'] ?? '') === $v) ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="interaction-field" style="position:relative">
            <label class="interaction-label">Medical Condition</label>
            <input type="text" name="condition" id="condition_input" class="interaction-input"
                   placeholder="e.g. Diabetes, Asthma" autocomplete="off"
                   value="<?= htmlspecialchars($_POST['condition'] ?? '') ?>" required>
            <ul id="condition-ac-list" class="autocomplete-list"></ul>
        </div>

        <div class="interaction-field" style="position:relative">
            <label class="interaction-label">Medication</label>
            <input type="text" name="drug_name" id="drug_name_input"
                   class="interaction-input interaction-drug-input"
                   placeholder="e.g. Aspirin, Metformin, Ibuprofen" autocomplete="off"
                   value="<?= htmlspecialchars($_POST['drug_name'] ?? '') ?>" required>
            <ul id="drug-ac-list" class="autocomplete-list"></ul>
        </div>

        <div class="interaction-field" style="position:relative">
            <label class="interaction-label">Side Effects Experienced</label>
            <input type="text" name="side_effects" id="side_effects_input" class="interaction-input"
                   placeholder="e.g. Nausea, Headache" autocomplete="off"
                   value="<?= htmlspecialchars($_POST['side_effects'] ?? '') ?>" required>
            <ul id="se-ac-list" class="autocomplete-list"></ul>
        </div>

        <div class="interaction-row">
            <div class="interaction-col">
                <label class="interaction-label">Dose in mg/ml (optional)</label>
                <input type="number" step="0.01" name="dose" class="interaction-input"
                       placeholder="e.g. 20" value="<?= htmlspecialchars($_POST['dose'] ?? '') ?>">
            </div>
            <div class="interaction-col">
                <label class="interaction-label">Duration days (optional)</label>
                <input type="number" name="duration" class="interaction-input"
                       placeholder="e.g. 7" value="<?= htmlspecialchars($_POST['duration'] ?? '') ?>">
            </div>
            <div class="interaction-col">
                <label class="interaction-label">Treatment Score (optional)</label>
                <input type="number" step="0.1" min="1" max="10" name="treatment_score"
                       class="interaction-input" placeholder="e.g. 8.5"
                       value="<?= htmlspecialchars($_POST['treatment_score'] ?? '') ?>">
            </div>
        </div>

        <div class="interaction-submit-wrapper">
            <button type="submit" class="interaction-submit">Analyze &amp; Save Profile</button>
        </div>
    </form>

    <hr class="interaction-divider-large">

    <?php if ($show_analysis && $last_entry): ?>
    <div class="analysis-actions" style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap;margin-top:20px">
        <a href="<?= BASE_URL ?>/similar_analysis.php?age=<?= $last_entry['Age'] ?>&gender=<?= urlencode($last_entry['Gender']) ?>&disease_id=<?= $last_entry['Diseases_idDisease'] ?>"
           class="analysis-button" style="padding:12px 28px;background:linear-gradient(135deg,var(--teal),var(--teal-bright));color:var(--ink-0);border-radius:var(--r99);font-weight:700;font-size:.76rem;letter-spacing:2px;text-transform:uppercase;text-decoration:none">
            View Similar Profiles
        </a>
        <a href="<?= BASE_URL ?>/export_pdf.php?entry_id=<?= $last_entry['idEntry'] ?>"
           style="padding:12px 28px;background:var(--surface-2);border:1px solid var(--b-t);color:var(--teal);border-radius:var(--r99);font-weight:700;font-size:.76rem;letter-spacing:2px;text-transform:uppercase;text-decoration:none">
            Download My Report (PDF)
        </a>
    </div>
    <?php endif; ?>

</div>

<script>
function makeAutocomplete(inputId, listId, apiUrl, multiValue) {
    var input = document.getElementById(inputId);
    var list  = document.getElementById(listId);
    if (!input || !list) return;
    var timer;

    input.addEventListener('input', function () {
        clearTimeout(timer);
        var raw   = this.value;
        var query = multiValue ? raw.split(/[,]+/).pop().trim() : raw.trim();
        if (query.length < 2) { list.style.display = 'none'; return; }

        timer = setTimeout(function () {
            fetch(apiUrl + encodeURIComponent(query))
                .then(r => r.json())
                .then(suggestions => {
                    list.innerHTML = '';
                    if (!suggestions.length) { list.style.display = 'none'; return; }
                    suggestions.forEach(name => {
                        var li = document.createElement('li');
                        li.textContent = name;
                        li.addEventListener('mousedown', e => {
                            e.preventDefault();
                            if (multiValue) {
                                var parts = input.value.split(/,/);
                                parts[parts.length - 1] = ' ' + name;
                                input.value = parts.join(',').replace(/^,\s*/, '') + ', ';
                            } else {
                                input.value = name;
                            }
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
}

document.addEventListener('DOMContentLoaded', function () {
    makeAutocomplete('condition_input',    'condition-ac-list', '<?= BASE_URL ?>/api/conditions.php?q=',   false);
    makeAutocomplete('drug_name_input',    'drug-ac-list',      '<?= BASE_URL ?>/api/drugs.php?q=',        true);
    makeAutocomplete('side_effects_input', 'se-ac-list',        '<?= BASE_URL ?>/api/sideeffects.php?q=', false);
});
</script>

<?php render_foot(); ?>

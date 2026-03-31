<?php
// includes/head.php — call render_head($title) at the top of every page
function render_head(string $title = 'RXInsight'): void {
    $base   = BASE_URL;
    $logged = is_logged_in();
    $email  = current_user_email();
    $flashed = get_flashed_messages();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> - RXInsight</title>
    <link rel="stylesheet" href="<?= $base ?>/css/style.css">
</head>
<body>

<nav>
    <a href="<?= $base ?>/index.php" class="nav-brand">RXInsight</a>

    <div class="nav-right">
        <a href="<?= $base ?>/index.php">Home</a>

        <div class="dropdown">
            <button class="dropbtn">Public Tools &#9662;</button>
            <div class="dropdown-content">
                <a href="<?= $base ?>/interactions.php">Interaction Tool</a>
                <a href="<?= $base ?>/conditions.php">Find Drugs by Disease</a>
                <a href="<?= $base ?>/side_effects.php">Find Side Effects by Drug</a>
            </div>
        </div>

        <?php if ($logged): ?>
            <a href="<?= $base ?>/entry.php">New Entry</a>
            <span class="nav-separator">|</span>
            <a href="<?= $base ?>/stats.php">Statistics</a>
            <a href="<?= $base ?>/user.php">Entries</a>
            <a href="<?= $base ?>/logout.php" class="logout-btn">Logout</a>
        <?php else: ?>
            <a href="<?= $base ?>/login.php">Login</a>
            <a href="<?= $base ?>/register.php" class="register-btn">Register</a>
        <?php endif; ?>
    </div>
</nav>

<div class="container">

<?php if (!empty($flashed)): ?>
    <?php foreach ($flashed as $f): ?>
        <?php if ($f['category'] === 'success'): ?>
            <div class="success-box" style="margin-bottom:20px">✅ <?= htmlspecialchars($f['message']) ?></div>
        <?php elseif ($f['category'] === 'error'): ?>
            <div class="interaction-empty" style="margin-bottom:20px">⚠️ <?= htmlspecialchars($f['message']) ?></div>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>

<?php
} // end render_head

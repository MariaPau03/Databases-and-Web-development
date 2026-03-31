<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/foot.php';

render_head('Menu');
?>

<div class="menu-hero">
    <h1 class="menu-title">RXInsight Menu</h1>
    <p class="menu-subtitle">Select a clinical tool to begin analysis</p>

    <div class="tool-grid">
        <a href="<?= BASE_URL ?>/interactions.php" class="tool-btn">🔬 Interaction Tool</a>
        <a href="<?= BASE_URL ?>/side_effects.php" class="tool-btn">💊 Side Effect Explorer</a>
        <a href="<?= BASE_URL ?>/conditions.php"   class="tool-btn">🩺 Conditions and Medications</a>
        <a href="<?= BASE_URL ?>/entry.php"        class="tool-btn">🧠 Personal Analysis</a>
    </div>
</div>

<?php render_foot(); ?>

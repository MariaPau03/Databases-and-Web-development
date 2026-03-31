<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/foot.php';

render_head('Home');
?>

<div class="home-hero">
    <h1 class="home-title">Welcome to RXInsight</h1>
    <p class="home-subtitle">
        Your professional tool for checking drug interactions and side effects safely.
    </p>
    <a href="<?= BASE_URL ?>/tool_selection.php" class="home-button">Start Search Tool</a>
</div>

<?php render_foot(); ?>

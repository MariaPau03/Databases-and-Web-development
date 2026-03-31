<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/foot.php';

if (is_logged_in()) {
    header('Location: ' . BASE_URL . '/tool_selection.php');
    exit;
}

$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $repeat   = $_POST['password_repeat'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $repeat) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        // Check if email already exists
        $stmt = $pdo->prepare('SELECT idUsers FROM Users WHERE Email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'Please use a different email address.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins  = $pdo->prepare('INSERT INTO Users (Email, Password) VALUES (?, ?)');
            $ins->execute([$email, $hash]);
            flash('success', 'Registered successfully! You can now log in.');
            header('Location: ' . BASE_URL . '/login.php');
            exit;
        }
    }
}

render_head('Register');
?>

<div class="login-container">
    <h2 class="login-title">Register</h2>

    <?php foreach ($errors as $e): ?>
        <div class="login-error-center"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <form method="post" action="">
        <?= csrf_field() ?>

        <div class="login-group">
            <label class="login-label">Email Address</label>
            <input type="email" name="email" class="login-input" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>

        <div class="login-group">
            <label class="login-label">Password</label>
            <input type="password" name="password" class="login-input" required>
        </div>

        <div class="login-group">
            <label class="login-label">Repeat Password</label>
            <input type="password" name="password_repeat" class="login-input" required>
        </div>

        <button type="submit" class="login-button">Register</button>
    </form>

    <p class="login-footer">
        <a href="<?= BASE_URL ?>/login.php" class="login-link-white">Already have an account? Login</a>
    </p>
</div>

<?php render_foot(); ?>

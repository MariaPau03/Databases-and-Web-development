<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/foot.php';

// Redirect if already logged in
if (is_logged_in()) {
    header('Location: ' . BASE_URL . '/tool_selection.php');
    exit;
}

$login_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT idUsers, Email, Password FROM Users WHERE Email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['Password'])) {
        $_SESSION['user_id']    = $user['idUsers'];
        $_SESSION['user_email'] = $user['Email'];
        $next = $_GET['next'] ?? (BASE_URL . '/tool_selection.php');
        header('Location: ' . $next);
        exit;
    } else {
        $login_error = 'Invalid email or password';
    }
}

render_head('Login');
?>

<?php if ($login_error): ?>
    <div class="interaction-empty" style="margin-bottom:20px"><?= htmlspecialchars($login_error) ?></div>
<?php endif; ?>

<div class="login-container">
    <h2 class="login-title">Login</h2>

    <form method="post" action="">
        <?= csrf_field() ?>

        <div class="login-group">
            <label class="login-label">Email</label>
            <input type="email" name="email" class="login-input" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>

        <div class="login-group">
            <label class="login-label">Password</label>
            <input type="password" name="password" class="login-input" required>
        </div>

        <div class="remember-group">
            <input type="checkbox" name="remember_me" id="remember_me" class="remember-checkbox">
            <label for="remember_me" class="remember-label">Remember Me</label>
        </div>

        <button type="submit" class="login-button">Sign In</button>
    </form>

    <p class="login-footer">
        <span class="login-footer-text">New user?</span>
        <a href="<?= BASE_URL ?>/register.php" class="login-link">Create Account</a>
    </p>
</div>

<?php render_foot(); ?>

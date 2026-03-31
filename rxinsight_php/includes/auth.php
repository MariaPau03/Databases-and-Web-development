<?php
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function require_login(): void {
    if (!is_logged_in()) {
        $next = urlencode($_SERVER['REQUEST_URI']);
        header('Location: ' . BASE_URL . '/login.php?next=' . $next);
        exit;
    }
}

function current_user_id(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function current_user_email(): ?string {
    return $_SESSION['user_email'] ?? null;
}

// ── CSRF ──────────────────────────────────────────────────────
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function verify_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!hash_equals(csrf_token(), $token)) {
            http_response_code(403);
            die('Invalid CSRF token. Please go back and try again.');
        }
    }
}

// ── Flash messages ────────────────────────────────────────────
function flash(string $category, string $message): void {
    $_SESSION['flash'][] = ['category' => $category, 'message' => $message];
}

function get_flashed_messages(): array {
    $msgs = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $msgs;
}

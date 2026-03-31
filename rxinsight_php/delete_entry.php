<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

require_login();
verify_csrf();

$entry_id = (int)($_POST['entry_id'] ?? 0);
$stmt = $pdo->prepare('SELECT Users_idUsers FROM Entries WHERE idEntry = ?');
$stmt->execute([$entry_id]);
$entry = $stmt->fetch();

if (!$entry || (int)$entry['Users_idUsers'] !== current_user_id()) {
    flash('error', 'Entry not found or access denied.');
} else {
    $pdo->prepare('DELETE FROM Entries WHERE idEntry = ?')->execute([$entry_id]);
    flash('success', 'Entry deleted successfully.');
}

header('Location: ' . BASE_URL . '/user.php');
exit;

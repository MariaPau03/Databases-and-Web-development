<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) { echo '[]'; exit; }

$stmt = $pdo->prepare('SELECT Name FROM Drugs WHERE Name LIKE ? ORDER BY Name LIMIT 8');
$stmt->execute([$q . '%']);
$results = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo json_encode(array_values($results));

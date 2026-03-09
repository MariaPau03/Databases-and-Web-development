<?php
/**
 * download.php — serves alignment result files securely.
 * Usage: download.php?file=result_a3f2c1d0.aln
 */

$RESULTS_DIR = __DIR__ . '/results/';

$filename = $_GET['file'] ?? '';

// Strict validation: only allow filenames matching our pattern
if (!preg_match('/^result_[a-f0-9]{8}\.\w+$/', $filename)) {
    http_response_code(403);
    exit("Forbidden.");
}

$filepath = $RESULTS_DIR . $filename;

if (!file_exists($filepath)) {
    http_response_code(404);
    exit("File not found.");
}

// Serve the file as a download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache');
readfile($filepath);
exit;

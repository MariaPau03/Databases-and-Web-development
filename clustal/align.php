<?php
/**
 * ClustalOmega Web Application — PHP Backend
 * align.php: handles POST requests, returns JSON
 */

header('Content-Type: application/json');

// ─── Configuration ────────────────────────────────────────────────────────────

// Path to clustalo executable. Override via environment or edit here.
$CLUSTALO_PATH = getenv('CLUSTALO_PATH') ?: 'clustalo';

$UPLOAD_DIR  = __DIR__ . '/uploads/';
$RESULTS_DIR = __DIR__ . '/results/';

// Ensure directories exist and are writable
foreach ([$UPLOAD_DIR, $RESULTS_DIR] as $dir) {
    if (!is_dir($dir)) mkdir($dir, 0755, true);
}

// Valid output formats → file extensions
$OUTPUT_FORMATS = [
    'clustal'   => 'aln',
    'fasta'     => 'fasta',
    'msf'       => 'msf',
    'phylip'    => 'phy',
    'selex'     => 'slx',
    'stockholm' => 'sto',
    'vienna'    => 'vienna',
];

// clustalo --seqtype values
$SEQUENCE_TYPES = [
    'protein' => 'Protein',
    'dna'     => 'DNA',
    'rna'     => 'RNA',
];

// IUPAC valid characters per sequence type (used in regex)
$SEQ_CHARS = [
    'protein' => '/[^ACDEFGHIKLMNPQRSTVWYXBZUJacdefghiklmnpqrstvwyxbzuj*\-]/',
    'dna'     => '/[^ACGTURYSWKMBDHVNacgturyswkmbdhvn\-]/',
    'rna'     => '/[^ACGURYSWKMBDHVNacguryswkmbdhvn\-]/',
];

// ─── Helpers ──────────────────────────────────────────────────────────────────

function fail(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

function success(array $data): void {
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

/**
 * Fetch a URL with cURL and return (body, error).
 */
function http_get(string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'ClustalOmegaWebApp/1.0',
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body    = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err     = curl_error($ch);
    curl_close($ch);

    if ($err) return [null, "cURL error: $err"];
    if ($httpCode === 404) return [null, "Not found (HTTP 404)."];
    if ($httpCode !== 200) return [null, "HTTP $httpCode returned."];
    if (empty($body))     return [null, "Empty response."];
    return [$body, null];
}

/**
 * Fetch a single UniProt accession as FASTA.
 */
function fetch_uniprot(string $uid): array {
    $uid = strtoupper(trim($uid));
    [$body, $err] = http_get("https://www.uniprot.org/uniprot/{$uid}.fasta");
    if ($err) return [null, "UniProt '{$uid}': $err"];
    if (!strpos(trim($body), '>') === 0) return [null, "UniProt '{$uid}': response is not FASTA."];
    return [$body, null];
}

/**
 * Fetch a single PDB entry as FASTA.
 */
function fetch_pdb(string $pid): array {
    $pid = strtoupper(trim($pid));
    [$body, $err] = http_get("https://www.rcsb.org/fasta/entry/{$pid}");
    if ($err) return [null, "PDB '{$pid}': $err"];
    if (!strpos(trim($body), '>') === 0) return [null, "PDB '{$pid}': response is not FASTA."];
    return [$body, null];
}

/**
 * Fetch multiple IDs (uniprot or pdb). Returns [combined_fasta, warnings[]].
 */
function fetch_ids(array $ids, string $type): array {
    $combined = '';
    $errors   = [];
    $fetched  = 0;

    foreach ($ids as $id) {
        $id = trim($id);
        if ($id === '') continue;
        [$fasta, $err] = ($type === 'uniprot') ? fetch_uniprot($id) : fetch_pdb($id);
        if ($err) {
            $errors[] = $err;
        } else {
            $combined .= trim($fasta) . "\n";
            $fetched++;
        }
    }

    if ($fetched === 0) {
        fail("Failed to fetch any sequences:\n" . implode("\n", $errors));
    }
    if ($fetched < 2) {
        $msg = "Only $fetched sequence(s) fetched successfully. Need at least 2.";
        if ($errors) $msg .= "\nErrors:\n" . implode("\n", $errors);
        fail($msg);
    }

    return [$combined, $errors]; // errors become warnings
}

/**
 * Validate FASTA text for the given sequence type.
 * Returns ['sequences' => [...], 'error' => null] or ['sequences' => null, 'error' => '...'].
 */
function validate_fasta(string $text, string $seq_type): array {
    global $SEQ_CHARS;
    $bad_re    = $SEQ_CHARS[$seq_type] ?? $SEQ_CHARS['protein'];
    $type_label = strtoupper($seq_type);
    $sequences  = [];
    $current_id = null;
    $current_seq = [];

    foreach (explode("\n", $text) as $raw_line) {
        $line = trim($raw_line);
        if ($line === '') continue;

        if (strpos($line, '>') === 0) {
            // Save previous sequence
            if ($current_id !== null) {
                $seq = implode('', $current_seq);
                if ($seq === '') return ['sequences' => null, 'error' => "Sequence '$current_id' has no residues."];
                $sequences[$current_id] = $seq;
            }
            $current_id = trim(substr($line, 1));
            if ($current_id === '') return ['sequences' => null, 'error' => "Found a '>' header with no sequence ID."];
            $current_seq = [];
        } else {
            if ($current_id === null) {
                return ['sequences' => null, 'error' => "Sequence data found before any FASTA header ('>...')."];
            }
            $cleaned = preg_replace('/\s/', '', $line);
            // Find invalid characters using the pattern directly
            preg_match_all($bad_re, $cleaned, $bad_matches);
            if (!empty($bad_matches[0])) {
                $bad_sample = implode('', array_unique($bad_matches[0]));
                $bad_sample = substr($bad_sample, 0, 10);
                return [
                    'sequences' => null,
                    'error'     => "Invalid $type_label characters in sequence '$current_id': '$bad_sample'. Check sequence type selection.",
                ];
            }
            $current_seq[] = $cleaned;
        }
    }

    // Save last sequence
    if ($current_id !== null) {
        $seq = implode('', $current_seq);
        if ($seq === '') return ['sequences' => null, 'error' => "Sequence '$current_id' has no residues."];
        $sequences[$current_id] = $seq;
    }

    if (count($sequences) < 2) {
        return ['sequences' => null, 'error' => 'At least 2 sequences are required. Found: ' . count($sequences) . '.'];
    }

    return ['sequences' => $sequences, 'error' => null];
}

/**
 * Run clustalo and return [output_text, output_path, error].
 */
function run_clustalo(string $fasta_text, string $out_format, string $seq_type,
                      string $extra_opts, int $iterations): array {
    global $CLUSTALO_PATH, $UPLOAD_DIR, $RESULTS_DIR, $OUTPUT_FORMATS, $SEQUENCE_TYPES;

    $job_id     = bin2hex(random_bytes(4));   // 8 hex chars
    $ext        = $OUTPUT_FORMATS[$out_format] ?? 'aln';
    $input_path  = $UPLOAD_DIR  . "input_{$job_id}.fasta";
    $output_path = $RESULTS_DIR . "result_{$job_id}.{$ext}";

    file_put_contents($input_path, $fasta_text);

    $seqtype_arg = $SEQUENCE_TYPES[$seq_type] ?? 'Protein';

    // Build command as array — shell_exec with escapeshellarg for safety
    $cmd_parts = [
        escapeshellcmd($CLUSTALO_PATH),
        '-i', escapeshellarg($input_path),
        '-o', escapeshellarg($output_path),
        '--outfmt', escapeshellarg($out_format),
        '--seqtype', escapeshellarg($seqtype_arg),
        '--force',
    ];

    if ($iterations > 0) {
        $cmd_parts[] = '--iter';
        $cmd_parts[] = (int)$iterations;
    }

    // Validate and append extra options
    if (trim($extra_opts) !== '') {
        // Block shell metacharacters
        if (preg_match('/[;&|`$<>]/', $extra_opts)) {
            @unlink($input_path);
            return [null, null, "Extra options contain unsafe characters."];
        }
        // Split on whitespace (basic shlex-like split, no quotes parsing needed for simple flags)
        $extra_parts = preg_split('/\s+/', trim($extra_opts));
        foreach ($extra_parts as $part) {
            $cmd_parts[] = escapeshellarg($part);
        }
    }

    $cmd    = implode(' ', $cmd_parts) . ' 2>&1';
    $output = [];
    $retval = 0;
    exec($cmd, $output, $retval);

    // Clean up input
    @unlink($input_path);

    if ($retval !== 0) {
        $err_msg = implode("\n", $output);
        return [null, null, "ClustalOmega error (code $retval):\n$err_msg"];
    }

    if (!file_exists($output_path)) {
        return [null, null, "ClustalOmega ran but produced no output file."];
    }

    $result_text = file_get_contents($output_path);
    return [$result_text, $output_path, null];
}

// ─── Main request handler ─────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail("Only POST requests are accepted.", 405);
}

$input_mode = $_POST['input_mode'] ?? '';
$out_format = $_POST['out_format'] ?? 'clustal';
$seq_type   = strtolower($_POST['seq_type'] ?? 'protein');
$extra_opts = $_POST['extra_opts'] ?? '';
$iterations = max(0, min(5, (int)($_POST['iterations'] ?? 0)));
$warnings   = [];

// Validate options
if (!array_key_exists($out_format, $OUTPUT_FORMATS)) {
    fail("Unknown output format: '$out_format'.");
}
if (!array_key_exists($seq_type, $SEQUENCE_TYPES)) {
    fail("Unknown sequence type: '$seq_type'. Choose protein, dna, or rna.");
}

$fasta_text = '';
$input_type = '';

// ── Route by input mode ────────────────────────────────────────────────────

if ($input_mode === 'file') {
    // ── File upload ──
    if (!isset($_FILES['fasta_file']) || $_FILES['fasta_file']['error'] === UPLOAD_ERR_NO_FILE) {
        fail("No file was uploaded.");
    }
    $file = $_FILES['fasta_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        fail("File upload error code: " . $file['error']);
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['fasta', 'fa', 'fas', 'txt', 'seq'];
    if (!in_array($ext, $allowed)) {
        fail("File type '.$ext' not allowed. Use FASTA format (.fasta, .fa, .fas, .txt).");
    }
    $fasta_text = file_get_contents($file['tmp_name']);
    if ($fasta_text === false) fail("Could not read uploaded file.");
    $input_type = 'fasta';

} elseif ($input_mode === 'fasta') {
    // ── Direct FASTA paste ──
    $fasta_text = trim($_POST['sequences'] ?? '');
    if ($fasta_text === '') {
        fail("No FASTA sequences provided. Each sequence must start with a >header line.");
    }
    $input_type = 'fasta';

} elseif ($input_mode === 'uniprot' || $input_mode === 'pdb') {
    // ── ID-based: UniProt or PDB ──
    if ($seq_type === 'dna' || $seq_type === 'rna') {
        $warnings[] = "UniProt and PDB entries contain protein sequences. Sequence type overridden from '" . strtoupper($seq_type) . "' to 'Protein'.";
        $seq_type = 'protein';
    }

    $raw_ids = trim($_POST['sequences'] ?? '');
    if ($raw_ids === '') {
        $source = $input_mode === 'uniprot' ? 'UniProt' : 'PDB';
        fail("No $source IDs provided. Enter at least 2 IDs, one per line.");
    }

    // Split on whitespace, commas, or semicolons
    $ids = preg_split('/[\s,;]+/', $raw_ids, -1, PREG_SPLIT_NO_EMPTY);

    // Normalise PDB IDs: strip optional trailing chain letter (e.g. 1SBIA → 1SBI)
    if ($input_mode === 'pdb') {
        $ids = array_map(function($id) {
            $id = strtoupper(trim($id));
            if (preg_match('/^([0-9][A-Z0-9]{3})[A-Z]?$/', $id, $m)) {
                return $m[1];
            }
            return $id;
        }, $ids);
    }

    if (count($ids) < 2) {
        $source = $input_mode === 'uniprot' ? 'UniProt' : 'PDB';
        fail("Only " . count($ids) . " $source ID provided. At least 2 are required to run an alignment.");
    }

    [$fasta_text, $fetch_warnings] = fetch_ids($ids, $input_mode);
    if ($fetch_warnings) $warnings = array_merge($warnings, $fetch_warnings);
    $input_type = $input_mode;

} else {
    fail("Unknown input mode: '$input_mode'. Expected fasta, uniprot, pdb, or file.");
}

// ── Validate FASTA ─────────────────────────────────────────────────────────

$validation = validate_fasta($fasta_text, $seq_type);
if ($validation['error']) {
    fail("Sequence validation error: " . $validation['error']);
}
$sequences = $validation['sequences'];
$seq_count = count($sequences);

// ── Run ClustalOmega ───────────────────────────────────────────────────────

[$result_text, $result_path, $run_error] = run_clustalo(
    $fasta_text, $out_format, $seq_type, $extra_opts, $iterations
);

if ($run_error) {
    fail($run_error, 500);
}

// ── Build stats ────────────────────────────────────────────────────────────

$lengths = array_map('strlen', $sequences);
$format_labels = [
    'clustal'   => 'Clustal (.aln)',
    'fasta'     => 'FASTA (.fasta)',
    'msf'       => 'MSF (.msf)',
    'phylip'    => 'PHYLIP (.phy)',
    'selex'     => 'SELEX (.slx)',
    'stockholm' => 'Stockholm (.sto)',
    'vienna'    => 'Vienna (.vienna)',
];
$seq_type_labels = ['protein' => 'Protein', 'dna' => 'DNA', 'rna' => 'RNA'];

$stats = [
    'sequences'  => $seq_count,
    'min_length' => min($lengths),
    'max_length' => max($lengths),
    'avg_length' => (int)round(array_sum($lengths) / count($lengths)),
    'format'     => $format_labels[$out_format] ?? $out_format,
    'seq_type'   => $seq_type_labels[$seq_type] ?? ucfirst($seq_type),
    'result_file'=> basename($result_path),
];

success([
    'result'      => $result_text,
    'stats'       => $stats,
    'warnings'    => $warnings,
    'input_type'  => $input_type,
    'out_format'  => $out_format,
    'seq_type'    => $seq_type,
    'result_file' => basename($result_path),
]);

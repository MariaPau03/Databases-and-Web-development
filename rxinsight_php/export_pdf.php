<?php
/**
 * PDF Export — requires FPDF.
 * Download fpdf.php from http://www.fpdf.org/ and place it in the same folder as this file.
 * Then rename it to fpdf.php (it usually already is).
 */
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

// ── Check FPDF is available ────────────────────────────────────
$fpdf_path = __DIR__ . '/fpdf.php';
if (!file_exists($fpdf_path)) {
    die('<p style="font-family:monospace;color:red;padding:20px">
         FPDF library not found.<br>
         Download <b>fpdf.php</b> from <a href="http://www.fpdf.org">fpdf.org</a>
         and place it in the root folder of the project.
         </p>');
}
require_once $fpdf_path;

$entry_id = (int)($_GET['entry_id'] ?? 0);

// Fetch entry
$stmt = $pdo->prepare('SELECT * FROM Entries WHERE idEntry = ? AND Users_idUsers = ?');
$stmt->execute([$entry_id, current_user_id()]);
$entry = $stmt->fetch();
if (!$entry) {
    die('Entry not found or access denied.');
}

// Latest profile of user
$latest_stmt = $pdo->prepare('SELECT * FROM Entries WHERE Users_idUsers = ? ORDER BY idEntry DESC LIMIT 1');
$latest_stmt->execute([current_user_id()]);
$latest = $latest_stmt->fetch();

$age    = $latest['Age'];
$gender = $latest['Gender'];

$dis_stmt = $pdo->prepare('SELECT * FROM Diseases WHERE idDisease = ?');
$dis_stmt->execute([$latest['Diseases_idDisease']]);
$disease      = $dis_stmt->fetch();
$disease_name = $disease['Name'] ?? 'Unknown';

// Unique drugs for this profile
$entries_stmt = $pdo->prepare(
    'SELECT DISTINCT Drugs_idDrug FROM Entries
     WHERE Users_idUsers = ? AND Age = ? AND Gender = ? AND Diseases_idDisease = ?'
);
$entries_stmt->execute([current_user_id(), $age, $gender, $latest['Diseases_idDisease']]);
$drug_ids = $entries_stmt->fetchAll(PDO::FETCH_COLUMN);

$drugs = [];
if ($drug_ids) {
    $in   = implode(',', array_fill(0, count($drug_ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM Drugs WHERE idDrug IN ($in)");
    $stmt->execute($drug_ids);
    $drugs = $stmt->fetchAll();
}

// Interactions
$severity_order = ['Major' => 3, 'Moderate' => 2, 'Minor' => 1, 'Unknown' => 0];
$interactions_by_drug = [];

foreach ($drugs as $drug) {
    $istmt = $pdo->prepare(
        'SELECT di.Level AS severity, d1.Name AS drug1_name, d2.Name AS drug2_name
         FROM DrugInteractions di
         JOIN Drugs d1 ON d1.idDrug = di.Drugs_idDrugA
         JOIN Drugs d2 ON d2.idDrug = di.Drugs_idDrugB
         WHERE di.Drugs_idDrugA = ? OR di.Drugs_idDrugB = ?'
    );
    $istmt->execute([$drug['idDrug'], $drug['idDrug']]);
    $conflicts = $istmt->fetchAll();

    $seen  = [];
    $ilist = [];
    foreach ($conflicts as $c) {
        $d1  = ucwords(strtolower($c['drug1_name']));
        $d2  = ucwords(strtolower($c['drug2_name']));
        $sev = $c['severity'] ?: 'Unknown';
        $key = implode('|', [min($d1, $d2), max($d1, $d2)]);
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $ilist[] = ['drug1_name' => $d1, 'drug2_name' => $d2, 'severity' => $sev];
    }

    usort($ilist, fn($a, $b) => ($severity_order[$b['severity']] ?? 0) - ($severity_order[$a['severity']] ?? 0));
    $interactions_by_drug[ucwords(strtolower($drug['Name']))] = $ilist;
}

// ── Generate PDF ───────────────────────────────────────────────
$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 15);

// Colours
$teal    = [0, 232, 212];
$dark    = [10, 20, 30];
$grey    = [100, 100, 100];
$red     = [217, 83, 79];
$white   = [255, 255, 255];
$lightbg = [244, 247, 249];

// ── Header ─────────────────────────────────────────────────────
$pdf->SetFont('Helvetica', 'B', 18);
$pdf->SetTextColor(...$teal);
$pdf->Cell(0, 10, 'RXInsight - Patient Report', 0, 1, 'C');

$pdf->SetFont('Helvetica', '', 8);
$pdf->SetTextColor(...$grey);
$ts = date('Y-m-d H:i');
$pdf->Cell(0, 6, "Generated: $ts  |  " . current_user_email(), 0, 1, 'C');

$pdf->SetDrawColor(...$teal);
$pdf->SetLineWidth(0.5);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(4);

// ── Patient Profile ─────────────────────────────────────────────
$pdf->SetFont('Helvetica', 'B', 13);
$pdf->SetTextColor(...$teal);
$pdf->Cell(0, 8, 'Patient Profile', 0, 1);

$pdf->SetFont('Helvetica', '', 10);
$pdf->SetTextColor(0, 0, 0);
$drug_names_str = implode(', ', array_map(fn($d) => ucwords(strtolower($d['Name'])), $drugs));
foreach (["Age: $age", "Gender: $gender", "Condition: $disease_name", "Medications: $drug_names_str"] as $item) {
    $pdf->Cell(5, 7, chr(149), 0, 0);
    $pdf->Cell(0, 7, $item, 0, 1);
}

$pdf->SetDrawColor(200, 200, 200);
$pdf->SetLineWidth(0.3);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(4);

// ── Drug Interactions ──────────────────────────────────────────
$pdf->SetFont('Helvetica', 'B', 13);
$pdf->SetTextColor(...$teal);
$pdf->Cell(0, 8, 'Drug Interactions', 0, 1);

$has_any = array_filter($interactions_by_drug, fn($x) => !empty($x));

if (!$has_any) {
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 7, 'No known interactions for the selected medications.', 0, 1);
} else {
    foreach ($interactions_by_drug as $dname => $ilist) {
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->SetTextColor(10, 20, 30);
        $pdf->Cell(0, 7, $dname, 0, 1);

        if (empty($ilist)) {
            $pdf->SetFont('Helvetica', 'I', 9);
            $pdf->SetTextColor(...$grey);
            $pdf->Cell(0, 6, 'No known interactions.', 0, 1);
        } else {
            // Table header
            $pdf->SetFillColor(30, 40, 50);
            $pdf->SetTextColor(200, 200, 200);
            $pdf->SetFont('Helvetica', 'B', 8);
            $pdf->Cell(140, 7, 'Drug Pair', 1, 0, 'L', true);
            $pdf->Cell(40, 7, 'Severity', 1, 1, 'C', true);

            $sev_colors = [
                'Major'    => [255, 77, 77],
                'Moderate' => [255, 149, 0],
                'Minor'    => [100, 200, 100],
                'Unknown'  => [150, 150, 150],
            ];

            foreach ($ilist as $inter) {
                $pair = $inter['drug1_name'] . '  +  ' . $inter['drug2_name'];
                $sev  = $inter['severity'];
                $col  = $sev_colors[$sev] ?? $sev_colors['Unknown'];

                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetFont('Helvetica', '', 9);
                $pdf->SetFillColor($col[0], $col[1], $col[2]);

                // bg just on severity cell
                $pdf->Cell(140, 6, $pair, 1, 0, 'L', false);
                $pdf->Cell(40, 6, $sev, 1, 1, 'C', true);
            }
            $pdf->Ln(3);
        }
    }
}

$pdf->SetDrawColor(200, 200, 200);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(4);

// ── Side Effects ───────────────────────────────────────────────
$pdf->SetFont('Helvetica', 'B', 13);
$pdf->SetTextColor(...$teal);
$pdf->Cell(0, 8, 'Possible Side Effects', 0, 1);

foreach ($drugs as $drug) {
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetTextColor(10, 20, 30);
    $pdf->Cell(0, 7, ucwords(strtolower($drug['Name'])), 0, 1);

    $se_stmt = $pdo->prepare(
        'SELECT se.Name FROM SideEffects se
         JOIN Drugs_has_SideEffects dse ON dse.SideEffects_idSideEffect = se.idSideEffect
         WHERE dse.Drugs_idDrug = ? LIMIT 15'
    );
    $se_stmt->execute([$drug['idDrug']]);
    $effects = array_unique($se_stmt->fetchAll(PDO::FETCH_COLUMN));

    if ($effects) {
        $half = (int)ceil(count($effects) / 2);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetFillColor(...$lightbg);
        $pdf->SetTextColor(0, 0, 0);
        for ($i = 0; $i < $half; $i++) {
            $left  = $effects[$i] ?? '';
            $right = $effects[$i + $half] ?? '';
            $pdf->Cell(90, 5, $left, 0, 0, 'L', true);
            $pdf->Cell(90, 5, $right, 0, 1, 'L', true);
        }
    } else {
        $pdf->SetFont('Helvetica', 'I', 8);
        $pdf->SetTextColor(...$grey);
        $pdf->Cell(0, 6, 'No data available.', 0, 1);
    }
    $pdf->Ln(3);
}

// ── Footer ─────────────────────────────────────────────────────
$pdf->SetY(-20);
$pdf->SetFont('Helvetica', 'I', 8);
$pdf->SetTextColor(...$grey);
$pdf->Cell(0, 5, 'RXInsight Report - For informational purposes only. Not medical advice.', 0, 0, 'C');

$filename = 'RXInsight_Report_' . date('Ymd_Hi') . '.pdf';
$pdf->Output('D', $filename);

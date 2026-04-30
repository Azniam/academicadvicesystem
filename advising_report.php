<?php
require_once '../includes/auth.php';
requireRole('student');

$user = getUserData($_SESSION['user_id']);

// Get current school year and semester
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$currentSchoolYear = $settings['current_school_year'] ?? '2024-2025';
$currentSemester = $settings['current_semester'] ?? '1';

// Get PASSED and FAILED subjects by ID
$stmt = $pdo->prepare("SELECT subject_id FROM grades WHERE student_id = ? AND status = 'PASSED'");
$stmt->execute([$user['user_id']]);
$passedSubjectIds = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

$stmt = $pdo->prepare("SELECT subject_id FROM grades WHERE student_id = ? AND status = 'FAILED'");
$stmt->execute([$user['user_id']]);
$failedSubjectIds = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

// Get all grades with subject details (limit to recent/most important)
$stmt = $pdo->prepare("
    SELECT s.subject_code, s.descriptive_title, s.units, g.grade, g.status, 
           s.year_level, s.semester
    FROM grades g
    JOIN subjects s ON g.subject_id = s.id
    WHERE g.student_id = ?
    ORDER BY s.year_level, s.semester, s.subject_code
");
$stmt->execute([$user['user_id']]);
$allGrades = $stmt->fetchAll();

// Calculate statistics
$totalSubjects = count($allGrades);
$passedSubjects = 0;
$failedSubjects = 0;
$totalUnits = 0;
$passedUnits = 0;

foreach ($allGrades as $grade) {
    $totalUnits += $grade['units'];
    if ($grade['status'] == 'PASSED') {
        $passedSubjects++;
        $passedUnits += $grade['units'];
    } elseif ($grade['status'] == 'FAILED') {
        $failedSubjects++;
    }
}

// Next semester logic
$nextYearLevel = $user['year_level'];
$nextSemester = $currentSemester + 1;
if ($currentSemester == 2) {
    $nextYearLevel = $user['year_level'] + 1;
    $nextSemester = 1;
}
$isGraduating = ($user['year_level'] == 4 && $currentSemester == 2);

// Get next semester subjects with prerequisites
if (!$isGraduating && $nextYearLevel <= 4) {
    $stmt = $pdo->prepare("
        SELECT s.*, 
               p.prerequisite_id,
               pre.subject_code as prereq_code
        FROM subjects s
        LEFT JOIN prerequisites p ON s.id = p.subject_id
        LEFT JOIN subjects pre ON p.prerequisite_id = pre.id
        WHERE s.course = ? 
        AND s.year_level = ? 
        AND s.semester = ?
        ORDER BY s.subject_code
    ");
    $stmt->execute([$user['course'], $nextYearLevel, $nextSemester]);
    $rows = $stmt->fetchAll();
} else {
    $rows = [];
}

// Group and filter prerequisites
$grouped = [];
foreach ($rows as $row) {
    $id = $row['id'];
    if (!isset($grouped[$id])) {
        $grouped[$id] = ['data' => $row, 'prereqs' => []];
    }
    if ($row['prerequisite_id']) {
        $grouped[$id]['prereqs'][] = [
            'id' => $row['prerequisite_id'],
            'code' => $row['prereq_code']
        ];
    }
}

$availableSubjects = [];
$unavailableSubjects = [];

foreach ($grouped as $g) {
    $subject = $g['data'];
    $missing = [];
    foreach ($g['prereqs'] as $prereq) {
        if (in_array($prereq['id'], $failedSubjectIds)) {
            $missing[] = $prereq['code'] . ' (FAILED)';
        } elseif (!in_array($prereq['id'], $passedSubjectIds)) {
            $missing[] = $prereq['code'] . ' (NOT TAKEN)';
        }
    }
    if (empty($missing)) {
        $availableSubjects[] = $subject;
    } else {
        $subject['missing'] = $missing;
        $unavailableSubjects[] = $subject;
    }
}

$totalNextUnits = 0;
foreach ($availableSubjects as $s) {
    $totalNextUnits += $s['units'];
}

$currentDate = date('F d, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advising Report - <?php echo htmlspecialchars($user['fullname']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', 'Roboto', Arial, sans-serif;
            background: #e8eef2;
            padding: 20px;
        }
        
        /* Print optimization - fits on one page */
        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
            }
            .action-buttons {
                display: none;
            }
            .report-card {
                box-shadow: none;
                border-radius: 0;
                margin: 0;
                page-break-inside: avoid;
            }
            .report-header {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .section {
                page-break-inside: avoid;
            }
            .data-table {
                page-break-inside: avoid;
            }
        }
        
        .report-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .report-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        /* Compact Header */
        .report-header {
            background: linear-gradient(135deg, #1a3c5e, #2c5a7a);
            color: white;
            padding: 15px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-title {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 1px;
        }
        
        .header-subtitle {
            font-size: 11px;
            opacity: 0.85;
            margin-top: 3px;
        }
        
        .header-date {
            font-size: 10px;
            text-align: right;
        }
        
        /* Compact Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            padding: 12px 25px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .info-item {
            text-align: center;
        }
        
        .info-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 3px;
        }
        
        .info-value {
            font-size: 13px;
            font-weight: 700;
            color: #1e293b;
        }
        
        /* Compact Section */
        .section {
            padding: 12px 25px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .section-title {
            font-size: 14px;
            font-weight: 700;
            color: #1a3c5e;
            margin-bottom: 10px;
            padding-bottom: 4px;
            border-bottom: 2px solid #ffd700;
            display: inline-block;
        }
        
        /* Compact Stats Row */
        .stats-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .stat-card {
            flex: 1;
            background: #f8fafc;
            border-radius: 8px;
            padding: 8px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }
        
        .stat-number {
            font-size: 20px;
            font-weight: 800;
            color: #1a3c5e;
        }
        
        .stat-label {
            font-size: 9px;
            color: #64748b;
            margin-top: 2px;
        }
        
        .stat-number.passed {
            color: #10b981;
        }
        
        .stat-number.failed {
            color: #ef4444;
        }
        
        /* Compact Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        
        .data-table th {
            background: #f1f5f9;
            padding: 6px 8px;
            text-align: left;
            font-weight: 600;
            font-size: 10px;
            color: #475569;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .data-table td {
            padding: 5px 8px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        
        .grade-passed {
            color: #10b981;
            font-weight: 600;
            font-size: 10px;
        }
        
        .grade-failed {
            color: #ef4444;
            font-weight: 600;
            font-size: 10px;
        }
        
        .grade-pending {
            color: #f59e0b;
            font-weight: 600;
            font-size: 10px;
        }
        
        .badge-available {
            background: #d1fae5;
            color: #065f46;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: 600;
            display: inline-block;
        }
        
        .badge-unavailable {
            background: #fee2e2;
            color: #991b1b;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: 600;
            display: inline-block;
        }
        
        .prerequisite-note {
            font-size: 9px;
            color: #dc2626;
            margin-top: 2px;
        }
        
        /* Compact Signature Area */
        .signature-area {
            padding: 12px 25px;
            background: #f8fafc;
        }
        
        .signature-grid {
            display: flex;
            justify-content: space-between;
            gap: 15px;
        }
        
        .signature-block {
            flex: 1;
            text-align: center;
        }
        
        .signature-line {
            border-top: 1px solid #94a3b8;
            width: 100%;
            margin-top: 25px;
            margin-bottom: 5px;
        }
        
        .signature-name {
            font-size: 9px;
            font-weight: 600;
            color: #334155;
        }
        
        .signature-title {
            font-size: 8px;
            color: #64748b;
            margin-top: 2px;
        }
        
        /* Compact Footer */
        .report-footer {
            padding: 8px 25px;
            background: #f1f5f9;
            text-align: center;
            font-size: 8px;
            color: #94a3b8;
        }
        
        .warning-box {
            margin-top: 10px;
            padding: 8px;
            background: #fef2f2;
            border-radius: 6px;
            border-left: 3px solid #ef4444;
            font-size: 9px;
        }
        
        .graduation-box {
            text-align: center;
            padding: 15px;
        }
        
        .graduation-box h3 {
            font-size: 16px;
            color: #1a3c5e;
            margin: 5px 0;
        }
        
        /* Buttons */
        .action-buttons {
            position: fixed;
            bottom: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 1000;
        }
        
        .btn-print, .btn-download {
            padding: 8px 18px;
            border-radius: 30px;
            border: none;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        
        .btn-print {
            background: linear-gradient(135deg, #1a3c5e, #2c5a7a);
            color: white;
        }
        
        .btn-download {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .btn-print:hover, .btn-download:hover {
            transform: translateY(-2px);
        }
        
        /* Helper classes */
        .text-center {
            text-align: center;
        }
        .fw-bold {
            font-weight: 700;
        }
    </style>
</head>
<body>
<div class="report-container">
    <div class="report-card">
        <!-- Compact Header -->
        <div class="report-header">
            <div>
                <div class="header-title">ACADEMIC ADVISING REPORT</div>
                <div class="header-subtitle"><?php echo $user['course'] == 'CE' ? 'Civil Engineering' : 'Computer Engineering'; ?></div>
            </div>
            <div class="header-date">
                <?php echo $currentDate; ?>
            </div>
        </div>
        
        <!-- Student Info -->
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">STUDENT</div>
                <div class="info-value"><?php echo htmlspecialchars($user['fullname']); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">ID NUMBER</div>
                <div class="info-value"><?php echo htmlspecialchars($user['user_id']); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">YEAR & SECTION</div>
                <div class="info-value"><?php echo $user['year_level']; ?>Yr - <?php echo $user['section']; ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">TERM</div>
                <div class="info-value"><?php echo $currentSemester == 1 ? '1st Sem' : '2nd Sem'; ?> <?php echo $currentSchoolYear; ?></div>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="section">
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalSubjects; ?></div>
                    <div class="stat-label">Total Subjects</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number passed"><?php echo $passedSubjects; ?></div>
                    <div class="stat-label">Passed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number failed"><?php echo $failedSubjects; ?></div>
                    <div class="stat-label">Failed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalUnits; ?></div>
                    <div class="stat-label">Total Units</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $passedUnits; ?></div>
                    <div class="stat-label">Units Earned</div>
                </div>
            </div>
        </div>
        
        <!-- Grades Summary (Compact) -->
        <div class="section">
            <div class="section-title">GRADE SUMMARY</div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Subject Title</th>
                            <th>Units</th>
                            <th>Grade</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($allGrades): ?>
                            <?php foreach(array_slice($allGrades, 0, 8) as $grade): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($grade['subject_code']); ?></td>
                                    <td><?php echo substr(htmlspecialchars($grade['descriptive_title']), 0, 35); ?><?php echo strlen($grade['descriptive_title']) > 35 ? '...' : ''; ?></td>
                                    <td class="text-center"><?php echo $grade['units']; ?></td>
                                    <td class="text-center"><strong><?php echo number_format($grade['grade'], 2); ?></strong></td>
                                    <td>
                                        <?php if($grade['status'] == 'PASSED'): ?>
                                            <span class="grade-passed">✓</span>
                                        <?php elseif($grade['status'] == 'FAILED'): ?>
                                            <span class="grade-failed">✗</span>
                                        <?php else: ?>
                                            <span class="grade-pending">⏳</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if(count($allGrades) > 8): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: #64748b;">+ <?php echo count($allGrades) - 8; ?> more subjects</td>
                                </tr>
                            <?php endif; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">No grades recorded yet</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Next Semester Recommendations -->
        <div class="section">
            <div class="section-title">
                <?php if($isGraduating): ?>
                    GRADUATION
                <?php elseif($nextYearLevel > 4): ?>
                    COMPLETION
                <?php else: ?>
                    NEXT TERM (<?php echo $nextSemester == 1 ? '1st Sem' : '2nd Sem'; ?> Yr <?php echo $nextYearLevel; ?>)
                <?php endif; ?>
            </div>
            
            <?php if($isGraduating): ?>
                <div class="graduation-box">
                    <div style="font-size: 30px;">🎓</div>
                    <h3>Congratulations Graduate!</h3>
                    <p style="font-size: 10px; color: #64748b;">Please proceed to the Dean's Office for graduation clearance.</p>
                </div>
            <?php elseif($nextYearLevel > 4): ?>
                <div class="graduation-box">
                    <div style="font-size: 30px;">🏆</div>
                    <h3>Program Completed!</h3>
                    <p style="font-size: 10px; color: #64748b;">You are eligible for graduation.</p>
                </div>
            <?php elseif($availableSubjects): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Subject Title</th>
                            <th>Units</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($availableSubjects as $subject): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($subject['subject_code']); ?></td>
                                <td><?php echo htmlspecialchars($subject['descriptive_title']); ?></td>
                                <td class="text-center"><?php echo $subject['units']; ?></td>
                                <td><span class="badge-available">Eligible</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: #f1f5f9;">
                            <td colspan="2" style="text-align: right; font-weight: 600;">Total Units:</td>
                            <td class="text-center" style="font-weight: 700;"><?php echo $totalNextUnits; ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            <?php else: ?>
                <p style="text-align: center; font-size: 11px; color: #64748b; padding: 10px;">No subjects available for next term.</p>
            <?php endif; ?>
            
            <?php if(!empty($unavailableSubjects) && !$isGraduating && $nextYearLevel <= 4): ?>
                <div class="warning-box">
                    <strong>⚠️ Prerequisites Needed:</strong>
                    <?php 
                    $blockedList = [];
                    foreach($unavailableSubjects as $subject):
                        $blockedList[] = htmlspecialchars($subject['subject_code']);
                    endforeach;
                    echo implode(', ', array_slice($blockedList, 0, 4));
                    if(count($blockedList) > 4) echo ' + ' . (count($blockedList) - 4) . ' more';
                    ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Signatures -->
        <div class="signature-area">
            <div class="signature-grid">
                <div class="signature-block">
                    <div class="signature-line"></div>
                    <div class="signature-name">Student's Signature</div>
                </div>
                <div class="signature-block">
                    <div class="signature-line"></div>
                    <div class="signature-name">Adviser's Signature</div>
                </div>
                <div class="signature-block">
                    <div class="signature-line"></div>
                    <div class="signature-name">Dean's Signature</div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="report-footer">
            This advising report is for academic planning purposes.
        </div>
    </div>
</div>

<div class="action-buttons">
    <button class="btn-print" onclick="window.print()">
        <i class="fas fa-print"></i> Print
    </button>
    <button class="btn-download" onclick="downloadAsPDF()">
        <i class="fas fa-download"></i> PDF
    </button>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="https://kit.fontawesome.com/a8e6a6f5b6.js" crossorigin="anonymous"></script>
<script>
    function downloadAsPDF() {
        const element = document.querySelector('.report-container');
        const opt = {
            margin: [0.4, 0.4, 0.4, 0.4],
            filename: 'Advising_Report_<?php echo $user['user_id']; ?>_<?php echo date('Ymd'); ?>.pdf',
            image: { type: 'jpeg', quality: 0.95 },
            html2canvas: { scale: 1.5, letterRendering: true },
            jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
        };
        html2pdf().set(opt).from(element).save();
    }
</script>
</body>
</html>
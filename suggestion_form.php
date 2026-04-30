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

// =============================================
// Get PASSED and FAILED subjects by ID (using prerequisites table)
// =============================================
$stmt = $pdo->prepare("SELECT subject_id FROM grades WHERE student_id = ? AND status = 'PASSED'");
$stmt->execute([$user['user_id']]);
$passedSubjectIds = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

$stmt = $pdo->prepare("SELECT subject_id FROM grades WHERE student_id = ? AND status = 'FAILED'");
$stmt->execute([$user['user_id']]);
$failedSubjectIds = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

// Get recent subjects with grades
$stmt = $pdo->prepare("
    SELECT s.subject_code, s.descriptive_title, s.units, g.grade, g.status, g.date_encoded
    FROM grades g
    JOIN subjects s ON g.subject_id = s.id
    WHERE g.student_id = ?
    ORDER BY g.date_encoded DESC
");
$stmt->execute([$user['user_id']]);
$recentSubjects = $stmt->fetchAll();

// =============================================
// LOGIC FOR NEXT SEMESTER SUBJECTS (SAME AS DASHBOARD)
// =============================================
// Determine next year level and next semester based on current
$nextYearLevel = $user['year_level'];
$nextSemester = $currentSemester + 1;

// If current semester is 2, then next is next year, 1st semester
if ($currentSemester == 2) {
    $nextYearLevel = $user['year_level'] + 1;
    $nextSemester = 1;
}

// Check if student is already in 4th year 2nd semester (graduating)
$isGraduating = ($user['year_level'] == 4 && $currentSemester == 2);

// Get all subjects for next semester with their prerequisites
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

// Group subjects and collect prerequisites
$grouped = [];
foreach ($rows as $row) {
    $id = $row['id'];
    if (!isset($grouped[$id])) {
        $grouped[$id] = [
            'data' => $row,
            'prereqs' => []
        ];
    }
    if ($row['prerequisite_id']) {
        $grouped[$id]['prereqs'][] = [
            'id' => $row['prerequisite_id'],
            'code' => $row['prereq_code']
        ];
    }
}

// Filter subjects based on prerequisites
$availableNextSubjects = [];
$unavailableNextSubjects = [];

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
        $availableNextSubjects[] = $subject;
    } else {
        $subject['missing_prereqs'] = $missing;
        $unavailableNextSubjects[] = $subject;
    }
}

// Remove duplicates from available subjects
$uniqueAvailable = [];
$uniqueIds = [];
foreach ($availableNextSubjects as $subject) {
    if (!in_array($subject['id'], $uniqueIds)) {
        $uniqueIds[] = $subject['id'];
        $uniqueAvailable[] = $subject;
    }
}
$availableNextSubjects = $uniqueAvailable;

// Calculate totals
$totalUnitsTaken = 0;
$totalUnitsPassed = 0;
foreach ($recentSubjects as $subject) {
    $totalUnitsTaken += $subject['units'];
    if ($subject['status'] == 'PASSED') {
        $totalUnitsPassed += $subject['units'];
    }
}

$totalNextUnits = 0;
foreach ($availableNextSubjects as $subject) {
    $totalNextUnits += $subject['units'];
}

// Format next semester display text
if ($isGraduating) {
    $nextSemesterDisplay = "You are on your final semester!";
} elseif ($nextYearLevel > 4) {
    $nextSemesterDisplay = "You have completed all subjects!";
} else {
    $nextSemesterDisplay = "Year " . $nextYearLevel . " - " . ($nextSemester == 1 ? "1st Semester" : "2nd Semester");
}

$currentDate = date('F d, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Suggestion Form - <?php echo htmlspecialchars($user['fullname']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Times New Roman', Times, serif;
            background: #e0e0e0;
            padding: 40px;
        }
        
        .suggestion-form {
            max-width: 1100px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        /* Header */
        .header {
            text-align: center;
            padding: 30px;
            border-bottom: 2px solid #333;
        }
        
        .logo {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .logo-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
        }
        
        .school-name {
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 2px;
        }
        
        .school-address {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .form-title {
            font-size: 20px;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid #ccc;
        }
        
        .form-subtitle {
            font-size: 14px;
            margin-top: 5px;
        }
        
        /* Student Info */
        .student-info {
            padding: 20px 30px;
            border-bottom: 1px solid #ccc;
            background: #f9f9f9;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }
        
        .info-label {
            width: 140px;
            font-weight: bold;
        }
        
        .info-value {
            flex: 1;
            border-bottom: 1px solid #999;
            padding-left: 10px;
        }
        
        /* Summary Box */
        .summary-box {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed #ccc;
        }
        
        .summary-item {
            text-align: center;
            flex: 1;
        }
        
        .summary-number {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }
        
        .summary-label {
            font-size: 12px;
            color: #666;
        }
        
        /* Sections */
        .section {
            padding: 20px 30px;
            border-bottom: 1px solid #ccc;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #667eea;
            display: inline-block;
        }
        
        /* Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        .data-table th,
        .data-table td {
            border: 1px solid #ddd;
            padding: 8px 12px;
            text-align: left;
        }
        
        .data-table th {
            background: #f5f5f5;
            font-weight: bold;
            text-align: center;
        }
        
        .data-table td {
            text-align: center;
        }
        
        .data-table td:first-child {
            text-align: left;
        }
        
        .status-passed {
            color: #28a745;
            font-weight: bold;
        }
        
        .status-failed {
            color: #dc3545;
            font-weight: bold;
        }
        
        .status-pending {
            color: #ffc107;
            font-weight: bold;
        }
        
        .badge-available {
            color: #28a745;
            font-weight: bold;
        }
        
        .badge-not-available {
            color: #dc3545;
            font-weight: bold;
        }
        
        .prerequisite-note {
            font-size: 12px;
            color: #dc3545;
            margin-top: 10px;
            padding: 10px;
            background: #fff3cd;
            border-radius: 5px;
        }
        
        /* Signature Section */
        .signature-section {
            padding: 30px;
            display: flex;
            justify-content: space-between;
        }
        
        .signature-box {
            text-align: center;
            width: 45%;
        }
        
        .signature-line {
            border-top: 1px solid #000;
            width: 100%;
            margin-top: 50px;
            margin-bottom: 8px;
        }
        
        .signature-label {
            font-size: 12px;
        }
        
        /* Footer */
        .footer {
            padding: 15px 30px;
            text-align: center;
            font-size: 10px;
            color: #999;
            border-top: 1px solid #ccc;
            background: #f9f9f9;
        }
        
        /* Print Button */
        .print-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 30px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        
        .download-btn {
            position: fixed;
            bottom: 30px;
            left: 30px;
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 30px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        
        .print-btn:hover, .download-btn:hover {
            transform: translateY(-2px);
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
            }
            .print-btn, .download-btn {
                display: none;
            }
            .suggestion-form {
                box-shadow: none;
                margin: 0;
                max-width: 100%;
            }
            .signature-line {
                margin-top: 60px;
            }
        }
    </style>
</head>
<body>
    <div class="suggestion-form">
        <!-- Header -->
        <div class="header">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div>
                    <div class="school-name">UNIVERSITY OF ACADEMIC EXCELLENCE</div>
                    <div class="school-address">123 Education St., Manila, Philippines 1000</div>
                </div>
            </div>
            <div class="form-title">
                ACADEMIC SUGGESTION FORM / ADVISING SLIP
            </div>
            <div class="form-subtitle">
                School Year: <?php echo $currentSchoolYear; ?> | Semester: <?php echo $currentSemester == 1 ? '1st Semester' : '2nd Semester'; ?>
            </div>
        </div>
        
        <!-- Student Information -->
        <div class="student-info">
            <div class="info-row">
                <div class="info-label">Student Name:</div>
                <div class="info-value"><?php echo htmlspecialchars($user['fullname']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Student ID:</div>
                <div class="info-value"><?php echo htmlspecialchars($user['user_id']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Course:</div>
                <div class="info-value"><?php echo $user['course'] == 'CE' ? 'Bachelor of Science in Civil Engineering (BSCE)' : 'Bachelor of Science in Computer Engineering (BSCpE)'; ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Year & Section:</div>
                <div class="info-value"><?php echo $user['year_level']; ?> Year - Section <?php echo htmlspecialchars($user['section']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Date:</div>
                <div class="info-value"><?php echo $currentDate; ?></div>
            </div>
            
            <!-- Summary -->
            <div class="summary-box">
                <div class="summary-item">
                    <div class="summary-number"><?php echo count($recentSubjects); ?></div>
                    <div class="summary-label">Total Subjects Taken</div>
                </div>
                <div class="summary-item">
                    <div class="summary-number"><?php echo $totalUnitsTaken; ?></div>
                    <div class="summary-label">Total Units Taken</div>
                </div>
                <div class="summary-item">
                    <div class="summary-number"><?php echo $totalUnitsPassed; ?></div>
                    <div class="summary-label">Total Units Passed</div>
                </div>
            </div>
        </div>
        
        <!-- Recent Subjects with Grades -->
        <div class="section">
            <div class="section-title">📚 RECENT SUBJECTS WITH GRADES</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Subject Code</th>
                        <th>Descriptive Title</th>
                        <th class="text-center">Units</th>
                        <th class="text-center">Grade</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($recentSubjects): ?>
                        <?php foreach($recentSubjects as $subject): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($subject['subject_code']); ?></td>
                                <td><?php echo htmlspecialchars($subject['descriptive_title']); ?></td>
                                <td class="text-center"><?php echo $subject['units']; ?></td>
                                <td class="text-center"><strong><?php echo number_format($subject['grade'], 2); ?></strong></td>
                                <td class="text-center">
                                    <?php if($subject['status'] == 'PASSED'): ?>
                                        <span class="status-passed">✓ PASSED</span>
                                    <?php elseif($subject['status'] == 'FAILED'): ?>
                                        <span class="status-failed">✗ FAILED</span>
                                    <?php else: ?>
                                        <span class="status-pending">⏳ PENDING</span>
                                    <?php endif; ?>
                                 </div>
                                 </div>
                             </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center">No grades recorded yet</div>
                         </div>
                    <?php endif; ?>
                </tbody>
            </div>
        </div>
        
        <!-- Next Subjects for Next Semester -->
        <div class="section">
            <div class="section-title">📖 NEXT SEMESTER SUBJECTS (<?php echo $nextSemesterDisplay; ?>)</div>
            
            <?php if($isGraduating): ?>
                <div class="text-center py-4">
                    <i class="fas fa-graduation-cap fa-3x text-muted mb-3"></i>
                    <p class="text-muted">You are on your final semester. No more subjects to take!</p>
                    <small class="text-muted">Please proceed to the Dean's office for graduation clearance.</small>
                </div>
            <?php elseif($nextYearLevel > 4): ?>
                <div class="text-center py-4">
                    <i class="fas fa-trophy fa-3x text-muted mb-3"></i>
                    <p class="text-muted">You have completed all subjects!</p>
                    <small class="text-muted">You are eligible for graduation.</small>
                </div>
            <?php elseif($availableNextSubjects): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Subject Code</th>
                            <th>Descriptive Title</th>
                            <th class="text-center">Units</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($availableNextSubjects as $subject): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($subject['subject_code']); ?></td>
                                <td><?php echo htmlspecialchars($subject['descriptive_title']); ?></td>
                                <td class="text-center"><?php echo $subject['units']; ?></td>
                                <td class="text-center">
                                    <span class="badge-available">✓ Available to take</span>
                                 </div>
                                 </div>
                             </div>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-light">
                            <td colspan="2" class="text-end fw-bold">Total Units: </div>
                            <td class="text-center fw-bold"><?php echo $totalNextUnits; ?> </div>
                            <td></div>
                             </tr>
                        </tfoot>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-info-circle fa-2x text-muted mb-2"></i>
                    <p class="text-muted">No subjects available for next semester.</p>
                </div>
            <?php endif; ?>
            
            <?php if($unavailableNextSubjects && !$isGraduating && $nextYearLevel <= 4): ?>
                <div class="prerequisite-note">
                    <strong>⚠️ Subjects Not Available (Missing Prerequisites):</strong><br>
                    <?php 
                    $unavailableList = [];
                    foreach($unavailableNextSubjects as $subject):
                        $unavailableList[] = htmlspecialchars($subject['subject_code']) . ' (needs ' . implode(' and ', array_unique($subject['missing_prereqs'])) . ')';
                    endforeach;
                    echo implode(' | ', array_unique($unavailableList));
                    ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Signature Section -->
        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-label">Student's Signature</div>
                <div class="signature-label" style="margin-top: 5px;">Date: _______________</div>
            </div>
            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-label">Adviser's Signature</div>
                <div class="signature-label" style="margin-top: 5px;">Date: _______________</div>
            </div>
        </div>
        
        <div class="signature-section" style="padding-top: 0;">
            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-label">Program Head / Dean's Signature</div>
                <div class="signature-label" style="margin-top: 5px;">Date: _______________</div>
            </div>
            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-label">Registrar's Signature</div>
                <div class="signature-label" style="margin-top: 5px;">Date: _______________</div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>This form is issued for academic advising purposes. Please present this form to the Program Head/Dean for approval.</p>
            <p>Generated on: <?php echo $currentDate . ' - ' . date('h:i A'); ?></p>
        </div>
    </div>
    
    <button class="print-btn" onclick="window.print()">
        <i class="fas fa-print"></i> Print Form
    </button>
    
    <button class="download-btn" onclick="downloadAsPDF()">
        <i class="fas fa-download"></i> Download as PDF
    </button>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a8e6a6f5b6.js" crossorigin="anonymous"></script>
    <script>
        function downloadAsPDF() {
            const element = document.querySelector('.suggestion-form');
            const opt = {
                margin: [0.5, 0.5, 0.5, 0.5],
                filename: 'Academic_Suggestion_Form_<?php echo $user['user_id']; ?>_<?php echo date('Ymd'); ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, letterRendering: true },
                jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
            };
            html2pdf().set(opt).from(element).save();
        }
    </script>
</body>
</html>
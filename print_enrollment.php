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

// Get subjects for current semester
$stmt = $pdo->prepare("
    SELECT s.*, g.grade, g.status as grade_status 
    FROM subjects s
    LEFT JOIN grades g ON s.id = g.subject_id AND g.student_id = ?
    WHERE s.course = ? AND s.year_level = ? AND s.semester = ?
    ORDER BY s.subject_code
");
$stmt->execute([$user['user_id'], $user['course'], $user['year_level'], $currentSemester]);
$subjects = $stmt->fetchAll();

// Calculate total units
$totalUnits = 0;
foreach ($subjects as $subject) {
    $totalUnits += $subject['units'];
}

// Get current date
$currentDate = date('F d, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment Form - <?php echo htmlspecialchars($user['fullname']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Times New Roman', Times, serif;
            background: #f0f0f0;
            padding: 40px;
        }
        
        .enrollment-form {
            max-width: 900px;
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
        
        .logo-placeholder {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 30px;
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
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid #ccc;
        }
        
        /* Student Info Section */
        .student-info {
            padding: 20px 30px;
            border-bottom: 1px solid #ccc;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 12px;
        }
        
        .info-label {
            width: 150px;
            font-weight: bold;
        }
        
        .info-value {
            flex: 1;
            border-bottom: 1px solid #000;
            padding-left: 10px;
        }
        
        /* Subjects Table */
        .subjects-section {
            padding: 20px 30px;
        }
        
        .subjects-title {
            font-weight: bold;
            margin-bottom: 15px;
            text-align: center;
            text-transform: uppercase;
        }
        
        .subjects-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .subjects-table th,
        .subjects-table td {
            border: 1px solid #000;
            padding: 8px 12px;
            text-align: left;
        }
        
        .subjects-table th {
            background: #f5f5f5;
            font-weight: bold;
            text-align: center;
        }
        
        .subjects-table td {
            text-align: center;
        }
        
        .subjects-table td:first-child {
            text-align: left;
        }
        
        .total-row {
            font-weight: bold;
            background: #f9f9f9;
        }
        
        /* Signature Section */
        .signature-section {
            padding: 20px 30px;
            border-top: 1px solid #ccc;
        }
        
        .signature-row {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        
        .signature-box {
            text-align: center;
            width: 45%;
        }
        
        .signature-line {
            border-top: 1px solid #000;
            width: 100%;
            margin-top: 40px;
            margin-bottom: 5px;
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
        }
        
        /* Print Styles */
        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
            }
            .no-print {
                display: none;
            }
            .enrollment-form {
                box-shadow: none;
                margin: 0;
                max-width: 100%;
            }
            .signature-line {
                margin-top: 50px;
            }
        }
        
        /* Print Button */
        .print-button {
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
        
        .print-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }
        
        .download-button {
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
        
        .download-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(40,167,69,0.3);
        }
    </style>
</head>
<body>
    <div class="enrollment-form">
        <!-- Header -->
        <div class="header">
            <div class="logo">
                <div class="logo-placeholder">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div>
                    <div class="school-name">UNIVERSITY OF ACADEMIC EXCELLENCE</div>
                    <div class="school-address">Marigman St., Antipolo, 1870 Rizal</div>
                </div>
            </div>
            <div class="form-title">
                ENROLLMENT FORM / ADVISING SLIP
            </div>
            <div style="font-size: 12px; margin-top: 5px;">
                School Year: <?php echo $currentSchoolYear; ?> | Semester: <?php echo $currentSemester == 1 ? '1st Semester' : '2nd Semester'; ?>
            </div>
        </div>
        
        <!-- Student Information -->
        <div class="student-info">
            <div class="info-row">
                <div class="info-label">Student ID:</div>
                <div class="info-value"><?php echo htmlspecialchars($user['user_id']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Full Name:</div>
                <div class="info-value"><?php echo htmlspecialchars($user['fullname']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Course:</div>
                <div class="info-value"><?php echo $user['course'] == 'CE' ? 'Bachelor of Science in Civil Engineering (BSCE)' : 'Bachelor of Science in Computer Engineering (BSCpE)'; ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Year Level:</div>
                <div class="info-value"><?php echo $user['year_level']; ?> Year</div>
            </div>
            <div class="info-row">
                <div class="info-label">Section:</div>
                <div class="info-value">Section <?php echo htmlspecialchars($user['section']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Date:</div>
                <div class="info-value"><?php echo $currentDate; ?></div>
            </div>
        </div>
        
        <!-- Subjects -->
        <div class="subjects-section">
            <div class="subjects-title">PROPOSED ENROLLMENT FOR THE CURRENT SEMESTER</div>
            
            <table class="subjects-table">
                <thead>
                    <tr>
                        <th style="width: 20%;">Subject Code</th>
                        <th style="width: 50%;">Descriptive Title</th>
                        <th style="width: 15%;">Units</th>
                        <th style="width: 15%;">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($subjects): ?>
                        <?php foreach($subjects as $subject): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($subject['subject_code']); ?></td>
                                <td><?php echo htmlspecialchars($subject['descriptive_title']); ?></td>
                                <td class="text-center"><?php echo $subject['units']; ?></td>
                                <td class="text-center">—</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center;">No subjects available for enrollment</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="2" style="text-align: right;"><strong>TOTAL UNITS:</strong></td>
                        <td style="text-align: center;"><strong><?php echo $totalUnits; ?></strong></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
            
            <div style="font-size: 11px; margin-top: 10px; font-style: italic;">
                * The student is advised to follow the prescribed curriculum. Any changes must be approved by the Program Head.
            </div>
        </div>
        
        <!-- Signature Section -->
        <div class="signature-section">
            <div class="signature-row">
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div class="signature-label">Student's Signature</div>
                </div>
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div class="signature-label">Date</div>
                </div>
            </div>
            
            <div class="signature-row">
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div class="signature-label">Adviser's Signature</div>
                </div>
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div class="signature-label">Date</div>
                </div>
            </div>
            
            <div class="signature-row">
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div class="signature-label">Program Head / Dean's Signature</div>
                </div>
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div class="signature-label">Date</div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>This form is issued for enrollment purposes. Please present this form to the Accounting Department and Registrar's Office.</p>
            <p>Generated on: <?php echo $currentDate . ' ' . date('h:i A'); ?></p>
        </div>
    </div>
    
    <button class="print-button no-print" onclick="window.print()">
        <i class="fas fa-print me-2"></i> Print Form
    </button>
    
    <button class="download-button no-print" onclick="downloadAsPDF()">
        <i class="fas fa-download me-2"></i> Download as PDF
    </button>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        function downloadAsPDF() {
            const element = document.querySelector('.enrollment-form');
            const opt = {
                margin: [0.5, 0.5, 0.5, 0.5],
                filename: 'Enrollment_Form_<?php echo $user['user_id']; ?>_<?php echo date('Ymd'); ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, letterRendering: true },
                jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
            };
            html2pdf().set(opt).from(element).save();
        }
    </script>
    <script src="https://kit.fontawesome.com/a8e6a6f5b6.js" crossorigin="anonymous"></script>
</body>
</html>
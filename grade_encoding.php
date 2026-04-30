<?php
require_once '../includes/auth.php';
requireRole('student');
$user = getUserData($_SESSION['user_id']);

// Check if grade encoding is enabled
$stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'grade_encoding_enabled'");
$stmt->execute();
$encodingEnabled = $stmt->fetch()['setting_value'] == '1';

// Get current school year and semester
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$currentSchoolYear = $settings['current_school_year'] ?? '2024-2025';
$currentSemester = $settings['current_semester'] ?? '1';

// Get student's enrolled subjects (current semester subjects based on year level)
$stmt = $pdo->prepare("
    SELECT s.*, g.id as grade_id, g.grade, g.status as grade_status 
    FROM subjects s
    LEFT JOIN grades g ON s.id = g.subject_id AND g.student_id = ? AND g.school_year = ? AND g.semester = ?
    WHERE s.course = ? AND s.year_level = ? AND s.semester = ?
    ORDER BY s.subject_code
");
$stmt->execute([$user['user_id'], $currentSchoolYear, $currentSemester, $user['course'], $user['year_level'], $currentSemester]);
$subjects = $stmt->fetchAll();

// Calculate year standing from all grades (all semesters)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total, 
           SUM(CASE WHEN status = 'PASSED' THEN 1 ELSE 0 END) as passed
    FROM grades 
    WHERE student_id = ?
");
$stmt->execute([$user['user_id']]);
$stats = $stmt->fetch();
$yearStanding = $stats['total'] > 0 ? ($stats['passed'] / $stats['total']) * 100 : 0;

// Handle grade submission
$save_success = '';
$save_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $encodingEnabled) {
    if (isset($_POST['save_all']) || isset($_POST['save_single'])) {
        $grades_saved = 0;
        $grades_failed = 0;
        
        foreach ($_POST['grades'] as $subject_id => $grade) {
            if (!empty($grade)) {
                $grade = floatval($grade);
                if ($grade >= 1.00 && $grade <= 5.00) {
                    $status = ($grade >= 1.00 && $grade <= 3.00) ? 'PASSED' : 'FAILED';
                    
                    // Check if grade already exists
                    $stmt = $pdo->prepare("
                        SELECT id FROM grades 
                        WHERE student_id = ? AND subject_id = ? AND school_year = ? AND semester = ?
                    ");
                    $stmt->execute([$user['user_id'], $subject_id, $currentSchoolYear, $currentSemester]);
                    $existing = $stmt->fetch();
                    
                    if ($existing) {
                        // UPDATE existing grade
                        $stmt = $pdo->prepare("
                            UPDATE grades 
                            SET grade = ?, status = ?, date_encoded = NOW()
                            WHERE student_id = ? AND subject_id = ? AND school_year = ? AND semester = ?
                        ");
                        if ($stmt->execute([$grade, $status, $user['user_id'], $subject_id, $currentSchoolYear, $currentSemester])) {
                            $grades_saved++;
                        } else {
                            $grades_failed++;
                        }
                    } else {
                        // INSERT new grade
                        $stmt = $pdo->prepare("
                            INSERT INTO grades (student_id, subject_id, grade, status, school_year, semester, date_encoded)
                            VALUES (?, ?, ?, ?, ?, ?, NOW())
                        ");
                        if ($stmt->execute([$user['user_id'], $subject_id, $grade, $status, $currentSchoolYear, $currentSemester])) {
                            $grades_saved++;
                        } else {
                            $grades_failed++;
                        }
                    }
                }
            }
        }
        
        // Check if redirect parameter is set to go back to dashboard
        $redirect_to = $_POST['redirect_to'] ?? '';
        
        if ($grades_saved > 0) {
            $save_success = "$grades_saved grade(s) have been saved successfully!";
            
            if ($redirect_to == 'dashboard') {
                header("Location: dashboard.php?success=1");
                exit();
            } else {
                header("Location: grade_encoding.php?success=1");
                exit();
            }
        } elseif ($grades_failed > 0) {
            $save_error = "Failed to save $grades_failed grade(s).";
        } else {
            $save_error = "No valid grades were entered. Please enter grades between 1.00 and 5.00.";
        }
    }
}

// Check for success message from redirect
if (isset($_GET['success'])) {
    $save_success = "Grades have been saved successfully!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Encoding - Academic Advising System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .grade-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .grade-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .status-passed { background: #d4edda; color: #155724; }
        .status-failed { background: #f8d7da; color: #721c24; }
        .status-pending { background: #fff3cd; color: #856404; }
        .standing-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .progress {
            height: 10px;
            border-radius: 10px;
        }
        .btn-print {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            padding: 10px 25px;
            border-radius: 25px;
            color: white;
        }
        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40,167,69,0.4);
            color: white;
        }
        .btn-dashboard {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            padding: 10px 25px;
            border-radius: 25px;
            color: white;
            margin-right: 10px;
        }
        .btn-dashboard:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
            color: white;
        }
        .grade-input {
            width: 100px;
            text-align: center;
        }
        .grade-input.editable {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
        }
        .grade-input.editable:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102,126,234,0.25);
            background-color: white;
        }
        .grade-display {
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 8px;
            display: inline-block;
        }
        .btn-save-single {
            padding: 5px 12px;
            font-size: 12px;
        }
        .table-grades th {
            background: #f8f9fa;
        }
        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 15px;
        }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-edit me-2"></i>Grade Encoding</h2>
        <div>
            <button class="btn-dashboard" onclick="window.location.href='dashboard.php'">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </button>
            <button class="btn-print" onclick="window.open('print_enrollment.php', '_blank')">
                <i class="fas fa-print me-2"></i>Print Enrollment Form
            </button>
        </div>
    </div>

    <?php if($save_success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $save_success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if($save_error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $save_error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if(!$encodingEnabled): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Grade encoding is currently disabled by the administrator.
        </div>
    <?php endif; ?>

    <div class="standing-card">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h5 class="mb-2">Year Standing</h5>
                <p class="mb-0">You need at least 75% to proceed to higher year level</p>
            </div>
            <div class="col-md-6">
                <div class="d-flex justify-content-between mb-2">
                    <span>Progress: <?php echo round($yearStanding); ?>%</span>
                    <span><?php echo $stats['passed']; ?>/<?php echo $stats['total']; ?> subjects passed</span>
                </div>
                <div class="progress bg-light">
                    <div class="progress-bar" style="width: <?php echo $yearStanding; ?>%; background: #ffd700;"></div>
                </div>
                <?php if($yearStanding >= 75): ?>
                    <div class="mt-2 text-success"><i class="fas fa-check-circle"></i> Eligible for higher year level</div>
                <?php else: ?>
                    <div class="mt-2 text-warning"><i class="fas fa-exclamation-circle"></i> Need to improve standing</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="grade-card">
        <div class="grade-header">
            <h4 class="mb-0">Enrolled Subjects</h4>
            <small><?php echo $currentSchoolYear . ' - ' . ($currentSemester == 1 ? '1st Semester' : '2nd Semester'); ?></small>
        </div>
        <div class="table-responsive">
            <form method="POST" id="gradeForm">
                <input type="hidden" name="redirect_to" id="redirect_to" value="">
                <table class="table table-bordered mb-0 table-grades">
                    <thead class="table-light">
                        <tr>
                            <th>Subject Code</th>
                            <th>Descriptive Title</th>
                            <th>Units</th>
                            <th>Grade</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($subjects as $subject): ?>
                            <?php 
                            $has_grade = !is_null($subject['grade']);
                            $grade_value = $has_grade ? floatval($subject['grade']) : '';
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($subject['subject_code']); ?></td>
                                <td><?php echo htmlspecialchars($subject['descriptive_title']); ?></td>
                                <td class="text-center"><?php echo $subject['units']; ?></td>
                                <td>
                                    <?php if($encodingEnabled): ?>
                                        <input type="number" step="0.01" min="1.00" max="5.00" 
                                               name="grades[<?php echo $subject['id']; ?>]" 
                                               class="form-control form-control-sm grade-input <?php echo !$has_grade ? 'editable' : ''; ?>"
                                               value="<?php echo $grade_value; ?>"
                                               placeholder="1.00 - 5.00">
                                    <?php else: ?>
                                        <?php if($has_grade): ?>
                                            <span class="grade-display"><?php echo number_format($grade_value, 2); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                 </div>
                                </td>
                                <td>
                                    <?php if($has_grade): ?>
                                        <span class="status-badge status-<?php echo strtolower($subject['grade_status']); ?>">
                                            <i class="fas <?php echo $subject['grade_status'] == 'PASSED' ? 'fa-check-circle' : 'fa-times-circle'; ?> me-1"></i>
                                            <?php echo $subject['grade_status']; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge status-pending">
                                            <i class="fas fa-clock me-1"></i>PENDING
                                        </span>
                                    <?php endif; ?>
                                 </div>
                                </td>
                                <td>
                                    <?php if($encodingEnabled): ?>
                                        <button type="submit" name="save_single" value="<?php echo $subject['id']; ?>" 
                                                class="btn btn-sm btn-primary btn-save-single"
                                                onclick="document.getElementById('redirect_to').value=''">
                                            <i class="fas fa-save me-1"></i>Save
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                 </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if($encodingEnabled && !empty($subjects)): ?>
                    <div class="action-buttons">
                        <button type="submit" name="save_all" class="btn btn-primary" onclick="document.getElementById('redirect_to').value=''">
                            <i class="fas fa-save me-2"></i>Save & Stay Here
                        </button>
                        <button type="submit" name="save_all" class="btn btn-success" onclick="document.getElementById('redirect_to').value='dashboard'">
                            <i class="fas fa-save me-2"></i>Save & Go to Dashboard
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    // Real-time grade validation and status preview
    $(document).ready(function() {
        $('.grade-input').on('input', function() {
            var grade = parseFloat($(this).val());
            var row = $(this).closest('tr');
            var statusSpan = row.find('.status-badge');
            
            if (isNaN(grade)) {
                statusSpan.removeClass('status-passed status-failed');
                statusSpan.addClass('status-pending');
                statusSpan.html('<i class="fas fa-clock me-1"></i>PENDING');
            } else if (grade >= 1.00 && grade <= 3.00) {
                statusSpan.removeClass('status-pending status-failed');
                statusSpan.addClass('status-passed');
                statusSpan.html('<i class="fas fa-check-circle me-1"></i>PASSED');
            } else if (grade > 3.00 && grade <= 5.00) {
                statusSpan.removeClass('status-pending status-passed');
                statusSpan.addClass('status-failed');
                statusSpan.html('<i class="fas fa-times-circle me-1"></i>FAILED');
            } else {
                statusSpan.removeClass('status-passed status-failed status-pending');
                statusSpan.addClass('status-pending');
                statusSpan.html('<i class="fas fa-exclamation-triangle me-1"></i>INVALID');
            }
        });
    });
    
    // Confirmation before saving all grades
    document.getElementById('gradeForm')?.addEventListener('submit', function(e) {
        var hasGrades = false;
        var gradeInputs = document.querySelectorAll('.grade-input');
        
        for (var i = 0; i < gradeInputs.length; i++) {
            if (gradeInputs[i].value !== '') {
                hasGrades = true;
                break;
            }
        }
        
        if (hasGrades && e.submitter && (e.submitter.name === 'save_all' || e.submitter.name === 'save_single')) {
            if (!confirm('Are you sure you want to save these grades? This will update your records.')) {
                e.preventDefault();
            }
        }
    });
</script>
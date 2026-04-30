<?php
require_once '../includes/auth.php';
requireRole('admin');

// Get current settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$grade_encoding_enabled = $settings['grade_encoding_enabled'] ?? '1';
$current_school_year = $settings['current_school_year'] ?? '2024-2025';
$current_semester = $settings['current_semester'] ?? '1';
$semester_status = $settings['semester_status'] ?? 'ongoing';

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Toggle grade encoding status
    if ($action === 'toggle_encoding') {
        $new_status = $_POST['grade_encoding_enabled'] ?? '0';
        $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'grade_encoding_enabled'");
        if ($stmt->execute([$new_status])) {
            $grade_encoding_enabled = $new_status;
            $message = "Grade encoding " . ($new_status == '1' ? "enabled" : "disabled") . " successfully!";
        } else {
            $error = "Failed to update grade encoding status.";
        }
    }
    
    // Update school year and semester
    if ($action === 'update_semester') {
        $school_year = $_POST['school_year'];
        $semester = $_POST['semester'];
        $status = $_POST['semester_status'];
        
        $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'current_school_year'");
        $stmt->execute([$school_year]);
        
        $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'current_semester'");
        $stmt->execute([$semester]);
        
        $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'semester_status'");
        $stmt->execute([$status]);
        
        $current_school_year = $school_year;
        $current_semester = $semester;
        $semester_status = $status;
        
        $message = "Semester settings updated successfully!";
    }
    
    // Bulk update grades
    if ($action === 'bulk_update_grades') {
        $subject_id = $_POST['subject_id'];
        $grades = $_POST['grades'] ?? [];
        
        $success_count = 0;
        $fail_count = 0;
        
        foreach ($grades as $student_id => $grade) {
            if (!empty($grade)) {
                $status = ($grade >= 1.00 && $grade <= 3.00) ? 'PASSED' : 'FAILED';
                
                $stmt = $pdo->prepare("
                    INSERT INTO grades (student_id, subject_id, grade, status, school_year, semester, date_encoded)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE 
                    grade = VALUES(grade), 
                    status = VALUES(status),
                    date_encoded = NOW()
                ");
                
                if ($stmt->execute([$student_id, $subject_id, $grade, $status, $current_school_year, $current_semester])) {
                    $success_count++;
                } else {
                    $fail_count++;
                }
            }
        }
        
        $message = "Grades updated: $success_count successful, $fail_count failed.";
    }
    
    // Reset all grades for a subject
    if ($action === 'reset_subject_grades') {
        $subject_id = $_POST['subject_id'];
        
        $stmt = $pdo->prepare("DELETE FROM grades WHERE subject_id = ? AND school_year = ? AND semester = ?");
        if ($stmt->execute([$subject_id, $current_school_year, $current_semester])) {
            $message = "All grades for this subject have been reset.";
        } else {
            $error = "Failed to reset grades.";
        }
    }
    
    // Send notification/announcement
    if ($action === 'send_announcement') {
        $announcement = $_POST['announcement'];
        
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value) 
            VALUES ('announcement', ?)
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        if ($stmt->execute([$announcement, $announcement])) {
            $message = "Announcement posted successfully!";
        } else {
            $error = "Failed to post announcement.";
        }
    }
}

// Get all subjects for dropdown
$subjects = $pdo->query("SELECT id, subject_code, descriptive_title, course, year_level, semester FROM subjects ORDER BY course, year_level, semester, subject_code")->fetchAll();

// Get students for grade entry
$students = $pdo->query("SELECT user_id, fullname, course, year_level, section FROM users WHERE role = 'student' AND status = 'active' ORDER BY fullname")->fetchAll();

// Get selected subject for grade entry
$selected_subject = $_GET['subject_id'] ?? ($subjects[0]['id'] ?? 0);
$student_grades = [];

if ($selected_subject) {
    $stmt = $pdo->prepare("
        SELECT s.*, 
               g.id as grade_id, g.grade, g.status as grade_status
        FROM users s
        LEFT JOIN grades g ON s.user_id = g.student_id AND g.subject_id = ? AND g.school_year = ? AND g.semester = ?
        WHERE s.role = 'student' AND s.status = 'active'
        ORDER BY s.fullname
    ");
    $stmt->execute([$selected_subject, $current_school_year, $current_semester]);
    $student_grades = $stmt->fetchAll();
}

// Get grade statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_grades,
        SUM(CASE WHEN status = 'PASSED' THEN 1 ELSE 0 END) as passed,
        SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) as failed,
        AVG(grade) as average_grade
    FROM grades 
    WHERE school_year = ? AND semester = ?
");
$stmt->execute([$current_school_year, $current_semester]);
$grade_stats = $stmt->fetch();

// Get recent grade submissions
$recent_grades = $pdo->query("
    SELECT g.*, u.fullname as student_name, s.subject_code, s.descriptive_title
    FROM grades g
    JOIN users u ON g.student_id = u.user_id
    JOIN subjects s ON g.subject_id = s.id
    ORDER BY g.date_encoded DESC
    LIMIT 10
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Encoding Control - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
        }
        .content-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        .status-toggle {
            display: inline-block;
            width: 60px;
            height: 30px;
            border-radius: 30px;
            background: #dc3545;
            position: relative;
            cursor: pointer;
            transition: all 0.3s;
        }
        .status-toggle.enabled {
            background: #28a745;
        }
        .status-toggle .toggle-slider {
            width: 26px;
            height: 26px;
            background: white;
            border-radius: 50%;
            position: absolute;
            top: 2px;
            left: 2px;
            transition: all 0.3s;
        }
        .status-toggle.enabled .toggle-slider {
            left: 32px;
        }
        .badge-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-enabled {
            background: #d4edda;
            color: #155724;
        }
        .badge-disabled {
            background: #f8d7da;
            color: #721c24;
        }
        .badge-ongoing {
            background: #fff3cd;
            color: #856404;
        }
        .badge-completed {
            background: #cce5ff;
            color: #004085;
        }
        .grade-input {
            width: 80px;
            text-align: center;
        }
        .grade-passed {
            background: #d4edda;
            color: #155724;
            font-weight: 600;
        }
        .grade-failed {
            background: #f8d7da;
            color: #721c24;
            font-weight: 600;
        }
        .stat-box {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: transform 0.3s;
        }
        .stat-box:hover {
            transform: translateY(-5px);
        }
        .stat-number {
            font-size: 32px;
            font-weight: 700;
        }
        .stat-label {
            font-size: 14px;
            color: #718096;
            margin-top: 5px;
        }
        .btn-save {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            padding: 10px 25px;
            border-radius: 25px;
            color: white;
        }
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
            color: white;
        }
        .modal-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        .announcement-box {
            background: #fff3cd;
            border-left: 4px solid var(--warning);
            padding: 15px;
            border-radius: 10px;
        }
        .table-grades th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        @media (max-width: 768px) {
            .grade-input {
                width: 60px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>

<div class="container-fluid">
    <!-- Messages -->
    <?php if($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Grade Encoding Status Card -->
    <div class="content-card">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h4><i class="fas fa-lock me-2 text-primary"></i>Grade Encoding Control</h4>
                <p class="text-muted mb-0">Enable or disable student grade encoding access</p>
            </div>
            <div class="col-md-6 text-md-end">
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="toggle_encoding">
                    <input type="hidden" name="grade_encoding_enabled" value="<?php echo $grade_encoding_enabled == '1' ? '0' : '1'; ?>">
                    <button type="submit" class="btn btn-save">
                        <i class="fas fa-power-off me-2"></i>
                        <?php echo $grade_encoding_enabled == '1' ? 'Disable Grade Encoding' : 'Enable Grade Encoding'; ?>
                    </button>
                </form>
                <div class="mt-2">
                    <span class="badge-status <?php echo $grade_encoding_enabled == '1' ? 'badge-enabled' : 'badge-disabled'; ?>">
                        <i class="fas <?php echo $grade_encoding_enabled == '1' ? 'fa-check-circle' : 'fa-times-circle'; ?> me-1"></i>
                        Grade Encoding is <?php echo $grade_encoding_enabled == '1' ? 'ENABLED' : 'DISABLED'; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Semester Settings Card -->
    <div class="content-card">
        <h5><i class="fas fa-calendar-alt me-2 text-primary"></i>Semester Settings</h5>
        <form method="POST" class="row g-3 mt-2">
            <input type="hidden" name="action" value="update_semester">
            <div class="col-md-3">
                <label class="form-label">School Year</label>
                <input type="text" name="school_year" class="form-control" value="<?php echo htmlspecialchars($current_school_year); ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Semester</label>
                <select name="semester" class="form-select" required>
                    <option value="1" <?php echo $current_semester == '1' ? 'selected' : ''; ?>>1st Semester</option>
                    <option value="2" <?php echo $current_semester == '2' ? 'selected' : ''; ?>>2nd Semester</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Semester Status</label>
                <select name="semester_status" class="form-select" required>
                    <option value="ongoing" <?php echo $semester_status == 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                    <option value="completed" <?php echo $semester_status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="on_hold" <?php echo $semester_status == 'on_hold' ? 'selected' : ''; ?>>On Hold</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-save me-2"></i>Update Settings
                </button>
            </div>
        </form>
    </div>

    <!-- Statistics Row -->
    <div class="row">
        <div class="col-md-3">
            <div class="stat-box">
                <div class="stat-number" style="color: var(--primary);"><?php echo number_format($grade_stats['total_grades'] ?? 0); ?></div>
                <div class="stat-label">Total Grades Recorded</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-box">
                <div class="stat-number" style="color: var(--success);"><?php echo number_format($grade_stats['passed'] ?? 0); ?></div>
                <div class="stat-label">Passed Subjects</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-box">
                <div class="stat-number" style="color: var(--danger);"><?php echo number_format($grade_stats['failed'] ?? 0); ?></div>
                <div class="stat-label">Failed Subjects</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-box">
                <div class="stat-number" style="color: var(--warning);"><?php echo number_format($grade_stats['average_grade'] ?? 0, 2); ?></div>
                <div class="stat-label">Average Grade</div>
            </div>
        </div>
    </div>

    <!-- Bulk Grade Entry Card -->
    <div class="content-card">
        <h5><i class="fas fa-edit me-2 text-primary"></i>Bulk Grade Entry</h5>
        <p class="text-muted">Enter grades for all students in a subject at once</p>
        
        <form method="POST" id="bulkGradeForm">
            <input type="hidden" name="action" value="bulk_update_grades">
            
            <div class="row mb-4">
                <div class="col-md-6">
                    <label class="form-label">Select Subject</label>
                    <select name="subject_id" class="form-select" id="subjectSelect" required>
                        <option value="">Select Subject</option>
                        <?php foreach($subjects as $subject): ?>
                            <option value="<?php echo $subject['id']; ?>" <?php echo $selected_subject == $subject['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['subject_code']); ?> - <?php echo htmlspecialchars($subject['descriptive_title']); ?> 
                                (<?php echo $subject['course']; ?> - Year <?php echo $subject['year_level']; ?>, <?php echo $subject['semester'] == 1 ? '1st' : '2nd'; ?> Sem)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <div class="action-buttons">
                        <button type="button" class="btn btn-success" onclick="fillAllPassed()">
                            <i class="fas fa-check-circle me-1"></i>Set All Passed (3.00)
                        </button>
                        <button type="button" class="btn btn-danger" onclick="fillAllFailed()">
                            <i class="fas fa-times-circle me-1"></i>Set All Failed (5.00)
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="clearAllGrades()">
                            <i class="fas fa-eraser me-1"></i>Clear All
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-bordered table-grades">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>Course</th>
                            <th>Year/Section</th>
                            <th>Current Grade</th>
                            <th>New Grade</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($selected_subject && $student_grades): ?>
                            <?php foreach($student_grades as $student): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['user_id']); ?></td>
                                    <td><?php echo htmlspecialchars($student['fullname']); ?></td>
                                    <td><?php echo $student['course']; ?></td>
                                    <td><?php echo $student['year_level'] . '/' . $student['section']; ?></td>
                                    <td>
                                        <?php if($student['grade']): ?>
                                            <span class="<?php echo $student['grade_status'] == 'PASSED' ? 'grade-passed' : 'grade-failed'; ?> px-2 py-1 rounded">
                                                <?php echo number_format($student['grade'], 2); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="1.00" max="5.00" 
                                               name="grades[<?php echo $student['user_id']; ?>]" 
                                               class="form-control grade-input" 
                                               placeholder="1.00-5.00">
                                    </td>
                                    <td id="status_<?php echo $student['user_id']; ?>" class="text-center">
                                        <span class="text-muted">—</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <?php if(!$selected_subject): ?>
                                        Please select a subject to enter grades.
                                    <?php else: ?>
                                        No students found.
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if($selected_subject && $student_grades): ?>
                <div class="text-end mt-3">
                    <button type="submit" class="btn btn-save">
                        <i class="fas fa-save me-2"></i>Save All Grades
                    </button>
                    <button type="button" class="btn btn-danger ms-2" data-bs-toggle="modal" data-bs-target="#resetSubjectModal">
                        <i class="fas fa-trash-alt me-2"></i>Reset All Grades
                    </button>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Announcement Card -->
    <div class="content-card">
        <h5><i class="fas fa-bullhorn me-2 text-primary"></i>Post Announcement</h5>
        <form method="POST">
            <input type="hidden" name="action" value="send_announcement">
            <div class="mb-3">
                <textarea name="announcement" class="form-control" rows="3" placeholder="Enter announcement for students..."><?php 
                    $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'announcement'");
                    $ann = $stmt->fetch();
                    echo htmlspecialchars($ann['setting_value'] ?? '');
                ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-paper-plane me-2"></i>Post Announcement
            </button>
        </form>
    </div>

    <!-- Recent Grade Submissions -->
    <div class="content-card">
        <h5><i class="fas fa-history me-2 text-primary"></i>Recent Grade Submissions</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Student</th>
                        <th>Subject</th>
                        <th>Grade</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($recent_grades as $grade): ?>
                        <tr>
                            <td><?php echo date('M d, Y h:i A', strtotime($grade['date_encoded'])); ?></td>
                            <td><?php echo htmlspecialchars($grade['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($grade['subject_code']); ?> - <?php echo htmlspecialchars($grade['descriptive_title']); ?></td>
                            <td class="fw-bold"><?php echo number_format($grade['grade'], 2); ?></td>
                            <td>
                                <span class="badge-status <?php echo $grade['status'] == 'PASSED' ? 'badge-enabled' : 'badge-disabled'; ?>">
                                    <?php echo $grade['status']; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <?php if(empty($recent_grades)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-3">No grade submissions yet</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Reset Subject Grades Modal -->
<div class="modal fade" id="resetSubjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Reset Subject Grades</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="reset_subject_grades">
                    <input type="hidden" name="subject_id" value="<?php echo $selected_subject; ?>">
                    <p>Are you sure you want to reset all grades for this subject?</p>
                    <p class="text-danger">This action cannot be undone!</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Yes, Reset All Grades</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto-update status preview when grade is entered
    document.querySelectorAll('.grade-input').forEach(input => {
        input.addEventListener('input', function() {
            const grade = parseFloat(this.value);
            const statusSpan = document.getElementById('status_' + this.name.match(/\d+/)[0]);
            
            if (isNaN(grade)) {
                statusSpan.innerHTML = '<span class="text-muted">—</span>';
            } else if (grade >= 1.00 && grade <= 3.00) {
                statusSpan.innerHTML = '<span class="badge-status badge-enabled">PASSED</span>';
            } else if (grade > 3.00 && grade <= 5.00) {
                statusSpan.innerHTML = '<span class="badge-status badge-disabled">FAILED</span>';
            } else {
                statusSpan.innerHTML = '<span class="text-danger">Invalid</span>';
            }
        });
    });
    
    // Fill all grades with PASSED (3.00)
    function fillAllPassed() {
        document.querySelectorAll('.grade-input').forEach(input => {
            input.value = '3.00';
            input.dispatchEvent(new Event('input'));
        });
    }
    
    // Fill all grades with FAILED (5.00)
    function fillAllFailed() {
        document.querySelectorAll('.grade-input').forEach(input => {
            input.value = '5.00';
            input.dispatchEvent(new Event('input'));
        });
    }
    
    // Clear all grades
    function clearAllGrades() {
        document.querySelectorAll('.grade-input').forEach(input => {
            input.value = '';
            input.dispatchEvent(new Event('input'));
        });
    }
    
    // Reload page when subject changes
    document.getElementById('subjectSelect').addEventListener('change', function() {
        if (this.value) {
            window.location.href = '?subject_id=' + this.value;
        }
    });
    
    // Confirmation before saving
    document.getElementById('bulkGradeForm').addEventListener('submit', function(e) {
        const hasGrades = Array.from(document.querySelectorAll('.grade-input')).some(input => input.value !== '');
        if (hasGrades && !confirm('Are you sure you want to save these grades?')) {
            e.preventDefault();
        }
    });
</script>
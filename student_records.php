<?php
require_once '../includes/auth.php';
requireRole('admin');

// Handle grade override or status update
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_grade') {
        $grade_id = $_POST['grade_id'];
        $grade = $_POST['grade'];
        $status = ($grade >= 1.00 && $grade <= 3.00) ? 'PASSED' : 'FAILED';
        
        $stmt = $pdo->prepare("UPDATE grades SET grade = ?, status = ? WHERE id = ?");
        if ($stmt->execute([$grade, $status, $grade_id])) {
            $message = "Grade updated successfully!";
        } else {
            $error = "Failed to update grade.";
        }
    }
    
    if ($action === 'update_status') {
        $student_id = $_POST['student_id'];
        $status = $_POST['status'];
        
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE user_id = ? AND role = 'student'");
        if ($stmt->execute([$status, $student_id])) {
            $message = "Student status updated successfully!";
        } else {
            $error = "Failed to update status.";
        }
    }
    
    if ($action === 'reset_password') {
        $student_id = $_POST['student_id'];
        $new_password = password_hash('student123', PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ? AND role = 'student'");
        if ($stmt->execute([$new_password, $student_id])) {
            $message = "Password reset to: student123";
        } else {
            $error = "Failed to reset password.";
        }
    }
}

// Get search parameters
$search = $_GET['search'] ?? '';
$course_filter = $_GET['course'] ?? '';
$year_filter = $_GET['year'] ?? '';
$section_filter = $_GET['section'] ?? '';

// Build query
$query = "SELECT * FROM users WHERE role = 'student'";
$params = [];

if (!empty($search)) {
    $query .= " AND (user_id LIKE ? OR fullname LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}
if (!empty($course_filter)) {
    $query .= " AND course = ?";
    $params[] = $course_filter;
}
if (!empty($year_filter)) {
    $query .= " AND year_level = ?";
    $params[] = $year_filter;
}
if (!empty($section_filter)) {
    $query .= " AND section = ?";
    $params[] = $section_filter;
}

$query .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Get selected student details
$selected_student = $_GET['student_id'] ?? ($students[0]['user_id'] ?? '');
$student_details = null;
$student_grades = [];
$student_subjects = [];

if ($selected_student) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? AND role = 'student'");
    $stmt->execute([$selected_student]);
    $student_details = $stmt->fetch();
    
    if ($student_details) {
        // Get all subjects for this student's course
        $stmt = $pdo->prepare("
            SELECT s.*, 
                   g.id as grade_id, g.grade, g.status as grade_status, g.date_encoded
            FROM subjects s
            LEFT JOIN grades g ON s.id = g.subject_id AND g.student_id = ?
            WHERE s.course = ?
            ORDER BY s.year_level, s.semester, s.subject_code
        ");
        $stmt->execute([$selected_student, $student_details['course']]);
        $student_subjects = $stmt->fetchAll();
        
        // Calculate statistics
        $total_subjects = count($student_subjects);
        $passed = 0;
        $failed = 0;
        $pending = 0;
        $total_units = 0;
        $earned_units = 0;
        
        foreach ($student_subjects as $subject) {
            $total_units += $subject['units'];
            if ($subject['grade_status'] == 'PASSED') {
                $passed++;
                $earned_units += $subject['units'];
            } elseif ($subject['grade_status'] == 'FAILED') {
                $failed++;
            } elseif ($subject['grade_status'] == 'PENDING') {
                $pending++;
            }
        }
        
        $year_standing = $total_subjects > 0 ? ($passed / $total_subjects) * 100 : 0;
    }
}

// Get unique sections and years for filters
$sections = $pdo->query("SELECT DISTINCT section FROM users WHERE role = 'student' AND section IS NOT NULL ORDER BY section")->fetchAll();
$years = $pdo->query("SELECT DISTINCT year_level FROM users WHERE role = 'student' ORDER BY year_level")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Records - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
        }
        .content-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .student-card {
            background: white;
            border-radius: 12px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .student-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .student-card.active {
            border-color: var(--primary);
            background: linear-gradient(135deg, #667eea10, #764ba210);
        }
        .student-name {
            font-weight: 600;
            color: #2d3748;
        }
        .student-id {
            font-size: 12px;
            color: #718096;
        }
        .status-badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        .grade-passed {
            background: #d4edda;
            color: #155724;
        }
        .grade-failed {
            background: #f8d7da;
            color: #721c24;
        }
        .grade-pending {
            background: #fff3cd;
            color: #856404;
        }
        .filter-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .btn-print {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            padding: 8px 20px;
            border-radius: 25px;
            color: white;
        }
        .student-list {
            max-height: 600px;
            overflow-y: auto;
        }
        .info-label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .info-value {
            font-size: 16px;
            font-weight: 600;
            color: #2d3748;
        }
        .stat-box {
            background: #f7fafc;
            border-radius: 12px;
            padding: 12px;
            text-align: center;
        }
        .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
        }
        .subjects-table th {
            background: #f8f9fa;
            font-size: 13px;
        }
        .subjects-table td {
            font-size: 13px;
            vertical-align: middle;
        }
        @media print {
            .sidebar, .btn-print, .filter-section, .student-list, .action-buttons, .no-print {
                display: none !important;
            }
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            .content-card {
                box-shadow: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>

<div class="container-fluid">
    <div class="content-card">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-users me-2 text-primary"></i>Student Records</h2>
            <button class="btn-print" onclick="window.print()">
                <i class="fas fa-print me-2"></i>Print Report
            </button>
        </div>

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

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Name, ID, or Email..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Course</label>
                    <select name="course" class="form-select">
                        <option value="">All Courses</option>
                        <option value="CE" <?php echo $course_filter == 'CE' ? 'selected' : ''; ?>>Civil Engineering</option>
                        <option value="CpE" <?php echo $course_filter == 'CpE' ? 'selected' : ''; ?>>Computer Engineering</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Year Level</label>
                    <select name="year" class="form-select">
                        <option value="">All Years</option>
                        <?php foreach($years as $year): ?>
                            <option value="<?php echo $year['year_level']; ?>" <?php echo $year_filter == $year['year_level'] ? 'selected' : ''; ?>>
                                <?php echo $year['year_level']; ?> Year
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Section</label>
                    <select name="section" class="form-select">
                        <option value="">All Sections</option>
                        <?php foreach($sections as $section): ?>
                            <option value="<?php echo htmlspecialchars($section['section']); ?>" <?php echo $section_filter == $section['section'] ? 'selected' : ''; ?>>
                                Section <?php echo htmlspecialchars($section['section']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i>Filter
                    </button>
                </div>
            </form>
        </div>

        <div class="row">
            <!-- Student List Sidebar -->
            <div class="col-md-4">
                <div class="student-list">
                    <h6 class="mb-3">Students (<?php echo count($students); ?>)</h6>
                    <?php foreach($students as $student): ?>
                        <div class="student-card <?php echo $selected_student == $student['user_id'] ? 'active' : ''; ?>" 
                             onclick="location.href='?student_id=<?php echo urlencode($student['user_id']); ?>&<?php echo http_build_query($_GET); ?>'">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="student-name"><?php echo htmlspecialchars($student['fullname']); ?></div>
                                    <div class="student-id">ID: <?php echo htmlspecialchars($student['user_id']); ?></div>
                                    <div class="small text-muted">
                                        <?php echo $student['course']; ?> - Year <?php echo $student['year_level']; ?> - Section <?php echo htmlspecialchars($student['section']); ?>
                                    </div>
                                </div>
                                <div>
                                    <span class="status-badge <?php echo $student['status'] == 'active' ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo ucfirst($student['status']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if(empty($students)): ?>
                        <p class="text-muted text-center py-3">No students found.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Student Details -->
            <div class="col-md-8">
                <?php if($student_details): ?>
                    <div class="content-card">
                        <div class="d-flex justify-content-between align-items-start mb-4">
                            <h4>
                                <i class="fas fa-user-graduate me-2 text-primary"></i>
                                Student Information
                            </h4>
                            <div class="action-buttons">
                                <button class="btn btn-sm btn-warning" onclick="resetPassword('<?php echo $student_details['user_id']; ?>', '<?php echo htmlspecialchars($student_details['fullname']); ?>')">
                                    <i class="fas fa-key me-1"></i>Reset Password
                                </button>
                                <button class="btn btn-sm btn-primary" onclick="editStatus('<?php echo $student_details['user_id']; ?>', '<?php echo $student_details['status']; ?>', '<?php echo htmlspecialchars($student_details['fullname']); ?>')">
                                    <i class="fas fa-edit me-1"></i>Update Status
                                </button>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="info-label">Student ID</div>
                                <div class="info-value"><?php echo htmlspecialchars($student_details['user_id']); ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Full Name</div>
                                <div class="info-value"><?php echo htmlspecialchars($student_details['fullname']); ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Email</div>
                                <div class="info-value"><?php echo htmlspecialchars($student_details['email']); ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Course</div>
                                <div class="info-value"><?php echo $student_details['course'] == 'CE' ? 'Civil Engineering' : 'Computer Engineering'; ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Year Level & Section</div>
                                <div class="info-value">Year <?php echo $student_details['year_level']; ?> - Section <?php echo htmlspecialchars($student_details['section']); ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Status</div>
                                <div class="info-value">
                                    <span class="status-badge <?php echo $student_details['status'] == 'active' ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo ucfirst($student_details['status']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Enrolled Since</div>
                                <div class="info-value"><?php echo date('F d, Y', strtotime($student_details['created_at'])); ?></div>
                            </div>
                        </div>
                        
                        <!-- Statistics -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="stat-box">
                                    <div class="stat-number"><?php echo $total_subjects; ?></div>
                                    <div class="small text-muted">Total Subjects</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-box">
                                    <div class="stat-number text-success"><?php echo $passed; ?></div>
                                    <div class="small text-muted">Passed</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-box">
                                    <div class="stat-number text-danger"><?php echo $failed; ?></div>
                                    <div class="small text-muted">Failed</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-box">
                                    <div class="stat-number"><?php echo round($year_standing); ?>%</div>
                                    <div class="small text-muted">Year Standing</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Subjects and Grades -->
                        <h5 class="mb-3"><i class="fas fa-book me-2 text-primary"></i>Subjects and Grades</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered subjects-table">
                                <thead>
                                    <tr>
                                        <th>Subject Code</th>
                                        <th>Descriptive Title</th>
                                        <th>Lec</th>
                                        <th>Lab</th>
                                        <th>Units</th>
                                        <th>Year/Sem</th>
                                        <th>Grade</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($student_subjects as $subject): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($subject['subject_code']); ?></td>
                                            <td><?php echo htmlspecialchars($subject['descriptive_title']); ?></td>
                                            <td class="text-center"><?php echo $subject['lec']; ?></td>
                                            <td class="text-center"><?php echo $subject['lab']; ?></td>
                                            <td class="text-center"><?php echo $subject['units']; ?></td>
                                            <td class="text-center"><?php echo $subject['year_level'] . '/' . ($subject['semester'] == 1 ? '1st' : '2nd'); ?></td>
                                            <td class="text-center">
                                                <?php if($subject['grade']): ?>
                                                    <span class="fw-bold"><?php echo number_format($subject['grade'], 2); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if($subject['grade_status'] == 'PASSED'): ?>
                                                    <span class="status-badge grade-passed">PASSED</span>
                                                <?php elseif($subject['grade_status'] == 'FAILED'): ?>
                                                    <span class="status-badge grade-failed">FAILED</span>
                                                <?php else: ?>
                                                    <span class="status-badge grade-pending">PENDING</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-primary btn-action" onclick="editGrade(<?php echo $subject['id']; ?>, '<?php echo htmlspecialchars($subject['subject_code']); ?>', '<?php echo $subject['grade'] ?? ''; ?>')">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="content-card text-center py-5">
                        <i class="fas fa-user-graduate fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Select a student from the left to view their records.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Edit Grade Modal -->
<div class="modal fade" id="editGradeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Grade</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_grade">
                    <input type="hidden" name="grade_id" id="gradeId">
                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <input type="text" id="gradeSubject" class="form-control" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Grade <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="1.00" max="5.00" name="grade" id="gradeValue" class="form-control" required>
                        <small class="text-muted">1.00 - 3.00 = PASSED | 3.01 - 5.00 = FAILED</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Grade</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-edit me-2"></i>Update Student Status</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="student_id" id="statusStudentId">
                    <div class="mb-3">
                        <label class="form-label">Student</label>
                        <input type="text" id="statusStudentName" class="form-control" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status <span class="text-danger">*</span></label>
                        <select name="status" id="statusValue" class="form-select" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="fas fa-key me-2"></i>Reset Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Reset password for <strong id="resetStudentName"></strong>?</p>
                <p>Default password will be set to: <code>student123</code></p>
            </div>
            <div class="modal-footer">
                <form method="POST">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="student_id" id="resetStudentId">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Reset Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function editGrade(subjectId, subjectCode, currentGrade) {
        document.getElementById('gradeId').value = subjectId;
        document.getElementById('gradeSubject').value = subjectCode;
        document.getElementById('gradeValue').value = currentGrade || '';
        new bootstrap.Modal(document.getElementById('editGradeModal')).show();
    }
    
    function editStatus(studentId, currentStatus, studentName) {
        document.getElementById('statusStudentId').value = studentId;
        document.getElementById('statusStudentName').value = studentName;
        document.getElementById('statusValue').value = currentStatus;
        new bootstrap.Modal(document.getElementById('statusModal')).show();
    }
    
    function resetPassword(studentId, studentName) {
        document.getElementById('resetStudentId').value = studentId;
        document.getElementById('resetStudentName').textContent = studentName;
        new bootstrap.Modal(document.getElementById('resetModal')).show();
    }
</script>
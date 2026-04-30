<?php
require_once '../includes/auth.php';
requireRole('admin');

// Get current tab from URL
$tab = $_GET['tab'] ?? 'students';

// Handle different operations
$message = '';
$error = '';

// =====================================================
// STUDENT MANAGEMENT
// =====================================================
if ($tab == 'students' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'add') {
        $user_id = trim($_POST['user_id'] ?? '');
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $course = $_POST['course'] ?? '';
        $section = trim($_POST['section'] ?? '');
        $year_level = $_POST['year_level'] ?? 1;
        $password = password_hash('student123', PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO users (user_id, fullname, email, password, role, course, section, year_level, status) VALUES (?, ?, ?, ?, 'student', ?, ?, ?, 'active')");
        if ($stmt->execute([$user_id, $fullname, $email, $password, $course, $section, $year_level])) {
            $message = "Student added successfully! Default password: student123";
        } else {
            $error = "Failed to add student. Student ID or Email may already exist.";
        }
    }
    
    if ($action == 'edit') {
        $id = $_POST['id'];
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $course = $_POST['course'] ?? '';
        $section = trim($_POST['section'] ?? '');
        $year_level = $_POST['year_level'] ?? 1;
        $status = $_POST['status'] ?? 'active';
        
        $stmt = $pdo->prepare("UPDATE users SET fullname = ?, email = ?, course = ?, section = ?, year_level = ?, status = ? WHERE id = ? AND role = 'student'");
        if ($stmt->execute([$fullname, $email, $course, $section, $year_level, $status, $id])) {
            $message = "Student updated successfully!";
        } else {
            $error = "Failed to update student.";
        }
    }
    
    if ($action == 'delete') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
        if ($stmt->execute([$id])) {
            $message = "Student deleted successfully!";
        } else {
            $error = "Failed to delete student.";
        }
    }
    
    if ($action == 'reset_password') {
        $id = $_POST['id'];
        $new_password = password_hash('student123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'student'");
        if ($stmt->execute([$new_password, $id])) {
            $message = "Password reset to: student123";
        } else {
            $error = "Failed to reset password.";
        }
    }
}

// =====================================================
// PROFESSOR MANAGEMENT
// =====================================================
if ($tab == 'professors' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'add') {
        $user_id = trim($_POST['user_id'] ?? '');
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = password_hash('prof123', PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO users (user_id, fullname, email, password, role, status) VALUES (?, ?, ?, ?, 'professor', 'active')");
        if ($stmt->execute([$user_id, $fullname, $email, $password])) {
            $message = "Professor added successfully! Default password: prof123";
        } else {
            $error = "Failed to add professor. Professor ID or Email may already exist.";
        }
    }
    
    if ($action == 'edit') {
        $id = $_POST['id'];
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $status = $_POST['status'] ?? 'active';
        
        $stmt = $pdo->prepare("UPDATE users SET fullname = ?, email = ?, status = ? WHERE id = ? AND role = 'professor'");
        if ($stmt->execute([$fullname, $email, $status, $id])) {
            $message = "Professor updated successfully!";
        } else {
            $error = "Failed to update professor.";
        }
    }
    
    if ($action == 'delete') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'professor'");
        if ($stmt->execute([$id])) {
            $message = "Professor deleted successfully!";
        } else {
            $error = "Failed to delete professor.";
        }
    }
    
    if ($action == 'reset_password') {
        $id = $_POST['id'];
        $new_password = password_hash('prof123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'professor'");
        if ($stmt->execute([$new_password, $id])) {
            $message = "Password reset to: prof123";
        } else {
            $error = "Failed to reset password.";
        }
    }
}

// =====================================================
// SUBJECT MANAGEMENT
// =====================================================
if ($tab == 'subjects' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'add') {
        $subject_code = trim($_POST['subject_code'] ?? '');
        $descriptive_title = trim($_POST['descriptive_title'] ?? '');
        $lec = intval($_POST['lec'] ?? 0);
        $lab = intval($_POST['lab'] ?? 0);
        $units = floatval($_POST['units'] ?? 0);
        $year_level = intval($_POST['year_level'] ?? 1);
        $semester = intval($_POST['semester'] ?? 1);
        $course = $_POST['course'] ?? '';
        
        $stmt = $pdo->prepare("INSERT INTO subjects (subject_code, descriptive_title, lec, lab, units, year_level, semester, course) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$subject_code, $descriptive_title, $lec, $lab, $units, $year_level, $semester, $course])) {
            $message = "Subject added successfully!";
        } else {
            $error = "Failed to add subject. Subject code may already exist.";
        }
    }
    
    if ($action == 'edit') {
        $id = $_POST['id'];
        $subject_code = trim($_POST['subject_code'] ?? '');
        $descriptive_title = trim($_POST['descriptive_title'] ?? '');
        $lec = intval($_POST['lec'] ?? 0);
        $lab = intval($_POST['lab'] ?? 0);
        $units = floatval($_POST['units'] ?? 0);
        $year_level = intval($_POST['year_level'] ?? 1);
        $semester = intval($_POST['semester'] ?? 1);
        $course = $_POST['course'] ?? '';
        
        $stmt = $pdo->prepare("UPDATE subjects SET subject_code = ?, descriptive_title = ?, lec = ?, lab = ?, units = ?, year_level = ?, semester = ?, course = ? WHERE id = ?");
        if ($stmt->execute([$subject_code, $descriptive_title, $lec, $lab, $units, $year_level, $semester, $course, $id])) {
            $message = "Subject updated successfully!";
        } else {
            $error = "Failed to update subject.";
        }
    }
    
    if ($action == 'delete') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
        if ($stmt->execute([$id])) {
            $message = "Subject deleted successfully!";
        } else {
            $error = "Failed to delete subject.";
        }
    }
}

// =====================================================
// PROSPECTUS MANAGEMENT
// =====================================================
if ($tab == 'prospectus' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'add') {
        $subject_code = trim($_POST['subject_code'] ?? '');
        $descriptive_title = trim($_POST['descriptive_title'] ?? '');
        $lec = intval($_POST['lec'] ?? 0);
        $lab = intval($_POST['lab'] ?? 0);
        $units = floatval($_POST['units'] ?? 0);
        $year_level = intval($_POST['year_level'] ?? 1);
        $semester = intval($_POST['semester'] ?? 1);
        $course = $_POST['course'] ?? '';
        $prerequisite_text = trim($_POST['prerequisite_text'] ?? '');
        
        $stmt = $pdo->prepare("INSERT INTO prospectus (subject_code, descriptive_title, lec, lab, units, year_level, semester, course, prerequisite_text) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$subject_code, $descriptive_title, $lec, $lab, $units, $year_level, $semester, $course, $prerequisite_text])) {
            $message = "Prospectus entry added successfully!";
        } else {
            $error = "Failed to add prospectus entry.";
        }
    }
    
    if ($action == 'edit') {
        $id = $_POST['id'];
        $subject_code = trim($_POST['subject_code'] ?? '');
        $descriptive_title = trim($_POST['descriptive_title'] ?? '');
        $lec = intval($_POST['lec'] ?? 0);
        $lab = intval($_POST['lab'] ?? 0);
        $units = floatval($_POST['units'] ?? 0);
        $year_level = intval($_POST['year_level'] ?? 1);
        $semester = intval($_POST['semester'] ?? 1);
        $course = $_POST['course'] ?? '';
        $prerequisite_text = trim($_POST['prerequisite_text'] ?? '');
        
        $stmt = $pdo->prepare("UPDATE prospectus SET subject_code = ?, descriptive_title = ?, lec = ?, lab = ?, units = ?, year_level = ?, semester = ?, course = ?, prerequisite_text = ? WHERE id = ?");
        if ($stmt->execute([$subject_code, $descriptive_title, $lec, $lab, $units, $year_level, $semester, $course, $prerequisite_text, $id])) {
            $message = "Prospectus entry updated successfully!";
        } else {
            $error = "Failed to update prospectus entry.";
        }
    }
    
    if ($action == 'delete') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM prospectus WHERE id = ?");
        if ($stmt->execute([$id])) {
            $message = "Prospectus entry deleted successfully!";
        } else {
            $error = "Failed to delete prospectus entry.";
        }
    }
}

// =====================================================
// COURSE MANAGEMENT
// =====================================================
// Courses are static (CE, CpE) - no CRUD needed

// Fetch data for display
$students = $pdo->query("SELECT * FROM users WHERE role = 'student' ORDER BY created_at DESC")->fetchAll();
$professors = $pdo->query("SELECT * FROM users WHERE role = 'professor' ORDER BY created_at DESC")->fetchAll();
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY course, year_level, semester, subject_code")->fetchAll();
$prospectus = $pdo->query("SELECT * FROM prospectus ORDER BY course, year_level, semester, id")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Management - Admin Dashboard</title>
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
        .nav-tabs-custom {
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 25px;
        }
        .nav-tabs-custom .nav-link {
            border: none;
            color: #718096;
            font-weight: 600;
            padding: 12px 25px;
            position: relative;
        }
        .nav-tabs-custom .nav-link:hover {
            color: var(--primary);
            background: transparent;
        }
        .nav-tabs-custom .nav-link.active {
            color: var(--primary);
            background: transparent;
        }
        .nav-tabs-custom .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 3px;
        }
        .btn-action {
            padding: 5px 12px;
            margin: 0 3px;
            border-radius: 8px;
            font-size: 12px;
        }
        .table-responsive {
            max-height: 500px;
            overflow-y: auto;
        }
        .modal-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        .search-box {
            margin-bottom: 20px;
        }
        .badge-status {
            padding: 5px 12px;
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
        .units-total {
            background: #f8f9fa;
            font-weight: bold;
        }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>

<div class="container-fluid">
    <div class="content-card">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-database me-2 text-primary"></i>Data Management</h2>
            <div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal" id="addButton">
                    <i class="fas fa-plus me-2"></i>Add New
                </button>
            </div>
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

        <!-- Tabs -->
        <ul class="nav nav-tabs-custom">
            <li class="nav-item">
                <a class="nav-link <?php echo $tab == 'students' ? 'active' : ''; ?>" href="?tab=students">
                    <i class="fas fa-user-graduate me-2"></i>Students
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $tab == 'professors' ? 'active' : ''; ?>" href="?tab=professors">
                    <i class="fas fa-chalkboard-teacher me-2"></i>Professors
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $tab == 'subjects' ? 'active' : ''; ?>" href="?tab=subjects">
                    <i class="fas fa-book me-2"></i>Subjects
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $tab == 'prospectus' ? 'active' : ''; ?>" href="?tab=prospectus">
                    <i class="fas fa-list-alt me-2"></i>Prospectus
                </a>
            </li>
        </ul>

        <!-- Search Box -->
        <div class="search-box">
            <div class="input-group" style="max-width: 300px;">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" id="searchInput" class="form-control" placeholder="Search...">
            </div>
        </div>

        <!-- ===================================================== -->
        <!-- STUDENTS TABLE -->
        <!-- ===================================================== -->
        <?php if($tab == 'students'): ?>
            <div class="table-responsive">
                <table class="table table-hover" id="dataTable">
                    <thead class="table-light">
                        <tr>
                            <th>Student ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Course</th>
                            <th>Section</th>
                            <th>Year Level</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($students as $student): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['user_id']); ?></td>
                                <td><?php echo htmlspecialchars($student['fullname']); ?></td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td><?php echo $student['course']; ?></td>
                                <td><?php echo $student['section']; ?></td>
                                <td class="text-center"><?php echo $student['year_level']; ?></td>
                                <td>
                                    <span class="badge-status <?php echo $student['status'] == 'active' ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo ucfirst($student['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary btn-action" onclick="editStudent(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['user_id']); ?>', '<?php echo htmlspecialchars($student['fullname']); ?>', '<?php echo htmlspecialchars($student['email']); ?>', '<?php echo $student['course']; ?>', '<?php echo $student['section']; ?>', <?php echo $student['year_level']; ?>, '<?php echo $student['status']; ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger btn-action" onclick="deleteItem('student', <?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['fullname']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <button class="btn btn-sm btn-warning btn-action" onclick="resetPassword('student', <?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['fullname']); ?>')">
                                        <i class="fas fa-key"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- ===================================================== -->
        <!-- PROFESSORS TABLE -->
        <!-- ===================================================== -->
        <?php if($tab == 'professors'): ?>
            <div class="table-responsive">
                <table class="table table-hover" id="dataTable">
                    <thead class="table-light">
                        <tr>
                            <th>Professor ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($professors as $professor): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($professor['user_id']); ?></td>
                                <td><?php echo htmlspecialchars($professor['fullname']); ?></td>
                                <td><?php echo htmlspecialchars($professor['email']); ?></td>
                                <td>
                                    <span class="badge-status <?php echo $professor['status'] == 'active' ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo ucfirst($professor['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary btn-action" onclick="editProfessor(<?php echo $professor['id']; ?>, '<?php echo htmlspecialchars($professor['fullname']); ?>', '<?php echo htmlspecialchars($professor['email']); ?>', '<?php echo $professor['status']; ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger btn-action" onclick="deleteItem('professor', <?php echo $professor['id']; ?>, '<?php echo htmlspecialchars($professor['fullname']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <button class="btn btn-sm btn-warning btn-action" onclick="resetPassword('professor', <?php echo $professor['id']; ?>, '<?php echo htmlspecialchars($professor['fullname']); ?>')">
                                        <i class="fas fa-key"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- ===================================================== -->
        <!-- SUBJECTS TABLE -->
        <!-- ===================================================== -->
        <?php if($tab == 'subjects'): ?>
            <div class="table-responsive">
                <table class="table table-hover" id="dataTable">
                    <thead class="table-light">
                        <tr>
                            <th>Subject Code</th>
                            <th>Descriptive Title</th>
                            <th>Lec</th>
                            <th>Lab</th>
                            <th>Units</th>
                            <th>Year</th>
                            <th>Sem</th>
                            <th>Course</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $totalLec = 0;
                        $totalLab = 0;
                        $totalUnits = 0;
                        foreach($subjects as $subject): 
                            $totalLec += $subject['lec'];
                            $totalLab += $subject['lab'];
                            $totalUnits += $subject['units'];
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($subject['subject_code']); ?></td>
                                <td><?php echo htmlspecialchars($subject['descriptive_title']); ?></td>
                                <td class="text-center"><?php echo $subject['lec']; ?></td>
                                <td class="text-center"><?php echo $subject['lab']; ?></td>
                                <td class="text-center"><?php echo $subject['units']; ?></td>
                                <td class="text-center"><?php echo $subject['year_level']; ?></td>
                                <td class="text-center"><?php echo $subject['semester'] == 1 ? '1st' : '2nd'; ?></td>
                                <td><?php echo $subject['course']; ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary btn-action" onclick="editSubject(<?php echo $subject['id']; ?>, '<?php echo htmlspecialchars($subject['subject_code']); ?>', '<?php echo htmlspecialchars($subject['descriptive_title']); ?>', <?php echo $subject['lec']; ?>, <?php echo $subject['lab']; ?>, <?php echo $subject['units']; ?>, <?php echo $subject['year_level']; ?>, <?php echo $subject['semester']; ?>, '<?php echo $subject['course']; ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger btn-action" onclick="deleteItem('subject', <?php echo $subject['id']; ?>, '<?php echo htmlspecialchars($subject['subject_code']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="units-total">
                        <tr>
                            <td colspan="2" class="text-end fw-bold">TOTAL:</td>
                            <td class="text-center fw-bold"><?php echo $totalLec; ?></td>
                            <td class="text-center fw-bold"><?php echo $totalLab; ?></td>
                            <td class="text-center fw-bold"><?php echo $totalUnits; ?></td>
                            <td colspan="4"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>

        <!-- ===================================================== -->
        <!-- PROSPECTUS TABLE -->
        <!-- ===================================================== -->
        <?php if($tab == 'prospectus'): ?>
            <div class="table-responsive">
                <table class="table table-hover" id="dataTable">
                    <thead class="table-light">
                        <tr>
                            <th>Subject Code</th>
                            <th>Descriptive Title</th>
                            <th>Lec</th>
                            <th>Lab</th>
                            <th>Units</th>
                            <th>Year</th>
                            <th>Sem</th>
                            <th>Course</th>
                            <th>Pre-requisite(s)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $totalLec = 0;
                        $totalLab = 0;
                        $totalUnits = 0;
                        foreach($prospectus as $item): 
                            $totalLec += $item['lec'];
                            $totalLab += $item['lab'];
                            $totalUnits += $item['units'];
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['subject_code']); ?></td>
                                <td><?php echo htmlspecialchars($item['descriptive_title']); ?></td>
                                <td class="text-center"><?php echo $item['lec']; ?></td>
                                <td class="text-center"><?php echo $item['lab']; ?></td>
                                <td class="text-center"><?php echo $item['units']; ?></td>
                                <td class="text-center"><?php echo $item['year_level']; ?></td>
                                <td class="text-center"><?php echo $item['semester'] == 1 ? '1st' : '2nd'; ?></td>
                                <td><?php echo $item['course']; ?></td>
                                <td><?php echo htmlspecialchars($item['prerequisite_text'] ?: '-'); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary btn-action" onclick="editProspectus(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['subject_code']); ?>', '<?php echo htmlspecialchars($item['descriptive_title']); ?>', <?php echo $item['lec']; ?>, <?php echo $item['lab']; ?>, <?php echo $item['units']; ?>, <?php echo $item['year_level']; ?>, <?php echo $item['semester']; ?>, '<?php echo $item['course']; ?>', '<?php echo htmlspecialchars($item['prerequisite_text']); ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger btn-action" onclick="deleteItem('prospectus', <?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['subject_code']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="units-total">
                        <tr>
                            <td colspan="2" class="text-end fw-bold">TOTAL:</td>
                            <td class="text-center fw-bold"><?php echo $totalLec; ?></td>
                            <td class="text-center fw-bold"><?php echo $totalLab; ?></td>
                            <td class="text-center fw-bold"><?php echo $totalUnits; ?></td>
                            <td colspan="5"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===================================================== -->
<!-- ADD MODAL -->
<!-- ===================================================== -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add New <span id="modalTitle"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addForm">
                <div class="modal-body" id="addModalBody">
                    <!-- Dynamic content based on tab -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===================================================== -->
<!-- EDIT MODAL -->
<!-- ===================================================== -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit <span id="editModalTitle"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId">
                <div class="modal-body" id="editModalBody">
                    <!-- Dynamic content based on tab -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===================================================== -->
<!-- DELETE CONFIRM MODAL -->
<!-- ===================================================== -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="deleteItemName"></strong>?</p>
                <p class="text-danger">This action cannot be undone!</p>
            </div>
            <div class="modal-footer">
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteId">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ===================================================== -->
<!-- RESET PASSWORD MODAL -->
<!-- ===================================================== -->
<div class="modal fade" id="resetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="fas fa-key me-2"></i>Reset Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Reset password for <strong id="resetItemName"></strong>?</p>
                <p>Default password will be set to: <code id="defaultPassword"></code></p>
            </div>
            <div class="modal-footer">
                <form method="POST" id="resetForm">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="id" id="resetId">
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
    const currentTab = '<?php echo $tab; ?>';
    
    // Set modal title and body based on tab
    document.getElementById('addButton').onclick = function() {
        const modalTitle = document.getElementById('modalTitle');
        const modalBody = document.getElementById('addModalBody');
        
        if (currentTab === 'students') {
            modalTitle.textContent = 'Student';
            modalBody.innerHTML = `
                <div class="mb-3">
                    <label class="form-label">Student ID</label>
                    <input type="text" name="user_id" class="form-control" required>
                    <small class="text-muted">Only numbers, 4-15 digits</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="fullname" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label">Course</label>
                        <select name="course" class="form-select" required>
                            <option value="CE">Civil Engineering (CE)</option>
                            <option value="CpE">Computer Engineering (CpE)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Section</label>
                        <select name="section" class="form-select" required>
                            <option value="A">A</option>
                            <option value="B">B</option>
                            <option value="C">C</option>
                            <option value="D">D</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3 mt-3">
                    <label class="form-label">Year Level</label>
                    <select name="year_level" class="form-select" required>
                        <option value="1">1st Year</option>
                        <option value="2">2nd Year</option>
                        <option value="3">3rd Year</option>
                        <option value="4">4th Year</option>
                    </select>
                </div>
                <input type="hidden" name="action" value="add">
            `;
        } else if (currentTab === 'professors') {
            modalTitle.textContent = 'Professor';
            modalBody.innerHTML = `
                <div class="mb-3">
                    <label class="form-label">Professor ID</label>
                    <input type="text" name="user_id" class="form-control" required>
                    <small class="text-muted">Example: PROF001</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="fullname" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <input type="hidden" name="action" value="add">
            `;
        } else if (currentTab === 'subjects') {
            modalTitle.textContent = 'Subject';
            modalBody.innerHTML = `
                <div class="mb-3">
                    <label class="form-label">Subject Code</label>
                    <input type="text" name="subject_code" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Descriptive Title</label>
                    <input type="text" name="descriptive_title" class="form-control" required>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">Lecture Hours</label>
                        <input type="number" name="lec" class="form-control" value="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Lab Hours</label>
                        <input type="number" name="lab" class="form-control" value="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Units</label>
                        <input type="number" step="0.5" name="units" class="form-control" required>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-4">
                        <label class="form-label">Year Level</label>
                        <select name="year_level" class="form-select" required>
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Semester</label>
                        <select name="semester" class="form-select" required>
                            <option value="1">1st Semester</option>
                            <option value="2">2nd Semester</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Course</label>
                        <select name="course" class="form-select" required>
                            <option value="CE">Civil Engineering (CE)</option>
                            <option value="CpE">Computer Engineering (CpE)</option>
                        </select>
                    </div>
                </div>
                <input type="hidden" name="action" value="add">
            `;
        } else if (currentTab === 'prospectus') {
            modalTitle.textContent = 'Prospectus Entry';
            modalBody.innerHTML = `
                <div class="mb-3">
                    <label class="form-label">Subject Code</label>
                    <input type="text" name="subject_code" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Descriptive Title</label>
                    <input type="text" name="descriptive_title" class="form-control" required>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">Lecture Hours</label>
                        <input type="number" name="lec" class="form-control" value="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Lab Hours</label>
                        <input type="number" name="lab" class="form-control" value="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Units</label>
                        <input type="number" step="0.5" name="units" class="form-control" required>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-4">
                        <label class="form-label">Year Level</label>
                        <select name="year_level" class="form-select" required>
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Semester</label>
                        <select name="semester" class="form-select" required>
                            <option value="1">1st Semester</option>
                            <option value="2">2nd Semester</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Course</label>
                        <select name="course" class="form-select" required>
                            <option value="CE">Civil Engineering (CE)</option>
                            <option value="CpE">Computer Engineering (CpE)</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Pre-requisite(s)</label>
                    <input type="text" name="prerequisite_text" class="form-control" placeholder="e.g., Math 2E, CpE1">
                </div>
                <input type="hidden" name="action" value="add">
            `;
        }
    };
    
    // Edit Functions
    function editStudent(id, userId, fullname, email, course, section, yearLevel, status) {
        document.getElementById('editModalTitle').textContent = 'Student';
        document.getElementById('editId').value = id;
        const modalBody = document.getElementById('editModalBody');
        modalBody.innerHTML = `
            <div class="mb-3">
                <label class="form-label">Student ID</label>
                <input type="text" class="form-control" value="${userId}" disabled>
            </div>
            <div class="mb-3">
                <label class="form-label">Full Name</label>
                <input type="text" name="fullname" class="form-control" value="${fullname}" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="${email}" required>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label">Course</label>
                    <select name="course" class="form-select">
                        <option value="CE" ${course === 'CE' ? 'selected' : ''}>Civil Engineering (CE)</option>
                        <option value="CpE" ${course === 'CpE' ? 'selected' : ''}>Computer Engineering (CpE)</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Section</label>
                    <select name="section" class="form-select">
                        <option value="A" ${section === 'A' ? 'selected' : ''}>A</option>
                        <option value="B" ${section === 'B' ? 'selected' : ''}>B</option>
                        <option value="C" ${section === 'C' ? 'selected' : ''}>C</option>
                        <option value="D" ${section === 'D' ? 'selected' : ''}>D</option>
                    </select>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-md-6">
                    <label class="form-label">Year Level</label>
                    <select name="year_level" class="form-select">
                        <option value="1" ${yearLevel == 1 ? 'selected' : ''}>1st Year</option>
                        <option value="2" ${yearLevel == 2 ? 'selected' : ''}>2nd Year</option>
                        <option value="3" ${yearLevel == 3 ? 'selected' : ''}>3rd Year</option>
                        <option value="4" ${yearLevel == 4 ? 'selected' : ''}>4th Year</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" ${status === 'active' ? 'selected' : ''}>Active</option>
                        <option value="inactive" ${status === 'inactive' ? 'selected' : ''}>Inactive</option>
                    </select>
                </div>
            </div>
        `;
        new bootstrap.Modal(document.getElementById('editModal')).show();
    }
    
    function editProfessor(id, fullname, email, status) {
        document.getElementById('editModalTitle').textContent = 'Professor';
        document.getElementById('editId').value = id;
        const modalBody = document.getElementById('editModalBody');
        modalBody.innerHTML = `
            <div class="mb-3">
                <label class="form-label">Full Name</label>
                <input type="text" name="fullname" class="form-control" value="${fullname}" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="${email}" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="active" ${status === 'active' ? 'selected' : ''}>Active</option>
                    <option value="inactive" ${status === 'inactive' ? 'selected' : ''}>Inactive</option>
                </select>
            </div>
        `;
        new bootstrap.Modal(document.getElementById('editModal')).show();
    }
    
    function editSubject(id, code, title, lec, lab, units, yearLevel, semester, course) {
        document.getElementById('editModalTitle').textContent = 'Subject';
        document.getElementById('editId').value = id;
        const modalBody = document.getElementById('editModalBody');
        modalBody.innerHTML = `
            <div class="mb-3">
                <label class="form-label">Subject Code</label>
                <input type="text" name="subject_code" class="form-control" value="${code}" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Descriptive Title</label>
                <input type="text" name="descriptive_title" class="form-control" value="${title}" required>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label">Lecture Hours</label>
                    <input type="number" name="lec" class="form-control" value="${lec}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Lab Hours</label>
                    <input type="number" name="lab" class="form-control" value="${lab}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Units</label>
                    <input type="number" step="0.5" name="units" class="form-control" value="${units}" required>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-md-4">
                    <label class="form-label">Year Level</label>
                    <select name="year_level" class="form-select">
                        <option value="1" ${yearLevel == 1 ? 'selected' : ''}>1st Year</option>
                        <option value="2" ${yearLevel == 2 ? 'selected' : ''}>2nd Year</option>
                        <option value="3" ${yearLevel == 3 ? 'selected' : ''}>3rd Year</option>
                        <option value="4" ${yearLevel == 4 ? 'selected' : ''}>4th Year</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Semester</label>
                    <select name="semester" class="form-select">
                        <option value="1" ${semester == 1 ? 'selected' : ''}>1st Semester</option>
                        <option value="2" ${semester == 2 ? 'selected' : ''}>2nd Semester</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Course</label>
                    <select name="course" class="form-select">
                        <option value="CE" ${course === 'CE' ? 'selected' : ''}>Civil Engineering (CE)</option>
                        <option value="CpE" ${course === 'CpE' ? 'selected' : ''}>Computer Engineering (CpE)</option>
                    </select>
                </div>
            </div>
        `;
        new bootstrap.Modal(document.getElementById('editModal')).show();
    }
    
    function editProspectus(id, code, title, lec, lab, units, yearLevel, semester, course, prerequisite) {
        document.getElementById('editModalTitle').textContent = 'Prospectus Entry';
        document.getElementById('editId').value = id;
        const modalBody = document.getElementById('editModalBody');
        modalBody.innerHTML = `
            <div class="mb-3">
                <label class="form-label">Subject Code</label>
                <input type="text" name="subject_code" class="form-control" value="${code}" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Descriptive Title</label>
                <input type="text" name="descriptive_title" class="form-control" value="${title}" required>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label">Lecture Hours</label>
                    <input type="number" name="lec" class="form-control" value="${lec}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Lab Hours</label>
                    <input type="number" name="lab" class="form-control" value="${lab}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Units</label>
                    <input type="number" step="0.5" name="units" class="form-control" value="${units}" required>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-md-4">
                    <label class="form-label">Year Level</label>
                    <select name="year_level" class="form-select">
                        <option value="1" ${yearLevel == 1 ? 'selected' : ''}>1st Year</option>
                        <option value="2" ${yearLevel == 2 ? 'selected' : ''}>2nd Year</option>
                        <option value="3" ${yearLevel == 3 ? 'selected' : ''}>3rd Year</option>
                        <option value="4" ${yearLevel == 4 ? 'selected' : ''}>4th Year</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Semester</label>
                    <select name="semester" class="form-select">
                        <option value="1" ${semester == 1 ? 'selected' : ''}>1st Semester</option>
                        <option value="2" ${semester == 2 ? 'selected' : ''}>2nd Semester</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Course</label>
                    <select name="course" class="form-select">
                        <option value="CE" ${course === 'CE' ? 'selected' : ''}>Civil Engineering (CE)</option>
                        <option value="CpE" ${course === 'CpE' ? 'selected' : ''}>Computer Engineering (CpE)</option>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Pre-requisite(s)</label>
                <input type="text" name="prerequisite_text" class="form-control" value="${prerequisite || ''}">
            </div>
        `;
        new bootstrap.Modal(document.getElementById('editModal')).show();
    }
    
    // Delete Function
    function deleteItem(type, id, name) {
        document.getElementById('deleteItemName').textContent = name;
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').action = `?tab=${currentTab}`;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }
    
    // Reset Password Function
    function resetPassword(type, id, name) {
        document.getElementById('resetItemName').textContent = name;
        document.getElementById('resetId').value = id;
        const defaultPassword = type === 'student' ? 'student123' : 'prof123';
        document.getElementById('defaultPassword').textContent = defaultPassword;
        document.getElementById('resetForm').action = `?tab=${currentTab}`;
        new bootstrap.Modal(document.getElementById('resetModal')).show();
    }
    
    // Search functionality
    document.getElementById('searchInput').addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        const table = document.getElementById('dataTable');
        const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
        
        for (let row of rows) {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        }
    });
</script>
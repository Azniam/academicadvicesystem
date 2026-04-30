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

// Get student's grades summary
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_subjects,
        SUM(CASE WHEN status = 'PASSED' THEN 1 ELSE 0 END) as passed,
        SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) as failed,
        AVG(CASE WHEN grade IS NOT NULL THEN grade END) as average_grade
    FROM grades 
    WHERE student_id = ?
");
$stmt->execute([$user['user_id']]);
$gradeSummary = $stmt->fetch();

// Get current subjects based on year level and semester
$stmt = $pdo->prepare("
    SELECT s.* 
    FROM subjects s
    WHERE s.course = ? 
    AND s.year_level = ? 
    AND s.semester = ?
    ORDER BY s.subject_code
");
$stmt->execute([$user['course'], $user['year_level'], $currentSemester]);
$currentSubjects = $stmt->fetchAll();

// Get student's grades for current subjects
if (!empty($currentSubjects)) {
    $subjectIds = array_column($currentSubjects, 'id');
    $placeholders = implode(',', array_fill(0, count($subjectIds), '?'));
    $stmt = $pdo->prepare("
        SELECT subject_id, grade, status
        FROM grades 
        WHERE student_id = ? AND subject_id IN ($placeholders)
    ");
    $params = array_merge([$user['user_id']], $subjectIds);
    $stmt->execute($params);
    $subjectGrades = [];
    while ($row = $stmt->fetch()) {
        $subjectGrades[$row['subject_id']] = $row;
    }
} else {
    $subjectGrades = [];
}

// Get ALL passed subjects by the student
$stmt = $pdo->prepare("
    SELECT subject_id 
    FROM grades 
    WHERE student_id = ? AND status = 'PASSED'
");
$stmt->execute([$user['user_id']]);
$passedSubjects = $stmt->fetchAll(PDO::FETCH_COLUMN);

// =============================================
// FIXED LOGIC FOR NEXT SEMESTER SUBJECTS
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

// Get all subjects for next semester
$stmt = $pdo->prepare("
    SELECT s.*, 
           p.prerequisite_id,
           pre.subject_code as prereq_code,
           pre.descriptive_title as prereq_title
    FROM subjects s
    LEFT JOIN prerequisites p ON s.id = p.subject_id
    LEFT JOIN subjects pre ON p.prerequisite_id = pre.id
    WHERE s.course = ? 
    AND s.year_level = ? 
    AND s.semester = ?
    ORDER BY s.subject_code
");
$stmt->execute([$user['course'], $nextYearLevel, $nextSemester]);
$allNextSubjects = $stmt->fetchAll();

// Filter subjects based on prerequisites
$availableNextSubjects = [];
$unavailableNextSubjects = [];

foreach ($allNextSubjects as $subject) {
    // Check if subject has prerequisites
    if ($subject['prerequisite_id']) {
        // Check if prerequisite is passed
        $prereq_passed = in_array($subject['prerequisite_id'], $passedSubjects);
        
        if ($prereq_passed) {
            $availableNextSubjects[] = $subject;
        } else {
            $unavailableNextSubjects[] = $subject;
        }
    } else {
        // No prerequisite, subject is available
        $availableNextSubjects[] = $subject;
    }
}

// Remove duplicates (subjects with multiple prerequisites)
$uniqueAvailable = [];
$uniqueIds = [];
foreach ($availableNextSubjects as $subject) {
    if (!in_array($subject['id'], $uniqueIds)) {
        $uniqueIds[] = $subject['id'];
        $uniqueAvailable[] = $subject;
    }
}
$availableNextSubjects = $uniqueAvailable;

// Get announcement/notice
$stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'announcement'");
$announcement = $stmt->fetch();
$announcementText = $announcement ? $announcement['setting_value'] : '';

// Calculate year standing
$total = $gradeSummary['total_subjects'] ?? 0;
$passed = $gradeSummary['passed'] ?? 0;
$yearStanding = $total > 0 ? ($passed / $total) * 100 : 0;

// Calculate total units for available subjects
$totalAvailableUnits = 0;
foreach ($availableNextSubjects as $subject) {
    $totalAvailableUnits += $subject['units'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Academic Advising System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #00A7E1;
            --secondary: #F17720;
        }
        .welcome-card {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 15px;
            padding: 25px;
            color: white;
            margin-bottom: 25px;
        }
        .info-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .grade-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
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
        .announcement {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        .subject-code {
            font-weight: 700;
            color: var(--primary);
        }
        .prerequisite-warning {
            background: #f8d7da;
            color: #721c24;
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 11px;
            display: inline-block;
        }
        .badge-available {
            background: #d4edda;
            color: #155724;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
        }
        .badge-not-available {
            background: #f8d7da;
            color: #721c24;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
        }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>

<div class="container-fluid">
    <!-- Welcome Card -->
    <div class="welcome-card">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="mb-2"><i class="fas fa-user-graduate me-2"></i>Welcome, <?php echo htmlspecialchars($user['fullname']); ?>!</h2>
                <p class="mb-0 opacity-75">
                    <?php echo $user['course']; ?> - Year <?php echo $user['year_level']; ?> Section <?php echo $user['section']; ?> | 
                    <?php echo $currentSchoolYear . ' - ' . ($currentSemester == 1 ? '1st Semester' : '2nd Semester'); ?>
                </p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <div class="bg-white bg-opacity-25 rounded p-2 d-inline-block">
                    <i class="fas fa-calendar-alt me-2"></i>
                    <?php echo date('F d, Y'); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Announcement -->
    <?php if($announcementText): ?>
    <div class="info-card announcement">
        <div class="d-flex">
            <div class="flex-shrink-0">
                <i class="fas fa-bullhorn fa-2x text-warning"></i>
            </div>
            <div class="flex-grow-1 ms-3">
                <h6 class="mb-1">Announcement</h6>
                <p class="mb-0"><?php echo htmlspecialchars($announcementText); ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Year Standing -->
    <div class="info-card">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-1"><i class="fas fa-chart-line me-2 text-primary"></i>Year Standing</h5>
                <p class="text-muted mb-0">You need at least 75% to proceed to the next year level</p>
            </div>
            <div class="text-end">
                <div class="progress" style="width: 200px; height: 10px;">
                    <div class="progress-bar bg-success" style="width: <?php echo $yearStanding; ?>%"></div>
                </div>
                <div class="mt-1">
                    <?php if($yearStanding >= 75): ?>
                        <span class="badge bg-success">
                            <i class="fas fa-check-circle me-1"></i><?php echo round($yearStanding); ?>% - Eligible
                        </span>
                    <?php else: ?>
                        <span class="badge bg-warning">
                            <i class="fas fa-exclamation-triangle me-1"></i><?php echo round($yearStanding); ?>% - Need <?php echo round(75 - $yearStanding); ?>% more
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Current Subjects for this Semester -->
    <div class="info-card">
        <h5><i class="fas fa-book-open me-2 text-primary"></i>Current Subjects - <?php echo $currentSemester == 1 ? '1st Semester' : '2nd Semester'; ?> (Year <?php echo $user['year_level']; ?>)</h5>
        
        <?php if($currentSubjects): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Subject Code</th>
                            <th>Description</th>
                            <th>Units</th>
                            <th>Status</th>
                            <th>Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($currentSubjects as $subject): ?>
                            <?php 
                            $has_grade = isset($subjectGrades[$subject['id']]);
                            $grade = $has_grade ? $subjectGrades[$subject['id']] : null;
                            $grade_status = $has_grade ? ($grade['status'] ?? 'PENDING') : 'NOT YET ENCODED';
                            $grade_value = $has_grade ? $grade['grade'] : null;
                            ?>
                            <tr>
                                <td><span class="subject-code"><?php echo htmlspecialchars($subject['subject_code']); ?></span></td>
                                <td><?php echo htmlspecialchars($subject['descriptive_title']); ?></td>
                                <td class="text-center"><?php echo $subject['units']; ?></td>
                                <td>
                                    <?php if($has_grade): ?>
                                        <?php if($grade_status == 'PASSED'): ?>
                                            <span class="grade-badge grade-passed">
                                                <i class="fas fa-check-circle me-1"></i>PASSED
                                            </span>
                                        <?php elseif($grade_status == 'FAILED'): ?>
                                            <span class="grade-badge grade-failed">
                                                <i class="fas fa-times-circle me-1"></i>FAILED
                                            </span>
                                        <?php else: ?>
                                            <span class="grade-badge grade-pending">
                                                <i class="fas fa-clock me-1"></i>PENDING
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="grade-badge grade-pending">
                                            <i class="fas fa-hourglass-half me-1"></i>NOT YET ENCODED
                                        </span>
                                    <?php endif; ?>
                                 </div>
                                </td>
                                <td>
                                    <?php if($has_grade && $grade_value): ?>
                                        <strong><?php echo number_format($grade_value, 2); ?></strong>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                 </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-4">
                <i class="fas fa-info-circle fa-2x text-muted mb-2"></i>
                <p class="text-muted mb-0">No subjects available for your current year level and semester.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Next Semester Subjects - Available -->
    <div class="info-card">
        <?php if($isGraduating): ?>
    <h5><i class="fas fa-forward me-2 text-primary"></i>Next Semester Subjects</h5>
<?php else: ?>
    <h5><i class="fas fa-forward me-2 text-primary"></i>Next Semester Subjects (Year <?php echo $nextYearLevel; ?> - <?php echo $nextSemester == 1 ? '1st Semester' : '2nd Semester'; ?>)</h5>
<?php endif; ?>
        
        <?php if($availableNextSubjects): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Subject Code</th>
                            <th>Description</th>
                            <th>Units</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($availableNextSubjects as $subject): ?>
                            <tr>
                                <td><span class="subject-code"><?php echo htmlspecialchars($subject['subject_code']); ?></span></td>
                                <td><?php echo htmlspecialchars($subject['descriptive_title']); ?></td>
                                <td class="text-center"><?php echo $subject['units']; ?></td>
                                <td>
                                    <span class="badge-available">
                                        <i class="fas fa-check-circle me-1"></i>Available
                                    </span>
                                 </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-light">
                            <td colspan="2" class="text-end fw-bold">Total Units:</td>
                            <td class="text-center fw-bold"><?php echo $totalAvailableUnits; ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-4">
                <i class="fas fa-info-circle fa-2x text-muted mb-2"></i>
                <p class="text-muted mb-0">No subjects available for next semester.</p>
                <?php if($isGraduating): ?>
                    <small class="text-muted">You are on your final semester. Please proceed to the Dean's office for graduation clearance.</small>
                <?php elseif($nextYearLevel > 4): ?>
                    <small class="text-muted">You have completed all subjects! You may be eligible for graduation.</small>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if($unavailableNextSubjects): ?>
            <div class="mt-3">
                <h6 class="text-muted"><i class="fas fa-exclamation-triangle me-2"></i>Subjects Not Available (Prerequisite Required)</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Subject Code</th>
                                <th>Description</th>
                                <th>Units</th>
                                <th>Required Prerequisite</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $displayedUnavailable = [];
                            foreach($unavailableNextSubjects as $subject):
                                if(in_array($subject['id'], $displayedUnavailable)) continue;
                                $displayedUnavailable[] = $subject['id'];
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($subject['subject_code']); ?></td>
                                    <td><?php echo htmlspecialchars($subject['descriptive_title']); ?></td>
                                    <td class="text-center"><?php echo $subject['units']; ?></td>
                                    <td>
                                        <span class="prerequisite-warning">
                                            <i class="fas fa-book me-1"></i>
                                            <?php echo htmlspecialchars($subject['prereq_code'] ?? 'Unknown'); ?>
                                        </span>
                                     </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <small class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    You need to pass the prerequisite subjects before you can enroll in these subjects.
                </small>
            </div>
        <?php endif; ?>
    </div>

    <!-- Quick Links -->
    <div class="info-card">
        <h5><i class="fas fa-link me-2 text-primary"></i>Quick Links</h5>
        <div class="row">
            <div class="col-md-3 col-6 mb-2">
                <a href="prospectus.php" class="btn btn-outline-primary w-100">
                    <i class="fas fa-book-open me-1"></i> View Prospectus
                </a>
            </div>
            <div class="col-md-3 col-6 mb-2">
                <a href="grade_encoding.php" class="btn btn-outline-success w-100">
                    <i class="fas fa-edit me-1"></i> Encode Grades
                </a>
            </div>
            <div class="col-md-3 col-6 mb-2">
                <a href="professor_schedule.php" class="btn btn-outline-info w-100">
                    <i class="fas fa-chalkboard-teacher me-1"></i> Professor Schedule
                </a>
            </div>
            <div class="col-md-3 col-6 mb-2">
                <a href="room_availability.php" class="btn btn-outline-warning w-100">
                    <i class="fas fa-door-open me-1"></i> Room Availability
                </a>
            </div>
            <div class="col-md-3 col-6 mb-2">
                <a href="advising_report.php" class="btn btn-outline-primary w-100">
                    <i class="fas fa-file-alt me-1"></i> Advising Report
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
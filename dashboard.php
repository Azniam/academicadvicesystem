<?php
require_once '../includes/auth.php';
requireRole('admin');

// Get current school year and semester from settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$currentSchoolYear = $settings['current_school_year'] ?? '2024-2025';
$currentSemester = $settings['current_semester'] ?? '1';

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'student'");
$totalStudents = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'professor'");
$totalProfessors = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'admin'");
$totalAdmins = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM subjects");
$totalSubjects = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM rooms");
$totalRooms = $stmt->fetch()['total'];

// Get recent student registrations
$stmt = $pdo->query("
    SELECT user_id, fullname, course, section, created_at 
    FROM users 
    WHERE role = 'student' 
    ORDER BY created_at DESC 
    LIMIT 5
");
$recentStudents = $stmt->fetchAll();

// Get grade encoding status
$gradeEncodingEnabled = $settings['grade_encoding_enabled'] ?? '1';

// Get current semester status
$semesterStatus = $settings['semester_status'] ?? 'ongoing';

// Get enrollment statistics by course
$stmt = $pdo->query("
    SELECT course, COUNT(*) as count 
    FROM users 
    WHERE role = 'student' 
    GROUP BY course
");
$courseStats = $stmt->fetchAll();

// Get recent activities (if activity_logs table exists)
$recentActivities = [];
try {
    $stmt = $pdo->query("
        SELECT user_name, action, timestamp 
        FROM activity_logs 
        ORDER BY timestamp DESC 
        LIMIT 10
    ");
    $recentActivities = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table doesn't exist yet
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Academic Advising System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #00A7E1;
            --secondary: #F17720;
            --dark: #2d3748;
            --light: #f7fafc;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
            margin-bottom: 20px;
            border-left: 4px solid var(--primary);
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark);
        }
        .stat-label {
            color: #718096;
            font-size: 14px;
        }
        .welcome-banner {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 15px;
            padding: 25px;
            color: white;
            margin-bottom: 25px;
        }
        .quick-action {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
            border: 1px solid #e2e8f0;
            margin-bottom: 15px;
        }
        .quick-action:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-color: var(--primary);
        }
        .quick-action i {
            font-size: 30px;
            color: var(--primary);
            margin-bottom: 10px;
        }
        .table-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .table-card h5 {
            margin-bottom: 20px;
            color: var(--dark);
            font-weight: 600;
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-enabled {
            background: #d4edda;
            color: #155724;
        }
        .status-disabled {
            background: #f8d7da;
            color: #721c24;
        }
        .status-ongoing {
            background: #fff3cd;
            color: #856404;
        }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>

<div class="container-fluid">
    <!-- Welcome Banner -->
    <div class="welcome-banner">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="mb-2"><i class="fas fa-crown me-2"></i>Welcome back, <?php echo htmlspecialchars($_SESSION['fullname']); ?>!</h2>
                <p class="mb-0 opacity-75">Here's what's happening with your academic advising system today.</p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <div class="bg-white bg-opacity-25 rounded p-2 d-inline-block">
                    <i class="fas fa-calendar-alt me-2"></i>
                    <?php echo date('F d, Y'); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-number"><?php echo number_format($totalStudents); ?></div>
                        <div class="stat-label">Total Students</div>
                    </div>
                    <div class="stat-icon bg-primary bg-opacity-10">
                        <i class="fas fa-users text-primary"></i>
                    </div>
                </div>
                <div class="mt-2">
                    <small class="text-muted">
                        <?php 
                        if($courseStats) {
                            foreach($courseStats as $stat) {
                                echo $stat['course'] . ': ' . $stat['count'] . ' ';
                            }
                        }
                        ?>
                    </small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-number"><?php echo number_format($totalProfessors); ?></div>
                        <div class="stat-label">Professors</div>
                    </div>
                    <div class="stat-icon bg-success bg-opacity-10">
                        <i class="fas fa-chalkboard-teacher text-success"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-number"><?php echo number_format($totalSubjects); ?></div>
                        <div class="stat-label">Subjects</div>
                    </div>
                    <div class="stat-icon bg-info bg-opacity-10">
                        <i class="fas fa-book text-info"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-number"><?php echo number_format($totalRooms); ?></div>
                        <div class="stat-label">Rooms</div>
                    </div>
                    <div class="stat-icon bg-warning bg-opacity-10">
                        <i class="fas fa-door-open text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- System Status -->
    <div class="row">
        <div class="col-md-12">
            <div class="table-card">
                <h5><i class="fas fa-info-circle me-2"></i>System Status</h5>
                <table class="table table-borderless">
                    <tr>
                        <td width="25%">School Year:</td>
                        <td><strong><?php echo $currentSchoolYear; ?></strong></td>
                    </tr>
                    <tr>
                        <td>Current Semester:</td>
                        <td><strong><?php echo $currentSemester == 1 ? '1st Semester' : '2nd Semester'; ?></strong></td>
                    </tr>
                    <tr>
                        <td>Grade Encoding:</td>
                        <td>
                            <span class="status-badge <?php echo $gradeEncodingEnabled == '1' ? 'status-enabled' : 'status-disabled'; ?>">
                                <?php echo $gradeEncodingEnabled == '1' ? 'Enabled' : 'Disabled'; ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td>Semester Status:</td>
                        <td>
                            <span class="status-badge status-ongoing">
                                <?php echo ucfirst($semesterStatus); ?>
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row">
        <div class="col-12">
            <div class="table-card">
                <h5><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                <div class="row">
                    <div class="col-md-2 col-4">
                        <div class="quick-action" onclick="location.href='student_records.php'">
                            <i class="fas fa-user-graduate"></i>
                            <div class="small mt-1">Students</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-4">
                        <div class="quick-action" onclick="location.href='data_management.php?tab=subjects'">
                            <i class="fas fa-book"></i>
                            <div class="small mt-1">Subjects</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-4">
                        <div class="quick-action" onclick="location.href='room_management.php'">
                            <i class="fas fa-door-open"></i>
                            <div class="small mt-1">Rooms</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-4">
                        <div class="quick-action" onclick="location.href='professor_schedule.php'">
                            <i class="fas fa-calendar-alt"></i>
                            <div class="small mt-1">Schedules</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-4">
                        <div class="quick-action" onclick="location.href='grade_control.php'">
                            <i class="fas fa-edit"></i>
                            <div class="small mt-1">Grade Control</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-4">
                        <div class="quick-action" onclick="location.href='data_management.php?tab=prospectus'">
                            <i class="fas fa-list-alt"></i>
                            <div class="small mt-1">Prospectus</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Students & Activities -->
    <div class="row">
        <div class="col-md-7">
            <div class="table-card">
                <h5><i class="fas fa-user-plus me-2"></i>Recent Student Registrations</h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Course</th>
                                <th>Section</th>
                                <th>Registered</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($recentStudents): ?>
                                <?php foreach($recentStudents as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['user_id']); ?></td>
                                        <td><?php echo htmlspecialchars($student['fullname']); ?></td>
                                        <td><?php echo $student['course']; ?></td>
                                        <td><?php echo $student['section']; ?></td>
                                        <td><?php echo date('M d, Y', strtotime($student['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">No recent registrations</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="table-card">
                <h5><i class="fas fa-history me-2"></i>Recent Activities</h5>
                <div class="activity-list" style="max-height: 300px; overflow-y: auto;">
                    <?php if($recentActivities): ?>
                        <?php foreach($recentActivities as $activity): ?>
                            <div class="d-flex align-items-center mb-3 pb-2 border-bottom">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-circle text-primary" style="font-size: 8px;"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <small class="text-muted"><?php echo date('h:i A', strtotime($activity['timestamp'])); ?></small>
                                    <div class="small"><?php echo htmlspecialchars($activity['user_name']); ?> - <?php echo ucfirst($activity['action']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted text-center">No recent activities</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
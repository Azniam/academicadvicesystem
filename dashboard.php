<?php
require_once '../includes/auth.php';
requireRole('professor');

$user = getUserData($_SESSION['user_id']);

// Get current school year and semester
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$currentSchoolYear = $settings['current_school_year'] ?? '2024-2025';
$currentSemester = $settings['current_semester'] ?? '1';

// Get professor's schedule
$stmt = $pdo->prepare("
    SELECT s.*, sub.subject_code, sub.descriptive_title, sub.units,
           r.room_code
    FROM schedules s
    JOIN subjects sub ON s.subject_id = sub.id
    JOIN rooms r ON s.room_id = r.id
    WHERE s.professor_id = ?
    ORDER BY FIELD(s.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), s.start_time
");
$stmt->execute([$user['user_id']]);
$schedules = $stmt->fetchAll();

// Get total students advised (if professor is assigned as advisor)
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'student'");
$stmt->execute();  // ← ADD THIS LINE
$result = $stmt->fetch();
$totalStudents = $result ? $result['total'] : 0;

// Get subjects taught count
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT subject_id) as total FROM schedules WHERE professor_id = ?");
$stmt->execute([$user['user_id']]);
$totalSubjects = $stmt->fetch()['total'];

// Get today's schedule
$today = date('l');
$todaySchedules = array_filter($schedules, function($schedule) use ($today) {
    return $schedule['day'] == $today;
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professor Dashboard - Academic Advising System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
        }
        .welcome-card {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 15px;
            padding: 25px;
            color: white;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            border-left: 4px solid var(--primary);
        }
        .schedule-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
        }
        .schedule-table th {
            background: #f8f9fa;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: #2d3748;
            border-bottom: 2px solid #e2e8f0;
        }
        .schedule-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        .schedule-table tr:hover {
            background: #f8f9fa;
        }
        .today-schedule {
            background: linear-gradient(135deg, #667eea15, #764ba215);
            border-left: 4px solid var(--primary);
        }
        .btn-add {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            padding: 8px 20px;
            border-radius: 25px;
            color: white;
        }
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
            color: white;
        }
        .badge-f2f {
            background: #d4edda;
            color: #155724;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-online {
            background: #cce5ff;
            color: #004085;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .day-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .day-monday { background: #e8f0fe; color: #1967d2; }
        .day-tuesday { background: #fce8e6; color: #c5221f; }
        .day-wednesday { background: #e6f4ea; color: #137333; }
        .day-thursday { background: #fef7e0; color: #b06000; }
        .day-friday { background: #f3e8ff; color: #9334e6; }
        .day-saturday { background: #e0f2fe; color: #0b5e7e; }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>

<div class="container-fluid">
    <!-- Welcome Card -->
    <div class="welcome-card">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="mb-2"><i class="fas fa-chalkboard-teacher me-2"></i>Welcome, Prof. <?php echo htmlspecialchars($user['fullname']); ?>!</h2>
                <p class="mb-0 opacity-75">Here's your teaching schedule and overview for <?php echo $currentSchoolYear . ' - ' . ($currentSemester == 1 ? '1st Semester' : '2nd Semester'); ?></p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <div class="bg-white bg-opacity-25 rounded p-2 d-inline-block">
                    <i class="fas fa-calendar-alt me-2"></i>
                    <?php echo date('F d, Y'); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="row">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-number" style="font-size: 28px; font-weight: 700;"><?php echo count($schedules); ?></div>
                        <div class="stat-label text-muted">Total Classes</div>
                    </div>
                    <i class="fas fa-calendar-check fa-2x text-primary opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-number" style="font-size: 28px; font-weight: 700;"><?php echo $totalSubjects; ?></div>
                        <div class="stat-label text-muted">Subjects Handled</div>
                    </div>
                    <i class="fas fa-book fa-2x text-success opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-number" style="font-size: 28px; font-weight: 700;"><?php echo $totalStudents; ?></div>
                        <div class="stat-label text-muted">Total Students</div>
                    </div>
                    <i class="fas fa-users fa-2x text-info opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Today's Schedule Section -->
    <?php if($todaySchedules): ?>
    <div class="schedule-card today-schedule">
        <h5><i class="fas fa-calendar-day me-2 text-primary"></i>Today's Schedule - <?php echo $today; ?></h5>
        <div class="table-responsive">
            <table class="schedule-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Subject</th>
                        <th>Room</th>
                        <th>Mode</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($todaySchedules as $schedule): ?>
                        <tr>
                            <td><?php echo date('h:i A', strtotime($schedule['start_time'])) . ' - ' . date('h:i A', strtotime($schedule['end_time'])); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($schedule['subject_code']); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($schedule['descriptive_title']); ?></small>
                            </td>
                            <td><i class="fas fa-door-open me-1"></i> <?php echo $schedule['room_code']; ?></td>
                            <td>
                                <span class="<?php echo $schedule['mode'] == 'F2F' ? 'badge-f2f' : 'badge-online'; ?>">
                                    <i class="fas <?php echo $schedule['mode'] == 'F2F' ? 'fa-chalkboard' : 'fa-laptop'; ?> me-1"></i>
                                    <?php echo $schedule['mode']; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Complete Schedule Table (Time, Subject, Day, Mode) -->
    <div class="schedule-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5><i class="fas fa-calendar-alt me-2 text-primary"></i>My Complete Schedule</h5>
            <button class="btn-add" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                <i class="fas fa-plus me-1"></i>Add Schedule
            </button>
        </div>
        
        <?php if($schedules): ?>
            <div class="table-responsive">
                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th style="width: 20%;">Time</th>
                            <th style="width: 35%;">Subject</th>
                            <th style="width: 20%;">Day</th>
                            <th style="width: 15%;">Mode</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($schedules as $schedule): ?>
                            <tr>
                                <td>
                                    <i class="fas fa-clock text-primary me-1"></i>
                                    <?php echo date('h:i A', strtotime($schedule['start_time'])) . ' - ' . date('h:i A', strtotime($schedule['end_time'])); ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($schedule['subject_code']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($schedule['descriptive_title']); ?></small>
                                    <br>
                                    <small><i class="fas fa-door-open me-1 text-muted"></i>Room: <?php echo $schedule['room_code']; ?></small>
                                </td>
                                <td>
                                    <span class="day-badge day-<?php echo strtolower($schedule['day']); ?>">
                                        <i class="fas fa-calendar-day me-1"></i>
                                        <?php echo $schedule['day']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="<?php echo $schedule['mode'] == 'F2F' ? 'badge-f2f' : 'badge-online'; ?>">
                                        <i class="fas <?php echo $schedule['mode'] == 'F2F' ? 'fa-chalkboard' : 'fa-laptop'; ?> me-1"></i>
                                        <?php echo $schedule['mode']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                <p class="text-muted">No schedules found. Click "Add Schedule" to create one.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Schedule Modal -->
<div class="modal fade" id="addScheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add Schedule</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="room_management.php" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <select name="subject_id" class="form-select" required>
                            <option value="">Select Subject</option>
                            <?php
                            $stmt = $pdo->query("SELECT id, subject_code, descriptive_title FROM subjects ORDER BY subject_code");
                            $allSubjects = $stmt->fetchAll();
                            foreach($allSubjects as $subject): ?>
                                <option value="<?php echo $subject['id']; ?>"><?php echo $subject['subject_code'] . ' - ' . $subject['descriptive_title']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Day</label>
                        <select name="day" class="form-select" required>
                            <option value="">Select Day</option>
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                            <option value="Saturday">Saturday</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Start Time</label>
                            <input type="time" name="start_time" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Time</label>
                            <input type="time" name="end_time" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label">Room</label>
                        <select name="room_id" class="form-select" required>
                            <option value="">Select Room</option>
                            <?php
                            $stmt = $pdo->query("SELECT id, room_code FROM rooms WHERE room_type != 'office'");
                            $rooms = $stmt->fetchAll();
                            foreach($rooms as $room): ?>
                                <option value="<?php echo $room['id']; ?>"><?php echo $room['room_code']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mode</label>
                        <select name="mode" class="form-select" required>
                            <option value="F2F">Face to Face (F2F)</option>
                            <option value="Online">Online Class</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
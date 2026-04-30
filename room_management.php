<?php
require_once '../includes/auth.php';
requireRole('professor');

$user = getUserData($_SESSION['user_id']);

// Handle schedule operations
$message = '';
$error = '';

// Add schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_schedule') {
        $subject_id = $_POST['subject_id'];
        $room_id = $_POST['room_id'];
        $day = $_POST['day'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $mode = $_POST['mode'];
        $professor_note = trim($_POST['professor_note'] ?? '');
        $school_year = $_POST['school_year'] ?? '2024-2025';
        $semester = $_POST['semester'] ?? '1';
        
        // Validate time
        if ($start_time >= $end_time) {
            $error = "❌ End time must be after start time!";
        } else {
            // Check for schedule conflict with same professor
            $stmt = $pdo->prepare("
                SELECT * FROM schedules 
                WHERE professor_id = ? AND day = ? 
                AND ((start_time <= ? AND end_time > ?) OR (start_time < ? AND end_time >= ?))
            ");
            $stmt->execute([$user['user_id'], $day, $start_time, $start_time, $end_time, $end_time]);
            
            if ($stmt->rowCount() > 0) {
                $error = "❌ Schedule conflict! You already have a class at this time.";
            } else {
                // Check for room conflict
                $stmt = $pdo->prepare("
                    SELECT s.*, u.fullname as professor_name
                    FROM schedules s
                    JOIN users u ON s.professor_id = u.user_id
                    WHERE s.room_id = ? AND s.day = ? 
                    AND ((s.start_time <= ? AND s.end_time > ?) OR (s.start_time < ? AND s.end_time >= ?))
                ");
                $stmt->execute([$room_id, $day, $start_time, $start_time, $end_time, $end_time]);
                $conflict = $stmt->fetch();
                
                if ($conflict) {
                    $error = "❌ Room conflict! This room is already booked at this time by Prof. " . htmlspecialchars($conflict['professor_name']);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO schedules (professor_id, subject_id, room_id, day, start_time, end_time, mode, professor_note, school_year, semester)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    if ($stmt->execute([$user['user_id'], $subject_id, $room_id, $day, $start_time, $end_time, $mode, $professor_note, $school_year, $semester])) {
                        $message = "✅ Schedule added successfully!";
                        header("Location: room_management.php?success=1");
                        exit();
                    } else {
                        $error = "❌ Failed to add schedule.";
                    }
                }
            }
        }
    }
    
    // Edit schedule
    if ($_POST['action'] === 'edit_schedule') {
        $id = $_POST['id'];
        $subject_id = $_POST['subject_id'];
        $room_id = $_POST['room_id'];
        $day = $_POST['day'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $mode = $_POST['mode'];
        $professor_note = trim($_POST['professor_note'] ?? '');
        
        // Validate time
        if ($start_time >= $end_time) {
            $error = "❌ End time must be after start time!";
        } else {
            // Check for professor conflicts (excluding current schedule)
            $stmt = $pdo->prepare("
                SELECT * FROM schedules 
                WHERE professor_id = ? AND day = ? AND id != ?
                AND ((start_time <= ? AND end_time > ?) OR (start_time < ? AND end_time >= ?))
            ");
            $stmt->execute([$user['user_id'], $day, $id, $start_time, $start_time, $end_time, $end_time]);
            
            if ($stmt->rowCount() > 0) {
                $error = "❌ Schedule conflict! You already have a class at this time.";
            } else {
                // Check for room conflict (excluding current schedule)
                $stmt = $pdo->prepare("
                    SELECT s.*, u.fullname as professor_name
                    FROM schedules s
                    JOIN users u ON s.professor_id = u.user_id
                    WHERE s.room_id = ? AND s.day = ? AND s.id != ?
                    AND ((s.start_time <= ? AND s.end_time > ?) OR (s.start_time < ? AND s.end_time >= ?))
                ");
                $stmt->execute([$room_id, $day, $id, $start_time, $start_time, $end_time, $end_time]);
                $conflict = $stmt->fetch();
                
                if ($conflict) {
                    $error = "❌ Room conflict! This room is already booked at this time by Prof. " . htmlspecialchars($conflict['professor_name']);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE schedules 
                        SET subject_id = ?, room_id = ?, day = ?, start_time = ?, end_time = ?, mode = ?, professor_note = ?
                        WHERE id = ? AND professor_id = ?
                    ");
                    if ($stmt->execute([$subject_id, $room_id, $day, $start_time, $end_time, $mode, $professor_note, $id, $user['user_id']])) {
                        $message = "✅ Schedule updated successfully!";
                        header("Location: room_management.php?success=1");
                        exit();
                    } else {
                        $error = "❌ Failed to update schedule.";
                    }
                }
            }
        }
    }
    
    // Delete schedule
    if ($_POST['action'] === 'delete_schedule') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM schedules WHERE id = ? AND professor_id = ?");
        if ($stmt->execute([$id, $user['user_id']])) {
            $message = "✅ Schedule deleted successfully!";
            header("Location: room_management.php?success=1");
            exit();
        } else {
            $error = "❌ Failed to delete schedule.";
        }
    }
}

// Get success message from URL
if (isset($_GET['success'])) {
    $message = "✅ Schedule saved successfully!";
}

// Get professor's schedules
$stmt = $pdo->prepare("
    SELECT s.*, 
           sub.subject_code, sub.descriptive_title, sub.units,
           r.room_code, r.room_type, r.capacity
    FROM schedules s
    JOIN subjects sub ON s.subject_id = sub.id
    JOIN rooms r ON s.room_id = r.id
    WHERE s.professor_id = ?
    ORDER BY FIELD(s.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), s.start_time
");
$stmt->execute([$user['user_id']]);
$schedules = $stmt->fetchAll();

// Get subjects
$stmt = $pdo->prepare("
    SELECT id, subject_code, descriptive_title, units
    FROM subjects
    ORDER BY subject_code
");
$stmt->execute();
$subjects = $stmt->fetchAll();

// Get available rooms
$rooms = $pdo->query("SELECT id, room_code, room_type, capacity FROM rooms WHERE room_type != 'office' ORDER BY room_code")->fetchAll();

// Get current school year and semester
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$currentSchoolYear = $settings['current_school_year'] ?? '2024-2025';
$currentSemester = $settings['current_semester'] ?? '1';

// Days order for sorting
$dayOrder = ['Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6];

// Sort schedules by day order
usort($schedules, function($a, $b) use ($dayOrder) {
    return $dayOrder[$a['day']] - $dayOrder[$b['day']];
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Management - Professor Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #00A7E1;
            --secondary: #F17720;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
        }
        .content-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .btn-add {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            padding: 10px 25px;
            border-radius: 25px;
            color: white;
        }
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,167,225,0.4);
            color: white;
        }
        .schedule-table th {
            background: #f8f9fa;
            padding: 12px;
            font-weight: 600;
        }
        .schedule-table td {
            vertical-align: middle;
            padding: 12px;
        }
        /* FULL ROW RED background when note exists */
        .schedule-row-with-note {
            background-color: #f8d7da !important;
        }
        .schedule-row-with-note:hover {
            background-color: #f5c6cb !important;
        }
        .schedule-row-with-note td {
            background-color: #f8d7da !important;
        }
        .schedule-row-with-note:hover td {
            background-color: #f5c6cb !important;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-f2f {
            background: #d4edda;
            color: #155724;
        }
        .status-online {
            background: #cce5ff;
            color: #004085;
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
        .btn-action {
            padding: 5px 10px;
            margin: 0 3px;
            border-radius: 8px;
            font-size: 12px;
        }
        .modal-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        .conflict-warning {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            padding: 10px;
            margin-top: 10px;
        }
        .room-availability {
            font-size: 12px;
            margin-top: 5px;
        }
        .room-available {
            color: var(--success);
        }
        .room-booked {
            color: var(--danger);
        }
        .professor-note {
            background: #fff3cd;
            border-left: 4px solid var(--warning);
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 12px;
            margin-top: 5px;
        }
        .professor-note i {
            color: var(--warning);
            margin-right: 5px;
        }
        .note-badge {
            background: #dc3545;
            color: white;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            display: inline-block;
        }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>

<div class="container-fluid">
    <div class="content-card">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-door-open me-2" style="color: var(--primary);"></i>My Schedule & Room Management</h2>
            <button class="btn-add" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                <i class="fas fa-plus me-2"></i>Add Schedule
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

        <!-- My Class Schedules Table -->
        <h5 class="mb-3"><i class="fas fa-calendar-alt me-2" style="color: var(--primary);"></i>My Class Schedules</h5>
        
        <?php if($schedules): ?>
            <div class="table-responsive">
                <table class="table table-hover schedule-table">
                    <thead>
                        <tr>
                            <th>Day</th>
                            <th>Time</th>
                            <th>Subject Code</th>
                            <th>Subject Title</th>
                            <th>Room</th>
                            <th>Mode</th>
                            <th>Note/Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($schedules as $schedule): ?>
                            <tr class="<?php echo (!empty($schedule['professor_note'])) ? 'schedule-row-with-note' : ''; ?>">
                                <td>
                                    <span class="day-badge day-<?php echo strtolower($schedule['day']); ?>">
                                        <i class="fas fa-calendar-day me-1"></i>
                                        <?php echo $schedule['day']; ?>
                                    </span>
                                 </div>
                                </td>
                                <td>
                                    <i class="fas fa-clock me-1 text-muted"></i>
                                    <?php echo date('h:i A', strtotime($schedule['start_time'])); ?> - 
                                    <?php echo date('h:i A', strtotime($schedule['end_time'])); ?>
                                 </div>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($schedule['subject_code']); ?></strong>
                                 </div>
                                </td>
                                <td><?php echo htmlspecialchars($schedule['descriptive_title']); ?></td>
                                <td>
                                    <i class="fas fa-door-open me-1 text-muted"></i>
                                    <?php echo $schedule['room_code']; ?>
                                 </div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($schedule['mode']); ?>">
                                        <i class="fas <?php echo $schedule['mode'] == 'F2F' ? 'fa-chalkboard' : 'fa-laptop'; ?> me-1"></i>
                                        <?php echo $schedule['mode']; ?>
                                    </span>
                                 </div>
                                
                                <td style="vertical-align: middle;">
                                    <?php if(!empty($schedule['professor_note'])): ?>
                                        <div class="professor-note">
                                            <i class="fas fa-sticky-note"></i>
                                            <strong class="text-danger">⚠️ NOTICE:</strong><br>
                                            <?php echo nl2br(htmlspecialchars($schedule['professor_note'])); ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                 </div>
                                
                                <td style="vertical-align: middle;">
                                    <button class="btn btn-sm btn-primary btn-action" onclick="editSchedule(<?php echo $schedule['id']; ?>, <?php echo $schedule['subject_id']; ?>, <?php echo $schedule['room_id']; ?>, '<?php echo $schedule['day']; ?>', '<?php echo $schedule['start_time']; ?>', '<?php echo $schedule['end_time']; ?>', '<?php echo $schedule['mode']; ?>', '<?php echo addslashes(htmlspecialchars($schedule['professor_note'] ?? '')); ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger btn-action" onclick="deleteSchedule(<?php echo $schedule['id']; ?>, '<?php echo htmlspecialchars($schedule['subject_code']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                 </div>
                                
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
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add Schedule</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addScheduleForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_schedule">
                    
                    <div class="mb-3">
                        <label class="form-label">Subject <span class="text-danger">*</span></label>
                        <select name="subject_id" id="addSubjectId" class="form-select" required>
                            <option value="">Select Subject</option>
                            <?php foreach($subjects as $subject): ?>
                                <option value="<?php echo $subject['id']; ?>">
                                    <?php echo htmlspecialchars($subject['subject_code']); ?> - <?php echo htmlspecialchars($subject['descriptive_title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Room <span class="text-danger">*</span></label>
                        <select name="room_id" id="addRoomId" class="form-select" required>
                            <option value="">Select Room</option>
                            <?php foreach($rooms as $room): ?>
                                <option value="<?php echo $room['id']; ?>">
                                    <?php echo $room['room_code']; ?> (Capacity: <?php echo $room['capacity']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="roomAvailabilityMsg" class="room-availability mt-1"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Day <span class="text-danger">*</span></label>
                        <select name="day" id="addDay" class="form-select" required>
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
                            <label class="form-label">Start Time <span class="text-danger">*</span></label>
                            <input type="time" name="start_time" id="addStartTime" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Time <span class="text-danger">*</span></label>
                            <input type="time" name="end_time" id="addEndTime" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Mode <span class="text-danger">*</span></label>
                        <select name="mode" class="form-select" required>
                            <option value="F2F">Face to Face (F2F)</option>
                            <option value="Online">Online Class</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Professor Note (Optional)</label>
                        <textarea name="professor_note" class="form-control" rows="3" placeholder="e.g., On sick leave, Out of office for meeting, Vacation leave, Class suspended, etc."></textarea>
                        <small class="text-muted">⚠️ Adding a note will highlight this schedule in RED to alert students.</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">School Year</label>
                            <input type="text" name="school_year" class="form-control" value="<?php echo $currentSchoolYear; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Semester</label>
                            <select name="semester" class="form-select">
                                <option value="1" <?php echo $currentSemester == '1' ? 'selected' : ''; ?>>1st Semester</option>
                                <option value="2" <?php echo $currentSemester == '2' ? 'selected' : ''; ?>>2nd Semester</option>
                            </select>
                        </div>
                    </div>
                    
                    <div id="scheduleConflictMsg" class="conflict-warning" style="display: none;">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <span id="conflictText"></span>
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

<!-- Edit Schedule Modal -->
<div class="modal fade" id="editScheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Schedule</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_schedule">
                    <input type="hidden" name="id" id="editId">
                    
                    <div class="mb-3">
                        <label class="form-label">Subject <span class="text-danger">*</span></label>
                        <select name="subject_id" id="editSubjectId" class="form-select" required>
                            <option value="">Select Subject</option>
                            <?php foreach($subjects as $subject): ?>
                                <option value="<?php echo $subject['id']; ?>">
                                    <?php echo htmlspecialchars($subject['subject_code']); ?> - <?php echo htmlspecialchars($subject['descriptive_title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Room <span class="text-danger">*</span></label>
                        <select name="room_id" id="editRoomId" class="form-select" required>
                            <option value="">Select Room</option>
                            <?php foreach($rooms as $room): ?>
                                <option value="<?php echo $room['id']; ?>">
                                    <?php echo $room['room_code']; ?> (Capacity: <?php echo $room['capacity']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="editRoomAvailabilityMsg" class="room-availability mt-1"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Day <span class="text-danger">*</span></label>
                        <select name="day" id="editDay" class="form-select" required>
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
                            <label class="form-label">Start Time <span class="text-danger">*</span></label>
                            <input type="time" name="start_time" id="editStartTime" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Time <span class="text-danger">*</span></label>
                            <input type="time" name="end_time" id="editEndTime" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Mode <span class="text-danger">*</span></label>
                        <select name="mode" id="editMode" class="form-select" required>
                            <option value="F2F">Face to Face (F2F)</option>
                            <option value="Online">Online Class</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Professor Note (Optional)</label>
                        <textarea name="professor_note" id="editProfessorNote" class="form-control" rows="3" placeholder="e.g., On sick leave, Out of office for meeting, Vacation leave, Class suspended, etc."></textarea>
                        <small class="text-muted">⚠️ Adding a note will highlight this schedule in RED to alert students.</small>
                    </div>
                    
                    <div id="editScheduleConflictMsg" class="conflict-warning" style="display: none;">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <span id="editConflictText"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the schedule for <strong id="deleteItemName"></strong>?</p>
                <p class="text-danger">This action cannot be undone!</p>
            </div>
            <div class="modal-footer">
                <form method="POST">
                    <input type="hidden" name="action" value="delete_schedule">
                    <input type="hidden" name="id" id="deleteId">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Check room availability function
    function checkRoomAvailability(roomId, day, startTime, endTime, excludeId = null, isEdit = false) {
        if (!roomId || !day || !startTime || !endTime) return;
        
        $.ajax({
            url: '../api/check_room_availability.php',
            method: 'POST',
            data: {
                room_id: roomId,
                day: day,
                start_time: startTime,
                end_time: endTime,
                exclude_id: excludeId
            },
            dataType: 'json',
            success: function(data) {
                if (isEdit) {
                    var msgDiv = $('#editRoomAvailabilityMsg');
                    var conflictDiv = $('#editScheduleConflictMsg');
                } else {
                    var msgDiv = $('#roomAvailabilityMsg');
                    var conflictDiv = $('#scheduleConflictMsg');
                }
                
                if (data.available) {
                    msgDiv.html('<i class="fas fa-check-circle me-1" style="color: #28a745;"></i> <span style="color: #28a745;">✓ Room is available at this time!</span>');
                    conflictDiv.hide();
                } else {
                    msgDiv.html('<i class="fas fa-times-circle me-1" style="color: #dc3545;"></i> <span style="color: #dc3545;">✗ Room is already booked by: ' + data.professor_name + '</span>');
                    conflictDiv.show();
                    if (isEdit) {
                        $('#editConflictText').text('Room conflict! This room is already booked at this time by Prof. ' + data.professor_name);
                    } else {
                        $('#conflictText').text('Room conflict! This room is already booked at this time by Prof. ' + data.professor_name);
                    }
                }
            }
        });
    }
    
    // Add schedule real-time validation
    $('#addRoomId, #addDay, #addStartTime, #addEndTime').on('change keyup', function() {
        var roomId = $('#addRoomId').val();
        var day = $('#addDay').val();
        var startTime = $('#addStartTime').val();
        var endTime = $('#addEndTime').val();
        
        if (roomId && day && startTime && endTime) {
            checkRoomAvailability(roomId, day, startTime, endTime, null, false);
        }
    });
    
    // Edit schedule real-time validation
    $('#editRoomId, #editDay, #editStartTime, #editEndTime').on('change keyup', function() {
        var editId = $('#editId').val();
        var roomId = $('#editRoomId').val();
        var day = $('#editDay').val();
        var startTime = $('#editStartTime').val();
        var endTime = $('#editEndTime').val();
        
        if (roomId && day && startTime && endTime) {
            checkRoomAvailability(roomId, day, startTime, endTime, editId, true);
        }
    });
    
    // Edit schedule function
    function editSchedule(id, subjectId, roomId, day, startTime, endTime, mode, professorNote) {
        document.getElementById('editId').value = id;
        document.getElementById('editSubjectId').value = subjectId;
        document.getElementById('editRoomId').value = roomId;
        document.getElementById('editDay').value = day;
        document.getElementById('editStartTime').value = startTime;
        document.getElementById('editEndTime').value = endTime;
        document.getElementById('editMode').value = mode;
        document.getElementById('editProfessorNote').value = professorNote;
        
        setTimeout(function() {
            checkRoomAvailability(roomId, day, startTime, endTime, id, true);
        }, 100);
        
        new bootstrap.Modal(document.getElementById('editScheduleModal')).show();
    }
    
    // Delete schedule function
    function deleteSchedule(id, subjectName) {
        document.getElementById('deleteItemName').textContent = subjectName;
        document.getElementById('deleteId').value = id;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }
    
    // Form validation before submit
    $('#addScheduleForm').on('submit', function(e) {
        var startTime = $('#addStartTime').val();
        var endTime = $('#addEndTime').val();
        
        if (startTime >= endTime) {
            e.preventDefault();
            alert('End time must be after start time!');
            return false;
        }
    });
    
    $('#editForm').on('submit', function(e) {
        var startTime = $('#editStartTime').val();
        var endTime = $('#editEndTime').val();
        
        if (startTime >= endTime) {
            e.preventDefault();
            alert('End time must be after start time!');
            return false;
        }
    });
</script>
<?php
require_once '../includes/auth.php';
requireRole('admin');

// Handle schedule operations
$message = '';
$error = '';

// Add schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $professor_id = $_POST['professor_id'];
        $subject_id = $_POST['subject_id'];
        $room_id = $_POST['room_id'];
        $day = $_POST['day'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $mode = $_POST['mode'];
        $school_year = $_POST['school_year'] ?? '2024-2025';
        $semester = $_POST['semester'] ?? '1';
        
        // Check for schedule conflict
        $stmt = $pdo->prepare("
            SELECT * FROM schedules 
            WHERE day = ? AND room_id = ? 
            AND ((start_time <= ? AND end_time > ?) OR (start_time < ? AND end_time >= ?))
        ");
        $stmt->execute([$day, $room_id, $start_time, $start_time, $end_time, $end_time]);
        
        if ($stmt->rowCount() > 0) {
            $error = "Schedule conflict! The room is already booked at this time.";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO schedules (professor_id, subject_id, room_id, day, start_time, end_time, mode, school_year, semester)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            if ($stmt->execute([$professor_id, $subject_id, $room_id, $day, $start_time, $end_time, $mode, $school_year, $semester])) {
                $message = "Schedule added successfully!";
            } else {
                $error = "Failed to add schedule.";
            }
        }
    }
    
    // Edit schedule
    if ($_POST['action'] === 'edit') {
        $id = $_POST['id'];
        $professor_id = $_POST['professor_id'];
        $subject_id = $_POST['subject_id'];
        $room_id = $_POST['room_id'];
        $day = $_POST['day'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $mode = $_POST['mode'];
        
        $stmt = $pdo->prepare("
            UPDATE schedules 
            SET professor_id = ?, subject_id = ?, room_id = ?, day = ?, start_time = ?, end_time = ?, mode = ?
            WHERE id = ?
        ");
        if ($stmt->execute([$professor_id, $subject_id, $room_id, $day, $start_time, $end_time, $mode, $id])) {
            $message = "Schedule updated successfully!";
        } else {
            $error = "Failed to update schedule.";
        }
    }
    
    // Delete schedule
    if ($_POST['action'] === 'delete') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM schedules WHERE id = ?");
        if ($stmt->execute([$id])) {
            $message = "Schedule deleted successfully!";
        } else {
            $error = "Failed to delete schedule.";
        }
    }
}

// Get all schedules with details
$stmt = $pdo->query("
    SELECT s.*, 
           u.user_id as professor_user_id, u.fullname as professor_name,
           sub.subject_code, sub.descriptive_title,
           r.room_code
    FROM schedules s
    JOIN users u ON s.professor_id = u.user_id
    JOIN subjects sub ON s.subject_id = sub.id
    JOIN rooms r ON s.room_id = r.id
    ORDER BY FIELD(s.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), s.start_time
");
$schedules = $stmt->fetchAll();

// Get professors for dropdown
$stmt = $pdo->query("SELECT user_id, fullname FROM users WHERE role = 'professor' AND status = 'active' ORDER BY fullname");
$professors = $stmt->fetchAll();

// Get subjects for dropdown
$stmt = $pdo->query("SELECT id, subject_code, descriptive_title FROM subjects ORDER BY subject_code");
$subjects = $stmt->fetchAll();

// Get rooms for dropdown
$stmt = $pdo->query("SELECT id, room_code FROM rooms WHERE room_type != 'office' ORDER BY room_code");
$rooms = $stmt->fetchAll();

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professor Schedule Management - Admin</title>
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
        .btn-add {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            padding: 10px 25px;
            border-radius: 25px;
            color: white;
        }
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
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
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
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
        .search-box {
            margin-bottom: 20px;
        }
        @media (max-width: 768px) {
            .schedule-table {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>

<div class="container-fluid">
    <div class="content-card">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-chalkboard-teacher me-2 text-primary"></i>Professor Schedule Management</h2>
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

        <!-- Search Box -->
        <div class="search-box">
            <div class="input-group" style="max-width: 300px;">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" id="searchInput" class="form-control" placeholder="Search by professor, subject, or room...">
            </div>
        </div>

        <!-- Schedules Table -->
        <div class="table-responsive">
            <table class="table table-hover schedule-table" id="scheduleTable">
                <thead>
                    <tr>
                        <th>Professor</th>
                        <th>Subject</th>
                        <th>Day</th>
                        <th>Time</th>
                        <th>Room</th>
                        <th>Mode</th>
                        <th>School Year</th>
                        <th>Semester</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($schedules as $schedule): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($schedule['professor_name']); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($schedule['professor_user_id']); ?></small>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($schedule['subject_code']); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($schedule['descriptive_title']); ?></small>
                            </td>
                            <td><?php echo $schedule['day']; ?></td>
                            <td>
                                <?php echo date('h:i A', strtotime($schedule['start_time'])); ?> - 
                                <?php echo date('h:i A', strtotime($schedule['end_time'])); ?>
                            </td>
                            <td><?php echo $schedule['room_code']; ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($schedule['mode']); ?>">
                                    <?php echo $schedule['mode']; ?>
                                </span>
                            </td>
                            <td><?php echo $schedule['school_year']; ?></td>
                            <td><?php echo $schedule['semester'] == 1 ? '1st' : '2nd'; ?></td>
                            <td>
                                <button class="btn btn-sm btn-primary btn-action" onclick="editSchedule(<?php echo $schedule['id']; ?>, '<?php echo htmlspecialchars($schedule['professor_user_id']); ?>', <?php echo $schedule['subject_id']; ?>, <?php echo $schedule['room_id']; ?>, '<?php echo $schedule['day']; ?>', '<?php echo $schedule['start_time']; ?>', '<?php echo $schedule['end_time']; ?>', '<?php echo $schedule['mode']; ?>')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger btn-action" onclick="deleteSchedule(<?php echo $schedule['id']; ?>, '<?php echo htmlspecialchars($schedule['subject_code']); ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <?php if(empty($schedules)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4">No schedules found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
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
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Professor <span class="text-danger">*</span></label>
                        <select name="professor_id" class="form-select" required>
                            <option value="">Select Professor</option>
                            <?php foreach($professors as $prof): ?>
                                <option value="<?php echo htmlspecialchars($prof['user_id']); ?>">
                                    <?php echo htmlspecialchars($prof['fullname']); ?> (<?php echo htmlspecialchars($prof['user_id']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Subject <span class="text-danger">*</span></label>
                        <select name="subject_id" class="form-select" required>
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
                        <select name="room_id" class="form-select" required>
                            <option value="">Select Room</option>
                            <?php foreach($rooms as $room): ?>
                                <option value="<?php echo $room['id']; ?>"><?php echo $room['room_code']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Day <span class="text-danger">*</span></label>
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
                            <label class="form-label">Start Time <span class="text-danger">*</span></label>
                            <input type="time" name="start_time" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Time <span class="text-danger">*</span></label>
                            <input type="time" name="end_time" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="mb-3 mt-3">
                        <label class="form-label">Mode <span class="text-danger">*</span></label>
                        <select name="mode" class="form-select" required>
                            <option value="F2F">Face to Face (F2F)</option>
                            <option value="Online">Online Class</option>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">School Year</label>
                            <input type="text" name="school_year" class="form-control" value="2024-2025">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Semester</label>
                            <select name="semester" class="form-select">
                                <option value="1">1st Semester</option>
                                <option value="2">2nd Semester</option>
                            </select>
                        </div>
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
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="editId">
                    
                    <div class="mb-3">
                        <label class="form-label">Professor <span class="text-danger">*</span></label>
                        <select name="professor_id" id="editProfessorId" class="form-select" required>
                            <option value="">Select Professor</option>
                            <?php foreach($professors as $prof): ?>
                                <option value="<?php echo htmlspecialchars($prof['user_id']); ?>">
                                    <?php echo htmlspecialchars($prof['fullname']); ?> (<?php echo htmlspecialchars($prof['user_id']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
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
                                <option value="<?php echo $room['id']; ?>"><?php echo $room['room_code']; ?></option>
                            <?php endforeach; ?>
                        </select>
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
                    
                    <div class="mb-3 mt-3">
                        <label class="form-label">Mode <span class="text-danger">*</span></label>
                        <select name="mode" id="editMode" class="form-select" required>
                            <option value="F2F">Face to Face (F2F)</option>
                            <option value="Online">Online Class</option>
                        </select>
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
                    <input type="hidden" name="action" value="delete">
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
    // Search functionality
    $('#searchInput').on('keyup', function() {
        const searchTerm = $(this).val().toLowerCase();
        $('#scheduleTable tbody tr').each(function() {
            const text = $(this).text().toLowerCase();
            $(this).toggle(text.includes(searchTerm));
        });
    });
    
    // Edit schedule function
    function editSchedule(id, professorId, subjectId, roomId, day, startTime, endTime, mode) {
        document.getElementById('editId').value = id;
        document.getElementById('editProfessorId').value = professorId;
        document.getElementById('editSubjectId').value = subjectId;
        document.getElementById('editRoomId').value = roomId;
        document.getElementById('editDay').value = day;
        document.getElementById('editStartTime').value = startTime;
        document.getElementById('editEndTime').value = endTime;
        document.getElementById('editMode').value = mode;
        
        new bootstrap.Modal(document.getElementById('editScheduleModal')).show();
    }
    
    // Delete schedule function
    function deleteSchedule(id, subjectName) {
        document.getElementById('deleteItemName').textContent = subjectName;
        document.getElementById('deleteId').value = id;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }
</script>
<?php
require_once '../includes/auth.php';
requireRole('admin');

// Handle room operations
$message = '';
$error = '';

// Add room
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_room') {
        $room_code = trim($_POST['room_code']);
        $room_type = $_POST['room_type'];
        $capacity = intval($_POST['capacity']);
        
        $stmt = $pdo->prepare("INSERT INTO rooms (room_code, room_type, capacity) VALUES (?, ?, ?)");
        if ($stmt->execute([$room_code, $room_type, $capacity])) {
            $message = "Room added successfully!";
        } else {
            $error = "Failed to add room. Room code may already exist.";
        }
    }
    
    // Edit room
    if ($_POST['action'] === 'edit_room') {
        $id = $_POST['id'];
        $room_code = trim($_POST['room_code']);
        $room_type = $_POST['room_type'];
        $capacity = intval($_POST['capacity']);
        $status = $_POST['status'];
        
        $stmt = $pdo->prepare("UPDATE rooms SET room_code = ?, room_type = ?, capacity = ?, status = ? WHERE id = ?");
        if ($stmt->execute([$room_code, $room_type, $capacity, $status, $id])) {
            $message = "Room updated successfully!";
        } else {
            $error = "Failed to update room.";
        }
    }
    
    // Delete room
    if ($_POST['action'] === 'delete_room') {
        $id = $_POST['id'];
        
        // Check if room has schedules
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM schedules WHERE room_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            $error = "Cannot delete room. It has existing schedules.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM rooms WHERE id = ?");
            if ($stmt->execute([$id])) {
                $message = "Room deleted successfully!";
            } else {
                $error = "Failed to delete room.";
            }
        }
    }
    
    // Add room schedule (for specific room)
    if ($_POST['action'] === 'add_schedule') {
        $room_id = $_POST['room_id'];
        $professor_id = $_POST['professor_id'];
        $subject_id = $_POST['subject_id'];
        $day = $_POST['day'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $mode = $_POST['mode'];
        $school_year = $_POST['school_year'] ?? '2024-2025';
        $semester = $_POST['semester'] ?? '1';
        
        // Check for schedule conflict
        $stmt = $pdo->prepare("
            SELECT * FROM schedules 
            WHERE room_id = ? AND day = ? 
            AND ((start_time <= ? AND end_time > ?) OR (start_time < ? AND end_time >= ?))
        ");
        $stmt->execute([$room_id, $day, $start_time, $start_time, $end_time, $end_time]);
        
        if ($stmt->rowCount() > 0) {
            $error = "Schedule conflict! This room is already booked at this time.";
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
    
    // Delete schedule
    if ($_POST['action'] === 'delete_schedule') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM schedules WHERE id = ?");
        if ($stmt->execute([$id])) {
            $message = "Schedule deleted successfully!";
        } else {
            $error = "Failed to delete schedule.";
        }
    }
}

// Get all rooms
$rooms = $pdo->query("SELECT * FROM rooms ORDER BY room_code")->fetchAll();

// Get schedules for selected room
$selected_room = $_GET['room_id'] ?? ($rooms[0]['id'] ?? 0);
$room_schedules = [];
$selected_room_data = null;

if ($selected_room) {
    $stmt = $pdo->prepare("
        SELECT s.*, 
               u.fullname as professor_name,
               sub.subject_code, sub.descriptive_title
        FROM schedules s
        JOIN users u ON s.professor_id = u.user_id
        JOIN subjects sub ON s.subject_id = sub.id
        WHERE s.room_id = ?
        ORDER BY FIELD(s.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), s.start_time
    ");
    $stmt->execute([$selected_room]);
    $room_schedules = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ?");
    $stmt->execute([$selected_room]);
    $selected_room_data = $stmt->fetch();
}

// Get professors for dropdown
$professors = $pdo->query("SELECT user_id, fullname FROM users WHERE role = 'professor' AND status = 'active' ORDER BY fullname")->fetchAll();

// Get subjects for dropdown
$subjects = $pdo->query("SELECT id, subject_code, descriptive_title FROM subjects ORDER BY subject_code")->fetchAll();

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$timeSlots = ['8:00', '9:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00', '17:00'];

// Build schedule grid
$schedule_grid = [];
foreach ($room_schedules as $schedule) {
    $day = $schedule['day'];
    $time = date('H:00', strtotime($schedule['start_time']));
    $schedule_grid[$day][$time] = $schedule;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Management - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --available: #28a745;
            --occupied: #dc3545;
            --maintenance: #ffc107;
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
        .room-card {
            background: white;
            border-radius: 12px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .room-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .room-card.active {
            border-color: var(--primary);
            background: linear-gradient(135deg, #667eea10, #764ba210);
        }
        .room-code {
            font-size: 18px;
            font-weight: 700;
            color: #2d3748;
        }
        .room-status {
            font-size: 12px;
            padding: 3px 10px;
            border-radius: 20px;
            display: inline-block;
        }
        .status-available {
            background: #d4edda;
            color: #155724;
        }
        .status-occupied {
            background: #f8d7da;
            color: #721c24;
        }
        .status-maintenance {
            background: #fff3cd;
            color: #856404;
        }
        .room-type {
            font-size: 12px;
            color: #718096;
        }
        .schedule-table th {
            background: #f8f9fa;
            text-align: center;
            padding: 12px;
        }
        .schedule-table td {
            text-align: center;
            vertical-align: middle;
            padding: 10px;
        }
        .schedule-cell {
            background: #e8f0fe;
            border-radius: 8px;
            padding: 6px;
            font-size: 12px;
        }
        .schedule-subject {
            font-weight: 600;
            color: var(--primary);
        }
        .empty-cell {
            color: #cbd5e0;
            font-size: 12px;
        }
        .modal-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        .btn-action {
            padding: 5px 10px;
            margin: 0 2px;
            border-radius: 8px;
            font-size: 12px;
        }
        .room-list {
            max-height: 500px;
            overflow-y: auto;
        }
        .capacity-badge {
            background: #e2e8f0;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 11px;
        }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>

<div class="container-fluid">
    <div class="content-card">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-door-open me-2 text-primary"></i>Room Management</h2>
            <button class="btn-add" data-bs-toggle="modal" data-bs-target="#addRoomModal">
                <i class="fas fa-plus me-2"></i>Add Room
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

        <div class="row">
            <!-- Room List Sidebar -->
            <div class="col-md-3">
                <div class="room-list">
                    <?php foreach($rooms as $room): ?>
                        <div class="room-card <?php echo $selected_room == $room['id'] ? 'active' : ''; ?>" 
                             onclick="location.href='?room_id=<?php echo $room['id']; ?>'">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="room-code"><?php echo htmlspecialchars($room['room_code']); ?></div>
                                    <div class="room-type">
                                        <i class="fas fa-building me-1"></i><?php echo ucfirst($room['room_type']); ?>
                                    </div>
                                </div>
                                <div>
                                    <span class="room-status status-<?php echo $room['status']; ?>">
                                        <?php echo ucfirst($room['status']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="mt-2">
                                <span class="capacity-badge">
                                    <i class="fas fa-users me-1"></i>Capacity: <?php echo $room['capacity']; ?>
                                </span>
                            </div>
                            <div class="mt-2">
                                <button class="btn btn-sm btn-primary btn-action" onclick="event.stopPropagation(); editRoom(<?php echo $room['id']; ?>, '<?php echo htmlspecialchars($room['room_code']); ?>', '<?php echo $room['room_type']; ?>', <?php echo $room['capacity']; ?>, '<?php echo $room['status']; ?>')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger btn-action" onclick="event.stopPropagation(); deleteRoom(<?php echo $room['id']; ?>, '<?php echo htmlspecialchars($room['room_code']); ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <button class="btn btn-sm btn-success btn-action" onclick="event.stopPropagation(); addSchedule(<?php echo $room['id']; ?>)">
                                    <i class="fas fa-calendar-plus"></i> Schedule
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if(empty($rooms)): ?>
                        <p class="text-muted text-center py-3">No rooms found. Click "Add Room" to create one.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Room Schedule Display -->
            <div class="col-md-9">
                <div class="content-card">
                    <?php if($selected_room_data): ?>
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4>
                                <i class="fas fa-door-open me-2 text-primary"></i>
                                Room <?php echo htmlspecialchars($selected_room_data['room_code']); ?>
                                <span class="room-status status-<?php echo $selected_room_data['status']; ?> ms-2">
                                    <?php echo ucfirst($selected_room_data['status']); ?>
                                </span>
                            </h4>
                            <div>
                                <span class="capacity-badge">
                                    <i class="fas fa-users me-1"></i>Capacity: <?php echo $selected_room_data['capacity']; ?>
                                </span>
                                <span class="room-type ms-2">
                                    <i class="fas fa-building me-1"></i><?php echo ucfirst($selected_room_data['room_type']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <h5 class="mb-3"><i class="fas fa-calendar-week me-2"></i>Weekly Schedule</h5>
                        
                        <?php if($room_schedules): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered schedule-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 100px;">Time</th>
                                            <?php foreach($days as $day): ?>
                                                <th><?php echo $day; ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($timeSlots as $time): ?>
                                            <tr>
                                                <td class="bg-light fw-bold"><?php echo $time; ?></td>
                                                <?php foreach($days as $day): ?>
                                                    <td>
                                                        <?php if(isset($schedule_grid[$day][$time])): 
                                                            $sch = $schedule_grid[$day][$time];
                                                        ?>
                                                            <div class="schedule-cell">
                                                                <div class="schedule-subject"><?php echo htmlspecialchars($sch['subject_code']); ?></div>
                                                                <div class="small"><?php echo htmlspecialchars($sch['professor_name']); ?></div>
                                                                <div class="small text-muted">
                                                                    <?php echo date('h:i A', strtotime($sch['start_time'])) . ' - ' . date('h:i A', strtotime($sch['end_time'])); ?>
                                                                </div>
                                                                <button class="btn btn-sm btn-danger btn-action mt-1" onclick="deleteSchedule(<?php echo $sch['id']; ?>, '<?php echo htmlspecialchars($sch['subject_code']); ?>')">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="empty-cell">—</div>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No schedules found for this room.</p>
                                <button class="btn-add" onclick="addSchedule(<?php echo $selected_room; ?>)">
                                    <i class="fas fa-plus me-2"></i>Add Schedule
                                </button>
                            </div>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-door-open fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Select a room from the left to view its schedule.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Room Modal -->
<div class="modal fade" id="addRoomModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add New Room</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_room">
                    <div class="mb-3">
                        <label class="form-label">Room Code <span class="text-danger">*</span></label>
                        <input type="text" name="room_code" class="form-control" placeholder="e.g., 3e1, 2e2, LAB101" required>
                        <small class="text-muted">Unique identifier for the room</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Room Type <span class="text-danger">*</span></label>
                        <select name="room_type" class="form-select" required>
                            <option value="classroom">Classroom</option>
                            <option value="laboratory">Laboratory</option>
                            <option value="office">Office</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Capacity</label>
                        <input type="number" name="capacity" class="form-control" value="40">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Room</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Room Modal -->
<div class="modal fade" id="editRoomModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Room</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_room">
                    <input type="hidden" name="id" id="editRoomId">
                    <div class="mb-3">
                        <label class="form-label">Room Code <span class="text-danger">*</span></label>
                        <input type="text" name="room_code" id="editRoomCode" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Room Type</label>
                        <select name="room_type" id="editRoomType" class="form-select" required>
                            <option value="classroom">Classroom</option>
                            <option value="laboratory">Laboratory</option>
                            <option value="office">Office</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Capacity</label>
                        <input type="number" name="capacity" id="editCapacity" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" id="editStatus" class="form-select" required>
                            <option value="available">Available</option>
                            <option value="occupied">Occupied</option>
                            <option value="maintenance">Maintenance</option>
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

<!-- Add Schedule Modal -->
<div class="modal fade" id="addScheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-calendar-plus me-2"></i>Add Room Schedule</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_schedule">
                    <input type="hidden" name="room_id" id="scheduleRoomId">
                    
                    <div class="mb-3">
                        <label class="form-label">Professor <span class="text-danger">*</span></label>
                        <select name="professor_id" class="form-select" required>
                            <option value="">Select Professor</option>
                            <?php foreach($professors as $prof): ?>
                                <option value="<?php echo htmlspecialchars($prof['user_id']); ?>">
                                    <?php echo htmlspecialchars($prof['fullname']); ?>
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
                        <label class="form-label">Day <span class="text-danger">*</span></label>
                        <select name="day" class="form-select" required>
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
                        <label class="form-label">Mode</label>
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

<!-- Delete Confirmation Modal -->
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
                <form method="POST">
                    <input type="hidden" name="action" id="deleteAction" value="delete_room">
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
    function editRoom(id, code, type, capacity, status) {
        document.getElementById('editRoomId').value = id;
        document.getElementById('editRoomCode').value = code;
        document.getElementById('editRoomType').value = type;
        document.getElementById('editCapacity').value = capacity;
        document.getElementById('editStatus').value = status;
        new bootstrap.Modal(document.getElementById('editRoomModal')).show();
    }
    
    function deleteRoom(id, name) {
        document.getElementById('deleteItemName').innerHTML = 'Room ' + name;
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteAction').value = 'delete_room';
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }
    
    function addSchedule(roomId) {
        document.getElementById('scheduleRoomId').value = roomId;
        new bootstrap.Modal(document.getElementById('addScheduleModal')).show();
    }
    
    function deleteSchedule(id, subjectName) {
        document.getElementById('deleteItemName').innerHTML = 'Schedule for ' + subjectName;
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteAction').value = 'delete_schedule';
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }
</script>
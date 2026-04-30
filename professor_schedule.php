<?php
require_once '../includes/auth.php';
requireRole('student');

$user = getUserData($_SESSION['user_id']);

// Get search parameter
$search = $_GET['search'] ?? '';
$selected_professor = $_GET['professor_id'] ?? '';

// Get all professors
if (!empty($search)) {
    $stmt = $pdo->prepare("
        SELECT user_id, fullname, email, picture 
        FROM users 
        WHERE role = 'professor' AND status = 'active' 
        AND (fullname LIKE ? OR user_id LIKE ? OR email LIKE ?)
        ORDER BY fullname
    ");
    $search_param = "%$search%";
    $stmt->execute([$search_param, $search_param, $search_param]);
} else {
    $stmt = $pdo->query("
        SELECT user_id, fullname, email, picture 
        FROM users 
        WHERE role = 'professor' AND status = 'active' 
        ORDER BY fullname
    ");
}
$professors = $stmt->fetchAll();

// If no professor selected and there are professors, select the first one
if (empty($selected_professor) && !empty($professors)) {
    $selected_professor = $professors[0]['user_id'];
}

// Get schedules for selected professor
$schedules = [];
$professor_info = null;

if ($selected_professor) {
    // Get professor info
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? AND role = 'professor'");
    $stmt->execute([$selected_professor]);
    $professor_info = $stmt->fetch();
    
    // Get schedules with professor_note
    $stmt = $pdo->prepare("
        SELECT s.*, 
               sub.subject_code, sub.descriptive_title, sub.units,
               r.room_code, r.room_type
        FROM schedules s
        JOIN subjects sub ON s.subject_id = sub.id
        JOIN rooms r ON s.room_id = r.id
        WHERE s.professor_id = ?
        ORDER BY FIELD(s.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), s.start_time
    ");
    $stmt->execute([$selected_professor]);
    $schedules = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professor Schedule - Academic Advising System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
        }
        .schedule-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            padding: 25px;
            margin-bottom: 25px;
        }
        .professor-card {
            background: white;
            border-radius: 12px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .professor-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .professor-card.active {
            border-color: var(--primary);
            background: linear-gradient(135deg, #667eea10, #764ba210);
        }
        .professor-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }
        .professor-name {
            font-weight: 600;
            color: #2d3748;
        }
        .professor-email {
            font-size: 12px;
            color: #718096;
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
        /* FULL ROW RED background when professor note exists */
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
        .search-box {
            margin-bottom: 20px;
        }
        .professor-list {
            max-height: 500px;
            overflow-y: auto;
        }
        .no-results {
            text-align: center;
            padding: 40px;
            color: #718096;
        }
        .professor-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        .professor-header-img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
        }
        .professor-note {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 12px;
            max-width: 250px;
        }
        .professor-note i {
            color: #ffc107;
            margin-right: 5px;
        }
        @media (max-width: 768px) {
            .schedule-table {
                font-size: 12px;
            }
            .professor-header {
                flex-direction: column;
                text-align: center;
            }
            .professor-note {
                max-width: 180px;
            }
        }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>

<div class="container-fluid">
    <div class="schedule-card">
        <h3><i class="fas fa-chalkboard-teacher me-2 text-primary"></i>Professor Schedule</h3>
        <p class="text-muted">Search and view schedules of professors</p>
    </div>

    <div class="row">
        <!-- Professor List Sidebar -->
        <div class="col-md-4">
            <div class="schedule-card">
                <h5><i class="fas fa-users me-2 text-primary"></i>Professors</h5>
                
                <!-- Search Box -->
                <div class="search-box">
                    <form method="GET" action="">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Search by name, ID, or email..." value="<?php echo htmlspecialchars($search); ?>">
                            <?php if(!empty($search)): ?>
                                <a href="professor_schedule.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i>
                                </a>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Professor List -->
                <div class="professor-list">
                    <?php if($professors): ?>
                        <?php foreach($professors as $prof): ?>
                            <div class="professor-card <?php echo $selected_professor == $prof['user_id'] ? 'active' : ''; ?>" 
                                 onclick="location.href='?professor_id=<?php echo urlencode($prof['user_id']); ?>&search=<?php echo urlencode($search); ?>'">
                                <div class="d-flex align-items-center">
                                    <?php 
                                    $prof_picture = '../uploads/professors/' . ($prof['picture'] ?? 'default.png');
                                    if (!file_exists($prof_picture)) {
                                        $prof_picture = '../uploads/professors/default.png';
                                    }
                                    ?>
                                    <img src="<?php echo $prof_picture; ?>" alt="<?php echo htmlspecialchars($prof['fullname']); ?>" class="professor-avatar me-3">
                                    <div>
                                        <div class="professor-name"><?php echo htmlspecialchars($prof['fullname']); ?></div>
                                        <div class="professor-email"><?php echo htmlspecialchars($prof['email']); ?></div>
                                        <div class="professor-email">ID: <?php echo htmlspecialchars($prof['user_id']); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-results">
                            <i class="fas fa-user-slash fa-2x mb-2"></i>
                            <p>No professors found matching your search.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Schedule Display -->
        <div class="col-md-8">
            <div class="schedule-card">
                <?php if($professor_info): ?>
                    <!-- Professor Header -->
                    <div class="professor-header">
                        <?php 
                        $prof_picture = '../uploads/professors/' . ($professor_info['picture'] ?? 'default.png');
                        if (!file_exists($prof_picture)) {
                            $prof_picture = '../uploads/professors/default.png';
                        }
                        ?>
                        <img src="<?php echo $prof_picture; ?>" alt="<?php echo htmlspecialchars($professor_info['fullname']); ?>" class="professor-header-img">
                        <div>
                            <h4 class="mb-1"><?php echo htmlspecialchars($professor_info['fullname']); ?></h4>
                            <p class="text-muted mb-1">
                                <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($professor_info['email']); ?>
                            </p>
                            <p class="text-muted mb-0">
                                <i class="fas fa-id-card me-1"></i><?php echo htmlspecialchars($professor_info['user_id']); ?>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Schedule Table -->
                    <?php if($schedules): ?>
                        <h5 class="mb-3"><i class="fas fa-calendar-alt me-2 text-primary"></i>Class Schedule</h5>
                        <div class="table-responsive">
                            <table class="schedule-table">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Subject</th>
                                        <th>Day</th>
                                        <th>Room</th>
                                        <th>Mode</th>
                                        <th>Professor Note</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($schedules as $schedule): ?>
                                        <tr class="<?php echo (!empty($schedule['professor_note'])) ? 'schedule-row-with-note' : ''; ?>">
                                            <td>
                                                <i class="fas fa-clock text-primary me-1"></i>
                                                <?php echo date('h:i A', strtotime($schedule['start_time'])) . ' - ' . date('h:i A', strtotime($schedule['end_time'])); ?>
                                             </div>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($schedule['subject_code']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($schedule['descriptive_title']); ?></small>
                                             </div>
                                            <td>
                                                <span class="day-badge day-<?php echo strtolower($schedule['day']); ?>">
                                                    <i class="fas fa-calendar-day me-1"></i>
                                                    <?php echo $schedule['day']; ?>
                                                </span>
                                             </div>
                                            <td>
                                                <i class="fas fa-door-open me-1 text-muted"></i>
                                                <?php echo $schedule['room_code']; ?>
                                             </div>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower($schedule['mode']); ?>">
                                                    <i class="fas <?php echo $schedule['mode'] == 'F2F' ? 'fa-chalkboard' : 'fa-laptop'; ?> me-1"></i>
                                                    <?php echo $schedule['mode']; ?>
                                                </span>
                                             </div>
                                            <td>
                                                <?php if(!empty($schedule['professor_note'])): ?>
                                                    <div class="professor-note">
                                                        <i class="fas fa-sticky-note"></i>
                                                        <?php echo nl2br(htmlspecialchars($schedule['professor_note'])); ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                             </div>
                                         </div>
                                    <?php endforeach; ?>
                                </tbody>
                             </div>
                        </div>
                    <?php else: ?>
                        <div class="no-results">
                            <i class="fas fa-calendar-times fa-3x mb-3"></i>
                            <p class="text-muted">No schedule found for this professor.</p>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="no-results">
                        <i class="fas fa-chalkboard-teacher fa-3x mb-3"></i>
                        <p class="text-muted">Select a professor from the left to view their schedule.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
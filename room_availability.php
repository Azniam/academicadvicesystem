<?php
require_once '../includes/auth.php';
requireRole('student');

// Get all rooms
$stmt = $pdo->prepare("SELECT * FROM rooms ORDER BY room_code");
$stmt->execute();
$rooms = $stmt->fetchAll();

// Days of week
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Availability - Academic Advising System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .room-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .room-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .room-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .room-card.available {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border: 2px solid #28a745;
        }
        .room-card.occupied {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            border: 2px solid #dc3545;
        }
        .room-card.office {
            background: #e9ecef;
            border: 2px solid #6c757d;
            opacity: 0.7;
        }
        .room-code {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .room-status {
            font-size: 0.9rem;
            font-weight: 600;
        }
        .schedule-list {
            max-height: 400px;
            overflow-y: auto;
        }
        .schedule-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 12px 15px;
            margin-bottom: 10px;
            transition: all 0.3s;
        }
        .schedule-item:hover {
            background: #e8f0fe;
            transform: translateX(5px);
        }
        .schedule-day {
            font-weight: 700;
            color: var(--primary);
        }
        .no-schedule {
            text-align: center;
            padding: 40px;
            color: #718096;
        }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-door-open me-2"></i>Room Availability</h2>
        <div>
            <label class="me-2">Filter:</label>
            <select id="roomFilter" class="form-select d-inline-block w-auto">
                <option value="all">All Rooms</option>
                <option value="available">Available Only</option>
                <option value="occupied">Occupied Only</option>
            </select>
        </div>
    </div>

    <div class="room-grid" id="roomGrid">
        <?php foreach($rooms as $room): ?>
            <?php
                $statusClass = 'available';
                if($room['room_type'] == 'office') {
                    $statusClass = 'office';
                } else {
                    $statusClass = $room['status'];
                }
            ?>
            <div class="room-card <?php echo $statusClass; ?>" data-room="<?php echo $room['room_code']; ?>" data-room-id="<?php echo $room['id']; ?>" data-status="<?php echo $statusClass; ?>">
                <div class="room-code"><?php echo htmlspecialchars($room['room_code']); ?></div>
                <div class="room-status">
                    <?php if($statusClass == 'available'): ?>
                        <span class="text-success"><i class="fas fa-check-circle"></i> Available</span>
                    <?php elseif($statusClass == 'occupied'): ?>
                        <span class="text-danger"><i class="fas fa-times-circle"></i> Occupied</span>
                    <?php else: ?>
                        <span class="text-secondary"><i class="fas fa-building"></i> Faculty/Dean Office</span>
                    <?php endif; ?>
                </div>
                <div class="mt-2 small text-muted">
                    <i class="fas fa-users"></i> Capacity: <?php echo $room['capacity']; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Schedule Modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-calendar-alt me-2"></i>Room Schedule</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h4 id="modalRoomCode" class="mb-3"></h4>
                <div id="scheduleContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    $('.room-card').click(function() {
        var roomCode = $(this).data('room');
        var roomId = $(this).data('room-id');
        $('#modalRoomCode').text('Room ' + roomCode + ' Schedule');
        
        // Show loading
        $('#scheduleContent').html('<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>');
        
        $.ajax({
            url: '../api/get_room_schedule_details.php',
            method: 'POST',
            data: { room_id: roomId, room_code: roomCode },
            dataType: 'json',
            success: function(data) {
                var html = '';
                
                if(data.schedules && data.schedules.length > 0) {
                    html += '<div class="schedule-list">';
                    $.each(data.schedules, function(index, schedule) {
                        html += '<div class="schedule-item">';
                        html += '<div class="row align-items-center">';
                        html += '<div class="col-md-3">';
                        html += '<span class="schedule-day"><i class="fas fa-calendar-day me-2"></i>' + schedule.day + '</span>';
                        html += '</div>';
                        html += '<div class="col-md-3">';
                        html += '<i class="fas fa-clock me-2 text-muted"></i>' + schedule.time;
                        html += '</div>';
                        html += '<div class="col-md-4">';
                        html += '<strong><i class="fas fa-book me-2 text-primary"></i>' + schedule.subject_code + '</strong><br>';
                        html += '<small class="text-muted">' + schedule.subject_title + '</small>';
                        html += '</div>';
                        html += '<div class="col-md-2">';
                        html += '<span class="badge ' + (schedule.mode == 'F2F' ? 'bg-success' : 'bg-info') + '">';
                        html += '<i class="fas ' + (schedule.mode == 'F2F' ? 'fa-chalkboard' : 'fa-laptop') + ' me-1"></i>' + schedule.mode;
                        html += '</span>';
                        html += '</div>';
                        html += '</div>';
                        html += '<div class="row mt-2">';
                        html += '<div class="col-12">';
                        html += '<small class="text-muted"><i class="fas fa-chalkboard-teacher me-1"></i>Professor: ' + schedule.professor_name + '</small>';
                        html += '</div>';
                        html += '</div>';
                        html += '</div>';
                    });
                    html += '</div>';
                } else {
                    html = '<div class="no-schedule">';
                    html += '<i class="fas fa-calendar-times fa-3x mb-3"></i>';
                    html += '<p class="mb-0">No schedules found for this room.</p>';
                    html += '</div>';
                }
                
                $('#scheduleContent').html(html);
                $('#scheduleModal').modal('show');
            },
            error: function() {
                $('#scheduleContent').html('<div class="no-schedule"><i class="fas fa-exclamation-triangle fa-3x mb-3 text-warning"></i><p class="mb-0">Failed to load schedule. Please try again.</p></div>');
                $('#scheduleModal').modal('show');
            }
        });
    });
    
    $('#roomFilter').change(function() {
        var filter = $(this).val();
        $('.room-card').each(function() {
            if(filter == 'all') {
                $(this).show();
            } else if(filter == 'available') {
                if($(this).data('status') == 'available') {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            } else if(filter == 'occupied') {
                if($(this).data('status') == 'occupied') {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            }
        });
    });
});
</script>
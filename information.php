<?php
require_once '../includes/auth.php';
requireRole('student');

$user = getUserData($_SESSION['user_id']);

// Handle profile update
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $section = trim($_POST['section'] ?? '');
        
        if (empty($fullname) || empty($email)) {
            $error = 'Please fill in all fields';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address';
        } else {
            $stmt = $pdo->prepare("UPDATE users SET fullname = ?, email = ?, section = ? WHERE user_id = ?");
            if ($stmt->execute([$fullname, $email, $section, $user['user_id']])) {
                $_SESSION['fullname'] = $fullname;
                $message = 'Profile updated successfully!';
                $user = getUserData($_SESSION['user_id']);
            } else {
                $error = 'Failed to update profile';
            }
        }
    }
    
    if ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'Please fill in all password fields';
        } elseif (strlen($new_password) < 6) {
            $error = 'New password must be at least 6 characters long';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match';
        } else {
            $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
            $stmt->execute([$user['user_id']]);
            $db_password = $stmt->fetch()['password'];
            
            if (password_verify($current_password, $db_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                if ($stmt->execute([$hashed_password, $user['user_id']])) {
                    $message = 'Password changed successfully!';
                } else {
                    $error = 'Failed to change password';
                }
            } else {
                $error = 'Current password is incorrect';
            }
        }
    }
    
    if ($action === 'update_picture') {
        if (isset($_FILES['picture']) && $_FILES['picture']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['picture']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $picture = time() . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $user['user_id']) . '.' . $ext;
                $upload_path = '../uploads/students/';
                
                if (!file_exists($upload_path)) {
                    mkdir($upload_path, 0777, true);
                }
                
                if (move_uploaded_file($_FILES['picture']['tmp_name'], $upload_path . $picture)) {
                    $stmt = $pdo->prepare("UPDATE users SET picture = ? WHERE user_id = ?");
                    if ($stmt->execute([$picture, $user['user_id']])) {
                        $message = 'Profile picture updated successfully!';
                        $user = getUserData($_SESSION['user_id']);
                    } else {
                        $error = 'Failed to update profile picture';
                    }
                } else {
                    $error = 'Failed to upload image';
                }
            } else {
                $error = 'Invalid file type. Allowed: jpg, jpeg, png, gif';
            }
        } else {
            $error = 'Please select a file to upload';
        }
    }
}

// Get subjects taken
$stmt = $pdo->prepare("
    SELECT s.subject_code, s.descriptive_title, s.units, s.year_level, s.semester, g.grade, g.status
    FROM subjects s
    LEFT JOIN grades g ON s.id = g.subject_id AND g.student_id = ?
    WHERE s.course = ?
    ORDER BY s.year_level, s.semester
");
$stmt->execute([$user['user_id'], $user['course']]);
$subjects_taken = $stmt->fetchAll();

$picture_path = '../uploads/students/' . ($user['picture'] ?? 'default.png');
if (!file_exists($picture_path)) {
    $picture_path = '../uploads/students/default.png';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Information - Academic Advising System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #00A7E1;
            --secondary: #F17720;
        }
        .profile-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 25px;
        }
        .profile-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            padding: 30px;
            text-align: center;
            color: white;
            position: relative;
        }
        .profile-picture {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            margin-bottom: 15px;
            cursor: pointer;
        }
        .profile-picture-wrapper {
            position: relative;
            display: inline-block;
        }
        .camera-icon {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: white;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .info-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .info-label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }
        .info-value {
            font-size: 16px;
            font-weight: 600;
            color: #2d3748;
        }
        .btn-edit {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            padding: 8px 20px;
            border-radius: 25px;
            color: white;
            font-size: 14px;
        }
        .btn-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,167,225,0.4);
            color: white;
        }
        .picture-upload {
            display: none;
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-passed {
            background: #d4edda;
            color: #155724;
        }
        .status-failed {
            background: #f8d7da;
            color: #721c24;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .table-subjects {
            font-size: 14px;
        }
        .table-subjects th {
            background: #f8f9fa;
        }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>

<div class="container-fluid">
    <!-- Messages -->
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
        <!-- Left Column - Profile Picture -->
        <div class="col-md-4">
            <div class="profile-card">
                <div class="profile-header">
                    <div class="profile-picture-wrapper">
                        <img id="profilePreview" src="<?php echo $picture_path; ?>" alt="Profile Picture" class="profile-picture" onclick="document.getElementById('pictureUpload').click()">
                        <div class="camera-icon" onclick="document.getElementById('pictureUpload').click()">
                            <i class="fas fa-camera"></i>
                        </div>
                    </div>
                    <h4 class="mb-1"><?php echo htmlspecialchars($user['fullname']); ?></h4>
                    <p class="mb-0 opacity-75">
                        <i class="fas fa-id-card me-1"></i><?php echo htmlspecialchars($user['user_id']); ?>
                    </p>
                </div>
                <div class="p-4">
                    <form method="POST" enctype="multipart/form-data" id="pictureForm">
                        <input type="file" name="picture" id="pictureUpload" class="picture-upload" accept="image/*">
                        <input type="hidden" name="action" value="update_picture">
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Column - Profile Details -->
        <div class="col-md-8">
            <!-- Personal Information Card -->
            <div class="info-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5><i class="fas fa-user-circle me-2 text-primary"></i>Personal Information</h5>
                    <button class="btn-edit" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                        <i class="fas fa-edit me-2"></i>Edit Profile
                    </button>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="info-label">Student ID</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['user_id']); ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="info-label">Full Name</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['fullname']); ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="info-label">Email Address</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="info-label">Course</div>
                        <div class="info-value">
                            <?php echo $user['course'] == 'CE' ? 'Civil Engineering' : 'Computer Engineering'; ?>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="info-label">Year Level</div>
                        <div class="info-value">
                            <?php echo $user['year_level']; ?> Year
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="info-label">Section</div>
                        <div class="info-value">Section <?php echo htmlspecialchars($user['section']); ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="info-label">Status</div>
                        <div class="info-value">
                            <span class="badge <?php echo $user['status'] == 'active' ? 'bg-success' : 'bg-danger'; ?>">
                                <?php echo ucfirst($user['status']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="info-label">Enrolled Since</div>
                        <div class="info-value"><?php echo date('F d, Y', strtotime($user['created_at'])); ?></div>
                    </div>
                </div>
            </div>

            <!-- Subjects Taken Card -->
            <div class="info-card">
                <h5><i class="fas fa-book me-2 text-primary"></i>Subjects Taken</h5>
                <div class="table-responsive">
                    <table class="table table-hover table-subjects">
                        <thead>
                            <tr>
                                <th>Subject Code</th>
                                <th>Descriptive Title</th>
                                <th>Units</th>
                                <th>Year/Sem</th>
                                <th>Grade</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($subjects_taken as $subject): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($subject['subject_code']); ?></td>
                                    <td><?php echo htmlspecialchars($subject['descriptive_title']); ?></td>
                                    <td class="text-center"><?php echo $subject['units']; ?></td>
                                    <td class="text-center"><?php echo $subject['year_level'] . '/' . ($subject['semester'] == 1 ? '1st' : '2nd'); ?></td>
                                    <td class="text-center">
                                        <?php if($subject['grade']): ?>
                                            <strong><?php echo number_format($subject['grade'], 2); ?></strong>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($subject['status'] == 'PASSED'): ?>
                                            <span class="status-badge status-passed">PASSED</span>
                                        <?php elseif($subject['status'] == 'FAILED'): ?>
                                            <span class="status-badge status-failed">FAILED</span>
                                        <?php else: ?>
                                            <span class="status-badge status-pending">PENDING</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Change Password Card -->
            <div class="info-card">
                <h5><i class="fas fa-key me-2 text-primary"></i>Security</h5>
                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                    <i class="fas fa-lock me-2"></i>Change Password
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #00A7E1, #F17720); color: white;">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Profile</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="fullname" class="form-control" value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Section</label>
                        <select name="section" class="form-select">
                            <option value="A" <?php echo $user['section'] == 'A' ? 'selected' : ''; ?>>Section A</option>
                            <option value="B" <?php echo $user['section'] == 'B' ? 'selected' : ''; ?>>Section B</option>
                            <option value="C" <?php echo $user['section'] == 'C' ? 'selected' : ''; ?>>Section C</option>
                            <option value="D" <?php echo $user['section'] == 'D' ? 'selected' : ''; ?>>Section D</option>
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

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #00A7E1, #F17720); color: white;">
                <h5 class="modal-title"><i class="fas fa-key me-2"></i>Change Password</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="change_password">
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required>
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Change Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Picture upload preview
    document.getElementById('pictureUpload').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('profilePreview').src = e.target.result;
            }
            reader.readAsDataURL(file);
            document.getElementById('pictureForm').submit();
        }
    });
</script>
<?php
require_once '../includes/auth.php';
requireRole('admin');
require_once '../vendor/autoload.php'; // PHPMailer autoload

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$message = '';
$error = '';
$step = 1; // 1: Form, 2: OTP Verification
$temp_data = [];

// Start session for OTP storage
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// PHPMailer configuration function
function sendOTPEmail($to_email, $to_name, $otp, $account_type) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->SMTPDebug = SMTP::DEBUG_OFF;
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'azniamedaj@gmail.com';
        $mail->Password   = 'nrcs cotu wpql smqj';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Recipients
        $mail->setFrom('azniamedaj@gmail.com', 'Academic Advising System');
        $mail->addAddress($to_email, $to_name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Account Creation OTP - Academic Advising System';
        
        // Email body
        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px; }
                .container { max-width: 500px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 20px; text-align: center; }
                .header h2 { margin: 0; font-size: 24px; }
                .content { padding: 30px; text-align: center; }
                .otp-code { font-size: 36px; font-weight: bold; color: #667eea; letter-spacing: 5px; margin: 20px 0; padding: 10px; background: #f0f0f0; border-radius: 8px; display: inline-block; }
                .account-type { background: #e8f0fe; padding: 10px; border-radius: 8px; margin: 15px 0; }
                .footer { background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>Academic Advising System</h2>
                    <p>Account Creation Verification</p>
                </div>
                <div class="content">
                    <p>Dear <strong>' . htmlspecialchars($to_name) . '</strong>,</p>
                    <p>An administrator is creating a <strong>' . strtoupper($account_type) . '</strong> account for you.</p>
                    <div class="account-type">
                        <strong>Account Type:</strong> ' . ucfirst($account_type) . '
                    </div>
                    <p>Please use the OTP code below to verify your email address:</p>
                    <div class="otp-code">' . $otp . '</div>
                    <p>This OTP is valid for <strong>10 minutes</strong>.</p>
                    <p>If you did not request this, please ignore this email.</p>
                </div>
                <div class="footer">
                    <p>&copy; 2024 Academic Advising System. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ';
        
        $mail->AltBody = "Dear $to_name,\n\nAn administrator is creating a $account_type account for you.\n\nOTP Code: $otp\n\nThis OTP is valid for 10 minutes.\n\nIf you did not request this, please ignore this email.\n\nRegards,\nAcademic Advising System";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

// Generate random OTP
function generateOTP() {
    return sprintf("%06d", mt_rand(1, 999999));
}

// Handle account creation with OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Step 1: Send OTP for verification
    if ($action === 'send_otp') {
        $account_type = $_POST['account_type'] ?? '';
        $user_id = trim($_POST['user_id'] ?? '');
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $course = $_POST['course'] ?? '';
        $section = $_POST['section'] ?? '';
        $year_level = $_POST['year_level'] ?? 1;
        
        // Validation
        if (empty($user_id) || empty($fullname) || empty($email) || empty($password)) {
            $error = 'Please fill in all fields';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match';
        } else {
            // Check if user ID already exists
            $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            if ($stmt->fetch()) {
                $error = ucfirst($account_type) . ' ID already exists';
            } else {
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = 'Email already exists';
                } else {
                    // Generate OTP
                    $otp = generateOTP();
                    $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                    
                    // Store temporary data in session
                    $_SESSION['temp_account'] = [
                        'account_type' => $account_type,
                        'user_id' => $user_id,
                        'fullname' => $fullname,
                        'email' => $email,
                        'password' => password_hash($password, PASSWORD_DEFAULT),
                        'course' => $course,
                        'section' => $section,
                        'year_level' => $year_level,
                        'otp' => $otp,
                        'otp_expiry' => $expiry
                    ];
                    
                    // Send OTP email
                    if (sendOTPEmail($email, $fullname, $otp, $account_type)) {
                        $step = 2;
                        $message = 'An OTP has been sent to the email address. Please verify to complete account creation.';
                    } else {
                        $error = 'Failed to send OTP email. Please try again.';
                        unset($_SESSION['temp_account']);
                    }
                }
            }
        }
    }
    
    // Step 2: Verify OTP and create account
    if ($action === 'verify_otp') {
        $otp = trim($_POST['otp'] ?? '');
        
        if (empty($otp)) {
            $error = 'Please enter the OTP code';
        } elseif (!isset($_SESSION['temp_account'])) {
            $error = 'Session expired. Please start over.';
            $step = 1;
        } else {
            $temp = $_SESSION['temp_account'];
            
            if ($temp['otp'] !== $otp) {
                $error = 'Invalid OTP code. Please try again.';
            } elseif (strtotime($temp['otp_expiry']) < time()) {
                $error = 'OTP has expired. Please start over.';
                unset($_SESSION['temp_account']);
                $step = 1;
            } else {
                // Create account based on type
                $account_type = $temp['account_type'];
                $hashed_password = $temp['password'];
                
                if ($account_type === 'admin') {
                    $stmt = $pdo->prepare("
                        INSERT INTO users (user_id, fullname, email, password, role, status) 
                        VALUES (?, ?, ?, ?, 'admin', 'active')
                    ");
                    $success = $stmt->execute([$temp['user_id'], $temp['fullname'], $temp['email'], $hashed_password]);
                    $success_msg = "Admin account created successfully!";
                } elseif ($account_type === 'professor') {
                    $stmt = $pdo->prepare("
                        INSERT INTO users (user_id, fullname, email, password, role, status) 
                        VALUES (?, ?, ?, ?, 'professor', 'active')
                    ");
                    $success = $stmt->execute([$temp['user_id'], $temp['fullname'], $temp['email'], $hashed_password]);
                    $success_msg = "Professor account created successfully!";
                } else {
                    // Student account
                    $stmt = $pdo->prepare("
                        INSERT INTO users (user_id, fullname, email, password, role, course, section, year_level, picture, status) 
                        VALUES (?, ?, ?, ?, 'student', ?, ?, ?, 'default.png', 'active')
                    ");
                    $success = $stmt->execute([
                        $temp['user_id'], 
                        $temp['fullname'], 
                        $temp['email'], 
                        $hashed_password,
                        $temp['course'],
                        $temp['section'],
                        $temp['year_level']
                    ]);
                    $success_msg = "Student account created successfully!";
                }
                
                if ($success) {
                    unset($_SESSION['temp_account']);
                    $message = $success_msg;
                    $step = 3; // Completion step
                } else {
                    $error = "Failed to create account. Please try again.";
                }
            }
        }
    }
    
    // Resend OTP
    if ($action === 'resend_otp') {
        if (isset($_SESSION['temp_account'])) {
            $temp = $_SESSION['temp_account'];
            $new_otp = generateOTP();
            $new_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            $_SESSION['temp_account']['otp'] = $new_otp;
            $_SESSION['temp_account']['otp_expiry'] = $new_expiry;
            
            if (sendOTPEmail($temp['email'], $temp['fullname'], $new_otp, $temp['account_type'])) {
                $message = 'A new OTP has been sent to the email address.';
            } else {
                $error = 'Failed to resend OTP. Please try again.';
            }
        } else {
            $error = 'Session expired. Please start over.';
            $step = 1;
        }
    }
}

// Get existing counts
$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
$admin_count = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'professor'");
$professor_count = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'");
$student_count = $stmt->fetch()['count'];

// Get recent accounts
$recent_admins = $pdo->query("
    SELECT user_id, fullname, email, created_at 
    FROM users 
    WHERE role = 'admin' 
    ORDER BY created_at DESC 
    LIMIT 5
")->fetchAll();

$recent_professors = $pdo->query("
    SELECT user_id, fullname, email, created_at 
    FROM users 
    WHERE role = 'professor' 
    ORDER BY created_at DESC 
    LIMIT 5
")->fetchAll();

$recent_students = $pdo->query("
    SELECT user_id, fullname, email, course, section, created_at 
    FROM users 
    WHERE role = 'student' 
    ORDER BY created_at DESC 
    LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - Admin</title>
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
            margin-bottom: 25px;
        }
        .form-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            height: 100%;
        }
        .form-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 15px 20px;
            margin: -25px -25px 20px -25px;
            border-radius: 15px 15px 0 0;
        }
        .btn-create {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            padding: 10px 25px;
            border-radius: 25px;
            color: white;
            width: 100%;
        }
        .btn-create:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
            color: white;
        }
        .btn-verify {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            padding: 12px;
            border-radius: 25px;
            color: white;
            width: 100%;
            font-weight: bold;
        }
        .btn-verify:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40,167,69,0.4);
            color: white;
        }
        .stat-box {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            text-align: center;
        }
        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary);
        }
        .recent-list {
            max-height: 300px;
            overflow-y: auto;
        }
        .recent-item {
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
            transition: all 0.3s;
        }
        .recent-item:hover {
            background: #f8f9fa;
        }
        .password-hint {
            font-size: 12px;
            color: #718096;
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
        }
        .nav-tabs-custom .nav-link.active {
            color: var(--primary);
            border-bottom: 3px solid var(--primary);
            background: transparent;
        }
        .otp-input {
            text-align: center;
            font-size: 1.5rem;
            letter-spacing: 10px;
            font-weight: bold;
        }
        .countdown {
            font-size: 0.85rem;
            color: #718096;
            text-align: center;
            margin-top: 15px;
        }
        .form-control.password-match {
            border-color: #28a745;
            background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%2328a745"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>');
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 20px;
        }
        .form-control.password-mismatch {
            border-color: #dc3545;
            background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23dc3545"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41z"/></svg>');
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 20px;
        }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>

<div class="container-fluid">
    <div class="content-card">
        <h2><i class="fas fa-user-plus me-2 text-primary"></i>Create Account</h2>
        <p class="text-muted">Create new admin, professor, or student accounts</p>
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

    <?php if($step == 3): ?>
        <div class="text-center mt-3">
            <a href="create_account.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Create Another Account
            </a>
        </div>
    <?php elseif($step == 2): ?>
        <!-- OTP Verification Step -->
        <div class="content-card">
            <div class="text-center mb-4">
                <i class="fas fa-envelope fa-4x text-primary mb-3"></i>
                <h4>Email Verification Required</h4>
                <p class="text-muted">We've sent a 6-digit verification code to:</p>
                <p class="fw-bold"><?php echo htmlspecialchars($_SESSION['temp_account']['email'] ?? ''); ?></p>
                <div class="alert alert-info">
                    <small><i class="fas fa-info-circle me-1"></i> Please check the email inbox and enter the OTP code below to complete account creation</small>
                </div>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="verify_otp">
                
                <div class="mb-4">
                    <label class="form-label text-center d-block fw-bold">Enter OTP Code</label>
                    <div class="row justify-content-center">
                        <div class="col-md-6">
                            <input type="text" name="otp" class="form-control otp-input text-center" 
                                   placeholder="000000" maxlength="6" required autofocus>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn-verify">
                    <i class="fas fa-check-circle me-2"></i>Verify OTP & Create Account
                </button>
                
                <div class="countdown">
                    <i class="fas fa-clock me-1"></i>
                    <span id="countdown"></span>
                    <a href="?resend=1" class="text-decoration-none ms-2" id="resendLink" style="display: none;">
                        <i class="fas fa-redo me-1"></i>Resend OTP
                    </a>
                </div>
            </form>
        </div>
        
        <script>
            let timeLeft = 600;
            const countdownElement = document.getElementById('countdown');
            const resendLink = document.getElementById('resendLink');
            
            function updateCountdown() {
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                countdownElement.textContent = `OTP expires in ${minutes}:${seconds.toString().padStart(2, '0')}`;
                
                if (timeLeft <= 0) {
                    countdownElement.innerHTML = 'OTP has expired. ';
                    if (resendLink) resendLink.style.display = 'inline';
                } else {
                    timeLeft--;
                    setTimeout(updateCountdown, 1000);
                }
            }
            updateCountdown();
        </script>
        
    <?php else: ?>
        <!-- Statistics Row -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-box">
                    <div class="stat-number"><?php echo $admin_count; ?></div>
                    <div class="text-muted">Total Admins</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-box">
                    <div class="stat-number"><?php echo $professor_count; ?></div>
                    <div class="text-muted">Total Professors</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-box">
                    <div class="stat-number"><?php echo $student_count; ?></div>
                    <div class="text-muted">Total Students</div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs-custom" id="accountTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#adminTab">
                    <i class="fas fa-user-shield me-2"></i>Admin
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#professorTab">
                    <i class="fas fa-chalkboard-teacher me-2"></i>Professor
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#studentTab">
                    <i class="fas fa-user-graduate me-2"></i>Student
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <!-- Admin Account Tab -->
            <div class="tab-pane fade show active" id="adminTab">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-card">
                            <div class="form-header">
                                <h5 class="mb-0"><i class="fas fa-user-shield me-2"></i>New Admin Account</h5>
                            </div>
                            <form method="POST">
                                <input type="hidden" name="action" value="send_otp">
                                <input type="hidden" name="account_type" value="admin">
                                
                                <div class="mb-3">
                                    <label class="form-label">Admin ID <span class="text-danger">*</span></label>
                                    <input type="text" name="user_id" class="form-control" placeholder="e.g., ADMIN002" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" name="fullname" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" name="email" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Password <span class="text-danger">*</span></label>
                                    <input type="password" name="password" id="adminPassword" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                    <input type="password" name="confirm_password" id="adminConfirmPassword" class="form-control" required>
                                    <div id="adminConfirmMsg" class="password-hint"></div>
                                </div>
                                
                                <button type="submit" class="btn-create">
                                    <i class="fas fa-envelope me-2"></i>Create & Send OTP
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-card">
                            <div class="form-header">
                                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Admin Accounts</h5>
                            </div>
                            <div class="recent-list">
                                <?php foreach($recent_admins as $admin): ?>
                                    <div class="recent-item">
                                        <strong><?php echo htmlspecialchars($admin['fullname']); ?></strong><br>
                                        <small>ID: <?php echo htmlspecialchars($admin['user_id']); ?> | Email: <?php echo htmlspecialchars($admin['email']); ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Professor Account Tab -->
            <div class="tab-pane fade" id="professorTab">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-card">
                            <div class="form-header">
                                <h5 class="mb-0"><i class="fas fa-chalkboard-teacher me-2"></i>New Professor Account</h5>
                            </div>
                            <form method="POST">
                                <input type="hidden" name="action" value="send_otp">
                                <input type="hidden" name="account_type" value="professor">
                                
                                <div class="mb-3">
                                    <label class="form-label">Professor ID <span class="text-danger">*</span></label>
                                    <input type="text" name="user_id" class="form-control" placeholder="e.g., PROF001" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" name="fullname" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" name="email" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Password <span class="text-danger">*</span></label>
                                    <input type="password" name="password" id="profPassword" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                    <input type="password" name="confirm_password" id="profConfirmPassword" class="form-control" required>
                                    <div id="profConfirmMsg" class="password-hint"></div>
                                </div>
                                
                                <button type="submit" class="btn-create">
                                    <i class="fas fa-envelope me-2"></i>Create & Send OTP
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-card">
                            <div class="form-header">
                                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Professor Accounts</h5>
                            </div>
                            <div class="recent-list">
                                <?php foreach($recent_professors as $prof): ?>
                                    <div class="recent-item">
                                        <strong><?php echo htmlspecialchars($prof['fullname']); ?></strong><br>
                                        <small>ID: <?php echo htmlspecialchars($prof['user_id']); ?> | Email: <?php echo htmlspecialchars($prof['email']); ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Student Account Tab -->
            <div class="tab-pane fade" id="studentTab">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-card">
                            <div class="form-header">
                                <h5 class="mb-0"><i class="fas fa-user-graduate me-2"></i>New Student Account</h5>
                            </div>
                            <form method="POST">
                                <input type="hidden" name="action" value="send_otp">
                                <input type="hidden" name="account_type" value="student">
                                
                                <div class="mb-3">
                                    <label class="form-label">Student ID <span class="text-danger">*</span></label>
                                    <input type="text" name="user_id" class="form-control" placeholder="e.g., 20240001" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" name="fullname" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" name="email" class="form-control" required>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label">Course <span class="text-danger">*</span></label>
                                        <select name="course" class="form-select" required>
                                            <option value="">Select</option>
                                            <option value="CE">Civil Engineering (CE)</option>
                                            <option value="CpE">Computer Engineering (CpE)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Section <span class="text-danger">*</span></label>
                                        <select name="section" class="form-select" required>
                                            <option value="">Select</option>
                                            <option value="A">A</option>
                                            <option value="B">B</option>
                                            <option value="C">C</option>
                                            <option value="D">D</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3 mt-3">
                                    <label class="form-label">Year Level</label>
                                    <select name="year_level" class="form-select">
                                        <option value="1">1st Year</option>
                                        <option value="2">2nd Year</option>
                                        <option value="3">3rd Year</option>
                                        <option value="4">4th Year</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Password <span class="text-danger">*</span></label>
                                    <input type="password" name="password" id="studentPassword" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                    <input type="password" name="confirm_password" id="studentConfirmPassword" class="form-control" required>
                                    <div id="studentConfirmMsg" class="password-hint"></div>
                                </div>
                                
                                <button type="submit" class="btn-create">
                                    <i class="fas fa-envelope me-2"></i>Create & Send OTP
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-card">
                            <div class="form-header">
                                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Student Accounts</h5>
                            </div>
                            <div class="recent-list">
                                <?php foreach($recent_students as $student): ?>
                                    <div class="recent-item">
                                        <strong><?php echo htmlspecialchars($student['fullname']); ?></strong><br>
                                        <small>ID: <?php echo htmlspecialchars($student['user_id']); ?> | <?php echo $student['course']; ?> - Section <?php echo $student['section']; ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Password match validation for each form
    function setupPasswordValidation(passwordId, confirmId, messageId) {
        const password = document.getElementById(passwordId);
        const confirm = document.getElementById(confirmId);
        const message = document.getElementById(messageId);
        
        if (!password || !confirm || !message) return;
        
        function validate() {
            if (confirm.value.length === 0) {
                message.innerHTML = '';
                confirm.classList.remove('password-match', 'password-mismatch');
                return;
            }
            
            if (password.value === confirm.value) {
                message.innerHTML = '<i class="fas fa-check-circle me-1"></i> Passwords match!';
                message.style.color = '#28a745';
                confirm.classList.remove('password-mismatch');
                confirm.classList.add('password-match');
            } else {
                message.innerHTML = '<i class="fas fa-times-circle me-1"></i> Passwords do not match';
                message.style.color = '#dc3545';
                confirm.classList.remove('password-match');
                confirm.classList.add('password-mismatch');
            }
        }
        
        password.addEventListener('input', validate);
        confirm.addEventListener('input', validate);
    }
    
    // Setup validation for all forms
    setupPasswordValidation('adminPassword', 'adminConfirmPassword', 'adminConfirmMsg');
    setupPasswordValidation('profPassword', 'profConfirmPassword', 'profConfirmMsg');
    setupPasswordValidation('studentPassword', 'studentConfirmPassword', 'studentConfirmMsg');
</script>

<?php include '../includes/footer.php'; ?>
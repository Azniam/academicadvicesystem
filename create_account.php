<?php
require_once 'config/database.php';
require_once 'vendor/autoload.php'; // PHPMailer autoload

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ' . $_SESSION['role'] . '/dashboard.php');
    exit();
}

$error = '';
$success = '';
$step = 1; // 1: Registration Form, 2: OTP Verification
$temp_data = [];

// Start session to store temporary registration data and OTP
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// PHPMailer configuration function
function sendOTPEmail($to_email, $to_name, $otp) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings - Configure according to your email provider
        $mail->SMTPDebug = SMTP::DEBUG_OFF; // Set to SMTP::DEBUG_SERVER for testing
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // Your SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'azniamedaj@gmail.com'; // Your email address
        $mail->Password   = 'nrcs cotu wpql smqj'; // Your email password or app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Recipients
        $mail->setFrom('azniamedaj@gmail.com', 'Academic Advising System');
        $mail->addAddress($to_email, $to_name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Email Verification OTP - Academic Advising System';
        
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
                .footer { background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #666; }
                .warning { color: #dc3545; font-size: 12px; margin-top: 10px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>Academic Advising System</h2>
                    <p>Email Verification</p>
                </div>
                <div class="content">
                    <p>Dear <strong>' . htmlspecialchars($to_name) . '</strong>,</p>
                    <p>Thank you for registering with Academic Advising System. Please use the OTP code below to verify your email address:</p>
                    <div class="otp-code">' . $otp . '</div>
                    <p>This OTP is valid for <strong>3 minutes</strong>.</p>
                    <p>If you did not request this registration, please ignore this email.</p>
                </div>
                <div class="footer">
                    <p>&copy; 2024 Academic Advising System. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ';
        
        $mail->AltBody = "Dear $to_name,\n\nThank you for registering with Academic Advising System. Please use the OTP code below to verify your email address:\n\nOTP Code: $otp\n\nThis OTP is valid for 3 minutes.\n\nIf you did not request this registration, please ignore this email.\n\nRegards,\nAcademic Advising System";
        
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

// Initialize OTP attempts in session if not exists
if (!isset($_SESSION['otp_attempts'])) {
    $_SESSION['otp_attempts'] = 0;
}

// Step 1: Process registration form and send OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    // Reset OTP attempts when starting new registration
    $_SESSION['otp_attempts'] = 0;
    
    $student_id = trim($_POST['student_id'] ?? '');
    $fullname = trim($_POST['fullname'] ?? '');
    $course = $_POST['course'] ?? '';
    $section = trim($_POST['section'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($student_id) || empty($fullname) || empty($course) || empty($section) || empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (!preg_match('/^[a-zA-Z0-9-]{4,20}$/', $student_id)) {
        $error = 'Student ID must contain only letters, numbers, and hyphens (4-20 characters)';
    } else {
        // Check if student ID already exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$student_id]);
        if ($stmt->fetch()) {
            $error = 'Student ID already registered';
        } else {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email already registered';
            } else {
                // Generate OTP
                $otp = generateOTP();
                $expiry = date('Y-m-d H:i:s', strtotime('+3 minutes'));
                
                // Store temporary data in session
                $_SESSION['temp_registration'] = [
                    'student_id' => $student_id,
                    'fullname' => $fullname,
                    'course' => $course,
                    'section' => $section,
                    'email' => $email,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'otp' => $otp,
                    'otp_expiry' => $expiry,
                    'picture' => null
                ];
                
                // Handle picture upload if any
                if (isset($_FILES['picture']) && $_FILES['picture']['error'] === UPLOAD_ERR_OK) {
                    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                    $filename = $_FILES['picture']['name'];
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    
                    if (in_array($ext, $allowed)) {
                        $picture_name = time() . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $student_id) . '.' . $ext;
                        $upload_path = 'uploads/students/';
                        
                        if (!file_exists($upload_path)) {
                            mkdir($upload_path, 0777, true);
                        }
                        
                        move_uploaded_file($_FILES['picture']['tmp_name'], $upload_path . $picture_name);
                        $_SESSION['temp_registration']['picture'] = $picture_name;
                    }
                }
                
                // Send OTP email
                if (sendOTPEmail($email, $fullname, $otp)) {
                    $step = 2;
                    $success = 'An OTP has been sent to your email address. Please check your inbox.';
                } else {
                    $error = 'Failed to send OTP email. Please try again.';
                    unset($_SESSION['temp_registration']);
                }
            }
        }
    }
}

// Step 2: Verify OTP and complete registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
    $otp = trim($_POST['otp'] ?? '');
    
    if (empty($otp)) {
        $error = 'Please enter the OTP code';
    } elseif (!isset($_SESSION['temp_registration'])) {
        $error = 'Registration session expired. Please start over.';
        $step = 1;
        $_SESSION['otp_attempts'] = 0;
    } else {
        $temp = $_SESSION['temp_registration'];
        
        // Check if OTP is expired
        if (strtotime($temp['otp_expiry']) < time()) {
            $error = 'OTP has expired. Please register again.';
            unset($_SESSION['temp_registration']);
            $_SESSION['otp_attempts'] = 0;
            $step = 1;
        }
        // Check if OTP is correct
        elseif ($temp['otp'] !== $otp) {
            $_SESSION['otp_attempts']++;
            $remaining_attempts = 3 - $_SESSION['otp_attempts'];
            
            if ($_SESSION['otp_attempts'] >= 3) {
                // Failed 3 times - go back to registration form
                $error = 'You have exceeded the maximum number of attempts (3). Please register again.';
                unset($_SESSION['temp_registration']);
                $_SESSION['otp_attempts'] = 0;
                $step = 1;
            } else {
                // WRONG OTP - STAY ON OTP FORM (step remains 2)
                $error = "Invalid OTP code. You have $remaining_attempts attempt(s) remaining.";
                // IMPORTANT: Do not change $step, stay on OTP verification
                $step = 2;
            }
        } else {
            // OTP verified successfully, reset attempts
            $_SESSION['otp_attempts'] = 0;
            
            // OTP verified, create account
            $picture = $temp['picture'] ?? 'default.png';
            
            $stmt = $pdo->prepare("
                INSERT INTO users (user_id, fullname, email, password, role, course, section, year_level, picture, status) 
                VALUES (?, ?, ?, ?, 'student', ?, ?, 1, ?, 'active')
            ");
            
            if ($stmt->execute([
                $temp['student_id'],
                $temp['fullname'],
                $temp['email'],
                $temp['password'],
                $temp['course'],
                $temp['section'],
                $picture
            ])) {
                // Clear temporary session data
                unset($_SESSION['temp_registration']);
                $success = 'Account successfully created! You can now login.';
                $step = 3; // Completion step
            } else {
                $error = 'Failed to create account. Please try again.';
                $step = 2; // Stay on OTP form
            }
        }
    }
}

// Resend OTP
if (isset($_GET['resend']) && $_GET['resend'] == '1') {
    if (isset($_SESSION['temp_registration'])) {
        // Reset attempts when resending OTP
        $_SESSION['otp_attempts'] = 0;
        
        $temp = $_SESSION['temp_registration'];
        $new_otp = generateOTP();
        $new_expiry = date('Y-m-d H:i:s', strtotime('+3 minutes'));
        
        $_SESSION['temp_registration']['otp'] = $new_otp;
        $_SESSION['temp_registration']['otp_expiry'] = $new_expiry;
        
        if (sendOTPEmail($temp['email'], $temp['fullname'], $new_otp)) {
            $success = 'A new OTP has been sent to your email address.';
            $step = 2; // Stay on OTP form
        } else {
            $error = 'Failed to resend OTP. Please try again.';
        }
    } else {
        $error = 'Session expired. Please register again.';
        $step = 1;
        $_SESSION['otp_attempts'] = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - Academic Advising System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }
        .register-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 550px;
            width: 100%;
        }
        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            text-align: center;
        }
        .register-header h2 {
            font-size: 1.8rem;
            margin-bottom: 5px;
        }
        .register-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }
        .register-body {
            padding: 30px;
        }
        .btn-register {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            width: 100%;
            padding: 12px;
            font-weight: bold;
            font-size: 1rem;
        }
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
        }
        .btn-verify {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            width: 100%;
            padding: 12px;
            font-weight: bold;
            font-size: 1rem;
        }
        .btn-verify:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40,167,69,0.4);
        }
        .picture-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #667eea;
            margin: 0 auto 15px;
            display: block;
            cursor: pointer;
        }
        .picture-upload {
            display: none;
        }
        .upload-label {
            text-align: center;
            display: block;
            cursor: pointer;
            color: #667eea;
            margin-bottom: 15px;
        }
        .upload-label:hover {
            text-decoration: underline;
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            padding: 10px 15px;
            transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102,126,234,0.25);
        }
        /* Password match validation styles */
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
        .password-hint {
            font-size: 12px;
            margin-top: 5px;
        }
        .password-hint.valid {
            color: #28a745;
        }
        .password-hint.invalid {
            color: #dc3545;
        }
        .input-group-text {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px 0 0 10px;
        }
        .input-group .form-control {
            border-radius: 0 10px 10px 0;
        }
        .alert {
            border-radius: 10px;
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
        .attempts-warning {
            font-size: 0.8rem;
            color: #dc3545;
            text-align: center;
            margin-top: 10px;
        }
        /* OTP input group styling */
        .otp-group {
            max-width: 300px;
            margin: 0 auto;
        }
        .otp-group input {
            text-align: center;
            font-size: 2rem;
            font-weight: bold;
            letter-spacing: 10px;
        }
        /* Student ID helper text */
        .student-id-hint {
            font-size: 11px;
            margin-top: 4px;
        }
        @media (max-width: 576px) {
            .register-body {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="register-card">
        <div class="register-header">
            <h2><i class="fas fa-user-graduate me-2"></i>Student Registration</h2>
            <p>Create your student account to get started</p>
        </div>
        <div class="register-body">
            <?php if($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if($success && $step == 3): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <div class="text-center mt-3">
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt me-2"></i>Go to Login
                    </a>
                </div>
            <?php elseif($step == 2): ?>
                <!-- Step 2: OTP Verification Form (Stays here until 3 failed attempts or success) -->
                <div class="text-center mb-4">
                    <div class="mb-3">
                        <i class="fas fa-envelope fa-4x text-primary"></i>
                    </div>
                    <h5>Email Verification Required</h5>
                    <p class="text-muted">We've sent a 6-digit verification code to:</p>
                    <p class="fw-bold"><?php echo htmlspecialchars($_SESSION['temp_registration']['email'] ?? ''); ?></p>
                    <div class="alert alert-info">
                        <small><i class="fas fa-info-circle me-1"></i> Please check your email inbox and enter the OTP code below</small>
                    </div>
                    <div class="attempts-warning">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        You have <?php echo 3 - ($_SESSION['otp_attempts'] ?? 0); ?> attempt(s) remaining
                    </div>
                </div>
                
                <form method="POST" id="otpForm">
                    <input type="hidden" name="action" value="verify_otp">
                    
                    <div class="mb-4">
                        <label class="form-label text-center d-block fw-bold">Enter OTP Code</label>
                        <div class="otp-group mx-auto">
                            <input type="text" name="otp" class="form-control otp-input text-center" 
                                   placeholder="000000" maxlength="6" required autofocus
                                   style="font-size: 2rem; letter-spacing: 8px; text-align: center;">
                        </div>
                        <small class="text-muted d-block text-center mt-2">Enter the 6-digit code sent to your email</small>
                    </div>
                    
                    <button type="submit" class="btn btn-success btn-verify">
                        <i class="fas fa-check-circle me-2"></i>Verify OTP & Create Account
                    </button>
                    
                    <div class="countdown">
                        <i class="fas fa-clock me-1"></i>
                        <span id="countdown"></span>
                        <a href="?resend=1" class="text-decoration-none ms-2" id="resendLink" style="display: none;">
                            <i class="fas fa-redo me-1"></i>Resend OTP
                        </a>
                    </div>
                    
                    <div class="text-center mt-3">
                        <a href="create_account.php" class="text-decoration-none">
                            <i class="fas fa-arrow-left me-1"></i>Back to Registration
                        </a>
                    </div>
                </form>
                
                <script>
                    // Countdown timer for OTP expiration (3 minutes = 180 seconds)
                    let timeLeft = 180;
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
                <!-- Step 1: Registration Form -->
                <form method="POST" enctype="multipart/form-data" id="registrationForm">
                    <input type="hidden" name="action" value="register">
                    
                    <!-- Picture Upload -->
                    <div class="text-center">
                        <img id="picturePreview" class="picture-preview" src="uploads/students/default.png" alt="Profile Picture">
                        <label for="pictureUpload" class="upload-label">
                            <i class="fas fa-camera me-2"></i>Upload Profile Picture
                        </label>
                        <input type="file" id="pictureUpload" name="picture" class="picture-upload" accept="image/*">
                    </div>

                    <!-- Student ID -->
                    <div class="mb-3">
                        <label class="form-label">Student ID <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                            <input type="text" name="student_id" class="form-control" placeholder="Enter your Student ID (e.g., 2024-001 or STUDENT-123)" 
                                   value="<?php echo htmlspecialchars($_POST['student_id'] ?? ''); ?>" required>
                        </div>
                        <small class="text-muted student-id-hint">Accepts letters, numbers, and hyphens (4-20 characters)</small>
                    </div>

                    <!-- Full Name -->
                    <div class="mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" name="fullname" class="form-control" placeholder="Enter your full name" 
                                   value="<?php echo htmlspecialchars($_POST['fullname'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <!-- Course -->
                    <div class="mb-3">
                        <label class="form-label">Course <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-graduation-cap"></i></span>
                            <select name="course" class="form-select" required>
                                <option value="">Select Course</option>
                                <option value="CE" <?php echo (isset($_POST['course']) && $_POST['course'] == 'CE') ? 'selected' : ''; ?>>Civil Engineering (CE)</option>
                                <option value="CpE" <?php echo (isset($_POST['course']) && $_POST['course'] == 'CpE') ? 'selected' : ''; ?>>Computer Engineering (CpE)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Section -->
                    <div class="mb-3">
                        <label class="form-label">Section <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-users"></i></span>
                            <select name="section" class="form-select" required>
                                <option value="">Select Section</option>
                                <option value="A" <?php echo (isset($_POST['section']) && $_POST['section'] == 'A') ? 'selected' : ''; ?>>Section A</option>
                                <option value="B" <?php echo (isset($_POST['section']) && $_POST['section'] == 'B') ? 'selected' : ''; ?>>Section B</option>
                                <option value="C" <?php echo (isset($_POST['section']) && $_POST['section'] == 'C') ? 'selected' : ''; ?>>Section C</option>
                                <option value="D" <?php echo (isset($_POST['section']) && $_POST['section'] == 'D') ? 'selected' : ''; ?>>Section D</option>
                            </select>
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="mb-3">
                        <label class="form-label">Email Address <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" name="email" class="form-control" placeholder="you@example.com" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <!-- Password with real-time validation -->
                    <div class="mb-3">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" name="password" id="password" class="form-control" placeholder="Minimum 6 characters" required>
                        </div>
                        <div id="passwordStrength" class="password-hint"></div>
                    </div>

                    <!-- Confirm Password with real-time validation -->
                    <div class="mb-4">
                        <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" name="confirm_password" id="confirmPassword" class="form-control" placeholder="Re-enter your password" required>
                        </div>
                        <div id="confirmMessage" class="password-hint"></div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-register" id="submitBtn">
                        <i class="fas fa-envelope me-2"></i>Register & Verify Email
                    </button>

                    <div class="text-center mt-3">
                        <span>Already have an account? </span>
                        <a href="login.php" class="text-decoration-none">Login here</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Picture preview functionality
        const pictureUpload = document.getElementById('pictureUpload');
        const picturePreview = document.getElementById('picturePreview');

        if (pictureUpload && picturePreview) {
            pictureUpload.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        picturePreview.src = e.target.result;
                    }
                    reader.readAsDataURL(file);
                }
            });

            picturePreview.addEventListener('click', function() {
                pictureUpload.click();
            });
        }
        
        // Real-time password match validation
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirmPassword');
        const passwordStrengthDiv = document.getElementById('passwordStrength');
        const confirmMessageDiv = document.getElementById('confirmMessage');
        const submitBtn = document.getElementById('submitBtn');
        
        // Password strength validation
        function validatePasswordStrength(password) {
            if (password.length === 0) {
                passwordStrengthDiv.innerHTML = '';
                passwordStrengthDiv.className = 'password-hint';
                return false;
            }
            
            if (password.length < 6) {
                passwordStrengthDiv.innerHTML = '<i class="fas fa-times-circle me-1"></i> Password must be at least 6 characters';
                passwordStrengthDiv.className = 'password-hint invalid';
                return false;
            } else {
                passwordStrengthDiv.innerHTML = '<i class="fas fa-check-circle me-1"></i> Password strength: Good';
                passwordStrengthDiv.className = 'password-hint valid';
                return true;
            }
        }
        
        // Confirm password validation
        function validateConfirmPassword() {
            const password = passwordInput.value;
            const confirm = confirmInput.value;
            
            if (confirm.length === 0) {
                confirmMessageDiv.innerHTML = '';
                confirmInput.classList.remove('password-match', 'password-mismatch');
                return false;
            }
            
            if (password === confirm) {
                confirmMessageDiv.innerHTML = '<i class="fas fa-check-circle me-1"></i> Passwords match!';
                confirmMessageDiv.className = 'password-hint valid';
                confirmInput.classList.remove('password-mismatch');
                confirmInput.classList.add('password-match');
                return true;
            } else {
                confirmMessageDiv.innerHTML = '<i class="fas fa-times-circle me-1"></i> Passwords do not match';
                confirmMessageDiv.className = 'password-hint invalid';
                confirmInput.classList.remove('password-match');
                confirmInput.classList.add('password-mismatch');
                return false;
            }
        }
        
        // Real-time validation events
        passwordInput.addEventListener('input', function() {
            validatePasswordStrength(this.value);
            if (confirmInput.value.length > 0) {
                validateConfirmPassword();
            }
        });
        
        confirmInput.addEventListener('input', function() {
            validateConfirmPassword();
        });
        
        // Form submission validation
        document.getElementById('registrationForm')?.addEventListener('submit', function(e) {
            const isPasswordValid = validatePasswordStrength(passwordInput.value);
            const isConfirmValid = validateConfirmPassword();
            
            if (!isPasswordValid || !isConfirmValid) {
                e.preventDefault();
                alert('Please make sure your password is valid and matches the confirmation.');
            }
        });
    </script>
</body>
</html>
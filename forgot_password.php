<?php
require_once 'config/database.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ' . $_SESSION['role'] . '/dashboard.php');
    exit();
}

$step = 1; // 1: Email input, 2: OTP verification, 3: New password
$error = '';
$success = '';
$email = '';

// Generate random OTP
function generateOTP() {
    return sprintf("%06d", mt_rand(1, 999999));
}

// Send OTP via email (using mail() function - configure for production)
function sendOTPEmail($to, $otp) {
    $subject = "Password Reset OTP - Academic Advising System";
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 500px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 20px; text-align: center; }
            .otp-code { font-size: 32px; font-weight: bold; color: #667eea; text-align: center; padding: 20px; letter-spacing: 5px; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Academic Advising System</h2>
                <p>Password Reset Request</p>
            </div>
            <div class='otp-code'>
                $otp
            </div>
            <p>Dear User,</p>
            <p>You have requested to reset your password. Please use the OTP code above to complete the password reset process.</p>
            <p>This OTP is valid for 10 minutes.</p>
            <p>If you did not request this, please ignore this email.</p>
            <div class='footer'>
                <p>&copy; 2024 Academic Advising System. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: noreply@academicadvising.com" . "\r\n";
    
    return mail($to, $subject, $message, $headers);
}

// Step 1: Request OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step']) && $_POST['step'] == '1') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Please enter your email address';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        // Check if email exists in database
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Generate OTP
            $otp = generateOTP();
            $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // Store OTP in database
            $stmt = $pdo->prepare("
                INSERT INTO password_resets (email, otp, expiry, created_at) 
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                otp = VALUES(otp), 
                expiry = VALUES(expiry),
                created_at = NOW()
            ");
            $stmt->execute([$email, $otp, $expiry]);
            
            // Send OTP email
            if (sendOTPEmail($email, $otp)) {
                $_SESSION['reset_email'] = $email;
                $step = 2;
                $success = 'An OTP has been sent to your email address. Please check your inbox.';
            } else {
                $error = 'Failed to send OTP email. Please try again.';
                // For testing without mail server, display OTP
                $error .= "<br><small class='text-muted'>[Debug] OTP: $otp</small>";
            }
        } else {
            $error = 'Email address not found in our records';
        }
    }
}

// Step 2: Verify OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step']) && $_POST['step'] == '2') {
    $otp = trim($_POST['otp'] ?? '');
    $email = $_SESSION['reset_email'] ?? '';
    
    if (empty($otp)) {
        $error = 'Please enter the OTP code';
    } else {
        // Check OTP in database
        $stmt = $pdo->prepare("
            SELECT * FROM password_resets 
            WHERE email = ? AND otp = ? AND expiry > NOW()
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$email, $otp]);
        $reset = $stmt->fetch();
        
        if ($reset) {
            // Delete used OTP
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt->execute([$email]);
            
            $step = 3;
            $success = 'OTP verified! Please enter your new password.';
        } else {
            $error = 'Invalid or expired OTP. Please request a new one.';
        }
    }
}

// Step 3: Reset Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step']) && $_POST['step'] == '3') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $email = $_SESSION['reset_email'] ?? '';
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = 'Please fill in all fields';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        // Update password in database
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        
        if ($stmt->execute([$hashed_password, $email])) {
            // Clear session
            unset($_SESSION['reset_email']);
            
            // Log the password reset activity
            $stmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, user_name, action, timestamp) 
                SELECT user_id, fullname, 'password_reset', NOW()
                FROM users WHERE email = ?
            ");
            $stmt->execute([$email]);
            
            $success = 'Password successfully reset! You can now login with your new password.';
            $step = 4; // Completion step
        } else {
            $error = 'Failed to reset password. Please try again.';
        }
    }
}

// Resend OTP
if (isset($_GET['resend']) && $_GET['resend'] == '1') {
    $email = $_SESSION['reset_email'] ?? '';
    if ($email) {
        $otp = generateOTP();
        $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        $stmt = $pdo->prepare("
            INSERT INTO password_resets (email, otp, expiry, created_at) 
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
            otp = VALUES(otp), 
            expiry = VALUES(expiry),
            created_at = NOW()
        ");
        $stmt->execute([$email, $otp, $expiry]);
        
        if (sendOTPEmail($email, $otp)) {
            $success = 'A new OTP has been sent to your email address.';
        } else {
            $error = 'Failed to resend OTP. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Academic Advising System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .forgot-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 480px;
            width: 100%;
            animation: fadeInUp 0.5s ease;
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .forgot-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .forgot-header h2 {
            font-size: 1.8rem;
            margin-bottom: 5px;
        }
        .forgot-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }
        .forgot-body {
            padding: 35px;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
        }
        .step-indicator::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 10%;
            right: 10%;
            height: 2px;
            background: #e2e8f0;
            z-index: 1;
        }
        .step {
            text-align: center;
            position: relative;
            z-index: 2;
            background: white;
            flex: 1;
        }
        .step-circle {
            width: 40px;
            height: 40px;
            background: #e2e8f0;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #718096;
            margin-bottom: 8px;
        }
        .step.active .step-circle {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        .step.completed .step-circle {
            background: #28a745;
            color: white;
        }
        .step-label {
            font-size: 0.75rem;
            color: #718096;
        }
        .step.active .step-label {
            color: #667eea;
            font-weight: 600;
        }
        .btn-reset {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            width: 100%;
            padding: 12px;
            font-weight: bold;
            font-size: 1rem;
        }
        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
        }
        .otp-input {
            text-align: center;
            font-size: 1.5rem;
            letter-spacing: 10px;
            font-weight: bold;
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            padding: 10px 15px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102,126,234,0.25);
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
        .countdown {
            font-size: 0.85rem;
            color: #718096;
            text-align: center;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="forgot-card">
        <div class="forgot-header">
            <h2><i class="fas fa-key me-2"></i>Forgot Password</h2>
            <p>Reset your password in a few easy steps</p>
        </div>
        <div class="forgot-body">
            <!-- Step Indicator -->
            <div class="step-indicator">
                <div class="step <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'completed' : ''; ?>">
                    <div class="step-circle">1</div>
                    <div class="step-label">Email</div>
                </div>
                <div class="step <?php echo $step >= 2 ? 'active' : ''; ?> <?php echo $step > 2 ? 'completed' : ''; ?>">
                    <div class="step-circle">2</div>
                    <div class="step-label">OTP</div>
                </div>
                <div class="step <?php echo $step >= 3 ? 'active' : ''; ?> <?php echo $step > 3 ? 'completed' : ''; ?>">
                    <div class="step-circle">3</div>
                    <div class="step-label">New Password</div>
                </div>
            </div>

            <?php if($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Step 1: Email Input -->
            <?php if($step == 1): ?>
                <form method="POST">
                    <input type="hidden" name="step" value="1">
                    <div class="mb-4">
                        <label class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" name="email" class="form-control" placeholder="Enter your registered email" 
                                   value="<?php echo htmlspecialchars($email); ?>" required autofocus>
                        </div>
                        <small class="text-muted">We'll send a password reset OTP to this email address.</small>
                    </div>
                    <button type="submit" class="btn btn-primary btn-reset">
                        <i class="fas fa-paper-plane me-2"></i>Send OTP
                    </button>
                    <div class="text-center mt-3">
                        <a href="login.php" class="text-decoration-none">
                            <i class="fas fa-arrow-left me-1"></i>Back to Login
                        </a>
                    </div>
                </form>
            <?php endif; ?>

            <!-- Step 2: OTP Verification -->
            <?php if($step == 2): ?>
                <form method="POST">
                    <input type="hidden" name="step" value="2">
                    <div class="mb-4">
                        <label class="form-label">Enter OTP Code</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-shield-alt"></i></span>
                            <input type="text" name="otp" class="form-control otp-input" placeholder="000000" 
                                   maxlength="6" required autofocus>
                        </div>
                        <small class="text-muted">Enter the 6-digit code sent to your email.</small>
                    </div>
                    <button type="submit" class="btn btn-primary btn-reset">
                        <i class="fas fa-check-circle me-2"></i>Verify OTP
                    </button>
                    <div class="countdown">
                        <i class="fas fa-clock me-1"></i>
                        <span id="countdown"></span>
                        <a href="?resend=1" class="text-decoration-none ms-2" id="resendLink" style="display: none;">
                            <i class="fas fa-redo me-1"></i>Resend OTP
                        </a>
                    </div>
                    <div class="text-center mt-3">
                        <a href="forgot_password.php" class="text-decoration-none">
                            <i class="fas fa-arrow-left me-1"></i>Start Over
                        </a>
                    </div>
                </form>
                <script>
                    // Countdown timer for OTP expiration (10 minutes = 600 seconds)
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
            <?php endif; ?>

            <!-- Step 3: New Password -->
            <?php if($step == 3): ?>
                <form method="POST">
                    <input type="hidden" name="step" value="3">
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" name="new_password" class="form-control" placeholder="Minimum 6 characters" required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Confirm New Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter your new password" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-reset">
                        <i class="fas fa-save me-2"></i>Reset Password
                    </button>
                    <div class="text-center mt-3">
                        <a href="login.php" class="text-decoration-none">
                            <i class="fas fa-arrow-left me-1"></i>Back to Login
                        </a>
                    </div>
                </form>
            <?php endif; ?>

            <!-- Step 4: Completion -->
            <?php if($step == 4): ?>
                <div class="text-center">
                    <div style="width: 80px; height: 80px; background: #28a745; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                        <i class="fas fa-check" style="font-size: 40px; color: white;"></i>
                    </div>
                    <h4 class="mb-3">Password Reset Complete!</h4>
                    <p class="text-muted mb-4">Your password has been successfully reset. You can now login with your new password.</p>
                    <a href="login.php" class="btn btn-primary btn-reset">
                        <i class="fas fa-sign-in-alt me-2"></i>Go to Login
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
require_once 'config/database.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    header("Location: $role/dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Advising System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Background Image - Layer 1 (Bottom) */
        .background-image {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -3;
            /* ============================================= */
            /* PLACE YOUR BACKGROUND IMAGE HERE */
            /* ============================================= */
            background-image: url('assets/images/background.jpg');
            /* ============================================= */
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }

        /* Blur Overlay - Layer 2 */
        .blur-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -2;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            background: rgba(0, 0, 0, 0.2);
        }

        /* Gradient Overlay - Layer 3 (Top of image, behind content) */
        .gradient-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background: linear-gradient(180deg, rgba(0,167,225,0.85) 0%, rgba(241,119,32,0.85) 100%);
        }

        .hero-section {
            text-align: center;
            padding: 20px;
            animation: fadeInUp 0.8s ease;
            position: relative;
            z-index: 1;
        }

        .logo-container {
            margin-bottom: 30px;
        }

        .layered-logo {
            position: relative;
            display: inline-block;
        }

        .layered-logo .logo-back {
            position: absolute;
            top: -15px;
            left: -20px;
            opacity: 0.3;
            z-index: 1;
        }

        .layered-logo .logo-front {
            position: relative;
            z-index: 2;
        }

        .layered-logo i {
            font-size: 80px;
            color: white;
        }

        h1 {
            font-size: 3.5rem;
            font-weight: 700;
            color: white;
            margin-bottom: 20px;
            letter-spacing: -0.5px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }

        .subtitle {
            font-size: 1.2rem;
            color: rgba(255,255,255,0.9);
            margin-bottom: 40px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .btn-get-started {
            background: white;
            color: #667eea;
            border: none;
            padding: 15px 50px;
            font-size: 1.2rem;
            font-weight: 600;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-get-started:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            background: white;
            color: #764ba2;
        }

        .btn-get-started i {
            font-size: 1.2rem;
        }

        .footer {
            position: fixed;
            bottom: 20px;
            left: 0;
            right: 0;
            text-align: center;
            color: rgba(255,255,255,0.6);
            font-size: 0.8rem;
            z-index: 1;
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

        @media (max-width: 768px) {
            h1 {
                font-size: 2rem;
            }
            .subtitle {
                font-size: 1rem;
                padding: 0 20px;
            }
            .btn-get-started {
                padding: 12px 40px;
                font-size: 1rem;
            }
            .layered-logo i {
                font-size: 60px;
            }
        }
    </style>
</head>
<body>
    <!-- Background Image - Layer 1 (Bottom) -->
    <div class="background-image"></div>
    
    <!-- Blur Overlay - Layer 2 -->
    <div class="blur-overlay"></div>
    
    <!-- Gradient Overlay - Layer 3 (Top of image, behind content) -->
    <div class="gradient-overlay"></div>

    <div class="hero-section">
        <div class="logo-container">
            <div class="layered-logo">
                <div class="logo-back">
                    <i class="fas fa-university"></i>
                </div>
                <div class="logo-front">
                    <i class="fas fa-graduation-cap"></i>
                </div>
            </div>
        </div>
        
        <h1>Academic Advising System</h1>
        
        <div class="subtitle">
            Your complete solution for academic planning, grade management, and student success tracking.
        </div>
        
        <button class="btn-get-started" onclick="location.href='login.php'">
            <i class="fas fa-arrow-right"></i>
            Get Started
        </button>
        
        <div class="footer">
            <i class="fas fa-copyright me-1"></i> 2024 Academic Advising System. All rights reserved.
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
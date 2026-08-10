<?php
require_once 'includes/dashboard.php';
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get user_id from session
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    // Redirect to login if no user_id in session
    header('Location: client/pages/auth/login.html');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FuelingSys ERP | Coming Soon</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ===== FUELINGSYS ERP LIGHT THEME ===== */
        :root {
            /* Core Brand Colors */
            --primary: #1F7BFF;
            --primary-hover: #1A6CDC;
            --primary-active: #1559B8;
            
            /* Backgrounds */
            --surface-0: #FFFFFF;
            --surface-1: #F7F9FC;
            --surface-2: #EFF2F7;
            
            /* Text */
            --heading: #0E1A2B;
            --body: #2F3B4C;
            --subtext: #6B7280;
            
            /* Borders */
            --border-default: #E1E6EE;
            --border-strong: #C9CFDA;
            
            /* Cute Accents */
            --cute-accent-1: #FF8AD4;
            --cute-accent-2: #7CE0FF;
            --cute-accent-3: #A3E8B9;
            
            /* Spacing */
            --spacing-sm: 12px;
            --spacing-md: 16px;
            --spacing-lg: 24px;
            --spacing-xl: 32px;
            
            /* Radius */
            --radius-sm: 4px;
            --radius-md: 8px;
            --radius-lg: 12px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--surface-1);
            color: var(--body);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: var(--spacing-md);
            line-height: 1.6;
        }
        
        /* ===== MAIN CONTAINER ===== */
        .container {
            max-width: 800px;
            width: 100%;
            text-align: center;
            background-color: var(--surface-0);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            position: relative;
            overflow: hidden;
            border: 1px solid var(--border-default);
        }
        
        /* ===== DECORATIVE ELEMENTS ===== */
        .decoration {
            position: absolute;
            z-index: 0;
            opacity: 0.7;
        }
        
        .circle-1 {
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, var(--cute-accent-2) 0%, transparent 70%);
            top: -150px;
            right: -150px;
            opacity: 0.1;
        }
        
        .circle-2 {
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, var(--cute-accent-1) 0%, transparent 70%);
            bottom: -100px;
            left: -100px;
            opacity: 0.08;
        }
        
        .dots {
            position: absolute;
            width: 20px;
            height: 20px;
            background-color: var(--cute-accent-3);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }
        
        .dot-1 {
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }
        
        .dot-2 {
            top: 15%;
            right: 15%;
            animation-delay: 2s;
            background-color: var(--cute-accent-2);
        }
        
        .dot-3 {
            bottom: 25%;
            left: 20%;
            animation-delay: 4s;
            background-color: var(--cute-accent-1);
        }
        
        /* ===== LOGO ===== */
        .logo {
            display: inline-flex;
            align-items: center;
            margin-bottom: var(--spacing-xl);
            position: relative;
            z-index: 1;
        }
        
        .logo-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--cute-accent-2) 100%);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: var(--spacing-md);
            color: white;
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        .logo-text {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--heading);
            letter-spacing: -0.5px;
        }
        
        .logo-text span {
            color: var(--primary);
        }
        
        /* ===== MAIN CONTENT ===== */
        .content {
            position: relative;
            z-index: 1;
        }
        
        .title {
            font-size: 3.2rem;
            font-weight: 700;
            color: var(--heading);
            line-height: 1.2;
            margin-bottom: var(--spacing-md);
            animation: fadeIn 1s ease;
        }
        
        .title span {
            color: var(--primary);
            position: relative;
            display: inline-block;
        }
        
        .title span::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--cute-accent-2));
            border-radius: 2px;
        }
        
        .subtitle {
            font-size: 1.3rem;
            color: var(--subtext);
            margin-bottom: var(--spacing-xl);
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            animation: fadeIn 1s ease 0.2s both;
        }
        
        .status {
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-sm);
            padding: var(--spacing-sm) var(--spacing-lg);
            background-color: rgba(31, 123, 255, 0.1);
            border: 1px solid rgba(31, 123, 255, 0.2);
            border-radius: var(--radius-md);
            color: var(--primary);
            font-weight: 600;
            margin-bottom: var(--spacing-xl);
            animation: fadeIn 1s ease 0.4s both;
        }
        
        .status-dot {
            width: 8px;
            height: 8px;
            background-color: var(--primary);
            border-radius: 50%;
            animation: pulse 1.5s infinite;
        }
        
        /* ===== CUTE ILLUSTRATION ===== */
        .illustration {
            height: 180px;
            margin: var(--spacing-xl) 0;
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            animation: fadeIn 1s ease 0.6s both;
        }
        
        .server {
            position: relative;
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, var(--surface-2), var(--surface-0));
            border-radius: var(--radius-lg);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }
        
        .server::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--cute-accent-1), var(--cute-accent-2));
        }
        
        .server-content {
            text-align: center;
            z-index: 1;
        }
        
        .server-icon {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: var(--spacing-sm);
            animation: bounce 2s infinite alternate;
        }
        
        .data-flow {
            position: absolute;
            width: 15px;
            height: 15px;
            background-color: var(--cute-accent-2);
            border-radius: 50%;
            opacity: 0;
        }
        
        .flow-1 {
            top: -30px;
            left: 30px;
            animation: dataFlow 2s infinite;
        }
        
        .flow-2 {
            top: -30px;
            right: 30px;
            animation: dataFlow 2s infinite 0.5s;
        }
        
        .flow-3 {
            bottom: -30px;
            left: 40px;
            animation: dataFlow 2s infinite 1s;
        }
        
        .flow-4 {
            bottom: -30px;
            right: 40px;
            animation: dataFlow 2s infinite 1.5s;
        }
        
        /* ===== SIMPLE ACTION ===== */
        .action {
            margin-top: var(--spacing-xl);
            animation: fadeIn 1s ease 0.8s both;
        }
        
        .action-text {
            font-size: 1.1rem;
            color: var(--subtext);
            margin-bottom: var(--spacing-md);
        }
        
        .contact-btn {
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-sm);
            background-color: var(--primary);
            color: white;
            padding: var(--spacing-md) var(--spacing-xl);
            border-radius: var(--radius-md);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }
        
        .contact-btn:hover {
            background-color: var(--primary-hover);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(31, 123, 255, 0.2);
        }
        
        /* ===== FOOTER ===== */
        .footer {
            margin-top: var(--spacing-xl);
            padding-top: var(--spacing-xl);
            border-top: 1px solid var(--border-default);
            color: var(--subtext);
            font-size: 0.9rem;
            animation: fadeIn 1s ease 1s both;
        }
        
        /* ===== ANIMATIONS ===== */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        @keyframes bounce {
            0% { transform: translateY(0); }
            100% { transform: translateY(-10px); }
        }
        
        @keyframes dataFlow {
            0% { opacity: 0; transform: scale(0.5); }
            50% { opacity: 1; transform: scale(1); }
            100% { opacity: 0; transform: scale(0.5) translateY(30px); }
        }
        
        /* ===== RESPONSIVE DESIGN ===== */
        @media (max-width: 768px) {
            .container {
                padding: var(--spacing-lg);
            }
            
            .title {
                font-size: 2.5rem;
            }
            
            .subtitle {
                font-size: 1.1rem;
            }
            
            .logo-text {
                font-size: 1.8rem;
            }
            
            .logo-icon {
                width: 48px;
                height: 48px;
                font-size: 1.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .title {
                font-size: 2rem;
            }
            
            .subtitle {
                font-size: 1rem;
            }
            
            .logo-text {
                font-size: 1.5rem;
            }
            
            .contact-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Decorative Elements -->
        <div class="decoration circle-1"></div>
        <div class="decoration circle-2"></div>
        <div class="decoration dots dot-1"></div>
        <div class="decoration dots dot-2"></div>
        <div class="decoration dots dot-3"></div>
        
        <!-- Logo -->
        <div class="logo">
            <div class="logo-icon">F</div>
            <div class="logo-text">Fueling<span>Sys</span></div>
        </div>
        
        <!-- Main Content -->
        <div class="content">
            <h1 class="title">
                Enterprise <span>ERP</span> System
            </h1>
            
            <p class="subtitle">
                Streamline your business operations with our next-generation enterprise resource planning solution.
            </p>
            
            <div class="status">
                <div class="status-dot"></div>
                <span>Coming Soon!</span>
            </div>
            
            <!-- Cute Illustration -->
            <div class="illustration">
                <div class="server">
                    <div class="data-flow flow-1"></div>
                    <div class="data-flow flow-2"></div>
                    <div class="data-flow flow-3"></div>
                    <div class="data-flow flow-4"></div>
                    
                    <div class="server-content">
                        <div class="server-icon">
                            <i class="fas fa-cogs"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Simple notification button
        document.getElementById('notifyBtn').addEventListener('click', function() {
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-check"></i> You\'ll Be Notified!';
            this.style.backgroundColor = 'var(--cute-accent-3)';
            this.style.color = '#2F3B4C';
            this.disabled = true;
            
            // Reset button after 3 seconds
            setTimeout(() => {
                this.innerHTML = originalText;
                this.style.backgroundColor = '';
                this.style.color = '';
                this.disabled = false;
            }, 3000);
            
            // In a real application, this would send a notification request
            console.log('Notification request sent');
        });
        
        // Add some interactive fun to the server
        const server = document.querySelector('.server');
        server.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.05)';
            this.style.boxShadow = '0 15px 30px rgba(0, 0, 0, 0.15)';
        });
        
        server.addEventListener('mouseleave', function() {
            this.style.transform = '';
            this.style.boxShadow = '';
        });
        
        // Animate the dots with different delays
        const dots = document.querySelectorAll('.dots');
        dots.forEach((dot, index) => {
            dot.style.animationDelay = `${index * 1.5}s`;
        });
        
        // Simple page load animation
        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.opacity = '0';
            document.body.style.transition = 'opacity 0.5s ease';
            
            setTimeout(() => {
                document.body.style.opacity = '1';
            }, 100);
        });
    </script>
</body>
</html>
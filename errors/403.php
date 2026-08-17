<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FuelingSys ERP | Access Denied</title>
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
            
            /* Semantic */
            --warning: #E8B23F;
            --warning-light: #FFD166;
            --warning-dark: #D4A024;
            --error: #E34F4F;
            
            /* Cute Accents */
            --cute-accent-1: #FF8AD4;
            --cute-accent-2: #7CE0FF;
            
            /* Spacing */
            --spacing-sm: 12px;
            --spacing-md: 16px;
            --spacing-lg: 24px;
            --spacing-xl: 32px;
            
            /* Radius */
            --radius-sm: 4px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
            
            /* Shadows */
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 6px 20px rgba(0, 0, 0, 0.1);
            --shadow-warning: 0 8px 25px rgba(232, 178, 63, 0.15);
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
            flex-direction: column;
            line-height: 1.6;
        }
        
        /* ===== HEADER ===== */
        .header {
            padding: var(--spacing-lg) var(--spacing-xl);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--surface-0);
            box-shadow: var(--shadow-sm);
            border-bottom: 1px solid var(--border-default);
        }
        
        .logo {
            display: flex;
            align-items: center;
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--cute-accent-2) 100%);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: var(--spacing-sm);
            color: white;
            font-size: 1.3rem;
            font-weight: 700;
        }
        
        .logo-text {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--heading);
            letter-spacing: -0.5px;
        }
        
        .logo-text span {
            color: var(--primary);
        }
        
        .security-badge {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            padding: var(--spacing-sm) var(--spacing-md);
            background-color: rgba(232, 178, 63, 0.1);
            border: 1px solid rgba(232, 178, 63, 0.2);
            border-radius: var(--radius-md);
            color: var(--warning-dark);
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .security-icon {
            animation: shake 2s infinite;
        }
        
        /* ===== MAIN CONTENT ===== */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: var(--spacing-xl);
            position: relative;
            overflow: hidden;
        }
        
        .error-container {
            max-width: 700px;
            width: 100%;
            text-align: center;
            z-index: 2;
        }
        
        /* ===== ERROR NUMBER ===== */
        .error-number {
            font-size: 6rem;
            font-weight: 800;
            color: var(--warning);
            line-height: 1;
            margin-bottom: var(--spacing-sm);
            position: relative;
            display: inline-block;
            text-shadow: 3px 3px 0 rgba(232, 178, 63, 0.1);
        }
        
        .error-number::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 250px;
            height: 250px;
            background: radial-gradient(circle, var(--warning-light) 0%, transparent 70%);
            opacity: 0.15;
            z-index: -1;
            border-radius: 50%;
        }
        
        /* ===== ERROR MESSAGE ===== */
        .error-title {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--heading);
            margin-bottom: var(--spacing-md);
        }
        
        .error-subtitle {
            font-size: 1.1rem;
            color: var(--subtext);
            max-width: 600px;
            margin: 0 auto var(--spacing-xl);
        }
        
        /* ===== LOCK ILLUSTRATION ===== */
        .lock-illustration {
            margin: var(--spacing-xl) 0;
            position: relative;
            height: 180px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .lock-container {
            position: relative;
            width: 150px;
            height: 150px;
        }
        
        .lock-body {
            position: absolute;
            top: 40px;
            left: 40px;
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--surface-2), var(--surface-0));
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: 3px solid var(--border-default);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 2;
        }
        
        .lock-shackle {
            position: absolute;
            top: 0;
            left: 20px;
            width: 110px;
            height: 60px;
            border: 8px solid var(--warning);
            border-radius: 60px 60px 0 0;
            border-bottom: none;
            box-sizing: border-box;
            animation: lockShake 3s infinite;
            z-index: 1;
        }
        
        .lock-keyhole {
            width: 10px;
            height: 15px;
            background-color: var(--warning);
            border-radius: var(--radius-sm);
            position: relative;
        }
        
        .lock-keyhole::after {
            content: '';
            position: absolute;
            top: -5px;
            left: 50%;
            transform: translateX(-50%);
            width: 6px;
            height: 6px;
            background-color: var(--warning);
            border-radius: 50%;
        }
        
        .key {
            position: absolute;
            right: 20px;
            top: 60px;
            width: 40px;
            height: 20px;
            background: linear-gradient(to right, var(--warning), var(--warning-dark));
            border-radius: 4px;
            animation: floatKey 4s ease-in-out infinite;
            transform-origin: left center;
        }
        
        .key-teeth {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 10px;
            background-color: var(--warning-dark);
            border-radius: 2px;
        }
        
        .security-rays {
            position: absolute;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            border: 2px dashed rgba(232, 178, 63, 0.3);
            animation: rotate 20s linear infinite;
            top: -25px;
            left: -25px;
        }
        
        /* ===== ACCESS CARD ===== */
        .access-card {
            background-color: var(--surface-0);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            margin: var(--spacing-xl) auto;
            max-width: 500px;
            box-shadow: var(--shadow-md);
            border: 2px solid var(--border-default);
            position: relative;
            overflow: hidden;
        }
        
        .access-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--warning), var(--warning-light));
        }
        
        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--heading);
            margin-bottom: var(--spacing-md);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--spacing-sm);
        }
        
        .card-title i {
            color: var(--warning);
        }
        
        .access-info {
            background-color: var(--surface-1);
            padding: var(--spacing-md);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            margin-bottom: var(--spacing-md);
            border-left: 4px solid var(--warning);
        }
        
        .permission-list {
            text-align: left;
            margin: var(--spacing-md) 0;
            padding-left: var(--spacing-lg);
        }
        
        .permission-list li {
            margin-bottom: var(--spacing-sm);
            color: var(--subtext);
            font-size: 0.9rem;
        }
        
        /* ===== ACTION BUTTONS ===== */
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: var(--spacing-md);
            margin: var(--spacing-xl) 0;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: var(--spacing-md) var(--spacing-xl);
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            border: none;
            gap: var(--spacing-sm);
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-hover);
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(31, 123, 255, 0.2);
        }
        
        .btn-warning {
            background-color: var(--warning);
            color: white;
        }
        
        .btn-warning:hover {
            background-color: var(--warning-dark);
            transform: translateY(-3px);
            box-shadow: var(--shadow-warning);
        }
        
        .btn-secondary {
            background-color: var(--surface-2);
            color: var(--heading);
            border: 1px solid var(--border-default);
        }
        
        .btn-secondary:hover {
            background-color: var(--surface-1);
            border-color: var(--border-strong);
            transform: translateY(-3px);
        }
        
        /* ===== DECORATIONS ===== */
        .decoration {
            position: absolute;
            z-index: 1;
            opacity: 0.7;
        }
        
        .shield {
            width: 30px;
            height: 40px;
            background-color: rgba(232, 178, 63, 0.2);
            border-radius: 10px 10px 5px 5px;
            position: relative;
            animation: float 6s ease-in-out infinite;
        }
        
        .shield::before {
            content: '';
            position: absolute;
            top: 5px;
            left: 50%;
            transform: translateX(-50%);
            width: 15px;
            height: 15px;
            background-color: rgba(232, 178, 63, 0.3);
            border-radius: 50%;
        }
        
        .shield-1 {
            top: 15%;
            left: 10%;
            animation-delay: 0s;
        }
        
        .shield-2 {
            top: 25%;
            right: 15%;
            animation-delay: 2s;
        }
        
        .shield-3 {
            bottom: 20%;
            left: 20%;
            animation-delay: 4s;
        }
        
        .warning-triangle {
            width: 0;
            height: 0;
            border-left: 15px solid transparent;
            border-right: 15px solid transparent;
            border-bottom: 25px solid rgba(232, 178, 63, 0.15);
            animation: pulseWarning 2s infinite;
        }
        
        .triangle-1 {
            top: 10%;
            right: 20%;
        }
        
        .triangle-2 {
            bottom: 15%;
            left: 10%;
            animation-delay: 1s;
        }
        
        /* ===== FOOTER ===== */
        .footer {
            padding: var(--spacing-xl);
            background-color: var(--surface-0);
            text-align: center;
            color: var(--subtext);
            font-size: 0.9rem;
            border-top: 1px solid var(--border-default);
        }
        
        .footer-links {
            display: flex;
            justify-content: center;
            gap: var(--spacing-xl);
            margin-bottom: var(--spacing-md);
            flex-wrap: wrap;
        }
        
        .footer-link {
            color: var(--subtext);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .footer-link:hover {
            color: var(--warning);
        }
        
        /* ===== ANIMATIONS ===== */
        @keyframes shake {
            0%, 100% { transform: rotate(0deg); }
            25% { transform: rotate(10deg); }
            75% { transform: rotate(-10deg); }
        }
        
        @keyframes lockShake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        @keyframes floatKey {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(20deg); }
        }
        
        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }
        
        @keyframes pulseWarning {
            0%, 100% { opacity: 0.3; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(1.1); }
        }
        
        /* ===== RESPONSIVE DESIGN ===== */
        @media (max-width: 768px) {
            .error-number {
                font-size: 5rem;
            }
            
            .error-title {
                font-size: 1.8rem;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 300px;
            }
            
            .security-badge {
                display: none;
            }
            
            .lock-container {
                transform: scale(0.8);
            }
        }
        
        @media (max-width: 480px) {
            .error-number {
                font-size: 4rem;
            }
            
            .error-title {
                font-size: 1.5rem;
            }
            
            .error-subtitle {
                font-size: 1rem;
            }
            
            .header {
                padding: var(--spacing-md);
            }
            
            .main-content {
                padding: var(--spacing-md);
            }
            
            .access-card {
                padding: var(--spacing-md);
            }
        }
    </style>
</head>
<body>
    <!-- Main Content -->
    <main class="main-content">
        <!-- Background Decorations -->
        <div class="decoration shield shield-1"></div>
        <div class="decoration shield shield-2"></div>
        <div class="decoration shield shield-3"></div>
        <div class="decoration warning-triangle triangle-1"></div>
        <div class="decoration warning-triangle triangle-2"></div>
        
        <div class="error-container">
            <!-- Error Number -->
            <div class="error-number">
                403
            </div>
            
            <!-- Error Title & Message -->
            <h1 class="error-title">Access Denied</h1>
            <p class="error-subtitle">
                You don't have permission to access this resource. This area requires specific authorization 
                or your current credentials don't have the necessary privileges.
            </p>
            
            <!-- Lock Illustration -->
            <div class="lock-illustration">
                <div class="lock-container">
                    <div class="security-rays"></div>
                    <div class="lock-shackle"></div>
                    <div class="lock-body">
                        <div class="lock-keyhole"></div>
                    </div>
                    <div class="key">
                        <div class="key-teeth"></div>
                    </div>
                </div>
            </div>
    <script>
        // Request Access Button
        document.getElementById('requestAccessBtn').addEventListener('click', function() {
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            this.disabled = true;
            
            // Simulate processing delay
            setTimeout(() => {
                alert('Access request has been sent to the system administrator.\n\nYou will receive an email notification once your request is reviewed.');
                this.innerHTML = originalText;
                this.disabled = false;
            }, 1500);
            
            // In a real application, this would send an access request
            console.log('Access request submitted');
        });
        
        // Interactive lock
        const lockBody = document.querySelector('.lock-body');
        const lockShackle = document.querySelector('.lock-shackle');
        const key = document.querySelector('.key');
        
        lockBody.addEventListener('mouseenter', function() {
            lockShackle.style.animation = 'lockShake 0.5s infinite';
            this.style.transform = 'scale(1.1)';
            this.style.boxShadow = '0 10px 25px rgba(0, 0, 0, 0.15)';
        });
        
        lockBody.addEventListener('mouseleave', function() {
            lockShackle.style.animation = 'lockShake 3s infinite';
            this.style.transform = '';
            this.style.boxShadow = '';
        });
        
        // Key animation on click
        key.addEventListener('click', function() {
            this.style.animation = 'none';
            this.style.transform = 'translateX(-80px) rotate(-45deg)';
            
            setTimeout(() => {
                this.style.animation = 'floatKey 4s ease-in-out infinite';
                this.style.transform = '';
            }, 1000);
        });
        
        // Security icon animation
        const securityIcon = document.querySelector('.security-icon');
        securityIcon.addEventListener('click', function() {
            this.style.animation = 'shake 0.5s';
            
            setTimeout(() => {
                this.style.animation = 'shake 2s infinite';
            }, 500);
        });
        
        // Generate a random request ID for logging
        const requestId = Math.random().toString(36).substring(2, 10).toUpperCase();
        console.log(`403 Access Denied - Request ID: FS-403-${requestId}`);
    </script>
</body>
</html>
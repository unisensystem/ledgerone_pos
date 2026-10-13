<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FuelingSys ERP | Server Error</title>
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
            --success: #2FBF71;
            --warning: #E8B23F;
            --error: #E34F4F;
            --error-light: #FF6B6B;
            --error-dark: #C0392B;
            
            /* Cute Accents */
            --cute-accent-1: #FF8AD4;
            --cute-accent-2: #7CE0FF;
            --cute-accent-3: #A3E8B9;
            
            /* Spacing */
            --spacing-xs: 8px;
            --spacing-sm: 12px;
            --spacing-md: 16px;
            --spacing-lg: 24px;
            --spacing-xl: 32px;
            --spacing-xxl: 48px;
            
            /* Radius */
            --radius-sm: 4px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
            
            /* Shadows */
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 6px 20px rgba(0, 0, 0, 0.1);
            --shadow-error: 0 8px 25px rgba(227, 79, 79, 0.15);
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
        
        .system-status {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            padding: var(--spacing-sm) var(--spacing-md);
            background-color: rgba(227, 79, 79, 0.1);
            border: 1px solid rgba(227, 79, 79, 0.2);
            border-radius: var(--radius-md);
            color: var(--error);
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .status-dot {
            width: 8px;
            height: 8px;
            background-color: var(--error);
            border-radius: 50%;
            animation: pulseError 1.5s infinite;
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
            max-width: 900px;
            width: 100%;
            text-align: center;
            z-index: 2;
        }
        
        /* ===== ERROR NUMBER ===== */
        .error-number {
            font-size: 8rem;
            font-weight: 800;
            color: var(--error);
            line-height: 1;
            margin-bottom: var(--spacing-sm);
            position: relative;
            display: inline-block;
            text-shadow: 3px 3px 0 rgba(227, 79, 79, 0.1);
        }
        
        .error-number .zero {
            display: inline-block;
            position: relative;
            animation: shakeError 0.5s ease-in-out infinite alternate;
        }
        
        .error-number .zero:nth-child(2) {
            animation-delay: 0.2s;
        }
        
        /* ===== ERROR MESSAGE ===== */
        .error-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--heading);
            margin-bottom: var(--spacing-md);
        }
        
        .error-subtitle {
            font-size: 1.1rem;
            color: var(--subtext);
            max-width: 700px;
            margin: 0 auto var(--spacing-xl);
        }
        
        /* ===== SERVER ERROR ILLUSTRATION ===== */
        .server-illustration {
            margin: var(--spacing-xl) 0;
            position: relative;
            height: 180px;
        }
        
        .server-rack {
            position: relative;
            width: 200px;
            height: 150px;
            margin: 0 auto;
        }
        
        .server-blade {
            position: absolute;
            width: 180px;
            height: 30px;
            background: linear-gradient(to right, var(--surface-2), var(--surface-0));
            border: 1px solid var(--border-default);
            border-radius: var(--radius-sm);
            left: 10px;
            transition: all 0.3s ease;
        }
        
        .server-blade:nth-child(1) {
            top: 0;
            animation: serverError1 2s infinite;
        }
        
        .server-blade:nth-child(2) {
            top: 40px;
            animation: serverError2 2.5s infinite;
            animation-delay: 0.3s;
        }
        
        .server-blade:nth-child(3) {
            top: 80px;
            animation: serverError3 3s infinite;
            animation-delay: 0.6s;
        }
        
        .server-blade:nth-child(4) {
            top: 120px;
            animation: serverError4 2.2s infinite;
            animation-delay: 0.9s;
        }
        
        .server-error-light {
            position: absolute;
            top: 5px;
            right: 10px;
            width: 10px;
            height: 10px;
            background-color: var(--error);
            border-radius: 50%;
            animation: blinkError 1s infinite;
            box-shadow: 0 0 10px var(--error);
        }
        
        .error-symbols {
            position: absolute;
            font-size: 1.5rem;
            color: var(--error);
            animation: floatError 4s ease-in-out infinite;
        }
        
        .symbol-1 {
            top: 20px;
            left: 15%;
            animation-delay: 0s;
        }
        
        .symbol-2 {
            top: 60px;
            right: 20%;
            animation-delay: 0.5s;
        }
        
        .symbol-3 {
            bottom: 40px;
            left: 25%;
            animation-delay: 1s;
        }
        
        /* ===== ERROR DETAILS CARD ===== */
        .error-details {
            background-color: var(--surface-0);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            margin: var(--spacing-xl) auto;
            max-width: 800px;
            text-align: left;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-default);
        }
        
        .error-details-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--heading);
            margin-bottom: var(--spacing-md);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }
        
        .error-details-title i {
            color: var(--error);
        }
        
        .error-info {
            background-color: var(--surface-1);
            padding: var(--spacing-md);
            border-radius: var(--radius-md);
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            overflow-x: auto;
            margin-bottom: var(--spacing-md);
            border-left: 4px solid var(--error);
        }
        
        .error-actions {
            display: flex;
            flex-wrap: wrap;
            gap: var(--spacing-sm);
            margin-top: var(--spacing-lg);
        }
        
        .error-action {
            padding: var(--spacing-xs) var(--spacing-md);
            background-color: var(--surface-2);
            border-radius: var(--radius-md);
            font-size: 0.85rem;
            color: var(--body);
            text-decoration: none;
            border: 1px solid var(--border-default);
            transition: all 0.3s ease;
        }
        
        .error-action:hover {
            background-color: var(--surface-1);
            border-color: var(--error);
            color: var(--error);
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
        
        .btn-error {
            background-color: var(--error);
            color: white;
        }
        
        .btn-error:hover {
            background-color: var(--error-dark);
            transform: translateY(-3px);
            box-shadow: var(--shadow-error);
        }
        
        /* ===== STATUS TIMELINE ===== */
        .status-timeline {
            max-width: 800px;
            margin: var(--spacing-xl) auto;
            background-color: var(--surface-0);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            box-shadow: var(--shadow-md);
        }
        
        .timeline-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--heading);
            margin-bottom: var(--spacing-lg);
            text-align: center;
        }
        
        .timeline-items {
            display: flex;
            justify-content: space-between;
            position: relative;
        }
        
        .timeline-items::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            right: 0;
            height: 2px;
            background-color: var(--border-default);
            z-index: 1;
        }
        
        .timeline-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
        }
        
        .timeline-dot {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: var(--spacing-sm);
        }
        
        .dot-investigating {
            background-color: var(--error);
            color: white;
        }
        
        .dot-identified {
            background-color: var(--warning);
            color: white;
        }
        
        .dot-fixing {
            background-color: var(--primary);
            color: white;
        }
        
        .dot-resolved {
            background-color: var(--surface-2);
            border: 2px solid var(--border-default);
            color: var(--subtext);
        }
        
        .timeline-label {
            font-size: 0.85rem;
            color: var(--subtext);
            text-align: center;
        }
        
        /* ===== DECORATIONS ===== */
        .decoration {
            position: absolute;
            z-index: 1;
            opacity: 0.7;
        }
        
        .error-dot {
            width: 12px;
            height: 12px;
            background-color: var(--error-light);
            border-radius: 50%;
            animation: floatError 6s ease-in-out infinite;
            box-shadow: 0 0 15px var(--error-light);
        }
        
        .error-dot-1 {
            top: 15%;
            left: 10%;
            animation-delay: 0s;
        }
        
        .error-dot-2 {
            top: 25%;
            right: 15%;
            animation-delay: 2s;
        }
        
        .error-dot-3 {
            bottom: 20%;
            left: 20%;
            animation-delay: 4s;
        }
        
        .error-wave {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 100px;
            background: linear-gradient(to right, transparent, rgba(227, 79, 79, 0.05), transparent);
            animation: waveMove 10s linear infinite;
            z-index: 0;
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
            color: var(--primary);
        }
        
        /* ===== ANIMATIONS ===== */
        @keyframes shakeError {
            0% { transform: translateX(0) rotate(0deg); }
            100% { transform: translateX(5px) rotate(5deg); }
        }
        
        @keyframes pulseError {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        @keyframes serverError1 {
            0%, 100% { transform: translateX(0); background: linear-gradient(to right, var(--surface-2), var(--surface-0)); }
            50% { transform: translateX(5px); background: linear-gradient(to right, #FFE5E5, #FFCCCC); }
        }
        
        @keyframes serverError2 {
            0%, 100% { transform: translateX(0); background: linear-gradient(to right, var(--surface-2), var(--surface-0)); }
            50% { transform: translateX(-5px); background: linear-gradient(to right, #FFE5E5, #FFCCCC); }
        }
        
        @keyframes serverError3 {
            0%, 100% { transform: translateX(0); background: linear-gradient(to right, var(--surface-2), var(--surface-0)); }
            50% { transform: translateX(3px); background: linear-gradient(to right, #FFE5E5, #FFCCCC); }
        }
        
        @keyframes serverError4 {
            0%, 100% { transform: translateX(0); background: linear-gradient(to right, var(--surface-2), var(--surface-0)); }
            50% { transform: translateX(-3px); background: linear-gradient(to right, #FFE5E5, #FFCCCC); }
        }
        
        @keyframes blinkError {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
        
        @keyframes floatError {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }
        
        @keyframes waveMove {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        /* ===== RESPONSIVE DESIGN ===== */
        @media (max-width: 768px) {
            .error-number {
                font-size: 6rem;
            }
            
            .error-title {
                font-size: 2rem;
            }
            
            .timeline-items {
                flex-direction: column;
                gap: var(--spacing-xl);
            }
            
            .timeline-items::before {
                display: none;
            }
            
            .timeline-item {
                flex-direction: row;
                justify-content: flex-start;
                gap: var(--spacing-md);
            }
            
            .timeline-dot {
                margin-bottom: 0;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 300px;
            }
            
            .server-rack {
                transform: scale(0.8);
            }
            
            .system-status {
                display: none;
            }
        }
        
        @media (max-width: 480px) {
            .error-number {
                font-size: 4.5rem;
            }
            
            .error-title {
                font-size: 1.7rem;
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
            
            .error-details {
                padding: var(--spacing-md);
            }
        }
    </style>
</head>
<body>
    <!-- Main Content -->
    <main class="main-content">
        <!-- Background Decorations -->
        <div class="decoration error-dot error-dot-1"></div>
        <div class="decoration error-dot error-dot-2"></div>
        <div class="decoration error-dot error-dot-3"></div>
        <div class="error-wave"></div>
        
        <div class="error-container">
            <!-- Error Number -->
            <div class="error-number">
                5<span class="zero">0</span>0
            </div>
            
            <!-- Error Title & Message -->
            <h1 class="error-title">Internal Server Error</h1>
            <p class="error-subtitle">
                Our servers are experiencing some technical difficulties. Our engineering team has been notified 
                and is working to resolve the issue as quickly as possible.
            </p>
            
            <!-- Server Error Illustration -->
            <div class="server-illustration">
                <div class="server-rack">
                    <div class="server-blade">
                        <div class="server-error-light"></div>
                    </div>
                    <div class="server-blade">
                        <div class="server-error-light"></div>
                    </div>
                    <div class="server-blade">
                        <div class="server-error-light"></div>
                    </div>
                    <div class="server-blade">
                        <div class="server-error-light"></div>
                    </div>
                </div>
                
                <div class="error-symbols symbol-1">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="error-symbols symbol-2">
                    <i class="fas fa-bug"></i>
                </div>
                <div class="error-symbols symbol-3">
                    <i class="fas fa-server"></i>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
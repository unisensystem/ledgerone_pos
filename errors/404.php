<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FuelingSys ERP | Page Not Found</title>
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
        
        .nav-links {
            display: flex;
            gap: var(--spacing-xl);
        }
        
        .nav-link {
            color: var(--body);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            transition: color 0.3s ease;
            position: relative;
        }
        
        .nav-link:hover {
            color: var(--primary);
        }
        
        .nav-link:hover::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: var(--primary);
            border-radius: 1px;
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
            max-width: 800px;
            width: 100%;
            text-align: center;
            z-index: 2;
        }
        
        /* ===== ERROR NUMBER ===== */
        .error-number {
            font-size: 10rem;
            font-weight: 800;
            color: var(--primary);
            line-height: 1;
            margin-bottom: var(--spacing-sm);
            position: relative;
            display: inline-block;
        }
        
        .error-number::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, var(--cute-accent-2) 0%, transparent 70%);
            opacity: 0.15;
            z-index: -1;
            border-radius: 50%;
        }
        
        .error-number .zero {
            display: inline-block;
            position: relative;
            animation: floatZero 4s ease-in-out infinite;
        }
        
        .error-number .zero:nth-child(2) {
            animation-delay: 0.5s;
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
            max-width: 600px;
            margin: 0 auto var(--spacing-xl);
        }
        
        /* ===== ERROR ILLUSTRATION ===== */
        .error-illustration {
            margin: var(--spacing-xl) 0;
            position: relative;
            height: 200px;
        }
        
        .lost-file {
            position: relative;
            width: 120px;
            height: 150px;
            background-color: var(--surface-0);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            animation: floatFile 6s ease-in-out infinite;
            overflow: hidden;
        }
        
        .file-tab {
            position: absolute;
            top: 0;
            left: 20px;
            width: 40px;
            height: 15px;
            background-color: var(--cute-accent-1);
            border-radius: var(--radius-sm) var(--radius-sm) 0 0;
        }
        
        .file-content {
            padding: var(--spacing-lg);
            text-align: center;
        }
        
        .file-icon {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: var(--spacing-sm);
        }
        
        .file-text {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--heading);
        }
        
        .search-icon {
            position: absolute;
            width: 60px;
            height: 60px;
            background-color: var(--cute-accent-3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--success);
            font-size: 1.5rem;
            z-index: 1;
        }
        
        .search-icon-1 {
            top: 20px;
            left: 25%;
            animation: searchMove 8s linear infinite;
        }
        
        .search-icon-2 {
            bottom: 40px;
            right: 25%;
            animation: searchMove 10s linear infinite reverse;
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
        
        /* ===== QUICK LINKS ===== */
        .quick-links {
            margin-top: var(--spacing-xl);
            padding-top: var(--spacing-xl);
            border-top: 1px solid var(--border-default);
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .quick-links-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--heading);
            margin-bottom: var(--spacing-md);
        }
        
        .links-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-md);
        }
        
        .link-card {
            background-color: var(--surface-0);
            padding: var(--spacing-md);
            border-radius: var(--radius-md);
            text-decoration: none;
            color: var(--body);
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            transition: all 0.3s ease;
            border: 1px solid var(--border-default);
        }
        
        .link-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary);
        }
        
        .link-icon {
            width: 40px;
            height: 40px;
            background-color: var(--surface-2);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.1rem;
        }
        
        .link-text {
            font-weight: 500;
        }
        
        /* ===== DECORATIONS ===== */
        .decoration {
            position: absolute;
            z-index: 1;
            opacity: 0.7;
        }
        
        .dot-decoration {
            width: 10px;
            height: 10px;
            background-color: var(--cute-accent-1);
            border-radius: 50%;
            animation: float 5s ease-in-out infinite;
        }
        
        .dot-1 {
            top: 15%;
            left: 10%;
            animation-delay: 0s;
        }
        
        .dot-2 {
            top: 25%;
            right: 15%;
            background-color: var(--cute-accent-2);
            animation-delay: 1s;
        }
        
        .dot-3 {
            bottom: 20%;
            left: 20%;
            background-color: var(--cute-accent-3);
            animation-delay: 2s;
        }
        
        .square-decoration {
            width: 20px;
            height: 20px;
            background-color: var(--primary);
            opacity: 0.1;
            border-radius: var(--radius-sm);
            animation: rotate 20s linear infinite;
        }
        
        .square-1 {
            top: 10%;
            right: 20%;
        }
        
        .square-2 {
            bottom: 15%;
            left: 10%;
            animation-direction: reverse;
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
        @keyframes floatZero {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        
        @keyframes floatFile {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            33% { transform: translateY(-15px) rotate(2deg); }
            66% { transform: translateY(10px) rotate(-2deg); }
        }
        
        @keyframes searchMove {
            0% { transform: translateX(-100px) rotate(0deg); }
            100% { transform: translateX(500px) rotate(360deg); }
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }
        
        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* ===== RESPONSIVE DESIGN ===== */
        @media (max-width: 768px) {
            .error-number {
                font-size: 7rem;
            }
            
            .error-title {
                font-size: 2rem;
            }
            
            .nav-links {
                display: none;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 300px;
            }
            
            .links-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .error-number {
                font-size: 5rem;
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
        }
    </style>
</head>
<body>
    <!-- Main Content -->
    <main class="main-content">
        <!-- Background Decorations -->
        <div class="decoration dot-decoration dot-1"></div>
        <div class="decoration dot-decoration dot-2"></div>
        <div class="decoration dot-decoration dot-3"></div>
        <div class="decoration square-decoration square-1"></div>
        <div class="decoration square-decoration square-2"></div>
        
        <div class="error-container">
            <!-- Error Number -->
            <div class="error-number">
                4<span class="zero">0</span>4
            </div>
            
            <!-- Error Title & Message -->
            <h1 class="error-title">Page Not Found</h1>
            <p class="error-subtitle">
                Oops! The page you're looking for seems to have wandered off into the digital void. 
                It might have been moved, deleted, or perhaps it never existed in the first place.
            </p>
            
            <!-- Error Illustration -->
            <div class="error-illustration">
                <div class="search-icon search-icon-1">
                    <i class="fas fa-search"></i>
                </div>
                
                <div class="lost-file">
                    <div class="file-tab"></div>
                    <div class="file-content">
                        <div class="file-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="file-text">
                            PAGE_404.EXT
                        </div>
                    </div>
                </div>
                
                <div class="search-icon search-icon-2">
                    <i class="fas fa-search"></i>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Search functionality
        document.getElementById('searchBtn').addEventListener('click', function() {
            const query = prompt("What would you like to search for?");
            if (query && query.trim() !== "") {
                alert(`Searching for: "${query}"\n\nIn a real application, this would redirect to search results.`);
                // In a real app: window.location.href = `/search?q=${encodeURIComponent(query)}`;
            }
        });
        
        // Animate the zeroes with different delays
        const zeros = document.querySelectorAll('.zero');
        zeros.forEach((zero, index) => {
            zero.style.animationDelay = `${index * 0.3}s`;
        });
        
        // Add hover effect to lost file
        const lostFile = document.querySelector('.lost-file');
        lostFile.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-10px) scale(1.05)';
            this.style.boxShadow = '0 15px 30px rgba(0,0,0,0.15)';
        });
        
        lostFile.addEventListener('mouseleave', function() {
            this.style.transform = '';
            this.style.boxShadow = '';
        });
        
        // Add click effect to link cards
        const linkCards = document.querySelectorAll('.link-card');
        linkCards.forEach(card => {
            card.addEventListener('click', function(e) {
                e.preventDefault();
                const linkText = this.querySelector('.link-text').textContent;
                alert(`Navigating to: ${linkText}\n\nIn a real application, this would redirect to the appropriate page.`);
            });
        });
    </script>
</body>
</html>
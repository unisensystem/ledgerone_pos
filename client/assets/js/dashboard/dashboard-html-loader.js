/**
 * Dashboard HTML Loader - For HTML files that can't use PHP includes
 */
(function() {
    'use strict';
    
    // Create navbar container
    const container = document.createElement('div');
    container.id = 'navbar-container';
    document.body.insertBefore(container, document.body.firstChild);
    
    // Load FontAwesome
    const fontAwesome = document.createElement('link');
    fontAwesome.rel = 'stylesheet';
    fontAwesome.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css';
    document.head.appendChild(fontAwesome);
    
    // Load dashboard core with cache busting
    const dashboardScript = document.createElement('script');
    dashboardScript.src = '/ledgerone_erp/client/assets/js/dashboard/dashboard-core.js?v=' + Date.now();
    dashboardScript.async = false;
    document.head.appendChild(dashboardScript);
    
    // Add content margin styles
    const style = document.createElement('style');
    style.textContent = `
        .main-content { margin-left: 60px; padding: 20px; transition: margin-left 0.3s ease; }
        .content { margin-left: 60px !important; }
        nav:not(.compact-nav) { display: none !important; }
        @media (max-width: 768px) { .main-content, .content { margin-left: 0; } }
    `;
    document.head.appendChild(style);
})();
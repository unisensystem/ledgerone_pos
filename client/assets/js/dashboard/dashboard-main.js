// Add interactivity to KPI cards
document.querySelectorAll('.kpi-card').forEach(card => {
    card.addEventListener('click', function () {
        this.style.transform = 'translateY(-2px)';
        this.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)';

        setTimeout(() => {
            this.style.transform = '';
            this.style.boxShadow = '';
        }, 200);
    });
});
/* assets/js/main.js */

document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle logic for mobile if needed (already handled by Bootstrap Offcanvas)
    
    // Smooth transitions for glass cards
    const cards = document.querySelectorAll('.glass-card');
    cards.forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'all 0.5s ease-out';
    });

    setTimeout(() => {
        cards.forEach((card, index) => {
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
    }, 100);

    // YouTube Embed link validation (Optional Client-side)
    const youtubeInput = document.querySelector('input[name="youtube_url"]');
    if (youtubeInput) {
        youtubeInput.addEventListener('change', function() {
            const url = this.value;
            if (url && !url.includes('youtube.com') && !url.includes('youtu.be')) {
                alert('Please enter a valid YouTube URL.');
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });
    }

    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        setTimeout(() => {
            const btn = alert.querySelector('.btn-close');
            if (btn) btn.click();
        }, 5000);
    });
});

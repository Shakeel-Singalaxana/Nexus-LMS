// assets/js/main.js
document.addEventListener('DOMContentLoaded', function() {
    const themeCheckbox = document.getElementById('themeCheckbox');
    const html = document.documentElement;

    function setTheme(theme, save = false) {
        html.setAttribute('data-theme', theme);
        if (themeCheckbox) themeCheckbox.checked = (theme === 'dark');
        
        // Update Icon
        const icon = document.querySelector('.theme-icon');
        if (icon) {
            icon.className = theme === 'dark' ? 'bi bi-sun-fill ms-2 theme-icon text-warning' : 'bi bi-moon-stars-fill ms-2 theme-icon';
        }

        if (save) saveThemePreference(theme);
    }

    function toggleTheme(e) {
        const theme = e.target.checked ? 'dark' : 'light';
        setTheme(theme, true);
    }
    
    // Initial UI update based on current theme
    const currentTheme = html.getAttribute('data-theme') || 'light';
    setTheme(currentTheme);

    function saveThemePreference(theme) {
        // Determine path to API (handle different directory depths)
        let pathParts = window.location.pathname.split('/');
        // Filter out empty strings from splitting
        pathParts = pathParts.filter(part => part.length > 0);
        
        // This logic is safer: calculate steps back to root based on known structure
        // If we are in admin/ or student/ or auth/, we need to go back 1 level
        const currentDir = pathParts[pathParts.length - 2];
        const isSubfolder = ['admin', 'student', 'auth'].includes(currentDir);
        
        const apiPath = isSubfolder ? '../auth/api_update_theme.php' : 'auth/api_update_theme.php';

        fetch(apiPath, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ theme: theme })
        })
        .then(response => response.json())
        .then(data => {
            console.log('Theme preference saved:', data);
        })
        .catch(err => console.error('Error saving theme:', err));
    }

    if (themeCheckbox) themeCheckbox.addEventListener('change', toggleTheme);
});

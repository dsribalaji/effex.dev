(function() {
    const saved = localStorage.getItem('daynight-theme');
    if (saved === 'carbon') {
        document.documentElement.classList.add('carbon');
        document.body.classList.add('carbon');
    }
})();

function initTheme() {
    const savedTheme = localStorage.getItem('daynight-theme');
    if (savedTheme === 'carbon') {
        document.documentElement.classList.add('carbon');
        document.body.classList.add('carbon');
        updateThemeButtons('carbon');
    } else {
        updateThemeButtons('snow');
    }
}

function setTheme(theme) {
    if (theme === 'carbon') {
        document.documentElement.classList.add('carbon');
        document.body.classList.add('carbon');
        localStorage.setItem('daynight-theme', 'carbon');
    } else {
        document.documentElement.classList.remove('carbon');
        document.body.classList.remove('carbon');
        localStorage.setItem('daynight-theme', 'snow');
    }
    updateThemeButtons(theme);
}

function updateThemeButtons(theme) {
    document.querySelectorAll('.theme-btn-snow').forEach(function(btn) {
        btn.classList.toggle('active', theme === 'snow');
    });
    document.querySelectorAll('.theme-btn-carbon').forEach(function(btn) {
        btn.classList.toggle('active', theme === 'carbon');
    });
}

document.addEventListener('DOMContentLoaded', initTheme);

/**
 * Theme Manager
 * Handles dark/light theme switching
 */

class ThemeManager {
    constructor() {
        this.themeKey = 'clock-it-theme';
        this.DEFAULT_THEME = 'light';
        this.init();
    }

    init() {
        const savedTheme = localStorage.getItem(this.themeKey) || this.getSystemTheme();
        this.setTheme(savedTheme);
        this.attachListeners();
    }

    getSystemTheme() {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }
        return this.DEFAULT_THEME;
    }

    setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        document.body.className = theme === 'dark' ? 'dark-theme' : 'light-theme';
        localStorage.setItem(this.themeKey, theme);
        this.updateThemeIcon(theme);

        // Send to server to save preference
        const appBaseUrl = window.APP_BASE_URL || document.documentElement.dataset.baseUrl || '';
        const themeEndpoint = `${appBaseUrl}/api/user/theme.php`;
        fetch(themeEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ theme: theme })
        }).catch(e => console.log('Theme preference save skipped'));
    }

    updateThemeIcon(theme) {
        const icon = document.getElementById('themeIcon');
        if (icon) {
            icon.textContent = theme === 'dark' ? '☀️' : '🌙';
        }
    }

    toggleTheme() {
        const current = document.documentElement.getAttribute('data-theme') || this.DEFAULT_THEME;
        const newTheme = current === 'dark' ? 'light' : 'dark';
        this.setTheme(newTheme);
    }

    attachListeners() {
        const toggle = document.getElementById('themeToggle');
        if (toggle) {
            toggle.addEventListener('click', () => this.toggleTheme());
        }

        // Listen for system theme changes
        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
                if (!localStorage.getItem(this.themeKey)) {
                    this.setTheme(e.matches ? 'dark' : 'light');
                }
            });
        }
    }
}

// Initialize theme on page load
document.addEventListener('DOMContentLoaded', () => {
    new ThemeManager();
});

/**
 * Main application JavaScript
 * Common functionality
 */

const APP_BASE_URL = window.APP_BASE_URL || document.documentElement.dataset.baseUrl || '';

// Mobile menu toggle
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.getElementById('menuToggle');
    const navMenu = document.getElementById('navMenu');

    if (menuToggle && navMenu) {
        menuToggle.addEventListener('click', function() {
            navMenu.classList.toggle('active');
        });

        // Close menu when clicking on a nav link
        const navLinks = navMenu.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                navMenu.classList.remove('active');
            });
        });

        // Close menu when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.navbar')) {
                navMenu.classList.remove('active');
            }
        });
    }
});

/**
 * API Helper
 */
class API {
    static async request(method, url, data = null) {
        const resolvedUrl = url.startsWith('/')
            ? (url.startsWith(APP_BASE_URL + '/') ? url : `${APP_BASE_URL}${url}`)
            : url;

        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            }
        };

        if (data) {
            options.body = JSON.stringify(data);
        }

        try {
            const response = await fetch(url, options);
            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'Request failed');
            }

            return result;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    static async get(url) {
        return this.request('GET', url);
    }

    static async post(url, data) {
        return this.request('POST', url, data);
    }

    static async put(url, data) {
        return this.request('PUT', url, data);
    }

    static async delete(url) {
        return this.request('DELETE', url);
    }
}

/**
 * Notification System
 */
class Notification {
    static show(message, type = 'info', duration = 5000) {
        const container = document.getElementById('notificationContainer') || this.createContainer();
        
        const notification = document.createElement('div');
        notification.className = `alert alert-${type}`;
        notification.textContent = message;

        container.appendChild(notification);

        setTimeout(() => {
            notification.classList.add('fade-out');
            setTimeout(() => notification.remove(), 300);
        }, duration);
    }

    static createContainer() {
        const container = document.createElement('div');
        container.id = 'notificationContainer';
        container.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
        `;
        document.body.appendChild(container);
        return container;
    }

    static success(message, duration) {
        this.show(message, 'success', duration);
    }

    static error(message, duration) {
        this.show(message, 'error', duration);
    }

    static warning(message, duration) {
        this.show(message, 'warning', duration);
    }

    static info(message, duration) {
        this.show(message, 'info', duration);
    }
}

/**
 * Form Utilities
 */
class Form {
    static validate(formElement) {
        return formElement.checkValidity();
    }

    static getFormData(formElement) {
        return new FormData(formElement);
    }

    static getFormJSON(formElement) {
        const formData = new FormData(formElement);
        const json = {};
        for (let [key, value] of formData) {
            json[key] = value;
        }
        return json;
    }

    static disable(formElement) {
        const inputs = formElement.querySelectorAll('input, select, textarea, button');
        inputs.forEach(input => input.disabled = true);
    }

    static enable(formElement) {
        const inputs = formElement.querySelectorAll('input, select, textarea, button');
        inputs.forEach(input => input.disabled = false);
    }

    static clear(formElement) {
        formElement.reset();
    }

    static setFieldError(fieldName, message) {
        const field = document.querySelector(`[name="${fieldName}"]`);
        if (field) {
            field.classList.add('error');
            field.setAttribute('data-error', message);
        }
    }

    static clearFieldError(fieldName) {
        const field = document.querySelector(`[name="${fieldName}"]`);
        if (field) {
            field.classList.remove('error');
            field.removeAttribute('data-error');
        }
    }
}

/**
 * Utilities
 */
class Utils {
    static formatDuration(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }

    static formatDate(date) {
        return new Intl.DateTimeFormat('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        }).format(new Date(date));
    }

    static formatDateTime(date) {
        return new Intl.DateTimeFormat('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }).format(new Date(date));
    }

    static debounce(func, delay) {
        let timeoutId;
        return function(...args) {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => func(...args), delay);
        };
    }

    static throttle(func, delay) {
        let lastCalled = 0;
        return function(...args) {
            const now = Date.now();
            if (now - lastCalled < delay) return;
            lastCalled = now;
            func(...args);
        };
    }

    static copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            Notification.success('Copied to clipboard!', 2000);
        });
    }

    static downloadJSON(data, filename) {
        const json = JSON.stringify(data, null, 2);
        const blob = new Blob([json], { type: 'application/json' });
        this.downloadFile(blob, filename);
    }

    static downloadCSV(data, filename) {
        let csv = '';
        if (Array.isArray(data) && data.length > 0) {
            // Headers
            csv += Object.keys(data[0]).join(',') + '\n';
            // Data
            data.forEach(row => {
                csv += Object.values(row).map(v => `"${v}"`).join(',') + '\n';
            });
        }
        const blob = new Blob([csv], { type: 'text/csv' });
        this.downloadFile(blob, filename);
    }

    static downloadFile(blob, filename) {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
    }

    static getQueryParam(param) {
        const params = new URLSearchParams(window.location.search);
        return params.get(param);
    }

    static setQueryParam(key, value) {
        const params = new URLSearchParams(window.location.search);
        params.set(key, value);
        window.history.replaceState({}, '', `?${params.toString()}`);
    }

    static isOnline() {
        return navigator.onLine;
    }
}

// Setup event listeners for offline/online
window.addEventListener('online', () => {
    Notification.success('Back online!');
});

window.addEventListener('offline', () => {
    Notification.warning('You are offline.');
});

/**
 * Add fade-out animation style
 */
const style = document.createElement('style');
style.textContent = `
    .fade-out {
        animation: fadeOut 0.3s ease-out forwards;
    }
    @keyframes fadeOut {
        from { opacity: 1; transform: translateX(0); }
        to { opacity: 0; transform: translateX(20px); }
    }
    input.error, select.error, textarea.error {
        border-color: #e74c3c !important;
    }
`;
document.head.appendChild(style);

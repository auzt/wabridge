/**
 * WhatsApp Bridge - JavaScript Utilities
 */

// Utility functions
const Utils = {
    // Copy text to clipboard
    copyToClipboard: function (text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function () {
                Utils.showNotification('Copied to clipboard!', 'success');
            }).catch(function (err) {
                console.error('Failed to copy: ', err);
                Utils.fallbackCopyTextToClipboard(text);
            });
        } else {
            Utils.fallbackCopyTextToClipboard(text);
        }
    },

    // Fallback copy method for older browsers
    fallbackCopyTextToClipboard: function (text) {
        const textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.top = "0";
        textArea.style.left = "0";
        textArea.style.position = "fixed";

        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            const successful = document.execCommand('copy');
            if (successful) {
                Utils.showNotification('Copied to clipboard!', 'success');
            } else {
                Utils.showNotification('Failed to copy to clipboard', 'error');
            }
        } catch (err) {
            console.error('Fallback: Could not copy text: ', err);
            Utils.showNotification('Copy not supported in this browser', 'error');
        }

        document.body.removeChild(textArea);
    },

    // Show notification
    showNotification: function (message, type = 'info', duration = 3000) {
        // Remove existing notifications
        const existing = document.querySelectorAll('.notification');
        existing.forEach(el => el.remove());

        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;

        // Style notification
        Object.assign(notification.style, {
            position: 'fixed',
            top: '20px',
            right: '20px',
            padding: '1rem 1.5rem',
            borderRadius: '4px',
            color: 'white',
            fontWeight: '500',
            zIndex: '9999',
            opacity: '0',
            transform: 'translateY(-20px)',
            transition: 'all 0.3s ease'
        });

        // Set background color based on type
        const colors = {
            success: '#27ae60',
            error: '#e74c3c',
            warning: '#f39c12',
            info: '#3498db'
        };
        notification.style.backgroundColor = colors[type] || colors.info;

        // Add to DOM
        document.body.appendChild(notification);

        // Animate in
        setTimeout(() => {
            notification.style.opacity = '1';
            notification.style.transform = 'translateY(0)';
        }, 10);

        // Auto remove
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateY(-20px)';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, duration);
    },

    // Format phone number
    formatPhoneNumber: function (phone) {
        // Remove all non-numeric characters
        let cleaned = phone.replace(/\D/g, '');

        // Add country code if missing
        if (cleaned.startsWith('0')) {
            cleaned = '62' + cleaned.substring(1);
        } else if (!cleaned.startsWith('62')) {
            cleaned = '62' + cleaned;
        }

        return cleaned;
    },

    // Validate phone number
    validatePhoneNumber: function (phone) {
        const formatted = Utils.formatPhoneNumber(phone);
        return formatted.length >= 10 && formatted.length <= 15;
    },

    // Format file size
    formatFileSize: function (bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';

        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB'];

        const i = Math.floor(Math.log(bytes) / Math.log(k));

        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    },

    // Time ago formatter
    timeAgo: function (dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffInSeconds = Math.floor((now - date) / 1000);

        if (diffInSeconds < 60) {
            return 'Just now';
        } else if (diffInSeconds < 3600) {
            const minutes = Math.floor(diffInSeconds / 60);
            return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
        } else if (diffInSeconds < 86400) {
            const hours = Math.floor(diffInSeconds / 3600);
            return `${hours} hour${hours > 1 ? 's' : ''} ago`;
        } else if (diffInSeconds < 2592000) {
            const days = Math.floor(diffInSeconds / 86400);
            return `${days} day${days > 1 ? 's' : ''} ago`;
        } else {
            return date.toLocaleDateString();
        }
    },

    // Debounce function
    debounce: function (func, wait, immediate) {
        let timeout;
        return function executedFunction() {
            const context = this;
            const args = arguments;
            const later = function () {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    },

    // AJAX helper
    ajax: function (options) {
        const defaults = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        };

        const config = Object.assign({}, defaults, options);

        return fetch(config.url, config)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .catch(error => {
                console.error('AJAX Error:', error);
                throw error;
            });
    }
};

// Modal functionality
const Modal = {
    open: function (modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
    },

    close: function (modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    },

    closeOnOutsideClick: function (modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    Modal.close(modalId);
                }
            });
        }
    }
};

// Form validation
const Validator = {
    rules: {
        required: function (value) {
            return value.trim() !== '';
        },
        email: function (value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(value);
        },
        phone: function (value) {
            return Utils.validatePhoneNumber(value);
        },
        url: function (value) {
            try {
                new URL(value);
                return true;
            } catch {
                return false;
            }
        },
        minLength: function (value, min) {
            return value.length >= min;
        },
        maxLength: function (value, max) {
            return value.length <= max;
        }
    },

    validate: function (form) {
        const errors = [];
        const inputs = form.querySelectorAll('[data-validate]');

        inputs.forEach(input => {
            const rules = input.dataset.validate.split('|');
            const value = input.value;
            const fieldName = input.dataset.name || input.name || 'Field';

            rules.forEach(rule => {
                const [ruleName, ruleValue] = rule.split(':');

                if (this.rules[ruleName]) {
                    const isValid = ruleValue ?
                        this.rules[ruleName](value, ruleValue) :
                        this.rules[ruleName](value);

                    if (!isValid) {
                        errors.push({
                            field: fieldName,
                            rule: ruleName,
                            element: input
                        });
                    }
                }
            });
        });

        return errors;
    },

    showErrors: function (errors) {
        // Clear previous errors
        document.querySelectorAll('.error-message').forEach(el => el.remove());
        document.querySelectorAll('.error').forEach(el => el.classList.remove('error'));

        errors.forEach(error => {
            const errorEl = document.createElement('div');
            errorEl.className = 'error-message';
            errorEl.style.color = '#e74c3c';
            errorEl.style.fontSize = '0.8rem';
            errorEl.style.marginTop = '0.25rem';
            errorEl.textContent = this.getErrorMessage(error);

            error.element.classList.add('error');
            error.element.style.borderColor = '#e74c3c';
            error.element.parentNode.appendChild(errorEl);
        });
    },

    getErrorMessage: function (error) {
        const messages = {
            required: `${error.field} is required`,
            email: `${error.field} must be a valid email`,
            phone: `${error.field} must be a valid phone number`,
            url: `${error.field} must be a valid URL`,
            minLength: `${error.field} is too short`,
            maxLength: `${error.field} is too long`
        };

        return messages[error.rule] || `${error.field} is invalid`;
    }
};

// Auto-refresh functionality
const AutoRefresh = {
    intervals: new Map(),

    start: function (callback, interval = 30000, immediate = false) {
        if (immediate) {
            callback();
        }

        const intervalId = setInterval(callback, interval);
        this.intervals.set(callback, intervalId);

        return intervalId;
    },

    stop: function (callback) {
        const intervalId = this.intervals.get(callback);
        if (intervalId) {
            clearInterval(intervalId);
            this.intervals.delete(callback);
        }
    },

    stopAll: function () {
        this.intervals.forEach((intervalId) => {
            clearInterval(intervalId);
        });
        this.intervals.clear();
    }
};

// DOM ready
document.addEventListener('DOMContentLoaded', function () {
    // Initialize tooltips
    const tooltips = document.querySelectorAll('[data-tooltip]');
    tooltips.forEach(element => {
        element.addEventListener('mouseenter', function () {
            // Tooltip implementation would go here
        });
    });

    // Initialize modals
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        Modal.closeOnOutsideClick(modal.id);

        const closeButtons = modal.querySelectorAll('.close, [data-close]');
        closeButtons.forEach(button => {
            button.addEventListener('click', () => {
                Modal.close(modal.id);
            });
        });
    });

    // Initialize forms with validation
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        form.addEventListener('submit', function (event) {
            const errors = Validator.validate(form);
            if (errors.length > 0) {
                event.preventDefault();
                Validator.showErrors(errors);
                Utils.showNotification('Please fix the errors below', 'error');
            }
        });
    });

    // Initialize copy buttons
    const copyButtons = document.querySelectorAll('[data-copy]');
    copyButtons.forEach(button => {
        button.addEventListener('click', function () {
            const text = this.dataset.copy;
            Utils.copyToClipboard(text);
        });
    });

    // Initialize phone number formatting
    const phoneInputs = document.querySelectorAll('input[type="tel"], input[data-phone]');
    phoneInputs.forEach(input => {
        input.addEventListener('blur', function () {
            if (this.value) {
                this.value = Utils.formatPhoneNumber(this.value);
            }
        });
    });

    // Auto-save form data to localStorage (if enabled)
    const autoSaveForms = document.querySelectorAll('form[data-autosave]');
    autoSaveForms.forEach(form => {
        const formId = form.id || 'autosave-form';

        // Load saved data
        const savedData = localStorage.getItem(`form-${formId}`);
        if (savedData) {
            try {
                const data = JSON.parse(savedData);
                Object.keys(data).forEach(key => {
                    const input = form.querySelector(`[name="${key}"]`);
                    if (input) {
                        input.value = data[key];
                    }
                });
            } catch (e) {
                console.warn('Failed to load saved form data:', e);
            }
        }

        // Save data on change
        const saveData = Utils.debounce(() => {
            const formData = new FormData(form);
            const data = {};
            for (let [key, value] of formData.entries()) {
                data[key] = value;
            }
            localStorage.setItem(`form-${formId}`, JSON.stringify(data));
        }, 1000);

        form.addEventListener('input', saveData);
        form.addEventListener('change', saveData);

        // Clear saved data on successful submit
        form.addEventListener('submit', function (event) {
            if (!event.defaultPrevented) {
                localStorage.removeItem(`form-${formId}`);
            }
        });
    });
});

// Page visibility API for auto-refresh
document.addEventListener('visibilitychange', function () {
    if (document.hidden) {
        AutoRefresh.stopAll();
    } else {
        // Restart auto-refresh when page becomes visible
        // This would be implemented per page
    }
});

// Global error handler
window.addEventListener('error', function (event) {
    console.error('Global error:', event.error);
    // Could send error reports to server here
});

// Expose utilities globally
window.Utils = Utils;
window.Modal = Modal;
window.Validator = Validator;
window.AutoRefresh = AutoRefresh;
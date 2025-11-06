/**
 * YalaGuard - Main JavaScript File
 * Common functionality used across all pages
 */

// Utility functions
const YalaGuard = {
    // Show notification message
    showNotification: function(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        
        // Add to page
        document.body.appendChild(notification);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 5000);
    },

    // Format date for display
    formatDate: function(date) {
        if (typeof date === 'string') {
            date = new Date(date);
        }
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    },

    // Format duration in seconds to human readable
    formatDuration: function(seconds) {
        if (seconds < 60) {
            return seconds + 's';
        } else if (seconds < 3600) {
            const minutes = Math.floor(seconds / 60);
            const remainingSeconds = seconds % 60;
            return minutes + 'm ' + remainingSeconds + 's';
        } else {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            return hours + 'h ' + minutes + 'm';
        }
    },

    // Validate email format
    validateEmail: function(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    },

    // Validate password strength
    validatePassword: function(password) {
        const minLength = 6;
        const hasUpperCase = /[A-Z]/.test(password);
        const hasLowerCase = /[a-z]/.test(password);
        const hasNumbers = /\d/.test(password);
        
        return {
            isValid: password.length >= minLength && hasUpperCase && hasLowerCase && hasNumbers,
            minLength: password.length >= minLength,
            hasUpperCase: hasUpperCase,
            hasLowerCase: hasLowerCase,
            hasNumbers: hasNumbers
        };
    },

    // Debounce function for performance
    debounce: function(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    // Throttle function for performance
    throttle: function(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    },

    // Local storage utilities
    storage: {
        set: function(key, value) {
            try {
                localStorage.setItem(key, JSON.stringify(value));
                return true;
            } catch (e) {
                console.error('Error saving to localStorage:', e);
                return false;
            }
        },

        get: function(key, defaultValue = null) {
            try {
                const item = localStorage.getItem(key);
                return item ? JSON.parse(item) : defaultValue;
            } catch (e) {
                console.error('Error reading from localStorage:', e);
                return defaultValue;
            }
        },

        remove: function(key) {
            try {
                localStorage.removeItem(key);
                return true;
            } catch (e) {
                console.error('Error removing from localStorage:', e);
                return false;
            }
        }
    },

    // API utilities
    api: {
        // Make API request
        request: async function(url, options = {}) {
            const defaultOptions = {
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin'
            };

            const finalOptions = { ...defaultOptions, ...options };

            try {
                const response = await fetch(url, finalOptions);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return await response.json();
                } else {
                    return await response.text();
                }
            } catch (error) {
                console.error('API request failed:', error);
                throw error;
            }
        },

        // GET request
        get: function(url) {
            return this.request(url, { method: 'GET' });
        },

        // POST request
        post: function(url, data) {
            return this.request(url, {
                method: 'POST',
                body: JSON.stringify(data)
            });
        },

        // PUT request
        put: function(url, data) {
            return this.request(url, {
                method: 'PUT',
                body: JSON.stringify(data)
            });
        },

        // DELETE request
        delete: function(url) {
            return this.request(url, { method: 'DELETE' });
        }
    },

    // Camera system utilities
    camera: {
        // Check if camera stream is accessible
        checkStreamAccess: async function(streamUrl) {
            try {
                const response = await fetch(streamUrl, { method: 'HEAD' });
                return response.ok;
            } catch (error) {
                return false;
            }
        },

        // Get camera snapshot
        getSnapshot: function(cameraUrl) {
            return new Promise((resolve, reject) => {
                const img = new Image();
                img.crossOrigin = 'anonymous';
                img.onload = () => resolve(img);
                img.onerror = reject;
                img.src = cameraUrl + '?' + new Date().getTime(); // Cache buster
            });
        }
    },

    // UI utilities
    ui: {
        // Show loading spinner
        showLoading: function(container) {
            const spinner = document.createElement('div');
            spinner.className = 'loading-spinner';
            spinner.innerHTML = '<div class="spinner"></div><p>Loading...</p>';
            container.appendChild(spinner);
            return spinner;
        },

        // Hide loading spinner
        hideLoading: function(spinner) {
            if (spinner && spinner.parentNode) {
                spinner.parentNode.removeChild(spinner);
            }
        },

        // Toggle element visibility
        toggle: function(element) {
            if (element.style.display === 'none') {
                element.style.display = 'block';
            } else {
                element.style.display = 'none';
            }
        },

        // Smooth scroll to element
        scrollTo: function(element, offset = 0) {
            const elementPosition = element.offsetTop - offset;
            window.scrollTo({
                top: elementPosition,
                behavior: 'smooth'
            });
        }
    }
};

// Initialize common functionality when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Add notification styles if not present
    if (!document.querySelector('#notification-styles')) {
        const style = document.createElement('style');
        style.id = 'notification-styles';
        style.textContent = `
            .notification {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 8px;
                color: white;
                font-weight: 500;
                z-index: 10000;
                animation: slideIn 0.3s ease;
            }
            
            .notification-info {
                background: #2196F3;
            }
            
            .notification-success {
                background: #4CAF50;
            }
            
            .notification-warning {
                background: #ff9800;
            }
            
            .notification-error {
                background: #f44336;
            }
            
            @keyframes slideIn {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            .loading-spinner {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 2rem;
            }
            
            .spinner {
                width: 40px;
                height: 40px;
                border: 4px solid #f3f3f3;
                border-top: 4px solid #667eea;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin-bottom: 1rem;
            }
            
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    }

    // Add escape key handler for modals and fullscreen
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            // Close any open modals or fullscreen views
            const modals = document.querySelectorAll('.modal, .fullscreen-overlay');
            modals.forEach(modal => {
                if (modal.style.display === 'block') {
                    modal.style.display = 'none';
                }
            });
        }
    });

    // Add click outside handler for modals
    document.addEventListener('click', function(e) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });
    });
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = YalaGuard;
}

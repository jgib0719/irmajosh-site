/**
 * IrmaJosh.com Main JavaScript
 * Handles HTMX configuration, utilities, and app functionality
 */

(function() {
    'use strict';
    
    // ========== HTMX Configuration ==========
    document.addEventListener('DOMContentLoaded', function() {
        // Configure HTMX to send CSRF token with every request
        document.body.addEventListener('htmx:configRequest', function(event) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            if (csrfToken) {
                event.detail.headers['X-CSRF-Token'] = csrfToken.content;
            }
        });
        
        // Handle HTMX errors
        document.body.addEventListener('htmx:responseError', function(event) {
            console.error('HTMX Error:', event.detail);
            showAlert('An error occurred. Please try again.', 'error');
        });
        
        // Handle successful HTMX responses with alerts
        document.body.addEventListener('htmx:afterSwap', function(event) {
            const response = event.detail.xhr;
            if (response) {
                const contentType = response.getResponseHeader('Content-Type');
                if (contentType && contentType.includes('application/json')) {
                    try {
                        const data = JSON.parse(response.responseText);
                        if (data.message) {
                            showAlert(data.message, data.success ? 'success' : 'error');
                        }
                    } catch (e) {
                        // Not JSON or failed to parse
                    }
                }
            }
        });
    });
    
    // ========== Alert System ==========
    window.showAlert = function(message, type = 'info') {
        const container = document.getElementById('alert-container');
        if (!container) return;
        
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.textContent = message;
        
        container.appendChild(alert);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            alert.style.transition = 'opacity 0.3s';
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.remove();
            }, 300);
        }, 5000);
    };
    
    // ========== Service Worker Registration ==========
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('/service-worker.js')
                .then(function(registration) {
                    console.log('ServiceWorker registered:', registration.scope);
                    
                    // Check for updates
                    registration.addEventListener('updatefound', function() {
                        const newWorker = registration.installing;
                        newWorker.addEventListener('statechange', function() {
                            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                // New service worker available
                                if (confirm('A new version is available. Reload to update?')) {
                                    window.location.reload();
                                }
                            }
                        });
                    });
                })
                .catch(function(err) {
                    console.log('ServiceWorker registration failed:', err);
                });
        });
    }
    
    // ========== PWA Install Prompt ==========
    let deferredPrompt;
    
    window.addEventListener('beforeinstallprompt', function(e) {
        e.preventDefault();
        deferredPrompt = e;
        
        // Show install button if you have one
        const installButton = document.getElementById('install-pwa');
        if (installButton) {
            installButton.style.display = 'block';
            installButton.addEventListener('click', function() {
                installButton.style.display = 'none';
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then(function(choiceResult) {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('PWA installed');
                    }
                    deferredPrompt = null;
                });
            });
        }
    });
    
    // ========== Online/Offline Detection ==========
    window.addEventListener('online', function() {
        showAlert('You are back online', 'success');
    });
    
    window.addEventListener('offline', function() {
        showAlert('You are offline. Some features may be unavailable.', 'warning');
    });
    
    // ========== Form Utilities ==========
    
    /**
     * Serialize form data to JSON
     */
    window.serializeForm = function(form) {
        const formData = new FormData(form);
        const data = {};
        for (const [key, value] of formData.entries()) {
            // Handle array fields (name ends with [])
            if (key.endsWith('[]')) {
                const arrayKey = key.slice(0, -2);
                if (!data[arrayKey]) {
                    data[arrayKey] = [];
                }
                data[arrayKey].push(value);
            } else {
                data[key] = value;
            }
        }
        return data;
    };
    
    /**
     * Disable form submit button and show loading state
     */
    window.setFormLoading = function(form, loading = true) {
        const submitButton = form.querySelector('[type="submit"]');
        if (submitButton) {
            submitButton.disabled = loading;
            if (loading) {
                submitButton.dataset.originalText = submitButton.textContent;
                submitButton.textContent = 'Loading...';
            } else {
                submitButton.textContent = submitButton.dataset.originalText || submitButton.textContent;
            }
        }
    };
    
    // ========== Date/Time Utilities ==========
    
    /**
     * Format date for display
     */
    window.formatDate = function(dateString, options = {}) {
        const date = new Date(dateString);
        const defaultOptions = {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };
        return date.toLocaleString('en-US', { ...defaultOptions, ...options });
    };
    
    /**
     * Convert date to datetime-local input format
     */
    window.toDateTimeLocal = function(date) {
        const d = new Date(date);
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        const hours = String(d.getHours()).padStart(2, '0');
        const minutes = String(d.getMinutes()).padStart(2, '0');
        return `${year}-${month}-${day}T${hours}:${minutes}`;
    };
    
    // ========== Local Storage Utilities ==========
    
    /**
     * Save data to localStorage with error handling
     */
    window.saveToStorage = function(key, value) {
        try {
            localStorage.setItem(key, JSON.stringify(value));
            return true;
        } catch (e) {
            console.error('Failed to save to localStorage:', e);
            return false;
        }
    };
    
    /**
     * Load data from localStorage
     */
    window.loadFromStorage = function(key, defaultValue = null) {
        try {
            const item = localStorage.getItem(key);
            return item ? JSON.parse(item) : defaultValue;
        } catch (e) {
            console.error('Failed to load from localStorage:', e);
            return defaultValue;
        }
    };
    
    /**
     * Remove item from localStorage
     */
    window.removeFromStorage = function(key) {
        try {
            localStorage.removeItem(key);
            return true;
        } catch (e) {
            console.error('Failed to remove from localStorage:', e);
            return false;
        }
    };
    
    // ========== API Request Helper ==========
    
    /**
     * Make authenticated API request
     */
    window.apiRequest = async function(url, options = {}) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken ? csrfToken.content : ''
            }
        };
        
        const mergedOptions = {
            ...defaultOptions,
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...options.headers
            }
        };
        
        try {
            const response = await fetch(url, mergedOptions);
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || 'Request failed');
            }
            
            return data;
        } catch (error) {
            console.error('API Request failed:', error);
            throw error;
        }
    };
    
    // ========== Debounce Utility ==========
    
    /**
     * Debounce function calls
     */
    window.debounce = function(func, wait = 300) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    };
    
    // ========== Initialize on Load ==========
    document.addEventListener('DOMContentLoaded', function() {
        // Display any flash messages from session
        const flashMessage = document.querySelector('[data-flash-message]');
        if (flashMessage) {
            const message = flashMessage.dataset.flashMessage;
            const type = flashMessage.dataset.flashType || 'info';
            showAlert(message, type);
        }
        
        // Initialize datetime-local inputs to current time if empty
        document.querySelectorAll('input[type="datetime-local"]').forEach(function(input) {
            if (!input.value && input.dataset.defaultNow) {
                input.value = toDateTimeLocal(new Date());
            }
        });
        
        // Auto-focus first form input on modals
        document.querySelectorAll('.modal').forEach(function(modal) {
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (modal.classList.contains('active')) {
                        const firstInput = modal.querySelector('input:not([type="hidden"]), textarea, select');
                        if (firstInput) {
                            setTimeout(() => firstInput.focus(), 100);
                        }
                    }
                });
            });
            observer.observe(modal, { attributes: true, attributeFilter: ['class'] });
        });
        
        console.log('IrmaJosh.com initialized');
    });
    
})();

/**
 * Alert/Notification Utilities
 * Simple toast notifications for user feedback
 */

/**
 * Show a toast notification
 * @param {string} message - The message to display
 * @param {string} type - The type of alert (success, error, warning, info)
 * @param {number} duration - Duration in milliseconds (default: 3000)
 */
window.showAlert = function(message, type = 'info', duration = 3000) {
    // Create alert container if it doesn't exist
    let container = document.getElementById('alert-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'alert-container';
        container.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 400px;
        `;
        document.body.appendChild(container);
    }
    
    // Create alert element
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.style.cssText = `
        padding: 1rem;
        border-radius: 0.5rem;
        border-left: 4px solid;
        background: var(--bg-primary);
        box-shadow: var(--shadow-lg);
        animation: slideIn 0.3s ease-out;
        min-width: 300px;
    `;
    
    // Set colors based on type
    const colors = {
        success: { bg: '#d1fae5', border: '#10b981', text: '#065f46' },
        error: { bg: '#fee2e2', border: '#ef4444', text: '#991b1b' },
        warning: { bg: '#fef3c7', border: '#f59e0b', text: '#92400e' },
        info: { bg: '#dbeafe', border: '#3b82f6', text: '#1e40af' }
    };
    
    const color = colors[type] || colors.info;
    alert.style.background = color.bg;
    alert.style.borderColor = color.border;
    alert.style.color = color.text;
    
    alert.textContent = message;
    
    // Add to container
    container.appendChild(alert);
    
    // Auto remove after duration
    setTimeout(() => {
        alert.style.animation = 'slideOut 0.3s ease-in';
        setTimeout(() => {
            container.removeChild(alert);
            if (container.children.length === 0) {
                document.body.removeChild(container);
            }
        }, 300);
    }, duration);
};

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

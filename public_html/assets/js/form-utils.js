/**
 * Form Utilities
 * Common form handling functions
 */

/**
 * Handle form submission with JSON
 * @param {HTMLFormElement} form - The form element
 * @param {Object} options - Configuration options
 * @param {string} options.url - URL to submit to
 * @param {string} options.method - HTTP method (default: POST)
 * @param {Function} options.onSuccess - Success callback
 * @param {Function} options.onError - Error callback
 * @param {Function} options.onFinally - Finally callback
 */
window.handleFormSubmit = async function(form, options = {}) {
    const {
        url,
        method = 'POST',
        onSuccess,
        onError,
        onFinally
    } = options;
    
    // Get CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]');
    
    // Serialize form data
    const formData = new FormData(form);
    const data = {};
    
    for (const [key, value] of formData.entries()) {
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
    
    // Add CSRF token
    if (csrfToken) {
        data.csrf_token = csrfToken.content;
    }
    
    // Set loading state
    setFormLoading(form, true);
    
    try {
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken ? csrfToken.content : ''
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (response.ok) {
            if (onSuccess) {
                onSuccess(result);
            }
            if (result.message) {
                showAlert(result.message, 'success');
            }
        } else {
            if (onError) {
                onError(result);
            }
            showAlert(result.error || 'An error occurred', 'error');
        }
        
        return result;
    } catch (error) {
        console.error('Form submission error:', error);
        if (onError) {
            onError(error);
        }
        showAlert('Failed to submit form', 'error');
        throw error;
    } finally {
        setFormLoading(form, false);
        if (onFinally) {
            onFinally();
        }
    }
};

/**
 * Populate form fields from data object
 * @param {HTMLFormElement} form - The form element
 * @param {Object} data - Data object with field values
 */
window.populateForm = function(form, data) {
    for (const [key, value] of Object.entries(data)) {
        const field = form.elements[key];
        
        if (!field) continue;
        
        if (field.type === 'checkbox') {
            field.checked = !!value;
        } else if (field.type === 'radio') {
            const radio = form.querySelector(`input[name="${key}"][value="${value}"]`);
            if (radio) {
                radio.checked = true;
            }
        } else {
            field.value = value || '';
        }
    }
};

/**
 * Clear all form validation errors
 * @param {HTMLFormElement} form - The form element
 */
window.clearFormErrors = function(form) {
    const errorElements = form.querySelectorAll('.error-message');
    errorElements.forEach(el => el.remove());
    
    const invalidFields = form.querySelectorAll('.is-invalid');
    invalidFields.forEach(field => field.classList.remove('is-invalid'));
};

/**
 * Display form validation errors
 * @param {HTMLFormElement} form - The form element
 * @param {Object} errors - Object with field names as keys and error messages as values
 */
window.displayFormErrors = function(form, errors) {
    clearFormErrors(form);
    
    for (const [fieldName, message] of Object.entries(errors)) {
        const field = form.elements[fieldName];
        if (field) {
            field.classList.add('is-invalid');
            
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.textContent = message;
            
            field.parentNode.insertBefore(errorDiv, field.nextSibling);
        }
    }
};

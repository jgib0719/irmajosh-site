/**
 * Modal Utilities
 * Reusable modal functions for opening/closing modals
 */

/**
 * Open a modal by ID
 * @param {string} modalId - The ID of the modal to open
 */
window.openModal = function(modalId) {
    console.log('openModal called with ID:', modalId);
    const modal = document.getElementById(modalId);
    console.log('Modal element:', modal);
    if (modal) {
        modal.classList.add('active');
        console.log('Modal opened successfully');
        
        // Focus first input
        setTimeout(() => {
            const firstInput = modal.querySelector('input:not([type="hidden"]), textarea, select');
            if (firstInput) {
                firstInput.focus();
            }
        }, 100);
    } else {
        console.error(`Modal with ID "${modalId}" not found`);
    }
};

/**
 * Close a modal by ID
 * @param {string} modalId - The ID of the modal to close
 */
window.closeModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        
        // Reset form if exists
        const form = modal.querySelector('form');
        if (form) {
            form.reset();
        }
    } else {
        console.error(`Modal with ID "${modalId}" not found`);
    }
};

/**
 * Close modal when clicking outside
 */
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal') && e.target.classList.contains('active')) {
        const modalId = e.target.id;
        if (modalId) {
            closeModal(modalId);
        }
    }
});

/**
 * Close modal when pressing Escape key
 */
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const activeModal = document.querySelector('.modal.active');
        if (activeModal && activeModal.id) {
            closeModal(activeModal.id);
        }
    }
});

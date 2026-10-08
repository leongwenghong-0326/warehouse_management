/**
 * Warehouse Management System
 * Vanilla JavaScript UI Interactions
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Mobile Sidebar Toggle
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    
    // Create overlay element if not present
    let overlay = document.querySelector('.sidebar-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
    }

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
        });

        overlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        });
    }

    // 2. Modals Control via Data Attributes
    document.querySelectorAll('[data-modal-target]').forEach(trigger => {
        trigger.addEventListener('click', (e) => {
            e.preventDefault();
            const modalId = trigger.getAttribute('data-modal-target');
            const modal = document.getElementById(modalId);
            if (modal) {
                openModal(modal);
            }
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach(closer => {
        closer.addEventListener('click', () => {
            const modal = closer.closest('.modal-backdrop');
            if (modal) {
                closeModal(modal);
            }
        });
    });

    // Close modal on clicking backdrop outside modal box
    document.querySelectorAll('.modal-backdrop').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModal(modal);
            }
        });
    });

    // 3. Confirmation Dialogs for destructive actions
    document.querySelectorAll('form[data-confirm]').forEach(form => {
        form.addEventListener('submit', (e) => {
            const message = form.getAttribute('data-confirm') || 'Are you sure you want to perform this action?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    document.querySelectorAll('a[data-confirm]').forEach(link => {
        link.addEventListener('click', (e) => {
            const message = link.getAttribute('data-confirm') || 'Are you sure you want to proceed?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    // 4. Auto-dismiss alerts after 6 seconds (optional enhancement)
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.4s ease';
            alert.style.opacity = '0';
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            }, 400);
        }, 6000);
    });
});

/**
 * Open a modal window
 */
function openModal(modalEl) {
    if (typeof modalEl === 'string') {
        modalEl = document.getElementById(modalEl);
    }
    if (modalEl) {
        modalEl.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
}

/**
 * Close a modal window
 */
function closeModal(modalEl) {
    if (typeof modalEl === 'string') {
        modalEl = document.getElementById(modalEl);
    }
    if (modalEl) {
        modalEl.classList.remove('show');
        document.body.style.overflow = '';
    }
}

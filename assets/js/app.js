/**
 * Nobin Fashion POS - Global Frontend JavaScript
 */

document.addEventListener('DOMContentLoaded', () => {
    // Initialize Modal triggers
    document.querySelectorAll('[data-modal-target]').forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-modal-target');
            openModal(targetId);
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('.modal-backdrop');
            if (modal) closeModal(modal.id);
        });
    });
});

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
    }
}

function showToast(message, type = 'success') {
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px;';
        document.body.appendChild(toastContainer);
    }

    const toast = document.createElement('div');
    const bgColor = type === 'success' ? 'var(--accent-emerald)' : (type === 'danger' ? 'var(--accent-rose)' : 'var(--accent-gold)');
    toast.style.cssText = `background: ${bgColor}; color: #fff; padding: 12px 20px; border-radius: 8px; font-weight: 600; box-shadow: 0 5px 15px rgba(0,0,0,0.3); font-size: 0.9rem; transition: all 0.3s ease; animation: fadeInRight 0.3s ease;`;
    toast.innerText = message;

    toastContainer.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(50px)';
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

// Quick Customer Modal AJAX submit handler
function handleQuickCustomerSubmit(e, callback) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);

    fetch('api/add-customer.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            form.reset();
            closeModal('modal-add-customer');
            if (typeof callback === 'function') {
                callback(data.customer);
            }
        } else {
            showToast(data.message || 'Failed to add customer', 'danger');
        }
    })
    .catch(err => {
        showToast('Server connection error', 'danger');
        console.error(err);
    });
}

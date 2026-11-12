// Local Jobs — Main JS
document.addEventListener('DOMContentLoaded', () => {
    // Auto-hide alerts
    document.querySelectorAll('.alert').forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity .5s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 500);
        }, 5000);
    });

    // Confirm action
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', e => {
            if (!confirm(el.dataset.confirm)) e.preventDefault();
        });
    });

    // File size validation (max 2MB)
    document.querySelectorAll('input[type=file]').forEach(input => {
        input.addEventListener('change', () => {
            if (input.files[0] && input.files[0].size > 2 * 1024 * 1024) {
                alert('File must be under 2MB');
                input.value = '';
            }
        });
    });
});
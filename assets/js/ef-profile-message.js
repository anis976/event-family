/**
 * Bouton « Envoyer un message » : désactivation visuelle à l'ouverture du formulaire.
 */
function initProfileMessageToggle() {
    const btnOpen = document.getElementById('btn-open-msg');
    const formContainer = document.getElementById('form-msg-profile');

    if (!btnOpen || !formContainer) {
        return;
    }

    btnOpen.addEventListener('click', () => {
        btnOpen.classList.add('disabled', 'ef-profile__msg-btn--muted');
        btnOpen.setAttribute('aria-disabled', 'true');
    });

    const btnCancel = formContainer.querySelector('[data-ef-msg-cancel]');
    if (btnCancel) {
        btnCancel.addEventListener('click', () => {
            btnOpen.classList.remove('disabled', 'ef-profile__msg-btn--muted');
            btnOpen.removeAttribute('aria-disabled');
        });
    }
}

document.addEventListener('DOMContentLoaded', initProfileMessageToggle);
document.addEventListener('turbo:load', initProfileMessageToggle);

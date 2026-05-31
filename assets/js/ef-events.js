/**
 * Modales événements : évite les conflits Bootstrap ↔ Turbo.
 */
function disposeEventModals() {
    document.querySelectorAll('.ef-events__modal, .modal[id^="modal-delete-event-"], #modal-delete-event-show').forEach((el) => {
        const instance = window.bootstrap?.Modal?.getInstance(el);
        if (instance) {
            instance.hide();
            instance.dispose();
        }
    });

    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');
    document.querySelectorAll('.modal-backdrop').forEach((el) => el.remove());
}

document.addEventListener('turbo:before-cache', disposeEventModals);

document.addEventListener('turbo:load', () => {
    document.querySelectorAll('[data-bs-toggle="modal"][data-turbo="false"]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const target = trigger.getAttribute('data-bs-target');
            if (!target) {
                return;
            }
            const modalEl = document.querySelector(target);
            if (modalEl && window.bootstrap?.Modal) {
                window.bootstrap.Modal.getOrCreateInstance(modalEl);
            }
        }, { once: false });
    });
});

export { disposeEventModals };

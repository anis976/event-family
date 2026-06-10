function getBootstrapModal(el) {
    return window.bootstrap?.Modal?.getOrCreateInstance(el);
}

function mountModalToBody(modalEl) {
    if (modalEl.parentElement !== document.body) {
        document.body.appendChild(modalEl);
    }
}

let viewModalBound = false;

export function initMessagePhotoLightbox() {
    const modalEl = document.getElementById('ef-message-photo-view-modal');
    if (!modalEl || viewModalBound) {
        return;
    }

    const imageEl = document.getElementById('ef-message-photo-view-image');
    const titleEl = document.getElementById('ef-message-photo-view-title');

    if (!imageEl) {
        return;
    }

    mountModalToBody(modalEl);
    viewModalBound = true;

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-ef-photo-view]');
        if (!trigger || !document.body.contains(trigger)) {
            return;
        }

        event.preventDefault();

        const viewUrl = trigger.dataset.efPhotoUrl ?? '';
        const label = trigger.dataset.efPhotoLabel ?? '';

        if ('' === viewUrl) {
            return;
        }

        imageEl.src = viewUrl;
        imageEl.alt = label;

        if (titleEl && '' !== label) {
            titleEl.textContent = label;
        }

        getBootstrapModal(modalEl)?.show();
    });

    modalEl.addEventListener('hidden.bs.modal', () => {
        imageEl.removeAttribute('src');
        imageEl.alt = '';
    });
}

document.addEventListener('turbo:load', initMessagePhotoLightbox);

if (document.readyState !== 'loading') {
    initMessagePhotoLightbox();
} else {
    document.addEventListener('DOMContentLoaded', initMessagePhotoLightbox);
}

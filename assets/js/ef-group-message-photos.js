const MAX_BYTES_DEFAULT = 5 * 1024 * 1024;
const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const CROPPER_CSS = 'https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css';
const CROPPER_JS = 'https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js';

let cropper = null;
let pendingFile = null;
let pendingSlot = null;
let objectUrl = null;
let cropperLoadPromise = null;

function getBootstrapModal(el) {
    return window.bootstrap?.Modal?.getOrCreateInstance(el);
}

function resetObjectUrl() {
    if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
        objectUrl = null;
    }
}

function destroyCropper() {
    if (cropper) {
        cropper.destroy();
        cropper = null;
    }
}

function loadStylesheet(href) {
    if (document.querySelector(`link[href="${href}"]`)) {
        return;
    }

    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = href;
    link.crossOrigin = 'anonymous';
    document.head.appendChild(link);
}

function loadScript(src) {
    const existing = document.querySelector(`script[src="${src}"]`);
    if (existing) {
        return existing.dataset.efLoaded === '1'
            ? Promise.resolve()
            : new Promise((resolve, reject) => {
                existing.addEventListener('load', () => resolve(), { once: true });
                existing.addEventListener('error', () => reject(new Error('script load failed')), { once: true });
            });
    }

    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = src;
        script.crossOrigin = 'anonymous';
        script.addEventListener('load', () => {
            script.dataset.efLoaded = '1';
            resolve();
        }, { once: true });
        script.addEventListener('error', () => reject(new Error('script load failed')), { once: true });
        document.head.appendChild(script);
    });
}

function ensureCropperLoaded() {
    if (typeof window.Cropper !== 'undefined') {
        return Promise.resolve();
    }

    if (!cropperLoadPromise) {
        cropperLoadPromise = (async () => {
            loadStylesheet(CROPPER_CSS);
            await loadScript(CROPPER_JS);
        })().catch((error) => {
            cropperLoadPromise = null;
            throw error;
        });
    }

    return cropperLoadPromise;
}

function assignFileToInput(input, file) {
    const dt = new DataTransfer();
    dt.items.add(file);
    input.files = dt.files;
}

function clearCropFields(prefix) {
    ['crop-x', 'crop-y', 'crop-w', 'crop-h'].forEach((suffix) => {
        const el = document.getElementById(`${prefix}-${suffix}`);
        if (el) {
            el.value = '';
        }
    });
}

function setCropFields(prefix, data) {
    document.getElementById(`${prefix}-crop-x`).value = Math.round(data.x);
    document.getElementById(`${prefix}-crop-y`).value = Math.round(data.y);
    document.getElementById(`${prefix}-crop-w`).value = Math.round(data.width);
    document.getElementById(`${prefix}-crop-h`).value = Math.round(data.height);
}

function renderPreviews(root, slots) {
    const previews = root.querySelector('[data-ef-photo-previews]');
    if (!previews) {
        return;
    }

    previews.innerHTML = '';
    const filled = slots.filter((slot) => slot.file);

    if (filled.length === 0) {
        previews.classList.add('d-none');
        return;
    }

    previews.classList.remove('d-none');

    filled.forEach((slot) => {
        const item = document.createElement('div');
        item.className = 'ef-messages__photo-preview position-relative';

        const img = document.createElement('img');
        img.src = URL.createObjectURL(slot.file);
        img.alt = '';
        img.className = 'ef-messages__photo-preview-img rounded-3';
        item.appendChild(img);

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn btn-sm btn-danger ef-messages__photo-preview-remove';
        removeBtn.innerHTML = '<i class="bi bi-x-lg" aria-hidden="true"></i>';
        removeBtn.setAttribute('aria-label', root.dataset.efRemoveLabel ?? 'Remove photo');
        removeBtn.addEventListener('click', () => {
            slot.file = null;
            slot.input.value = '';
            clearCropFields(`ef-group-photo-${slot.index}`);
            renderPreviews(root, slots);
        });
        item.appendChild(removeBtn);

        previews.appendChild(item);
    });
}

function findNextSlot(slots) {
    return slots.find((slot) => !slot.file) ?? null;
}

function commitPhoto(root, slots, slot, file, cropData) {
    assignFileToInput(slot.input, file);
    const prefix = `ef-group-photo-${slot.index}`;
    clearCropFields(prefix);
    if (cropData) {
        setCropFields(prefix, cropData);
    }
    slot.file = file;
    renderPreviews(root, slots);
}

function mountCropModal(modalEl) {
    if (modalEl.parentElement !== document.body) {
        document.body.appendChild(modalEl);
    }
}

async function initCropperOnImage(cropImage, alerts, modalEl) {
    try {
        await ensureCropperLoaded();
    } catch {
        window.alert(alerts.cropperUnavailable || 'Cropping tool unavailable.');
        getBootstrapModal(modalEl)?.hide();

        return false;
    }

    if (typeof window.Cropper === 'undefined') {
        window.alert(alerts.cropperUnavailable || 'Cropping tool unavailable.');
        getBootstrapModal(modalEl)?.hide();

        return false;
    }

    destroyCropper();

    const startCropper = () => {
        cropper = new window.Cropper(cropImage, {
            viewMode: 1,
            dragMode: 'move',
            autoCropArea: 1,
            responsive: true,
            background: false,
        });
    };

    if (cropImage.complete && cropImage.naturalWidth > 0) {
        startCropper();
    } else {
        cropImage.addEventListener('load', startCropper, { once: true });
    }

    return true;
}

export function initGroupMessagePhotos() {
    const root = document.querySelector('.ef-messages__group-compose');
    if (!root || root.dataset.efBound === '1') {
        return;
    }

    const attachBtn = root.querySelector('[data-ef-photo-attach]');
    const modalEl = document.getElementById('ef-group-photo-crop-modal');
    const cropImage = document.getElementById('ef-group-photo-crop-image');
    const applyBtn = modalEl?.querySelector('[data-ef-photo-crop-apply]');
    const skipBtn = modalEl?.querySelector('[data-ef-photo-crop-skip]');
    const picker = document.createElement('input');

    if (!attachBtn || !modalEl || !cropImage || !applyBtn || !skipBtn) {
        return;
    }

    mountCropModal(modalEl);

    root.dataset.efBound = '1';

    const maxPhotos = Number.parseInt(root.dataset.efMaxPhotos ?? '2', 10);
    const maxBytes = Number.parseInt(root.dataset.efMaxBytes ?? String(MAX_BYTES_DEFAULT), 10);
    const alerts = {
        invalidFormat: root.dataset.efAlertInvalidFormat ?? '',
        maxSize: root.dataset.efAlertMaxSize ?? '',
        maxCount: root.dataset.efAlertMaxCount ?? '',
        cropperUnavailable: root.dataset.efAlertCropperUnavailable ?? '',
    };

    const slots = Array.from({ length: maxPhotos }, (_, index) => ({
        index,
        input: root.querySelector(`[data-ef-photo-input="${index}"]`),
        file: null,
    })).filter((slot) => slot.input instanceof HTMLInputElement);

    picker.type = 'file';
    picker.accept = ALLOWED_TYPES.join(',');
    picker.className = 'd-none';
    document.body.appendChild(picker);

    void ensureCropperLoaded().catch(() => {});

    attachBtn.addEventListener('click', () => {
        if (slots.every((slot) => slot.file)) {
            window.alert(alerts.maxCount || 'Maximum photos reached.');
            return;
        }
        picker.value = '';
        picker.click();
    });

    picker.addEventListener('change', () => {
        const file = picker.files?.[0];
        if (!file) {
            return;
        }

        if (!ALLOWED_TYPES.includes(file.type)) {
            window.alert(alerts.invalidFormat || 'Unsupported format.');
            return;
        }

        if (file.size > maxBytes) {
            window.alert(alerts.maxSize || 'File too large.');
            return;
        }

        const slot = findNextSlot(slots);
        if (!slot) {
            window.alert(alerts.maxCount || 'Maximum photos reached.');
            return;
        }

        pendingFile = file;
        pendingSlot = slot;
        resetObjectUrl();
        objectUrl = URL.createObjectURL(file);
        cropImage.src = objectUrl;
        getBootstrapModal(modalEl)?.show();
    });

    modalEl.addEventListener('shown.bs.modal', () => {
        void initCropperOnImage(cropImage, alerts, modalEl);
    });

    modalEl.addEventListener('hidden.bs.modal', () => {
        destroyCropper();
        resetObjectUrl();
        cropImage.removeAttribute('src');
        pendingFile = null;
        pendingSlot = null;
    });

    skipBtn.addEventListener('click', () => {
        if (!pendingFile || !pendingSlot) {
            getBootstrapModal(modalEl)?.hide();
            return;
        }

        commitPhoto(root, slots, pendingSlot, pendingFile, null);
        getBootstrapModal(modalEl)?.hide();
    });

    applyBtn.addEventListener('click', () => {
        if (!cropper || !pendingFile || !pendingSlot) {
            return;
        }

        commitPhoto(root, slots, pendingSlot, pendingFile, cropper.getData(true));
        getBootstrapModal(modalEl)?.hide();
    });
}

document.addEventListener('turbo:load', initGroupMessagePhotos);

if (document.readyState !== 'loading') {
    initGroupMessagePhotos();
} else {
    document.addEventListener('DOMContentLoaded', initGroupMessagePhotos);
}

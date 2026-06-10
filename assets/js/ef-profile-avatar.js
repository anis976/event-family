const MAX_BYTES = 4 * 1024 * 1024;
const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const CROPPER_CSS = 'https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css';
const CROPPER_JS = 'https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js';

let cropper = null;
let selectedFile = null;
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

export function initProfileAvatarManager() {
    const managerEl = document.querySelector('.ef-profile-avatar-manager');
    if (!managerEl) {
        return;
    }

    const chooseBtn = document.getElementById('ef-avatar-choose-btn');
    const fileInput = document.getElementById('ef-avatar-file-input');
    const modalEl = document.getElementById('ef-avatar-crop-modal');
    const cropImage = document.getElementById('ef-avatar-crop-image');
    const saveBtn = document.getElementById('ef-avatar-crop-save');
    const uploadForm = document.getElementById('ef-avatar-upload-form');

    if (!chooseBtn || !fileInput || !modalEl || !cropImage || !saveBtn || !uploadForm) {
        return;
    }

    const alerts = {
        invalidFormat: managerEl.dataset.efAlertInvalidFormat ?? '',
        maxSize: managerEl.dataset.efAlertMaxSize ?? '',
        cropperUnavailable: managerEl.dataset.efAlertCropperUnavailable ?? '',
        uploadFailed: managerEl.dataset.efAlertUploadFailed ?? '',
    };

    if (chooseBtn.dataset.efBound === '1') {
        return;
    }

    chooseBtn.dataset.efBound = '1';

    chooseBtn.addEventListener('click', () => fileInput.click());

    fileInput.addEventListener('change', () => {
        const file = fileInput.files?.[0];
        if (!file) {
            return;
        }

        if (!ALLOWED_TYPES.includes(file.type)) {
            window.alert(alerts.invalidFormat || 'Unsupported format.');
            fileInput.value = '';
            return;
        }

        if (file.size > MAX_BYTES) {
            window.alert(alerts.maxSize || 'File too large.');
            fileInput.value = '';
            return;
        }

        selectedFile = file;
        resetObjectUrl();
        objectUrl = URL.createObjectURL(file);
        cropImage.src = objectUrl;

        const modal = getBootstrapModal(modalEl);
        modal?.show();
    });

    modalEl.addEventListener('shown.bs.modal', async () => {
        try {
            await ensureCropperLoaded();
        } catch {
            window.alert(alerts.cropperUnavailable || 'Cropping tool unavailable.');
            getBootstrapModal(modalEl)?.hide();

            return;
        }

        if (typeof window.Cropper === 'undefined') {
            window.alert(alerts.cropperUnavailable || 'Cropping tool unavailable.');
            getBootstrapModal(modalEl)?.hide();

            return;
        }

        destroyCropper();
        cropper = new window.Cropper(cropImage, {
            aspectRatio: 1,
            viewMode: 1,
            dragMode: 'move',
            autoCropArea: 1,
            responsive: true,
            background: false,
        });
    });

    modalEl.addEventListener('hidden.bs.modal', () => {
        destroyCropper();
        resetObjectUrl();
        cropImage.removeAttribute('src');
        fileInput.value = '';
        selectedFile = null;
    });

    saveBtn.addEventListener('click', () => {
        if (!cropper || !selectedFile) {
            return;
        }

        const data = cropper.getData(true);
        document.getElementById('ef-avatar-crop-x').value = Math.round(data.x);
        document.getElementById('ef-avatar-crop-y').value = Math.round(data.y);
        document.getElementById('ef-avatar-crop-w').value = Math.round(data.width);
        document.getElementById('ef-avatar-crop-h').value = Math.round(data.height);

        const visibilityChoice = document.querySelector('input[name="ef_avatar_visibility_choice"]:checked');
        document.getElementById('ef-avatar-visibility').value = visibilityChoice?.value ?? 'private';

        const formData = new FormData(uploadForm);
        formData.set('photo', selectedFile);

        saveBtn.disabled = true;

        fetch(uploadForm.action, {
            method: 'POST',
            body: formData,
        })
            .then((response) => {
                if (response.redirected) {
                    window.location.href = response.url;
                    return;
                }

                window.location.reload();
            })
            .catch(() => {
                saveBtn.disabled = false;
                window.alert(alerts.uploadFailed || 'Upload failed.');
            });
    });
}

document.addEventListener('turbo:load', initProfileAvatarManager);

if (document.readyState !== 'loading') {
    initProfileAvatarManager();
} else {
    document.addEventListener('DOMContentLoaded', initProfileAvatarManager);
}

const MAX_BYTES = 4 * 1024 * 1024;
const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

let cropper = null;
let selectedFile = null;
let objectUrl = null;

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

function initProfileAvatarManager() {
    const chooseBtn = document.getElementById('ef-avatar-choose-btn');
    const fileInput = document.getElementById('ef-avatar-file-input');
    const modalEl = document.getElementById('ef-avatar-crop-modal');
    const cropImage = document.getElementById('ef-avatar-crop-image');
    const saveBtn = document.getElementById('ef-avatar-crop-save');
    const uploadForm = document.getElementById('ef-avatar-upload-form');

    if (!chooseBtn || !fileInput || !modalEl || !cropImage || !saveBtn || !uploadForm) {
        return;
    }

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
            window.alert('Format non autorisé. Utilise JPG, PNG ou WebP.');
            fileInput.value = '';
            return;
        }

        if (file.size > MAX_BYTES) {
            window.alert('La photo ne doit pas dépasser 4 Mo.');
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

    modalEl.addEventListener('shown.bs.modal', () => {
        if (typeof window.Cropper === 'undefined') {
            window.alert('Outil de recadrage indisponible. Recharge la page.');
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
                window.alert('Impossible d\'envoyer la photo. Réessaie.');
            });
    });
}

document.addEventListener('turbo:load', initProfileAvatarManager);

if (document.readyState !== 'loading') {
    initProfileAvatarManager();
} else {
    document.addEventListener('DOMContentLoaded', initProfileAvatarManager);
}

export { initProfileAvatarManager };

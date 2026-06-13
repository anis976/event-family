/**
 * RapproFam — messagerie (lecture auto, réponses, Turbo-safe).
 */

function initMessageReplyToggles() {
    document.querySelectorAll('[data-ef-toggle-reply]').forEach((button) => {
        if (button.dataset.efBound === '1') {
            return;
        }
        button.dataset.efBound = '1';

        button.addEventListener('click', () => {
            const targetId = button.getAttribute('data-ef-toggle-reply');
            document.getElementById(targetId)?.classList.toggle('d-none');
        });
    });
}

function initMessageDeleteConfirm() {
    document.querySelectorAll('form[data-ef-confirm]').forEach((form) => {
        if (form.dataset.efBound === '1') {
            return;
        }
        form.dataset.efBound = '1';

        form.addEventListener('submit', (event) => {
            const message = form.getAttribute('data-ef-confirm');
            if (message && !window.confirm(message)) {
                event.preventDefault();
            }
        });
    });
}

function initMessageReadObservers() {
    const container = document.querySelector('.ef-messages[data-ef-read-url-base]');
    if (!container) {
        return;
    }

    const urlBase = container.dataset.efReadUrlBase;
    if (!urlBase) {
        return;
    }

    document.querySelectorAll('.ef-message--unread').forEach((element) => {
        if (element.dataset.observerAttached === '1') {
            return;
        }
        element.dataset.observerAttached = '1';

        const messageId = element.dataset.id;
        if (!messageId) {
            return;
        }

        const url = `${urlBase}${messageId}`;

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting || element.dataset.reading === '1') {
                    return;
                }

                element.dataset.reading = '1';

                fetch(url, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                })
                    .then((response) => response.json())
                    .then((data) => {
                        if (data.status === 'success') {
                            element.classList.remove('ef-message--unread');
                            element.querySelector('.ef-messages__unread-dot')?.remove();
                            observer.unobserve(element);

                            import('./ef-notifications.js').then(({ refreshNotificationCounts }) => {
                                refreshNotificationCounts();
                            });
                        }
                    })
                    .catch(() => {
                        element.dataset.reading = '0';
                    });
            });
        }, { threshold: 0.7 });

        observer.observe(element);
    });
}

function initStaffNoticeVariantPicker() {
    document.querySelectorAll('[data-ef-staff-notice-picker]').forEach((picker) => {
        if (picker.dataset.efBound === '1') {
            return;
        }
        picker.dataset.efBound = '1';

        const select = picker.querySelector('[data-ef-staff-notice-input]');
        const labelEl = picker.querySelector('[data-ef-staff-notice-label]');
        if (!select || !labelEl) {
            return;
        }

        const updateSelection = (value) => {
            picker.querySelectorAll('[data-ef-staff-notice-variant]').forEach((button) => {
                const isActive = button.dataset.efStaffNoticeVariant === value;
                button.classList.toggle('ef-theme-active', isActive);
                button.setAttribute('aria-selected', isActive ? 'true' : 'false');

                const check = button.querySelector('.bi-check2');
                if (check) {
                    check.classList.toggle('d-none', !isActive);
                }
            });

            const activeButton = picker.querySelector(`[data-ef-staff-notice-variant="${value}"]`);
            if (activeButton) {
                labelEl.textContent = activeButton.dataset.efStaffNoticeLabel ?? activeButton.textContent.trim();
            }
        };

        picker.addEventListener('click', (event) => {
            const button = event.target.closest('[data-ef-staff-notice-variant]');
            if (!button) {
                return;
            }

            const value = button.dataset.efStaffNoticeVariant;
            if (!value) {
                return;
            }

            select.value = value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            updateSelection(value);
        });

        const initialValue = select.value || picker.querySelector('[data-ef-staff-notice-variant]')?.dataset.efStaffNoticeVariant;
        if (initialValue) {
            select.value = initialValue;
            updateSelection(initialValue);
        }
    });
}

export function initMessagesPage() {
    initMessageReplyToggles();
    initMessageDeleteConfirm();
    initMessageReadObservers();
    initStaffNoticeVariantPicker();
}

document.addEventListener('turbo:load', initMessagesPage);

if (document.readyState !== 'loading') {
    initMessagesPage();
} else {
    document.addEventListener('DOMContentLoaded', initMessagesPage);
}

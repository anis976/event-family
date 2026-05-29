/**
 * EventFamily — messagerie (lecture auto, réponses, Turbo-safe).
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

export function initMessagesPage() {
    initMessageReplyToggles();
    initMessageDeleteConfirm();
    initMessageReadObservers();
}

document.addEventListener('turbo:load', initMessagesPage);

if (document.readyState !== 'loading') {
    initMessagesPage();
} else {
    document.addEventListener('DOMContentLoaded', initMessagesPage);
}

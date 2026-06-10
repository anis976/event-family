/**
 * RapporFam — badges invitations / messages (AJAX + polling + Turbo).
 * Les compteurs ne sont plus calculés côté serveur à chaque page HTML.
 */

const POLL_INTERVAL_MS = 30_000;

let pollTimer = null;

function updateBadge(name, count) {
    document.querySelectorAll(`[data-ef-badge="${name}"]`).forEach((badge) => {
        const value = Math.max(0, Number(count) || 0);
        badge.textContent = String(value);
        badge.classList.toggle('d-none', value <= 0);
    });
}

function updateBellButton(total) {
    const button = document.getElementById('ef-notification-bell-btn');
    if (!button) {
        return;
    }

    const value = Math.max(0, Number(total) || 0);
    const pendingTemplate = document.body.dataset.efNotificationPendingAria || '';
    const defaultAria = document.body.dataset.efNotificationAria || '';

    if (value > 0 && pendingTemplate) {
        button.setAttribute('aria-label', pendingTemplate.replace('%count%', String(value)));
    } else if (defaultAria) {
        button.setAttribute('aria-label', defaultAria);
    }
}

function updateBellMenu(total, bellUrl) {
    const emptyItem = document.getElementById('ef-notifications-empty');
    const priorityDivider = document.getElementById('ef-notifications-priority-divider');
    const priorityRow = document.getElementById('ef-notifications-priority-row');
    const priorityLink = document.getElementById('ef-notifications-priority-link');
    const value = Math.max(0, Number(total) || 0);
    const hasNotifications = value > 0;

    if (emptyItem) {
        emptyItem.classList.toggle('d-none', hasNotifications);
    }

    if (priorityDivider) {
        priorityDivider.classList.toggle('d-none', !hasNotifications);
    }

    if (priorityRow) {
        priorityRow.classList.toggle('d-none', !hasNotifications);
    }

    if (priorityLink && bellUrl) {
        priorityLink.setAttribute('href', bellUrl);
    }
}

function applyCounts(data) {
    if (!data || typeof data !== 'object') {
        return;
    }

    const total = Number(data.total) || 0;

    updateBadge('invitations', data.invitations);
    updateBadge('messages', data.messages);
    updateBadge('notifications', total);
    updateBellButton(total);
    updateBellMenu(total, data.bell_url);
}

export async function refreshNotificationCounts() {
    const url = document.body.dataset.efCountsUrl;
    if (!url) {
        return;
    }

    try {
        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return;
        }

        applyCounts(await response.json());
    } catch {
        // Réseau indisponible — on ignore silencieusement.
    }
}

function startPolling() {
    if (pollTimer || !document.body.dataset.efCountsUrl) {
        return;
    }

    pollTimer = window.setInterval(refreshNotificationCounts, POLL_INTERVAL_MS);
}

function stopPolling() {
    if (!pollTimer) {
        return;
    }

    window.clearInterval(pollTimer);
    pollTimer = null;
}

export function initNotificationBadges() {
    if (!document.body.dataset.efCountsUrl) {
        return;
    }

    refreshNotificationCounts();
    startPolling();
}

document.addEventListener('turbo:load', initNotificationBadges);
document.addEventListener('turbo:before-cache', stopPolling);

if (document.readyState !== 'loading') {
    initNotificationBadges();
} else {
    document.addEventListener('DOMContentLoaded', initNotificationBadges);
}

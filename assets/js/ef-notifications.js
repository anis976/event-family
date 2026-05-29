/**
 * EventFamily — badges invitations / notifications (polling + Turbo).
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

function applyCounts(data) {
    if (!data || typeof data !== 'object') {
        return;
    }

    updateBadge('invitations', data.invitations);
    updateBadge('messages', data.messages);
    updateBadge('notifications', data.total);
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

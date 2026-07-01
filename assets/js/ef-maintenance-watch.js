/**
 * Maintenance planifiée — redirection et détection de fenêtre (polling notifications).
 * Horloge + bandeau : script inline dans _maintenance_watch_script.html.twig.
 */

export function redirectForMaintenance() {
    if (window.__efMaintenanceRedirecting) {
        return;
    }
    window.__efMaintenanceRedirecting = true;
    window.__efStopMaintenanceWatch?.();
    const url = new URL(window.location.href);
    url.searchParams.set('_ef_m', String(Date.now()));
    window.location.replace(url.toString());
}

export function maintenanceWatchScheduled() {
    const body = document.body;
    if (!body || body.dataset.efMaintenanceScheduled !== '1') {
        return false;
    }

    const startTs = Number.parseInt(body.dataset.efMaintenanceStartTs ?? '0', 10);
    const warnSec = Math.max(Number.parseInt(body.dataset.efMaintenanceWarnSeconds ?? '0', 10), 60);
    if (!Number.isFinite(startTs) || startTs <= 0) {
        return false;
    }

    const remaining = startTs - Math.floor(Date.now() / 1000);

    return remaining > 0 && remaining <= warnSec + 120;
}

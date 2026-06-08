/**
 * Déconnexion rapide sur la zone d'administration EasyAdmin.
 */
(function () {
    'use strict';

    const ACTIVITY_EVENTS = ['mousedown', 'keydown', 'scroll', 'touchstart', 'click', 'wheel', 'input'];

    function readMeta(name) {
        const el = document.querySelector('meta[name="' + name + '"]');
        return el ? el.getAttribute('content') : '';
    }

    const idleTimeoutSec = Number.parseInt(readMeta('ef-admin-idle-timeout'), 10);
    const warningDurationSec = Number.parseInt(readMeta('ef-admin-idle-warning'), 10);
    const activityUrl = readMeta('ef-admin-activity-url');
    const csrfToken = readMeta('ef-admin-csrf-token');
    const logoutUrl = readMeta('ef-admin-logout-url');
    const logoutCsrf = readMeta('ef-admin-logout-csrf');
    const homeUrl = readMeta('ef-admin-home-url');

    if (!Number.isFinite(idleTimeoutSec) || idleTimeoutSec <= 0 || !activityUrl) {
        return;
    }

    const warningSec = Number.isFinite(warningDurationSec) && warningDurationSec > 0
        ? Math.min(warningDurationSec, Math.max(idleTimeoutSec - 1, 1))
        : 10;

    const idleTimeoutMs = idleTimeoutSec * 1000;
    const warningDurationMs = warningSec * 1000;
    const warningDelayMs = Math.max(idleTimeoutMs - warningDurationMs, 0);

    let warningTimerId = null;
    let logoutTimerId = null;
    let countdownIntervalId = null;
    let warningVisible = false;
    let initialized = false;
    let lastServerPingAt = 0;
    let mousemoveTickScheduled = false;
    const SERVER_PING_INTERVAL_MS = 15000;

    function clearTimers() {
        if (null !== warningTimerId) {
            clearTimeout(warningTimerId);
            warningTimerId = null;
        }
        if (null !== logoutTimerId) {
            clearTimeout(logoutTimerId);
            logoutTimerId = null;
        }
        if (null !== countdownIntervalId) {
            clearInterval(countdownIntervalId);
            countdownIntervalId = null;
        }
    }

    function hideWarningModal() {
        warningVisible = false;
        const modalEl = document.getElementById('ef-admin-idle-modal');
        if (modalEl && window.bootstrap?.Modal) {
            window.bootstrap.Modal.getInstance(modalEl)?.hide();
        }
    }

    function leaveAdminArea() {
        clearTimers();
        hideWarningModal();

        if (homeUrl) {
            window.location.replace(homeUrl);
            return;
        }

        window.location.replace('/');
    }

    function performLogout() {
        clearTimers();
        hideWarningModal();

        if (!logoutUrl) {
            window.location.href = '/login?idle=admin';
            return;
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = logoutUrl;

        const tokenInput = document.createElement('input');
        tokenInput.type = 'hidden';
        tokenInput.name = '_csrf_token';
        tokenInput.value = logoutCsrf;
        form.appendChild(tokenInput);

        document.body.appendChild(form);
        form.submit();
    }

    function showWarningModal() {
        const modalEl = document.getElementById('ef-admin-idle-modal');
        const countdownEl = document.getElementById('ef-admin-idle-countdown');

        if (!modalEl || !countdownEl) {
            performLogout();
            return;
        }

        warningVisible = true;
        let remaining = Math.ceil(warningDurationMs / 1000);
        countdownEl.textContent = String(remaining);

        if (window.bootstrap?.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: 'static', keyboard: false }).show();
        }

        countdownIntervalId = window.setInterval(function () {
            remaining -= 1;
            countdownEl.textContent = String(Math.max(remaining, 0));
        }, 1000);

        logoutTimerId = window.setTimeout(performLogout, warningDurationMs);
    }

    function scheduleIdleTimers() {
        clearTimers();
        warningVisible = false;
        warningTimerId = window.setTimeout(showWarningModal, warningDelayMs);
    }

    function updateMetaToken(name, value) {
        if (!value) {
            return;
        }

        const meta = document.querySelector('meta[name="' + name + '"]');
        if (meta) {
            meta.setAttribute('content', value);
        }
    }

    async function pingServerActivity() {
        const tokenMeta = document.querySelector('meta[name="ef-admin-csrf-token"]');
        const currentToken = tokenMeta ? tokenMeta.getAttribute('content') : csrfToken;

        if (!activityUrl || !currentToken) {
            return;
        }

        const body = new URLSearchParams();
        body.set('_token', currentToken);

        try {
            const response = await fetch(activityUrl, {
                method: 'POST',
                body: body,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (response.status === 403) {
                leaveAdminArea();
                return;
            }

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            updateMetaToken('ef-admin-csrf-token', data.csrf_token);
            updateMetaToken('ef-admin-logout-csrf', data.logout_csrf_token);
        } catch (e) {
            // Ignoré — le serveur appliquera le timeout au prochain chargement.
        }
    }

    function maybePingServer() {
        const now = Date.now();
        if (now - lastServerPingAt >= SERVER_PING_INTERVAL_MS) {
            lastServerPingAt = now;
            pingServerActivity();
        }
    }

    function onUserActivity() {
        if (warningVisible) {
            return;
        }

        scheduleIdleTimers();
        maybePingServer();
    }

    function onMouseMove() {
        if (mousemoveTickScheduled) {
            return;
        }

        mousemoveTickScheduled = true;
        window.requestAnimationFrame(function () {
            mousemoveTickScheduled = false;
            onUserActivity();
        });
    }

    async function stayConnected() {
        hideWarningModal();
        clearTimers();
        warningVisible = false;
        lastServerPingAt = Date.now();
        await pingServerActivity();
        scheduleIdleTimers();
    }

    function bindModalActions() {
        const stayBtn = document.getElementById('ef-admin-idle-stay');
        const logoutBtn = document.getElementById('ef-admin-idle-logout');

        if (stayBtn && stayBtn.dataset.efBound !== '1') {
            stayBtn.dataset.efBound = '1';
            stayBtn.addEventListener('click', function (event) {
                event.preventDefault();
                stayConnected();
            });
        }

        if (logoutBtn && logoutBtn.dataset.efBound !== '1') {
            logoutBtn.dataset.efBound = '1';
            logoutBtn.addEventListener('click', function (event) {
                event.preventDefault();
                performLogout();
            });
        }
    }

    function bindActivityListeners() {
        if (initialized) {
            return;
        }

        initialized = true;

        ACTIVITY_EVENTS.forEach(function (eventName) {
            document.addEventListener(eventName, onUserActivity, { passive: true });
        });

        document.addEventListener('mousemove', onMouseMove, { passive: true });

        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') {
                onUserActivity();
            }
        });

        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                onUserActivity();
            }
        });
    }

    function init() {
        bindModalActions();
        bindActivityListeners();

        if (!warningVisible) {
            scheduleIdleTimers();
        }

        lastServerPingAt = Date.now();
        pingServerActivity();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

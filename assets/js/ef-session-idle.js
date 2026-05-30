/**
 * Déconnexion automatique après inactivité (Turbo-safe).
 */

import { generateCsrfToken } from '../controllers/csrf_protection_controller.js';

const ACTIVITY_EVENTS = ['mousedown', 'keydown', 'scroll', 'touchstart', 'click'];

let idleTimeoutMs = 0;
let warningDurationMs = 0;
let warningDelayMs = 0;

let warningTimerId = null;
let logoutTimerId = null;
let countdownIntervalId = null;
let warningVisible = false;
let sessionIdleInitialized = false;

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

function getModalElements() {
    return {
        modalEl: document.getElementById('ef-session-idle-modal'),
        countdownEl: document.getElementById('ef-session-idle-countdown'),
        stayBtn: document.getElementById('ef-session-idle-stay'),
    };
}

function hideWarningModal() {
    const { modalEl } = getModalElements();
    warningVisible = false;

    if (modalEl && window.bootstrap?.Modal) {
        const instance = window.bootstrap.Modal.getInstance(modalEl);
        instance?.hide();
    }
}

function showWarningModal() {
    const { modalEl, countdownEl } = getModalElements();
    if (!modalEl || !countdownEl) {
        performLogout();

        return;
    }

    warningVisible = true;

    let remainingSeconds = Math.ceil(warningDurationMs / 1000);
    countdownEl.textContent = String(remainingSeconds);

    if (window.bootstrap?.Modal) {
        window.bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: 'static', keyboard: false }).show();
    }

    countdownIntervalId = window.setInterval(() => {
        remainingSeconds -= 1;
        countdownEl.textContent = String(Math.max(remainingSeconds, 0));

        if (remainingSeconds <= 0 && null !== countdownIntervalId) {
            clearInterval(countdownIntervalId);
            countdownIntervalId = null;
        }
    }, 1000);

    logoutTimerId = window.setTimeout(() => {
        performLogout();
    }, warningDurationMs);
}

function scheduleIdleTimers() {
    clearTimers();
    warningVisible = false;

    warningTimerId = window.setTimeout(showWarningModal, warningDelayMs);
}

async function pingServerActivity() {
    const form = document.getElementById('ef-session-activity-form');
    if (!form) {
        return;
    }

    try {
        await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
    } catch {
        // La prochaine navigation sera gérée côté serveur.
    }
}

function onUserActivity() {
    if (warningVisible) {
        return;
    }

    scheduleIdleTimers();
}

async function stayConnected() {
    hideWarningModal();
    clearTimers();
    warningVisible = false;
    await pingServerActivity();
    scheduleIdleTimers();
}

function submitLogoutForm() {
    const form = document.getElementById('ef-session-logout-form');
    if (!form) {
        window.location.href = '/login?idle=1';

        return;
    }

    generateCsrfToken(form);

    if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
    } else {
        form.submit();
    }
}

function performLogout() {
    clearTimers();
    hideWarningModal();
    submitLogoutForm();
}

function bindModalActions() {
    const { stayBtn } = getModalElements();
    const logoutBtn = document.getElementById('ef-session-idle-logout');

    if (stayBtn && stayBtn.dataset.efBound !== '1') {
        stayBtn.dataset.efBound = '1';
        stayBtn.addEventListener('click', (event) => {
            event.preventDefault();
            stayConnected();
        });
    }

    if (logoutBtn && logoutBtn.dataset.efBound !== '1') {
        logoutBtn.dataset.efBound = '1';
        logoutBtn.addEventListener('click', (event) => {
            event.preventDefault();
            performLogout();
        });
    }
}

function readConfigFromBody() {
    const body = document.body;
    if (!body?.dataset.efSessionIdle) {
        return null;
    }

    const idleTimeout = Number.parseInt(body.dataset.efSessionIdle, 10);
    const idleWarning = Number.parseInt(body.dataset.efSessionWarning ?? '0', 10);

    if (!Number.isFinite(idleTimeout) || idleTimeout <= 0) {
        return null;
    }

    const warning = Number.isFinite(idleWarning) && idleWarning > 0
        ? Math.min(idleWarning, Math.max(idleTimeout - 1, 1))
        : Math.min(60, Math.max(idleTimeout - 1, 1));

    return {
        idleTimeoutMs: idleTimeout * 1000,
        warningDurationMs: warning * 1000,
    };
}

export function initSessionIdle() {
    const parsed = readConfigFromBody();
    if (!parsed) {
        return;
    }

    idleTimeoutMs = parsed.idleTimeoutMs;
    warningDurationMs = parsed.warningDurationMs;
    warningDelayMs = Math.max(idleTimeoutMs - warningDurationMs, 0);

    bindModalActions();

    if (!sessionIdleInitialized) {
        sessionIdleInitialized = true;

        ACTIVITY_EVENTS.forEach((eventName) => {
            document.addEventListener(eventName, onUserActivity, { passive: true });
        });

        document.addEventListener('turbo:load', () => {
            if (!warningVisible) {
                scheduleIdleTimers();
            }
        });

        document.addEventListener('turbo:before-cache', () => {
            if (warningVisible) {
                hideWarningModal();
            }
            clearTimers();
        });
    }

    if (!warningVisible) {
        scheduleIdleTimers();
    }
}

export function disposeSessionIdleWarning() {
    if (warningVisible) {
        hideWarningModal();
    }
    clearTimers();
    warningVisible = false;
}

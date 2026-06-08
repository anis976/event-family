/**
 * Gestion du consentement cookies (CNIL / RGPD) — compatible Turbo.
 */

const INIT_FLAG = '__efConsentBound';
const PREFERENCES_MODAL_A11Y_FLAG = '__efConsentModalA11yBound';
const PREFERENCES_MODAL_ID = 'ef-consent-preferences-modal';

/** @type {HTMLElement|null} */
let root = null;

/** @type {HTMLElement|null} */
let preferencesModalTrigger = null;

/** @type {number} */
let consentVersion = 1;

/** @type {number} */
let consentTtl = 0;

/** @type {string} */
let cookieName = 'ef_consent';

/**
 * @returns {object|null}
 */
function readConsentCookie() {
    const pattern = new RegExp(`(?:^|; )${encodeURIComponent(cookieName)}=([^;]*)`);
    const match = document.cookie.match(pattern);

    if (!match) {
        return null;
    }

    try {
        return JSON.parse(decodeURIComponent(match[1]));
    } catch {
        try {
            return JSON.parse(match[1]);
        } catch {
            return null;
        }
    }
}

/**
 * @param {object} payload
 */
function writeConsentCookie(payload) {
    const secure = window.location.protocol === 'https:' ? '; Secure' : '';
    const encoded = encodeURIComponent(JSON.stringify(payload));

    document.cookie = `${encodeURIComponent(cookieName)}=${encoded}; path=/; max-age=${consentTtl}; SameSite=Lax${secure}`;
}

/**
 * @param {unknown} data
 */
function isValidConsent(data) {
    if (!data || typeof data !== 'object') {
        return false;
    }

    const record = /** @type {{ v?: unknown; necessary?: unknown; marketing?: unknown; analytics?: unknown; ts?: unknown }} */ (data);

    return (
        record.v === consentVersion
        && record.necessary === true
        && typeof record.marketing === 'boolean'
        && typeof record.analytics === 'boolean'
        && Number.isFinite(record.ts)
    );
}

/**
 * @param {boolean} marketing
 * @param {boolean} analytics
 * @returns {object}
 */
function buildPayload(marketing, analytics) {
    return {
        v: consentVersion,
        necessary: true,
        marketing,
        analytics,
        ts: Math.floor(Date.now() / 1000),
    };
}

/**
 * @param {object} consent
 */
function applyConsent(consent) {
    const marketing = Boolean(consent.marketing);
    const analytics = Boolean(consent.analytics);

    document.documentElement.dataset.efConsentMarketing = marketing ? '1' : '0';
    document.documentElement.dataset.efConsentAnalytics = analytics ? '1' : '0';
    document.documentElement.dataset.efConsentChoice = '1';
    window.__efConsent = consent;

    toggleManageTriggers(true);
    syncPreferenceInputs(marketing, analytics);

    document.dispatchEvent(
        new CustomEvent('ef:consent-updated', { detail: { ...consent } }),
    );
}

function toggleManageTriggers(visible) {
    document.querySelectorAll('[data-ef-consent-manage]').forEach((el) => {
        el.classList.toggle('d-none', !visible);
    });
}

/**
 * @param {boolean} marketing
 * @param {boolean} analytics
 */
function syncPreferenceInputs(marketing, analytics) {
    const marketingInput = document.getElementById('ef-consent-marketing');

    if (marketingInput instanceof HTMLInputElement) {
        marketingInput.checked = marketing;
    }

    const analyticsInput = document.getElementById('ef-consent-analytics');

    if (analyticsInput instanceof HTMLInputElement) {
        analyticsInput.checked = analytics;
    }
}

function getBanner() {
    return document.getElementById('ef-consent-banner');
}

function showBanner() {
    const banner = getBanner();

    if (banner) {
        banner.hidden = false;
        banner.classList.remove('ef-consent-banner--hidden');
    }
}

function hideBanner() {
    const banner = getBanner();

    if (banner) {
        banner.hidden = true;
        banner.classList.add('ef-consent-banner--hidden');
    }
}

function getPreferencesModalElement() {
    return document.getElementById(PREFERENCES_MODAL_ID);
}

function getPreferencesModal() {
    const modalEl = getPreferencesModalElement();

    if (!modalEl || !window.bootstrap?.Modal) {
        return null;
    }

    return window.bootstrap.Modal.getOrCreateInstance(modalEl);
}

/**
 * Retire le focus de la modale avant aria-hidden (évite l'avertissement navigateur).
 *
 * @param {HTMLElement} modalEl
 */
function releaseModalFocus(modalEl) {
    const active = document.activeElement;

    if (active instanceof HTMLElement && modalEl.contains(active)) {
        active.blur();
    }
}

function bindPreferencesModalA11y() {
    const modalEl = getPreferencesModalElement();

    if (!modalEl || modalEl.dataset[PREFERENCES_MODAL_A11Y_FLAG] === '1') {
        return;
    }

    modalEl.dataset[PREFERENCES_MODAL_A11Y_FLAG] = '1';

    modalEl.addEventListener('hide.bs.modal', () => {
        releaseModalFocus(modalEl);
    });

    modalEl.addEventListener('hidden.bs.modal', () => {
        const trigger = preferencesModalTrigger;
        preferencesModalTrigger = null;

        if (trigger instanceof HTMLElement && document.contains(trigger)) {
            trigger.focus();
            return;
        }

        const fab = document.querySelector('[data-ef-consent-manage]:not(.d-none)');

        if (fab instanceof HTMLElement) {
            fab.focus();
        }
    });
}

export function openCookiePreferences() {
    const consent = readConsentCookie();

    if (isValidConsent(consent)) {
        syncPreferenceInputs(Boolean(consent.marketing), Boolean(consent.analytics));
    } else {
        syncPreferenceInputs(false, false);
    }

    const active = document.activeElement;
    preferencesModalTrigger = active instanceof HTMLElement && !active.closest(`#${PREFERENCES_MODAL_ID}`)
        ? active
        : null;

    getPreferencesModal()?.show();
}

/**
 * @param {boolean} marketing
 * @param {boolean} analytics
 */
function persistConsent(marketing, analytics) {
    const payload = buildPayload(marketing, analytics);

    writeConsentCookie(payload);
    applyConsent(payload);
    hideBanner();
    getPreferencesModal()?.hide();
}

function handleRootClick(event) {
    const target = event.target;

    if (!(target instanceof Element)) {
        return;
    }

    const actionEl = target.closest('[data-ef-consent-action]');

    if (!actionEl || !root?.contains(actionEl)) {
        return;
    }

    const action = actionEl.getAttribute('data-ef-consent-action');

    switch (action) {
        case 'accept-all':
            persistConsent(true, true);
            break;
        case 'reject-all':
            persistConsent(false, false);
            break;
        case 'open-preferences':
            openCookiePreferences();
            break;
        case 'save-preferences': {
            const marketingInput = document.getElementById('ef-consent-marketing');
            const analyticsInput = document.getElementById('ef-consent-analytics');
            const marketing = marketingInput instanceof HTMLInputElement && marketingInput.checked;
            const analytics = analyticsInput instanceof HTMLInputElement && analyticsInput.checked;

            persistConsent(marketing, analytics);
            break;
        }
        default:
            break;
    }
}

function handleManageClick(event) {
    const target = event.target;

    if (!(target instanceof Element)) {
        return;
    }

    if (!target.closest('[data-ef-consent-manage]')) {
        return;
    }

    event.preventDefault();
    openCookiePreferences();
}

function bindConsentUi() {
    bindPreferencesModalA11y();

    root = document.getElementById('ef-consent-root');

    if (!root || root.dataset[INIT_FLAG] === '1') {
        return;
    }

    root.dataset[INIT_FLAG] = '1';

    cookieName = root.dataset.efConsentCookie || 'ef_consent';
    consentVersion = Number.parseInt(root.dataset.efConsentVersion || '1', 10);
    consentTtl = Number.parseInt(root.dataset.efConsentTtl || '0', 10);

    root.addEventListener('click', handleRootClick);

    const existing = readConsentCookie();

    if (isValidConsent(existing)) {
        applyConsent(existing);
        hideBanner();
    } else {
        document.documentElement.dataset.efConsentChoice = '0';
        document.documentElement.dataset.efConsentMarketing = '0';
        document.documentElement.dataset.efConsentAnalytics = '0';
        toggleManageTriggers(false);
        showBanner();
    }
}

export function initCookieConsent() {
    bindConsentUi();
}

if (!window.__efConsentManageBound) {
    window.__efConsentManageBound = true;
    document.addEventListener('click', handleManageClick);
}

document.addEventListener('turbo:load', bindConsentUi);

if (document.readyState !== 'loading') {
    bindConsentUi();
} else {
    document.addEventListener('DOMContentLoaded', bindConsentUi);
}

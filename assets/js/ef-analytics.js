/**
 * Google Analytics (gtag.js) — chargé uniquement après consentement « analytics ».
 */

const LOADED_FLAG = '__efGaLoaded';

/**
 * @returns {string}
 */
function getMeasurementId() {
    const root = document.getElementById('ef-consent-root');

    return (root?.dataset.efGaId || '').trim();
}

/**
 * @returns {boolean}
 */
function hasAnalyticsConsent() {
    return document.documentElement.dataset.efConsentAnalytics === '1';
}

/**
 * @param {string} measurementId
 */
function loadGoogleAnalytics(measurementId) {
    if (!measurementId || window[LOADED_FLAG]) {
        return;
    }

    window.dataLayer = window.dataLayer || [];
    function gtag() {
        window.dataLayer.push(arguments);
    }
    window.gtag = gtag;
    gtag('js', new Date());
    gtag('config', measurementId);

    const script = document.createElement('script');
    script.async = true;
    script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(measurementId)}`;
    document.head.appendChild(script);

    window[LOADED_FLAG] = true;
}

export function syncGoogleAnalytics() {
    if (!hasAnalyticsConsent()) {
        return;
    }

    loadGoogleAnalytics(getMeasurementId());
}

document.addEventListener('ef:consent-updated', (event) => {
    if (event instanceof CustomEvent && event.detail?.analytics) {
        loadGoogleAnalytics(getMeasurementId());
    }
});

document.addEventListener('turbo:load', syncGoogleAnalytics);

if (document.readyState !== 'loading') {
    syncGoogleAnalytics();
} else {
    document.addEventListener('DOMContentLoaded', syncGoogleAnalytics);
}

/**
 * Google AdSense — script + blocs publicitaires, chargés uniquement après consentement « marketing ».
 */

const LOADED_ATTR = 'efAdsenseLoaded';
const SCRIPT_LOADED_FLAG = '__efAdsenseScriptLoaded';
const SCRIPT_LOADING_FLAG = '__efAdsenseScriptLoading';

/**
 * @returns {string}
 */
function getClientId() {
    const root = document.getElementById('ef-consent-root');

    return (root?.dataset.efAdsenseClientId || '').trim();
}

/**
 * @returns {boolean}
 */
function hasMarketingConsent() {
    return document.documentElement.dataset.efConsentMarketing === '1';
}

/**
 * @param {string} clientId
 * @returns {Promise<void>}
 */
function loadAdsenseScript(clientId) {
    if (!clientId) {
        return Promise.resolve();
    }

    if (window[SCRIPT_LOADED_FLAG]) {
        return Promise.resolve();
    }

    const pending = window[SCRIPT_LOADING_FLAG];
    if (pending instanceof Promise) {
        return pending;
    }

    window[SCRIPT_LOADING_FLAG] = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.async = true;
        script.crossOrigin = 'anonymous';
        script.src = `https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=${encodeURIComponent(clientId)}`;
        script.onload = () => {
            window[SCRIPT_LOADED_FLAG] = true;
            resolve();
        };
        script.onerror = () => {
            window[SCRIPT_LOADING_FLAG] = null;
            reject(new Error('adsense_script_blocked'));
        };
        document.head.appendChild(script);
    });

    return window[SCRIPT_LOADING_FLAG];
}

/**
 * @param {HTMLElement} unit
 */
function pushAdUnit(unit) {
    if (unit.dataset[LOADED_ATTR] === '1') {
        return;
    }

    const ins = unit.querySelector('ins.adsbygoogle');

    if (!(ins instanceof HTMLElement)) {
        return;
    }

    try {
        (window.adsbygoogle = window.adsbygoogle || []).push({});
        unit.dataset[LOADED_ATTR] = '1';
    } catch {
        // Compte non encore approuvé ou script indisponible.
    }
}

function syncAdsenseUnits() {
    const clientId = getClientId();
    const units = document.querySelectorAll('[data-ef-adsense-unit]');

    units.forEach((node) => {
        if (!(node instanceof HTMLElement)) {
            return;
        }

        if (!hasMarketingConsent()) {
            node.classList.add('ef-adsense--hidden');
            return;
        }

        node.classList.remove('ef-adsense--hidden');
    });

    if (!hasMarketingConsent() || !clientId || units.length === 0) {
        return;
    }

    loadAdsenseScript(clientId)
        .then(() => {
            units.forEach((node) => {
                if (node instanceof HTMLElement) {
                    pushAdUnit(node);
                }
            });
        })
        .catch(() => {
            // Bloqueur de pub ou réseau : pas d’erreur console côté visiteur sans consentement.
        });
}

document.addEventListener('ef:consent-updated', (event) => {
    if (event instanceof CustomEvent && event.detail?.marketing) {
        syncAdsenseUnits();
    }
});

document.addEventListener('turbo:load', syncAdsenseUnits);

if (document.readyState !== 'loading') {
    syncAdsenseUnits();
} else {
    document.addEventListener('DOMContentLoaded', syncAdsenseUnits);
}

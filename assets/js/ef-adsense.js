/**
 * Google AdSense — blocs publicitaires, chargés uniquement après consentement « marketing ».
 */

const LOADED_ATTR = 'efAdsenseLoaded';

/**
 * @returns {boolean}
 */
function hasMarketingConsent() {
    return document.documentElement.dataset.efConsentMarketing === '1';
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
        // Script head absent ou compte non encore approuvé.
    }
}

function syncAdsenseUnits() {
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
        pushAdUnit(node);
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

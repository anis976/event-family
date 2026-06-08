/**
 * Synchronise le thème site (ef-theme) ↔ admin EasyAdmin (ea/colorScheme).
 */
(function () {
    const EF_KEY = 'ef-theme';
    const EA_KEY = 'ea/colorScheme';
    const VALID = new Set(['light', 'dark', 'auto']);

    function isValid(value) {
        return typeof value === 'string' && VALID.has(value);
    }

    function syncSiteToAdmin() {
        const sitePreference = localStorage.getItem(EF_KEY);
        if (isValid(sitePreference)) {
            localStorage.setItem(EA_KEY, sitePreference);
        }
    }

    syncSiteToAdmin();

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('a[data-ea-color-scheme]').forEach((link) => {
            link.addEventListener('click', () => {
                const scheme = link.getAttribute('data-ea-color-scheme');
                if (isValid(scheme)) {
                    localStorage.setItem(EF_KEY, scheme);
                }
            });
        });
    });
})();

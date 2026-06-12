/**
 * Applique le thème avant le premier paint (évite le flash clair/sombre).
 * Écoute aussi Turbo : la réponse serveur force data-bs-theme="light" sur <html>.
 */
(function () {
    const STORAGE_THEME_KEY = 'ef-theme';
    const ADMIN_STORAGE_THEME_KEY = 'ea/colorScheme';
    function getPreference() {
        const stored = localStorage.getItem(STORAGE_THEME_KEY);

        return stored === 'light' || stored === 'dark' || stored === 'auto' ? stored : 'auto';
    }

    function resolveTheme(preference) {
        if (preference === 'light' || preference === 'dark') {
            return preference;
        }

        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    function applyEarlyTheme(root) {
        const doc = root && root.documentElement ? root : document;
        const html = doc.documentElement;
        if (!html) {
            return;
        }

        const preference = getPreference();
        const resolved = resolveTheme(preference);

        html.setAttribute('data-bs-theme', resolved);
        html.dataset.efThemePreference = preference;
        html.style.colorScheme = resolved;
        localStorage.setItem(ADMIN_STORAGE_THEME_KEY, preference);
    }

    function onTurboBeforeRender(event) {
        const newDoc = event.detail.newBody?.ownerDocument;
        if (newDoc) {
            applyEarlyTheme(newDoc);
        }

        applyEarlyTheme(document);
    }

    const MOBILE_SIDEBAR_MQ = window.matchMedia('(max-width: 991.98px)');

    /**
     * Hauteur réelle visible (barre d’adresse Android).
     * L’inspecteur Chrome ≠ téléphone : on lit visualViewport (hauteur + offsetTop).
     */
    function efUpdateViewportHeight() {
        const vv = window.visualViewport;
        const height = Math.round(vv?.height ?? window.innerHeight);
        const top = Math.round(vv?.offsetTop ?? 0);

        document.documentElement.style.setProperty('--ef-vh', height + 'px');
        document.documentElement.style.setProperty('--ef-vv-top', top + 'px');
        document.documentElement.style.setProperty('--ef-vv-height', height + 'px');
    }

    /** Ancre le pied de sidebar (roue dentée) via mesure DOM en px — fiable sur Honor / Android. */
    function efSyncMobileSidebar() {
        efUpdateViewportHeight();

        if (!MOBILE_SIDEBAR_MQ.matches) {
            document.documentElement.style.removeProperty('--ef-sidebar-settings-h');
            return;
        }

        const sidebar = document.getElementById('ef-sidebar');
        const settings = sidebar?.querySelector('.ef-sidebar__settings');
        if (!settings) {
            return;
        }

        const settingsHeight = Math.ceil(settings.getBoundingClientRect().height);
        if (settingsHeight > 0) {
            document.documentElement.style.setProperty('--ef-sidebar-settings-h', settingsHeight + 'px');
        }
    }

    window.efUpdateViewportHeight = efUpdateViewportHeight;
    window.efSyncMobileSidebar = efSyncMobileSidebar;

    efSyncMobileSidebar();
    window.visualViewport?.addEventListener('resize', efSyncMobileSidebar);
    window.visualViewport?.addEventListener('scroll', efSyncMobileSidebar);
    window.addEventListener('resize', efSyncMobileSidebar);
    window.addEventListener('orientationchange', () => {
        window.setTimeout(efSyncMobileSidebar, 100);
    });

    applyEarlyTheme(document);

    document.addEventListener('turbo:before-render', onTurboBeforeRender);
    document.addEventListener('turbo:render', () => applyEarlyTheme(document));
    document.addEventListener('turbo:before-cache', () => applyEarlyTheme(document));
})();

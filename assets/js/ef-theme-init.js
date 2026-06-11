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

    /** Hauteur réelle visible (barre d’adresse Android) — évite sidebar/footer coupés ~360×804. */
    function efUpdateViewportHeight() {
        const h = Math.round(window.visualViewport?.height ?? window.innerHeight);
        document.documentElement.style.setProperty('--ef-vh', h + 'px');
    }

    window.efUpdateViewportHeight = efUpdateViewportHeight;
    efUpdateViewportHeight();
    window.visualViewport?.addEventListener('resize', efUpdateViewportHeight);
    window.visualViewport?.addEventListener('scroll', efUpdateViewportHeight);
    window.addEventListener('resize', efUpdateViewportHeight);

    applyEarlyTheme(document);

    document.addEventListener('turbo:before-render', onTurboBeforeRender);
    document.addEventListener('turbo:render', () => applyEarlyTheme(document));
    document.addEventListener('turbo:before-cache', () => applyEarlyTheme(document));
})();

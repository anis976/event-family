/**
 * Applique le thème avant le premier paint (évite le flash clair/sombre).
 */
(function () {
    const STORAGE_THEME_KEY = 'ef-theme';
    const stored = localStorage.getItem(STORAGE_THEME_KEY);
    const preference = stored === 'light' || stored === 'dark' || stored === 'auto' ? stored : 'auto';
    let resolved = preference;

    if (preference === 'auto') {
        resolved = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    document.documentElement.setAttribute('data-bs-theme', resolved);
    document.documentElement.dataset.efThemePreference = preference;
})();

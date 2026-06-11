/**
 * RapporFam — layout UI (Turbo-safe, idempotent).
 */

const STORAGE_THEME_KEY = 'ef-theme';
const ADMIN_STORAGE_THEME_KEY = 'ea/colorScheme';
const SCROLL_TOP_THRESHOLD = 300;

const THEME_ICONS = {
    light: 'bi-sun-fill',
    dark: 'bi-moon-stars-fill',
    auto: 'bi-circle-half',
};

let layoutInitialized = false;
let scrollHandler = null;
let resizeHandler = null;

function getBootstrap() {
    return window.bootstrap;
}

export function resolveThemeMode(stored) {
    if (stored === 'light' || stored === 'dark') {
        return stored;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

export function getStoredThemePreference() {
    const stored = localStorage.getItem(STORAGE_THEME_KEY);

    return stored === 'light' || stored === 'dark' || stored === 'auto' ? stored : 'auto';
}

export function applyTheme(preference) {
    const resolved = preference === 'auto' ? resolveThemeMode('auto') : preference;

    document.documentElement.setAttribute('data-bs-theme', resolved);
    document.documentElement.dataset.efThemePreference = preference;
    localStorage.setItem(ADMIN_STORAGE_THEME_KEY, preference);

    const icon = document.getElementById('theme-icon-main');
    if (icon) {
        icon.className = `bi ${THEME_ICONS[preference] ?? THEME_ICONS.auto}`;
    }

    document.querySelectorAll('[data-ef-theme]').forEach((button) => {
        const isActive = button.dataset.efTheme === preference;
        button.classList.toggle('ef-theme-active', isActive);

        const check = button.querySelector('.bi-check2');
        if (check) {
            check.classList.toggle('d-none', !isActive);
        }
    });
}

/** Bootstrap : instances invalides après navigation Turbo → dispose + recréation */
function disposeBootstrapDropdowns() {
    const bootstrap = getBootstrap();
    if (!bootstrap?.Dropdown) {
        return;
    }

    document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach((element) => {
        bootstrap.Dropdown.getInstance(element)?.dispose();
    });
}

const CONSENT_PREFERENCES_MODAL_ID = 'ef-consent-preferences-modal';

function isConsentPreferencesModal(element) {
    return element instanceof HTMLElement && element.id === CONSENT_PREFERENCES_MODAL_ID;
}

function disposeBootstrapModals() {
    const bootstrap = getBootstrap();
    if (!bootstrap?.Modal) {
        return;
    }

    document.querySelectorAll('.modal.show').forEach((element) => {
        if (isConsentPreferencesModal(element) && element.contains(document.activeElement)) {
            document.activeElement?.blur();
        }

        bootstrap.Modal.getInstance(element)?.hide();
    });

    document.querySelectorAll('.modal').forEach((element) => {
        if (isConsentPreferencesModal(element)) {
            return;
        }

        bootstrap.Modal.getInstance(element)?.dispose();
    });

    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');
    document.querySelectorAll('.modal-backdrop').forEach((backdrop) => backdrop.remove());
}

function initBootstrapDropdowns() {
    const bootstrap = getBootstrap();
    if (!bootstrap?.Dropdown) {
        return;
    }

    document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach((element) => {
        bootstrap.Dropdown.getOrCreateInstance(element);
    });
}

function closeSidebar() {
    document.body.classList.remove('ef-sidebar-open');
}

function toggleSidebar() {
    document.body.classList.toggle('ef-sidebar-open');
}

function updateSearchToggleState(isOpen) {
    const toggle = document.getElementById('btnSearchToggle');
    if (toggle) {
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }
}

function closeSearchPanel() {
    document.getElementById('searchBarCollapse')?.classList.remove('ef-search-panel--open');
    document.body.classList.remove('ef-search-open');
    updateSearchToggleState(false);
}

function toggleSearchPanel() {
    const panel = document.getElementById('searchBarCollapse');
    if (!panel) {
        return;
    }

    const isOpen = !panel.classList.contains('ef-search-panel--open');
    panel.classList.toggle('ef-search-panel--open', isOpen);
    document.body.classList.toggle('ef-search-open', isOpen);
    updateSearchToggleState(isOpen);

    if (isOpen) {
        document.getElementById('searchInput')?.focus();
    }
}

function updateBackToTopVisibility() {
    const button = document.getElementById('backToTop');
    if (!button) {
        return;
    }

    button.classList.toggle('ef-btn-back-to-top--visible', window.scrollY > SCROLL_TOP_THRESHOLD);
}

function onThemeButtonClick(event) {
    const button = event.target.closest('[data-ef-theme]');
    if (!button) {
        return;
    }

    event.preventDefault();

    const preference = button.dataset.efTheme;
    localStorage.setItem(STORAGE_THEME_KEY, preference);
    applyTheme(preference);

    const bootstrap = getBootstrap();
    const dropdownRoot = button.closest('.dropdown');
    const toggle = dropdownRoot?.querySelector('[data-bs-toggle="dropdown"]');
    if (toggle && bootstrap?.Dropdown) {
        bootstrap.Dropdown.getInstance(toggle)?.hide();
    }
}

function onLayoutClick(event) {
    if (document.body.classList.contains('ef-search-open')) {
        if (!event.target.closest('#searchBarCollapse') && !event.target.closest('#btnSearchToggle')) {
            closeSearchPanel();
            return;
        }
    }

    if (event.target.closest('#mobile-toggle')) {
        toggleSidebar();
        return;
    }

    if (event.target.closest('#close-sidebar')) {
        closeSidebar();
        return;
    }

    if (event.target.closest('#sidebarOverlay')) {
        closeSidebar();
        return;
    }

    if (event.target.closest('#btnSearchToggle')) {
        toggleSearchPanel();
        return;
    }

    if (event.target.closest('#backToTop')) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        return;
    }

    if (event.target.closest('[data-ef-theme]')) {
        onThemeButtonClick(event);
    }
}

function onTurboBeforeCache() {
    closeSidebar();
    closeSearchPanel();
    disposeBootstrapDropdowns();
    disposeBootstrapModals();
    document.getElementById('backToTop')?.classList.remove('ef-btn-back-to-top--visible');
}

function onResize() {
    if (window.matchMedia('(min-width: 992px)').matches) {
        closeSidebar();
    }
}

function onSystemThemeChange() {
    if (getStoredThemePreference() === 'auto') {
        applyTheme('auto');
    }
}

export function initRapporFamLayout() {
    applyTheme(getStoredThemePreference());
    initBootstrapDropdowns();
    updateBackToTopVisibility();

    if (layoutInitialized) {
        return;
    }

    layoutInitialized = true;

    document.addEventListener('click', onLayoutClick);
    document.addEventListener('turbo:before-cache', onTurboBeforeCache);

    scrollHandler = () => updateBackToTopVisibility();
    window.addEventListener('scroll', scrollHandler, { passive: true });

    resizeHandler = onResize;
    window.addEventListener('resize', resizeHandler);

    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)');
    prefersDark.addEventListener('change', onSystemThemeChange);
}

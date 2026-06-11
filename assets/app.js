import './stimulus_bootstrap.js';
import './controllers/csrf_protection_controller.js';
import { applyTheme, getStoredThemePreference, initRapporFamLayout } from './js/ef-layout.js';
import { initSessionIdle } from './js/ef-session-idle.js';
import { ensureCharCounterDelegation, initDescriptionCounters } from './js/ef-groups.js';
import { initContactForm } from './js/ef-contact-form.js';
import './js/ef-notifications.js';
import './js/ef-consent.js';
import './js/ef-analytics.js';
import './js/ef-adsense.js';
// Modules page : un seul point d’entrée pour éviter que Turbo attende de nouveaux <script> dans <head>
import './js/ef-events.js';
import './js/ef-messages.js';
import './js/ef-profile-avatar.js';
import './js/ef-group-message-photos.js';
import './js/ef-message-photo-lightbox.js';
import './js/ef-profile-message.js';

function initAppShell() {
    initRapporFamLayout();
    initSessionIdle();
    ensureCharCounterDelegation();
    initDescriptionCounters();
    initContactForm();
}

document.addEventListener('turbo:load', initAppShell);
document.addEventListener('turbo:render', () => {
    applyTheme(getStoredThemePreference());
    initDescriptionCounters();
    initContactForm();
});

if (document.readyState !== 'loading') {
    initAppShell();
} else {
    document.addEventListener('DOMContentLoaded', initAppShell);
}

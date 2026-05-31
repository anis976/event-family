import './stimulus_bootstrap.js';
import './controllers/csrf_protection_controller.js';
import { initEventFamilyLayout } from './js/ef-layout.js';
import { initSessionIdle } from './js/ef-session-idle.js';
import { ensureCharCounterDelegation, initDescriptionCounters } from './js/ef-groups.js';
import { initContactForm } from './js/ef-contact-form.js';
import './js/ef-notifications.js';

function initAppShell() {
    initEventFamilyLayout();
    initSessionIdle();
    ensureCharCounterDelegation();
    initDescriptionCounters();
    initContactForm();
}

document.addEventListener('turbo:load', initAppShell);
document.addEventListener('turbo:render', () => {
    initDescriptionCounters();
    initContactForm();
});

if (document.readyState !== 'loading') {
    initAppShell();
} else {
    document.addEventListener('DOMContentLoaded', initAppShell);
}

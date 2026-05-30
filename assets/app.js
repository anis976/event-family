import './stimulus_bootstrap.js';
import './controllers/csrf_protection_controller.js';
import { initEventFamilyLayout } from './js/ef-layout.js';
import { initSessionIdle } from './js/ef-session-idle.js';
import './js/ef-notifications.js';

document.addEventListener('turbo:load', () => {
    initEventFamilyLayout();
    initSessionIdle();
});

if (document.readyState !== 'loading') {
    initEventFamilyLayout();
    initSessionIdle();
} else {
    document.addEventListener('DOMContentLoaded', () => {
        initEventFamilyLayout();
        initSessionIdle();
    });
}

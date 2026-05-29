import './stimulus_bootstrap.js';
import './controllers/csrf_protection_controller.js';
import { initEventFamilyLayout } from './js/ef-layout.js';
import './js/ef-notifications.js';

document.addEventListener('turbo:load', initEventFamilyLayout);

if (document.readyState !== 'loading') {
    initEventFamilyLayout();
} else {
    document.addEventListener('DOMContentLoaded', initEventFamilyLayout);
}

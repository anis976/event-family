import './stimulus_bootstrap.js';
import { initEventFamilyLayout } from './js/ef-layout.js';

document.addEventListener('turbo:load', initEventFamilyLayout);

if (document.readyState !== 'loading') {
    initEventFamilyLayout();
} else {
    document.addEventListener('DOMContentLoaded', initEventFamilyLayout);
}

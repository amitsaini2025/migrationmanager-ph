/**
 * Shared CRM vendor libraries (Phase 2b).
 * Loaded via @vite after jQuery CDN in layout <head>.
 * Exposes globals expected by legacy public/js scripts.
 */
import TomSelect from 'tom-select';
import flatpickr from 'flatpickr';
import JSZip from 'jszip';

import 'datatables.net';
import 'datatables.net-bs5';
import 'datatables.net-buttons';
import 'datatables.net-buttons-bs5';
import 'datatables.net-buttons/js/buttons.html5.mjs';

import iziToast from 'izitoast';
import { registerMmTomSelectBridge } from './vendor/mm-tomselect-jquery.js';
import './vendor/crm-flatpickr.js';

window.TomSelect = TomSelect;
window.flatpickr = flatpickr;
window.JSZip = JSZip;
// Expose iziToast globally for legacy scripts (Vite CJS import does not set window by itself).
window.iziToast = iziToast && (iziToast.default || iziToast);

// Register after TomSelect is on window (static import is hoisted; do not rely on side-effect init).
registerMmTomSelectBridge(window.jQuery, TomSelect);

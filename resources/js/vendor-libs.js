/**
 * Shared CRM vendor libraries (Phase 2b).
 * Loaded via @vite after jQuery CDN in layout <head>.
 * Exposes globals expected by legacy public/js scripts.
 */
import TomSelect from 'tom-select';
import flatpickr from 'flatpickr';
import JSZip from 'jszip';

window.TomSelect = TomSelect;
window.flatpickr = flatpickr;
window.JSZip = JSZip;

import 'datatables.net';
import 'datatables.net-bs5';
import 'datatables.net-buttons';
import 'datatables.net-buttons-bs5';
import 'datatables.net-buttons/js/buttons.html5.mjs';

import '@legacy/iziToast.min.js';
import './vendor/mm-tomselect-jquery.js';
import './vendor/crm-flatpickr.js';

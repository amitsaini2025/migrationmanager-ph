/**
 * pdfMake + font vfs for DataTables pdfHtml5 export.
 * Separate entry (~1MB) so pages without PDF export avoid the download.
 */
import pdfMake from 'pdfmake/build/pdfmake.js';
import vfs from 'pdfmake/build/vfs_fonts.js';

pdfMake.vfs = vfs;
window.pdfMake = pdfMake;

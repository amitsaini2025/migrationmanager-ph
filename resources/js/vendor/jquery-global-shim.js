/**
 * DataTables npm packages import 'jquery'. CRM layouts load jQuery synchronously
 * from CDN in <head> — this shim re-exports that global instead of bundling jQuery.
 */
const jq = typeof window !== 'undefined' ? window.jQuery : undefined;

if (!jq) {
    throw new Error(
        'vendor-libs.js requires jQuery in <head> before the Vite vendor bundle loads.'
    );
}

export default jq;
export { jq as jQuery };

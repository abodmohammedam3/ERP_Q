/**
 * ═══════════════════════════════════════════════
 * الحزمة العامة للنظام - تُحمَّل في كل الصفحات
 * ═══════════════════════════════════════════════
 */

// ─── Bootstrap 5 ───
import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

// ─── Axios ───
import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const csrfToken = document.querySelector('meta[name="csrf-token"]');
if (csrfToken) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken.content;
}

// ─── الأدوات المشتركة ───
import './shared/utils';
import './shared/state';
import './shared/modals';

// ─── نظام التنبيهات ───
import './system';

console.log('[App] Bootstrap + Utils + State + Modals loaded');
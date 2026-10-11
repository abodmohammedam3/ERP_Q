/* ============================================================
   النسخ الاحتياطي — System Backup
   المرجع الهيكلي: accounting/journalEntries.js
   ============================================================ */

'use strict';

(function () {

    // ─────────────────────────────────────────────────────────
    //  الحالة
    // ─────────────────────────────────────────────────────────
    const State = {
        backups: [],
        totalSize: 0,
        activity: { count: 0, threshold: 100 },
        retention: { manual: 7, safety: 3 },

        listAbort: null,
        pollAbort: null,
        pollTimer: null,

        currentOperationId: null,
        pendingDeleteFilename: null,
    };

    // ─────────────────────────────────────────────────────────
    //  المسارات
    // ─────────────────────────────────────────────────────────
    const API = {
        index: '/settings/system/backup',
        export: '/settings/system/backup/export',
        import: '/settings/system/backup/import',
        operation: (id) => `/settings/system/backup/operations/${id}`,
        download: (fn) => `/settings/system/backup/files/${encodeURIComponent(fn)}`,
        destroy: (fn) => `/settings/system/backup/files/${encodeURIComponent(fn)}`,
        cleanup: '/settings/system/backup/cleanup',
    };

    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // ─────────────────────────────────────────────────────────
    //  العناصر
    // ─────────────────────────────────────────────────────────
    const El = {};

    // ─────────────────────────────────────────────────────────
    //  أدوات
    // ─────────────────────────────────────────────────────────
    function toast(message, type = 'info') {
        if (typeof window.showSystemToast === 'function') {
            window.showSystemToast(message, type);
        }
    }

    function cloneTemplate(templateId) {
        const tpl = document.getElementById(templateId);
        if (!tpl) return null;
        const first = tpl.content.firstElementChild;
        return first ? first.cloneNode(true) : null;
    }

    function formatBytes(bytes) {
        if (!bytes || bytes <= 0) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB'];
        let i = 0;
        let value = bytes;
        while (value >= 1024 && i < units.length - 1) {
            value /= 1024;
            i++;
        }
        return value.toFixed(2) + ' ' + units[i];
    }

    function detectType(filename) {
        if (!filename) return 'unknown';
        if (filename.startsWith('safety_')) return 'safety';
        if (filename.startsWith('auto_')) return 'auto';
        if (filename.startsWith('backup_')) return 'manual';
        return 'unknown';
    }

    function typeBadge(type) {
        const map = {
            manual: 'sbBadgeManualTemplate',
            auto: 'sbBadgeAutoTemplate',
            safety: 'sbBadgeSafetyTemplate',
            unknown: 'sbBadgeUnknownTemplate',
        };
        return cloneTemplate(map[type] || map.unknown);
    }

    // ─────────────────────────────────────────────────────────
    //  تحميل القائمة
    // ─────────────────────────────────────────────────────────
    async function loadBackups() {
        if (State.listAbort) State.listAbort.abort();
        State.listAbort = new AbortController();

        try {
            const res = await fetch(API.index, {
                signal: State.listAbort.signal,
                headers: { 'Accept': 'application/json' },
            });

            const json = await res.json();

            if (!json.success) {
                renderError();
                return;
            }

            State.backups = json.backups || [];
            State.totalSize = json.total_size || 0;
            State.activity = json.activity || State.activity;
            State.retention = json.retention || State.retention;

            renderHeader(json);
            renderTable();
        } catch (error) {
            if (error.name === 'AbortError') return;
            console.error(error);
            renderError();
        }
    }

    // ─────────────────────────────────────────────────────────
    //  عرض الرأس
    // ─────────────────────────────────────────────────────────
    function renderHeader(json) {
        const totalSizeEl = document.getElementById('sbTotalSize');
        const totalCountEl = document.getElementById('sbTotalCount');
        const lastDateEl = document.getElementById('sbLastBackupDate');
        const lastTriggerEl = document.getElementById('sbLastBackupTrigger');
        const retentionEl = document.getElementById('sbRetentionLimit');

        if (totalSizeEl) totalSizeEl.textContent = formatBytes(json.total_size || 0);
        if (totalCountEl) totalCountEl.textContent = String(json.total_count || 0);
        if (retentionEl) retentionEl.textContent = String(State.retention.manual || 7);

        if (json.last_backup) {
            if (lastDateEl) lastDateEl.textContent = json.last_backup.date;

            if (lastTriggerEl) {
                const labels = {
                    manual: 'يدوي',
                    auto: 'تلقائي',
                    safety: 'آمن',
                    unknown: '—',
                };
                lastTriggerEl.textContent = labels[detectType(json.last_backup.filename)] || '—';
            }
        } else {
            if (lastDateEl) lastDateEl.textContent = 'لا توجد';
            if (lastTriggerEl) lastTriggerEl.textContent = '—';
        }

        renderActivity(json.activity || State.activity);
    }

    function renderActivity(activity) {
        const count = activity.since_last_backup || 0;
        const threshold = activity.threshold || 100;

        const countEl = document.getElementById('sbActivityCount');
        const thresholdEl = document.getElementById('sbActivityThreshold');
        const progressEl = document.getElementById('sbActivityProgress');
        const lastAtEl = document.getElementById('sbActivityLastAt');

        if (countEl) countEl.textContent = String(count);
        if (thresholdEl) thresholdEl.textContent = String(threshold);

        if (progressEl) {
            const percent = Math.min(100, Math.round((count / threshold) * 100));
            progressEl.style.width = percent + '%';
        }

        if (lastAtEl && activity.last_at) {
            const date = new Date(activity.last_at);
            lastAtEl.textContent = 'آخر نشاط: ' + date.toLocaleString('ar-EG', {
                hour: '2-digit',
                minute: '2-digit',
            });
        }
    }

    // ─────────────────────────────────────────────────────────
    //  عرض الجدول
    // ─────────────────────────────────────────────────────────
    function renderTable() {
        const tbody = El.tableBody;
        if (!tbody) return;

        tbody.replaceChildren();

        if (!State.backups.length) {
            const empty = cloneTemplate('sbEmptyTemplate');
            if (empty) tbody.appendChild(empty);
            updateRowCount(0);
            return;
        }

        const fragment = document.createDocumentFragment();

        State.backups.forEach(b => {
            const row = buildRow(b);
            if (row) fragment.appendChild(row);
        });

        tbody.appendChild(fragment);
        updateRowCount(State.backups.length);
    }

    function buildRow(backup) {
        const tr = cloneTemplate('sbRowTemplate');
        if (!tr) return null;

        const filenameEl = tr.querySelector('.sb-filename');
        const typeEl = tr.querySelector('.sb-type');
        const dateEl = tr.querySelector('.sb-date');
        const sizeEl = tr.querySelector('.sb-size');

        if (filenameEl) filenameEl.textContent = backup.filename || '';
        if (dateEl) dateEl.textContent = backup.date || '';
        if (sizeEl) sizeEl.textContent = formatBytes(backup.size || 0);

        if (typeEl) {
            const badge = typeBadge(detectType(backup.filename));
            if (badge) typeEl.appendChild(badge);
        }

        const downloadBtn = tr.querySelector('.sb-btn-download');
        if (downloadBtn) {
            downloadBtn.addEventListener('click', () => downloadExisting(backup.filename));
        }

        const deleteBtn = tr.querySelector('.sb-btn-delete');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', () => askDelete(backup.filename));
        }

        return tr;
    }

    function updateRowCount(count) {
        const el = document.getElementById('sbRowCount');
        if (el) el.textContent = `عدد النتائج: ${count}`;
    }

    function renderError() {
        const tbody = El.tableBody;
        if (!tbody) return;
        tbody.replaceChildren();
        const row = cloneTemplate('sbErrorTemplate');
        if (row) tbody.appendChild(row);
    }

    // ─────────────────────────────────────────────────────────
    //  التصدير
    // ─────────────────────────────────────────────────────────
    async function startExport() {
        const modal = bootstrap.Modal.getInstance(El.exportModal);
        modal?.hide();

        showProgress('جاري تصدير النسخة...');

        try {
            const res = await fetch(API.export, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                },
                body: JSON.stringify({ format: 'sql' }),
            });

            const json = await res.json();

            if (!json.success) {
                hideProgress();
                toast(json.message || 'فشل بدء التصدير', 'danger');
                return;
            }

            State.currentOperationId = json.operation_id;
            pollOperation(json.operation_id);

        } catch (error) {
            hideProgress();
            toast('فشل الاتصال بالخادم', 'danger');
        }
    }

    // ─────────────────────────────────────────────────────────
    //  الاستيراد
    // ─────────────────────────────────────────────────────────
    async function startImport() {
        const fileInput = document.getElementById('sbImportFile');
        const confirmInput = document.getElementById('sbImportConfirm');

        const file = fileInput?.files?.[0];

        if (!file) {
            toast('يجب اختيار ملف النسخة', 'warning');
            return;
        }

        if (confirmInput?.value.trim() !== 'استبدال') {
            toast('يجب كتابة كلمة: استبدال', 'warning');
            return;
        }

        const formData = new FormData();
        formData.append('backup_file', file);
        formData.append('confirmation', 'استبدال');

        const modal = bootstrap.Modal.getInstance(El.importModal);
        modal?.hide();

        showProgress('جاري رفع الملف...');

        try {
            const xhr = new XMLHttpRequest();

            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const percent = Math.round((e.loaded / e.total) * 50);
                    updateProgress(percent, 'جاري رفع الملف...');
                }
            });

            xhr.addEventListener('load', () => {
                try {
                    const json = JSON.parse(xhr.responseText);

                    if (xhr.status !== 202 || !json.success) {
                        hideProgress();
                        toast(json.message || 'فشل الاستيراد', 'danger');
                        return;
                    }

                    State.currentOperationId = json.operation_id;
                    pollOperation(json.operation_id);

                } catch (err) {
                    hideProgress();
                    toast('فشل قراءة الرد من الخادم', 'danger');
                }
            });

            xhr.addEventListener('error', () => {
                hideProgress();
                toast('فشل رفع الملف', 'danger');
            });

            xhr.open('POST', API.import);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-CSRF-TOKEN', CSRF);
            xhr.send(formData);

        } catch (error) {
            hideProgress();
            toast('فشل الاتصال بالخادم', 'danger');
        }
    }

    // ─────────────────────────────────────────────────────────
    //  Polling — متابعة العملية
    // ─────────────────────────────────────────────────────────
    function pollOperation(operationId) {
        if (State.pollAbort) State.pollAbort.abort();
        State.pollAbort = new AbortController();

        let failures = 0;
        let notFoundCount = 0;

        const doFetch = async () => {
            try {
                const res = await fetch(API.operation(operationId), {
                    signal: State.pollAbort.signal,
                    headers: { 'Accept': 'application/json' },
                });

                // 404 متكرر = غالباً استبدل الاستيراد صف العملية (لقطة قديمة).
                // بعد ~30 ثانية نتوقف ونحدّث الصفحة بدل المتابعة للأبد.
                if (res.status === 404) {
                    notFoundCount++;

                    if (notFoundCount > 9) {
                        hideProgress();
                        toast('اكتملت العملية على الخادم — سيتم تحديث الصفحة', 'info');
                        setTimeout(() => window.location.reload(), 5000);
                        return;
                    }

                    State.pollTimer = setTimeout(doFetch, 3000);
                    return;
                }

                notFoundCount = 0;

                // رد غير ناجح (مثلاً 503 أثناء وضع الصيانة) — نواصل المتابعة
                // بدل إخفاء شريط التقدم صامتاً كما كان سابقاً
                if (!res.ok) {
                    failures++;

                    if (failures > 100) { // ≈ 5 دقائق
                        hideProgress();
                        toast('انتهت مهلة المتابعة — حدّث الصفحة لاحقاً للنتيجة', 'warning');
                        return;
                    }

                    State.pollTimer = setTimeout(doFetch, 3000);
                    return;
                }

                const json = await res.json();
                failures = 0;

                if (!json.success) {
                    hideProgress();
                    toast(json.message || 'فشل متابعة العملية', 'danger');
                    return;
                }

                const op = json.operation;

                updateProgress(op.progress, op.stage || 'جاري التنفيذ...');

                if (op.status === 'done') {
                    hideProgress();

                    if (op.type === 'export' && op.download) {
                        toast('تم التصدير — سيبدأ التنزيل الآن', 'success');
                        window.location.href = op.download;
                        setTimeout(loadBackups, 2000);
                    } else if (op.type === 'import') {
                        toast('تم الاستيراد — سيتم تحديث الصفحة', 'success');
                        setTimeout(() => window.location.reload(), 2000);
                    } else {
                        toast('تمت العملية بنجاح', 'success');
                        loadBackups();
                    }
                    return;
                }

                if (op.status === 'failed') {
                    hideProgress();
                    toast(op.error || 'فشلت العملية', 'danger');
                    loadBackups();
                    return;
                }

                State.pollTimer = setTimeout(doFetch, 2000);

            } catch (error) {
                if (error.name === 'AbortError') return;
                console.error(error);

                // خطأ شبكة مؤقت (صيانة/إعادة تشغيل الخادم) — نواصل المحاولة
                failures++;

                if (failures > 20) {
                    hideProgress();
                    toast('انقطع الاتصال بالخادم — حدّث الصفحة لاحقاً للنتيجة', 'warning');
                    return;
                }

                State.pollTimer = setTimeout(doFetch, 3000);
            }
        };

        doFetch();
    }

    // ─────────────────────────────────────────────────────────
    //  شريط التقدم
    // ─────────────────────────────────────────────────────────
    function showProgress(title) {
        const el = document.getElementById('sbProgressContainer');
        if (!el) return;
        el.classList.remove('d-none');

        const titleEl = document.getElementById('sbProgressTitle');
        if (titleEl) titleEl.textContent = title;

        updateProgress(0, 'جاري التحضير...');
    }

    function updateProgress(percent, stage) {
        const bar = document.getElementById('sbProgressBar');
        const percentEl = document.getElementById('sbProgressPercent');
        const stageEl = document.getElementById('sbProgressStage');

        if (bar) bar.style.width = Math.min(100, Math.max(0, percent)) + '%';
        if (percentEl) percentEl.textContent = percent + '%';
        if (stageEl) stageEl.textContent = stage || '';
    }

    function hideProgress() {
        const el = document.getElementById('sbProgressContainer');
        if (el) el.classList.add('d-none');

        if (State.pollTimer) {
            clearTimeout(State.pollTimer);
            State.pollTimer = null;
        }
    }

    // ─────────────────────────────────────────────────────────
    //  تنزيل ملف موجود
    // ─────────────────────────────────────────────────────────
    function downloadExisting(filename) {
        window.location.href = API.download(filename);
    }

    // ─────────────────────────────────────────────────────────
    //  حذف نسخة (بمودال موحّد)
    // ─────────────────────────────────────────────────────────
    function askDelete(filename) {
        State.pendingDeleteFilename = filename;

        const modal = document.getElementById('sbDeleteModal');
        const nameEl = document.getElementById('sbDeleteFilename');

        if (nameEl) nameEl.textContent = filename;

        if (modal) {
            modal.classList.add('show');
            modal.style.display = 'flex';
        }
    }

    async function performDelete() {
        const filename = State.pendingDeleteFilename;
        if (!filename) return;

        closeDeleteModal();

        try {
            const res = await fetch(API.destroy(filename), {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                },
            });

            const json = await res.json();

            if (!json.success) {
                toast(json.message || 'فشل الحذف', 'danger');
                return;
            }

            toast('تم الحذف بنجاح', 'success');
            loadBackups();

        } catch (error) {
            toast('فشل الاتصال بالخادم', 'danger');
        }
    }

    function closeDeleteModal() {
        const modal = document.getElementById('sbDeleteModal');
        if (modal) {
            modal.classList.remove('show');
            modal.style.display = 'none';
        }
        State.pendingDeleteFilename = null;
    }

    // ─────────────────────────────────────────────────────────
    //  تنظيف يدوي
    // ─────────────────────────────────────────────────────────
    function askCleanup() {
        const modal = document.getElementById('sbCleanupModal');
        if (modal) {
            modal.classList.add('show');
            modal.style.display = 'flex';
        }
    }

    async function performCleanup() {
        closeCleanupModal();

        try {
            const res = await fetch(API.cleanup, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                },
            });

            const json = await res.json();

            if (!json.success) {
                toast(json.message || 'فشل التنظيف', 'danger');
                return;
            }

            toast(json.message || 'تم التنظيف', 'success');
            loadBackups();

        } catch (error) {
            toast('فشل الاتصال بالخادم', 'danger');
        }
    }

    function closeCleanupModal() {
        const modal = document.getElementById('sbCleanupModal');
        if (modal) {
            modal.classList.remove('show');
            modal.style.display = 'none';
        }
    }

    // ─────────────────────────────────────────────────────────
    //  التهيئة
    // ─────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        cacheElements();

        if (!El.tableBody) return;

        bindEvents();
        loadBackups();

        // تحديث دوري كل 30 ثانية
        setInterval(loadBackups, 30000);
    });

    function cacheElements() {
        El.tableBody = document.getElementById('sbTableBody');

        El.btnExport = document.getElementById('sbBtnExport');
        El.btnImport = document.getElementById('sbBtnImport');
        El.btnCleanup = document.getElementById('sbBtnCleanup');
        El.btnRefresh = document.getElementById('sbBtnRefresh');

        El.exportModal = document.getElementById('sbExportModal');
        El.importModal = document.getElementById('sbImportModal');

        El.btnConfirmExport = document.getElementById('sbBtnConfirmExport');
        El.btnConfirmImport = document.getElementById('sbBtnConfirmImport');

        El.importFile = document.getElementById('sbImportFile');
        El.importConfirm = document.getElementById('sbImportConfirm');
    }

    function bindEvents() {
        El.btnExport?.addEventListener('click', () => {
            bootstrap.Modal.getOrCreateInstance(El.exportModal).show();
        });

        El.btnImport?.addEventListener('click', () => {
            if (El.importFile) El.importFile.value = '';
            if (El.importConfirm) El.importConfirm.value = '';
            if (El.btnConfirmImport) El.btnConfirmImport.disabled = true;

            bootstrap.Modal.getOrCreateInstance(El.importModal).show();
        });

        El.btnCleanup?.addEventListener('click', askCleanup);
        El.btnRefresh?.addEventListener('click', loadBackups);

        El.btnConfirmExport?.addEventListener('click', startExport);

        El.importFile?.addEventListener('change', checkImportReady);
        El.importConfirm?.addEventListener('input', checkImportReady);
        El.btnConfirmImport?.addEventListener('click', startImport);

        // مودال الحذف
        document.getElementById('sbDeleteConfirmBtn')?.addEventListener('click', performDelete);
        document.getElementById('sbDeleteCancelBtn')?.addEventListener('click', closeDeleteModal);

        // مودال التنظيف
        document.getElementById('sbCleanupConfirmBtn')?.addEventListener('click', performCleanup);
        document.getElementById('sbCleanupCancelBtn')?.addEventListener('click', closeCleanupModal);

        // ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeDeleteModal();
                closeCleanupModal();
            }
        });
    }

    function checkImportReady() {
        const fileReady = El.importFile?.files?.length > 0;
        const confirmReady = El.importConfirm?.value.trim() === 'استبدال';

        if (El.btnConfirmImport) {
            El.btnConfirmImport.disabled = !(fileReady && confirmReady);
        }
    }

})();

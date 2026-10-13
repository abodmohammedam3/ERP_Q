/* =========================================================================
   مودال تأكيد عام (App Confirm Modal)
   يتطلب وجود #appConfirmModal في الصفحة (يُضمَّن في layouts.app).
   ========================================================================= */

(function () {
    'use strict';

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = String(str ?? '');
        return div.innerHTML;
    }

    function cleanupBackdrops() {
        if (document.querySelectorAll('.modal.show').length > 0) return;

        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
    }

    /**
     * يعرض مودال تأكيد ويعيد Promise<boolean>.
     * @param {string} message
     * @param {{title?:string, okText?:string, cancelText?:string, danger?:boolean}} [opts]
     * @returns {Promise<boolean>}
     */
    function appConfirm(message, opts = {}) {
        const modalEl = document.getElementById('appConfirmModal');

        if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            return Promise.resolve(window.confirm(message));
        }

        const titleEl   = modalEl.querySelector('#appConfirmModalTitle');
        const msgEl     = modalEl.querySelector('#appConfirmModalMessage');
        const okBtn     = modalEl.querySelector('#appConfirmModalOk');
        const cancelBtn = modalEl.querySelector('#appConfirmModalCancel');

        if (titleEl)   titleEl.textContent = opts.title || 'تأكيد';
        if (msgEl)     msgEl.innerHTML = escapeHtml(message).replace(/\n/g, '<br>');
        if (okBtn)     okBtn.textContent = opts.okText || 'تأكيد';
        if (cancelBtn) cancelBtn.textContent = opts.cancelText || 'إلغاء';

        if (okBtn) {
            okBtn.classList.toggle('btn-danger', !!opts.danger);
            okBtn.classList.toggle('btn-primary', !opts.danger);
        }

        return new Promise((resolve) => {
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            let result = false;

            const onOk = () => {
                result = true;
                modal.hide();
            };

            const onHidden = () => {
                okBtn?.removeEventListener('click', onOk);
                modalEl.removeEventListener('hidden.bs.modal', onHidden);
                resolve(result);
                cleanupBackdrops();
            };

            okBtn?.addEventListener('click', onOk, { once: true });
            modalEl.addEventListener('hidden.bs.modal', onHidden, { once: true });

            modal.show();
        });
    }

    window.appConfirm = appConfirm;
})();
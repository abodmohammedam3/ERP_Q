

<div
    class="modal fade"
    id="appConfirmModal"
    tabindex="-1"
    aria-hidden="true"
    data-bs-backdrop="static"
    data-bs-keyboard="false"
>
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">

            <div class="modal-header">
                <h6 class="modal-title" id="appConfirmModalTitle">تأكيد</h6>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="إغلاق"
                ></button>
            </div>

            <div class="modal-body">
                <p class="mb-0" id="appConfirmModalMessage"></p>
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn-secondary btn-sm"
                    id="appConfirmModalCancel"
                    data-bs-dismiss="modal"
                >
                    إلغاء
                </button>

                <button
                    type="button"
                    class="btn btn-primary btn-sm"
                    id="appConfirmModalOk"
                >
                    تأكيد
                </button>
            </div>

        </div>
    </div>
</div><?php /**PATH C:\Laravel\qaatSystem\resources\views/shared/app-confirm-modal.blade.php ENDPATH**/ ?>
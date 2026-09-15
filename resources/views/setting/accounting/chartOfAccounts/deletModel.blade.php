{{-- ========================================================= --}}
{{-- مودال تأكيد حذف الحساب --}}
{{-- ========================================================= --}}

<div
    class="modal fade"
    id="deleteConfirmModal"
    tabindex="-1"
    aria-labelledby="deleteConfirmModalLabel"
    aria-hidden="true"
    data-bs-backdrop="static"
    data-bs-keyboard="false"
>
    <div class="modal-dialog modal-dialog-centered modal-sm">

        <div class="modal-content">

            {{-- رأس المودال --}}
            <div class="modal-header position-relative">

                <h5
                    class="modal-title fw-bold text-danger"
                    id="deleteConfirmModalLabel"
                >
                    تأكيد حذف الحساب
                </h5>

                <button
                    type="button"
                    class="btn-close position-absolute top-0 start-0 m-3"
                    id="deleteCancelBtn"
                    aria-label="إغلاق"
                ></button>

            </div>


            {{-- محتوى التأكيد --}}
            <div class="modal-body text-center">

                <div class="mb-3">

                    <div
                        class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger-subtle text-danger"
                        style="width: 64px; height: 64px;"
                    >
                        <i class="bi bi-trash fs-3"></i>
                    </div>

                </div>


                <h6 class="fw-bold mb-2">
                    هل أنت متأكد من حذف هذا الحساب؟
                </h6>


                <p class="text-body-secondary mb-0">
                    لا يمكن التراجع عن عملية الحذف بعد تنفيذها.
                </p>

            </div>


            {{-- أزرار المودال --}}
            <div class="modal-footer justify-content-center">

                <button
                    type="button"
                    class="btn btn-secondary"
                    id="deleteCancelBtnFooter"
                >
                    <i class="bi bi-x-lg me-1"></i>
                    إلغاء
                </button>

                <button
                    type="button"
                    class="btn btn-danger"
                    id="deleteConfirmBtn"
                >
                    <i class="bi bi-trash me-1"></i>
                    نعم، حذف الحساب
                </button>

            </div>

        </div>

    </div>
</div>
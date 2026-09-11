{{-- =====================================================
     مودال تأكيد الحذف
===================================================== --}}

@push('modals')

<div
    class="modal fade"
    id="deleteConfirmModal"
    tabindex="-1"
    aria-labelledby="deleteConfirmModalLabel"
    aria-hidden="true"
    data-bs-backdrop="static"
    data-bs-keyboard="false"
>
    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content border-0 shadow-lg">

            {{-- رأس المودال --}}
            <div class="modal-header border-0 pb-0">

                <h5
                    class="modal-title fw-bold"
                    id="deleteConfirmModalLabel"
                >
                    تأكيد الحذف
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="إغلاق"
                ></button>

            </div>


            {{-- جسم المودال --}}
            <div class="modal-body text-center py-4">

                <div
                    class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger-subtle mb-3"
                    style="width: 80px; height: 80px;"
                >
                    <i class="bi bi-exclamation-triangle-fill text-danger fs-1"></i>
                </div>

                <h5 class="fw-bold mb-2">
                    هل أنت متأكد من الحذف؟
                </h5>

                <p class="text-muted mb-0">
                    لا يمكن التراجع عن هذا الإجراء بعد تنفيذه.
                </p>

            </div>


            {{-- أزرار المودال --}}
            <div class="modal-footer border-0 pt-0 justify-content-center gap-2">

                <button
                    type="button"
                    class="btn btn-light px-4"
                    id="deleteCancelBtn"
                    data-bs-dismiss="modal"
                >
                    <i class="bi bi-x-lg me-1"></i>
                    إلغاء
                </button>

                <button
                    type="button"
                    class="btn btn-danger px-4"
                    id="deleteConfirmBtn"
                >
                    <i class="bi bi-trash me-1"></i>
                    نعم، احذف
                </button>

            </div>

        </div>

    </div>
</div>

@endpush
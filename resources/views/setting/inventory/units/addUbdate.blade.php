{{-- مودال إضافة/تعديل وحدة --}}
<div class="modal fade" id="unitModal" tabindex="-1" aria-labelledby="unitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="unitModalLabel">
                    <i class="bi bi-rulers"></i>
                    إضافة وحدة جديدة
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body">
                <form id="unitForm">
                    <input type="hidden" id="unitID">
                    <div class="mb-3">
                        <label for="unitName" class="form-label">اسم الوحدة <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="unitName" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i> إلغاء
                </button>
                <button type="button" class="btn btn-primary" onclick="saveUnit()">
                    <i class="bi bi-check-lg"></i> حفظ البيانات
                </button>
            </div>
        </div>
    </div>
</div>
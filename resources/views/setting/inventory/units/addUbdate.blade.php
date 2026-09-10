{{-- مودال إضافة/تعديل وحدة --}}
<div class="modal fade" id="unitModal" tabindex="-1" aria-labelledby="unitModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header position-relative">
                <h5 class="modal-title" id="unitModalLabel">إضافة وحدة جديدة</h5>
                <button type="button" class="btn-close position-absolute top-0 start-0 m-3 " data-bs-dismiss="modal" aria-label="Close"></button>
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
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" onclick="saveUnit()">حفظ البيانات</button>
            </div>
        </div>
    </div>
</div>
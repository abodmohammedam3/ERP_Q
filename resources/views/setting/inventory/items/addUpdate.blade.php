<!-- النافذة المنبثقة (Modal) لإضافة/تعديل صنف -->
<div class="modal fade" id="itemModal" tabindex="-1" aria-labelledby="itemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="itemModalLabel">
                    <i class="bi bi-box-seam"></i>
                    إضافة صنف جديد
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body">
                <form id="itemForm">
                    <input type="hidden" id="itemID">
                    
                    <div class="mb-3">
                        <label for="itemName" class="form-label">اسم الصنف <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="itemName" >
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i> إلغاء
                </button>
                <button type="button" class="btn btn-primary" onclick="saveItem()">
                    <i class="bi bi-check-lg"></i> حفظ البيانات
                </button>
            </div>
        </div>
    </div>
</div>
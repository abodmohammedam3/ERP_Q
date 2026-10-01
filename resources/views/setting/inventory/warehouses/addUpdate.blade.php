
<!-- مودال الإضافة/التعديل -->
<div class="modal fade" id="stockModal" tabindex="-1" aria-labelledby="stockModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="stockModalLabel">
                    <i class="bi bi-building"></i>
                    إضافة مخزن جديد
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body">
                <form id="stockForm">
                    <input type="hidden" id="stockID">
                    <div class="mb-3">
                        <label for="stockName" class="form-label">اسم المخزن <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="stockName" >
                    </div>
                    <div class="mb-3">
                        <label for="accountDisplay" class="form-label">رقم الحساب التحليلي</label>
                        <input type="text" class="form-control" id="accountDisplay" readonly>
                        <small class="text-muted">يتم إنشاؤه تلقائياً عند الإضافة</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i> إلغاء
                </button>
                <button type="button" class="btn btn-primary" onclick="saveStock()">
                    <i class="bi bi-check-lg"></i> حفظ البيانات
                </button>
            </div>
        </div>
    </div>
</div>


<!-- مودال الإضافة/التعديل -->
<div class="modal fade" id="stockModal" tabindex="-1" aria-labelledby="stockModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header  position-relative">
                <h5 class="modal-title" id="stockModalLabel">إضافة مخزن جديد</h5>
                <button type="button" class="btn-close position-absolute  top-0 start-0 m-3" data-bs-dismiss="modal"></button>
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
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" onclick="saveStock()">حفظ</button>
            </div>
        </div>
    </div>
</div>

<!-- النافذة المنبثقة (Modal) لإضافة/تعديل صنف -->
<div class="modal fade" id="itemModal" tabindex="-1" aria-labelledby="itemModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header position-relative">
                <h5 class="modal-title" id="itemModalLabel">إضافة صنف جديد</h5>
                <button type="button" class="btn-close position-absolute top-0 start-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
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
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" onclick="saveItem()">حفظ البيانات</button>
            </div>
        </div>
    </div>
</div>
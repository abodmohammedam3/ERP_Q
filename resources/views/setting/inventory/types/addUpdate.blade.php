<!-- النافذة المنبثقة (Modal) لإضافة/تعديل نوع -->
<div class="modal fade" id="typeModal" tabindex="-1" aria-labelledby="typeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header position-relative">
                <h5 class="modal-title" id="typeModalLabel">إضافة نوع جديد</h5>
                <button type="button" class="btn-close position-absolute top-0 start-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="typeForm">
                    <input type="hidden" id="typeID">

                    <div class="mb-3">
                        <label for="typeName" class="form-label">اسم النوع <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="typeName" >
                    </div>

                    <div class="mb-3">
                        <label for="typeCode" class="form-label">الكود</label>
                        <input type="text" class="form-control" id="typeCode" placeholder="مثال: T001 أو 123">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" onclick="saveType()">حفظ البيانات</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="sbImportModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">

            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    استيراد نسخة — سيتم استبدال كل البيانات
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <div class="alert alert-danger">
                    <h6 class="alert-heading">
                        <i class="bi bi-shield-exclamation"></i>
                        تحذير حرج
                    </h6>
                    <p class="mb-0">
                        الاستيراد سيستبدل <strong>كل بيانات النظام الحالية</strong>
                        ببيانات الملف المرفوع. لا يمكن التراجع.
                    </p>
                </div>

                <div class="alert alert-success py-2">
                    <small>
                        <i class="bi bi-shield-check"></i>
                        سيتم إنشاء <strong>نسخة أمان تلقائية</strong>
                        قبل الاستيراد. إذا فشل الاستيراد،
                        سيتم استعادة بياناتك السابقة تلقائياً.
                    </small>
                </div>

                <div class="mb-3">
                    <label for="sbImportFile" class="form-label">
                        ملف النسخة (SQL)
                    </label>
                    <input type="file"
                           class="form-control"
                           id="sbImportFile"
                           accept=".sql,application/sql">
                    <div class="form-text">
                        الحد الأقصى: 500 ميجا. الصيغة: .sql فقط.
                    </div>
                </div>

                <div class="mb-3">
                    <label for="sbImportConfirm" class="form-label">
                        للتأكيد، اكتب كلمة: <code>استبدال</code>
                    </label>
                    <input type="text"
                           class="form-control"
                           id="sbImportConfirm"
                           autocomplete="off"
                           placeholder="اكتب: استبدال">
                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    إلغاء
                </button>
                <button type="button"
                        class="btn btn-danger"
                        id="sbBtnConfirmImport"
                        disabled>
                    <i class="bi bi-upload"></i>
                    استبدال نهائي
                </button>
            </div>

        </div>
    </div>
</div>

<div class="modal fade" id="sbExportModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-download text-primary"></i>
                    تصدير نسخة احتياطية
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <div class="alert alert-info py-2">
                    <i class="bi bi-info-circle"></i>
                    سيتم تصدير قاعدة البيانات كاملة بصيغة SQL.
                </div>

                <div class="mb-3">
                    <label class="form-label">صيغة الملف</label>

                    <div class="form-check mb-2">
                        <input class="form-check-input"
                               type="radio"
                               name="sbFormat"
                               id="sbFormatSql"
                               value="sql"
                               checked>
                        <label class="form-check-label" for="sbFormatSql">
                            <strong>SQL</strong>
                            <small class="text-muted d-block">الأسرع — الصيغة القياسية</small>
                        </label>
                    </div>

                    <div class="form-check opacity-50">
                        <input class="form-check-input" type="radio" disabled>
                        <label class="form-check-label">
                            ZIP
                            <small class="text-muted d-block">(قريباً — مع المرفقات)</small>
                        </label>
                    </div>
                </div>

                <div class="alert alert-light border mb-0">
                    <small>
                        <i class="bi bi-lightbulb text-warning"></i>
                        بعد اكتمال التصدير، سيبدأ التنزيل تلقائياً.
                        يمكنك اختيار مكان الحفظ من نافذة المتصفح.
                    </small>
                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    إلغاء
                </button>
                <button type="button" class="btn btn-primary" id="sbBtnConfirmExport">
                    <i class="bi bi-check-lg"></i>
                    تصدير
                </button>
            </div>

        </div>
    </div>
</div>

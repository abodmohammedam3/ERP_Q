
<!-- مودال إضافة / تعديل حساب -->
<div class="modal fade" id="addAccountModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">إضافة حساب جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body">
                <form>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">رقم الحساب <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" placeholder="مثال: 1100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">اسم الحساب <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" placeholder="مثال: البنك الأهلي">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">نوع الحساب <span class="text-danger">*</span></label>
                            <select class="form-select">
                                <option value="">اختر النوع...</option>
                                <option value="assets">أصول</option>
                                <option value="liabilities">خصوم</option>
                                <option value="equity">حقوق ملكية</option>
                                <option value="revenue">إيرادات</option>
                                <option value="expense">مصروفات</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">الحساب الأب</label>
                            <select class="form-select">
                                <option value="">لا يوجد (حساب رئيسي)</option>
                                <option value="1000">1000 - الصندوق</option>
                                <option value="2000">2000 - البنك</option>
                                <option value="3000">3000 - المخزون</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">الرصيد الافتتاحي</label>
                            <input type="number" class="form-control" placeholder="0.00" step="0.01">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">الحالة</label>
                            <select class="form-select">
                                <option value="active">نشط</option>
                                <option value="inactive">غير نشط</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">ملاحظات</label>
                            <textarea class="form-control" rows="2" placeholder="ملاحظات إضافية..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary">حفظ الحساب</button>
            </div>
        </div>
    </div>
</div>
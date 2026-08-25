
    <!-- بطاقة جدول الحسابات -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>رقم الحساب</th>
                            <th>اسم الحساب</th>
                            <th>النوع</th>
                            <th>الحساب الأب</th>
                            <th>الرصيد</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- نموذج صف بيانات (سيتم استبداله بالبيانات الفعلية) -->
                        <tr>
                            <td>1</td>
                            <td>1000</td>
                            <td>الصندوق</td>
                            <td><span class="badge bg-success">أصول</span></td>
                            <td>--</td>
                            <td>10,000.00</td>
                            <td><span class="badge bg-primary">نشط</span></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary me-1">تعديل</button>
                                <button class="btn btn-sm btn-outline-danger">حذف</button>
                            </td>
                        </tr>
                        <tr>
                            <td>2</td>
                            <td>2000</td>
                            <td>البنك</td>
                            <td><span class="badge bg-success">أصول</span></td>
                            <td>--</td>
                            <td>50,000.00</td>
                            <td><span class="badge bg-primary">نشط</span></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary me-1">تعديل</button>
                                <button class="btn btn-sm btn-outline-danger">حذف</button>
                            </td>
                        </tr>
                        <tr>
                            <td>3</td>
                            <td>4000</td>
                            <td>المبيعات</td>
                            <td><span class="badge bg-info">إيرادات</span></td>
                            <td>--</td>
                            <td>0.00</td>
                            <td><span class="badge bg-secondary">غير نشط</span></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary me-1">تعديل</button>
                                <button class="btn btn-sm btn-outline-danger">حذف</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- تذييل الجدول (ترقيم الصفحات) -->
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center">
            <span class="text-muted small">عرض 1-3 من 3 حسابات</span>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item disabled"><a class="page-link" href="#">السابق</a></li>
                    <li class="page-item active"><a class="page-link" href="#">1</a></li>
                    <li class="page-item disabled"><a class="page-link" href="#">التالي</a></li>
                </ul>
            </nav>
        </div>
    </div>
</div>

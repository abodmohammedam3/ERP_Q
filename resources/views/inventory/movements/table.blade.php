{{-- جدول الحركات --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-clock-history"></i> سجل حركات المخزون
        </span>
        <span class="badge bg-secondary" id="movementsCountBadge">10</span>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-bordered mb-0 align-middle" id="movementsTable">
                <thead class="table-light">
                    <tr>
                        <th class="text-center">#</th>
                        <th>التاريخ</th>
                        <th>المخزن</th>
                        <th>نوع الحركة</th>
                        <th>الاتجاه</th>
                        <th>البيان</th>
                        <th class="text-center no-print">التفاصيل</th>
                    </tr>
                </thead>
                <tbody id="movementsTableBody">

                    <!-- البيانات التجريبية الـ 10 -->
                    <tr class="movement-row">
                        <td class="text-center">1001</td>
                        <td class="row-date">2026-08-20</td>
                        <td class="row-stock">المعرض الرئيسي</td>
                        <td class="row-type">شراء</td>
                        <td><span class="badge bg-success">دخول</span></td>
                        <td>فاتورة مشتريات رقم #P-501</td>
                        <td class="text-center no-print"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> عرض</button></td>
                    </tr>
                    <tr class="movement-row">
                        <td class="text-center">1002</td>
                        <td class="row-date">2026-08-21</td>
                        <td class="row-stock">المعرض الرئيسي</td>
                        <td class="row-type">بيع</td>
                        <td><span class="badge bg-danger">خروج</span></td>
                        <td>فاتورة مبيعات رقم #S-902</td>
                        <td class="text-center no-print"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> عرض</button></td>
                    </tr>
                    <tr class="movement-row">
                        <td class="text-center">1003</td>
                        <td class="row-date">2026-08-21</td>
                        <td class="row-stock">مستودع الجملة</td>
                        <td class="row-type">شراء</td>
                        <td><span class="badge bg-success">دخول</span></td>
                        <td>فاتورة مشتريات رقم #P-502</td>
                        <td class="text-center no-print"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> عرض</button></td>
                    </tr>
                    <tr class="movement-row">
                        <td class="text-center">1004</td>
                        <td class="row-date">2026-08-22</td>
                        <td class="row-stock">المعرض الرئيسي</td>
                        <td class="row-type">مرتجع بيع</td>
                        <td><span class="badge bg-success">دخول</span></td>
                        <td>إرجاع من العميل للفاتورة #S-902</td>
                        <td class="text-center no-print"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> عرض</button></td>
                    </tr>
                    <tr class="movement-row">
                        <td class="text-center">1005</td>
                        <td class="row-date">2026-08-23</td>
                        <td class="row-stock">مستودع الجملة</td>
                        <td class="row-type">بيع</td>
                        <td><span class="badge bg-danger">خروج</span></td>
                        <td>فاتورة مبيعات جملة رقم #S-903</td>
                        <td class="text-center no-print"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> عرض</button></td>
                    </tr>
                    <tr class="movement-row">
                        <td class="text-center">1006</td>
                        <td class="row-date">2026-08-24</td>
                        <td class="row-stock">المعرض الرئيسي</td>
                        <td class="row-type">بيع</td>
                        <td><span class="badge bg-danger">خروج</span></td>
                        <td>فاتورة مبيعات رقم #S-904</td>
                        <td class="text-center no-print"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> عرض</button></td>
                    </tr>
                    <tr class="movement-row">
                        <td class="text-center">1007</td>
                        <td class="row-date">2026-08-25</td>
                        <td class="row-stock">مستودع الجملة</td>
                        <td class="row-type">مرتجع شراء</td>
                        <td><span class="badge bg-danger">خروج</span></td>
                        <td>إرجاع للمورد مؤسسة الأفق</td>
                        <td class="text-center no-print"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> عرض</button></td>
                    </tr>
                    <tr class="movement-row">
                        <td class="text-center">1008</td>
                        <td class="row-date">2026-08-26</td>
                        <td class="row-stock">المعرض الرئيسي</td>
                        <td class="row-type">شراء</td>
                        <td><span class="badge bg-success">دخول</span></td>
                        <td>فاتورة مشتريات رقم #P-503</td>
                        <td class="text-center no-print"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> عرض</button></td>
                    </tr>
                    <tr class="movement-row">
                        <td class="text-center">1009</td>
                        <td class="row-date">2026-08-27</td>
                        <td class="row-stock">مستودع الجملة</td>
                        <td class="row-type">بيع</td>
                        <td><span class="badge bg-danger">خروج</span></td>
                        <td>فاتورة مبيعات رقم #S-905</td>
                        <td class="text-center no-print"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> عرض</button></td>
                    </tr>
                    <tr class="movement-row">
                        <td class="text-center">1010</td>
                        <td class="row-date">2026-08-28</td>
                        <td class="row-stock">المعرض الرئيسي</td>
                        <td class="row-type">بيع</td>
                        <td><span class="badge bg-danger">خروج</span></td>
                        <td>فاتورة مبيعات رقم #S-906</td>
                        <td class="text-center no-print"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> عرض</button></td>
                    </tr>

                    <tr id="emptyMovementsRow" style="display: none;">
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-arrow-left-right fs-2 d-block mb-2"></i>
                            لا توجد حركات مطابقة لشروط البحث
                        </td>
                    </tr>

                </tbody>
            </table>
        </div>
    </div>
</div>

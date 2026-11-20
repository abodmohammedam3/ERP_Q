{{-- مودال تفاصيل التوزيع (#distDetailsModal) --}}
<div class="modal fade" id="distDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">

            {{-- ترويسة مودال التفاصيل --}}
            <div class="modal-header bg-white border-0 py-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="bg-primary-subtle text-primary rounded-2 px-2 py-1">
                        <i class="bi bi-receipt"></i>
                    </span>
                    <div>
                        <h6 class="modal-title mb-0 fw-semibold" id="ddTitle">تفاصيل التوزيع</h6>
                        <div class="text-muted small" id="ddSubtitle">—</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                {{-- بطاقات الملخص الإجمالي للتوزيع --}}
                <div class="row g-2 mb-3 text-center">
                    <div class="col">
                        <div class="bg-light rounded-2 py-2">
                            <div class="text-muted small">صفوف</div>
                            <div class="fw-bold" id="ddDetailsCount">0</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="bg-light rounded-2 py-2">
                            <div class="text-muted small">عملاء</div>
                            <div class="fw-bold" id="ddCustomersCount">0</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="bg-light rounded-2 py-2">
                            <div class="text-muted small">كمية</div>
                            <div class="fw-bold" id="ddQty">0</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="bg-light rounded-2 py-2">
                            <div class="text-muted small">تكلفة</div>
                            <div class="fw-bold text-danger" id="ddTotalCost">0</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="bg-light rounded-2 py-2">
                            <div class="text-muted small">بيع</div>
                            <div class="fw-bold text-success" id="ddTotalSale">0</div>
                        </div>
                    </div>
                </div>

                {{-- جدول تفاصيل التوزيع بالأعمدة المطلوبة --}}
                <div class="table-responsive border rounded-3" style="max-height:400px; overflow-y:auto;">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr class="small text-muted">
                                <th>الفاتورة</th>
                                <th>الصنف</th>
                                <th>النوع</th>
                                <th>الرمز</th>
                                <th>العميل</th>
                                <th class="text-end">الكمية</th>
                                <th class="text-end">تكلفة الوحدة</th>
                                <th class="text-end">سعر البيع</th>
                                <th class="text-end">إجمالي</th>
                                <th class="text-center">الفوترة</th>
                            </tr>
                        </thead>
                        <tbody id="ddTbody"></tbody>
                    </table>
                </div>

                {{-- سجل تتبع التوزيع (Audit Log) --}}
                <div class="mt-3">
                    <button class="btn btn-sm btn-outline-secondary w-100 d-flex align-items-center justify-content-between" type="button"
                            data-bs-toggle="collapse" data-bs-target="#ddLogsCollapse"
                            aria-expanded="false" aria-controls="ddLogsCollapse">
                        <span><i class="bi bi-clock-history"></i> سجل التتبع (Audit Log)</span>
                        <i class="bi bi-chevron-down small"></i>
                    </button>
                    <div class="collapse" id="ddLogsCollapse">
                        <div class="border border-top-0 rounded-bottom p-2 bg-light">
                            <div id="ddLogsBody" class="small text-muted py-2 text-center">جارٍ التحميل…</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- أزرار أسفل مودال التفاصيل --}}
            <div class="modal-footer bg-white border-0 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    إغلاق
                </button>
                <a href="#" class="btn btn-primary d-none" id="ddEditBtn">
                    <i class="bi bi-pencil-square"></i> فتح للتعديل
                </a>
            </div>
        </div>
    </div>
</div>

{{-- مودال استعراض وتعديل بيانات عميل داخل التوزيع (#editDistCustomerModal) --}}
<div class="modal fade" id="editDistCustomerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">

            {{-- ترويسة مودال تعديل العميل --}}
            <div class="modal-header bg-white border-0 py-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="bg-warning-subtle text-warning rounded-2 px-2 py-1">
                        <i class="bi bi-person-gear"></i>
                    </span>
                    <div>
                        <h6 class="modal-title mb-0 fw-semibold" id="edcTitle">تعديل بيانات عميل في التوزيع</h6>
                        <div class="text-muted small" id="edcSubtitle">—</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                {{-- جدول تعديل صفوف العميل داخل التوزيع بالأعمدة المطلوبة --}}
                <div class="table-responsive border rounded-3" style="max-height:420px; overflow-y:auto;">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr class="small text-muted">
                                <th>الفاتورة</th>
                                <th>الرمز</th>
                                <th>الصنف والنوع</th>
                                <th class="text-center" style="width:120px;">الكمية</th>
                                <th class="text-center">الوحدة</th>
                                <th class="text-center" style="width:120px;">سعر البيع</th>
                                <th class="text-end">إجمالي البيع</th>
                                <th class="text-center">الحالة</th>
                                <th class="text-center" style="width:60px;">حذف</th>
                            </tr>
                        </thead>
                        <tbody id="edcTbody"></tbody>
                    </table>
                </div>
            </div>

            {{-- أزرار حفظ أو إغلاق مودال التعديل --}}
            <div class="modal-footer bg-white border-0 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    إغلاق
                </button>
                <button type="button" class="btn btn-primary" id="edcSaveBtn">
                    <i class="bi bi-check2-circle"></i> حفظ التعديلات
                </button>
            </div>

        </div>
    </div>
</div>

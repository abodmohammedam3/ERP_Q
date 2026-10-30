<div class="modal fade" id="distDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">

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

                <div class="table-responsive border rounded-3" style="max-height:400px; overflow-y:auto;">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr class="small text-muted">
                                <th>الصنف</th>
                                <th>الفاتورة</th>
                                <th>العميل</th>
                                <th class="text-end">الكمية</th>
                                <th class="text-center">الوحدة</th>
                                <th class="text-end">تكلفة الوحدة</th>
                                <th class="text-end">سعر البيع</th>
                                <th class="text-end">إجمالي التكلفة</th>
                                <th class="text-end">إجمالي البيع</th>
                                <th class="text-center">الفوترة</th>
                            </tr>
                        </thead>
                        <tbody id="ddTbody"></tbody>
                    </table>
                </div>
            </div>

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
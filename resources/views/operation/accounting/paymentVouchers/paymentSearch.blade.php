<!-- =========================================================
     مودال البحث عن سندات الصرف
     ========================================================= -->

<div
    class="modal fade"
    id="PaymentVoucherSearchModal"
    tabindex="-1"
    aria-labelledby="PaymentVoucherSearchModalLabel"
    aria-hidden="true"
>
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">

            <!-- رأس المودال -->
            <div class="modal-header">
                <h5 class="modal-title" id="PaymentVoucherSearchModalLabel">
                    <i class="bi bi-search me-2"></i>
                    البحث عن سند صرف
                </h5>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="إغلاق"
                ></button>
            </div>

            <!-- جسم المودال -->
            <div class="modal-body">

                <!-- حقل البحث (يعمل تلقائياً) -->
                <div class="row g-2 mb-3">
                    <div class="col-md-10">
                        <input
                            type="text"
                            class="form-control"
                            id="PaymentVoucherSearchInput"
                            placeholder="ابحث برقم السند أو اسم المورد..."
                            oninput="PaymentVoucherSearch.search()"
                            autofocus
                        >
                    </div>
                    <div class="col-md-2">
                        <button
                            type="button"
                            class="btn btn-primary w-100"
                            onclick="PaymentVoucherSearch.search()"
                        >
                            <i class="bi bi-search"></i>
                            بحث
                        </button>
                    </div>
                </div>

                <!-- جدول النتائج -->
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light">
                            <tr class="text-center">
                                <th>رقم السند</th>
                                <th>التاريخ</th>
                                <th>المورد</th>
                                <th>المبلغ</th>
                                <th>العملة</th>
                                <th>طريقة الدفع</th>
                                <th>الحالة</th>
                                <th>اختيار</th>
                            </tr>
                        </thead>
                        <tbody id="PaymentVoucherSearchResults">
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    ابحث عن سند صرف
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div><!-- /.modal-body -->

        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->
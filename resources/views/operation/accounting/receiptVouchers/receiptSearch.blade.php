<!-- =========================================================
     مودال البحث عن سندات القبض
     ========================================================= -->

<div class="modal fade"
     id="ReceiptVoucherSearchModal"
     tabindex="-1"
     aria-labelledby="ReceiptVoucherSearchModalLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">

            <!-- رأس المودال -->
            <div class="modal-header">
                <h5 class="modal-title" id="ReceiptVoucherSearchModalLabel">
                    <i class="bi bi-search me-2"></i>
                    البحث عن سند قبض
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>

            <!-- جسم المودال -->
            <div class="modal-body">

                <!-- حقل البحث + زر تفريغ -->
                <div class="row g-2 mb-3">
                    <div class="col-md-10">
                        <input type="text"
                               class="form-control"
                               id="ReceiptVoucherSearchInput"
                               placeholder="ابحث برقم السند أو اسم الحساب..."
                               autocomplete="off"
                               autofocus>
                    </div>
                    <div class="col-md-2">
                        <button type="button"
                                class="btn btn-outline-secondary w-100"
                                id="ReceiptVoucherClearBtn">
                            <i class="bi bi-x-lg"></i>
                            تفريغ
                        </button>
                    </div>
                </div>

                <!-- جدول النتائج -->
                <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                    <table class="table table-bordered table-hover align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr class="text-center">
                                <th style="width: 12%;">رقم السند</th>
                                <th style="width: 10%;">التاريخ</th>
                                <th>الحساب الدائن (العميل)</th>
                                <th>الحساب المدين (الصندوق/البنك)</th>
                                <th style="width: 12%;">المبلغ</th>
                                <th style="width: 8%;">العملة</th>
                                <th style="width: 10%;">طريقة الدفع</th>
                            </tr>
                        </thead>
                        <tbody id="ReceiptVoucherSearchResults">
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    ابحث عن سند قبض
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>
<!-- ===================================================== -->
<!-- نافذة البحث عن فاتورة بيع -->
<!-- ===================================================== -->

<div
    class="modal fade"
    id="salesInvoiceSearchModal"
    tabindex="-1"
    aria-labelledby="salesInvoiceSearchModalLabel"
    aria-hidden="true"
>

    <div class="modal-dialog modal-xl modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">

                <h5
                    class="modal-title"
                    id="salesInvoiceSearchModalLabel"
                >
                    <i class="bi bi-search"></i>
                    البحث عن فاتورة بيع
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    style="margin-right: auto; margin-left: 0;"
                    data-bs-dismiss="modal"
                    aria-label="إغلاق"
                ></button>

            </div>


            <div class="modal-body">

                <div class="row g-2 mb-3">

                    <div class="col-md-10">

                        <input
                            type="text"
                            class="form-control"
                            id="salesInvoiceSearchInput"
                            placeholder="أدخل رقم الفاتورة أو اسم العميل..."
                            onkeydown="if(event.key==='Enter') performSalesInvoiceSearch()"
                        >

                    </div>

                    <div class="col-md-2">

                        <button
                            type="button"
                            class="btn btn-primary w-100"
                            onclick="performSalesInvoiceSearch()"
                        >
                            <i class="bi bi-search"></i>
                            بحث
                        </button>

                    </div>

                </div>


                <div class="table-responsive" style="max-height: 450px;">

                    <table class="table table-bordered table-hover align-middle mb-0">

                        <thead class="table-light sticky-top">

                            <tr class="text-center">

                                <th>رقم الفاتورة</th>
                                <th>التاريخ</th>
                                <th>العميل</th>
                                <th>العملة</th>
                                <th>طريقة الدفع</th>
                                <th>الإجمالي</th>
                                <th>الحالة</th>
                                <th style="min-width: 180px;">إجراءات</th>

                            </tr>

                        </thead>

                        <tbody id="salesInvoiceSearchResults">

                            <tr>

                                <td
                                    colspan="8"
                                    class="text-center text-muted py-4"
                                >
                                    <span class="spinner-border spinner-border-sm me-2"></span>
                                    جاري التحميل...
                                </td>

                            </tr>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>

</div>
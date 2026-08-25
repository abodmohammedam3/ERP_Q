 <!-- ===================================== -->
    <!-- تفاصيل الفاتورة -->
    <!-- ===================================== -->

    <div class="card mb-3">

        <div class="card-header d-flex justify-content-between align-items-center">

            <strong>
                تفاصيل الأصناف
            </strong>

            <div class="btn-group">

                <button
                    type="button"
                    class="btn btn-sm btn-success"
                    id="btnAddSalesItem">

                    <i class="bi bi-plus-lg"></i>
                    إضافة صنف

                </button>
                <button
                    type="button"
                    class="btn btn-sm btn-outline-secondary"
                    id="btnClearSalesItems">

                    <i class="bi bi-trash"></i>
                    تفريغ التفاصيل

                </button>

            </div>

        </div>


        <div class="card-body p-0">

            <div class="table-responsive">

                <table
                    class="table table-bordered table-hover align-middle mb-0">

                    <thead class="table-light">

                        <tr>

                            <th>#</th>

                            <th>الصنف</th>

                            <th>النوع</th>

                            <th>المخزن</th>

                            <th>الكمية</th>

                            <th>تكلفة الصنف</th>

                            <th>سعر البيع</th>

                            <th>إجمالي السطر</th>

                            <th>الخصم</th>

                            <th>الإجمالي</th>

                            <th>الإجراءات</th>

                        </tr>

                    </thead>


                    <tbody id="salesInvoiceDetails">

                        <tr>

                            <td
                                colspan="11"
                                class="text-center text-muted py-4">

                                لا توجد أصناف مضافة إلى الفاتورة

                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>

        </div>

    </div>


    <!-- ===================================== -->
    <!-- ملخص الفاتورة -->
    <!-- ===================================== -->

    <div class="row g-3">

        <div class="col-lg-8">

            <div class="card">

                <div class="card-header">

                    <strong>
                        معلومات العملية
                    </strong>

                </div>

                <div class="card-body">

                    <div class="row g-3">

                        <div class="col-md-6">

                            <label class="form-label">
                                عدد الأصناف
                            </label>

                            <input
                                type="number"
                                class="form-control"
                                id="totalItems"
                                readonly>

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                إجمالي الكمية
                            </label>

                            <input
                                type="number"
                                class="form-control"
                                id="totalQuantity"
                                readonly>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- إجماليات البيع -->

        <div class="col-lg-4">

            <div class="card">

                <div class="card-header">

                    <strong>
                        إجماليات الفاتورة
                    </strong>

                </div>

                <div class="card-body">


                    <!-- SalesTotalAmount3 -->

                    <div class="mb-3">

                        <label
                            for="SalesTotalAmount3"
                            class="form-label">

                            إجمالي الفاتورة

                        </label>

                        <input
                            type="number"
                            step="0.000001"
                            class="form-control"
                            id="SalesTotalAmount3"
                            name="SalesTotalAmount3"
                            readonly>

                    </div>


                    <!-- SalesDiscount3 -->

                    <div class="mb-3">
                        <label
                            for="SalesDiscount3"
                            class="form-label">

                            الخصم

                        </label>

                        <input
                            type="number"
                            step="0.000001"
                            class="form-control"
                            id="SalesDiscount3"
                            name="SalesDiscount3">

                    </div>


                    <!-- SalesNetAmount3 -->

                    <div class="mb-3">

                        <label
                            for="SalesNetAmount3"
                            class="form-label">

                            صافي الفاتورة

                        </label>

                        <input
                            type="number"
                            step="0.000001"
                            class="form-control"
                            id="SalesNetAmount3"
                            name="SalesNetAmount3"
                            readonly>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- ===================================== -->
    <!-- أزرار الحفظ -->
    <!-- ===================================== -->

    <div class="d-flex flex-wrap justify-content-end gap-2 mt-3">

        <button
            type="button"
            class="btn btn-success"
            id="btnSaveSalesInvoice">

            <i class="bi bi-check-lg"></i>
            حفظ

        </button>


        <button
            type="button"
            class="btn btn-success"
            id="btnSaveNewSalesInvoice">

            <i class="bi bi-check2-all"></i>
            حفظ وإضافة جديد

        </button>


        <button
            type="button"
            class="btn btn-secondary"
            id="btnCancelSalesInvoice">

            <i class="bi bi-x-lg"></i>
            إلغاء

        </button>

    </div>
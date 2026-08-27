 <!-- ========================= -->
    <!-- تفاصيل الفاتورة -->
    <!-- ========================= -->

    <div class="card mb-3">

        <div class="card-header d-flex justify-content-between align-items-center">

            <strong>تفاصيل الأصناف</strong>

            <button
                type="button"
                class="btn btn-sm btn-success"
            >
                <i class="bi bi-plus-lg"></i>
                إضافة صنف
            </button>

        </div>


        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table table-bordered table-hover align-middle mb-0">

                    <thead class="table-light">

                        <tr  class="text-center">

                            <th>الرقم</th>

                            <th>الصنف</th>

                            <th>النوع</th>

                            <th>الرمز</th>

                            <th>المخزن</th>

                            <th>الوزن</th>

                            <th>الكمية</th>

                            <th>سعر الوحدة</th>

                            <th>السعر</th>

                            <th>الخصم</th>

                            <th>الإجمالي</th>

                            <th>إجراء</th>

                        </tr>

                    </thead>


                    <tbody id="purchaseInvoiceDetails">

                        <!--
                            سيتم إنشاء صفوف التفاصيل ديناميكيًا
                            بواسطة JavaScript لاحقًا
                        -->

                        <tr>

                            <td colspan="11" class="text-center text-muted py-4">
                                لا توجد أصناف مضافة إلى الفاتورة
                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>

        </div>

    </div>


    <!-- ========================= -->
    <!-- إجماليات الفاتورة -->
    <!-- ========================= -->

    <div class="row g-3">

        <div class="col-lg-8">
            <!-- التكاليف -->
            <div class="card">

                <div class="card-header">
                    <strong>التكاليف الإضافية</strong>
                </div>

                <div class="card-body">

                    <div class="row g-3">

                        <!-- PuInExpenses -->
                        <div class="col-md-6">

                            <label for="PuInExpenses" class="form-label">
                                المصروفات
                            </label>

                            <input
                                type="number"
                                step="0.000001"
                                class="form-control"
                                id="PuInExpenses"
                                name="PuInExpenses"
                            >

                        </div>


                        <!-- PuInTransportation -->
                        <div class="col-md-6">

                            <label for="PuInTransportation" class="form-label">
                                النقل
                            </label>

                            <input
                                type="number"
                                step="0.000001"
                                class="form-control"
                                id="PuInTransportation"
                                name="PuInTransportation"
                            >

                        </div>


                        <!-- PuInTaxCost -->
                        <div class="col-md-6">

                            <label for="PuInTaxCost" class="form-label">
                                الضريبة
                            </label>

                            <input
                                type="number"
                                step="0.000001"
                                class="form-control"
                                id="PuInTaxCost"
                                name="PuInTaxCost"
                            >

                        </div>


                        <!-- PuInOtherCost -->
                        <div class="col-md-6">

                            <label for="PuInOtherCost" class="form-label">
                                تكاليف أخرى
                            </label>

                            <input
                                type="number"
                                step="0.000001"
                                class="form-control"
                                id="PuInOtherCost"
                                name="PuInOtherCost"
                            >

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- الإجماليات -->
        <div class="col-lg-4">

            <div class="card">

                <div class="card-header">
                    <strong>ملخص الفاتورة</strong>
                </div>

                <div class="card-body">

                    <!-- PuInTptalPrice2 -->
                    <div class="mb-3">

                        <label for="PuInTptalPrice2" class="form-label">
                            إجمالي الفاتورة
                        </label>

                        <input
                            type="number"
                            step="0.000001"
                            class="form-control"
                            id="PuInTptalPrice2"
                            name="PuInTptalPrice2"
                            readonly
                        >

                    </div>


                    <!-- PuInDIscount2 -->
                    <div class="mb-3">

                        <label for="PuInDIscount2" class="form-label">
                            الخصم
                        </label>
                        <input
                            type="number"
                            step="0.000001"
                            class="form-control"
                            id="PuInDIscount2"
                            name="PuInDIscount2"
                        >

                    </div>


                    <!-- PuInTotalAfterDiscount2 -->
                    <div class="mb-3">

                        <label for="PuInTotalAfterDiscount2" class="form-label">
                            الإجمالي بعد الخصم
                        </label>

                        <input
                            type="number"
                            step="0.000001"
                            class="form-control"
                            id="PuInTotalAfterDiscount2"
                            name="PuInTotalAfterDiscount2"
                            readonly
                        >

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- ========================= -->
    <!-- أزرار العملية -->
    <!-- ========================= -->

    <div class="d-flex justify-content-end gap-2 mt-3">

        <button
            type="button"
            class="btn btn-primary"
        >
            حفظ الفاتورة
        </button>

        <button
            type="button"
            class="btn btn-secondary"
        >
            إلغاء
        </button>

    </div>

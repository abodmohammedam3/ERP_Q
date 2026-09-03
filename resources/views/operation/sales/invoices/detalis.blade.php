<!-- ========================= -->
<!-- تفاصيل الفاتورة -->
<!-- ========================= -->

<div class="card mb-3">

    <div class="card-header d-flex justify-content-between align-items-center">

        <strong>
            <i class="bi bi-box-seam"></i>
            تفاصيل الأصناف
        </strong>

        <button
            type="button"
            class="btn btn-sm btn-success"
            id="btnAddSalesRow"
            onclick="addSalesRow()"
            disabled
        >

            <i class="bi bi-plus-lg"></i>
            إضافة صنف

        </button>

    </div>


    <div class="card-body p-0">

        <table
            class="table table-bordered table-hover align-middle text-center mb-0"
        >

            <thead class="table-light">

                <tr>

                    <!-- 1 -->

                    <th>
                        الرقم
                    </th>

                    <!-- 2 -->

                    <th>
                        الصنف
                    </th>

                    <!-- 3 -->

                    <th>
                        النوع
                    </th>

                    <!-- 4 -->

                    <th>
                        الرمز
                    </th>

                    <!-- 5 -->

                    <th>
                        الوحدة
                    </th>

                    <!-- 6 -->

                    <th>
                        سعر الوحدة
                    </th>

                    <!-- 7 -->
                    <th>
                        المخزن
                    </th>
                    <!-- 8 -->
                      <!-- id="salesQuantityWeightHeader" -->
                    <th>
                        العدد 
                    </th>
                    <!-- 9 -->
                    <th>
                        الخصم
                    </th>
                    <!-- 10 -->
                    <th class="row-total">  
                        إجمالي السطر
                    </th>

                    
                    <!-- الإجراءات -->

                    <th>
                        إجراء
                    </th>

                </tr>

            </thead>


            <tbody id="salesInvoiceDetails">

                <tr>

                    <td
                        colspan="11"
                        class="text-center text-muted py-4"
                    >
                        لا توجد أصناف مضافة إلى الفاتورة
                    </td>

                </tr>

            </tbody>


            <!-- ========================= -->
            <!-- الإجماليات -->
            <!-- ========================= -->

            <tfoot>

                <tr>

                    <td
                        colspan="11"
                        class="p-3"
                    >

                        <div class="d-flex justify-content-start">

                            <div class="border rounded p-3">

                                <div class="d-flex align-items-center gap-4">

                                    <strong class="text-danger">
                                        إجمالي الخصم:
                                        <span
                                            id="totalSalesDiscountDisplay"
                                        >
                                            0.00
                                        </span>
                                    </strong>


                                    <strong class="text-success">
                                        إجمالي الفاتورة:
                                        <span 
                                            id="salesInvoiceTotalDisplay"
                                        >
                                            0.00
                                        </span>
                                    </strong>

                                </div>

                            </div>

                        </div>

                    </td>

                </tr>

            </tfoot>

        </table>

    </div>

</div>


<!-- ===================================================== -->
<!-- البيانات الإضافية -->
<!-- ===================================================== -->

<div class="card mb-3">

    <div class="card-header">

        <strong>
            <i class="bi bi-card-text"></i>
            بيانات إضافية
        </strong>

    </div>


    <div class="card-body">

        <div class="row g-3">


            <!-- البيان -->

            <div class="col-md-6">

                <label
                    for="SalesStatement"
                    class="form-label"
                >
                    البيان
                </label>

                <textarea
                    class="form-control"
                    id="SalesStatement"
                    name="SalesStatement"
                    rows="2"
                    disabled
                ></textarea>

            </div>


            <!-- المرجع -->

            <div class="col-md-6">

                <label
                    for="SalesReference"
                    class="form-label"
                >
                    المرجع
                </label>

                <input
                    type="text"
                    class="form-control"
                    id="SalesReference"
                    name="SalesReference"
                    placeholder="المرجع"
                    disabled
                >

            </div>


        </div>

    </div>

</div>


<!-- ========================= -->
<!-- أزرار العملية -->
<!-- ========================= -->

<div class="d-flex justify-content-end gap-2 mt-3 flex-wrap">


    <!-- حفظ -->

    <button
        type="button"
        class="btn btn-success"
        id="btnSaveSalesInvoice"
        onclick="saveSalesInvoice()"
        disabled
    >

        <i class="bi bi-save"></i>
        حفظ

    </button>


    <!-- حفظ وإضافة فاتورة -->

    <button
        type="button"
        class="btn btn-primary"
        id="btnSaveAndNewSalesInvoice"
        onclick="saveAndNewSalesInvoice()"
        disabled
    >

        <i class="bi bi-save2"></i>
        حفظ وإضافة فاتورة

    </button>


    <!-- تعديل -->

    <button
        type="button"
        class="btn btn-warning"
        id="btnEditSalesInvoice"
        onclick="editSalesInvoice()"
        disabled
    >

        <i class="bi bi-pencil-square"></i>
        تعديل

    </button>

    <button
        type="button"
        class="btn btn-primary"
        id="btnprintButton"
        onclick="printButton()"
        disabled
    >

        <i class="printer"></i>
        طباعة

    </button>


    <!-- إلغاء -->

    <button
        type="button"
        class="btn btn-secondary d-none"
        id="btnCancelSalesInvoice"
        onclick="cancelSalesInvoice()"
    >

        <i class="bi bi-x-lg"></i>
        إلغاء

    </button>


</div>
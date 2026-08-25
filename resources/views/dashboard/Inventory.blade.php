 <!-- ========================================= -->
    <!-- ملخص المخزون والحركة -->
    <!-- ========================================= -->

    <div class="row g-3 mb-4">

        <!-- المخازن -->

        <div class="col-12 col-lg-4">

            <div class="card h-100">

                <div class="card-header">

                    <strong>
                        المخزون
                    </strong>

                </div>

                <div class="card-body">

                    <div class="row g-3">

                        <div class="col-6">

                            <div class="border rounded p-3">

                                <div class="text-muted small">
                                    المخازن
                                </div>

                                <div
                                    class="fs-4 fw-bold"
                                    id="stocksCount">

                                    0

                                </div>

                            </div>

                        </div>


                        <div class="col-6">

                            <div class="border rounded p-3">

                                <div class="text-muted small">
                                    الأصناف
                                </div>

                                <div
                                    class="fs-4 fw-bold"
                                    id="itemsCount">

                                    0

                                </div>

                            </div>

                        </div>


                        <div class="col-6">

                            <div class="border rounded p-3">

                                <div class="text-muted small">
                                    الأنواع
                                </div>

                                <div
                                    class="fs-4 fw-bold"
                                    id="typesCount">

                                    0

                                </div>

                            </div>

                        </div>


                        <div class="col-6">

                            <div class="border rounded p-3">

                                <div class="text-muted small">
                                    الوحدات
                                </div>

                                <div
                                    class="fs-4 fw-bold"
                                    id="unitsCount">

                                    0

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- حركة المخزون -->

        <div class="col-12 col-lg-8">

            <div class="card h-100">

                <div class="card-header d-flex justify-content-between align-items-center">

                    <strong>
                        آخر حركات المخزون
                    </strong>

                    <span class="badge text-bg-secondary">
                        آخر الحركات
                    </span>                    
    </div>


                <div class="card-body p-0">

                    <div class="table-responsive">

                        <table
                            class="table table-bordered table-hover align-middle mb-0">

                            <thead class="table-light">

                                <tr>

                                    <th>التاريخ</th>

                                    <th>نوع الحركة</th>

                                    <th>البيان</th>

                                    <th>الصنف</th>

                                    <th>الكمية</th>

                                    <th>التكلفة</th>

                                </tr>

                            </thead>


                            <tbody id="dashboardStockMovements">

                                <tr>

                                    <td
                                        colspan="6"
                                        class="text-center text-muted py-4">

                                        لا توجد حركات مخزون

                                    </td>

                                </tr>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>

    </div>
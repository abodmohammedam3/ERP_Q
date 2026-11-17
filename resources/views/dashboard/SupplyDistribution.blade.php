 <!-- ========================================= -->
    <!-- التوريد و التوزيع -->
    <!-- ========================================= -->

    <div class="row g-3 mb-4">

        <!-- آخر فواتير البيع -->

        <div class="col-12 col-xl-6">

            <div class="card h-100">

                <div class="card-header">

                    <strong>
                        التوريد 
                    </strong>

                </div>


                <div class="card-body p-0">

                    <div class="table-responsive">

                        <table
                            class="table table-bordered table-hover align-middle mb-0">

                            <thead class="table-light">

                                <tr  class="text-center">

                                    <th>رقم الحركة</th>

                                    <th>الرعوي</th>

                                    <th>التاريخ</th>

                                    <th>اليوم</th>

                                    <th>الصنف</th>

                                    <th>النوع</th>

                                    <th>الرمز</th>

                                    <th>الوحدة</th>

                                    <th>الكمية</th>

                                    <th>سعرالبيع</th>

                                </tr>

                            </thead>


                            <tbody id="dashboardSalesInvoices">

                                <tr>

                                    <td
                                        colspan="10"
                                        class="text-center text-muted py-4">

                                        جاري تحميل البيانات...

                                    </td>

                                </tr>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>


            <!-- التوزيع-->

            <div class="col-12 col-xl-6">

                <div class="card h-100">

                    <div class="card-header">

                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">

                            <strong>
                                التوزيع
                            </strong>

                            {{-- الرأس: اليوم + التاريخ + اسم العميل --}}
                            <div class="d-flex align-items-center gap-2 flex-wrap">

                                <button type="button"
                                        class="btn btn-sm btn-outline-secondary"
                                        id="distDayPrevBtn"
                                        title="اليوم السابق">
                                    <i class="bi bi-chevron-right"></i>
                                </button>

                                <span class="badge text-bg-light text-dark border" id="distDayDate">—</span>
                                <span class="badge text-bg-secondary" id="distDayName">—</span>

                                <button type="button"
                                        class="btn btn-sm btn-outline-secondary"
                                        id="distDayNextBtn"
                                        title="اليوم التالي">
                                    <i class="bi bi-chevron-left"></i>
                                </button>

                            </div>

                        </div>

                        {{-- سطر اسم العميل + السهمان بين عملاء اليوم --}}
                        <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">

                            <div class="d-flex align-items-center gap-1">

                                <button type="button"
                                        class="btn btn-sm btn-outline-primary"
                                        id="distCustomerPrevBtn"
                                        title="العميل السابق">
                                    <i class="bi bi-arrow-right"></i>
                                </button>

                                <span class="fw-semibold" id="distCustomerName">—</span>

                                <button type="button"
                                        class="btn btn-sm btn-outline-primary"
                                        id="distCustomerNextBtn"
                                        title="العميل التالي">
                                    <i class="bi bi-arrow-left"></i>
                                </button>

                            </div>

                            {{-- الفلتر: عميل + تاريخ --}}
                            <div class="d-flex gap-1 align-items-center">

                                <select id="distCustomerFilter"
                                        class="form-select form-select-sm"
                                        style="max-width: 180px;">
                                    <option value="">كل العملاء</option>
                                </select>

                                <input type="date"
                                       id="distDateFilter"
                                       class="form-control form-control-sm"
                                       style="max-width: 150px;">

                                <button type="button"
                                        class="btn btn-sm btn-outline-secondary"
                                        id="distDateTodayBtn"
                                        title="اليوم">
                                    <i class="bi bi-calendar-check"></i>
                                </button>

                            </div>

                        </div>

                    </div>


                    <div class="card-body p-0">

                        <div class="table-responsive">

                            <table
                                class="table table-bordered table-hover align-middle mb-0">

                                <thead class="table-light">

                                    <tr  class="text-center">

                                        <th>الكمية</th>

                                        <th>الرمز</th>

                                        <th>سعر الحبة</th>

                                        <th>الاجمالي</th>

                                        <th>النوع</th>

                                    </tr>

                                </thead>


                                <tbody id="dashboardDistributionDaily">

                                    <tr>
                                        <td
                                            colspan="5"
                                            class="text-center text-muted py-4">

                                            جاري تحميل البيانات...
                                        </td>
                                    </tr>

                                </tbody>

                                <tfoot class="table-light fw-semibold">

                                    <tr class="text-center">

                                        <td colspan="3" class="text-start ps-3">
                                            الإجمالي
                                            <small class="text-muted d-block">
                                                بالريال السعودي: <span id="distTotalSar">0.00</span>
                                                <span id="distSarRate" class="d-none"></span>
                                            </small>
                                        </td>

                                        <td id="distTotalAmount">0.00</td>

                                        <td>
                                            الوزن كم: <span id="distTotalKg">0.000</span>
                                            <span id="distKgUnknown" class="d-none text-warning small">(بعض الصفوف بلا وزن)</span>
                                        </td>

                                    </tr>

                                </tfoot>

                            </table>

                        </div>

                    </div>

                </div>

            </div>

        </div>
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

                                {{-- بحث بالكتابة مع قائمة اقتراحات (تُملأ من JS) --}}
                                <div class="position-relative" style="max-width: 200px;">
                                    <input type="text"
                                           id="distCustomerFilter"
                                           class="form-control form-control-sm"
                                           placeholder="بحث باسم العميل..."
                                           autocomplete="off">
                                    <div id="distCustomerSuggestions"
                                         class="list-group position-absolute w-100 shadow-sm d-none"
                                         style="top: 100%; right: 0; z-index: 1050; max-height: 220px; overflow-y: auto;">
                                    </div>
                                </div>

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

                                {{-- تذييل بلا خطوط أعمدة: tfoot::td بلا حد جانبي، والجدول تُحجب حدوده السفلية --}}
                                <tfoot class="table-light fw-semibold tfoot-no-borders">

                                    <tr class="text-center">

                                        {{-- العداد: إجمالي الكميات (أسفل عمود الكمية فقط) --}}
                                        <td class="text-start ps-3">
                                            العداد:
                                            <span id="distTotalQty">0</span>
                                        </td>

                                        <td></td>

                                        {{-- قديم أعلى "بالريال السعودي" في نفس الحقل --}}
                                        <td>
                                            قديم: <span id="distTotalAmount">0.00</span>
                                            <small class="text-muted d-block">
                                                بالريال السعودي: <span id="distTotalSar">0.00</span>
                                                <span id="distSarRate" class="d-none"></span>
                                            </small>
                                        </td>

                                        {{-- الوزن أسفل عمود النوع (العمود الأخير) --}}
                                        <td>
                                            الوزن: <span id="distTotalKg">0.000</span> كيلو
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

    </div>

</div>

{{-- إخفاء الحدود العمودية (الفواصل الرأسية بين الأعمدة) في تذييل جدول التوزيع
    اليومي — تبقى حدود الصفوف فقط. موضوعة هنا مباشرة لأن layouts/app.blade.php
    لا يحتوي على @stack('styles') فأي @push('styles') لن يُعرض. --}}
<style>
    #dashboardDistributionDaily tfoot td,
    tfoot.tfoot-no-borders td {
        border-left: 0 !important;
        border-right: 0 !important;
    }
</style>

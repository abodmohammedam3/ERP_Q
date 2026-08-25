@extends('layouts.app')

@section('title', 'لوحة التحكم ')

@section('content')
<div class="py-3">

    <!-- ========================================= -->
    <!-- عنوان لوحة التحكم -->
    <!-- ========================================= -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h4 class="mb-1">
                لوحة التحكم
            </h4>

            <p class="text-muted mb-0">
                ملخص العمليات والحركة الحالية في النظام
            </p>

        </div>

        <div>

            <span class="badge text-bg-secondary">

                <i class="bi bi-calendar3"></i>

                <span id="dashboardDate">
                    اليوم
                </span>

            </span>

        </div>

    </div>


    <!-- ========================================= -->
    <!-- مؤشرات النظام -->
    <!-- ========================================= -->

    <div class="row g-3 mb-4">

        <!-- المبيعات -->

        <div class="col-12 col-sm-6 col-xl-3">

            <div class="card h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-start">

                        <div>

                            <h6 class="text-muted mb-2">
                                فواتير البيع
                            </h6>

                            <h3
                                class="mb-0"
                                id="salesInvoicesCount">

                                0

                            </h3>

                        </div>

                        <span class="fs-3 text-success">

                            <i class="bi bi-cart-check"></i>

                        </span>

                    </div>

                    <small class="text-muted">
                        إجمالي فواتير البيع
                    </small>

                </div>

            </div>

        </div>


        <!-- المشتريات -->

        <div class="col-12 col-sm-6 col-xl-3">

            <div class="card h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-start">

                        <div>

                            <h6 class="text-muted mb-2">
                                فواتير الشراء
                            </h6>

                            <h3
                                class="mb-0"
                                id="purchaseInvoicesCount">

                                0

                            </h3>

                        </div>

                        <span class="fs-3 text-primary">

                            <i class="bi bi-bag-check"></i>

                        </span>

                    </div>

                    <small class="text-muted">
                        إجمالي فواتير الشراء
                    </small>

                </div>

            </div>

        </div>


        <!-- العملاء -->

        <div class="col-12 col-sm-6 col-xl-3">

            <div class="card h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-start">

                        <div>

                            <h6 class="text-muted mb-2">
                                العملاء
                            </h6>

                            <h3
                                class="mb-0"
                                id="customersCount">

                                0

                            </h3>

                        </div>

                        <span class="fs-3 text-info">

                            <i class="bi bi-people"></i>

                        </span>

                    </div>

                    <small class="text-muted">
                        إجمالي العملاء
                    </small>

                </div>

            </div>

        </div>


        <!-- الموردون -->

        <div class="col-12 col-sm-6 col-xl-3">

            <div class="card h-100">

                <div class="card-body">
<div class="d-flex justify-content-between align-items-start">

                        <div>

                            <h6 class="text-muted mb-2">
                                الموردون
                            </h6>

                            <h3
                                class="mb-0"
                                id="suppliersCount">

                                0

                            </h3>

                        </div>

                        <span class="fs-3 text-warning">

                            <i class="bi bi-person-vcard"></i>

                        </span>

                    </div>

                    <small class="text-muted">
                        إجمالي الموردين
                    </small>

                </div>

            </div>

        </div>

    </div>


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

                                <tr>

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
                                        colspan="5"
                                        class="text-center text-muted py-4">

                                        بيانات 

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

                    <strong>
                        التوزيع
                    </strong>

                </div>


                <div class="card-body p-0">

                    <div class="table-responsive">

                        <table
                            class="table table-bordered table-hover align-middle mb-0">

                            <thead class="table-light">

                                <tr>

                                    <th> التاجر</th>

                                    <th>اسم التاجر</th>

                                    <th>الحركة</th>

                                    <th>النوع</th>

                                    <th>الوحدة</th>

                                    <th>الكمية</th>

                                    <th>السعر</th>

                                    <th>الاجمالي</th>

                                </tr>

                            </thead>


                            <tbody id="dashboardPurchaseInvoices">

                                <tr>
                                    <td
                                        colspan="5"
                                        class="text-center text-muted py-4">

                                        بيانات

                                    </td>

                                </tr>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- ========================================= -->
    <!-- ملخص الحسابات -->
    <!-- ========================================= -->

    <div class="row g-3 mb-4">

        <!-- المبيعات -->

        <div class="col-12 col-md-4">

            <div class="card">

                <div class="card-body">

                    <h6 class="text-muted">
                        إجمالي المبيعات
                    </h6>

                    <h4
                        id="dashboardSalesTotal"
                        class="mb-0">

                        0

                    </h4>

                </div>

            </div>

        </div>


        <!-- المشتريات -->

        <div class="col-12 col-md-4">

            <div class="card">

                <div class="card-body">

                    <h6 class="text-muted">
                        إجمالي المشتريات
                    </h6>

                    <h4
                        id="dashboardPurchaseTotal"
                        class="mb-0">

                        0

                    </h4>

                </div>

            </div>

        </div>


        <!-- حركة المخزون -->

        <div class="col-12 col-md-4">

            <div class="card">

                <div class="card-body">

                    <h6 class="text-muted">
                        حركات المخزون
                    </h6>

                    <h4
                        id="dashboardStockMovementsCount"
                        class="mb-0">

                        0

                    </h4>

                </div>

            </div>

        </div>

    </div>


    <!-- ========================================= -->
    <!-- تنبيهات النظام -->
    <!-- ========================================= -->

    <div class="card">

        <div class="card-header">

            <strong>
                تنبيهات النظام
            </strong>

        </div>

        <div class="card-body">

            <div
                id="dashboardAlerts"
                class="list-group list-group-flush">

                <div class="list-group-item text-muted">

                    لا توجد تنبيهات حالياً

                </div>

            </div>

        </div>

    </div>

</div>                
@endsection

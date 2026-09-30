@extends('layouts.app')

@section('title', 'مرتجعات المشتريات | نظام ERP')

@section('content')

<div class="container-fluid py-3">

    <!-- عنوان الشاشة والأزرار -->
    <div class="d-flex justify-content-between align-items-center mb-3">

        <div>
            <h4 class="mb-1 text-warning">
                <i class="bi bi-arrow-return-right me-2"></i>
                مرتجع شراء
            </h4>
            <small class="text-muted">إدارة فواتير مرتجعات المشتريات للموردين</small>
        </div>

        <div class="btn-group" role="group">

            <button
                type="button"
                class="btn btn-warning text-dark fw-bold"
                id="btnNewPurchaseReturn"
                onclick="resetPurchaseReturn()"
            >
                <i class="bi bi-plus-lg"></i>
                مرتجع جديد
            </button>

            <button
                type="button"
                class="btn btn-outline-secondary"
                id="btnSearchPurchaseReturn"
                onclick="searchPurchaseReturn()"
            >
                <i class="bi bi-search"></i>
                بحث في المرتجعات
            </button>

        </div>

    </div>

    <!-- رأس المرتجع -->
    @include('operation.purchases.returns.header')

    <!-- تفاصيل المرتجع -->
    @include('operation.purchases.returns.details')

</div>


<!-- ===================================================== -->
<!-- نافذة البحث عن فاتورة شراء أصلية -->
<!-- ===================================================== -->
<div class="modal fade" id="originalPurchaseInvoiceSearchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-search me-2"></i>
                    البحث عن فاتورة شراء أصلية
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <div class="row g-2 mb-3">
                    <div class="col-md-10">
                        <input
                            type="text"
                            class="form-control"
                            id="originalPurchaseInvoiceSearchInput"
                            placeholder="أدخل رقم الفاتورة أو اسم المورد..."
                            onkeydown="if(event.key==='Enter') performOriginalPurchaseInvoiceSearch()"
                        >
                    </div>
                    <div class="col-md-2">
                        <button
                            type="button"
                            class="btn btn-primary w-100"
                            onclick="performOriginalPurchaseInvoiceSearch()"
                        >
                            <i class="bi bi-search"></i>
                            بحث
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light">
                            <tr class="text-center">
                                <th>رقم الفاتورة</th>
                                <th>التاريخ</th>
                                <th>المورد</th>
                                <th>العملة</th>
                                <th>طريقة الدفع</th>
                                <th>الإجمالي</th>
                                <th>اختيار</th>
                            </tr>
                        </thead>
                        <tbody id="originalPurchaseInvoiceSearchResults">
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    أدخل بيانات البحث ثم اضغط بحث
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>


<!-- ===================================================== -->
<!-- نافذة البحث عن مرتجع شراء -->
<!-- ===================================================== -->
<div class="modal fade" id="purchaseReturnSearchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title text-warning">
                    <i class="bi bi-search me-2"></i>
                    البحث عن مرتجع شراء
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <div class="row g-2 mb-3">
                    <div class="col-md-10">
                        <input
                            type="text"
                            class="form-control"
                            id="purchaseReturnSearchInput"
                            placeholder="أدخل رقم المرتجع أو رقم الفاتورة أو اسم المورد..."
                            onkeydown="if(event.key==='Enter') performPurchaseReturnSearch()"
                        >
                    </div>
                    <div class="col-md-2">
                        <button
                            type="button"
                            class="btn btn-warning w-100"
                            onclick="performPurchaseReturnSearch()"
                        >
                            <i class="bi bi-search"></i>
                            بحث
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light">
                            <tr class="text-center">
                                <th>رقم المرتجع</th>
                                <th>التاريخ</th>
                                <th>الفاتورة الأصلية</th>
                                <th>المورد</th>
                                <th>طريقة الدفع</th>
                                <th>الإجمالي</th>
                                <th>عرض</th>
                            </tr>
                        </thead>
                        <tbody id="purchaseReturnSearchResults">
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    أدخل بيانات البحث ثم اضغط بحث
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
    @vite(['resources/js/pages/purchase-return.js'])
@endpush

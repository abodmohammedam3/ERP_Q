@extends('layouts.app')

@section('title', 'مرتجعات المبيعات | نظام ERP')

@section('content')

<div class="container-fluid py-3">

    <!-- عنوان الشاشة والأزرار -->
    <div class="d-flex justify-content-between align-items-center mb-3">

        <div>
            <h4 class="mb-1 text-danger">
                <i class="bi bi-arrow-return-left me-2"></i>
                مرتجع بيع
            </h4>
            <small class="text-muted">إدارة فواتير مرتجعات المبيعات للعملاء</small>
        </div>

        <div class="btn-group" role="group">

            <button
                type="button"
                class="btn btn-primary"
                id="btnNewSalesReturn"
                onclick="resetSalesReturn()"
            >
                <i class="bi bi-plus-lg"></i>
                مرتجع جديد
            </button>

            <button
                type="button"
                class="btn btn-outline-secondary"
                id="btnSearchSalesReturn"
                onclick="searchSalesReturn()"
            >
                <i class="bi bi-search"></i>
                بحث في المرتجعات
            </button>

        </div>

    </div>

    <!-- رأس المرتجع -->
    @include('operation.sales.returns.header')

    <!-- تفاصيل المرتجع -->
    @include('operation.sales.returns.details')

</div>


<!-- ===================================================== -->
<!-- نافذة البحث عن فاتورة بيع أصلية -->
<!-- ===================================================== -->
<div class="modal fade" id="originalInvoiceSearchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-search me-2"></i>
                    البحث عن فاتورة بيع أصلية
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <div class="row g-2 mb-3">
                    <div class="col-md-10">
                        <input
                            type="text"
                            class="form-control"
                            id="originalInvoiceSearchInput"
                            placeholder="أدخل رقم الفاتورة أو اسم العميل..."
                            onkeydown="if(event.key==='Enter') performOriginalInvoiceSearch()"
                        >
                    </div>
                    <div class="col-md-2">
                        <button
                            type="button"
                            class="btn btn-primary w-100"
                            onclick="performOriginalInvoiceSearch()"
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
                                <th>العميل</th>
                                <th>العملة</th>
                                <th>طريقة الدفع</th>
                                <th>الإجمالي</th>
                                <th>اختيار</th>
                            </tr>
                        </thead>
                        <tbody id="originalInvoiceSearchResults">
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
<!-- نافذة البحث عن مرتجع بيع -->
<!-- ===================================================== -->
<div class="modal fade" id="salesReturnSearchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title text-danger">
                    <i class="bi bi-search me-2"></i>
                    البحث عن مرتجع بيع
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <div class="row g-2 mb-3">
                    <div class="col-md-10">
                        <input
                            type="text"
                            class="form-control"
                            id="salesReturnSearchInput"
                            placeholder="أدخل رقم المرتجع أو رقم الفاتورة أو اسم العميل..."
                            onkeydown="if(event.key==='Enter') performSalesReturnSearch()"
                        >
                    </div>
                    <div class="col-md-2">
                        <button
                            type="button"
                            class="btn btn-danger w-100"
                            onclick="performSalesReturnSearch()"
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
                                <th>العميل</th>
                                <th>طريقة الدفع</th>
                                <th>الإجمالي</th>
                                <th>عرض</th>
                            </tr>
                        </thead>
                        <tbody id="salesReturnSearchResults">
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
    @vite(['resources/js/pages/sales-return.js'])
@endpush

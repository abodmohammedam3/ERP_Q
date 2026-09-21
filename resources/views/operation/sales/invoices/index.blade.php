@extends('layouts.app')

@section('title', 'فواتير البيع | نظام ERP')

@section('content')

<div class="container-fluid py-3">

    <!-- عنوان الشاشة والأزرار -->
    <div class="d-flex justify-content-between align-items-center mb-3">

        <div>
            <h4 class="mb-1">فاتورة بيع</h4>
            <small class="text-muted">إدارة فواتير المبيعات للعملاء</small>
        </div>

        <div class="btn-group" role="group">

            <button
                type="button"
                class="btn btn-primary"
                id="btnNewSalesInvoice"
                onclick="resetSalesInvoice()"
            >
                <i class="bi bi-plus-lg"></i>
                إضافة فاتورة
            </button>

            <button
                type="button"
                class="btn btn-outline-secondary"
                id="btnSearchSalesInvoice"
                onclick="searchSalesInvoice()"
            >
                <i class="bi bi-search"></i>
                بحث
            </button>

        </div>

    </div>


    <!-- رأس الفاتورة -->
    @include('operation.sales.invoices.header')

    <!-- تفاصيل الفاتورة -->
    @include('operation.sales.invoices.detalis')

</div>


<!-- ===================================================== -->
<!-- نافذة البحث عن فاتورة بيع -->
<!-- ===================================================== -->

<div class="modal fade" id="salesInvoiceSearchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-search"></i>
                    البحث عن فاتورة بيع
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                        <tbody id="salesInvoiceSearchResults">
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
    @vite(['resources/js/pages/sales-invoice.js'])
@endpush
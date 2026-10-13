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

@include('shared.lookup-modal')


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

                <!-- ✅ حقل البحث مع زر التفريغ -->
                <div class="row g-2 mb-3">
                    <div class="col-md-12">
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="bi bi-search text-muted"></i>
                            </span>
                            <input
                                type="text"
                                class="form-control"
                                id="salesInvoiceSearchInput"
                                placeholder="أدخل رقم الفاتورة أو اسم العميل..."
                                oninput="debouncedSalesInvoiceSearch()"
                                autocomplete="off"
                            >
                            <button
                                type="button"
                                class="btn btn-outline-secondary"
                                id="btnClearSalesInvoiceSearch"
                                onclick="clearSalesInvoiceSearch()"
                                title="تفريغ البحث"
                            >
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="table-responsive" style="max-height: 450px;">
                    <table class="table table-bordered table-hover align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr class="text-center">
                                <th>رقم الفاتورة</th>
                                <th>التاريخ</th>
                                <th>العميل</th>
                                <th>العملة</th>
                                <th>طريقة الدفع</th>
                                <th>الإجمالي</th>
                                <th>الحالة</th>
                                <th style="min-width: 180px;">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody id="salesInvoiceSearchResults">
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <span class="spinner-border spinner-border-sm me-2"></span>
                                    جاري التحميل...
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
<script>
    window.SALES_SYSTEM_CURRENCY_ID   = @json($systemCurrencyId ?? null);
    window.SALES_SYSTEM_CURRENCY_CODE = @json($systemCurrencyCode ?? '');
</script>
@vite(['resources/js/pages/sales-invoice.js'])
@endpush
@extends('layouts.app')

@section('title', 'فواتير البيع | نظام ERP')

@section('content')

<div class="container-fluid py-3">

    <!-- ========================= -->
    <!-- عنوان الشاشة والأزرار -->
    <!-- ========================= -->

    <div class="d-flex justify-content-between align-items-center mb-3">

        <div>
            <h4 class="mb-1">فاتورة بيع</h4>
            <small class="text-muted">
                إدارة فواتير المبيعات للعملاء
            </small>
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


    <!-- ========================= -->
    <!-- حالة الشاشة -->
    <!-- ========================= -->

    <div
        id="salesInvoiceModeAlert"
        class="alert alert-secondary py-2 d-none"
        role="alert"
    >
        <i class="bi bi-eye"></i>
        <span id="salesInvoiceModeText"></span>
    </div>


    <!-- ========================= -->
    <!-- رأس الفاتورة -->
    <!-- ========================= -->

    @include('operation.sales.invoices.header')


    <!-- ========================= -->
    <!-- تفاصيل الفاتورة -->
    <!-- ========================= -->

    @include('operation.sales.invoices.detalis')

</div>


    <!-- ===================================================== -->
    <!-- نافذة البحث عن فاتورة البيع -->
<!-- ===================================================== -->

    @include('operation.sales.invoices.search')


<!-- ===================================================== -->
<!-- نافذة اختيار العميل -->
<!-- ===================================================== -->

    @include('operation.models.customer')


<!-- ===================================================== -->
<!-- نافذة اختيار العملة -->
<!-- ===================================================== -->


     @include('operation.models.currency')

<!-- ===================================================== -->
<!-- نافذة اختيار الصنف -->
<!-- ===================================================== -->

     @include('operation.models.item')


<!-- ===================================================== -->
<!-- نافذة اختيار النوع -->
<!-- ===================================================== -->

     @include('operation.models.type')


<!-- ===================================================== -->
<!-- نافذة اختيار المخزن -->
<!-- ===================================================== -->

     @include('operation.models.warehouses')


<!-- ===================================================== -->
<!-- نافذة اختيار الوحدة -->
<!-- ===================================================== -->

     @include('operation.models.unit')


@endsection


@push('scripts')


    {{-- ================================================= --}}
    {{-- JavaScript - فاتورة البيع --}}
    {{-- ================================================= --}}

    <script src="{{ asset('js/invoice/sales_invoice_state.js') }}"></script>
    <script src="{{ asset('js/invoice/sales_invoice_init.js') }}"></script>
    <script src="{{ asset('js/invoice/sales_invoice_mode.js') }}"></script>
    <script src="{{ asset('js/invoice/sales_invoice_form.js') }}"></script>
    <script src="{{ asset('js/invoice/sales_invoice_payment.js') }}"></script>
    <script src="{{ asset('js/invoice/sales_invoice_totals.js') }}"></script>
    <script src="{{ asset('js/invoice/sales_invoice_rows.js') }}"></script>
    <script src="{{ asset('js/invoice/sales_invoice_customer.js') }}"></script>
    <script src="{{ asset('js/invoice/sales_invoice_currency.js') }}"></script>
    <script src="{{ asset('js/invoice/sales_invoice_item.js') }}"></script>
    <script src="{{ asset('js/invoice/sales_invoice_type.js') }}"></script>
    <script src="{{ asset('js/invoice/sales_invoice_unit.js') }}"></script>
    <script src="{{ asset('js/invoice/sales_invoice_warehouse.js') }}"></script>
    <script src="{{ asset('js/invoice/sales_invoice_search.js') }}"></script>
    <script src="{{ asset('js/invoice/sales_invoice.js') }}"></script>

@endpush
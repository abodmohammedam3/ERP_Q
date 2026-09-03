@extends('layouts.app')

@section('title', 'سندات القبض | نظام ERP')

@section('content')

<div dir="rtl" class="container-fluid p-3">

    <!-- ====== رأس الشاشة ====== -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div class="d-flex align-items-center gap-2">
            <h4 class="mb-0">
                <i class="bi bi-cash-stack text-success me-1"></i>
                سند القبض
            </h4>
            <span class="badge bg-secondary" id="ReceiptVoucherNumberDisplay">
                رقم السند: --
            </span>
            <span class="badge bg-info text-dark status-bar" id="ReceiptVoucherDateDisplay">
                <i class="bi bi-calendar3 me-1"></i>
                --
            </span>
        </div>

        <!-- أزرار الإجراءات السريعة -->
        <div class="btn-group" role="group">
            <button type="button" class="btn btn-success" id="btnNewReceiptVoucher">
                <i class="bi bi-plus-lg"></i> إضافة سند
            </button>
            <button type="button" class="btn btn-outline-secondary" id="btnSearchReceiptVoucher">
                <i class="bi bi-search"></i> بحث
            </button>
        </div>
    </div>

    <!-- ====== بيانات السند ====== -->
    @include('operation.accounting.receiptVouchers.item')

    <!-- ====== أزرار العمليات ====== -->
    <div class="d-flex justify-content-end gap-2 mt-3 flex-wrap">
        <button type="button" class="btn btn-outline-secondary d-none" id="btnCancelReceiptVoucher">
            <i class="bi bi-x-lg"></i> إلغاء
        </button>
        <button type="button" class="btn btn-success" id="btnSaveReceiptVoucher" disabled>
            <i class="bi bi-check-lg"></i> حفظ
        </button>
        <button type="button" class="btn btn-success" id="btnSaveAndNewReceiptVoucher" disabled>
            <i class="bi bi-plus-circle"></i> حفظ وإضافة جديد
        </button>
        <button type="button" class="btn btn-warning" id="btnEditReceiptVoucher" disabled>
            <i class="bi bi-pencil-square"></i> تعديل
        </button>
        <button type="button" class="btn btn-dark" id="btnPrintReceiptVoucher" disabled>
            <i class="bi bi-printer"></i> طباعة
        </button>
    </div>

</div>

<!-- ====== النوافذ المنبثقة ====== -->
@include('operation.model.customer')                 <!-- العميل -->
@include('operation.model.currency')
@include('operation.accounting.receiptVouchers.receiptSearch')    <!-- بحث سندات القبض -->

@endsection


@push('scripts')
    <!-- المكونات العامة -->
    <script src="{{ asset('js/shared/modals.js') }}"></script>
    <script src="{{ asset('js/shared/state.js') }}"></script>
    <script src="{{ asset('js/shared/customer.js') }}"></script>          <!-- العميل -->
    <script src="{{ asset('js/shared/currency.js') }}"></script>
    <script src="{{ asset('js/shared/paymentMethod.js') }}"></script>
    <script src="{{ asset('js/recepit/receiptSearch.js') }}"></script>
    <script src="{{ asset('js/shared/utils.js') }}"></script>

    <!-- ملف الصفحة الرئيسي -->
    <script src="{{ asset('js/recepit/recepit.js') }}"></script>
@endpush
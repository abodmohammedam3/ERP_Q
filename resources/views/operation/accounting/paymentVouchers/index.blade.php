@extends('layouts.app')

@section('title', 'سندات الصرف | نظام ERP')

@section('content')

<div dir="rtl" class="container-fluid p-3">

    <!-- ══════════════════════════════════════════════════════════ -->
    <!--  رأس الشاشة -->
    <!-- ══════════════════════════════════════════════════════════ -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div class="d-flex align-items-center gap-2">
            <h4 class="mb-0">
                <i class="bi bi-cash-coin text-danger me-1"></i>
                سند الصرف
            </h4>
            <span class="badge bg-secondary" id="PaymentVoucherNumberDisplay">
                رقم السند: --
            </span>
            <span class="badge bg-info text-dark status-bar" id="PaymentVoucherDateDisplay">
                <i class="bi bi-calendar3 me-1"></i>
                --
            </span>
        </div>

        <div class="btn-group" role="group">
            <button type="button" class="btn btn-success" id="btnNewPaymentVoucher">
                <i class="bi bi-plus-lg"></i> إضافة سند
            </button>
            <button type="button" class="btn btn-outline-secondary" id="btnSearchPaymentVoucher">
                <i class="bi bi-search"></i> بحث
            </button>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════ -->
    <!--  بيانات السند -->
    <!-- ══════════════════════════════════════════════════════════ -->
    @include('operation.accounting.paymentVouchers.item')

    <!-- ══════════════════════════════════════════════════════════ -->
    <!--  أزرار العمليات -->
    <!-- ══════════════════════════════════════════════════════════ -->
    <div class="d-flex justify-content-end gap-2 mt-3 flex-wrap">
        <button type="button" class="btn btn-outline-secondary d-none" id="btnCancelPaymentVoucher">
            <i class="bi bi-x-lg"></i> إلغاء
        </button>
        <button type="button" class="btn btn-success" id="btnSavePaymentVoucher" disabled>
            <i class="bi bi-check-lg"></i> حفظ
        </button>
        <button type="button" class="btn btn-success" id="btnSaveAndNewPaymentVoucher" disabled>
            <i class="bi bi-plus-circle"></i> حفظ وإضافة جديد
        </button>
        <button type="button" class="btn btn-warning" id="btnEditPaymentVoucher" disabled>
            <i class="bi bi-pencil-square"></i> تعديل
        </button>
        <button type="button" class="btn btn-dark" id="btnPrintPaymentVoucher" disabled>
            <i class="bi bi-printer"></i> طباعة
        </button>
    </div>

</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<!--  النوافذ المنبثقة -->
<!-- ══════════════════════════════════════════════════════════════ -->
@include('operation.accounting.paymentVouchers.accountPickerModal')
@include('operation.accounting.paymentVouchers.paymentSearch')

@endsection


@push('scripts')
    @vite(['resources/js/payment/payment.js'])
@endpush
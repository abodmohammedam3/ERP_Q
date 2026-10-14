@extends('layouts.app')

@section('title', 'سندات القبض | نظام ERP')

@section('content')

<div dir="rtl" class="container-fluid p-3">

    <!-- ══════════════════════════════════════════════════════════ -->
    <!-- رأس الشاشة -->
    <!-- ══════════════════════════════════════════════════════════ -->

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">

        <div class="d-flex align-items-center gap-2">

            <h4 class="mb-0">
                <i class="bi bi-cash-coin text-success me-1"></i>
                سند القبض
            </h4>

            <span
                class="badge bg-secondary"
                id="ReceiptVoucherNumberDisplay"
            >
                رقم السند: --
            </span>

            <span
                class="badge bg-info text-dark status-bar"
                id="ReceiptVoucherDateDisplay"
            >
                <i class="bi bi-calendar3 me-1"></i>
                --
            </span>

        </div>


        <div class="btn-group" role="group">

            <button
                type="button"
                class="btn btn-success"
                id="btnNewReceiptVoucher"
            >
                <i class="bi bi-plus-lg"></i>
                إضافة سند
            </button>

            <button
                type="button"
                class="btn btn-outline-secondary"
                id="btnSearchReceiptVoucher"
            >
                <i class="bi bi-search"></i>
                بحث
            </button>

        </div>

    </div>


    <!-- ══════════════════════════════════════════════════════════ -->
    <!-- بيانات السند -->
    <!-- ══════════════════════════════════════════════════════════ -->

    @include('operation.accounting.receiptVouchers.item')


    <!-- ══════════════════════════════════════════════════════════ -->
    <!-- أزرار العمليات -->
    <!-- ══════════════════════════════════════════════════════════ -->

    <div class="d-flex justify-content-end gap-2 mt-3 flex-wrap">

        {{-- حفظ --}}
        <button
            type="button"
            class="btn btn-success"
            id="btnSaveReceiptVoucher"
            disabled
        >
            <i class="bi bi-check-lg"></i>
            حفظ
        </button>

        {{-- حفظ وإضافة جديد --}}
        <button
            type="button"
            class="btn btn-success"
            id="btnSaveAndNewReceiptVoucher"
            disabled
        >
            <i class="bi bi-plus-circle"></i>
            حفظ وإضافة جديد
        </button>

        {{-- إلغاء --}}
        <button
            type="button"
            class="btn btn-outline-secondary d-none"
            id="btnCancelReceiptVoucher"
        >
            <i class="bi bi-x-lg"></i>
            إلغاء
        </button>

        {{-- تعديل --}}
        <button
            type="button"
            class="btn btn-warning"
            id="btnEditReceiptVoucher"
            disabled
        >
            <i class="bi bi-pencil-square"></i>
            تعديل
        </button>

        {{-- ✅ ترحيل (btn-outline-success — مطابق للنظام) --}}
        <button
            type="button"
            class="btn btn-outline-success d-none"
            id="btnPostReceiptVoucher"
        >
            <i class="bi bi-check2-circle"></i>
            ترحيل
        </button>

        {{-- إلغاء الترحيل --}}
        <button
            type="button"
            class="btn btn-outline-warning d-none"
            id="btnUnpostReceiptVoucher"
        >
            <i class="bi bi-arrow-counterclockwise"></i>
            إلغاء الترحيل
        </button>

        {{-- طباعة --}}
        <button
            type="button"
            class="btn btn-dark"
            id="btnPrintReceiptVoucher"
            disabled
        >
            <i class="bi bi-printer"></i>
            طباعة
        </button>

    </div>

</div>


<!-- ══════════════════════════════════════════════════════════════ -->
<!-- النوافذ المنبثقة -->
<!-- ══════════════════════════════════════════════════════════════ -->

@include('operation.accounting.receiptVouchers.accountPickerModal')

@include('operation.accounting.receiptVouchers.receiptSearch')

@endsection


{{-- ✅ المودال العام — يُحمَّل في هذه الصفحة فقط --}}
@push('modals')
    @include('shared.app-confirm-modal')
@endpush


@push('scripts')

    @vite(['resources/js/pages/receipt-voucher.js'])

@endpush
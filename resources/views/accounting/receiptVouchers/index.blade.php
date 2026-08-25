@extends('layouts.app')

@section('title', 'سندات القبض | نظام ERP')

@section('content')
<div dir="rtl" class="container-fluid p-3">
   <div dir="rtl" class="container-fluid p-3">

        <!-- ====== رأس الشاشة مع أزرار الإجراءات السريعة ====== -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div class="d-flex align-items-center gap-2">
                <h4 class="mb-0 d-inline-block">
                    <i class="bi bi-receipt-cutoff text-primary me-1"></i> سند قبض
                </h4>
                <span class="badge bg-secondary ms-2">رقم السند: قبض-2026-001</span>
                <span class="badge bg-info text-dark ms-2 status-bar">
                    <i class="bi bi-calendar3 me-1"></i> 2026-08-19
                </span>
                <span class="badge bg-warning text-dark ms-2 status-bar">
                    <i class="bi bi-clock-history me-1"></i> قيد المراجعة
                </span>
            </div>
            <div class="action-buttons d-flex flex-wrap gap-1">
                <button class="btn btn-outline-secondary" onclick="window.print()" title="طباعة السند">
                    <i class="bi bi-printer"></i> طباعة
                </button>
                <button class="btn btn-outline-primary" onclick="resetForm()" title="إنشاء سند جديد">
                    <i class="bi bi-plus-circle"></i> جديد
                </button>
                <button class="btn btn-success" onclick="saveVoucher()">
                    <i class="bi bi-check2-circle"></i> حفظ السند
                </button>
            </div>
        </div>


@include('accounting.receiptVouchers.item')
@include('accounting.receiptVouchers.itemdetails')


 
        <!-- ====== أزرار إضافية في أسفل الشاشة ====== -->
        <div class="mt-3 d-flex flex-wrap gap-2 justify-content-end">
            <button class="btn btn-secondary" onclick="cancelVoucher()">
                <i class="bi bi-x-circle"></i> إلغاء
            </button>
            <button class="btn btn-warning" onclick="saveDraft()">
                <i class="bi bi-pencil-square"></i> حفظ كمسودة
            </button>
            <button class="btn btn-success" onclick="approveAndPrint()">
                <i class="bi bi-check2-all"></i> اعتماد وطباعة
            </button>
        </div>
    </div>

</div>
@endsection

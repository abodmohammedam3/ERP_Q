@extends('layouts.app')

@section('title', 'فواتير البيع | نظام ERP')

@section('content')
<div class="container-fluid py-3">

    <!-- عنوان الشاشة -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">فاتورة بيع</h4>
            <small class="text-muted">إدارة فواتير المبيعات للعملاء</small>
        </div>

        <div class="btn-group" role="group">
            <button type="button" class="btn btn-primary" onclick="resetSalesInvoice()">
                <i class="bi bi-plus-lg"></i> فاتورة جديدة
            </button>
            <button type="button" class="btn btn-outline-secondary">
                <i class="bi bi-search"></i> بحث
            </button>
        </div>
    </div>

    <!-- استدعاء ملفات الفاتورة -->
    @include('sales.invoices.header')
    @include('sales.invoices.detalis')

</div>

<!-- تضمين ملف الجافا سكربت الخاص بالشاشة -->
@endsection

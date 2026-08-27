@extends('layouts.app')

@section('title', 'فواتير الشراء | نظام ERP')

@section('content')
<div class="container-fluid py-3">

    <!-- عنوان الشاشة -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">فاتورة شراء</h4>
            <small class="text-muted">إدارة فواتير المشتريات وتفاصيل الأصناف</small>
        </div>

        <div class="btn-group" role="group">
            <button type="button" class="btn btn-primary" onclick="resetInvoice()">
                <i class="bi bi-plus-lg"></i>
                فاتورة جديدة
            </button>

            <button type="button" class="btn btn-outline-secondary" onclick="searchInvoice()">
                <i class="bi bi-search"></i>
                بحث
            </button>
        </div>
    </div>

    @include('purchases.invoicesPurch.haed')
    @include('purchases.invoicesPurch.detals')

</div>


@endsection

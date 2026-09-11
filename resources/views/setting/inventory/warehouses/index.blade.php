@extends('layouts.app')

@section('title', 'المخازن')

@section('content')

<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1"><i class="bi bi-building"></i> المخازن</h4>
            <small class="text-muted">إدارة المخازن المرتبطة بالنظام</small>
        </div>
        <div class="btn-group">
            @if($hasParent)
                <button type="button" class="btn btn-outline-secondary" onclick="printStocks()">
                    <i class="bi bi-printer"></i> طباعة
                </button>
                <button type="button" class="btn btn-primary" onclick="openStockModal()">
                    <i class="bi bi-plus-lg"></i> إضافة مخزن
                </button>
            @endif
        </div>
    </div>

    @if(!$hasParent)
        <div class="alert alert-warning mb-3">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>تنبيه:</strong> لم يتم العثور على حساب "المخازن" في دليل الحسابات.
            يرجى إنشاء حساب باسم "المخازن" كحساب فرعي تحت "أصول متداولة" لإمكانية إدارة المخازن.
            <br><small>حالياً الشاشة في وضع <strong>العرض فقط</strong>.</small>
        </div>
    @endif

    @include('setting.inventory.warehouses.search')
    @include('setting.inventory.warehouses.table')
    @include('setting.inventory.warehouses.addUpdate')
    @include('setting.inventory.warehouses.deletModel')
</div>

@endsection
@push('scripts')
<script src="{{ asset('js/stocks.js') }}"></script>
@endpush
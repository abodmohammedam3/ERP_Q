@extends('layouts.app')

@section('title', 'توزيع أصناف فواتير الشراء')

@section('content')
<div class="container-fluid py-4" dir="rtl">
    @include('operation.purchase-distribution.tabs')
</div>

{{-- Modals --}}
@include('operation.purchase-distribution.invoice-modal')
@include('operation.purchase-distribution.allocation-modal')
@include('operation.purchase-distribution.accountPickerModal')
@include('operation.purchase-distribution.history.distribution-modal')
@include('operation.purchase-distribution.history.invoice-picker-modal')

{{-- مودالات إصدار الفواتير --}}
@include('operation.purchase-distribution.customer-invoices.distribution-picker-modal')
@include('operation.purchase-distribution.customer-invoices.customer-picker-modal')
@endsection

@push('scripts')
    @vite([
        
        'resources/js/pages/purchase-distribution.js',
        'resources/js/pages/customer-invoices.js',
        'resources/js/pages/history.js',
    ])
@endpush
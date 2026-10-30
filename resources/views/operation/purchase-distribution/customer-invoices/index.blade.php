@extends('layouts.app')

@section('title', 'إصدار فواتير العملاء')

@section('content')
<div class="container-fluid py-4" dir="rtl">

    @include('operation.purchase-distribution.customer-invoices.header')

    <div id="alertArea" class="mb-3"></div>

    @include('operation.purchase-distribution.customer-invoices.customer-search')

    <div id="contentArea" class="d-none">
        @include('operation.purchase-distribution.customer-invoices.summary')
        @include('operation.purchase-distribution.customer-invoices.distributions-list')
        @include('operation.purchase-distribution.customer-invoices.actions')
    </div>

</div>

@include('operation.purchase-distribution.accountPickerModal')
@endsection

@push('scripts')
    @vite('resources/js/pages/customer-invoices.js')
@endpush
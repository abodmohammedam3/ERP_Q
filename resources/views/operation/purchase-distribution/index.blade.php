@extends('layouts.app')

@section('title', 'توزيع أصناف فواتير الشراء')

@section('content')
<div class="container-fluid py-4" dir="rtl">

    @include('operation.purchase-distribution.header')

    <div id="alertArea" class="mb-3"></div>

    @include('operation.purchase-distribution.distribution-info')

    <div class="mt-3">
        @include('operation.purchase-distribution.items-table')
    </div>

    <div class="row g-3 mt-1">
        <div class="col-lg-5">
            @include('operation.purchase-distribution.customers-panel')
        </div>
        <div class="col-lg-7">
            @include('operation.purchase-distribution.allocation-panel')
        </div>
    </div>

    <div class="mt-3">
        @include('operation.purchase-distribution.summary')
    </div>

    <div class="mt-3">
        @include('operation.purchase-distribution.actions')
    </div>
</div>

@include('operation.purchase-distribution.invoice-modal')

@include('operation.purchase-distribution.accountPickerModal')

@endsection

@push('scripts')
    @vite('resources/js/pages/purchase-distribution.js')
@endpush
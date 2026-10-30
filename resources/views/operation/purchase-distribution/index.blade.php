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

    <div class="mt-3">
        @include('operation.purchase-distribution.summary')
    </div>

    <div class="mt-3">
        @include('operation.purchase-distribution.actions')
    </div>
</div>

@include('operation.purchase-distribution.invoice-modal')
@include('operation.purchase-distribution.allocation-modal')

{{-- مسار account-picker حسب مشروعك --}}
@include('operation.purchase-distribution.accountPickerModal')

@endsection

@push('scripts')
    @vite('resources/js/pages/purchase-distribution.js')
@endpush
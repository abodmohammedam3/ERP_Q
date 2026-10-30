@extends('layouts.app')

@section('title', 'سجل التوزيعات')

@section('content')
<div class="container-fluid py-4" dir="rtl">

    @include('operation.purchase-distribution.history.header')

    <div id="alertArea" class="mb-3"></div>

    @include('operation.purchase-distribution.history.header-stats')

    <div class="card border-0 shadow-sm">
        @include('operation.purchase-distribution.history.tabs')

        <div class="card-body">
            @include('operation.purchase-distribution.history.tab-distributions')
            @include('operation.purchase-distribution.history.tab-customers')
            @include('operation.purchase-distribution.history.tab-invoices')
        </div>
    </div>
</div>

@include('operation.purchase-distribution.history.distribution-modal')
@endsection

@push('scripts')
    @vite('resources/js/pages/history.js')
@endpush
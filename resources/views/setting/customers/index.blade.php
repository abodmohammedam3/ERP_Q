@extends('layouts.app')

@section('title', 'العملاء')

@section('content')

<div class="container-fluid py-3">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">
                <i class="bi bi-people"></i> العملاء
            </h4>
            <small class="text-muted">إدارة العملاء المسجلين في النظام</small>
        </div>

        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary" onclick="printCustomers()">
                <i class="bi bi-printer"></i> طباعة 
            </button>
            <button
                type="button"
                class="btn btn-sm btn-primary"
                id="addCustomerBtn">
                <i class="bi bi-plus-lg me-1"></i>
                إضافة عميل 
            </button>
        </div>
    </div>

    @include('setting.customers.search')

    <div id="customersTableContainer">
        @include('setting.customers.table')
    </div>

</div>

@include('setting.customers.deletModel')
@include('setting.customers.addUpdate')

@endsection

@push('scripts')
<script src="{{ asset('js/customer.js') }}"></script>
@endpush
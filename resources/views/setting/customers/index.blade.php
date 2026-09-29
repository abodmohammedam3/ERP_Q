@extends('layouts.app')

@section('title', 'العملاء')

@section('content')
<div class="container-fluid py-3">

    <header class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">
                <i class="bi bi-people"></i> العملاء
            </h4>
            <small class="text-muted">إدارة العملاء المسجلين في النظام</small>
        </div>

        <div class="d-flex gap-2">
            {{-- ✅ تمت إضافة id وإزالة onclick --}}
            <button type="button"
                    class="btn btn-sm btn-outline-secondary"
                    id="printCustomersBtn">
                <i class="bi bi-printer"></i> طباعة
            </button>

            <button type="button"
                    class="btn btn-sm btn-primary"
                    id="addCustomerBtn">
                <i class="bi bi-plus-lg me-1"></i> إضافة عميل
            </button>
        </div>
    </header>

    @include('setting.customers.search')

    <div id="customersTableContainer">
        @include('setting.customers.table')
    </div>

</div>

@include('setting.customers.deletModel')
@include('setting.customers.addUpdate')
@endsection

@push('scripts')
    @vite(['resources/js/pages/customers.js'])
@endpush
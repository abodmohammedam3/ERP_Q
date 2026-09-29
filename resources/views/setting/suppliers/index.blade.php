@extends('layouts.app')

@section('title', 'الموردون')

@section('content')
<div class="container-fluid py-3">

    <header class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">
                <i class="bi bi-truck"></i> الموردون
            </h4>
            <small class="text-muted">إدارة الموردين المسجلين في النظام</small>
        </div>

        <div class="d-flex gap-2">
            {{-- ✅ تمت إضافة id وإزالة onclick --}}
            <button type="button"
                    class="btn btn-sm btn-outline-secondary"
                    id="printSuppliersBtn">
                <i class="bi bi-printer"></i> طباعة
            </button>

            <button type="button"
                    class="btn btn-sm btn-primary"
                    id="addSupplierBtn">
                <i class="bi bi-plus-lg me-1"></i> إضافة مورد
            </button>
        </div>
    </header>

    @include('setting.suppliers.search')

    <div id="suppliersTableContainer">
        @include('setting.suppliers.table')
    </div>

</div>

@include('setting.suppliers.addUpdate')
@include('setting.suppliers.deletModel')

@endsection

@push('scripts')
    @vite(['resources/js/pages/suppliers.js'])
@endpush
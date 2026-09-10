@extends('layouts.app')

@section('title', 'الموردون')

@section('content')

<div class="container-fluid py-3"><div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1">
            <i class="bi bi-truck"></i> الموردون
        </h4>
        <small class="text-muted">إدارة الموردين المسجلين في النظام</small>
    </div>

    <div class="btn-group">

    <button
        type="button"
        class="btn btn-outline-secondary"
        onclick="printSuppliers()">
        <i class="bi bi-printer"></i> طباعة
    </button>

    <button
        type="button"
        class="btn btn-sm btn-primary"
        id="addSupplierBtn">
        <i class="bi bi-plus-lg me-1"></i> إضافة مورد
    </button>

</div>
</div>

<!-- استدعاء ملف البحث -->
@include('setting.suppliers.search')

<div id="suppliersTableContainer">
    @include('setting.suppliers.table')
</div>

</div>
<!-- النافذة المنبثقة (Modal) لإضافة/تعديل مورد -->
@include('setting.suppliers.addUpdate')

@include('setting.suppliers.deletModel')

<!-- تضمين ملف الجافا سكربت الخاص بالشاشة -->@endsection

@push('scripts')

<script src="{{ asset('js/supplier.js') }}"></script>@endpush
@extends('layouts.app')

@section('title', 'الوحدات')

@section('content')

<div class="container-fluid py-3">

    {{-- عنوان الشاشة --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">
                <i class="bi bi-rulers"></i>
                الوحدات
            </h4>
            <small class="text-muted">إدارة وحدات القياس المستخدمة في النظام</small>
        </div>

        <div class="d-flex gap-2">
            <button type="button" class="btn btn-secondary" onclick="printUnits()">
                <i class="bi bi-printer"></i> طباعة
            </button>
            <button type="button" class="btn btn-primary" onclick="openUnitModal()">
                <i class="bi bi-plus-lg"></i> إضافة وحدة
            </button>
        </div>
    </div>

    {{-- استدعاء ملفات البحث والجدول --}}
    @include('setting.inventory.units.search')
    @include('setting.inventory.units.table')
    @include('setting.inventory.units.addUbdate')
    @include('setting.inventory.units.deletModel')

</div>


@push('scripts')
    <script src="{{ asset('js/units.js') }}"></script>
@endpush

@endsection
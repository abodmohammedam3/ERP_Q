@extends('layouts.app')

@section('title', 'الأصناف')

@section('content')

<div class="container-fluid py-3">

    {{-- عنوان الشاشة --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">
                <i class="bi bi-box-seam"></i> الأصناف
            </h4>
            <small class="text-muted">إدارة الأصناف المسجلة في النظام</small>
        </div>

        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary" onclick="printItems()">
                <i class="bi bi-printer"></i> طباعة
            </button>
            <button type="button" class="btn btn-primary" onclick="openItemModal()">
                <i class="bi bi-plus-lg"></i> إضافة صنف
            </button>
        </div>
    </div>

    <!-- استدعاء ملفات البحث والجدول -->
    @include('setting.inventory.items.search')
    @include('setting.inventory.items.table')
    @include('setting.inventory.items.addUpdate')
    @include('setting.inventory.items.deletModel')

</div>


@push('scripts')
    <script src="{{asset("js/items.js")}}"></script>
@endpush

@endsection

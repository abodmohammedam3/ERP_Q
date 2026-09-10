@extends('layouts.app')

@section('title', 'أنواع الأصناف')

@section('content')

<div class="container-fluid py-3">

    {{-- عنوان الشاشة --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">
                <i class="bi bi-tags"></i> الأنواع
            </h4>
            <small class="text-muted">إدارة أنواع الأصناف</small>
        </div>

        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary" onclick="printTypes()">
                <i class="bi bi-printer"></i> طباعة
            </button>
            <button type="button" class="btn btn-primary" onclick="openTypeModal()">
                <i class="bi bi-plus-lg"></i> إضافة نوع
            </button>
        </div>
    </div>

    <!-- استدعاء ملفات البحث والجدول -->
    @include('setting.inventory.types.search')
    @include('setting.inventory.types.table')
     @include('setting.inventory.types.addUpdate')

</div>
   



@push('scripts')
    <script src="{{ asset('js/type.js') }}"></script>
@endpush

@endsection
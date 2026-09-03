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

</div>

<!-- النافذة المنبثقة (Modal) لإضافة/تعديل نوع -->
<div class="modal fade" id="typeModal" tabindex="-1" aria-labelledby="typeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="typeModalLabel">إضافة نوع جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="typeForm">
                    <input type="hidden" id="typeID">
                    
                    <div class="mb-3">
                        <label for="typeName" class="form-label">اسم النوع <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="typeName" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" onclick="saveType()">حفظ البيانات</button>
            </div>
        </div>
    </div>
</div>
@push('scripts')
    <script src="{{asset("js/type.js")}}"></script>
@endpush

@endsection

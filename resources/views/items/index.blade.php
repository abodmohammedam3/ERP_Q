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
    @include('items.search')
    @include('items.table')

</div>

<!-- النافذة المنبثقة (Modal) لإضافة/تعديل صنف -->
<div class="modal fade" id="itemModal" tabindex="-1" aria-labelledby="itemModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="itemModalLabel">إضافة صنف جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="itemForm">
                    <input type="hidden" id="itemID">
                    
                    <div class="mb-3">
                        <label for="itemName" class="form-label">اسم الصنف <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="itemName" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" onclick="saveItem()">حفظ البيانات</button>
            </div>
        </div>
    </div>
</div>


@endsection

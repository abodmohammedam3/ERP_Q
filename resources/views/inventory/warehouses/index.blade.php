@extends('layouts.app')

@section('title', 'المخازن')

@section('content')

<div class="container-fluid py-3">
    
    {{-- عنوان الشاشة --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">
                <i class="bi bi-building"></i> المخازن
            </h4>
            <small class="text-muted">إدارة المخازن المرتبطة بالنظام</small>
        </div>

        <div>
            <button type="button" class="btn btn-primary" onclick="openStockModal()">
                <i class="bi bi-plus-lg"></i> إضافة مخزن
            </button>
        </div>
    </div>

    <!-- استدعاء ملف البحث وملف الجدول -->
    @include('inventory.warehouses.search')
    @include('inventory.warehouses.table')

</div>

<!-- النافذة المنبثقة (Modal) لإضافة/تعديل مخزن -->
<div class="modal fade" id="stockModal" tabindex="-1" aria-labelledby="stockModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="stockModalLabel">إضافة مخزن جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="stockForm">
                    <input type="hidden" id="stockID">
                    
                    <div class="mb-3">
                        <label for="stockName" class="form-label">اسم المخزن <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="stockName" required>
                    </div>

                    <div class="mb-3">
                        <label for="stockAccount" class="form-label">الحساب المرتبط</label>
                        <input type="text" class="form-control" id="stockAccount" placeholder="مثال: حساب المخزون الرئيسي">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" onclick="saveStock()">حفظ البيانات</button>
            </div>
        </div>
    </div>
</div>



@endsection

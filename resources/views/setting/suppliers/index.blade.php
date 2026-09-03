@extends('layouts.app')

@section('title', 'الموردون')

@section('content')

<div class="container-fluid py-3">

    <div class="d-flex justify-content-between align-items-center mb-3">

        <div>
            <h4 class="mb-1">
                <i class="bi bi-truck"></i> الموردون
            </h4>
            <small class="text-muted">إدارة الموردين المسجلين في النظام</small>
        </div>

        <button type="button" class="btn btn-primary" onclick="openSupplierModal()">
            <i class="bi bi-plus-lg"></i> إضافة مورد
        </button>

    </div>

    <!-- استدعاء ملف البحث وملف الجدول -->
    @include('setting.suppliers.search')
    @include('setting.suppliers.table')

</div>

<!-- النافذة المنبثقة (Modal) لإضافة/تعديل مورد -->
<div class="modal fade" id="supplierModal" tabindex="-1" aria-labelledby="supplierModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="supplierModalLabel">إضافة مورد جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="supplierForm">
                    <input type="hidden" id="suplierID">
                    
                    <div class="mb-3">
                        <label for="supName" class="form-label">اسم المورد <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="supName" required>
                    </div>

                    <div class="mb-3">
                        <label for="supPhone" class="form-label">رقم الهاتف <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="supPhone" required>
                    </div>

                    <div class="mb-3">
                        <label for="supArea" class="form-label">المنطقة</label>
                        <input type="text" class="form-control" id="supArea">
                    </div>

                    <div class="mb-3">
                        <label for="supStatus" class="form-label">الحالة</label>
                        <select class="form-select" id="supStatus">
                            <option value="0">نشط</option>
                            <option value="1">متوقف</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" onclick="saveSupplier()">حفظ البيانات</button>
            </div>
        </div>

    </div>

</div>

<!-- تضمين ملف الجافا سكربت الخاص بالشاشة -->

@endsection
@push('scripts')

<script src="{{ asset('js/supplier.js') }}"></script>

@endpush

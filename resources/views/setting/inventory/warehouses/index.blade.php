@extends('layouts.app')

@section('title', 'المخازن')

@section('content')

<div class="container-fluid py-3">
    {{-- عنوان الشاشة --}}
    <div class="d-flex justify-content-between align-items-center mb-3">

        <div>
            <h4 class="mb-1">
                <i class="bi bi-building"></i>
                المخازن
            </h4>

            <small class="text-muted">
                إدارة المخازن المرتبطة بالنظام
            </small>
        </div>

        <div class="d-flex gap-2">

            <button
                type="button"
                class="btn btn-secondary"
                id="printWarehousesBtn"
            >
                <i class="bi bi-printer"></i>
                طباعة
            </button>

            <button
                type="button"
                class="btn btn-primary"
                id="addWarehouseBtn"
            >
                <i class="bi bi-plus-lg"></i>
                إضافة مخزن
            </button>

        </div>

    </div>

    {{-- شريط البحث --}}
    <div class="card mb-3">

        <div class="card-body">

            <div class="row g-2 align-items-end">

                <div class="col-md-6">

                    <label class="form-label">
                        البحث
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="warehouseSearch"
                        placeholder="ابحث باسم المخزن..."
                    >

                </div>

                <div class="col-md-auto">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        id="searchWarehouseBtn"
                    >
                        <i class="bi bi-search"></i>
                        بحث
                    </button>

                </div>

            </div>

        </div>

    </div>

    {{-- جدول المخازن --}}
    <div class="card">

        <div class="card-header d-flex justify-content-between align-items-center">

            <span>
                <i class="bi bi-list-ul"></i>
                قائمة المخازن
            </span>

            <span class="badge bg-secondary" id="warehouseCount">
                0
            </span>

        </div>

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table table-hover table-bordered mb-0 align-middle" id="warehousesTable">

                    <thead class="table-light">

                        <tr>

                            <th class="text-center">#</th>
                            <th>اسم المخزن</th>
                            <th>رقم الحساب التحليلي</th>  <!-- تم التعديل -->
                            <th class="text-center" style="width: 180px;">الإجراءات</th>

                        </tr>

                    </thead>

                    <tbody id="warehousesTableBody">

                        @forelse($stocks ?? [] as $stock)

                            <tr class="warehouse-row" data-id="{{ $stock->StockID }}">

                                <td class="text-center row-number">{{ $loop->iteration }}</td>
                                <td class="row-name">{{ $stock->StockName2 }}</td>
                                <td class="row-analytical">{{ $stock->analytical_account ?? 'غير محدد' }}</td>
                                <td class="text-center">

                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary edit-warehouse"
                                        data-id="{{ $stock->StockID }}"
                                        title="تعديل"
                                    >
                                        <i class="bi bi-pencil"></i> تعديل
                                    </button>

                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger delete-warehouse"
                                        data-id="{{ $stock->StockID }}"
                                        title="حذف"
                                    >
                                        <i class="bi bi-trash"></i> حذف
                                    </button>

                                </td>

                            </tr>

                        @empty

                            <tr id="emptyWarehouseRow">

                                <td colspan="4" class="text-center text-muted py-5">
                                    <i class="bi bi-building fs-2 d-block mb-2"></i>
                                    لا توجد مخازن مسجلة
                                </td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

<!-- نافذة منبثقة (مودال) للإضافة والتعديل -->
<div class="modal fade" id="warehouseModal" tabindex="-1" aria-labelledby="warehouseModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="warehouseModalLabel">
                    <i class="bi bi-building"></i> إضافة مخزن
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="warehouseForm">
                    <input type="hidden" id="warehouseId">

                    <div class="mb-3">
                        <label for="warehouseName" class="form-label">اسم المخزن <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="warehouseName" required>
                    </div>

                    <div class="mb-3">
                        <label for="warehouseAnalytical" class="form-label">رقم الحساب التحليلي</label>
                        <input type="text" class="form-control" id="warehouseAnalytical" readonly>
                        <small class="form-text text-muted">يتم توليده تلقائياً عند الإضافة.</small>
                    </div>

                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i> إلغاء
                </button>
                <button type="button" class="btn btn-success" id="saveWarehouseBtn">
                    <i class="bi bi-check-lg"></i> حفظ
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="{{ asset('js/warehouses.js') }}"></script>
@endpush
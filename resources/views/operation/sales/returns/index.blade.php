
@extends('layouts.app')

@section('title', 'مرتجعات المبيعات | نظام ERP')

@section('content')

<div class="container-fluid py-3">

    {{-- عنوان الشاشة والأزرار --}}
    <div class="d-flex justify-content-between align-items-center mb-3">

        <div>
            <h4 class="mb-1 text-danger">
                <i class="bi bi-arrow-return-left me-2"></i>
                مرتجع بيع
            </h4>
            <small class="text-muted">
                إدارة فواتير مرتجعات المبيعات للعملاء
            </small>
        </div>

        <div class="btn-group" role="group">

            <button
                type="button"
                class="btn btn-primary"
                id="btnNewSalesReturn"
                onclick="resetSalesReturn()"
            >
                <i class="bi bi-plus-lg"></i>
                مرتجع جديد
            </button>

            <button
                type="button"
                class="btn btn-outline-secondary"
                id="btnSearchSalesReturn"
                onclick="searchSalesReturn()"
            >
                <i class="bi bi-search"></i>
                بحث في المرتجعات
            </button>

        </div>

    </div>

    {{-- رأس المرتجع --}}
    @include('operation.sales.returns.header')

    {{-- تفاصيل المرتجع --}}
    @include('operation.sales.returns.details')

</div>


{{-- ===================================================== --}}
{{-- نافذة البحث عن فاتورة بيع أصلية --}}
{{-- ===================================================== --}}
<div
    class="modal fade"
    id="originalSalesInvoiceSearchModal"
    tabindex="-1"
    aria-hidden="true"
>
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-search me-2"></i>
                    البحث عن فاتورة بيع أصلية
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="إغلاق"
                ></button>
            </div>

            <div class="modal-body">

                {{-- حقل البحث مع زر التفريغ --}}
                <div class="row g-2 mb-3">
                    <div class="col-md-12">
                        <div class="input-group">

                            <span class="input-group-text bg-white">
                                <i class="bi bi-search text-muted"></i>
                            </span>

                            <input
                                type="text"
                                class="form-control"
                                id="originalSalesInvoiceSearchInput"
                                placeholder="أدخل رقم الفاتورة أو اسم العميل..."
                                oninput="debouncedOriginalSalesInvoiceSearch()"
                                autocomplete="off"
                            >

                            <button
                                type="button"
                                class="btn btn-outline-secondary"
                                id="btnClearOriginalSalesInvoiceSearch"
                                onclick="clearOriginalSalesInvoiceSearch()"
                                title="تفريغ البحث"
                            >
                                <i class="bi bi-x-lg"></i>
                            </button>

                        </div>
                    </div>
                </div>

                <div class="table-responsive" style="max-height: 450px;">
                    <table class="table table-bordered table-hover align-middle mb-0">

                        <thead class="table-light sticky-top">
                            <tr class="text-center">
                                <th>رقم الفاتورة</th>
                                <th>التاريخ</th>
                                <th>العميل</th>
                                <th>العملة</th>
                                <th>طريقة الدفع</th>
                                <th>الإجمالي</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>

                        <tbody id="originalSalesInvoiceSearchResults">
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <span class="spinner-border spinner-border-sm me-2"></span>
                                    جاري التحميل...
                                </td>
                            </tr>
                        </tbody>

                    </table>
                </div>

                <div class="text-muted small mt-2">
                    <i class="bi bi-info-circle me-1"></i>
                    اضغط على صف الفاتورة لاختيارها، ويجب أن تكون الفاتورة مرحّلة.
                </div>

            </div>
        </div>
    </div>
</div>


{{-- ===================================================== --}}
{{-- نافذة البحث عن مرتجع بيع --}}
{{-- ===================================================== --}}
<div
    class="modal fade"
    id="salesReturnSearchModal"
    tabindex="-1"
    aria-hidden="true"
>
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title text-danger">
                    <i class="bi bi-search me-2"></i>
                    البحث عن مرتجع بيع
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="إغلاق"
                ></button>
            </div>

            <div class="modal-body">

                {{-- حقل البحث مع زر التفريغ --}}
                <div class="row g-2 mb-3">
                    <div class="col-md-12">
                        <div class="input-group">

                            <span class="input-group-text bg-white">
                                <i class="bi bi-search text-muted"></i>
                            </span>

                            <input
                                type="text"
                                class="form-control"
                                id="salesReturnSearchInput"
                                placeholder="أدخل رقم المرتجع أو رقم الفاتورة أو اسم العميل..."
                                oninput="debouncedSalesReturnSearch()"
                                autocomplete="off"
                            >

                            <button
                                type="button"
                                class="btn btn-outline-secondary"
                                id="btnClearSalesReturnSearch"
                                onclick="clearSalesReturnSearch()"
                                title="تفريغ البحث"
                            >
                                <i class="bi bi-x-lg"></i>
                            </button>

                        </div>
                    </div>
                </div>

                <div class="table-responsive" style="max-height: 450px;">
                    <table class="table table-bordered table-hover align-middle mb-0">

                        <thead class="table-light sticky-top">
                            <tr class="text-center">
                                <th>رقم المرتجع</th>
                                <th>التاريخ</th>
                                <th>الفاتورة الأصلية</th>
                                <th>العميل</th>
                                <th>طريقة الدفع</th>
                                <th>الإجمالي</th>
                                <th>الحالة</th>
                                <th style="min-width: 180px;">إجراءات</th>
                            </tr>
                        </thead>

                        <tbody id="salesReturnSearchResults">
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <span class="spinner-border spinner-border-sm me-2"></span>
                                    جاري التحميل...
                                </td>
                            </tr>
                        </tbody>

                    </table>
                </div>

            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
    @vite(['resources/js/pages/sales-return.js'])
@endpush

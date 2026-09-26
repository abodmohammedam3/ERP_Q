@extends('layouts.app')

@section('title', 'فواتير الشراء | نظام ERP')

@section('content')

<div class="container-fluid py-3">

    <!-- عنوان الشاشة والأزرار -->
    <div class="d-flex justify-content-between align-items-center mb-3">

        <div>
            <h4 class="mb-1">فاتورة شراء</h4>
        </div>

        <div class="btn-group" role="group">

            <button
                type="button"
                class="btn btn-primary"
                id="btnNewInvoice"
                onclick="resetInvoice()"
            >
                <i class="bi bi-plus-lg"></i>
                إضافة فاتورة
            </button>

            <button
                type="button"
                class="btn btn-outline-secondary"
                id="btnSearchInvoice"
                onclick="searchInvoice()"
            >
                <i class="bi bi-search"></i>
                بحث
            </button>

        </div>

    </div>

    <!-- رأس + تفاصيل الفاتورة -->
    @include('operation.purchases.invoicesPurch.detals')
    @include('shared.lookup-modal')

</div>


<!-- ===================================================== -->
<!-- نافذة البحث عن الفاتورة — محسّنة -->
<!-- ===================================================== -->

<div
    class="modal fade"
    id="invoiceSearchModal"
    tabindex="-1"
    aria-labelledby="invoiceSearchModalLabel"
    aria-hidden="true"
>
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="invoiceSearchModalLabel">
                    <i class="bi bi-search"></i>
                    البحث عن فاتورة شراء
                </h5>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="إغلاق"
                ></button>
            </div>

            <div class="modal-body">

                <!-- ✅ حقل بحث واحد موسّع — بحث فوري -->
                <div class="mb-3">
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-white">
                            <i class="bi bi-search text-primary"></i>
                        </span>
                        <input
                            type="text"
                            class="form-control"
                            id="invoiceSearchInput"
                            placeholder="ابحث برقم الفاتورة، اسم المورد، الصنف، النوع، الرمز، طريقة الدفع..."
                            oninput="debouncedInvoiceSearch()"
                            autocomplete="off"
                        >
                    </div>
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i>
                        النتائج تظهر تلقائيًا أثناء الكتابة
                    </small>
                </div>

                <div class="table-responsive" style="max-height: 450px;">
                    <table class="table table-bordered table-hover align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr class="text-center">
                                <th>رقم الفاتورة</th>
                                <th>التاريخ</th>
                                <th>المورد</th>
                                <th>العملة</th>
                                <th>طريقة الدفع</th>
                                <th>الإجمالي</th>
                                <th>اختيار</th>
                            </tr>
                        </thead>
                        <tbody id="invoiceSearchResults">
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
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
<script>
    window.PURCHASE_SYSTEM_CURRENCY_ID   = @json($systemCurrencyId ?? null);
    window.PURCHASE_SYSTEM_CURRENCY_CODE = @json($systemCurrencyCode ?? '');
</script>
@vite(['resources/js/pages/purchase-invoice.js'])
@endpush
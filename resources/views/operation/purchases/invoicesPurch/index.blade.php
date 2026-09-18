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
<!-- نافذة البحث عن الفاتورة -->
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

                <div class="row g-2 mb-3">
                    <div class="col-md-10">
                        <input
                            type="text"
                            class="form-control"
                            id="invoiceSearchInput"
                            placeholder="أدخل رقم الفاتورة أو اسم المورد..."
                            onkeydown="if(event.key==='Enter') performInvoiceSearch()"
                        >
                    </div>
                    <div class="col-md-2">
                        <button
                            type="button"
                            class="btn btn-primary w-100"
                            onclick="performInvoiceSearch()"
                        >
                            <i class="bi bi-search"></i>
                            بحث
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light">
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
                                    أدخل بيانات البحث ثم اضغط بحث
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
<script defer src="{{ asset('js/shared/lookup.js') }}"></script>
<script defer src="{{ asset('js/purchase_invoice.js') }}"></script>


@endpush
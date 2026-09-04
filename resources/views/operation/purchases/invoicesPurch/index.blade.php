@extends('layouts.app')

@section('title', 'فواتير الشراء | نظام ERP')

@section('content')

<div class="container-fluid py-3"><!-- ========================= -->
<!-- عنوان الشاشة والأزرار -->
<!-- ========================= -->

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


<!-- ========================= -->
<!-- رأس الفاتورة -->
<!-- ========================= -->

@include('operation.purchases.invoicesPurch.haed')


<!-- ========================= -->
<!-- تفاصيل الفاتورة -->
<!-- ========================= -->

@include('operation.purchases.invoicesPurch.detals')

</div><!-- ===================================================== --><!-- نافذة البحث عن الفاتورة --><!-- ===================================================== --><div
    class="modal fade"
    id="invoiceSearchModal"
    tabindex="-1"
    aria-labelledby="invoiceSearchModalLabel"
    aria-hidden="true"
><div class="modal-dialog modal-xl modal-dialog-centered">

    <div class="modal-content">

        <div class="modal-header">

            <h5
                class="modal-title"
                id="invoiceSearchModalLabel"
            >
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

                            <td
                                colspan="7"
                                class="text-center text-muted py-4"
                            >
                                أدخل بيانات البحث ثم اضغط بحث
                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

</div><!-- ===================================================== --><!-- نافذة اختيار المورد --><!-- ===================================================== --><div
    class="modal fade"
    id="supplierModal"
    tabindex="-1"
    aria-labelledby="supplierModalLabel"
    aria-hidden="true"
><div class="modal-dialog modal-lg modal-dialog-centered">

    <div class="modal-content">

        <div class="modal-header">

            <h5
                class="modal-title"
                id="supplierModalLabel"
            >
                <i class="bi bi-person"></i>
                اختيار المورد
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
                        id="supplierSearchInput"
                        placeholder="اسم المورد أو الرقم المحاسبي"
                    >

                </div>

                <div class="col-md-2">

                    <button
                        type="button"
                        class="btn btn-primary w-100"
                        onclick="searchSuppliers()"
                    >
                        بحث
                    </button>

                </div>

            </div>


            <div class="table-responsive">

                <table class="table table-bordered table-hover">

                    <thead class="table-light">

                        <tr class="text-center">

                            <th>الرقم</th>
                            <th>اسم المورد</th>
                            <th>الرقم المحاسبي</th>
                            <th>اختيار</th>

                        </tr>

                    </thead>

                    <tbody id="supplierResults">

                        <tr>

                            <td
                                colspan="4"
                                class="text-center text-muted py-3"
                            >
                                لا توجد نتائج
                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

<!-- ===================================================== -->
<!-- نافذة اختيار الصنف -->
<!-- ===================================================== -->

<div
    class="modal fade"
    id="salesItemModal"
    tabindex="-1"
    aria-labelledby="salesItemModalLabel"
    aria-hidden="true"
>

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">

                <h5
                    class="modal-title"
                    id="salesItemModalLabel"
                >
                    <i class="bi bi-box-seam"></i>
                    اختيار الصنف
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
                            id="salesItemSearchInput"
                            placeholder="اسم الصنف"
                        >

                    </div>

                    <div class="col-md-2">

                        <button
                            type="button"
                            class="btn btn-primary w-100"
                            onclick="searchSalesItems()"
                        >
                            بحث
                        </button>

                    </div>

                </div>


                <div class="table-responsive">

                    <table class="table table-bordered table-hover">

                        <thead class="table-light">

                            <tr class="text-center">

                                <th>الرقم</th>
                                <th>الصنف</th>
                                <th>الرمز</th>
                                <th>اختيار</th>

                            </tr>

                        </thead>

                        <tbody id="salesItemResults"></tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>

</div>

</div><!-- ===================================================== --><!-- نافذة اختيار العملة --><!-- ===================================================== --><div
    class="modal fade"
    id="currencyModal"
    tabindex="-1"
    aria-labelledby="currencyModalLabel"
    aria-hidden="true"
><div class="modal-dialog modal-lg modal-dialog-centered">

    <div class="modal-content">

        <div class="modal-header">

            <h5
                class="modal-title"
                id="currencyModalLabel"
            >
                <i class="bi bi-currency-exchange"></i>
                اختيار العملة
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
                        id="currencySearchInput"
                        placeholder="اسم العملة أو رمزها"
                    >

                </div>

                <div class="col-md-2">

                    <button
                        type="button"
                        class="btn btn-primary w-100"
                        onclick="searchCurrencies()"
                    >
                        بحث
                    </button>

                </div>

            </div>


            <div class="table-responsive">

                <table class="table table-bordered table-hover">

                    <thead class="table-light">

                        <tr class="text-center">

                            <th>الرقم</th>
                            <th>العملة</th>
                            <th>الرمز</th>
                            <th>سعر الصرف</th>
                            <th>اختيار</th>

                        </tr>

                    </thead>

                    <tbody id="currencyResults">

                        <tr>

                            <td
                                colspan="5"
                                class="text-center text-muted py-3"
                            >
                                لا توجد نتائج
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

<script src="{{ asset('js/purchase_invoice.js') }}"></script>
<script src="{{ asset('js/sales/invoice/sales_invoice_item.js') }}"></script>

@endpush
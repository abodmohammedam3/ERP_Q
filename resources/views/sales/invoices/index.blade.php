@extends('layouts.app')

@section('title', 'فواتير البيع | نظام ERP')

@section('content')
<div class="container-fluid py-3">

    <!-- ===================================== -->
    <!-- عنوان الشاشة -->
    <!-- ===================================== -->

    <div class="d-flex justify-content-between align-items-center mb-3">

        <div>
            <h4 class="mb-1">فواتير البيع</h4>
            <small class="text-muted">
                إدارة فواتير البيع 
            </small>
        </div>

        <div class="btn-group" role="group">

            <button
                type="button"
                class="btn btn-primary"
                id="btnNewSalesInvoice">

                <i class="bi bi-plus-lg"></i>
                فاتورة بيع جديدة

            </button>

            <button
                type="button"
                class="btn btn-outline-secondary"
                id="btnSearchSalesInvoice">

                <i class="bi bi-search"></i>
                بحث

            </button>

            <button
                type="button"
                class="btn btn-outline-secondary"
                id="btnRefreshSalesInvoice">

                <i class="bi bi-arrow-clockwise"></i>
                تحديث

            </button>

        </div>

    </div>

 @include('sales.invoices.header')
 @include('sales.invoices.detalis')

    <!-- ===================================== -->
    <!-- أزرار العمليات بعد الحفظ -->
    <!-- ===================================== -->

    <div class="card mt-3">

        <div class="card-header">
            <strong>عمليات الفاتورة</strong>
        </div>

        <div class="card-body">

            <div class="d-flex flex-wrap gap-2">

                <button
                    type="button"
                    class="btn btn-outline-warning"
                    id="btnEditSalesInvoice">

                    <i class="bi bi-pencil"></i>
                    تعديل

                </button>


                <!-- <button
                    type="button"
                    class="btn btn-outline-danger"
                    id="btnDeleteSalesInvoice">

                    <i class="bi bi-trash"></i>
                    حذف

                </button> -->


                <button
                    type="button"
                    class="btn btn-outline-primary"
                    onclick="approveAndPrint()"
                    id="btnPrintSalesInvoice">

                    <i class="bi bi-printer"></i>
                    طباعة

                </button>


                <button
                    type="button"
                    class="btn btn-outline-secondary"
                    id="btnPreviewSalesInvoice">

                    <i class="bi bi-eye"></i>
                    معاينة

                </button>


                <button
                    type="button"
                    class="btn btn-outline-info"
                    id="btnCustomerAccount">

                    <i class="bi bi-person-lines-fill"></i>
                    حساب العميل

                </button>


                <button
                    type="button"
                    class="btn btn-outline-dark"
                    id="btnStockMovement">

                    <i class="bi bi-box-seam"></i>
                    حركة المخزون

                </button>


                <button
                    type="button"
                    class="btn btn-outline-danger"
                    id="btnSalesReturn">
                    <i class="bi bi-arrow-return-right"></i>
                    مرتجع بيع

                </button>


                <button
                    type="button"
                    class="btn btn-outline-success"
                    id="btnJournalEntry">

                    <i class="bi bi-journal-text"></i>
                    القيد المحاسبي

                </button>


                <button
                    type="button"
                    class="btn btn-outline-secondary"
                     onclick="resetForm()" title="فاتوره جديدة"
                    id="btnNewAfterSave">

                    <i class="bi bi-plus-circle"></i>
                    فاتورة جديدة

                </button>

            </div>

        </div>

    </div>

</div>
@endsection

<?php $__env->startSection('title', 'مرتجعات المشتريات | نظام ERP'); ?>

<?php $__env->startSection('content'); ?>

<div class="container-fluid py-3">

    <!-- عنوان الشاشة والأزرار -->
    <div class="d-flex justify-content-between align-items-center mb-3">

        <div>
            <h4 class="mb-1 text-danger">
                <i class="bi bi-arrow-return-right me-2"></i>
                مرتجع شراء
            </h4>
            <small class="text-muted">إدارة فواتير مرتجعات المشتريات للموردين</small>
        </div>

        <div class="btn-group" role="group">

            <button
                type="button"
                class="btn btn-primary"
                id="btnNewPurchaseReturn"
                onclick="resetPurchaseReturn()"
            >
                <i class="bi bi-plus-lg"></i>
                مرتجع جديد
            </button>

            <button
                type="button"
                class="btn btn-outline-secondary"
                id="btnSearchPurchaseReturn"
                onclick="searchPurchaseReturn()"
            >
                <i class="bi bi-search"></i>
                بحث في المرتجعات
            </button>

        </div>

    </div>

    <!-- رأس المرتجع -->
    <?php echo $__env->make('operation.purchases.returns.header', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <!-- تفاصيل المرتجع -->
    <?php echo $__env->make('operation.purchases.returns.details', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

</div>


<!-- ===================================================== -->
<!-- نافذة البحث عن فاتورة شراء أصلية -->
<!-- ===================================================== -->
<div class="modal fade" id="originalPurchaseInvoiceSearchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-search me-2"></i>
                    البحث عن فاتورة شراء أصلية
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <!-- ✅ حقل البحث مع زر التفريغ -->
                <div class="row g-2 mb-3">
                    <div class="col-md-12">
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="bi bi-search text-muted"></i>
                            </span>
                            <input
                                type="text"
                                class="form-control"
                                id="originalPurchaseInvoiceSearchInput"
                                placeholder="أدخل رقم الفاتورة أو اسم المورد..."
                                oninput="debouncedOriginalPurchaseInvoiceSearch()"
                                autocomplete="off"
                            >
                            <button
                                type="button"
                                class="btn btn-outline-secondary"
                                id="btnClearOriginalInvoiceSearch"
                                onclick="clearOriginalPurchaseInvoiceSearch()"
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
                                <th>المورد</th>
                                <th>العملة</th>
                                <th>طريقة الدفع</th>
                                <th>الإجمالي</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody id="originalPurchaseInvoiceSearchResults">
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


<!-- ===================================================== -->
<!-- نافذة البحث عن مرتجع شراء -->
<!-- ===================================================== -->
<div class="modal fade" id="purchaseReturnSearchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title text-danger">
                    <i class="bi bi-search me-2"></i>
                    البحث عن مرتجع شراء
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <!-- ✅ حقل البحث مع زر التفريغ -->
                <div class="row g-2 mb-3">
                    <div class="col-md-12">
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="bi bi-search text-muted"></i>
                            </span>
                            <input
                                type="text"
                                class="form-control"
                                id="purchaseReturnSearchInput"
                                placeholder="أدخل رقم المرتجع أو رقم الفاتورة أو اسم المورد..."
                                oninput="debouncedPurchaseReturnSearch()"
                                autocomplete="off"
                            >
                            <button
                                type="button"
                                class="btn btn-outline-secondary"
                                id="btnClearPurchaseReturnSearch"
                                onclick="clearPurchaseReturnSearch()"
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
                                <th>المورد</th>
                                <th>طريقة الدفع</th>
                                <th>الإجمالي</th>
                                <th>الحالة</th>
                                <th style="min-width: 180px;">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody id="purchaseReturnSearchResults">
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

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <?php echo app('Illuminate\Foundation\Vite')(['resources/js/pages/purchase-return.js']); ?>
<?php $__env->stopPush(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Laravel\qaatSystem\resources\views/operation/purchases/returns/index.blade.php ENDPATH**/ ?>
{{-- ═══════════════════════════════════════════════════════════
     نوافذ أرصدة المخزون:
       - sortingFromBalanceModal (فرز / تحويل وحدات)
       - pricingEditModal        (تعديل التسعير)
     ═══════════════════════════════════════════════════════════ --}}


{{-- ===================================================== --}}
{{-- نافذة الفرز / تحويل الوحدات                         --}}
{{-- ===================================================== --}}
<div class="modal fade" id="sortingFromBalanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-scissors text-primary me-2"></i>
                    فرز / تحويل وحدات
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>

            <div class="modal-body">

                <div class="alert alert-info py-2 mb-3">
                    <div class="d-flex justify-content-between">
                        <span><strong>الصنف:</strong> <span id="sortingItemName">—</span></span>
                        <span><strong>النوع:</strong> <span id="sortingTypeName">—</span></span>
                    </div>
                    <div class="d-flex justify-content-between mt-1">
                        <span><strong>المخزن:</strong> <span id="sortingWarehouseName">—</span></span>
                        <span><strong>الكمية المتاحة:</strong> <span id="sortingAvailable">0</span> <span id="sortingAvailableUnit"></span></span>
                    </div>
                    <div class="d-flex justify-content-between mt-1">
                        <span><strong>تكلفة الوحدة:</strong> <span id="sortingUnitCost">0</span></span>
                    </div>
                </div>

                {{-- الوحدات: من / إلى --}}
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">من وحدة <span class="text-danger">*</span></label>
                        <select id="sortingFromUnit" class="form-select" onchange="calculateSortingFromBalance()"></select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">إلى وحدة <span class="text-danger">*</span></label>
                        <select id="sortingToUnit" class="form-select" onchange="calculateSortingFromBalance()"></select>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">الكمية المصدر <span class="text-danger">*</span></label>
                        <input
                            type="number"
                            class="form-control"
                            id="sortingInputQty"
                            step="0.001"
                            min="0.001"
                            oninput="calculateSortingFromBalance()"
                        >
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">الكمية الناتجة <span class="text-danger">*</span></label>
                        <input
                            type="number"
                            class="form-control"
                            id="sortingOutputQty"
                            step="0.001"
                            min="0.001"
                            oninput="calculateSortingFromBalance()"
                        >
                    </div>
                </div>

                <div class="alert alert-success mt-3 py-2">
                    <div class="d-flex justify-content-between">
                        <span>تكلفة الوحدة الناتجة:</span>
                        <strong id="sortingResultUnitCost">0.00</strong>
                    </div>
                    <div class="d-flex justify-content-between mt-1">
                        <span>القيمة الإجمالية:</span>
                        <strong id="sortingResultTotal">0.00</strong>
                    </div>
                </div>

                <div class="row g-3 mt-2">
                    <div class="col-md-4">
                        <label class="form-label">سعر البيع للوحدة الناتجة</label>
                        <input type="number" class="form-control" id="sortingSalePrice" step="0.01" min="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">الحد الأدنى</label>
                        <input type="number" class="form-control" id="sortingMinPrice" step="0.01" min="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">الحد الأعلى</label>
                        <input type="number" class="form-control" id="sortingMaxPrice" step="0.01" min="0">
                    </div>
                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" id="btnSaveSorting" onclick="saveSortingFromBalance()">
                    <i class="bi bi-check-lg"></i>
                    حفظ الفرز
                </button>
            </div>

        </div>
    </div>
</div>


{{-- ===================================================== --}}
{{-- نافذة تعديل التسعير (سعر البيع + الحد الأعلى فقط)    --}}
{{-- ===================================================== --}}
<div class="modal fade" id="pricingEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-pencil text-info me-2"></i>
                    تعديل التسعير
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>

            <div class="modal-body">

                <div class="alert alert-info py-2 mb-3 small">
                    <div class="d-flex justify-content-between">
                        <span><strong>الصنف:</strong> <span id="pricingItemName">—</span></span>
                    </div>
                    <div class="d-flex justify-content-between mt-1">
                        <span><strong>المخزن:</strong> <span id="pricingWarehouseName">—</span></span>
                    </div>
                    <div class="d-flex justify-content-between mt-1">
                        <span><strong>التكلفة:</strong> <span id="pricingUnitCost">0</span></span>
                        <span><strong>الحد الأدنى:</strong> <span id="pricingMinPrice">0</span></span>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">سعر البيع</label>
                    <input type="number" class="form-control" id="pricingSalePrice" step="0.01" min="0">
                </div>

                <div class="mb-2">
                    <label class="form-label">الحد الأعلى</label>
                    <input type="number" class="form-control" id="pricingMaxPrice" step="0.01" min="0">
                </div>

                <div class="form-text">
                    يُسمح بتعديل سعر البيع والحد الأعلى فقط — الحد الأدنى يُحدَّد من فاتورة الشراء.
                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-info" id="btnSavePricing" onclick="savePricingEdit()">
                    <i class="bi bi-check-lg"></i>
                    حفظ
                </button>
            </div>

        </div>
    </div>
</div>
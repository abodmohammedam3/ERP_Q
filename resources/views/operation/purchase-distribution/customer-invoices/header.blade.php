<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <div class="d-flex align-items-center gap-3">
        <div class="bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center"
             style="width:52px; height:52px;">
            <i class="bi bi-receipt fs-4"></i>
        </div>
        <div>
            <h5 class="mb-0 fw-bold">إصدار فواتير العملاء</h5>
            <div class="text-muted small">اختر عميلًا، راجع توزيعاته غير المفوترة، ثم أصدر الفاتورة</div>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('purchases.distribution.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-right-circle"></i> شاشة التوزيع
        </a>
        <a href="{{ route('purchases.distribution.history') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-clock-history"></i> سجل التوزيعات
        </a>
    </div>
</div>
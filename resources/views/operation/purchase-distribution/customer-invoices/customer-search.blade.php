<div class="row g-2 mb-3">
    {{-- التوزيع --}}
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex justify-content-between align-items-center gap-2 py-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="bg-primary-subtle text-primary rounded-2 px-2 py-1">
                        <i class="bi bi-diagram-3"></i>
                    </span>
                    <div>
                        <div class="text-muted small">التوزيع المختار</div>
                        <div class="fw-bold" id="selectedDistributionLabel">لم يتم اختيار توزيع</div>
                    </div>
                </div>
                <button id="btnPickDistribution" class="btn btn-sm btn-primary">
                    <i class="bi bi-search"></i> اختيار توزيع
                </button>
            </div>
        </div>
    </div>

    {{-- العميل --}}
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex justify-content-between align-items-center gap-2 py-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="bg-success-subtle text-success rounded-2 px-2 py-1">
                        <i class="bi bi-person-circle"></i>
                    </span>
                    <div>
                        <div class="text-muted small">العميل المختار</div>
                        <div class="fw-bold" id="selectedCustomerLabel">اختر توزيعًا أولًا</div>
                    </div>
                </div>
                <button id="btnPickCustomer" class="btn btn-sm btn-outline-success" disabled>
                    <i class="bi bi-person-plus"></i> اختيار عميل
                </button>
            </div>
        </div>
    </div>
</div>
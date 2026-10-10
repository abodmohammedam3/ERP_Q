<div class="row g-3 mb-3">

    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rc-title-icon">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div>
                    <small class="text-muted d-block mb-1">آخر نسخة احتياطية</small>
                    <strong class="d-block fs-5" id="sbLastBackupDate">—</strong>
                    <small class="text-success" id="sbLastBackupTrigger">
                        <i class="bi bi-check-circle-fill"></i>
                        لا توجد
                    </small>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rc-title-icon" style="background:#e8f5e9;color:#198754">
                    <i class="bi bi-collection"></i>
                </div>
                <div>
                    <small class="text-muted d-block mb-1">عدد النسخ</small>
                    <strong class="d-block fs-5" id="sbTotalCount">0</strong>
                    <small class="text-muted">
                        الحد الأقصى: <span id="sbRetentionLimit">7</span>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rc-title-icon" style="background:#fff8e1;color:#f57c00">
                    <i class="bi bi-hdd-stack"></i>
                </div>
                <div>
                    <small class="text-muted d-block mb-1">الحجم الإجمالي</small>
                    <strong class="d-block fs-5" id="sbTotalSize">0 B</strong>
                    <small class="text-muted">على السيرفر</small>
                </div>
            </div>
        </div>
    </div>

</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">

        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-activity text-primary"></i>
                <strong>حفظ التقدم التلقائي</strong>
            </div>
            <small class="text-muted" id="sbActivityLastAt">لا يوجد نشاط</small>
        </div>

        <div class="progress mb-2" style="height: 10px;">
            <div class="progress-bar bg-success"
                 id="sbActivityProgress"
                 role="progressbar"
                 style="width: 0%"></div>
        </div>

        <div class="d-flex justify-content-between align-items-center">
            <small class="text-muted">
                عمليات منذ آخر حفظ:
                <strong id="sbActivityCount">0</strong> /
                <span id="sbActivityThreshold">100</span>
            </small>
            <small class="text-muted">
                <i class="bi bi-info-circle"></i>
                حفظ تلقائي عند: 100 عملية، 15 دقيقة خمول، 2 صباحاً
            </small>
        </div>

    </div>
</div>

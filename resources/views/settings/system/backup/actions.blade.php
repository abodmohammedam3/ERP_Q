<div class="card border-0 shadow-sm mb-3">
    <div class="card-body d-flex flex-wrap gap-2 justify-content-between align-items-center">

        <div class="d-flex flex-wrap gap-2">

            <button type="button" class="btn btn-primary" id="sbBtnExport">
                <i class="bi bi-download"></i>
                تصدير نسخة
            </button>

            <button type="button" class="btn btn-outline-warning" id="sbBtnImport">
                <i class="bi bi-upload"></i>
                استيراد نسخة
            </button>

            <button type="button" class="btn btn-outline-danger" id="sbBtnCleanup">
                <i class="bi bi-trash3"></i>
                تنظيف القديم
            </button>

        </div>

        <button type="button" class="btn btn-outline-secondary btn-sm" id="sbBtnRefresh">
            <i class="bi bi-arrow-clockwise"></i>
            تحديث
        </button>

    </div>
</div>

<div class="card border-0 shadow-sm mb-3 d-none" id="sbProgressContainer">
    <div class="card-body">

        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="d-flex align-items-center gap-2">
                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                <strong id="sbProgressTitle">جاري تنفيذ العملية...</strong>
            </div>
            <span class="badge bg-primary" id="sbProgressPercent">0%</span>
        </div>

        <div class="progress mb-2" style="height: 8px;">
            <div class="progress-bar progress-bar-striped progress-bar-animated"
                 id="sbProgressBar"
                 role="progressbar"
                 style="width: 0%"></div>
        </div>

        <small class="text-muted" id="sbProgressStage">جاري التحضير...</small>

    </div>
</div>

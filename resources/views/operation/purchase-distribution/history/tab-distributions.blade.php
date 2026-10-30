<div id="tab-distributions">

    {{-- الفلاتر --}}
    <div class="row g-2 mb-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label small text-muted mb-1">بحث برقم التوزيع</label>
            <input type="text" class="form-control form-control-sm"
                   id="fSearch" placeholder="مثال: DIST-2026-0001">
        </div>
        <div class="col-md-2">
            <label class="form-label small text-muted mb-1">من تاريخ</label>
            <input type="date" class="form-control form-control-sm" id="fDateFrom">
        </div>
        <div class="col-md-2">
            <label class="form-label small text-muted mb-1">إلى تاريخ</label>
            <input type="date" class="form-control form-control-sm" id="fDateTo">
        </div>
        <div class="col-md-2">
            <label class="form-label small text-muted mb-1">الحالة</label>
            <select class="form-select form-select-sm" id="fStatus">
                <option value="">الكل</option>
                <option value="draft">مسودة</option>
                <option value="invoiced">مفوتر</option>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button class="btn btn-sm btn-primary flex-fill" id="btnRefresh">
                <i class="bi bi-search"></i> بحث
            </button>
            <button class="btn btn-sm btn-outline-secondary flex-fill" id="btnClear">
                <i class="bi bi-x-circle"></i> تفريغ
            </button>
        </div>
    </div>

    {{-- الجدول --}}
    <div class="table-responsive border rounded-3" style="max-height:520px; overflow-y:auto;">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light sticky-top">
                <tr class="small text-muted">
                    <th style="width:36px">#</th>
                    <th>رقم التوزيع</th>
                    <th>التاريخ</th>
                    <th>الحالة</th>
                    <th class="text-center">صفوف</th>
                    <th class="text-center">عملاء</th>
                    <th class="text-end">تكلفة</th>
                    <th class="text-end">بيع</th>
                    <th class="text-center">عرض</th>
                </tr>
            </thead>
            <tbody id="distTbody">
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">جارٍ التحميل...</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
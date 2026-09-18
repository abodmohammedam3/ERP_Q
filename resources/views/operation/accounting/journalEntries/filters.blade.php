<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">

            {{-- البحث --}}
            <div class="col-md-4">
                <label class="form-label small mb-1">البحث</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text"
                           id="jeSearch"
                           class="form-control"
                           placeholder="رقم القيد، البيان، المستند..."
                           autocomplete="off">
                </div>
            </div>

            {{-- من تاريخ --}}
            <div class="col-md-2">
                <label class="form-label small mb-1">من تاريخ</label>
                <input type="date"
                       id="jeDateFrom"
                       class="form-control form-control-sm">
            </div>

            {{-- إلى تاريخ --}}
            <div class="col-md-2">
                <label class="form-label small mb-1">إلى تاريخ</label>
                <input type="date"
                       id="jeDateTo"
                       class="form-control form-control-sm">
            </div>

            {{-- نوع المستند --}}
            <div class="col-md-2">
                <label class="form-label small mb-1">نوع المستند</label>
                <select id="jeDocType" class="form-select form-select-sm">
                    <option value="">الكل</option>
                    <option value="قيد افتتاحي">قيد افتتاحي</option>
                    <option value="سند قبض">سند قبض</option>
                    <option value="سند صرف">سند صرف</option>
                    <option value="فاتورة بيع">فاتورة بيع</option>
                    <option value="فاتورة شراء">فاتورة شراء</option>
                    <option value="مرتجع بيع">مرتجع بيع</option>
                    <option value="مرتجع شراء">مرتجع شراء</option>
                </select>
            </div>

            {{-- أزرار --}}
            <div class="col-md-2">
                <div class="d-flex gap-1">
                    <button type="button"
                            class="btn btn-sm btn-primary flex-grow-1"
                            id="jeBtnApply">
                        <i class="bi bi-funnel"></i> تطبيق
                    </button>
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary"
                            id="jeBtnReset"
                            title="إعادة تعيين">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>
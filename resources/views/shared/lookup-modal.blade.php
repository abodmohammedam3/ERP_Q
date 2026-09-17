{{-- =========================================================
     النافذة الموحّدة للنظام (Lookup Modal)
     تُستخدم لاختيار: المورد / العميل / العملة / المخزن / الصنف / النوع / الوحدة / الصندوق / البنك
     ========================================================= --}}

<div class="modal fade" id="unifiedLookupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:14px;">

            <div class="modal-header bg-light border-0" style="border-radius:14px 14px 0 0;">
                <h5 class="modal-title fw-bold" id="unifiedLookupTitle">
                    <i class="bi bi-search text-primary me-2"></i> اختيار
                </h5>
                <button
                    type="button"
                    class="btn-close"
                    style="margin-right: auto; margin-left: 0;"
                    data-bs-dismiss="modal"
                    aria-label="إغلاق"
                ></button>
            </div>

            <div class="modal-body p-3">

                <div class="input-group mb-3">
                    <span class="input-group-text bg-white">
                        <i class="bi bi-search"></i>
                    </span>
                    <input
                        type="text"
                        class="form-control"
                        id="unifiedLookupSearch"
                        placeholder="ابحث..."
                        autocomplete="off"
                        oninput="lookupSearchInput(event)"
                        onkeydown="lookupSearchKeyDown(event)"
                    >
                </div>

                <div class="table-responsive" style="max-height: 400px;">
                    <table class="table table-hover align-middle mb-0 lookup-table">
                        <colgroup id="unifiedLookupColgroup"></colgroup>
                        <thead class="table-light sticky-top">
                            <tr id="unifiedLookupHeader"></tr>
                        </thead>
                        <tbody id="unifiedLookupBody"></tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>
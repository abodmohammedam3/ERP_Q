{{-- =====================================================
     بطاقة عرض شجرة الحسابات التجميعية
===================================================== --}}

<div class="card mb-4" id="accountsTable">

    <div class="card-header bg-body border-bottom">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-diagram-3 text-primary"></i>
            <h6 class="mb-0 fw-bold">شجرة الحسابات التجميعية</h6>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>رقم الحساب</th>
                        <th>اسم الحساب</th>
                        <th>طبيعة الحساب</th>
                        <th>الحساب الأب</th>
                        <th>الحالة</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody id="accountsTreeBody">
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <div class="d-flex justify-content-center align-items-center gap-2">
                                <div class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></div>
                                <span>جاري تحميل الحسابات...</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-footer d-flex flex-wrap justify-content-between align-items-center" id="accountsPagination">
        <span class="text-muted small" id="accountsPaginationInfo">عرض 0-0 من 0 حساب</span>
        <nav aria-label="ترقيم الحسابات">
            <ul class="pagination pagination-sm mb-0" id="accountsPaginationList"></ul>
        </nav>
    </div>

</div>


{{-- ✅ FIX: المودال انتقل خارج content إلى @stack('modals') --}}
@push('modals')

<div
    class="modal fade"
    id="analyticalAccountsModal"
    tabindex="-1"
    aria-labelledby="analyticalAccountsModalLabel"
    aria-hidden="true"
>
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold" id="analyticalAccountsModalLabel">
                        الحسابات التحليلية
                    </h5>
                    <div class="text-muted small mt-1" id="analyticalAccountsParent">-</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>

            <div class="modal-body">

                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control" id="analyticalSearchCode" placeholder="بحث برقم الحساب">
                    </div>
                    <div class="col-md-4">
                        <input type="text" class="form-control" id="analyticalSearchName" placeholder="بحث باسم الحساب">
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" id="analyticalSearchNature">
                            <option value="">كل الطبائع</option>
                            <option value="0">مدين</option>
                            <option value="1">دائن</option>
                        </select>
                    </div>
                </div>

                <div class="table-responsive" style="max-height: 450px; overflow-y: auto;">
                    <table class="table table-striped table-hover mb-0">
                        <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                            <tr>
                                <th>#</th>
                                <th>رقم الحساب</th>
                                <th>اسم الحساب</th>
                                <th>طبيعة الحساب</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody id="analyticalAccountsBody">
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    اختر حسابًا لعرض الحسابات التحليلية
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>

            <div class="modal-footer d-flex flex-wrap justify-content-between align-items-center">
                <span class="text-muted small" id="analyticalPaginationInfo">عرض 0-0 من 0 حساب</span>
                <nav aria-label="ترقيم الحسابات التحليلية">
                    <ul class="pagination pagination-sm mb-0" id="analyticalPaginationList"></ul>
                </nav>
            </div>

        </div>
    </div>
</div>

@endpush
{{-- جدول العملات --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-list-ul"></i> قائمة العملات
        </span>
        <span class="badge bg-secondary" id="coinsCountBadge">
            {{ isset($coins) ? $coins->count() : 0 }}
        </span>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-bordered mb-0 align-middle" id="coinsTable">
                <thead class="table-light">
                    <tr class="text-center">
                        <th style="width: 60px;">الرقم</th>
                        <th>اسم العملة</th>
                        <th style="width: 120px;">الرمز</th>
                        <th style="width: 160px;">سعر الصرف</th>
                        <th style="width: 140px;">العملة الأساسية</th>
                        <th style="width: 120px;">الحالة</th>
                        <th style="width: 180px;" class="no-print">الإجراءات</th>
                    </tr>
                </thead>
                <tbody id="coinsTableBody">
                    @forelse($coins ?? [] as $coin)
                        <tr class="coin-row text-center"
                            data-id="{{ $coin->coinsID }}"
                            data-name="{{ $coin->coinsName }}"
                            data-code="{{ $coin->coinsCode }}"
                            data-rate="{{ $coin->coinsExchangeRate }}"
                            data-system="{{ $coin->coinsSystem ? 1 : 0 }}"
                            data-active="{{ $coin->is_active ? 1 : 0 }}">

                            <td>{{ $loop->iteration }}</td>
                            <td class="row-name text-center">{{ $coin->coinsName }}</td>
                            <td class="row-code">
                                <span class="badge bg-secondary">{{ $coin->coinsCode }}</span>
                            </td>
                            <td class="row-rate">{{ number_format($coin->coinsExchangeRate ?? 0, 6) }}</td>
                            <td class="row-system">
                                @if($coin->coinsSystem)
                                    <span class="badge bg-success">نعم</span>
                                @else
                                    <span class="badge bg-light text-dark border">لا</span>
                                @endif
                            </td>
                            <td class="row-status">
                                <button type="button"
                                        class="btn btn-sm {{ $coin->is_active ? 'btn-success' : 'btn-secondary' }} toggle-status-btn"
                                        onclick="toggleCoinStatus(this)"
                                        title="{{ $coin->is_active ? 'تعطيل' : 'تفعيل' }}">
                                    {{ $coin->is_active ? 'نشط' : 'غير نشط' }}
                                </button>
                            </td>
                            <td class="no-print">
                                <div class="btn-action-group">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="editCoin(this)" title="تعديل">
                                        <i class="bi bi-pencil d-md-none"></i>
                                        <span class="d-none d-md-inline">تعديل</span>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteCoin(this)" title="حذف">
                                        <i class="bi bi-trash d-md-none"></i>
                                        <span class="d-none d-md-inline">حذف</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr id="emptyCoinRow">
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-currency-exchange fs-2 d-block mb-2"></i>
                                لا توجد عملات مسجلة
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-footer d-flex justify-content-center align-items-center">
        <nav aria-label="Pagination">
            <ul class="pagination pagination-sm mb-0" id="coinsPaginationList"></ul>
        </nav>
    </div>
</div>



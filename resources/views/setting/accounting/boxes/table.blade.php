{{-- جدول الصناديق --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-list-ul"></i> قائمة الصناديق
        </span>
        <span class="badge bg-secondary" id="boxesCountBadge">
            {{ isset($boxes) ? $boxes->count() : 0 }}
        </span>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-bordered mb-0 align-middle" id="boxesTable">
                <thead class="table-light">
                    <tr class="text-center">
                        <th style="width: 60px;">الرقم</th>
                        <th>اسم الصندوق</th>
                        <th style="width: 160px;">العملة</th>
                        <th style="width: 140px;">سعر الصرف</th>
                        <th style="width: 140px;">رقم الحساب</th>
                        <th style="width: 120px;">الحالة</th>
                        <th style="width: 180px;" class="no-print">الإجراءات</th>
                    </tr>
                </thead>
                <tbody id="boxesTableBody">
                    @forelse($boxes ?? [] as $box)
                        <tr class="box-row text-center"
                            data-id="{{ $box->boxID }}"
                            data-name="{{ $box->boxName }}"
                            data-coin="{{ $box->coinsID }}"
                            data-active="{{ $box->is_active ? 1 : 0 }}">

                            <td>{{ $loop->iteration }}</td>
                            <td class="row-name text-center">{{ $box->boxName }}</td>
                            <td class="row-coin">
                                <span class="badge bg-secondary">
                                    {{ $box->coin->coinsCode ?? '—' }}
                                </span>
                            </td>
                            <td class="row-rate">{{ number_format($box->coin->coinsExchangeRate ?? 0, 6) }}</td>
                            <td class="row-account">{{ $box->account->accCode ?? '—' }}</td>
                            <td class="row-status">
                                <button type="button"
                                        class="btn btn-sm {{ $box->is_active ? 'btn-success' : 'btn-secondary' }} toggle-status-btn"
                                        onclick="toggleBoxStatus(this)"
                                        title="{{ $box->is_active ? 'تعطيل' : 'تفعيل' }}">
                                    {{ $box->is_active ? 'نشط' : 'غير نشط' }}
                                </button>
                            </td>
                            <td class="no-print">
                                <div class="btn-action-group">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="editBox(this)" title="تعديل">
                                        <i class="bi bi-pencil d-md-none"></i>
                                        <span class="d-none d-md-inline">تعديل</span>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteBox(this)" title="حذف">
                                        <i class="bi bi-trash d-md-none"></i>
                                        <span class="d-none d-md-inline">حذف</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr id="emptyBoxRow">
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-safe2 fs-2 d-block mb-2"></i>
                                لا توجد صناديق مسجلة
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-footer d-flex justify-content-center align-items-center">
        <nav aria-label="Pagination">
            <ul class="pagination pagination-sm mb-0" id="boxesPaginationList"></ul>
        </nav>
    </div>
</div>


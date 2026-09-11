<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list-ul"></i> قائمة البنوك</span>
        <span class="badge bg-secondary" id="banksCountBadge">
            {{ isset($banks) ? $banks->count() : 0 }}
        </span>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-bordered mb-0 align-middle" id="banksTable">
                <thead class="table-light">
                    <tr class="text-center">
                        <th style="width: 60px;">الرقم</th>
                        <th>اسم البنك</th>
                        <th style="width: 130px;">العملة</th>
                        <th style="width: 130px;">سعر الصرف</th>
                        <th style="width: 150px;">رقم الحساب البنكي</th>
                        <th style="width: 130px;">رقم الحساب المحاسبي</th>
                        <th style="width: 120px;">الحالة</th>
                        <th style="width: 180px;" class="no-print">الإجراءات</th>
                    </tr>
                </thead>
                <tbody id="banksTableBody">
                    @forelse($banks ?? [] as $bank)
                        <tr class="bank-row text-center"
                            data-id="{{ $bank->bankID }}"
                            data-name="{{ $bank->bankName }}"
                            data-coin="{{ $bank->coinsID }}"
                            data-account-number="{{ $bank->accountNumber }}"
                            data-account-code="{{ $bank->account->accCode ?? '' }}"
                            data-active="{{ $bank->is_active ? 1 : 0 }}"
                            @if(!$bank->coinsID) style="background-color: #fff3cd;" @endif>

                            <td>{{ $loop->iteration }}</td>
                            <td class="row-name text-center">{{ $bank->bankName }}</td>
                            <td class="row-coin">
                                @if($bank->coin)
                                    <span class="badge bg-secondary">{{ $bank->coin->coinsCode }}</span>
                                @else
                                    <span class="badge bg-warning text-dark">
                                        <i class="bi bi-exclamation-triangle-fill"></i> بلا عملة
                                    </span>
                                @endif
                            </td>
                            <td class="row-rate">
                                {{ $bank->coin ? number_format($bank->coin->coinsExchangeRate ?? 0, 6) : '—' }}
                            </td>
                            <td class="row-account-number">{{ $bank->accountNumber ?? '—' }}</td>
                            <td class="row-account">{{ $bank->account->accCode ?? '—' }}</td>
                            <td class="row-status">
                                <button type="button"
                                        class="btn btn-sm {{ $bank->is_active ? 'btn-success' : 'btn-secondary' }} toggle-status-btn"
                                        onclick="toggleBankStatus(this)"
                                        title="{{ $bank->is_active ? 'تعطيل' : 'تفعيل' }}">
                                    {{ $bank->is_active ? 'نشط' : 'غير نشط' }}
                                </button>
                            </td>
                            <td class="no-print">
                                <div class="btn-action-group">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="editBank(this)">
                                        <i class="bi bi-pencil d-md-none"></i>
                                        <span class="d-none d-md-inline">تعديل</span>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteBank(this)">
                                        <i class="bi bi-trash d-md-none"></i>
                                        <span class="d-none d-md-inline">حذف</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr id="emptyBankRow">
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="bi bi-bank fs-2 d-block mb-2"></i>
                                لا توجد بنوك مسجلة
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-footer d-flex justify-content-center align-items-center">
        <nav aria-label="Pagination">
            <ul class="pagination pagination-sm mb-0" id="banksPaginationList"></ul>
        </nav>
    </div>
</div>

{{-- ============================================================
     قوالب JS: تُستخدم من ملف banks.js لإعادة بناء الصفوف
     ============================================================ --}}

{{-- قالب صف البنك --}}
<template id="bankRowTemplate">
    <tr class="bank-row text-center">
        <td class="row-index"></td>

        <td class="row-name text-center"></td>

        <td class="row-coin"></td>

        <td class="row-rate"></td>

        <td class="row-account-number"></td>

        <td class="row-account"></td>

        <td class="row-status">
            <button type="button"
                    class="btn btn-sm toggle-status-btn"
                    onclick="toggleBankStatus(this)">
            </button>
        </td>

        <td class="no-print">
            <div class="btn-action-group">
                <button type="button"
                        class="btn btn-sm btn-outline-primary"
                        onclick="editBank(this)">
                    <i class="bi bi-pencil d-md-none"></i>
                    <span class="d-none d-md-inline">تعديل</span>
                </button>

                <button type="button"
                        class="btn btn-sm btn-outline-danger"
                        onclick="deleteBank(this)">
                    <i class="bi bi-trash d-md-none"></i>
                    <span class="d-none d-md-inline">حذف</span>
                </button>
            </div>
        </td>
    </tr>
</template>

{{-- قالب شارة رمز العملة (بنك مرتبط بعملة) --}}
<template id="bankCoinBadgeTemplate">
    <span class="badge bg-secondary"></span>
</template>

{{-- قالب شارة "بلا عملة" --}}
<template id="bankNoCoinBadgeTemplate">
    <span class="badge bg-warning text-dark">
        <i class="bi bi-exclamation-triangle-fill"></i> بلا عملة
    </span>
</template>

{{-- قالب نص "—" (بديل عام) --}}
<template id="bankDashTemplate">
    <span class="text-muted">—</span>
</template>

{{-- قالب صف "لا توجد بيانات" --}}
<template id="emptyBankRowTemplate">
    <tr>
        <td colspan="8" class="text-center text-muted py-5">
            <i class="bi bi-bank fs-2 d-block mb-2"></i>
            لا توجد بنوك مسجلة
        </td>
    </tr>
</template>
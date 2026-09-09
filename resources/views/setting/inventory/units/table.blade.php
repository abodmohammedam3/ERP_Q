{{-- جدول الوحدات --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-list-ul"></i> قائمة الوحدات
        </span>
        <span class="badge bg-secondary" id="unitsCountBadge">
            {{ isset($units) ? $units->count() : 0 }}
        </span>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-bordered mb-0 align-middle" id="unitsTable">
                <thead class="table-light">
                    <tr class="text-center">
                        <th style="width: 60px;">#</th>
                        <th>اسم الوحدة</th>
                        <th style="width: 120px;">الحالة</th>
                        <th style="width: 180px;" class="no-print">الإجراءات</th>
                    </tr>
                </thead>
                <tbody id="unitsTableBody">
                    {{-- عرض البيانات مباشرة من الخادم --}}
                    @forelse($units ?? [] as $unit)
                        <tr class="unit-row text-center" data-id="{{ $unit->UnitID }}">
                            <td>{{ $loop->iteration }}</td>
                            <td class="row-name">{{ $unit->UnitName }}</td>
                            <td class="row-status">
                                {{-- زر الحالة (نشط / غير نشط) --}}
                                <button type="button" 
                                        class="btn btn-sm {{ $unit->is_active ? 'btn-success' : 'btn-secondary' }} toggle-status-btn"
                                        onclick="toggleUnitStatus(this)"
                                        title="{{ $unit->is_active ? 'تعطيل' : 'تفعيل' }}">
                                    {{ $unit->is_active ? 'نشط' : 'غير نشط' }}
                                </button>
                            </td>
                            <td class="no-print">
                                <div class="btn-action-group">
                                    {{-- زر تعديل (نص + أيقونة) --}}
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="editUnit(this)" title="تعديل">
                                        <i class="bi bi-pencil d-md-none"></i>
                                        <span class="d-none d-md-inline">تعديل</span>
                                    </button>
                                    {{-- زر حذف (نص + أيقونة) --}}
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteUnit(this)" title="حذف">
                                        <i class="bi bi-trash d-md-none"></i>
                                        <span class="d-none d-md-inline">حذف</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr id="emptyUnitRow">
                            <td colspan="4" class="text-center text-muted py-5">
                                <i class="bi bi-rulers fs-2 d-block mb-2"></i>
                                لا توجد وحدات مسجلة
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-footer d-flex justify-content-center align-items-center">
        <nav aria-label="Pagination">
            <ul class="pagination pagination-sm mb-0" id="unitsPaginationList">
                {{-- سيتم ملؤها بواسطة JavaScript --}}
            </ul>
        </nav>
    </div>
</div>

{{-- أنماط إضافية للتجاوب --}}
<style>
    .btn-action-group {
        display: flex !important;
        justify-content: center !important;
        align-items: center !important;
        gap: 6px !important;
        flex-wrap: nowrap !important;
        flex-direction: row !important;
    }
    .btn-action-group .btn-sm {
        padding: 4px 12px !important;
        font-size: 13px !important;
        line-height: 1.4 !important;
        border-radius: 6px !important;
        min-width: 28px !important;
        height: 32px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 4px !important;
        transition: all 0.2s ease !important;
    }
    .btn-action-group .btn-sm i {
        font-size: 15px !important;
    }

    /* زر الحالة */
    .toggle-status-btn {
        min-width: 80px !important;
        padding: 4px 12px !important;
        font-size: 13px !important;
        font-weight: 600 !important;
        border-radius: 6px !important;
        border: none !important;
        cursor: pointer !important;
        transition: all 0.25s ease !important;
    }
    .toggle-status-btn.btn-success {
        background-color: #28a745 !important;
        color: #fff !important;
    }
    .toggle-status-btn.btn-success:hover {
        background-color: #218838 !important;
    }
    .toggle-status-btn.btn-secondary {
        background-color: #6c757d !important;
        color: #fff !important;
    }
    .toggle-status-btn.btn-secondary:hover {
        background-color: #5a6268 !important;
    }

    /* =========================================================
       نافذة تأكيد الحذف
    ========================================================= */
    .delete-confirm-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 9999;
    }

    .delete-confirm-overlay.show {
        display: flex;
    }

    .delete-confirm-box {
        background: #fff;
        border-radius: 16px;
        padding: 40px 50px;
        max-width: 420px;
        width: 90%;
        text-align: center;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        animation: deleteFadeIn 0.3s ease-out;
    }

    @keyframes deleteFadeIn {
        from {
            opacity: 0;
            transform: scale(0.95) translateY(-20px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    .delete-confirm-icon {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        background: #fee2e2;
        color: #dc2626;
        display: flex;
        justify-content: center;
        align-items: center;
        margin: 0 auto 16px;
        font-size: 30px;
    }

    .delete-confirm-box h3 {
        font-size: 20px;
        font-weight: 700;
        color: #111827;
        margin-bottom: 8px;
    }

    .delete-confirm-box p {
        color: #6b7280;
        font-size: 14px;
        line-height: 1.6;
        margin-bottom: 0;
    }

    .delete-confirm-box p span {
        color: #9ca3af;
        font-size: 13px;
    }

    .delete-confirm-actions {
        display: flex;
        gap: 12px;
        justify-content: center;
        margin-top: 24px;
    }

    .delete-confirm-actions button {
        padding: 10px 28px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .delete-cancel-btn {
        background: #f3f4f6;
        color: #374151;
    }
    .delete-cancel-btn:hover {
        background: #e5e7eb;
    }

    .delete-confirm-btn {
        background: #dc2626;
        color: #fff;
    }
    .delete-confirm-btn:hover {
        background: #b91c1c;
    }
    .delete-confirm-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    /* =========================================================
       التجاوب: أزرار أيقونية فقط على الهواتف (أقل من 768px)
    ========================================================= */
    @media (max-width: 767.98px) {
        .btn-action-group .btn-sm {
            padding: 2px 6px !important;
            min-width: 28px !important;
            height: 28px !important;
        }
        .btn-action-group .btn-sm i {
            font-size: 14px !important;
        }
        .toggle-status-btn {
            min-width: 60px !important;
            padding: 2px 8px !important;
            font-size: 11px !important;
        }
    }
</style>
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
                        <th style="width: 60px;">الرقم</th>
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

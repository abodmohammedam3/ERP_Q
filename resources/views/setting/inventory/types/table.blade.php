{{-- جدول الأنواع --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-list-ul"></i> قائمة الأنواع
        </span>
        <span class="badge bg-secondary" id="typesCountBadge">
            {{ isset($types) ? $types->count() : 0 }}
        </span>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-bordered mb-0 align-middle" id="typesTable">
                <thead class="table-light">
                    <tr class="text-center">
                        <th style="width: 60px;">الرقم </th>
                        <th>اسم النوع</th>
                        <th style="width: 130px;">الرمز</th>
                        <th style="width: 120px;">الحالة</th>
                        <th style="width: 180px;" class="no-print">الإجراءات</th>
                    </tr>
                </thead>
                <tbody id="typesTableBody">
                    @forelse($types ?? [] as $type)
                        <tr class="type-row text-center"
                            data-id="{{ $type->id }}"
                            data-name="{{ $type->name }}"
                            data-code="{{ $type->code }}"
                            data-active="{{ $type->is_active ? 1 : 0 }}">

                            <td>{{ $loop->iteration }}</td>
                            <td class="row-name text-center">{{ $type->name }}</td>
                            <td class="row-code">
                                <span class="badge bg-secondary">{{ $type->code ?? '—' }}</span>
                            </td>
                            <td class="row-status">
                                <button type="button"
                                        class="btn btn-sm {{ $type->is_active ? 'btn-success' : 'btn-secondary' }} toggle-status-btn"
                                        onclick="toggleTypeStatus(this)"
                                        title="{{ $type->is_active ? 'تعطيل' : 'تفعيل' }}">
                                    {{ $type->is_active ? 'نشط' : 'غير نشط' }}
                                </button>
                            </td>
                            <td class="no-print">
                                <div class="btn-action-group">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="editType(this)" title="تعديل">
                                        <i class="bi bi-pencil d-md-none"></i>
                                        <span class="d-none d-md-inline">تعديل</span>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteType(this)" title="حذف">
                                        <i class="bi bi-trash d-md-none"></i>
                                        <span class="d-none d-md-inline">حذف</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr id="emptyTypeRow">
                            <td colspan="5" class="text-center text-muted py-5">
                                <i class="bi bi-tags fs-2 d-block mb-2"></i>
                                لا توجد أنواع مسجلة
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-footer d-flex justify-content-center align-items-center">
        <nav aria-label="Pagination">
            <ul class="pagination pagination-sm mb-0" id="typesPaginationList"></ul>
        </nav>
    </div>
</div>


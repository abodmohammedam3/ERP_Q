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
                        <th style="width: 60px;">الرقم</th>
                        <th>اسم النوع</th>
                        <th style="width: 100px;">الكود</th>
                        <th style="width: 100px;">الحالة</th>
                        <th style="width: 130px;" class="no-print">الإجراءات</th>
                    </tr>
                </thead>
                <tbody id="typesTableBody">
                    {{-- عرض البيانات مباشرة من الخادم --}}
                    @forelse($types ?? [] as $type)
                        <tr class="type-row text-center" data-id="{{ $type->id }}">
                            <td>{{ $loop->iteration }}</td>
                            <td class="row-name">{{ $type->name }}</td>
                            <td class="row-code">{{ $type->code ?? '---' }}</td>
                            <td class="row-status">
                                <span class="badge {{ $type->is_active ? 'bg-success' : 'bg-danger' }}">
                                    {{ $type->is_active ? 'نشط' : 'غير نشط' }}
                                </span>
                            </td>
                            <td class="no-print">
                                <div class="btn-action-group">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="editType(this)" title="تعديل">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm {{ $type->is_active ? 'btn-toggle-on' : 'btn-toggle-off' }}" onclick="toggleTypeStatus(this)" title="{{ $type->is_active ? 'تعطيل' : 'تفعيل' }}">
                                        <i class="bi {{ $type->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteType(this)" title="حذف">
                                        <i class="bi bi-trash"></i>
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
            <ul class="pagination pagination-sm mb-0" id="typesPaginationList">
                {{-- سيتم ملؤها بواسطة JavaScript --}}
            </ul>
        </nav>
    </div>
</div>

{{-- أنماط إضافية --}}
<style>
    .btn-action-group {
        display: flex !important;
        justify-content: center !important;
        align-items: center !important;
        gap: 4px !important;
        flex-wrap: nowrap !important;
        flex-direction: row !important;
    }
    .btn-action-group .btn-sm {
        padding: 2px 6px !important;
        font-size: 14px !important;
        line-height: 1.2 !important;
        border-radius: 4px !important;
        min-width: 28px !important;
        height: 28px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
    }
    .btn-action-group .btn-sm i {
        font-size: 14px !important;
    }
    .btn-toggle-on {
        background-color: #28a745 !important;
        color: #fff !important;
        border-color: #28a745 !important;
    }
    .btn-toggle-on:hover {
        background-color: #218838 !important;
        border-color: #1e7e34 !important;
    }
    .btn-toggle-off {
        background-color: #6c757d !important;
        color: #fff !important;
        border-color: #6c757d !important;
    }
    .btn-toggle-off:hover {
        background-color: #5a6268 !important;
        border-color: #545b62 !important;
    }
</style>
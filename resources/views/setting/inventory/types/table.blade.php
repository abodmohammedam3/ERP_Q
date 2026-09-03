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
                        <th>#</th>
                        <th>اسم النوع</th>
                        <th class="no-print" style="width: 180px;">الإجراءات</th>
                    </tr>
                </thead>
                <tbody id="typesTableBody">

                    @forelse($types ?? [] as $type)
                        <tr class="type-row text-center">
                            <td class="row-id">{{ $type->typeID }}</td>
                            <td class="row-name">{{ $type->typeName2 }}</td>
                            <td class="no-print">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="editType(this)">
                                    <i class="bi bi-pencil"></i> تعديل
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteType(this)">
                                    <i class="bi bi-trash"></i> حذف
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr id="emptyTypeRow">
                            <td colspan="3" class="text-center text-muted py-5">
                                <i class="bi bi-tags fs-2 d-block mb-2"></i>
                                لا توجد أنواع مسجلة
                            </td>
                        </tr>
                    @endforelse

                </tbody>
            </table>
        </div>
    </div>
</div>

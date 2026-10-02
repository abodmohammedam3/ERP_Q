{{-- ========================= --}}
{{-- جدول عرض الموردين --}}
{{-- ========================= --}}

<div class="card" id="suppliersTableCard">

    <div class="card-header d-flex justify-content-between align-items-center">

        <span>
            <i class="bi bi-list-ul"></i> قائمة الموردين
        </span>

        <span class="badge bg-secondary" id="suppliersCountBadge">
            {{ isset($suppliers) ? $suppliers->count() : 0 }}
        </span>

    </div>

    <div class="card-body p-0">

        <div class="table-responsive">

            <table
                class="table table-hover table-bordered mb-0 align-middle"
                id="suppliersTable"
                style="width: 100%; table-layout: fixed;"
            >

                <colgroup>
                    <col style="width: 6%;">
                    <col style="width: 18%;">
                    <col style="width: 15%;">
                    <col style="width: 20%;">
                    <col style="width: 14%;">
                    <col style="width: 10%;">
                    <col style="width: 17%;">
                </colgroup>

                <thead class="table-light">

                    <tr class="text-center">

                        <th>#</th>

                        <th>اسم المورد</th>

                        <th>رقم الهاتف</th>

                        <th>العنوان</th>

                        <th>رقم الحساب التحليلي</th>

                        <th>الحالة</th>

                        <th class="text-center">
                            الإجراءات
                        </th>

                    </tr>

                </thead>

                <tbody id="suppliersTableBody">

                    @forelse($suppliers ?? [] as $index => $supplier)

                        <tr
                            class="supplier-row text-center"
                            data-id="{{ $supplier->suplierID }}"
                        >

                            {{-- # --}}
                            <td>
                                {{ $loop->iteration }}
                            </td>


                            {{-- اسم المورد --}}
                            <td
                                class="fw-semibold"
                                style="
                                    overflow: hidden;
                                    text-overflow: ellipsis;
                                    white-space: nowrap;
                                    max-width: 0;
                                "
                            >
                                {{ $supplier->supName }}
                            </td>


                            {{-- رقم الهاتف --}}
                            <td
                                style="
                                    overflow: hidden;
                                    text-overflow: ellipsis;
                                    white-space: nowrap;
                                    max-width: 0;
                                "
                            >
                                {{ $supplier->supPhone ?? '--' }}
                            </td>


                            {{-- العنوان --}}
                            <td
                                style="
                                    overflow: hidden;
                                    text-overflow: ellipsis;
                                    white-space: nowrap;
                                    max-width: 0;
                                "
                            >
                                {{ $supplier->supArea ?? '--' }}
                            </td>


                            {{-- رقم الحساب التحليلي --}}
                            <td>

                                <span class="badge bg-light text-dark border">

                                    {{ $supplier->account?->accCode ?? '--' }}

                                </span>

                            </td>


                            {{-- الحالة --}}
                            {{-- ✅ is_active: 1 = نشط، 0 = غير نشط --}}
                            <td>

                                @if($supplier->is_active === 1)

                                    <span class="badge bg-success">
                                        نشط
                                    </span>

                                @else

                                    <span class="badge bg-secondary">
                                        غير نشط
                                    </span>

                                @endif

                            </td>


                            {{-- الإجراءات --}}
                            <td class="text-center">

                                <div class="btn-action-group">

                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary edit-supplier"
                                        data-id="{{ $supplier->suplierID }}"
                                        title="تعديل"
                                    >
                                        <i class="bi bi-pencil d-md-none"></i>
                                        <span class="d-none d-md-inline">تعديل</span>
                                    </button>

                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger delete-supplier"
                                        data-id="{{ $supplier->suplierID }}"
                                        title="حذف"
                                    >
                                        <i class="bi bi-trash d-md-none"></i>
                                        <span class="d-none d-md-inline">حذف</span>
                                    </button>

                                </div>

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="7"
                                class="text-center py-4 text-muted"
                            >

                                <i class="bi bi-truck fs-2 d-block mb-2 text-secondary"></i>

                                لا يوجد موردون مسجلون

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>


    {{-- تذييل الجدول (ترقيم الصفحات) --}}
    <div class="card-footer d-flex justify-content-center align-items-center">

        <nav aria-label="Pagination">

            <ul
                class="pagination pagination-sm mb-0"
                id="suppliersPaginationList"
            >
            </ul>

        </nav>

    </div>

</div>
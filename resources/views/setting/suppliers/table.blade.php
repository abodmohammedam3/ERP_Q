<!-- ========================= --><!-- جدول عرض الموردين --><!-- ========================= --><div class="card" id="suppliersTableCard"><div class="card-header bg-body border-bottom d-flex justify-content-between align-items-center">

    <div class="d-flex align-items-center gap-2">
        <i class="bi bi-truck text-primary"></i>
        <h6 class="mb-0 fw-bold">قائمة الموردين</h6>
    </div>

</div>

<div class="card-body p-0">

    <div class="table-responsive">

        <table
            class="table table-striped table-hover mb-0 align-middle"
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

                <tr>

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
                        class="supplier-row"
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

                                {{ 2101000 + (int) $supplier->suplierID }}

                            </span>

                        </td>


                        {{-- الحالة --}}
                        <td>

                            @if($supplier->supStoped == 0)

                                <span class="badge bg-primary">
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

                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary me-1 edit-supplier"
                                data-id="{{ $supplier->suplierID }}"
                            >
                                <i class="bi bi-pencil"></i>
                                تعديل
                            </button>

                            <button
                                type="button"
                                class="btn btn-sm btn-outline-danger delete-supplier"
                                data-id="{{ $supplier->suplierID }}"
                            >
                                <i class="bi bi-trash"></i>
                                حذف
                            </button>

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
<div
    class="card-footer d-flex flex-wrap justify-content-between align-items-center"
    id="suppliersPagination"
>

    <span
        class="text-muted small"
        id="suppliersPaginationInfo"
    >
        عرض 0-0 من 0 مورد
    </span>

    <nav>

        <ul
            class="pagination pagination-sm mb-0"
            id="suppliersPaginationList"
        >
        </ul>

    </nav>

</div>

</div>
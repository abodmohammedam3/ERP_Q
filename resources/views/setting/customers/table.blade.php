<!-- بطاقة جدول العملاء -->
<div class="card" id="customersTableCard">

    {{-- رأس البطاقة --}}
    <div class="card-header bg-body border-bottom d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-people text-primary"></i>
            <h6 class="mb-0 fw-bold">قائمة العملاء</h6>
        </div>
        
    </div>

    {{-- جسم البطاقة والجدول --}}
    <div class="card-body p-0">
        <div class="table-responsive">
            <table
                class="table table-striped table-hover mb-0 align-middle"
                id="customersTable"
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
                        <th>اسم العميل</th>
                        <th>رقم الهاتف</th>
                        <th>العنوان</th>
                        <th>رقم الحساب التحليلي</th>
                        <th>الحالة</th>
                        <th class="text-center">الإجراءات</th>
                    </tr>
                </thead>

                <tbody id="customersTableBody">

                    @forelse($customers ?? [] as $index => $customer)

                        <tr
                            class="customer-row"
                            data-id="{{ $customer->CustomersID }}"
                        >

                            {{-- # --}}
                            <td>
                                {{ $loop->iteration }}
                            </td>

                            {{-- اسم العميل --}}
                            <td
                                class="fw-semibold"
                                style="
                                    overflow: hidden;
                                    text-overflow: ellipsis;
                                    white-space: nowrap;
                                    max-width: 0;
                                "
                            >
                                {{ $customer->CustomersName2 }}
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
                                {{ $customer->CusPhone ?? '--' }}
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
                                {{ $customer->CusAddress ?? '--' }}
                            </td>

                            {{-- رقم الحساب التحليلي --}}
                            <td>
                                <span class="badge bg-light text-dark border">
                                    {{ 110000 + (int) $customer->CustomersID }}
                                </span>
                            </td>

                            {{-- الحالة --}}
                            <td>
                                @if($customer->CusIsStopeed == 0)

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
                                    class="btn btn-sm btn-outline-primary me-1 edit-customer"
                                    data-id="{{ $customer->CustomersID }}"
                                >
                                    <i class="bi bi-pencil"></i>
                                    تعديل
                                </button>

                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger delete-customer"
                                    data-id="{{ $customer->CustomersID }}"
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
                                <i class="bi bi-people fs-2 d-block mb-2 text-secondary"></i>

                                لا يوجد عملاء مسجلون
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
        id="customersPagination"
    >

        <span
            class="text-muted small"
            id="customersPaginationInfo"
        >
            عرض 0-0 من 0 عميل
        </span>

        <nav>
            <ul
                class="pagination pagination-sm mb-0"
                id="customersPaginationList"
            >
            </ul>
        </nav>

    </div>

</div>
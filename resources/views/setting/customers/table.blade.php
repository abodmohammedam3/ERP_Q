<!-- بطاقة جدول العملاء -->
<div class="card" id="customersTableCard">

    {{-- رأس البطاقة --}}
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-list-ul"></i> قائمة العملاء
        </span>
        <span class="badge bg-secondary" id="customersCountBadge">
            {{ isset($customers) ? $customers->count() : 0 }}
        </span>
        
    </div>

    {{-- جسم البطاقة والجدول --}}
    <div class="card-body p-0">
        <div class="table-responsive">
            <table
                class="table table-hover table-bordered mb-0 align-middle"
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
                    <tr class="text-center">
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
                            class="customer-row text-center"
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
                                    {{ $customer->account?->accCode ?? '--' }}
                                </span>
                            </td>

                            {{-- الحالة --}}
                            <td>
                               @if($customer->is_active == 1)

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
                                        class="btn btn-sm btn-outline-primary edit-customer"
                                        data-id="{{ $customer->CustomersID }}"
                                        title="تعديل"
                                    >
                                        <i class="bi bi-pencil d-md-none"></i>
                                        <span class="d-none d-md-inline">تعديل</span>
                                    </button>

                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger delete-customer"
                                        data-id="{{ $customer->CustomersID }}"
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
    <div class="card-footer d-flex justify-content-center align-items-center">

        <nav aria-label="Pagination">
            <ul
                class="pagination pagination-sm mb-0"
                id="customersPaginationList"
            >
            </ul>
        </nav>

    </div>

</div>
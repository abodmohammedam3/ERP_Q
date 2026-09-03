
    <!-- بطاقة جدول الحسابات -->
<div class="card" id="accountsTable">
            <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>رقم الحساب</th>
                            <th>اسم الحساب</th>
                            <th>النوع</th>
                            <th>الحساب الأب</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>

             @forelse($accounts as $index => $account)

                    <tr>
 
            {{-- الرقم التسلسلي --}}
                             <td>
                         {{ $index + 1 }}
                             </td>

            {{-- رقم الحساب --}}
                            <td>
                            {{ $account->accCode }}
                            </td>

            {{-- اسم الحساب --}}
                             <td>
                            {{ $account->accName }}
                            </td>

                         {{-- النوع --}}
                             <td>
                             @if($account->nature == 1)
                             <span class="badge bg-success">
                                    أصول
                                </span>
                             @else
                                <span class="badge bg-info">
                                    إيرادات
                             </span>
                             @endif
                        </td>

                      {{-- الحساب الأب --}}
                        <td>
                            {{ $account->accParent ?? '--' }}
                        </td>

                          {{-- الحالة --}}
                        <td>
                             @if($account->IsActive == 1)
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
<td>

    {{-- زر التعديل --}}
    <button
        type="button"
        class="btn btn-sm btn-outline-primary me-1 edit-account"
        data-id="{{ $account->accountID }}">

        <i class="bi bi-pencil"></i>
        تعديل

    </button>


    {{-- زر الحذف --}}
    <button
        type="button"
        class="btn btn-sm btn-outline-danger">

        <i class="bi bi-trash"></i>
        حذف

    </button>

</td>
                    </tr>

    @empty

                        <tr>

                            <td colspan="7" class="text-center py-4 text-muted">
                                لا توجد حسابات
                            </td>

                         </tr>

    @endforelse

                    </tbody>
                </table>
            </div>
        </div>
        <!-- تذييل الجدول (ترقيم الصفحات) -->
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center">
            <span class="text-muted small">عرض 1-3 من 3 حسابات</span>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item disabled"><a class="page-link" href="#">السابق</a></li>
                    <li class="page-item active"><a class="page-link" href="#">1</a></li>
                    <li class="page-item disabled"><a class="page-link" href="#">التالي</a></li>
                </ul>
            </nav>
        </div>
</div> 

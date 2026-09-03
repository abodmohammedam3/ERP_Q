<div>

    {{-- عنوان القسم والأزرار --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">

        <div>
            <h5 class="mb-1 fw-semibold">
                أرصدة الحسابات
            </h5>

            <small class="text-body-secondary">
                إدخال الأرصدة الافتتاحية للحسابات العامة
            </small>
        </div>

        <button
            type="button"
            class="btn btn-primary"
        >
            <i class="bi bi-plus-lg me-1"></i>
            إضافة حساب
        </button>

    </div>


    {{-- البحث والتصفية --}}
    <div class="card border-0 bg-body-tertiary mb-3">

        <div class="card-body">

            <div class="row g-3">

                {{-- البحث --}}
                <div class="col-12 col-md-6 col-xl-4">

                    <label
                        for="accountSearch"
                        class="form-label"
                    >
                        البحث
                    </label>

                    <div class="input-group">

                        <span class="input-group-text">
                            <i class="bi bi-search"></i>
                        </span>

                        <input
                            type="text"
                            id="accountSearch"
                            class="form-control"
                            placeholder="رقم الحساب أو اسم الحساب"
                        >

                    </div>

                </div>


                {{-- نوع الحساب --}}
                <div class="col-12 col-md-6 col-xl-3">

                    <label
                        for="accountType"
                        class="form-label"
                    >
                        نوع الحساب
                    </label>

                    <select
                        id="accountType"
                        class="form-select"
                    >

                        <option value="">
                            جميع الحسابات
                        </option>

                        <option value="asset">
                            الأصول
                        </option>

                        <option value="liability">
                            الالتزامات
                        </option>

                        <option value="equity">
                            حقوق الملكية
                        </option>

                        <option value="revenue">
                            الإيرادات
                        </option>

                        <option value="expense">
                            المصروفات
                        </option>

                    </select>

                </div>


                {{-- زر البحث --}}
                <div class="col-12 col-md-auto d-flex align-items-end">

                    <button
                        type="button"
                        class="btn btn-outline-primary"
                    >
                        <i class="bi bi-search me-1"></i>
                        بحث
                    </button>

                </div>

            </div>

        </div>

    </div>


    {{-- جدول الحسابات --}}
    <div class="table-responsive">

        <table class="table table-bordered table-hover align-middle mb-0">

            <thead class="table-light">

                <tr>

                    <th style="width: 60px;">
                        #
                    </th>

                    <th>
                        رقم الحساب
                    </th>

                    <th>
                        اسم الحساب
                    </th>

                    <th style="width: 180px;">
                        مدين
                    </th>

                    <th style="width: 180px;">
                        دائن
                    </th>

                    <th style="width: 140px;">
                        العملة
                    </th>

                    <th style="width: 140px;">
                        سعر الصرف
                    </th>

                    <th>
                        البيان
                    </th>

                    <th style="width: 80px;">
                        الإجراء
                    </th>

                </tr>

            </thead>


            <tbody>

                {{-- مثال مؤقت --}}
                <tr>

                    <td>1</td>

                    <td>
                        <span class="fw-medium">
                            1101
                        </span>
                    </td>

                    <td>
                        الصندوق الرئيسي
                    </td>

                    <td>
                        <input
                            type="number"
                            class="form-control"
                            placeholder="0.00"
                        >
                    </td>

                    <td>
                        <input
                            type="number"
                            class="form-control"
                            placeholder="0.00"
                        >
                    </td>

                    <td>

                        <select class="form-select">

                            <option>
                                ريال يمني
                            </option>

                            <option>
                                دولار أمريكي
                            </option>

                        </select>

                    </td>

                    
                    <td>
                        <input
                            type="number"
                            class="form-control"
                            placeholder="0.00"
                        >
                    </td>
                    <td>

                        <input
                            type="text"
                            class="form-control"
                            placeholder="البيان"
                        >

                    </td>

                    <td class="text-center">

                        <button
                            type="button"
                            class="btn btn-sm btn-outline-danger"
                            title="حذف"
                        >
                            <i class="bi bi-trash"></i>
                        </button>

                    </td>

                </tr>

            </tbody>

        </table>

    </div>


    {{-- حالة عدم وجود بيانات --}}
    <div class="text-center py-4 text-body-secondary d-none">
        <i class="bi bi-journal-x fs-2 d-block mb-2"></i>
        لا توجد أرصدة افتتاحية مضافة.
    </div>
</div>
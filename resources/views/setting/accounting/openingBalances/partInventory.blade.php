<div>

    {{-- عنوان القسم --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">

        <div>
            <h5 class="mb-1 fw-semibold">
                أرصدة المخزون الافتتاحية
            </h5>

            <small class="text-body-secondary">
                إدخال الكميات والتكلفة الافتتاحية للأصناف
            </small>
        </div>

        <button
            type="button"
            class="btn btn-primary"
        >
            <i class="bi bi-plus-lg me-1"></i>
            إضافة صنف
        </button>

    </div>


    {{-- البحث والتصفية --}}
    <div class="card border-0 bg-body-tertiary mb-3">

        <div class="card-body">

            <div class="row g-3">

                {{-- البحث --}}
                <div class="col-12 col-md-6 col-xl-4">

                    <label
                        for="inventorySearch"
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
                            id="inventorySearch"
                            class="form-control"
                            placeholder="كود الصنف أو اسم الصنف"
                        >

                    </div>

                </div>


                {{-- المستودع --}}
                <div class="col-12 col-md-6 col-xl-3">

                    <label
                        for="warehouse"
                        class="form-label"
                    >
                        المستودع
                    </label>

                    <select
                        id="warehouse"
                        class="form-select"
                    >

                        <option value="">
                            جميع المستودعات
                        </option>

                        <option value="1">
                            المستودع الرئيسي
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


    {{-- جدول المخزون --}}
    <div class="table-responsive">

        <table class="table table-bordered table-hover align-middle mb-0">

            <thead class="table-light">

                <tr>

                    <th style="width: 55px;">
                        #
                    </th>

                    <th>
                        كود الصنف
                    </th>

                    <th>
                        اسم الصنف
                    </th>

                    <th>
                        المستودع
                    </th>

                    <th style="width: 130px;">
                        الكمية
                    </th>

                    <th style="width: 160px;">
                        تكلفة الوحدة
                    </th>

                    <th style="width: 170px;">
                        إجمالي التكلفة
                    </th>

                    <th style="width: 130px;">
                        العملة
                    </th>

                    <th>
                        البيان
                    </th>

                    <th style="width: 70px;">
                        الإجراء
                    </th>

                </tr>

            </thead>


            <tbody>

                {{-- مثال مؤقت --}}
                <tr>

                    <td>
                        1
                    </td>

                    <td>
                        <span class="fw-medium">
                            P001
                        </span>
                    </td>

                    <td>
                        صنف تجريبي
                    </td>

                    <td>
                        المستودع الرئيسي
                    </td>

                    <td>

                        <input
                            type="number"
                            class="form-control"
                            value="10"
                            min="0"
                            step="0.001"
                        >

                    </td>

                    <td>

                        <input
                            type="number"
                            class="form-control"
                            value="500"
                            min="0"
                            step="0.01"
                        >

                    </td>

                    <td>

                        <input
                            type="number"
                            class="form-control"
                            value="5000"
                            readonly
                        >

                    </td>

                    <td>

                        <select class="form-select">

                            <option value="YER">
                                ريال يمني
                            </option>

                            <option value="USD">
                                دولار أمريكي
                            </option>

                        </select>

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


    {{-- ملخص المخزون --}}
    <div class="border-top mt-3 pt-3">

        <div class="row g-3">

            {{-- إجمالي الكمية --}}
            <div class="col-12 col-md-4">

                <div class="card border-0 bg-body-tertiary">

                    <div class="card-body py-3">

                        <small class="text-body-secondary d-block">
                            إجمالي الكمية
                        </small>

                        <span class="fs-5 fw-semibold">
                            0
                        </span>

                    </div>

                </div>

            </div>


            {{-- قيمة المخزون --}}
            <div class="col-12 col-md-4">

                <div class="card border-0 bg-body-tertiary">

                    <div class="card-body py-3">

                        <small class="text-body-secondary d-block">
                            قيمة المخزون
                        </small>

                        <span class="fs-5 fw-semibold">
                            0.00
                        </span>

                    </div>

                </div>

            </div>


            {{-- عدد الأصناف --}}
            <div class="col-12 col-md-4">

                <div class="card border-0 bg-body-tertiary">

                    <div class="card-body py-3">

                        <small class="text-body-secondary d-block">
                            عدد الأصناف
                        </small>

                        <span class="fs-5 fw-semibold">
                            0
                        </span>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>
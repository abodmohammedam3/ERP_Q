<div>

    {{-- عنوان القسم --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">

        <div>
            <h5 class="mb-1 fw-semibold">
                أرصدة الموردين الافتتاحية
            </h5>

            <small class="text-body-secondary">
                إدخال الأرصدة السابقة لكل مورد
            </small>
        </div>

        <button
            type="button"
            class="btn btn-primary"
        >
            <i class="bi bi-plus-lg me-1"></i>
            إضافة مورد
        </button>

    </div>


    {{-- البحث والتصفية --}}
    <div class="card border-0 bg-body-tertiary mb-3">

        <div class="card-body">

            <div class="row g-3">

                {{-- البحث --}}
                <div class="col-12 col-md-6 col-xl-4">

                    <label
                        for="supplierSearch"
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
                            id="supplierSearch"
                            class="form-control"
                            placeholder="كود المورد أو اسم المورد"
                        >

                    </div>

                </div>


                {{-- العملة --}}
                <div class="col-12 col-md-6 col-xl-3">

                    <label
                        for="supplierCurrency"
                        class="form-label"
                    >
                        العملة
                    </label>

                    <select
                        id="supplierCurrency"
                        class="form-select"
                    >

                        <option value="">
                            جميع العملات
                        </option>

                        <option value="YER">
                            ريال يمني
                        </option>

                        <option value="USD">
                            دولار أمريكي
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


    {{-- جدول الموردين --}}
    <div class="table-responsive">

        <table class="table table-bordered table-hover align-middle mb-0">

            <thead class="table-light">

                <tr>

                    <th style="width: 55px;">
                        #
                    </th>

                    <th>
                        كود المورد
                    </th>

                    <th>
                        اسم المورد
                    </th>

                    <th style="width: 170px;">
                        مدين
                    </th>

                    <th style="width: 170px;">
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
                            S001
                        </span>
                    </td>

                    <td>
                        شركة النور
                    </td>

                    <td>

                        <input
                            type="number"
                            class="form-control"
                            placeholder="0.00"
                            min="0"
                            step="0.01"
                        >

                    </td>

                    <td>

                        <input
                            type="number"
                            class="form-control"
                            placeholder="0.00"
                            min="0"
                            step="0.01"
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


    {{-- ملخص أرصدة الموردين --}}
    <div class="border-top mt-3 pt-3">

        <div class="row g-3">

            {{-- إجمالي المدين --}}
            <div class="col-12 col-md-4">

                <div class="card border-0 bg-body-tertiary">

                    <div class="card-body py-3">

                        <small class="text-body-secondary d-block">
                            إجمالي المدين
                        </small>

                        <span class="fs-5 fw-semibold">
                            0.00
                        </span>

                    </div>

                </div>

            </div>


            {{-- إجمالي الدائن --}}
            <div class="col-12 col-md-4">

                <div class="card border-0 bg-body-tertiary">

                    <div class="card-body py-3">

                        <small class="text-body-secondary d-block">
                            إجمالي الدائن
                        </small>

                        <span class="fs-5 fw-semibold">
                            0.00
                        </span>

                    </div>

                </div>

            </div>


            {{-- عدد الموردين --}}
            <div class="col-12 col-md-4">

                <div class="card border-0 bg-body-tertiary">

                    <div class="card-body py-3">

                        <small class="text-body-secondary d-block">
                            عدد الموردين
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
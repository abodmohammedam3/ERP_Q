<!-- مودال إضافة / تعديل مورد --><div
    class="modal fade"
    id="supplierModal"
    tabindex="-1"
    aria-hidden="true"
>
    <div class="modal-dialog modal-lg">    <div class="modal-content">

        {{-- ================================
             رأس المودال
        ================================= --}}

        <div class="modal-header position-relative">

            <h5
                class="modal-title"
                id="supplierModalLabel"
            >
                إضافة مورد جديد
            </h5>

            <button
                type="button"
                class="btn-close position-absolute top-0 start-0 m-3"
                data-bs-dismiss="modal"
                aria-label="إغلاق"
            ></button>

        </div>


        {{-- ================================
             جسم المودال
        ================================= --}}

        <div class="modal-body">

            <form id="supplierForm">

                @csrf

                {{-- معرف المورد المخفي --}}
                <input
                    type="hidden"
                    name="suplierID"
                    id="supplierID"
                >


                <div class="row g-3">

                    {{-- ================================
                         1 - اسم المورد
                    ================================= --}}

                    <div class="col-md-6">

                        <label
                            for="supName"
                            class="form-label"
                        >
                            اسم المورد
                            <span class="text-danger">*</span>
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            name="supName"
                            id="supName"
                            autocomplete="off"
                        >

                    </div>


                    {{-- ================================
                         2 - رقم الحساب التحليلي
                    ================================= --}}

                    <div class="col-md-6">

                        <label
                            for="supplierAccountCode"
                            class="form-label"
                        >
                            رقم الحساب التحليلي
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="supplierAccountCode"
                            value=""
                            readonly
                            style="background-color: var(--bs-tertiary-bg);"
                        >

                        <small class="text-muted">
                            يتم توليده تلقائيًا بواسطة النظام
                        </small>

                    </div>


                    {{-- ================================
                         3 - رقم الهاتف
                    ================================= --}}

                    <div class="col-md-6">

                        <label
                            for="supPhone"
                            class="form-label"
                        >
                            رقم الهاتف
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            name="supPhone"
                            id="supPhone"
                            autocomplete="off"
                        >

                    </div>


                    {{-- ================================
                         4 - الحالة
                    ================================= --}}

                    <div class="col-md-6">

                        <label
                            for="supStatus"
                            class="form-label"
                        >
                            الحالة
                        </label>

                        <select
                            class="form-select"
                            name="supStoped"
                            id="supStatus"
                        >

                            <option value="0">
                                نشط
                            </option>

                            <option value="1">
                                غير نشط
                            </option>

                        </select>

                    </div>


                    {{-- ================================
                         5 - العنوان
                    ================================= --}}

                    <div class="col-md-12">

                        <label
                            for="supArea"
                            class="form-label"
                        >
                            العنوان
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            name="supArea"
                            id="supArea"
                            autocomplete="off"
                        >

                    </div>

                </div>


                {{-- ================================
                     أزرار المودال
                ================================= --}}

                <div class="modal-footer mt-4">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal"
                    >
                        إلغاء
                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary"
                        id="saveSupplierBtn"
                    >
                        حفظ البيانات
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

</div>
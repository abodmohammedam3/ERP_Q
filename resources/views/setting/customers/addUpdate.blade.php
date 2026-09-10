<!-- مودال إضافة / تعديل عميل -->
<div
    class="modal fade"
    id="customerModal"
    tabindex="-1"
    aria-hidden="true"
>
    <div class="modal-dialog modal-lg">

        <div class="modal-content">

            {{-- ================================
                 رأس المودال
            ================================= --}}

            <div class="modal-header position-relative">

                <h5
                    class="modal-title"
                    id="customerModalLabel"
                >
                    إضافة عميل جديد
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

                <form id="customerForm">

                    @csrf

                    {{-- معرف العميل المخفي --}}
                    <input
                        type="hidden"
                        name="CustomersID"
                        id="customerID"
                    >


                    <div class="row g-3">

                        {{-- ================================
                             1 - اسم العميل
                        ================================= --}}

                        <div class="col-md-6">

                            <label
                                for="cusName"
                                class="form-label"
                            >
                                اسم العميل
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                name="CustomersName2"
                                id="cusName"
                                autocomplete="off"
                            >

                        </div>


                        {{-- ================================
                             2 - رقم الحساب التحليلي
                        ================================= --}}

                        <div class="col-md-6">

                            <label
                                for="customerAccountCode"
                                class="form-label"
                            >
                                رقم الحساب التحليلي
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="customerAccountCode"
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
                                for="cusPhone"
                                class="form-label"
                            >
                                رقم الهاتف
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                name="CusPhone"
                                id="cusPhone"
                                autocomplete="off"
                            >

                        </div>


                        {{-- ================================
                             4 - الحالة
                        ================================= --}}

                        <div class="col-md-6">

                            <label
                                for="cusStatus"
                                class="form-label"
                            >
                                الحالة
                            </label>

                            <select
                                class="form-select"
                                name="CusIsStopeed"
                                id="cusStatus"
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
                                for="cusAddress"
                                class="form-label"
                            >
                                العنوان
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                name="CusAddress"
                                id="cusAddress"
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
                            id="saveCustomerBtn"
                        >
                            حفظ البيانات
                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>
</div>
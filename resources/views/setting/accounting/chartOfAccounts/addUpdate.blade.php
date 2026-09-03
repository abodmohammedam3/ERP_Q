<!-- مودال إضافة / تعديل حساب -->
<div class="modal fade" id="addAccountModal" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog modal-lg">

        <div class="modal-content">

            {{-- ================================
                 رأس المودال
            ================================= --}}

            <div class="modal-header position-relative">

                <h5 class="modal-title" id="accountModalTitle">
                    إضافة حساب جديد
                </h5>

                <button
                    type="button"
                    class="btn-close position-absolute top-0 start-0 m-3"
                    data-bs-dismiss="modal"
                    aria-label="إغلاق">
                </button>

            </div>


            {{-- ================================
                 جسم المودال
            ================================= --}}

            <div class="modal-body">

                <form
                    id="accountForm"
                    action="{{ route('chartOfAccounts.store') }}"
                    method="POST">

                    @csrf

                    {{-- رقم الحساب الداخلي --}}
                    <input
                        type="hidden"
                        name="accountID"
                        id="accountID">


                    <div class="row g-3">

                        {{-- رقم الحساب --}}
                        <div class="col-md-6">

                            <label class="form-label">
                                رقم الحساب
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                name="accCode">

                        </div>


                        {{-- اسم الحساب --}}
                        <div class="col-md-6">

                            <label class="form-label">
                                اسم الحساب
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                name="accName">

                        </div>


                        {{-- نوع الحساب --}}
                        <div class="col-md-6">

                            <label class="form-label">
                                نوع الحساب
                            </label>

                            <select
                                class="form-select"
                                name="accTypeID">

                                <option value="1">
                                    أصول
                                </option>

                                <option value="2">
                                    خصوم
                                </option>

                                <option value="3">
                                    حقوق ملكية
                                </option>

                                <option value="4">
                                    إيرادات
                                </option>

                                <option value="5">
                                    مصروفات
                                </option>

                            </select>

                        </div>


                        {{-- الحساب الأب --}}
                        <div class="col-md-6">

                            <label class="form-label">
                                الحساب الأب
                            </label>

                            <select
                                class="form-select"
                                name="accParent">

                                <option value="">
                                    لا يوجد (حساب رئيسي)
                                </option>

                                <option value="1">
                                    1000 - الصندوق
                                </option>

                                <option value="2">
                                    2000 - البنك
                                </option>

                                <option value="3">
                                    3000 - المخزون
                                </option>

                            </select>

                        </div>


                        {{-- طبيعة الحساب --}}
                        <div class="col-md-6">

                            <label class="form-label">
                                طبيعة الحساب
                            </label>

                            <select
                                class="form-select"
                                name="nature">

                                <option value="0">
                                    مدين
                                </option>

                                <option value="1">
                                    دائن
                                </option>

                            </select>

                        </div>


                        {{-- الحالة --}}
                        <div class="col-md-6">

                            <label class="form-label">
                                الحالة
                            </label>

                            <select
                                class="form-select"
                                name="IsActive">

                                <option value="1">
                                    نشط
                                </option>

                                <option value="0">
                                    غير نشط
                                </option>

                            </select>

                        </div>


                        {{-- مستوى الحساب --}}
                        <div class="col-md-6">

                            <label class="form-label">
                                مستوى الحساب
                            </label>

                            <select
                                class="form-select"
                                name="accLevel">

                                <option value="1">
                                    حساب رئيسي
                                </option>

                                <option value="2">
                                    حساب فرعي
                                </option>

                            </select>

                        </div>


                        {{-- يقبل العمليات --}}
                        <div class="col-md-6">

                            <label class="form-label">
                                يقبل عليه العمليات
                            </label>

                            <select
                                class="form-select"
                                name="isPostable">

                                <option value="1">
                                    نعم
                                </option>

                                <option value="0">
                                    لا
                                </option>

                            </select>

                        </div>

                    </div>


                    {{-- ================================
                         أزرار المودال
                    ================================= --}}

                    <div class="modal-footer mt-4">

                        <button
                            type="button"
                            class="btn btn-secondary"
                            data-bs-dismiss="modal">

                            إلغاء

                        </button>


                        <button
                            type="submit"
                            class="btn btn-primary"
                            id="saveAccountBtn">

                            حفظ الحساب

                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>

</div>
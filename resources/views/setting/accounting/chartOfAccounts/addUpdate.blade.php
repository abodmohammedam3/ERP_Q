<!-- مودال إضافة / تعديل حساب --><div
    class="modal fade"
    id="addAccountModal"
    tabindex="-1"
    aria-hidden="true"
><div class="modal-dialog modal-lg">

    <div class="modal-content">

        {{-- ================================
             رأس المودال
        ================================= --}}

        <div class="modal-header position-relative">

            <h5
                class="modal-title"
                id="accountModalTitle"
            >
                إضافة حساب جديد
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

            <form
                id="accountForm"
                action="{{ route('chartOfAccounts.store') }}"
                method="POST"
            >

                @csrf

                {{-- معرف الحساب --}}
                <input
                    type="hidden"
                    name="accountID"
                    id="accountID"
                >


                <div class="row g-3">


                    {{-- ================================
                         1 - الحساب الأب (حقل بحث منبثق)
                    ================================= --}}

                    <div class="col-md-6">

                        <label
                            for="accParentDisplay"
                            class="form-label"
                        >
                            الحساب الأب
                        </label>

                        {{-- الحقل الظاهر للمستخدم (للعرض والضغط) --}}
                        <input
                            type="text"
                            class="form-control"
                            id="accParentDisplay"
                            placeholder="اضغط لاختيار الحساب الأب"
                            readonly
                            style="cursor: pointer; background-color: #fff;"
                        >

                        {{-- الحقل المخفي الحقيقي الذي يحمل القيمة --}}
                      

                       <select
                            name="accParent"
                            id="accParent"
                            style="display: none;"
                        >
                            <option value="">
                                لا يوجد (حساب رئيسي)
                            </option>
                        </select>

                     

                    </div>


                    {{-- ================================
                         2 - رقم الحساب
                    ================================= --}}

                    <div class="col-md-6">

                        <label
                            for="accCode"
                            class="form-label"
                        >
                            رقم الحساب
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            name="accCode"
                            id="accCode"
                            autocomplete="off"
                        >

                    </div>


                    {{-- ================================
                         3 - اسم الحساب
                    ================================= --}}

                    <div class="col-md-6">

                        <label
                            for="accName"
                            class="form-label"
                        >
                            اسم الحساب
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            name="accName"
                            id="accName"
                            autocomplete="off"
                        >

                    </div>


                    {{-- ================================
                         4 - نوع الحساب
                    ================================= --}}

                    <div class="col-md-6">

                        <label
                            for="accTypeID"
                            class="form-label"
                        >
                            نوع الحساب
                        </label>

                        <select
                            class="form-select"
                            name="accTypeID"
                            id="accTypeID"
                        >

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


                    {{-- ================================
                         5 - طبيعة الحساب
                    ================================= --}}

                    <div class="col-md-6">

                        <label
                            for="nature"
                            class="form-label"
                        >
                            طبيعة الحساب
                        </label>

                        <select
                            class="form-select"
                            name="nature"
                            id="nature"
                        >

                            <option value="0">
                                مدين
                            </option>

                            <option value="1">
                                دائن
                            </option>

                        </select>

                    </div>


                    {{-- ================================
                         6 - مستوى الحساب
                    ================================= --}}

                    <div class="col-md-6">

                        <label
                            for="accLevel"
                            class="form-label"
                        >
                            مستوى الحساب
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            name="accLevel"
                            id="accLevel"
                            value="1"
                            readonly
                        >

                        <small class="text-muted">
                            يتم تحديد المستوى تلقائياً حسب الحساب الأب
                        </small>

                    </div>


                    {{-- ================================
                         7 - الحالة
                    ================================= --}}

                    <div class="col-md-6">

                        <label
                            for="IsActive"
                            class="form-label"
                        >
                            الحالة
                        </label>

                        <select
                            class="form-select"
                            name="IsActive"
                            id="IsActive"
                        >

                            <option value="1">
                                نشط
                            </option>

                            <option value="0">
                                غير نشط
                            </option>

                        </select>

                    </div>


                    {{-- ================================
                         8 - يقبل عليه العمليات
                    ================================= --}}

                    <div class="col-md-6">

                        <label
                            for="isPostable"
                            class="form-label"
                        >
                            يقبل عليه العمليات
                        </label>

                        <select
                            class="form-select"
                            name="isPostable"
                            id="isPostable"
                        >

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
                        data-bs-dismiss="modal"
                    >
                        إلغاء
                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary"
                        id="saveAccountBtn"
                    >
                        حفظ الحساب
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

</div>


{{-- ================================================
     مودال البحث عن الحساب الأب
================================================ --}}

<div
    class="modal fade"
    id="parentAccountSearchModal"
    tabindex="-1"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered">

        <div
            class="modal-content"
            style="height: 550px;"
        >

            {{-- رأس المودال --}}
            <div class="modal-header position-relative">

                <h5 class="modal-title">
                    اختيار الحساب الأب
                </h5>

                <button
                    type="button"
                    class="btn-close position-absolute top-0 start-0 m-3"
                    data-bs-dismiss="modal"
                    aria-label="إغلاق"
                ></button>

            </div>


            {{-- جسم المودال --}}
            <div
                class="modal-body d-flex flex-column p-0"
                style="overflow: hidden;"
            >

                {{-- حقل البحث --}}
                <div class="p-3 border-bottom">

                    <input
                        type="text"
                        class="form-control"
                        id="parentAccountSearchInput"
                        placeholder="ابحث بالاسم أو رقم الحساب..."
                        autocomplete="off"
                    >

                </div>


                {{-- قائمة النتائج --}}
                <div
                    id="parentAccountSearchResults"
                    class="flex-grow-1"
                    style="overflow-y: auto;"
                >

                    <ul
                        class="list-group list-group-flush"
                        id="parentAccountSearchList"
                    >

                        {{-- خيار: لا يوجد (حساب رئيسي) --}}
                        <li
                            class="list-group-item list-group-item-action parent-search-item"
                            data-id=""
                            data-code=""
                            data-name="لا يوجد (حساب رئيسي)"
                            data-level="0"
                            style="cursor: pointer;"
                        >
                            <strong class="text-muted">
                                لا يوجد (حساب رئيسي)
                            </strong>
                        </li>

                       

                    </ul>

                </div>

            </div>

        </div>

    </div>

</div>
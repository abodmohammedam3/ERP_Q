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
                                اسم العميل <span class="text-danger">*</span>
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
                             2 - الحساب المحاسبي المرتبط
                        ================================= --}}

                        <div class="col-md-6">

                            <label
                                for="accountDisplay"
                                class="form-label"
                            >
                                الحساب المحاسبي المرتبط <span class="text-danger">*</span>
                            </label>

                            {{-- الحقل الظاهر للمستخدم --}}
                            <input
                                type="text"
                                class="form-control"
                                id="accountDisplay"
                                placeholder="اضغط لاختيار الحساب المحاسبي"
                                readonly
                                style="cursor: pointer; background-color: #fff;"
                            >

                            {{-- الحقل المخفي الذي يحمل رقم الحساب accountID --}}
                            <select
                                name="accountID"
                                id="accountID"
                                style="display: none;"
                            >
                                <option value="">اختر الحساب</option>
                                @foreach($accounts as $acc)
                                    <option
                                        value="{{ $acc->accountID }}"
                                        data-code="{{ $acc->accCode }}"
                                        data-name="{{ $acc->accName }}"
                                    >
                                        {{ $acc->accCode }} - {{ $acc->accName }}
                                    </option>
                                @endforeach
                            </select>

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
                             4 - الحالة (نشط / متوقف)
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
                                name="CusIsStopped"
                                id="cusStatus"
                            >

                                <option value="0">
                                    نشط
                                </option>

                                <option value="1">
                                    متوقف
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


{{-- ================================================
     مودال البحث عن الحساب المحاسبي
================================================ --}}

<div
    class="modal fade"
    id="accountSearchModal"
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
                    اختيار الحساب المحاسبي
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
                        id="accountSearchInput"
                        placeholder="ابحث بالاسم أو رقم الحساب..."
                        autocomplete="off"
                    >

                </div>


                {{-- قائمة النتائج --}}
                <div
                    id="accountSearchResults"
                    class="flex-grow-1"
                    style="overflow-y: auto;"
                >

                    <ul
                        class="list-group list-group-flush"
                        id="accountSearchList"
                    >

                        @foreach($accounts as $acc)

                            <li
                                class="list-group-item list-group-item-action account-search-item"
                                data-id="{{ $acc->accountID }}"
                                data-code="{{ $acc->accCode }}"
                                data-name="{{ $acc->accName }}"
                                style="cursor: pointer;"
                            >
                                <span class="badge bg-secondary me-2">
                                    {{ $acc->accCode }}
                                </span>
                                {{ $acc->accName }}
                            </li>

                        @endforeach

                    </ul>

                </div>

            </div>

        </div>

    </div>
</div>
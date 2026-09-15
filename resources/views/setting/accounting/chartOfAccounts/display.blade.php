<div class="card border-0 shadow-sm">

    {{-- رأس البطاقة --}}
    <div class="card-header bg-body border-bottom">

        <div class="d-flex justify-content-between align-items-center">

            <div class="d-flex align-items-center gap-2">

                <i class="bi bi-diagram-3 text-primary"></i>

                <h6 class="mb-0 fw-bold">
                    شجرة الحسابات
                </h6>

            </div>

        </div>

    </div>


    {{-- جدول الحسابات --}}
    <div class="card-body p-0">

        <div class="table-responsive">

            <table class="table table-striped table-hover align-middle mb-0">

                <thead class="table-light">

                    <tr>

                        <th
                            scope="col"
                            class="text-center"
                            style="width: 70px;"
                        >
                            #
                        </th>

                        <th scope="col">
                            رقم الحساب التحليلي
                        </th>

                        <th scope="col">
                            اسم الحساب
                        </th>

                        <th scope="col">
                            الطبيعة
                        </th>

                        <th scope="col">
                            الحساب الأب
                        </th>

                        <th
                            scope="col"
                            class="text-center"
                        >
                            الحالة
                        </th>

                        <th
                            scope="col"
                            class="text-center"
                            style="width: 180px;"
                        >
                            الإجراءات
                        </th>

                    </tr>

                </thead>


                {{-- جسم الجدول --}}
                <tbody id="accountsTreeBody">

                    <tr>

                        <td
                            colspan="7"
                            class="text-center py-5 text-body-secondary"
                        >

                            <div class="d-flex flex-column align-items-center gap-2">

                                <div
                                    class="spinner-border spinner-border-sm text-primary"
                                    role="status"
                                >
                                    <span class="visually-hidden">
                                        جاري التحميل...
                                    </span>
                                </div>

                                <span>
                                    جاري تحميل الحسابات...
                                </span>

                            </div>

                        </td>

                    </tr>

                </tbody>

            </table>

        </div>


        {{-- Pagination للشجرة --}}
        <div
            class="border-top px-3 py-3"
            id="accountsPaginationContainer"
        >

            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">

                <div
                    class="small text-body-secondary"
                    id="accountsPaginationInfo"
                >
                </div>

                <nav
                    aria-label="صفحات الحسابات"
                >

                    <ul
                        class="pagination pagination-sm mb-0"
                        id="accountsPaginationList"
                    >
                    </ul>

                </nav>

            </div>

        </div>

    </div>

</div>


{{-- ========================================================= --}}
{{-- قوالب حالات جدول الحسابات --}}
{{-- ========================================================= --}}

<template id="accountsLoadingTemplate">

    <tr>

        <td
            colspan="7"
            class="text-center py-5 text-body-secondary"
        >

            <div class="d-flex flex-column align-items-center gap-2">

                <div
                    class="spinner-border spinner-border-sm text-primary"
                    role="status"
                >
                    <span class="visually-hidden">
                        جاري التحميل...
                    </span>
                </div>

                <span>
                    جاري تحميل الحسابات...
                </span>

            </div>

        </td>

    </tr>

</template>


<template id="accountsEmptyTemplate">

    <tr>

        <td
            colspan="7"
            class="text-center py-5 text-body-secondary"
        >

            <div class="d-flex flex-column align-items-center gap-2">

                <i class="bi bi-inbox fs-2"></i>

                <span>
                    لا توجد حسابات مطابقة للبحث
                </span>

            </div>

        </td>

    </tr>

</template>


<template id="accountsErrorTemplate">

    <tr>

        <td
            colspan="7"
            class="text-center py-5 text-danger"
        >

            <div class="d-flex flex-column align-items-center gap-2">

                <i class="bi bi-exclamation-triangle fs-2"></i>

                <span>
                    حدث خطأ أثناء تحميل الحسابات
                </span>

            </div>

        </td>

    </tr>

</template>


{{-- ========================================================= --}}
{{-- قالب صف الحساب --}}
{{-- ========================================================= --}}

<template id="accountRowTemplate">

    <tr class="account-tree-row">

        {{-- الرقم التسلسلي --}}
        <td class="text-center account-row-number">
        </td>


        {{-- رقم الحساب --}}
        <td>

            <div class="d-flex align-items-center gap-2">

                {{-- زر فتح/إغلاق الحسابات الفرعية --}}
                <button
                    type="button"
                    class="btn btn-sm btn-link text-body p-0 account-tree-toggle d-none"
                    title="فتح أو إغلاق الحسابات الفرعية"
                    aria-label="فتح أو إغلاق الحسابات الفرعية"
                >
                    <i class="bi bi-chevron-left"></i>
                </button>


                <span class="account-code fw-semibold">
                </span>

            </div>

        </td>


        {{-- اسم الحساب --}}
        <td>

            <div class="d-flex align-items-center gap-2">

                <span class="account-name">
                </span>


                

            </div>

        </td>


        {{-- الطبيعة --}}
        <td>

            <span class="account-nature">
            </span>

        </td>


        {{-- الحساب الأب --}}
        <td>

            <span class="account-parent text-body-secondary">
            </span>

        </td>


        {{-- الحالة --}}
        <td class="text-center">

            <span class="badge account-status">
            </span>

        </td>


        {{-- الإجراءات --}}
        <td class="text-center">
            {{-- شارة الحساب النظامي --}}
                <span
                    class="badge bg-secondary-subtle text-secondary account-system-badge d-none"
                >
                    نظامي
                </span>

            <div class="d-inline-flex align-items-center gap-1">

                {{-- عرض الحسابات التحليلية --}}
                <button
                    type="button"
                    class="btn btn-sm btn-outline-info show-analytical d-none"
                    title="الحسابات التحليلية"
                    aria-label="الحسابات التحليلية"
                >
                    <i class="bi bi-list-ul"></i>
                </button>


                {{-- تعديل --}}
                <button
                    type="button"
                    class="btn btn-sm btn-outline-primary edit-account"
                    title="تعديل الحساب"
                    aria-label="تعديل الحساب"
                >
                    <i class="bi bi-pencil">تعديل</i>
                </button>


                {{-- حذف --}}
                <button
                    type="button"
                    class="btn btn-sm btn-outline-danger delete-account"
                    title="حذف الحساب"
                    aria-label="حذف الحساب"
                >
                    <i class="bi bi-trash">حذف</i>
                </button>

            </div>

        </td>

    </tr>

</template>


{{-- ========================================================= --}}
{{-- قوالب Pagination --}}
{{-- ========================================================= --}}

<template id="paginationPageTemplate">

    <li class="page-item">

        <button
            type="button"
            class="page-link pagination-page"
        >
        </button>

    </li>

</template>


<template id="paginationPreviousTemplate">

    <li class="page-item">

        <button
            type="button"
            class="page-link pagination-previous"
            aria-label="السابق"
        >
            <i class="bi bi-chevron-right"></i>
        </button>

    </li>

</template>


<template id="paginationNextTemplate">

    <li class="page-item">

        <button
            type="button"
            class="page-link pagination-next"
            aria-label="التالي"
        >
            <i class="bi bi-chevron-left"></i>
        </button>

    </li>

</template>


<template id="paginationEllipsisTemplate">

    <li class="page-item disabled">

        <span class="page-link">
            ...
        </span>

    </li>

</template>


{{-- ========================================================= --}}
{{-- مودال الحسابات التحليلية --}}
{{-- ========================================================= --}}

<div
    class="modal fade"
    id="analyticalAccountsModal"
    tabindex="-1"
    aria-labelledby="analyticalAccountsModalLabel"
    aria-hidden="true"
>

    <div class="modal-dialog modal-xl modal-dialog-scrollable">

        <div class="modal-content">

            {{-- رأس المودال --}}
            <div class="modal-header position-relative">

                <div>

                    <h5
                        class="modal-title fw-bold"
                        id="analyticalAccountsModalLabel"
                    >
                        الحسابات التحليلية
                    </h5>

                    <div
                        class="small text-body-secondary mt-1"
                        id="analyticalAccountsParent"
                    >
                    </div>

                </div>

                <button
                    type="button"
                    class="btn-close position-absolute top-0 start-0 m-3"
                    data-bs-dismiss="modal"
                    aria-label="إغلاق"
                ></button>

            </div>


            {{-- محتوى المودال --}}
            <div class="modal-body">


                {{-- بحث الحسابات التحليلية --}}
                <div class="card border-0 bg-body-tertiary mb-3">

                    <div class="card-body">

                        <div class="row g-3">

                            {{-- رقم الحساب --}}
                            <div class="col-12 col-md-6">

                                <label
                                    for="analyticalSearchCode"
                                    class="form-label"
                                >
                                    رقم الحساب
                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    id="analyticalSearchCode"
                                    placeholder="رقم الحساب"
                                    autocomplete="off"
                                >

                            </div>


                            {{-- اسم الحساب --}}
                            <div class="col-12 col-md-6">

                                <label
                                    for="analyticalSearchName"
                                    class="form-label"
                                >
                                    اسم الحساب
                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    id="analyticalSearchName"
                                    placeholder="اسم الحساب"
                                    autocomplete="off"
                                >

                            </div>

                        </div>

                    </div>

                </div>


                {{-- جدول الحسابات التحليلية --}}
                <div class="table-responsive">

                    <table class="table table-striped table-hover align-middle mb-0">

                        <thead class="table-light">

                            <tr>

                                <th
                                    class="text-center"
                                    style="width: 70px;"
                                >
                                    #
                                </th>

                                <th>
                                    رقم الحساب
                                </th>

                                <th>
                                    اسم الحساب
                                </th>

                                <th>
                                    الطبيعة
                                </th>

                                <th class="text-center">
                                    الحالة
                                </th>

                                <th
                                    class="text-center"
                                    style="width: 140px;"
                                >
                                    الإجراءات
                                </th>

                            </tr>

                        </thead>


                        <tbody id="analyticalAccountsBody">

                            <tr>

                                <td
                                    colspan="6"
                                    class="text-center py-5 text-body-secondary"
                                >
                                    جاري تحميل الحسابات...
                                </td>

                            </tr>

                        </tbody>

                    </table>

                </div>

            </div>


            {{-- Footer --}}
            <div class="modal-footer">

                <div
                    class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 w-100"
                >

                    <div
                        class="small text-body-secondary"
                        id="analyticalPaginationInfo"
                    >
                    </div>

                    <nav aria-label="صفحات الحسابات التحليلية">

                        <ul
                            class="pagination pagination-sm mb-0"
                            id="analyticalPaginationList"
                        >
                        </ul>

                    </nav>

                </div>

            </div>

        </div>

    </div>

</div>


{{-- ========================================================= --}}
{{-- قالب صف الحساب التحليلي --}}
{{-- ========================================================= --}}

<template id="analyticalAccountRowTemplate">

    <tr>

        <td class="text-center analytical-row-number">
        </td>

        <td>

            <span class="analytical-account-code fw-semibold">
            </span>

        </td>

        <td>

            <span class="analytical-account-name">
            </span>

        </td>

        <td>

            <span class=" analytical-account-nature">
            </span>

        </td>

        <td class="text-center">

            <span class="badge analytical-account-status">
            </span>

        </td>

        <td class="text-center">

            <div class="d-inline-flex align-items-center gap-1">

                <button
                    type="button"
                    class="btn btn-sm btn-outline-primary edit-analytical-account"
                    title="تعديل الحساب"
                    aria-label="تعديل الحساب"
                >
                    <i class="bi bi-pencil"></i>
                </button>

                <button
                    type="button"
                    class="btn btn-sm btn-outline-danger delete-analytical-account"
                    title="حذف الحساب"
                    aria-label="حذف الحساب"
                >
                    <i class="bi bi-trash"></i>
                </button>

            </div>

        </td>

    </tr>

</template>


{{-- ========================================================= --}}
{{-- حالات الحسابات التحليلية --}}
{{-- ========================================================= --}}

<template id="analyticalLoadingTemplate">

    <tr>

        <td
            colspan="6"
            class="text-center py-5 text-body-secondary"
        >

            <div class="d-flex flex-column align-items-center gap-2">

                <div
                    class="spinner-border spinner-border-sm text-primary"
                    role="status"
                >
                    <span class="visually-hidden">
                        جاري التحميل...
                    </span>
                </div>

                <span>
                    جاري تحميل الحسابات التحليلية...
                </span>

            </div>

        </td>

    </tr>

</template>


<template id="analyticalEmptyTemplate">

    <tr>

        <td
            colspan="6"
            class="text-center py-5 text-body-secondary"
        >

            <div class="d-flex flex-column align-items-center gap-2">

                <i class="bi bi-inbox fs-2"></i>

                <span>
                    لا توجد حسابات تحليلية
                </span>

            </div>

        </td>

    </tr>

</template>


<template id="analyticalErrorTemplate">

    <tr>

        <td
            colspan="6"
            class="text-center py-5 text-danger"
        >

            <div class="d-flex flex-column align-items-center gap-2">

                <i class="bi bi-exclamation-triangle fs-2"></i>

                <span>
                    حدث خطأ أثناء تحميل الحسابات التحليلية
                </span>

            </div>

        </td>

    </tr>

</template>
@extends('layouts.app')

@section('title', 'دليل الحسابات')

@section('content')

<div class="container-fluid py-3">

    {{-- رسالة النجاح --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="إغلاق">
            </button>
        </div>
    @endif


    {{-- =========================================
         رأس الصفحة
    ========================================== --}}

    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">

        <div>

            <div class="d-flex align-items-center gap-2 mb-1">

                <i class="bi bi-diagram-3 fs-4 text-primary"></i>

                <h4 class="mb-0 fw-bold">
                    دليل الحسابات
                </h4>

            </div>

            <p class="text-body-secondary mb-0">
                إدارة وتنظيم الحسابات.
            </p>

        </div>


        <div class="d-flex flex-wrap gap-2">

            <button
                type="button"
                class="btn btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#addAccountModal"
                id="addAccountBtn">

                <i class="bi bi-plus-lg me-1"></i>

                إضافة حساب

            </button>

        </div>

    </div>


    {{-- =========================================
         البحث
    ========================================== --}}

    @include('setting.accounting.chartOfAccounts.search')


    {{-- =========================================
         جدول الحسابات
    ========================================== --}}

    @include('setting.accounting.chartOfAccounts.display')


    {{-- =========================================
         مودال الإضافة والتعديل
    ========================================== --}}

    @include('setting.accounting.chartOfAccounts.addUpdate')


</div>


{{-- =====================================================
     JavaScript
===================================================== --}}

<script>

document.addEventListener('DOMContentLoaded', function () {


    // =====================================================
    // عناصر الصفحة
    // =====================================================

    const accountForm =
        document.getElementById('accountForm');

    const saveAccountBtn =
        document.getElementById('saveAccountBtn');

    const accountModal =
        document.getElementById('addAccountModal');

    const accountIDInput =
        document.getElementById('accountID');

    const modalTitle =
        document.getElementById('accountModalTitle');

    const addAccountBtn =
        document.getElementById('addAccountBtn');


    // =====================================================
    // التأكد من وجود عناصر المودال
    // =====================================================

    if (
        !accountForm ||
        !saveAccountBtn ||
        !accountModal ||
        !accountIDInput ||
        !modalTitle
    ) {

        console.error(
            'لم يتم العثور على أحد عناصر نموذج الحساب'
        );

        return;
    }


    // =====================================================
    // البحث برقم الحساب
    // =====================================================

    const searchCode =
        document.getElementById('searchCode');

    let searchTimer;


    if (searchCode) {

        searchCode.addEventListener('input', function () {

            clearTimeout(searchTimer);


            searchTimer = setTimeout(function () {

                const url = new URL(
                    "{{ route('chartOfAccounts.list') }}",
                    window.location.origin
                );


                url.searchParams.set(
                    'search_code',
                    searchCode.value
                );


                fetch(url, {

                    method: 'GET',

                    headers: {
                        'X-Requested-With':
                            'XMLHttpRequest'
                    }

                })

                .then(response => {

                    if (!response.ok) {

                        throw new Error(
                            'حدث خطأ أثناء جلب الحسابات'
                        );

                    }

                    return response.text();

                })

                .then(html => {

                    const accountsTable =
                        document.getElementById('accountsTable');


                    if (accountsTable) {

                        accountsTable.outerHTML =
                            html;

                    }

                })

                .catch(error => {

                    console.error(
                        'خطأ البحث:',
                        error
                    );

                });

            }, 300);

        });

    }


    // =====================================================
    // زر إضافة حساب
    // =====================================================

    if (addAccountBtn) {

        addAccountBtn.addEventListener(
            'click',
            function () {


                // تفريغ الحقول
                accountForm.reset();


                // حذف ID
                accountIDInput.value = '';


                // رابط الإضافة
                accountForm.action =
                    "{{ route('chartOfAccounts.store') }}";


                // عنوان المودال
                modalTitle.textContent =
                    'إضافة حساب جديد';


                // نص الزر
                saveAccountBtn.textContent =
                    'حفظ الحساب';

            }
        );

    }


    // =====================================================
    // تعديل الحساب
    // =====================================================

    document.addEventListener(
        'click',
        function (event) {


            const editButton =
                event.target.closest('.edit-account');


            if (!editButton) {
                return;
            }


            const id =
                editButton.dataset.id;


            console.log(
                'جاري جلب الحساب:',
                id
            );


            // =================================================
            // جلب بيانات الحساب
            // =================================================

            fetch(
                "{{ url('/settings/accounting/chartOfAccounts') }}/" + id,
                {
                    method: 'GET',

                    headers: {
                        'X-Requested-With':
                            'XMLHttpRequest',

                        'Accept':
                            'application/json'
                    }
                }
            )

            .then(response => {

                console.log(
                    'HTTP Status:',
                    response.status
                );


                if (!response.ok) {

                    throw new Error(
                        'حدث خطأ أثناء جلب الحساب'
                    );

                }


                return response.json();

            })


            .then(data => {

                console.log(
                    'بيانات الحساب:',
                    data
                );


                if (!data.success) {

                    throw new Error(
                        'لم يتم العثور على الحساب'
                    );

                }


                const account =
                    data.account;


                // =================================================
                // تعبئة الحقول
                // =================================================

                accountIDInput.value =
                    account.accountID ?? '';


                accountForm.elements['accCode'].value =
                    account.accCode ?? '';


                accountForm.elements['accName'].value =
                    account.accName ?? '';


                accountForm.elements['accTypeID'].value =
                    account.accTypeID ?? '';


                accountForm.elements['accParent'].value =
                    account.accParent ?? '';


                accountForm.elements['nature'].value =
                    account.nature ?? '';


                accountForm.elements['IsActive'].value =
                    account.IsActive ?? '';


                accountForm.elements['accLevel'].value =
                    account.accLevel ?? '';


                accountForm.elements['isPostable'].value =
                    account.isPostable ?? '';


                // =================================================
                // وضع التعديل
                // =================================================

                modalTitle.textContent =
                    'تعديل الحساب';


                saveAccountBtn.textContent =
                    'حفظ التعديلات';


                // =================================================
                // رابط update
                // =================================================

                accountForm.action =
                    "{{ url('/settings/accounting/chartOfAccounts') }}/"
                    + account.accountID;


                // =================================================
                // فتح المودال
                // =================================================

                const modal =
                    bootstrap.Modal.getOrCreateInstance(
                        accountModal
                    );


                modal.show();

            })


            .catch(error => {

                console.error(
                    'خطأ التعديل:',
                    error
                );


                alert(
                    'حدث خطأ أثناء جلب بيانات الحساب'
                );

            });

        }
    );


    // =====================================================
    // حفظ الحساب
    // إضافة أو تعديل
    // =====================================================

    accountForm.addEventListener(
        'submit',
        function (event) {


            event.preventDefault();


            // تعطيل الزر
            saveAccountBtn.disabled =
                true;


            saveAccountBtn.textContent =
                'جاري الحفظ...';


            // =================================================
            // بيانات الفورم
            // =================================================

            const formData =
                new FormData(accountForm);


            // =================================================
            // إذا كان هناك accountID
            // فهذا يعني أننا في وضع التعديل
            // =================================================

            if (accountIDInput.value) {

                formData.append(
                    '_method',
                    'PUT'
                );

            }


            // =================================================
            // إرسال البيانات
            // =================================================

            fetch(
                accountForm.action,
                {
                    method: 'POST',

                    body: formData,

                    headers: {

                        'X-Requested-With':
                            'XMLHttpRequest',

                        'Accept':
                            'application/json'

                    }

                }
            )


            .then(response => {

                if (!response.ok) {

                    return response.json()
                        .then(error => {

                            throw error;

                        });

                }


                return response.json();

            })


            .then(data => {


                if (!data.success) {

                    throw new Error(
                        data.message ||
                        'حدث خطأ أثناء الحفظ'
                    );

                }


                // =================================================
                // رسالة النجاح
                // =================================================

                const alertBox =
                    document.createElement('div');


                alertBox.className =
                    'alert alert-success alert-dismissible fade show';


                alertBox.setAttribute(
                    'role',
                    'alert'
                );


                alertBox.innerHTML = `

                    ${data.message}

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                        aria-label="إغلاق">
                    </button>

                `;


                document
                    .querySelector('.container-fluid')
                    .prepend(alertBox);


                // =================================================
                // إغلاق المودال
                // =================================================

                const modal =
                    bootstrap.Modal.getInstance(
                        accountModal
                    );


                if (modal) {

                    modal.hide();

                }


                // =================================================
                // تحديث الجدول فقط
                // =================================================

                return fetch(
                    "{{ route('chartOfAccounts.list') }}",
                    {
                        method: 'GET',

                        headers: {

                            'X-Requested-With':
                                'XMLHttpRequest'

                        }

                    }
                );

            })


            .then(response => {

                if (!response) {
                    return null;
                }


                return response.text();

            })


            .then(html => {

                if (!html) {
                    return;
                }


                const accountsTable =
                    document.getElementById('accountsTable');


                if (accountsTable) {

                    accountsTable.outerHTML =
                        html;

                }


                // =================================================
                // إعادة الفورم إلى وضع الإضافة
                // =================================================

                accountForm.reset();


                accountIDInput.value =
                    '';


                accountForm.action =
                    "{{ route('chartOfAccounts.store') }}";


                modalTitle.textContent =
                    'إضافة حساب جديد';


                saveAccountBtn.textContent =
                    'حفظ الحساب';

            })


            .catch(error => {

                console.error(
                    'خطأ الحفظ:',
                    error
                );


                alert(
                    'حدث خطأ أثناء حفظ الحساب'
                );

            })


            .finally(() => {

                saveAccountBtn.disabled =
                    false;

            });

        }
    );

});

</script>


@endsection
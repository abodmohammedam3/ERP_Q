/**
 * ============================================================
 * حركات المخزون
 * Inventory Movements
 * Laravel 12 + Bootstrap 5.3.8 + Bootstrap Icons
 * ============================================================
 *
 * أوضاع الشاشة:
 *
 * view = عرض حركة موجودة
 * add  = إضافة حركة يدوية
 *
 * لا يوجد edit ولا delete.
 *
 * النوافذ المنبثقة تعمل فقط عند:
 * - أمر توريد مخزني
 * - أمر صرف مخزني
 *
 * ============================================================
 */


/* ============================================================
   حالة الشاشة
   ============================================================ */

let movementMode = 'view';


/*
 * نوع الحركة الحالية
 *
 * supply = توريد مخزني
 * issue  = صرف مخزني
 */
let currentMovementType = null;


/*
 * بيانات الحركة الحالية
 */
let currentMovement = null;


/*
 * عداد مؤقت للصفوف
 */
let movementRowCounter = 0;


/*
 * ============================================================
 * بيانات تجريبية مؤقتة
 * سيتم استبدالها لاحقاً ببيانات Laravel / Database
 * ============================================================
 */


/* المخازن */

const movementWarehouses = [
    {
        id: 1,
        name: 'المخزن الرئيسي'
    },
    {
        id: 2,
        name: 'المخزن الثاني'
    },
    {
        id: 3,
        name: 'مخزن الفرع'
    }
];


/* الأصناف */

const movementItems = [
    {
        id: 1,
        name: 'أرحبي'
    },
    {
        id: 2,
        name: 'ماوية'
    },
    {
        id: 3,
        name: 'حاشدي'
    }
];


/* الأنواع */

const movementTypes = [
    {
        id: 1,
        item_id: 1,
        name: 'قطل'
    },
    {
        id: 2,
        item_id: 1,
        name: 'عود أحمر'
    },
    {
        id: 3,
        item_id: 2,
        name: 'قطل'
    },
    {
        id: 4,
        item_id: 2,
        name: 'عود'
    },
    {
        id: 5,
        item_id: 3,
        name: 'متوسط'
    }
];


/* الوحدات */

const movementUnits = [
    {
        id: 1,
        name: 'حبة'
    },
    {
        id: 2,
        name: 'كيلو'
    },
    {
        id: 3,
        name: 'كيس'
    }
];


/* ============================================================
   تشغيل الشاشة
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {

    /*
     * عند فتح الشاشة لأول مرة
     * تكون في وضع العرض.
     */

    setMovementMode('view');

    clearMovementForm();

});


/* ============================================================
   تغيير وضع الشاشة
   ============================================================ */

function setMovementMode(mode) {

    /*
     * لا نسمح إلا بوضعين
     */

    if (mode !== 'view' && mode !== 'add') {
        return;
    }


    movementMode = mode;


    /*
     * عناصر الرأس
     */

    const movementDate =
        document.getElementById('movementDate');

    const movementDocumentNumber =
        document.getElementById('movementDocumentNumber');

    const movementStatement =
        document.getElementById('movementStatement');


    /*
     * زر إضافة صف
     */

    const btnAddMovementRow =
        document.getElementById('btnAddMovementRow');


    /*
     * منطقة أزرار الحفظ
     */

    const movementSaveActions =
        document.getElementById('movementSaveActions');


    /*
     * في وضع الإضافة
     */

    if (mode === 'add') {

        if (movementDate) {
            movementDate.removeAttribute('readonly');
        }

        if (movementDocumentNumber) {
            movementDocumentNumber.removeAttribute('readonly');
        }

        if (movementStatement) {
            movementStatement.removeAttribute('readonly');
        }

        if (btnAddMovementRow) {
            btnAddMovementRow.disabled = false;
        }

        if (movementSaveActions) {
            movementSaveActions.classList.remove('d-none');
        }

    }


    /*
     * في وضع العرض
     */

    else {

        if (movementDate) {
            movementDate.setAttribute('readonly', true);
        }

        if (movementDocumentNumber) {
            movementDocumentNumber.setAttribute('readonly', true);
        }

        if (movementStatement) {
            movementStatement.setAttribute('readonly', true);
        }

        if (btnAddMovementRow) {
            btnAddMovementRow.disabled = true;
        }

        if (movementSaveActions) {
            movementSaveActions.classList.add('d-none');
        }

    }

}


/* ============================================================
   بدء أمر توريد مخزني
   ============================================================ */

function startSupplyMovement() {

    /*
     * هذه حركة يدوية.
     * لذلك يسمح باستخدام النوافذ المنبثقة.
     */

    currentMovementType = 'supply';


    clearMovementForm();


    setMovementMode('add');


    /*
     * تحديد نوع الحركة
     */

    const movementType =
        document.getElementById('movementType');

    if (movementType) {
        movementType.value = 'توريد مخزني';
    }


    /*
     * تحديد الاتجاه
     */

    const movementDirection =
        document.getElementById('movementDirection');

    if (movementDirection) {
        movementDirection.value = 'دخول';
    }


    /*
     * التاريخ الحالي
     */

    setTodayDate();


    /*
     * إنشاء رقم حركة تجريبي
     */

    const movementDisplayId =
        document.getElementById('movementDisplayId');

    if (movementDisplayId) {

        movementDisplayId.value =
            generateTemporaryMovementNumber();

    }


    /*
     * إضافة صف فارغ مباشرة
     */

    addMovementRow();

}


/* ============================================================
   بدء أمر صرف مخزني
   ============================================================ */

function startIssueMovement() {

    /*
     * حركة يدوية.
     */

    currentMovementType = 'issue';


    clearMovementForm();


    setMovementMode('add');


    /*
     * نوع الحركة
     */

    const movementType =
        document.getElementById('movementType');

    if (movementType) {
        movementType.value = 'صرف مخزني';
    }


    /*
     * اتجاه الحركة
     */

    const movementDirection =
        document.getElementById('movementDirection');

    if (movementDirection) {
        movementDirection.value = 'خروج';
    }


    /*
     * التاريخ
     */

    setTodayDate();


    /*
     * رقم الحركة
     */

    const movementDisplayId =
        document.getElementById('movementDisplayId');

    if (movementDisplayId) {

        movementDisplayId.value =
            generateTemporaryMovementNumber();

    }


    /*
     * إضافة صف
     */

    addMovementRow();

}


/* ============================================================
   تنظيف نموذج الحركة
   ============================================================ */

function clearMovementForm() {

    currentMovement = null;


    /*
     * الرأس
     */

    setValue('movementDisplayId', '');
    setValue('movementType', '');
    setValue('movementDirection', '');
    setValue('movementDate', '');
    setValue('movementDocumentNumber', '');
    setValue('movementStatement', '');
    setValue('movementWarehouse', '');
    setValue('movementWarehouseId', '');


    /*
     * التفاصيل
     */

    const details =
        document.getElementById('movementDetails');

    if (details) {
        details.innerHTML = '';
    }


    /*
     * الإجمالي
     */

    setValue('movementTotal', '0.00');


    movementRowCounter = 0;


    /*
     * العودة إلى العرض
     */

    setMovementMode('view');

}


/* ============================================================
   إضافة صف تفاصيل
   ============================================================ */

function addMovementRow() {

    /*
     * لا يسمح بإضافة صف في وضع العرض
     */

    if (movementMode !== 'add') {
        return;
    }


    const tbody =
        document.getElementById('movementDetails');

    if (!tbody) {
        return;
    }


    movementRowCounter++;


    const row =
        document.createElement('tr');


    row.className =
        'movement-detail-row';


    row.dataset.rowId =
        movementRowCounter;


    /*
     * إنشاء الصف
     *
     * لا يوجد عمود إجراء.
     */

    row.innerHTML = `

        <td class="movement-row-number">
            ${movementRowCounter}
        </td>


        <td>

            <input
                type="hidden"
                class="movement-item-id"
            >

            <input
                type="text"
                class="form-control form-control-sm movement-item"
                placeholder="اختر الصنف"
                readonly
            >

        </td>


        <td>

            <input
                type="hidden"
                class="movement-type-id"
            >

            <input
                type="text"
                class="form-control form-control-sm movement-type"
                placeholder="اختر النوع"
                readonly
            >

        </td>


        <td>

            <input
                type="text"
                class="form-control form-control-sm movement-code"
                placeholder="الرمز"
            >

        </td>


        <td>

            <input
                type="hidden"
                class="movement-warehouse-id"
            >

            <input
                type="text"
                class="form-control form-control-sm movement-warehouse"
                placeholder="اختر المخزن"
                readonly
            >

        </td>


        <td>

            <input
                type="hidden"
                class="movement-unit-id"
            >

            <input
                type="text"
                class="form-control form-control-sm movement-unit"
                placeholder="اختر الوحدة"
                readonly
            >

        </td>


        <td>

            <input
                type="number"
                class="form-control form-control-sm movement-quantity text-center"
                min="0"
                step="0.01"
                value="0"
            >

        </td>


        <td>

            <input
                type="number"
                class="form-control form-control-sm movement-unit-cost text-center"
                min="0"
                step="0.01"
                value="0"
            >

        </td>


        <td>

            <input
                type="number"
                class="form-control form-control-sm movement-sale-price text-center"
                min="0"
                step="0.01"
                value="0"
            >

        </td>


        <td>

            <input
                type="text"
                class="form-control form-control-sm movement-total text-center"
                value="0.00"
                readonly
            >

        </td>

    `;


    tbody.appendChild(row);


    enableMovementRow(row);


}


/* ============================================================
   تفعيل صف الحركة
   ============================================================ */

function enableMovementRow(row) {

    if (!row) {
        return;
    }


    /*
     * اختيار الصنف
     */

    const itemInput =
        row.querySelector('.movement-item');

    if (itemInput) {

        itemInput.addEventListener(
            'click',
            function () {

                if (movementMode !== 'add') {
                    return;
                }

                openMovementItemModal(row);

            }
        );

    }


    /*
     * اختيار النوع
     */

    const typeInput =
        row.querySelector('.movement-type');

    if (typeInput) {

        typeInput.addEventListener(
            'click',
            function () {

                if (movementMode !== 'add') {
                    return;
                }

                openMovementTypeModal(row);

            }
        );

    }


    /*
     * اختيار المخزن
     */

    const warehouseInput =
        row.querySelector('.movement-warehouse');

    if (warehouseInput) {

        warehouseInput.addEventListener(
            'click',
            function () {

                if (movementMode !== 'add') {
                    return;
                }

                openMovementWarehouseModal(row);

            }
        );

    }


    /*
     * اختيار الوحدة
     */

    const unitInput =
        row.querySelector('.movement-unit');

    if (unitInput) {

        unitInput.addEventListener(
            'click',
            function () {

                if (movementMode !== 'add') {
                    return;
                }

                openMovementUnitModal(row);

            }
        );

    }


    /*
     * الكمية
     */

    const quantity =
        row.querySelector('.movement-quantity');


    /*
     * تكلفة الوحدة
     */

    const unitCost =
        row.querySelector('.movement-unit-cost');


    if (quantity) {

        quantity.addEventListener(
            'input',
            function () {
                calculateMovementRow(row);
            }
        );

    }


    if (unitCost) {

        unitCost.addEventListener(
            'input',
            function () {
                calculateMovementRow(row);
            }
        );

    }

}


/* ============================================================
   حساب إجمالي الصف
   ============================================================ */

function calculateMovementRow(row) {

    if (!row) {
        return;
    }


    const quantity =
        parseFloat(
            row.querySelector('.movement-quantity')?.value
        ) || 0;


    const unitCost =
        parseFloat(
            row.querySelector('.movement-unit-cost')?.value
        ) || 0;


    const total =
        quantity * unitCost;


    const totalInput =
        row.querySelector('.movement-total');


    if (totalInput) {

        totalInput.value =
            total.toFixed(2);

    }


    calculateMovementTotal();

}


/* ============================================================
   حساب إجمالي الحركة
   ============================================================ */

function calculateMovementTotal() {

    let total = 0;


    const rows =
        document.querySelectorAll(
            '#movementDetails .movement-detail-row'
        );


    rows.forEach(function (row) {

        const rowTotal =
            parseFloat(
                row.querySelector('.movement-total')?.value
            ) || 0;


        total += rowTotal;

    });


    setValue(
        'movementTotal',
        total.toFixed(2)
    );

}


/* ============================================================
   نافذة اختيار الصنف
   ============================================================ */

function openMovementItemModal(row) {

    if (movementMode !== 'add') {
        return;
    }


    const modalId =
        'movementItemModal';


    const modal =
        createMovementModal(
            modalId,
            'اختيار الصنف',
            `
            <div class="mb-3">

                <input
                    type="text"
                    id="movementItemSearchInput"
                    class="form-control"
                    placeholder="بحث عن الصنف..."
                >

            </div>

            <div
                id="movementItemResults"
                class="list-group"
            ></div>
            `
        );


    /*
     * البحث
     */

    const searchInput =
        document.getElementById(
            'movementItemSearchInput'
        );


    function renderItems(search = '') {

        const results =
            document.getElementById(
                'movementItemResults'
            );


        if (!results) {
            return;
        }


        const filtered =
            movementItems.filter(function (item) {

                return item.name
                    .toLowerCase()
                    .includes(search.toLowerCase());

            });


        results.innerHTML = '';


        filtered.forEach(function (item) {

            const button =
                document.createElement('button');


            button.type = 'button';

            button.className =
                'list-group-item list-group-item-action';


            button.textContent =
                item.name;


            button.addEventListener(
                'click',
                function () {

                    selectMovementItem(
                        row,
                        item
                    );

                    bootstrap.Modal
                        .getInstance(modal)
                        .hide();

                }
            );


            results.appendChild(button);

        });

    }


    if (searchInput) {

        searchInput.addEventListener(
            'input',
            function () {

                renderItems(
                    this.value
                );

            }
        );

    }


    renderItems();


    bootstrap.Modal
        .getOrCreateInstance(modal)
        .show();

}


/* ============================================================
   اختيار الصنف
   ============================================================ */

function selectMovementItem(row, item) {

    if (!row || !item) {
        return;
    }


    const idInput =
        row.querySelector(
            '.movement-item-id'
        );


    const nameInput =
        row.querySelector(
            '.movement-item'
        );


    if (idInput) {
        idInput.value = item.id;
    }


    if (nameInput) {
        nameInput.value = item.name;
    }


    /*
     * عند تغيير الصنف
     * نمسح النوع السابق.
     */

    const typeId =
        row.querySelector(
            '.movement-type-id'
        );


    const typeName =
        row.querySelector(
            '.movement-type'
        );


    if (typeId) {
        typeId.value = '';
    }


    if (typeName) {
        typeName.value = '';
    }

}


/* ============================================================
   نافذة اختيار النوع
   ============================================================ */

function openMovementTypeModal(row) {

    if (movementMode !== 'add') {
        return;
    }


    const itemId =
        row.querySelector(
            '.movement-item-id'
        )?.value;


    if (!itemId) {

        alert(
            'يرجى اختيار الصنف أولاً.'
        );

        return;

    }


    const modal =
        createMovementModal(
            'movementTypeModal',
            'اختيار النوع',
            `
            <div class="mb-3">

                <input
                    type="text"
                    id="movementTypeSearchInput"
                    class="form-control"
                    placeholder="بحث عن النوع..."
                >

            </div>

            <div
                id="movementTypeResults"
                class="list-group"
            ></div>
            `
        );


    const searchInput =
        document.getElementById(
            'movementTypeSearchInput'
        );


    function renderTypes(search = '') {

        const results =
            document.getElementById(
                'movementTypeResults'
            );


        if (!results) {
            return;
        }


        const filtered =
            movementTypes.filter(function (type) {

                const belongsToItem =
                    String(type.item_id) ===
                    String(itemId);


                const matchesSearch =
                    type.name
                        .toLowerCase()
                        .includes(
                            search.toLowerCase()
                        );


                return belongsToItem &&
                    matchesSearch;

            });


        results.innerHTML = '';


        if (filtered.length === 0) {

            results.innerHTML = `
                <div class="alert alert-warning mb-0">
                    لا توجد أنواع لهذا الصنف.
                </div>
            `;

            return;
        }


        filtered.forEach(function (type) {

            const button =
                document.createElement('button');


            button.type = 'button';

            button.className =
                'list-group-item list-group-item-action';


            button.textContent =
                type.name;


            button.addEventListener(
                'click',
                function () {

                    selectMovementType(
                        row,
                        type
                    );


                    bootstrap.Modal
                        .getInstance(modal)
                        .hide();

                }
            );


            results.appendChild(button);

        });

    }


    if (searchInput) {

        searchInput.addEventListener(
            'input',
            function () {

                renderTypes(
                    this.value
                );

            }
        );

    }


    renderTypes();


    bootstrap.Modal
        .getOrCreateInstance(modal)
        .show();

}


/* ============================================================
   اختيار النوع
   ============================================================ */

function selectMovementType(row, type) {

    if (!row || !type) {
        return;
    }


    const idInput =
        row.querySelector(
            '.movement-type-id'
        );


    const nameInput =
        row.querySelector(
            '.movement-type'
        );


    if (idInput) {
        idInput.value = type.id;
    }


    if (nameInput) {
        nameInput.value = type.name;
    }

}


/* ============================================================
   نافذة اختيار المخزن
   ============================================================ */

function openMovementWarehouseModal(row) {

    if (movementMode !== 'add') {
        return;
    }


    const modal =
        createMovementModal(
            'movementWarehouseModal',
            'اختيار المخزن',
            `
            <div class="mb-3">

                <input
                    type="text"
                    id="movementWarehouseSearchInput"
                    class="form-control"
                    placeholder="بحث عن المخزن..."
                >

            </div>

            <div
                id="movementWarehouseResults"
                class="list-group"
            ></div>
            `
        );


    const searchInput =
        document.getElementById(
            'movementWarehouseSearchInput'
        );


    function renderWarehouses(search = '') {

        const results =
            document.getElementById(
                'movementWarehouseResults'
            );


        if (!results) {
            return;
        }


        const filtered =
            movementWarehouses.filter(
                function (warehouse) {

                    return warehouse.name
                        .toLowerCase()
                        .includes(
                            search.toLowerCase()
                        );

                }
            );


        results.innerHTML = '';


        filtered.forEach(
            function (warehouse) {

                const button =
                    document.createElement(
                        'button'
                    );


                button.type = 'button';

                button.className =
                    'list-group-item list-group-item-action';


                button.textContent =
                    warehouse.name;


                button.addEventListener(
                    'click',
                    function () {

                        selectMovementWarehouse(
                            row,
                            warehouse
                        );


                        bootstrap.Modal
                            .getInstance(modal)
                            .hide();

                    }
                );


                results.appendChild(
                    button
                );

            }
        );

    }


    if (searchInput) {

        searchInput.addEventListener(
            'input',
            function () {

                renderWarehouses(
                    this.value
                );

            }
        );

    }


    renderWarehouses();


    bootstrap.Modal
        .getOrCreateInstance(modal)
        .show();

}


/* ============================================================
   اختيار المخزن
   ============================================================ */

function selectMovementWarehouse(
    row,
    warehouse
) {

    if (!row || !warehouse) {
        return;
    }


    const idInput =
        row.querySelector(
            '.movement-warehouse-id'
        );


    const nameInput =
        row.querySelector(
            '.movement-warehouse'
        );


    if (idInput) {
        idInput.value =
            warehouse.id;
    }


    if (nameInput) {
        nameInput.value =
            warehouse.name;
    }

}


/* ============================================================
   نافذة اختيار الوحدة
   ============================================================ */

function openMovementUnitModal(row) {

    if (movementMode !== 'add') {
        return;
    }


    const modal =
        createMovementModal(
            'movementUnitModal',
            'اختيار الوحدة',
            `
            <div class="mb-3">

                <input
                    type="text"
                    id="movementUnitSearchInput"
                    class="form-control"
                    placeholder="بحث عن الوحدة..."
                >

            </div>

            <div
                id="movementUnitResults"
                class="list-group"
            ></div>
            `
        );


    const searchInput =
        document.getElementById(
            'movementUnitSearchInput'
        );


    function renderUnits(search = '') {

        const results =
            document.getElementById(
                'movementUnitResults'
            );


        if (!results) {
            return;
        }


        const filtered =
            movementUnits.filter(
                function (unit) {

                    return unit.name
                        .toLowerCase()
                        .includes(
                            search.toLowerCase()
                        );

                }
            );


        results.innerHTML = '';


        filtered.forEach(
            function (unit) {

                const button =
                    document.createElement(
                        'button'
                    );


                button.type = 'button';

                button.className =
                    'list-group-item list-group-item-action';


                button.textContent =
                    unit.name;


                button.addEventListener(
                    'click',
                    function () {

                        selectMovementUnit(
                            row,
                            unit
                        );


                        bootstrap.Modal
                            .getInstance(modal)
                            .hide();

                    }
                );


                results.appendChild(
                    button
                );

            }
        );

    }


    if (searchInput) {

        searchInput.addEventListener(
            'input',
            function () {

                renderUnits(
                    this.value
                );

            }
        );

    }


    renderUnits();


    bootstrap.Modal
        .getOrCreateInstance(modal)
        .show();

}


/* ============================================================
   اختيار الوحدة
   ============================================================ */

function selectMovementUnit(
    row,
    unit
) {

    if (!row || !unit) {
        return;
    }


    const idInput =
        row.querySelector(
            '.movement-unit-id'
        );


    const nameInput =
        row.querySelector(
            '.movement-unit'
        );


    if (idInput) {
        idInput.value =
            unit.id;
    }


    if (nameInput) {
        nameInput.value =
            unit.name;
    }

}


/* ============================================================
   إنشاء Modal ديناميكي
   ============================================================ */

function createMovementModal(
    id,
    title,
    body
) {

    /*
     * إذا كانت نافذة موجودة
     * نحذفها أولاً حتى لا تتكرر.
     */

    const oldModal =
        document.getElementById(id);


    if (oldModal) {

        const oldInstance =
            bootstrap.Modal.getInstance(
                oldModal
            );


        if (oldInstance) {
            oldInstance.dispose();
        }


        oldModal.remove();

    }


    const modal =
        document.createElement('div');


    modal.className =
        'modal fade';


    modal.id = id;


    modal.tabIndex = -1;


    modal.setAttribute(
        'aria-hidden',
        'true'
    );


    modal.innerHTML = `

        <div class="modal-dialog modal-dialog-centered">

            <div class="modal-content">

                <div class="modal-header">

                    <h5 class="modal-title">
                        ${title}
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="إغلاق"
                    ></button>

                </div>

                <div class="modal-body">

                    ${body}

                </div>

            </div>

        </div>

    `;


    document.body.appendChild(modal);


    return modal;

}


/* ============================================================
   البحث في حركات المخزون
   ============================================================ */

function searchMovements() {

    /*
     * البحث لا يستخدم Modal.
     */

    const warehouse =
        document.getElementById(
            'searchWarehouse'
        )?.value || '';


    const movementType =
        document.getElementById(
            'searchMovementType'
        )?.value || '';


    const dateFrom =
        document.getElementById(
            'searchDateFrom'
        )?.value || '';


    const dateTo =
        document.getElementById(
            'searchDateTo'
        )?.value || '';


    /*
     * حالياً بيانات تجريبية.
     *
     * لاحقاً:
     *
     * fetch()
     *    ↓
     * Laravel Route
     *    ↓
     * Controller
     *    ↓
     * Database
     */

    const results =
        getTemporaryMovementResults(
            warehouse,
            movementType,
            dateFrom,
            dateTo
        );


    displayMovementSearchResults(
        results
    );


}


/* ============================================================
   بيانات بحث تجريبية
   ============================================================ */

function getTemporaryMovementResults(
    warehouse,
    movementType,
    dateFrom,
    dateTo
) {

    /*
     * هذه بيانات مؤقتة فقط.
     */

    const movements = [

        {
            id: 1001,
            display_id: 'MOV-0001',
            type: 'توريد مخزني',
            type_code: 'supply',
            direction: 'دخول',
            date: '2026-09-01',
            document_number: 'SUP-001',
            warehouse_id: 1,
            warehouse_name: 'المخزن الرئيسي',
            statement: 'توريد مخزني يدوي'
        },

        {
            id: 1002,
            display_id: 'MOV-0002',
            type: 'صرف مخزني',
            type_code: 'issue',
            direction: 'خروج',
            date: '2026-09-02',
            document_number: 'ISS-001',
            warehouse_id: 2,
            warehouse_name: 'المخزن الثاني',
            statement: 'صرف مخزني'
        }

    ];


    return movements.filter(
        function (movement) {

            /*
             * المخزن
             */

            if (
                warehouse &&
                String(movement.warehouse_id) !==
                String(warehouse)
            ) {
                return false;
            }


            /*
             * نوع الحركة
             */

            if (
                movementType &&
                movement.type_code !==
                movementType
            ) {
                return false;
            }


            /*
             * من تاريخ
             */

            if (
                dateFrom &&
                movement.date < dateFrom
            ) {
                return false;
            }


            /*
             * إلى تاريخ
             */

            if (
                dateTo &&
                movement.date > dateTo
            ) {
                return false;
            }


            return true;

        }
    );

}


/* ============================================================
   عرض نتائج البحث
   ============================================================ */

function displayMovementSearchResults(
    results
) {

    const container =
        document.getElementById(
            'movementSearchResults'
        );


    const tbody =
        document.getElementById(
            'movementSearchResultsBody'
        );


    if (!container || !tbody) {
        return;
    }


    tbody.innerHTML = '';


    /*
     * لا توجد نتائج
     */

    if (results.length === 0) {

        tbody.innerHTML = `

            <tr>

                <td
                    colspan="6"
                    class="text-center text-muted"
                >

                    لا توجد حركات مطابقة للبحث.

                </td>

            </tr>

        `;


        container.classList.remove(
            'd-none'
        );


        return;

    }


    /*
     * النتائج
     */

    results.forEach(
        function (movement) {

            const row =
                document.createElement('tr');


            row.style.cursor =
                'pointer';


            row.innerHTML = `

                <td>
                    ${movement.display_id}
                </td>

                <td>
                    ${movement.type}
                </td>

                <td>
                    ${movement.direction}
                </td>

                <td>
                    ${movement.date}
                </td>

                <td>
                    ${movement.document_number}
                </td>

                <td>
                    ${movement.warehouse_name}
                </td>

            `;


            /*
             * عند الضغط على النتيجة
             * يتم تحميل الحركة في وضع view.
             */

            row.addEventListener(
                'click',
                function () {

                    loadMovement(
                        movement
                    );

                }
            );


            tbody.appendChild(row);

        }
    );


    container.classList.remove(
        'd-none'
    );

}


/* ============================================================
   تحميل حركة موجودة
   ============================================================ */

function loadMovement(movement) {

    if (!movement) {
        return;
    }


    /*
     * مهم:
     *
     * هذه حركة موجودة في النظام.
     *
     * لذلك لا نفتح أي Modal.
     */

    clearMovementForm();


    currentMovement =
        movement;


    setMovementMode('view');


    /*
     * الرأس
     */

    setValue(
        'movementDisplayId',
        movement.display_id || ''
    );


    setValue(
        'movementType',
        movement.type || ''
    );


    setValue(
        'movementDirection',
        movement.direction || ''
    );


    setValue(
        'movementDate',
        movement.date || ''
    );


    setValue(
        'movementDocumentNumber',
        movement.document_number || ''
    );


    setValue(
        'movementStatement',
        movement.statement || ''
    );


    setValue(
        'movementWarehouse',
        movement.warehouse_name || ''
    );


    setValue(
        'movementWarehouseId',
        movement.warehouse_id || ''
    );


    /*
     * التفاصيل
     *
     * إذا كانت التفاصيل موجودة مع الحركة
     * نقوم بعرضها مباشرة.
     */

    if (movement.details) {

        movement.details.forEach(
            function (detail) {

                addReadOnlyMovementRow(
                    detail
                );

            }
        );

    }


    calculateMovementTotal();

}


/* ============================================================
   إضافة صف قراءة فقط
   ============================================================ */

function addReadOnlyMovementRow(
    detail
) {

    const tbody =
        document.getElementById(
            'movementDetails'
        );


    if (!tbody) {
        return;
    }


    movementRowCounter++;


    const row =
        document.createElement('tr');


    row.className =
        'movement-detail-row';


    row.dataset.rowId =
        movementRowCounter;


    row.innerHTML = `

        <td>
            ${movementRowCounter}
        </td>


        <td>

            <input
                type="hidden"
                class="movement-item-id"
                value="${escapeHtml(detail.item_id || '')}"
            >

            <input
                type="text"
                class="form-control form-control-sm"
                value="${escapeHtml(detail.item_name || '')}"
                readonly
            >

        </td>


        <td>

            <input
                type="hidden"
                class="movement-type-id"
                value="${escapeHtml(detail.type_id || '')}"
            >

            <input
                type="text"
                class="form-control form-control-sm"
                value="${escapeHtml(detail.type_name || '')}"
                readonly
            >

        </td>


        <td>

            <input
                type="text"
                class="form-control form-control-sm"
                value="${escapeHtml(detail.code || '')}"
                readonly
            >

        </td>


        <td>

            <input
                type="text"
                class="form-control form-control-sm"
                value="${escapeHtml(detail.warehouse_name || '')}"
                readonly
            >

        </td>


        <td>

            <input
                type="text"
                class="form-control form-control-sm"
                value="${escapeHtml(detail.unit_name || '')}"
                readonly
            >

        </td>


        <td>

            <input
                type="number"
                class="form-control form-control-sm"
                value="${escapeHtml(detail.quantity || 0)}"
                readonly
            >

        </td>


        <td>

            <input
                type="number"
                class="form-control form-control-sm"
                value="${escapeHtml(detail.unit_cost || 0)}"
                readonly
            >

        </td>


        <td>

            <input
                type="number"
                class="form-control form-control-sm"
                value="${escapeHtml(detail.sale_price || 0)}"
                readonly
            >

        </td>


        <td>

            <input
                type="text"
                class="form-control form-control-sm"
                value="${escapeHtml(detail.total || 0)}"
                readonly
            >

        </td>

    `;


    tbody.appendChild(row);

}


/* ============================================================
   حفظ الحركة
   ============================================================ */

function saveMovement() {

    /*
     * لا يمكن الحفظ إلا في add.
     */

    if (movementMode !== 'add') {
        return;
    }


    /*
     * التحقق من نوع الحركة
     */

    if (!currentMovementType) {

        alert(
            'لم يتم تحديد نوع الحركة.'
        );

        return;

    }


    /*
     * التحقق من البيانات
     */

    if (!validateMovement()) {
        return;
    }


    /*
     * جمع البيانات
     */

    const movementData =
        collectMovementData();


    /*
     * حالياً نحاكي عملية الحفظ.
     *
     * لاحقاً سيتم إرسال البيانات إلى Laravel.
     */

    console.log(
        'Movement data:',
        movementData
    );


    alert(
        'تم حفظ حركة المخزون بنجاح.'
    );


    /*
     * بعد الحفظ:
     *
     * add → view
     *
     * ولا يوجد edit.
     */

    currentMovement =
        movementData;


    setMovementMode('view');


    /*
     * إخفاء أزرار الحفظ
     */

    const actions =
        document.getElementById(
            'movementSaveActions'
        );


    if (actions) {
        actions.classList.add(
            'd-none'
        );
    }

}


/* ============================================================
   جمع بيانات الحركة
   ============================================================ */

function collectMovementData() {

    const details = [];


    const rows =
        document.querySelectorAll(
            '#movementDetails .movement-detail-row'
        );


    rows.forEach(
        function (row) {

            details.push({

                item_id:
                    row.querySelector(
                        '.movement-item-id'
                    )?.value || null,

                item_name:
                    row.querySelector(
                        '.movement-item'
                    )?.value || '',

                type_id:
                    row.querySelector(
                        '.movement-type-id'
                    )?.value || null,

                type_name:
                    row.querySelector(
                        '.movement-type'
                    )?.value || '',

                code:
                    row.querySelector(
                        '.movement-code'
                    )?.value || '',

                warehouse_id:
                    row.querySelector(
                        '.movement-warehouse-id'
                    )?.value || null,

                warehouse_name:
                    row.querySelector(
                        '.movement-warehouse'
                    )?.value || '',

                unit_id:
                    row.querySelector(
                        '.movement-unit-id'
                    )?.value || null,

                unit_name:
                    row.querySelector(
                        '.movement-unit'
                    )?.value || '',

                quantity:
                    parseFloat(
                        row.querySelector(
                            '.movement-quantity'
                        )?.value
                    ) || 0,

                unit_cost:
                    parseFloat(
                        row.querySelector(
                            '.movement-unit-cost'
                        )?.value
                    ) || 0,

                sale_price:
                    parseFloat(
                        row.querySelector(
                            '.movement-sale-price'
                        )?.value
                    ) || 0,

                total:
                    parseFloat(
                        row.querySelector(
                            '.movement-total'
                        )?.value
                    ) || 0

            });

        }
    );


    return {

        display_id:
            getValue(
                'movementDisplayId'
            ),

        type:
            getValue(
                'movementType'
            ),

        direction:
            getValue(
                'movementDirection'
            ),

        date:
            getValue(
                'movementDate'
            ),

        document_number:
            getValue(
                'movementDocumentNumber'
            ),

        statement:
            getValue(
                'movementStatement'
            ),

        warehouse_id:
            getValue(
                'movementWarehouseId'
            ),

        warehouse_name:
            getValue(
                'movementWarehouse'
            ),

        total:
            parseFloat(
                getValue(
                    'movementTotal'
                )
            ) || 0,

        details:
            details

    };

}


/* ============================================================
   التحقق من الحركة قبل الحفظ
   ============================================================ */

function validateMovement() {

    const date =
        getValue(
            'movementDate'
        );


    if (!date) {

        alert(
            'يرجى تحديد تاريخ الحركة.'
        );

        return false;

    }


    const rows =
        document.querySelectorAll(
            '#movementDetails .movement-detail-row'
        );


    if (rows.length === 0) {

        alert(
            'يجب إضافة صنف واحد على الأقل.'
        );

        return false;

    }


    let valid =
        true;


    rows.forEach(
        function (row) {

            const item =
                row.querySelector(
                    '.movement-item-id'
                )?.value;


            const type =
                row.querySelector(
                    '.movement-type-id'
                )?.value;


            const warehouse =
                row.querySelector(
                    '.movement-warehouse-id'
                )?.value;


            const unit =
                row.querySelector(
                    '.movement-unit-id'
                )?.value;


            const quantity =
                parseFloat(
                    row.querySelector(
                        '.movement-quantity'
                    )?.value
                ) || 0;


            const unitCost =
                parseFloat(
                    row.querySelector(
                        '.movement-unit-cost'
                    )?.value
                ) || 0;


            if (!item) {

                alert(
                    'يجب اختيار الصنف في جميع الصفوف.'
                );

                valid = false;

                return;

            }


            if (!type) {

                alert(
                    'يجب اختيار النوع في جميع الصفوف.'
                );

                valid = false;

                return;

            }


            if (!warehouse) {

                alert(
                    'يجب اختيار المخزن في جميع الصفوف.'
                );

                valid = false;

                return;

            }


            if (!unit) {

                alert(
                    'يجب اختيار الوحدة في جميع الصفوف.'
                );

                valid = false;

                return;

            }


            if (quantity <= 0) {

                alert(
                    'الكمية يجب أن تكون أكبر من صفر.'
                );

                valid = false;

                return;

            }


            if (unitCost < 0) {

                alert(
                    'سعر تكلفة الوحدة غير صحيح.'
                );

                valid = false;

                return;

            }

        }
    );


    return valid;

}


/* ============================================================
   إلغاء إضافة الحركة
   ============================================================ */

function cancelMovement() {

    if (movementMode !== 'add') {
        return;
    }


    const confirmed =
        confirm(
            'هل تريد إلغاء الحركة الحالية؟'
        );


    if (!confirmed) {
        return;
    }


    clearMovementForm();

}


/* ============================================================
   طباعة الحركة
   ============================================================ */

function printMovement() {

    /*
     * الطباعة تعمل على الحركة المعروضة.
     */

    if (movementMode !== 'view') {

        alert(
            'يجب حفظ الحركة أولاً قبل طباعتها.'
        );

        return;

    }


    window.print();

}


/* ============================================================
   تحديد تاريخ اليوم
   ============================================================ */

function setTodayDate() {

    const input =
        document.getElementById(
            'movementDate'
        );


    if (!input) {
        return;
    }


    const today =
        new Date();


    const year =
        today.getFullYear();


    const month =
        String(
            today.getMonth() + 1
        ).padStart(
            2,
            '0'
        );


    const day =
        String(
            today.getDate()
        ).padStart(
            2,
            '0'
        );


    input.value =
        `${year}-${month}-${day}`;

}


/* ============================================================
   إنشاء رقم حركة مؤقت
   ============================================================ */

function generateTemporaryMovementNumber() {

    const timestamp =
        Date.now();


    return `MOV-${String(timestamp).slice(-6)}`;

}


/* ============================================================
   الحصول على قيمة عنصر
   ============================================================ */

function getValue(id) {

    const element =
        document.getElementById(id);


    if (!element) {
        return '';
    }


    return element.value || '';

}


/* ============================================================
   وضع قيمة في عنصر
   ============================================================ */

function setValue(
    id,
    value
) {

    const element =
        document.getElementById(id);


    if (!element) {
        return;
    }


    element.value =
        value ?? '';

}


/* ============================================================
   حماية النص قبل وضعه داخل HTML
   ============================================================ */

function escapeHtml(value) {

    return String(value ?? '')
        .replace(
            /&/g,
            '&amp;'
        )
        .replace(
            /</g,
            '&lt;'
        )
        .replace(
            />/g,
            '&gt;'
        )
        .replace(
            /"/g,
            '&quot;'
        )
        .replace(
            /'/g,
            '&#039;'
        );

}
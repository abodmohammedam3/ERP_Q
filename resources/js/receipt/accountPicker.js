/**
 * ═══════════════════════════════════════════════════════════
 *  مكون اختيار الحساب الموحد
 *
 *  الأنواع:
 *  customer
 *  supplier
 *  expense
 *  bank
 *  cash
 *
 *  يدعم other كاسم قديم للمصروفات للحفاظ على التوافق
 * ═══════════════════════════════════════════════════════════
 */

window.AccountPicker = {

    currentType: 'customer',

    onSelect: null,

    bsModal: null,

    pickerUrl: '/operation/accounting/receiptVouchers/picker',

    coinsID: null,

    allowedTypes: null,


    /**
     * تغيير رابط الـ API
     */
    setUrl(url) {
        this.pickerUrl = url;
    },


    /**
     * تحويل الاسم القديم other إلى expense
     */
    normalizeType(type) {
        return type === 'other'
            ? 'expense'
            : type;
    },


    /**
     * فتح المودال
     */
    open(type, onSelect, options = {}) {

        type = this.normalizeType(type);

        this.currentType = type;
        this.onSelect = onSelect;

        this.coinsID = options.coinsID || null;

        this.allowedTypes = options.allowedTypes
            ? options.allowedTypes.map(item => this.normalizeType(item))
            : null;

        this.applyTabsVisibility();

        this.activateTab(type);

        const searchInput =
            document.getElementById('AccountPickerSearch');

        if (searchInput) {
            searchInput.value = '';
        }

        const results =
            document.getElementById('AccountPickerResults');

        if (results) {

            results.innerHTML = `
                <tr>
                    <td colspan="5"
                        class="text-center text-muted py-4">
                        جاري التحميل...
                    </td>
                </tr>
            `;
        }

        if (!this.bsModal) {

            const modalEl =
                document.getElementById('AccountPickerModal');

            if (!modalEl) {

                console.error(
                    '❌ AccountPickerModal غير موجود في الصفحة'
                );

                return;
            }

            this.bsModal =
                new bootstrap.Modal(modalEl);
        }

        this.bsModal.show();

        setTimeout(() => {

            document
                .getElementById('AccountPickerSearch')
                ?.focus();

        }, 300);

        this.search();
    },


    /**
     * إظهار / إخفاء التبويبات
     */
    applyTabsVisibility() {

        const tabs =
            document.querySelectorAll(
                '#AccountPickerTabs .nav-link'
            );

        tabs.forEach(tab => {

            const type =
                this.normalizeType(tab.dataset.type);

            if (!this.allowedTypes) {

                tab.parentElement.style.display = '';

                return;
            }

            tab.parentElement.style.display =
                this.allowedTypes.includes(type)
                    ? ''
                    : 'none';
        });
    },


    /**
     * تفعيل التبويب
     */
    activateTab(type) {

        type = this.normalizeType(type);

        const tabs =
            document.querySelectorAll(
                '#AccountPickerTabs .nav-link'
            );

        tabs.forEach(tab => {

            const tabType =
                this.normalizeType(tab.dataset.type);

            tab.classList.toggle(
                'active',
                tabType === type
            );
        });

        this.currentType = type;

        this.updateColumnHeaders(type);
    },


    /**
     * تغيير عناوين الأعمدة
     */
    updateColumnHeaders(type) {

        type = this.normalizeType(type);

        const parentCol =
            document.getElementById(
                'AccountPickerColParent'
            );

        const extraCol =
            document.getElementById(
                'AccountPickerColExtra'
            );

        const balanceCol =
            document.getElementById(
                'AccountPickerColBalance'
            );


        /*
         * الحساب الأب يظهر للمصروفات فقط
         */
        if (parentCol) {

            parentCol.classList.toggle(
                'd-none',
                type !== 'expense'
            );
        }


        /*
         * عمود المعلومة الإضافية
         */
        if (extraCol) {

            if (type === 'customer') {

                extraCol.textContent =
                    'رقم الهاتف';

                extraCol.style.display = '';

            } else if (type === 'supplier') {

                extraCol.textContent =
                    'رقم الهاتف';

                extraCol.style.display = '';

            } else if (
                type === 'bank' ||
                type === 'cash'
            ) {

                extraCol.textContent =
                    'العملة';

                extraCol.style.display = '';

            } else if (type === 'expense') {

                /*
                 * لا نحتاج معلومة إضافية للمصروفات
                 */
                extraCol.style.display = 'none';

            } else {

                extraCol.textContent =
                    'معلومة إضافية';

                extraCol.style.display = '';
            }
        }


        /*
         * الرصيد يظهر:
         * العملاء
         * الموردين
         * المصروفات
         */
        if (balanceCol) {

            const showBalance = [
                'customer',
                'supplier',
                'expense'
            ].includes(type);

            balanceCol.style.display =
                showBalance ? '' : 'none';
        }
    },


    /**
     * البحث
     */
    async search() {

        const searchInput =
            document.getElementById(
                'AccountPickerSearch'
            );

        const tbody =
            document.getElementById(
                'AccountPickerResults'
            );

        if (!tbody) {
            return;
        }

        const search =
            searchInput?.value.trim() || '';

        const type =
            this.normalizeType(this.currentType);


        tbody.innerHTML = `
            <tr>
                <td colspan="5"
                    class="text-center text-muted py-4">
                    جاري البحث...
                </td>
            </tr>
        `;


        try {

            const params =
                new URLSearchParams({
                    type: type,
                    search: search,
                });


            if (this.coinsID) {

                params.set(
                    'coinsID',
                    this.coinsID
                );
            }


            const response = await fetch(
                `${this.pickerUrl}?${params.toString()}`,
                {
                    headers: {
                        'Accept':
                            'application/json'
                    }
                }
            );


            const data =
                await response.json();

            tbody.innerHTML = '';


            if (
                !data.success ||
                !data.rows ||
                data.rows.length === 0
            ) {

                tbody.innerHTML = `
                    <tr>
                        <td colspan="5"
                            class="text-center text-muted py-4">
                            لا توجد نتائج
                        </td>
                    </tr>
                `;

                return;
            }


            const showBalance = [
                'customer',
                'supplier',
                'expense'
            ].includes(type);


            const showParent =
                type === 'expense';


            const showExtra =
                type !== 'expense';


            data.rows.forEach(row => {

                const tr =
                    document.createElement('tr');

                tr.style.cursor = 'pointer';


                const balanceText =
                    (
                        row.balance !== null &&
                        row.balance !== undefined
                    )
                        ? this.formatMoney(row.balance)
                        : '—';


                const parentHtml =
                    showParent
                        ? `
                            <td class="text-center">
                                <div>
                                    ${this.escapeHtml(
                                        row.parent_name || '—'
                                    )}
                                </div>

                                ${
                                    row.parent_code
                                        ? `
                                            <small class="text-muted">
                                                ${this.escapeHtml(
                                                    row.parent_code
                                                )}
                                            </small>
                                        `
                                        : ''
                                }
                            </td>
                          `
                        : `
                            <td style="display:none;"></td>
                          `;


                const extraHtml =
                    showExtra
                        ? `
                            <td class="text-center text-muted">
                                ${this.escapeHtml(
                                    row.extra || '—'
                                )}
                            </td>
                          `
                        : `
                            <td style="display:none;"></td>
                          `;


                tr.innerHTML = `

                    <td class="text-center fw-bold">
                        ${this.escapeHtml(row.code)}
                    </td>

                    <td>
                        ${this.escapeHtml(row.name)}
                    </td>

                    ${parentHtml}

                    ${extraHtml}

                    <td class="text-center fw-bold text-primary"
                        style="${
                            showBalance
                                ? ''
                                : 'display:none'
                        }">

                        ${balanceText}

                    </td>
                `;


                tr.addEventListener(
                    'click',
                    () => this.selectRow(row)
                );


                tbody.appendChild(tr);
            });


        } catch (error) {

            console.error(
                '❌ خطأ في البحث:',
                error
            );


            tbody.innerHTML = `
                <tr>
                    <td colspan="5"
                        class="text-center text-danger py-4">
                        فشل الاتصال بالخادم
                    </td>
                </tr>
            `;
        }
    },


    /**
     * تنسيق المبلغ
     */
    formatMoney(value) {

        return Number(value || 0)
            .toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
    },


    /**
     * حماية النص قبل وضعه داخل innerHTML
     */
    escapeHtml(value) {

        const div =
            document.createElement('div');

        div.textContent =
            value ?? '';

        return div.innerHTML;
    },


    /**
     * اختيار الحساب
     */
    selectRow(row) {

        if (this.onSelect) {

            this.onSelect({

                id: row.id,

                code: row.code,

                name: row.name,

                extra: row.extra,

                balance: row.balance,

                nature:
                    row.nature !== undefined &&
                    row.nature !== null
                        ? parseInt(row.nature, 10)
                        : 0,

                parent_name:
                    row.parent_name || null,

                parent_code:
                    row.parent_code || null,

                currency_id:
                    row.currency_id || null,

                currency_code:
                    row.currency_code || null,

                exchange_rate:
                    row.exchange_rate || 1,
            });
        }


        this.bsModal.hide();
    },
};


/**
 * ═══════════════════════════════════════════════════════════
 * أحداث الصفحة
 * ═══════════════════════════════════════════════════════════
 */

document.addEventListener(
    'DOMContentLoaded',
    function () {

        /**
         * زر التفريغ
         */
        document
            .getElementById(
                'AccountPickerClearBtn'
            )
            ?.addEventListener(
                'click',
                function () {

                    const input =
                        document.getElementById(
                            'AccountPickerSearch'
                        );

                    if (input) {
                        input.value = '';
                    }

                    window.AccountPicker.search();
                }
            );


        /**
         * البحث التلقائي
         */
        let searchTimeout;


        document
            .getElementById(
                'AccountPickerSearch'
            )
            ?.addEventListener(
                'input',
                function () {

                    clearTimeout(searchTimeout);

                    searchTimeout =
                        setTimeout(
                            () =>
                                window.AccountPicker.search(),
                            300
                        );
                }
            );


        /**
         * البحث عند Enter
         */
        document
            .getElementById(
                'AccountPickerSearch'
            )
            ?.addEventListener(
                'keydown',
                function (event) {

                    if (event.key === 'Enter') {

                        event.preventDefault();

                        window.AccountPicker.search();
                    }
                }
            );


        /**
         * الضغط على التبويبات
         */
        document
            .querySelectorAll(
                '#AccountPickerTabs .nav-link'
            )
            .forEach(tab => {

                tab.addEventListener(
                    'click',
                    function () {

                        window.AccountPicker
                            .activateTab(
                                this.dataset.type
                            );

                        window.AccountPicker.search();
                    }
                );
            });
    }
);
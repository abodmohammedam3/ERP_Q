/**
 * Currency Module - إدارة العملات (بحث، اختيار، مودال)
 */

window.Currency = {
    targetFieldId: 'CurrencyName',
    targetHiddenId: 'CoinsID',
    targetRateId: 'ExchangeRate',
    onSelect: null,

    setTargets(nameFieldId, hiddenFieldId, rateFieldId) {
        this.targetFieldId = nameFieldId;
        this.targetHiddenId = hiddenFieldId;
        this.targetRateId = rateFieldId;
        return this;
    },

    setOnSelect(callback) {
        this.onSelect = callback;
        return this;
    },

    openModal() {
        const modalId = 'CurrencyModal';
        const modal = Modals.get(modalId);
        if (!modal) {
            alert('مودال العملة غير متوفر');
            return;
        }

        const currentValue = document.getElementById(this.targetFieldId)?.value || '';
        const searchInput = document.getElementById('CurrencySearchInput');
        if (searchInput) searchInput.value = currentValue;

        modal.show();

        setTimeout(() => {
            if (searchInput) searchInput.focus();
            this.search();
        }, 300);
    },

    search() {
        const searchValue = document.getElementById('CurrencySearchInput')?.value.trim() || '';
        const tbody = document.getElementById('CurrencyResults');
        if (!tbody) return;

        const currencies = [
            { id: 1, name: 'ريال يمني', symbol: 'YER', rate: 1 },
            { id: 2, name: 'ريال سعودي', symbol: 'SAR', rate: 140 },
            { id: 3, name: 'دولار أمريكي', symbol: 'USD', rate: 530 }
        ];

        const results = currencies.filter(c =>
            c.name.includes(searchValue) || c.symbol.toLowerCase().includes(searchValue.toLowerCase())
        );

        tbody.innerHTML = '';
        if (results.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-3">لا توجد نتائج</td></tr>`;
            return;
        }

        results.forEach(c => {
            tbody.innerHTML += `
                <tr>
                    <td>${c.id}</td>
                    <td>${c.name}</td>
                    <td>${c.symbol}</td>
                    <td>${c.rate}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-success"
                                onclick="Currency.select(${c.id}, '${c.name}', ${c.rate})">
                            اختيار
                        </button>
                    </td>
                </tr>
            `;
        });
    },

    select(id, name, rate) {
        document.getElementById(this.targetHiddenId).value = id;
        document.getElementById(this.targetFieldId).value = name;
        document.getElementById(this.targetRateId).value = rate;

        Modals.hide('CurrencyModal');

        if (this.onSelect) this.onSelect(id, name, rate);
    },

    keyDown(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            this.openModal();
        }
    },

    blur() {
        // يمكن إضافة منطق إضافي
    }
};
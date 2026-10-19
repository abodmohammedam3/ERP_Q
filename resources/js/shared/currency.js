/* =========================================================================
   ⚠️ DEPRECATED — متوافق فقط (Compatibility Shims)
   النظام الموحّد الوحيد لاختيار العملة هو shared/lookup.js:
       openLookup('currency', targetField)
   أو أي حقل يحمل data-lookup="currency".
   ملفات العملة القديمة (currency.js + operation/model/currency.blade.php)
   لم تعد تُستخدم في أي شاشة؛ وتم تحويل كل الدوال أدناه إلى lookup.js
   لتجنّب عرض قائمة عملات ثابتة مكتوبة يدويًا (140/530) لا تقرأ من DB.
   ========================================================================= */

/**
 * Currency Module — شيم توافقي (DEPRECATED)
 * كل البحث/الاختيار يتم من shared/lookup.js (بيانات من قاعدة البيانات).
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

    /**
     * فتح نافذة العملة الموحّدة (بيانات من قاعدة البيانات)
     * تستخدم الحقل الهدف المسجّل عبر setTargets().
     */
    openModal() {
        const target = document.getElementById(this.targetFieldId);

        if (!target || typeof window.openLookup !== 'function') {
            alert('نظام اختيار العملة الموحّد (lookup) غير متوفر');
            return;
        }

        target.dataset.lookup = 'currency';
        if (this.targetHiddenId) target.dataset.lookupTarget = this.targetHiddenId;

        return window.openLookup('currency', target);
    },

    /**
     * البحث يتم داخل النافذة الموحّدة — لا قائمة ثابتة هنا.
     */
    search() {
        if (typeof window.lookupSearchInput === 'function') {
            window.lookupSearchInput();
        }
    },

    /**
     * اختيار يدوي (نادر) — يبقى متاحًا للتوافق فقط.
     * الاختيار الطبيعي يتم عبر lookup.js → LookupConfigs.currency.onSelect.
     */
    select(id, name, rate) {
        const hiddenEl = document.getElementById(this.targetHiddenId);
        const fieldEl = document.getElementById(this.targetFieldId);
        const rateEl = document.getElementById(this.targetRateId);

        if (hiddenEl) hiddenEl.value = id ?? '';
        if (fieldEl) fieldEl.value = name ?? '';
        if (rateEl) rateEl.value = rate ?? 1;

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
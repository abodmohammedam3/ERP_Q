/**
 * Utilities - دوال مساعدة (تحويل الأرقام، حساب الإجماليات، إلخ)
 */

window.Utils = {
    /**
     * تحويل الرقم إلى كلمات مع إمكانية إضافة اسم العملة
     * @param {number} number - الرقم المراد تحويله
     * @param {string|null} currency - اسم العملة (اختياري)
     * @returns {string} النص المحول
     */
    numberToWords(number, currency = null) {
        if (number === 0) {
            return 'صفر' + (currency ? ' ' + currency + ' فقط لا غير' : '');
        }
        if (number < 0) {
            return 'سالب ' + this.numberToWords(-number, currency);
        }

        // دالة تحويل الأعداد إلى كلمات (مأخوذة من كود tafqeet)
        const ones = ["", "واحد", "اثنان", "ثلاثة", "أربعة", "خمسة", "ستة", "سبعة", "ثمانية", "تسعة"];
        const teens = ["عشرة", "أحد عشر", "اثنا عشر", "ثلاثة عشر", "أربعة عشر", "خمسة عشر", "ستة عشر", "سبعة عشر", "ثمانية عشر", "تسعة عشر"];
        const tens = ["", "", "عشرون", "ثلاثون", "أربعون", "خمسون", "ستون", "سبعون", "ثمانون", "تسعون"];
        const hundreds = ["", "مائة", "مائتان", "ثلاثمائة", "أربعمائة", "خمسمائة", "ستمائة", "سبعمائة", "ثمانمائة", "تسعمائة"];
        const thousands = ["", "ألف", "ألفان", "آلاف"];
        const millions = ["", "مليون", "مليونان", "ملايين"];
        const billions = ["", "مليار", "ملياران", "مليارات"];

        function convertLessThan1000(n) {
            let str = "";
            if (n >= 100) {
                str += hundreds[Math.floor(n / 100)];
                n %= 100;
                if (n > 0) str += " و ";
            }
            if (n >= 20) {
                const one = n % 10;
                const ten = Math.floor(n / 10);
                if (one > 0) str += ones[one] + " و " + tens[ten];
                else str += tens[ten];
            } else if (n >= 10) {
                str += teens[n - 10];
            } else if (n > 0) {
                str += ones[n];
            }
            return str;
        }

        function convertGroup(n, type) {
            if (n === 0) return "";
            if (n === 1) return type[1];
            if (n === 2) return type[2];
            if (n >= 3 && n <= 10) return convertLessThan1000(n) + " " + type[3];
            return convertLessThan1000(n) + " " + type[1];
        }

        // تقسيم الرقم إلى مجموعات ثلاثية
        let num = Math.floor(number);
        let billionsPart = Math.floor(num / 1000000000);
        let millionsPart = Math.floor((num % 1000000000) / 1000000);
        let thousandsPart = Math.floor((num % 1000000) / 1000);
        let hundredsPart = num % 1000;

        let result = "";
        if (billionsPart > 0) result += convertGroup(billionsPart, billions) + " ";
        if (millionsPart > 0) result += convertGroup(millionsPart, millions) + " ";
        if (thousandsPart > 0) result += convertGroup(thousandsPart, thousands) + " ";
        if (hundredsPart > 0) result += convertLessThan1000(hundredsPart);

        result = result.trim().replace(/ و /g, " و ");

        // إضافة العملة وعبارة "فقط لا غير" إذا وُجدت العملة
        if (currency) {
            result += ' ' + currency + ' فقط لا غير';
        }

        return result;
    },

    /**
     * دالة مساعدة لتحويل المبلغ مع العملة (توافق مع الاستدعاءات القديمة)
     */
    numberToCurrencyWords(number, currencyName) {
        return this.numberToWords(number, currencyName);
    },

    // ── تنسيق الأرقام الموحّد (متوافق مع formatMoney في القيود اليومية) ──

    /**
     * تنسيق مبلغ/سعر بفواصل الآلاف ومنزلتين
     * مثال: 1234.5 → "1,234.50"
     */
    formatAmount(v) {
        const n = Number(v);
        return (isNaN(n) ? 0 : n).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    },

    /**
     * تنسيق سعر صرف بدون أصفار زائدة (حتى 6 منازل)
     * مثال: 1.000000 → "1" ، 1.250000 → "1.25" ، 1.2345678 → "1.234568"
     */
    formatRate(v) {
        const n = Number(v);
        if (isNaN(n)) return '0';
        return String(parseFloat(n.toFixed(6)));
    },

    /**
     * تحويل نص منسّق (بفواصل) إلى رقم خام — لحقول الإدخال عند الحفظ
     */
    parseAmount(v) {
        if (v === null || v === undefined) return 0;
        const n = parseFloat(String(v).replace(/,/g, ''));
        return isNaN(n) ? 0 : n;
    },

    // تنسيق الأرقام
    formatNumber(num, decimals = 2) {
        return parseFloat(num).toFixed(decimals);
    },

    // حساب إجمالي صفوف الجدول
    calculateTableTotal(tableId, rowClass, totalFieldClass) {
        let total = 0;
        document.querySelectorAll(`#${tableId} .${rowClass}`).forEach(row => {
            const input = row.querySelector(`.${totalFieldClass}`);
            if (input) {
                total += parseFloat(input.value) || 0;
            }
        });
        return total;
    },

    // عرض رسالة تأكيد — موحّد عبر appConfirm مع fallback
    confirm(message) {
        if (typeof window.appConfirm === 'function') {
            return window.appConfirm(message);
        }
        return Promise.resolve(window.confirm(message));
    },

    // عرض رسالة خطأ
    alert(message) {
        alert(message);
    }
};

/* ✅ واجهة التنسيق الموحّدة للتطبيق (تفهرس من أي ملف عبر window) */
if (typeof window !== 'undefined') {
    window.AppFormat = {
        amount: (v) => window.Utils.formatAmount(v),
        rate:   (v) => window.Utils.formatRate(v),
        parse:  (v) => window.Utils.parseAmount(v),
    };
}
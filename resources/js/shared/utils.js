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

    // عرض رسالة تأكيد
    confirm(message) {
        return confirm(message);
    },

    // عرض رسالة خطأ
    alert(message) {
        alert(message);
    }
};
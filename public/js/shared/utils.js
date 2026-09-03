/**
 * Utilities - دوال مساعدة (تحويل الأرقام، حساب الإجماليات، إلخ)
 */

window.Utils = {
    // تحويل الرقم إلى كلمات (دالة مبسطة)
    numberToWords(num) {
        if (num === 0) return 'صفر';
        const units = ['', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة'];
        const tens = ['', 'عشرة', 'عشرون', 'ثلاثون', 'أربعون', 'خمسون', 'ستون', 'سبعون', 'ثمانون', 'تسعون'];
        const hundreds = ['', 'مائة', 'مائتان', 'ثلاثمائة', 'أربعمائة', 'خمسمائة', 'ستمائة', 'سبعمائة', 'ثمانمائة', 'تسعمائة'];

        let numStr = Math.floor(num).toString();
        let result = '';
        if (numStr.length === 1) {
            result = units[parseInt(numStr)];
        } else if (numStr.length === 2) {
            let tensDigit = parseInt(numStr[0]);
            let unitDigit = parseInt(numStr[1]);
            if (unitDigit === 0) {
                result = tens[tensDigit];
            } else {
                result = units[unitDigit] + ' و' + tens[tensDigit];
            }
        } else if (numStr.length === 3) {
            let hundredsDigit = parseInt(numStr[0]);
            let rest = parseInt(numStr.slice(1));
            result = hundreds[hundredsDigit];
            if (rest > 0) {
                result += ' و' + this.numberToWords(rest);
            }
        } else {
            result = 'عدد كبير';
        }
        return result;
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
/**
 * State Manager - يدير وضع الشاشة
 * الأوضاع:
 * view = عرض سند محفوظ
 * add  = إضافة سند جديد
 * edit = تعديل سند موجود
 */

window.StateManager = class StateManager {

    constructor(pageId) {
        this.pageId = pageId;

        // الوضع الافتراضي
        this.mode = 'view';

        // الحقول التي يتحكم بها النظام
        this.fieldIds = [];

        // معرفات الأزرار
        this.buttonIds = {
            save: null,
            saveNew: null,
            cancel: null,
            edit: null,
            print: null,
            add: null,
            search: null
        };

        // Callback عند تغيير الوضع
        this._onModeChange = null;
    }


    // =====================================================
    // تحديد الحقول
    // =====================================================
    setFields(fieldIds) {
        this.fieldIds = fieldIds;
        return this;
    }


    // =====================================================
    // تحديد الأزرار
    // =====================================================
    setButtons(buttons) {
        this.buttonIds = {
            ...this.buttonIds,
            ...buttons
        };

        return this;
    }


    // =====================================================
    // دالة عند تغيير الوضع
    // =====================================================
    onModeChange(callback) {
        this._onModeChange = callback;
        return this;
    }


    // =====================================================
    // تغيير الوضع
    // =====================================================
    setMode(mode) {

        // التأكد من الوضع الصحيح
        if (!['view', 'add', 'edit'].includes(mode)) {
            console.warn('StateManager: وضع غير معروف:', mode);
            return;
        }

        this.mode = mode;

        // تطبيق الوضع
        this._applyMode();

        // تشغيل Callback
        if (this._onModeChange) {
            this._onModeChange(mode);
        }
    }


    // =====================================================
    // تطبيق الوضع على الحقول والأزرار
    // =====================================================
    _applyMode() {

        const isView = this.mode === 'view';
        const isAdd = this.mode === 'add';
        const isEdit = this.mode === 'edit';


        // =================================================
        // الحقول
        // =================================================
        this.fieldIds.forEach(id => {

            const el = document.getElementById(id);

            if (el) {

                // في العرض: الحقول معطلة
                // في الإضافة والتعديل: الحقول مفعلة
                el.disabled = isView;
            }
        });


        // =================================================
        // الحصول على الأزرار
        // =================================================
        const save = this.buttonIds.save
            ? document.getElementById(this.buttonIds.save)
            : null;

        const saveNew = this.buttonIds.saveNew
            ? document.getElementById(this.buttonIds.saveNew)
            : null;

        const cancel = this.buttonIds.cancel
            ? document.getElementById(this.buttonIds.cancel)
            : null;

        const edit = this.buttonIds.edit
            ? document.getElementById(this.buttonIds.edit)
            : null;

        const print = this.buttonIds.print
            ? document.getElementById(this.buttonIds.print)
            : null;

        const add = this.buttonIds.add
            ? document.getElementById(this.buttonIds.add)
            : null;

        const search = this.buttonIds.search
            ? document.getElementById(this.buttonIds.search)
            : null;


        // =================================================
        // زر الحفظ
        // =================================================

        if (save) {
            // الحفظ يعمل في الإضافة والتعديل فقط
            save.disabled = isView;
        }


        // =================================================
        // زر حفظ وإنشاء جديد
        // =================================================

        if (saveNew) {
            saveNew.disabled = isView;
        }


        // =================================================
        // زر الإلغاء
        // =================================================

        if (cancel) {

            if (isView) {
                cancel.classList.add('d-none');
            } else {
                cancel.classList.remove('d-none');
            }
        }


        // =================================================
        // زر التعديل
        // =================================================

        if (edit) {

            // التعديل يعمل فقط عندما يكون السند محفوظاً
            // أي في وضع view

            edit.disabled = !isView;
        }


        // =================================================
        // زر الطباعة
        // =================================================

        if (print) {

            // الطباعة تعمل عندما يكون السند محفوظاً
            // أي في وضع view

            print.disabled = !isView;
        }


        // =================================================
        // زر الإضافة
        // =================================================

        if (add) {

            // الإضافة تعمل فقط من وضع العرض
            add.disabled = !isView;
        }


        // =================================================
        // زر البحث
        // =================================================

        if (search) {

            // البحث دائماً متاح
            search.disabled = false;
        }
    }


    // =====================================================
    // التحقق من وجود بيانات
    // =====================================================
    hasData(fieldId) {

        const el = document.getElementById(fieldId);

        return el && el.value.trim() !== '';
    }
};
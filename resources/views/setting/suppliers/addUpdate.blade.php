{{-- ================================================================
     مودال إضافة / تعديل مورد
================================================================ --}}
<div class="modal fade"
     id="supplierModal"
     tabindex="-1"
     aria-labelledby="supplierModalLabel"
     aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            {{-- ================================
                 رأس المودال
            ================================= --}}
            <div class="modal-header">

                <h5 class="modal-title" id="supplierModalLabel">
                    إضافة مورد جديد
                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="إغلاق"></button>

            </div>


            {{-- ================================
                 جسم المودال
            ================================= --}}
            <div class="modal-body">

                <form id="supplierForm" novalidate>

                    @csrf

                    {{-- معرف المورد المخفي --}}
                    <input type="hidden"
                           name="suplierID"
                           id="supplierID">

                    <div class="row g-3">

                        {{-- 1 - اسم المورد --}}
                        <div class="col-md-6">
                            <label for="supName" class="form-label">
                                اسم المورد
                                <span class="text-danger">*</span>
                            </label>

                            <input type="text"
                                   class="form-control"
                                   name="supName"
                                   id="supName"
                                   autocomplete="off"
                                   required>

                            <div class="invalid-feedback">
                                يرجى إدخال اسم المورد
                            </div>
                        </div>


                        {{-- 2 - رقم الحساب التحليلي --}}
                        <div class="col-md-6">
                            <label for="supplierAccountCode" class="form-label">
                                رقم الحساب التحليلي
                            </label>

                            <input type="text"
                                   class="form-control bg-body-tertiary"
                                   id="supplierAccountCode"
                                   name="accountCode"
                                   readonly
                                   placeholder="—">

                            <small class="text-muted">
                                يتم توليده تلقائيًا بواسطة النظام
                            </small>
                        </div>


                        {{-- 3 - رقم الهاتف --}}
                        <div class="col-md-6">
                            <label for="supPhone" class="form-label">
                                رقم الهاتف
                            </label>

                            <input type="tel"
                                   class="form-control"
                                   name="supPhone"
                                   id="supPhone"
                                   autocomplete="off"
                                   inputmode="tel">
                        </div>


                        {{-- 4 - الحالة --}}
                        <div class="col-md-6">
                            <label for="supStatus" class="form-label">
                                الحالة
                            </label>

                            {{-- ✅ name="is_active" — القيم معكوسة:
                                 1 = نشط (افتراضي)
                                 0 = غير نشط --}}
                            <select class="form-select"
                                    name="is_active"
                                    id="supStatus">

                                <option value="1" selected>
                                    نشط
                                </option>

                                <option value="0">
                                    غير نشط
                                </option>

                            </select>
                        </div>


                        {{-- 5 - العنوان --}}
                        <div class="col-md-12">
                            <label for="supArea" class="form-label">
                                العنوان
                            </label>

                            <input type="text"
                                   class="form-control"
                                   name="supArea"
                                   id="supArea"
                                   autocomplete="off">
                        </div>

                    </div>

                </form>

            </div>


            {{-- ================================
                 أزرار المودال
            ================================= --}}
            <div class="modal-footer">

                <button type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">
                    إلغاء
                </button>

                <button type="submit"
                        form="supplierForm"
                        class="btn btn-primary"
                        id="saveSupplierBtn">
                    حفظ البيانات
                </button>

            </div>

        </div>

    </div>
</div>
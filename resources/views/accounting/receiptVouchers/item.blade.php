     <!-- ====== بطاقة بيانات السند الأساسية ====== -->
        <div class="card mb-3">
            <div class="card-body">
                <form id="voucherForm" novalidate>
                    <div class="row g-3">
                        <!-- الجهة / العميل -->
                        <div class="col-md-6">
                            <label class="form-label" for="customer">الجهة / العميل <span class="required-star">*</span></label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="customer" placeholder="ابحث بالاسم أو الرمز" value="شركة الأمل للتجارة" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="searchCustomer()">
                                    <i class="bi bi-search"></i> بحث
                                </button>
                            </div>
                            <div class="invalid-feedback">يرجى إدخال اسم الجهة أو العميل.</div>
                        </div>
                        <!-- المبلغ -->
                        <div class="col-md-3">
                            <label class="form-label" for="amount">المبلغ <span class="required-star">*</span></label>
                            <input type="number" class="form-control" id="amount" placeholder="0.00" value="15000.00" step="0.01" min="0" required oninput="updateAmountWords(this.value)">
                            <div class="invalid-feedback">المبلغ مطلوب وقيمته يجب أن تكون أكبر من صفر.</div>
                        </div>
                        <!-- العملة -->
                        <div class="col-md-3">
                            <label class="form-label" for="currency">العملة</label>
                            <select class="form-select" id="currency">
                                <option>ريال سعودي (SAR)</option>
                                <option>دولار أمريكي (USD)</option>
                                <option>دينار كويتي (KWD)</option>
                            </select>
                        </div>

                        <!-- تاريخ السند -->
                        <div class="col-md-3">
                            <label class="form-label" for="voucherDate">تاريخ السند <span class="required-star">*</span></label>
                            <input type="date" class="form-control" id="voucherDate" value="2026-08-19" required>
                            <div class="invalid-feedback">تاريخ السند مطلوب.</div>
                        </div>
                        <!-- رقم المرجع / الإسناد -->
                        <div class="col-md-3">
                            <label class="form-label" for="refNo">رقم المرجع (إسناد)</label>
                            <input type="text" class="form-control" id="refNo" placeholder="مثال: فاتورة رقم 102" value="فاتورة رقم 102">
                        </div>
                        <!-- طريقة الدفع -->
                        <div class="col-md-3">
                            <label class="form-label" for="paymentMethod">طريقة الدفع <span class="required-star">*</span></label>
                            <select class="form-select" id="paymentMethod" required>
                                <option value="">اختر طريقة الدفع</option>
                                <option selected>نقدي</option>
                                <option>شيك</option>
                                <option>تحويل بنكي</option>
                                <option>بطاقة ائتمان</option>
                            </select>
                            <div class="invalid-feedback">يرجى اختيار طريقة الدفع.</div>
                        </div>
                        <!-- الحساب الدائن (الحساب المقابل) -->
                        <div class="col-md-3">
                            <label class="form-label" for="creditAccount">الحساب الدائن (المقابل)</label>
                            <select class="form-select" id="creditAccount">
                                <option>1000 - الصندوق</option>
                                <option selected>2000 - البنك الأهلي</option>
                                <option>3000 - البنك الرياض</option>
                            </select>
                        </div>

                        <!-- ملاحظات -->
                        <div class="col-12">
                            <label class="form-label" for="notes">البيان / الملاحظات</label>
                            <input type="text" class="form-control" id="notes" placeholder="سبب القبض أو وصف العملية" value="قبض قيمة الدفعة الأولى من الفاتورة رقم 102">
                        </div>
                    </div>
                </form>
            </div>
        </div>

<!-- بطاقة البحث والتصفية -->
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="searchCode" class="form-label">رقم الحساب</label>
                    <input type="text" class="form-control" id="searchCode" placeholder="بحث بالرقم">
                </div>
                <div class="col-md-3">
                    <label for="searchName" class="form-label">اسم الحساب</label>
                    <input type="text" class="form-control" id="searchName" placeholder="بحث بالاسم">
                </div>
                <div class="col-md-3">
                    <label for="searchType" class="form-label">نوع الحساب</label>
                    <select class="form-select" id="searchType">
                        <option value="">الكل</option>
                        <option value="assets">أصول</option>
                        <option value="liabilities">خصوم</option>
                        <option value="equity">حقوق ملكية</option>
                        <option value="revenue">إيرادات</option>
                        <option value="expense">مصروفات</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-secondary w-100">بحث</button>
                </div>
            </div>
        </div>
    </div>

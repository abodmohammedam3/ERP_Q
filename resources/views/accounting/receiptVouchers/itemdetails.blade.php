  <!-- ====== بطاقة تفاصيل السند (الفواتير المرتبطة) ====== -->
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list-ul me-1"></i> <strong>تفاصيل القبض (الفواتير المرتبطة)</strong></span>
                <button class="btn btn-sm btn-primary" onclick="addInvoiceRow()">
                    <i class="bi bi-plus-circle"></i> إضافة فاتورة
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0" id="invoiceTable">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>رقم الفاتورة</th>
                                <th>تاريخ الفاتورة</th>
                                <th>قيمة الفاتورة</th>
                                <th>المبلغ المدفوع</th>
                                <th>المتبقي</th>
                                <th>المسدد الآن</th>
                                <th>إجراء</th>
                            </tr>
                        </thead>
                        <tbody id="invoiceBody">
                            <!-- الصفوف تدرج ديناميكياً، لكن نضع نموذجاً -->
                            <tr data-id="1">
                                <td>1</td>
                                <td><input type="text" class="form-control form-control-sm" value="INV-2026-100" placeholder="رقم الفاتورة"></td>
                                <td><input type="date" class="form-control form-control-sm" value="2026-08-10"></td>
                                <td><input type="number" class="form-control form-control-sm invoice-total" value="25000.00" step="0.01" onchange="updateInvoiceRow(this)"></td>
                                <td><input type="number" class="form-control form-control-sm invoice-paid" value="0.00" step="0.01" readonly></td>
                                <td class="invoice-balance">25,000.00</td>
                                <td><input type="number" class="form-control form-control-sm invoice-now" value="15000.00" step="0.01" min="0" oninput="updateTotals()"></td>
                                <td><button class="btn btn-sm btn-outline-danger" onclick="removeRow(this)"><i class="bi bi-trash3"></i></button></td>
                            </tr>
                            <tr data-id="2">
                                <td>2</td>
                                <td><input type="text" class="form-control form-control-sm" value="INV-2026-105" placeholder="رقم الفاتورة"></td>
                                <td><input type="date" class="form-control form-control-sm" value="2026-08-15"></td>
                                <td><input type="number" class="form-control form-control-sm invoice-total" value="10000.00" step="0.01" onchange="updateInvoiceRow(this)"></td>
                                <td><input type="number" class="form-control form-control-sm invoice-paid" value="10000.00" step="0.01" readonly></td>
                                <td class="invoice-balance">0.00</td>
                                <td><input type="number" class="form-control form-control-sm invoice-now" value="0.00" step="0.01" min="0" disabled oninput="updateTotals()"></td>
                                <td><button class="btn btn-sm btn-outline-danger" onclick="removeRow(this)"><i class="bi bi-trash3"></i></button></td>
                            </tr>
                        </tbody>
                        <tfoot class="table-secondary">
                            <tr>
                                <th colspan="5" class="text-start">إجمالي المبلغ المسدد في هذا السند</th>
                                <th colspan="3" class="text-start" id="totalPaidDisplay">15,000.00</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- ====== منطقة ملخص السند وموافقات إضافية ====== -->
        <div class="row g-3">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label" for="amountWords">المبلغ كتابة</label>
                                <input type="text" class="form-control amount-words" id="amountWords" value="خمسة عشر ألف ريال سعودي فقط" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="voucherStatus">حالة السند</label>
                                <select class="form-select" id="voucherStatus">
                                    <option selected>قيد المراجعة</option>
                                    <option>معتمد</option>
                                    <option>ملغي</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 summary-card">
                    <div class="card-body d-flex flex-column justify-content-center">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">إجمالي المبلغ:</span>
                            <strong class="total-amount" id="summaryTotal">15,000.00</strong>
                        </div>
                        <div class="d-flex justify-content-between border-top pt-2 mt-2">
                            <span class="text-muted">المدفوع نقداً / بنكاً:</span>
                            <span class="text-success fw-bold" id="summaryPaid">15,000.00</span>
                        </div>
                        <div class="d-flex justify-content-between border-top pt-2 mt-2">
                            <span class="text-muted">المتبقي:</span>
                            <span class="text-danger fw-bold" id="summaryRemain">0.00</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

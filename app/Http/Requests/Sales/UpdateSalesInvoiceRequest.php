<?php

namespace App\Http\Requests\Sales;

/**
 * التحقق من بيانات تعديل فاتورة البيع.
 *
 * نفس قواعد الإنشاء — يرث كل شيء من StoreSalesInvoiceRequest.
 */
class UpdateSalesInvoiceRequest extends StoreSalesInvoiceRequest
{
    // يرث كل القواعد والرسائل — لا يحتاج تعديل.
}
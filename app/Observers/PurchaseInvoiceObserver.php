<?php

namespace App\Observers;

use App\Models\Inventory\InventoryMovement;
use App\Models\Purchases\PurchaseInvoice;
use App\Services\JournalEntryService;

class PurchaseInvoiceObserver
{
    public function deleted(PurchaseInvoice $invoice): void
    {
        if (!empty($invoice->entryID)) {
            JournalEntryService::delete((int) $invoice->entryID);
        } elseif (!empty($invoice->invoice_number)) {
            JournalEntryService::deleteByDocNumber(
                (string) $invoice->invoice_number
            );
        }

        /* ✅ توحيد السلوك مع SalesInvoiceObserver: حذف مجمّع — منطق موحد في
           InventoryService (عكس hook + حذف تفاصيل/رؤوس + إبطال كاش) */
        app(\App\Services\Inventory\InventoryService::class)->deleteMovementsBySource(
            InventoryMovement::SOURCE_PURCHASE_INVOICE,
            (int) $invoice->purchase_invoice_id
        );
    }
}
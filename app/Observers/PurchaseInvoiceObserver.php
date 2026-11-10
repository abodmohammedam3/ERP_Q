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

        $movements = InventoryMovement::where(
                'source_type',
                InventoryMovement::SOURCE_PURCHASE_INVOICE
            )
            ->where('source_id', $invoice->purchase_invoice_id)
            ->with('details')
            ->get();

        foreach ($movements as $movement) {
            // ✅ توحيد السلوك مع SalesInvoiceObserver: عكس الرصيد أولًا
            app(\App\Services\Inventory\InventoryService::class)
                ->reverseMovement($movement);

            $movement->details()->delete();
            $movement->delete();
        }
    }
}
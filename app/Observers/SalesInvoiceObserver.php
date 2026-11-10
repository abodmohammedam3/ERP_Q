<?php

namespace App\Observers;

use App\Models\Sales\SalesInvoice;
use App\Models\Inventory\InventoryMovement;
use App\Services\Inventory\InventoryService;

class SalesInvoiceObserver
{
    /**
     * @var InventoryService
     */
    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Safety Net — عند حذف الفاتورة من أي مكان لا يمر بالـ Service
     */
    public function deleted(SalesInvoice $invoice): void
    {
        // ✅ توحيد السلوك مع PurchaseInvoiceObserver: تنظيف القيد المحاسبي (شبكة أمان)
        if (!empty($invoice->entryID)) {
            \App\Services\JournalEntryService::delete((int) $invoice->entryID);
        } elseif (!empty($invoice->invoice_number)) {
            \App\Services\JournalEntryService::deleteByDocNumber(
                (string) $invoice->invoice_number
            );
        }

        $movements = InventoryMovement::where(
                'source_type',
                InventoryMovement::SOURCE_SALES_INVOICE
            )
            ->where('source_id', $invoice->sales_invoice_id)
            ->with('details')
            ->get();

        foreach ($movements as $movement) {
            // عكس الرصيد
            $this->inventoryService->reverseMovement($movement);

            // حذف التفاصيل والرأس
            $movement->details()->delete();
            $movement->delete();
        }
    }
}
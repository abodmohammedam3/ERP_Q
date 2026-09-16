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
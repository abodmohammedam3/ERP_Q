<?php

namespace App\Observers;

use App\Models\Purchases\PurchaseInvoice;
use App\Models\Inventory\InventoryMovement;

class PurchaseInvoiceObserver
{
    /**
     * Safety Net لحذف حركة المخزون المرتبطة.
     *
     * الحذف الطبيعي يمر عبر PurchaseInvoiceService::delete()
     * الذي يحذف الحركة أولًا.
     *
     * هذا المراقب يعمل فقط إذا حُذفت الفاتورة من مكان آخر
     * (Tinker, Seeder, Command, Import...) لضمان عدم بقاء
     * حركة مخزون يتيمة.
     */
    public function deleted(PurchaseInvoice $invoice): void
    {
        $movements = InventoryMovement::where(
                'source_type',
                InventoryMovement::SOURCE_PURCHASE_INVOICE
            )
            ->where('source_id', $invoice->purchase_invoice_id)
            ->get();

        foreach ($movements as $movement) {
            app(\App\Services\Inventory\InventoryService::class)
                ->reverseMovement($movement);
            $movement->details()->delete();
            $movement->delete();
        }
    }
}
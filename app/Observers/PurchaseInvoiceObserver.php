<?php

namespace App\Observers;

use App\Models\Purchases\PurchaseInvoice;
use App\Models\Inventory\InventoryMovement;
use App\Services\JournalEntryService;

class PurchaseInvoiceObserver
{
    /**
     * Safety Net لحذف حركة المخزون والقيد المحاسبي المرتبطين.
     *
     * الحذف الطبيعي يمر عبر PurchaseInvoiceService::delete()
     * الذي يحذف الحركة والقيد أولًا.
     *
     * هذا المراقب يعمل فقط إذا حُذفت الفاتورة من مكان آخر
     * (Tinker, Seeder, Command, Import...) لضمان عدم بقاء
     * حركة مخزون أو قيد محاسبي يتيم.
     */
    public function deleted(PurchaseInvoice $invoice): void
    {
        // ============================================
        // 1) حذف القيد المحاسبي المرتبط
        // ============================================
        if (!empty($invoice->entryID)) {
            JournalEntryService::delete((int) $invoice->entryID);
        } elseif (!empty($invoice->invoice_number)) {
            // خطة بديلة للفواتير القديمة أو التي فُقد فيها entryID
            JournalEntryService::deleteByDocNumber(
                (string) $invoice->invoice_number
            );
        }

        // ============================================
        // 2) حذف حركة المخزون المرتبطة
        // ============================================
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
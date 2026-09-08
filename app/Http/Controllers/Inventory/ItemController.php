<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Item;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ItemController extends Controller
{
    /**
     * عرض قائمة الأصناف (النشطة فقط أو الكل)
     */
    public function index()
    {
        // يمكن عرض الكل، أو عرض النشطة فقط
        $items = Item::orderBy('itemID', 'desc')->get();
        return view('setting.inventory.items.index', compact('items'));
    }

    /**
     * إضافة صنف جديد
     */
    public function store(Request $request)
    {
        $request->validate([
            'itemName2' => 'required|string|max:255|unique:Items,itemName2',
        ]);

        $item = Item::create([
            'itemName2' => $request->itemName2,
            'is_active' => 1, // افتراضي نشط
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة الصنف بنجاح',
            'data' => $item,
        ]);
    }

    /**
     * تحديث صنف موجود
     */
    public function update(Request $request, $id)
    {
        $item = Item::findOrFail($id);

        $request->validate([
            'itemName2' => [
                'required',
                'string',
                'max:255',
                Rule::unique('Items', 'itemName2')->ignore($item->itemID, 'itemID'),
            ],
        ]);

        $item->update([
            'itemName2' => $request->itemName2,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الصنف بنجاح',
            'data' => $item,
        ]);
    }

    /**
     * حذف صنف (فعلي أو تعطيل)
     * نفضل التعطيل بدلاً من الحذف الفعلي.
     * إذا أردت الحذف الفعلي، استخدم delete() بعد التحقق من عدم استخدامه.
     */
    public function destroy($id)
    {
        $item = Item::findOrFail($id);

        // هنا يمكنك إضافة فحص إذا كان الصنف مستخدماً في جداول أخرى
        // مثلاً: if ($item->purchaseDetails()->exists()) { return response()->json(...) }

        // نستخدم التعطيل بدلاً من الحذف
        $item->update(['is_active' => 0]);

        return response()->json([
            'success' => true,
            'message' => 'تم تعطيل الصنف بنجاح',
        ]);
    }

    /**
     * تبديل حالة التفعيل (تمكين / تعطيل)
     */
    public function toggleStatus($id)
    {
        $item = Item::findOrFail($id);
        $newStatus = !$item->is_active;
        $item->update(['is_active' => $newStatus]);

        $statusText = $newStatus ? 'تم التفعيل' : 'تم التعطيل';
        return response()->json([
            'success' => true,
            'message' => $statusText . ' بنجاح',
            'data' => $item,
        ]);
    }

    /**
     * البحث عن الأصناف (لـ Item Selector)
     */
    public function search(Request $request)
    {
        $search = $request->input('search', '');
        $items = Item::where('itemName2', 'LIKE', "%{$search}%")
            ->orderBy('itemName2')
            ->get(['itemID', 'itemName2']); // نرجع الحقول المطلوبة فقط

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }
}
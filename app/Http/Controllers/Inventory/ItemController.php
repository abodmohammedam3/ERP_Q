<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Item;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator; // تأكد من استيراد هذه

class ItemController extends Controller
{
    public function index()
    {
        $items = Item::orderBy('itemID', 'asc')->get();
        return view('setting.inventory.items.index', compact('items'));
    }

    public function list(Request $request)
    {
        $search = $request->input('search');
        $query = Item::query();

        if ($search) {
            $query->where('itemName2', 'LIKE', "%{$search}%");
        }

        $items = $query->orderBy('itemID', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function store(Request $request)
    {
        // استخدام Validator يدوياً
        $validator = Validator::make($request->all(), [
            'itemName2' => 'required|string|max:255|unique:Items,itemName2'
        ], [
            'itemName2.unique' => 'هذا الصنف موجود بالفعل',
            'itemName2.required' => 'اسم الصنف مطلوب',
        ]);

        // إذا فشل التحقق، نعيد JSON مع الأخطاء
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first('itemName2'),
                'errors' => $validator->errors()
            ], 422);
        }

        $item = Item::create([
            'itemName2' => $request->itemName2,
            'is_active' => 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة الصنف بنجاح',
            'data' => $item,
        ]);
    }

    public function update(Request $request, $id)
    {
        $item = Item::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'itemName2' => [
                'required',
                'string',
                'max:255',
                Rule::unique('Items', 'itemName2')->ignore($item->itemID, 'itemID'),
            ],
        ], [
            'itemName2.unique' => 'هذا الصنف موجود بالفعل',
            'itemName2.required' => 'اسم الصنف مطلوب',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first('itemName2'),
                'errors' => $validator->errors()
            ], 422);
        }

        $item->update([
            'itemName2' => $request->itemName2,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الصنف بنجاح',
            'data' => $item,
        ]);
    }

    public function destroy($id)
    {
        $item = Item::findOrFail($id);
        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الصنف بنجاح',
        ]);
    }

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

    public function search(Request $request)
    {
        $search = $request->input('search', '');
        $items = Item::where('itemName2', 'LIKE', "%{$search}%")
            ->orderBy('itemName2')
            ->get(['itemID', 'itemName2']);

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }
}
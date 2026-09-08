<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Type;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class TypeController extends Controller
{
    public function index()
    {
        $types = Type::orderBy('id', 'asc')->get();
        return view('setting.inventory.types.index', compact('types'));
    }

    public function list(Request $request)
    {
        $types = Type::orderBy('id', 'asc')->get();
        return response()->json([
            'success' => true,
            'data' => $types,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:type,name',
            'code' => 'nullable|string|max:50',
        ], [
            'name.unique' => 'هذا النوع موجود بالفعل',
            'name.required' => 'اسم النوع مطلوب',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first('name'),
                'errors' => $validator->errors()
            ], 422);
        }

        $type = Type::create([
            'name' => $request->name,
            'code' => $request->code,
            'is_active' => 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة النوع بنجاح',
            'data' => $type,
        ]);
    }

    public function update(Request $request, $id)
    {
        $type = Type::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('type', 'name')->ignore($type->id, 'id'),
            ],
            'code' => 'nullable|string|max:50',
        ], [
            'name.unique' => 'هذا النوع موجود بالفعل',
            'name.required' => 'اسم النوع مطلوب',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first('name'),
                'errors' => $validator->errors()
            ], 422);
        }

        $type->update([
            'name' => $request->name,
            'code' => $request->code,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث النوع بنجاح',
            'data' => $type,
        ]);
    }

    public function destroy($id)
    {
        $type = Type::findOrFail($id);
        $type->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف النوع بنجاح',
        ]);
    }

    public function toggleStatus($id)
    {
        $type = Type::findOrFail($id);
        $newStatus = !$type->is_active;
        $type->update(['is_active' => $newStatus]);

        $statusText = $newStatus ? 'تم التفعيل' : 'تم التعطيل';
        return response()->json([
            'success' => true,
            'message' => $statusText . ' بنجاح',
            'data' => $type,
        ]);
    }
}
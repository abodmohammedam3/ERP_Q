<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Unit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class UnitController extends Controller
{
    public function index()
    {
        $units = Unit::orderBy('UnitID', 'asc')->get();
        return view('setting.inventory.units.index', compact('units'));
    }

    public function list(Request $request)
    {
        $units = Unit::orderBy('UnitID', 'asc')->get();
        return response()->json([
            'success' => true,
            'data' => $units,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'UnitName' => 'required|string|max:255|unique:units,UnitName',
        ], [
            'UnitName.unique' => 'هذه الوحدة موجودة بالفعل',
            'UnitName.required' => 'اسم الوحدة مطلوب',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first('UnitName'),
                'errors' => $validator->errors()
            ], 422);
        }

        $unit = Unit::create([
            'UnitName' => $request->UnitName,
            'is_active' => 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة الوحدة بنجاح',
            'data' => $unit,
        ]);
    }

    public function update(Request $request, $id)
    {
        $unit = Unit::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'UnitName' => [
                'required',
                'string',
                'max:255',
                Rule::unique('units', 'UnitName')->ignore($unit->UnitID, 'UnitID'),
            ],
        ], [
            'UnitName.unique' => 'هذه الوحدة موجودة بالفعل',
            'UnitName.required' => 'اسم الوحدة مطلوب',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first('UnitName'),
                'errors' => $validator->errors()
            ], 422);
        }

        $unit->update([
            'UnitName' => $request->UnitName,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الوحدة بنجاح',
            'data' => $unit,
        ]);
    }

    public function destroy($id)
    {
        $unit = Unit::findOrFail($id);
        $unit->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الوحدة بنجاح',
        ]);
    }

    public function toggleStatus($id)
    {
        $unit = Unit::findOrFail($id);
        $newStatus = !$unit->is_active;
        $unit->update(['is_active' => $newStatus]);

        $statusText = $newStatus ? 'تم التفعيل' : 'تم التعطيل';
        return response()->json([
            'success' => true,
            'message' => $statusText . ' بنجاح',
            'data' => $unit,
        ]);
    }
}
<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\UnitStoreRequest;
use App\Http\Requests\Inventory\UnitUpdateRequest;
use App\Http\Resources\Inventory\UnitResource;
use App\Models\Inventory\Unit;
use App\Services\Inventory\UnitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controller نحيف — كل منطق الأعمال في UnitService (نموذج الوحدة 1).
 */
class UnitController extends Controller
{
    public function __construct(private readonly UnitService $service)
    {
    }

    public function index(): View
    {
        $units = $this->service->allForView();

        return view('setting.inventory.units.index', compact('units'));
    }

    public function list(Request $request): JsonResponse
    {
        $page   = max(1, (int) $request->input('page', 1));
        $search = trim((string) $request->input('search', ''));
        $full   = $request->boolean('full');

        // full=1: القائمة الكاملة (طباعة/تصدير) — عقد متوافق مع الواجهة الحالية
        if ($full) {
            $units = $this->service->allForView();

            return response()->json([
                'success' => true,
                'data'    => UnitResource::collection($units),
            ]);
        }

        $paginator = $this->service->list($search ?: null, $page);

        return response()->json([
            'success' => true,
            'data'    => UnitResource::collection($paginator->items()),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    public function store(UnitStoreRequest $request): JsonResponse
    {
        try {
            $unit = $this->service->store($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'تم إضافة الوحدة بنجاح',
                'data'    => new UnitResource($unit),
            ]);
        } catch (\RuntimeException $e) {
            return $this->fail($e);
        }
    }

    public function update(UnitUpdateRequest $request, $id): JsonResponse
    {
        try {
            $unit = $this->service->update((int) $id, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الوحدة بنجاح',
                'data'    => new UnitResource($unit),
            ]);
        } catch (\RuntimeException $e) {
            return $this->fail($e);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $this->service->delete((int) $id);

            return response()->json([
                'success' => true,
                'message' => 'تم حذف الوحدة بنجاح',
            ]);
        } catch (\RuntimeException $e) {
            return $this->fail($e);
        }
    }

    public function toggleStatus($id): JsonResponse
    {
        try {
            $unit = $this->service->toggleStatus((int) $id);

            $statusText = $unit->is_active ? 'تم التفعيل' : 'تم التعطيل';

            return response()->json([
                'success' => true,
                'message' => $statusText . ' بنجاح',
                'data'    => new UnitResource($unit),
            ]);
        } catch (\RuntimeException $e) {
            return $this->fail($e);
        }
    }

    /**
     * رسالة استثناء موحّدة → عقد الواجهة {success,message} مع 422.
     */
    private function fail(\RuntimeException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 422);
    }
}
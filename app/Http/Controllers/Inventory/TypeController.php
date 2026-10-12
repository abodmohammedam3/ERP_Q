<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\TypeStoreRequest;
use App\Http\Requests\Inventory\TypeUpdateRequest;
use App\Http\Resources\Inventory\TypeResource;
use App\Services\Inventory\TypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controller نحيف — كل منطق الأعمال في TypeService (نمط الوحدة المرجعية).
 */
class TypeController extends Controller
{
    public function __construct(private readonly TypeService $service)
    {
    }

    public function index(): View
    {
        $types = $this->service->allForView();

        return view('setting.inventory.types.index', compact('types'));
    }

    public function list(Request $request): JsonResponse
    {
        $page   = max(1, (int) $request->input('page', 1));
        $search = trim((string) $request->input('search', ''));
        $full   = $request->boolean('full');

        // full=1: القائمة الكاملة (طباعة/تصدير) — عقد متوافق مع الواجهة الحالية
        if ($full) {
            $types = $this->service->allForView();

            return response()->json([
                'success' => true,
                'data'    => TypeResource::collection($types),
            ]);
        }

        $paginator = $this->service->list($search ?: null, $page);

        return response()->json([
            'success' => true,
            'data'    => TypeResource::collection($paginator->items()),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    public function store(TypeStoreRequest $request): JsonResponse
    {
        try {
            $type = $this->service->store($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'تم إضافة النوع بنجاح',
                'data'    => new TypeResource($type),
            ]);
        } catch (\RuntimeException $e) {
            return $this->fail($e);
        }
    }

    public function update(TypeUpdateRequest $request, $id): JsonResponse
    {
        try {
            $type = $this->service->update((int) $id, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث النوع بنجاح',
                'data'    => new TypeResource($type),
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
                'message' => 'تم حذف النوع بنجاح',
            ]);
        } catch (\RuntimeException $e) {
            return $this->fail($e);
        }
    }

    public function toggleStatus($id): JsonResponse
    {
        try {
            $type = $this->service->toggleStatus((int) $id);

            $statusText = $type->is_active ? 'تم التفعيل' : 'تم التعطيل';

            return response()->json([
                'success' => true,
                'message' => $statusText . ' بنجاح',
                'data'    => new TypeResource($type),
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
<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ItemStoreRequest;
use App\Http\Requests\Inventory\ItemUpdateRequest;
use App\Http\Resources\Inventory\ItemResource;
use App\Models\Inventory\Item;
use App\Services\Inventory\ItemService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controller نحيف — كل منطق الأعمال في ItemService (نمط الوحدة المرجعية).
 */
class ItemController extends Controller
{
    public function __construct(private readonly ItemService $service)
    {
    }

    public function index(): View
    {
        $items = $this->service->allForView();

        return view('setting.inventory.items.index', compact('items'));
    }

    public function list(Request $request): JsonResponse
    {
        $page   = max(1, (int) $request->input('page', 1));
        $search = trim((string) $request->input('search', ''));
        $full   = $request->boolean('full');

        // full=1: القائمة الكاملة (طباعة/تصدير) — عقد متوافق مع الواجهة الحالية
        if ($full) {
            $items = $this->service->allForView();

            return response()->json([
                'success' => true,
                'data'    => ItemResource::collection($items),
            ]);
        }

        $paginator = $this->service->list($search ?: null, $page);

        return response()->json([
            'success' => true,
            'data'    => ItemResource::collection($paginator->items()),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    public function store(ItemStoreRequest $request): JsonResponse
    {
        try {
            $item = $this->service->store($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'تم إضافة الصنف بنجاح',
                'data'    => new ItemResource($item),
            ]);
        } catch (\RuntimeException $e) {
            return $this->fail($e);
        }
    }

    public function update(ItemUpdateRequest $request, $id): JsonResponse
    {
        try {
            $item = $this->service->update((int) $id, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الصنف بنجاح',
                'data'    => new ItemResource($item),
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->notFound($e);
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
                'message' => 'تم حذف الصنف بنجاح',
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->notFound($e);
        } catch (\RuntimeException $e) {
            return $this->fail($e);
        }
    }

    public function toggleStatus($id): JsonResponse
    {
        try {
            $item = $this->service->toggleStatus((int) $id);

            $statusText = $item->is_active ? 'تم التفعيل' : 'تم التعطيل';

            return response()->json([
                'success' => true,
                'message' => $statusText . ' بنجاح',
                'data'    => new ItemResource($item),
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->notFound($e);
        } catch (\RuntimeException $e) {
            return $this->fail($e);
        }
    }

    /**
     * بحث نوافذ الاختيار (lookup) — الأصناف النشطة فقط، حد أقصى 100.
     * يُبقي هذا المسار منفصلاً عن list لأنه عقد مستقل تستخدمه تفاصيل الفواتير.
     */
    public function search(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));

        $items = Item::query()
            ->active()
            ->select(['itemID', 'itemName2'])
            ->when($search !== '', fn ($q) => $q->where('itemName2', 'like', "{$search}%"))
            ->orderBy('itemName2')
            ->limit(100)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $items,
        ]);
    }

    /**
     * سجل غير موجود → 404 بعقد الواجهة نفسه.
     */
    private function notFound(ModelNotFoundException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'الصنف المطلوب غير موجود',
        ], 404);
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
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Export\ExportService;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\JsonResponse;

class ExportController extends Controller
{
    protected ExportService $exportService;

    public function __construct(ExportService $exportService)
    {
        $this->exportService = $exportService;
    }

    /**
     * Handle export for a given entity.
     */
    public function export(Request $request, string $entity): StreamedResponse|JsonResponse
    {
        try {
            return $this->exportService->export($entity, $request->all());
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error'   => 'Invalid Entity',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    public function units(Request $request): StreamedResponse|JsonResponse
    {
        return $this->export($request, 'units');
    }

    public function type(Request $request): StreamedResponse|JsonResponse
    {
        return $this->export($request, 'type');
    }

    public function coins(Request $request): StreamedResponse|JsonResponse
    {
        return $this->export($request, 'coins');
    }

    public function characcount(Request $request): StreamedResponse|JsonResponse
    {
        return $this->export($request, 'characcount');
    }

    public function boxes(Request $request): StreamedResponse|JsonResponse
    {
        return $this->export($request, 'boxes');
    }

    public function banks(Request $request): StreamedResponse|JsonResponse
    {
        return $this->export($request, 'banks');
    }

    public function stocks(Request $request): StreamedResponse|JsonResponse
    {
        return $this->export($request, 'stocks');
    }

    public function items(Request $request): StreamedResponse|JsonResponse
    {
        return $this->export($request, 'items');
    }

    public function customers(Request $request): StreamedResponse|JsonResponse
    {
        return $this->export($request, 'customers');
    }

    public function suppliers(Request $request): StreamedResponse|JsonResponse
    {
        return $this->export($request, 'suppliers');
    }
}

<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardService;
use App\Support\DashboardCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * لوحة المعلومات — Controller رقيق.
 *
 * لماذا Controller وليس Closure في routes/web.php كما كان:
 *  - نفس نمط كل شاشات النظام (SalesInvoiceController, PurchaseInvoiceController).
 *  - الـ Route name أصبح ممكنًا (routeIs('dashboard')) بعد أن كان
 *    sidebar.blade.php:44 يعتمد على نص المسار request()->is('dashboard').
 *  - أي صلاحية مستقبلية (Permissions::authorize) تحتاج Controller.
 *
 * SSR هنا محدود عن قصد: نمرّر للمتصفح indicators + summary + version فقط
 * (7 أرقام)، لا الجداول. السبب:
 *  - الشاشة الحالية تعرض "0" حتى انتهاء الـ fetch (SystemIndicators.blade.php:27)
 *    فيرى المستخدم صفرًا زائفًا لحظة الفتح.
 *  - تمرير الجداول عبر SSR سيكر استعلامات ويتعارض مع نمط AJAX الموجود
 *    في كل شاشة (pages/*.js) ومع نمط reports/center.blade.php:136.
 *  - الشاشة الرئيسية تُخدم من مسارين: "/" و "/dashboard" — SSR في أحدهما
 *    فقط كان سيترك الأخرى بأصفار.
 */
class DashboardController extends Controller
{
    public function __construct(private DashboardService $service)
    {
    }

    /**
     * الشاشة (تُخدم من "/" و "/dashboard").
     */
    public function index(): View
    {
        // نفس نمط SalesInvoiceController::index(): تحرير الجلسة مبكرًا
        // لتوازي طلبات الحصول على البيانات بعد رسم الصفحة.
        session()->save();

        $initial = [
            'indicators' => [],
            'summary'    => [],
            'version'    => DashboardCache::version(),
        ];

        // SSR دفاعي: أي فشل هنا لا يمنع فتح الشاشة — الواجهة تكمل عبر API.
        try {
            $initial['indicators'] = $this->service->indicators();
            $initial['summary']    = $this->service->summary();
        } catch (\Throwable $e) {
            Log::warning('[Dashboard] SSR failed — falling back to AJAX', [
                'error' => $e->getMessage(),
            ]);
        }

        return view('dashboard.index', [
            'initial' => $initial,
        ]);
    }

    /**
     * البيانات الكاملة للشاشة (JSON).
     *
     * نقطة واحدة لكل الأجزاء: كل جزء منها مخزّن في الكاش بمفتاح يحمل الإصدار،
     * فتضيف استعلامات فقط بعد أي كتابة (FlushDashboardCache).
     */
    public function data(): JsonResponse
    {
        try {
            $payload = $this->service->payload();

            return response()->json([
                'success' => true,
                'data'    => $payload,
            ]);
        } catch (\Throwable $e) {
            Log::error('[Dashboard] data failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'تعذّر تحميل بيانات لوحة المعلومات',
            ], 500);
        }
    }
}

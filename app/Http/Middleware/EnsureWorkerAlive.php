<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkerAlive
{
    /**
     * يفحص إذا Worker حي كل 5 دقائق.
     * إن لا → يشغّله في الخلفية.
     * هذا Fallback إن فشل Task Scheduler.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // مرة كل 5 دقائق فقط
        if (Cache::add('worker.health.check', true, 300)) {
            try {
                $this->ensureWorkerRunning();
            } catch (\Throwable $e) {
                Log::warning('[Worker] Health check failed', ['error' => $e->getMessage()]);
            }
        }

        return $next($request);
    }

    private function ensureWorkerRunning(): void
    {
        // هذا الـ Fallback مخصص لطابور database — لا فائدة مع sync/redis
        if (config('queue.default') !== 'database') {
            return;
        }

        // هل يوجد Job عالق؟
        $pending = DB::table('jobs')->where('queue', 'backups')->count();
        if ($pending === 0) {
            return; // لا حاجة
        }

        // فحص آخر Job قديم (يدل على أن Worker مات)
        $oldest = DB::table('jobs')
            ->where('queue', 'backups')
            ->min('available_at');

        if (! $oldest) {
            return;
        }

        // إذا أقدم Job أقدم من 3 دقائق → Worker ميت
        if (now()->timestamp - $oldest < 180) {
            return; // حديث → اتركه
        }

        // شغّل Worker
        $this->spawnWorker();
    }

    private function spawnWorker(): void
    {
        $workerDir = storage_path('app/worker');
        $vbs = $workerDir.DIRECTORY_SEPARATOR.'start-hidden.vbs';

        if (PHP_OS_FAMILY === 'Windows') {
            if (! file_exists($vbs)) {
                Log::warning('[Worker] start-hidden.vbs not found — run installer first');
                return;
            }

            pclose(popen('start /B "" wscript.exe "'. $vbs .'"', 'r'));
        } else {
            $php = PHP_BINARY;
            $artisan = base_path('artisan');
            exec(sprintf(
                'nohup %s %s queue:work --queue=backups,default --tries=1 --timeout=3600 > /dev/null 2>&1 &',
                escapeshellarg($php),
                escapeshellarg($artisan)
            ));
        }

        Log::info('[Worker] Self-healing spawn triggered');
    }
}

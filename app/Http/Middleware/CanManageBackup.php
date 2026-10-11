<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CanManageBackup
{
    public function handle(Request $request, Closure $next): Response
    {
        // ⚠️ حالياً مفتوح — لا يوجد نظام صلاحيات في المشروع.
        // عند إضافة spatie/laravel-permission:
        //   if (!$request->user()?->can('manage-backup')) {
        //       abort(403, 'لا تملك صلاحية إدارة النسخ الاحتياطي');
        //   }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReleaseSessionLock
{
    /**
     * حرّر قفل الجلسة فورًا للطلبات AJAX/JSON
     * لمنع تعطّل الطلبات المتوازية بسبب Session Lock
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->expectsJson() || $request->ajax()) {
            session()->save();
        }

        return $next($request);
    }
}
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'can-manage-backup' => \App\Http\Middleware\CanManageBackup::class,
        ]);

        // السماح بمتابعة عمليات النسخ الاحتياطي وتنزيل النتائج
        // حتى أثناء وضع الصيانة (الذي يُفعَّل أثناء الاستيراد في الإنتاج)
        $middleware->preventRequestsDuringMaintenance(except: [
            'settings/system/backup/operations/*',
            'settings/system/backup/download/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
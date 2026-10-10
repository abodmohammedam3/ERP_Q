<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;

class SystemSettingsController extends Controller
{
    /**
     * صفحة إعدادات النظام — الواجهة مؤجلة.
     *
     * حالياً نُعيد JSON للتحقق من الـ routes.
     * الواجهة (Blade) ستُبنى في مرحلة لاحقة.
     */
    public function index()
    {
        return response()->json([
            'success'    => true,
            'message'    => 'إعدادات النظام — الواجهة قيد التطوير',
            'backup_api' => [
                'index'   => route('settings.system.backup.index'),
                'export'  => route('settings.system.backup.export'),
                'import'  => route('settings.system.backup.import'),
                'cleanup' => route('settings.system.backup.cleanup'),
            ],
        ]);
    }
}

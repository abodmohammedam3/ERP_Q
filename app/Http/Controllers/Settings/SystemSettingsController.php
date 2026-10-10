<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;

class SystemSettingsController extends Controller
{
    /**
     * صفحة إعدادات النظام — تاب النسخ الاحتياطي.
     */
    public function index()
    {
        return view('settings.system.index');
    }
}

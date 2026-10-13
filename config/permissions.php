<?php

return [

    /*
    |--------------------------------------------------------------------------
    | تفعيل نظام الصلاحيات
    |--------------------------------------------------------------------------
    |
    | false → كل شيء مسموح (الوضع الحالي)
    | true  → التحقق من الصلاحيات فعليًا
    |
    | عند التفعيل: غيّر PERMISSIONS_ENABLED=true في .env
    |
    */
    'enabled' => env('PERMISSIONS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Purchase Invoices
    |--------------------------------------------------------------------------
    */
    'purchase_invoices' => [
        'view'   => 'purchase_invoices.view',
        'create' => 'purchase_invoices.create',
        'update' => 'purchase_invoices.update',
        'delete' => 'purchase_invoices.delete',
        'post'   => 'purchase_invoices.post',
        'unpost' => 'purchase_invoices.unpost',
        'print'  => 'purchase_invoices.print',
    ],

    /*
    |--------------------------------------------------------------------------
    | Purchase Returns
    |--------------------------------------------------------------------------
    */
    'purchase_returns' => [
        'view'   => 'purchase_returns.view',
        'create' => 'purchase_returns.create',
        'update' => 'purchase_returns.update',
        'delete' => 'purchase_returns.delete',
        'post'   => 'purchase_returns.post',
        'unpost' => 'purchase_returns.unpost',
        'print'  => 'purchase_returns.print',
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Vouchers
    |--------------------------------------------------------------------------
    */
    'payment_vouchers' => [
        'view'   => 'payment_vouchers.view',
        'create' => 'payment_vouchers.create',
        'update' => 'payment_vouchers.update',
        'delete' => 'payment_vouchers.delete',
        'post'   => 'payment_vouchers.post',
        'unpost' => 'payment_vouchers.unpost',
        'print'  => 'payment_vouchers.print',
    ],

    /*
    |--------------------------------------------------------------------------
    | Receipt Vouchers
    |--------------------------------------------------------------------------
    */
    'receipt_vouchers' => [
        'view'   => 'receipt_vouchers.view',
        'create' => 'receipt_vouchers.create',
        'update' => 'receipt_vouchers.update',
        'delete' => 'receipt_vouchers.delete',
        'post'   => 'receipt_vouchers.post',
        'unpost' => 'receipt_vouchers.unpost',
        'print'  => 'receipt_vouchers.print',
    ],
    /*
|--------------------------------------------------------------------------
| Sales Invoices
|--------------------------------------------------------------------------
*/
'sales_invoices' => [
    'view'   => 'sales_invoices.view',
    'create' => 'sales_invoices.create',
    'update' => 'sales_invoices.update',
    'delete' => 'sales_invoices.delete',
    'post'   => 'sales_invoices.post',
    'unpost' => 'sales_invoices.unpost',
    'print'  => 'sales_invoices.print',
],

/*
|--------------------------------------------------------------------------
| Sales Returns
|--------------------------------------------------------------------------
*/
'sales_returns' => [
    'view'   => 'sales_returns.view',
    'create' => 'sales_returns.create',
    'update' => 'sales_returns.update',
    'delete' => 'sales_returns.delete',
    'post'   => 'sales_returns.post',
    'unpost' => 'sales_returns.unpost',
    'print'  => 'sales_returns.print',
],

];
<?php

namespace Database\Seeders;

use App\Models\Accounting\CharAccount;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [

            // =========================================
            // 1 - الأصول
            // =========================================

            [
                'system_key' => 'assets',
                'accCode' => '1',
                'accName' => 'الأصول',
                'accParentKey' => null,
                'accLevel' => 1,
                'nature' => 0,
            ],

            [
                'system_key' => 'current_assets',
                'accCode' => '11',
                'accName' => 'الأصول المتداولة',
                'accParentKey' => 'assets',
                'accLevel' => 2,
                'nature' => 0,
            ],

            [
                'system_key' => 'cash',
                'accCode' => '1101',
                'accName' => 'الصندوق',
                'accParentKey' => 'current_assets',
                'accLevel' => 3,
                'nature' => 0,
            ],

            [
                'system_key' => 'banks',
                'accCode' => '1102',
                'accName' => 'البنوك',
                'accParentKey' => 'current_assets',
                'accLevel' => 3,
                'nature' => 0,
            ],

            [
                'system_key' => 'customers',
                'accCode' => '1103',
                'accName' => 'العملاء',
                'accParentKey' => 'current_assets',
                'accLevel' => 3,
                'nature' => 0,
            ],

            [
                'system_key' => 'inventory',
                'accCode' => '1104',
                'accName' => 'المخزون',
                'accParentKey' => 'current_assets',
                'accLevel' => 3,
                'nature' => 0,
            ],

            [
                'system_key' => 'prepaid_expenses',
                'accCode' => '1105',
                'accName' => 'المصروفات المدفوعة مقدماً',
                'accParentKey' => 'current_assets',
                'accLevel' => 3,
                'nature' => 0,
            ],

            [
                'system_key' => 'accrued_revenue',
                'accCode' => '1106',
                'accName' => 'إيرادات مستحقة',
                'accParentKey' => 'current_assets',
                'accLevel' => 3,
                'nature' => 0,
            ],

            [
                'system_key' => 'non_current_assets',
                'accCode' => '12',
                'accName' => 'الأصول غير المتداولة',
                'accParentKey' => 'assets',
                'accLevel' => 2,
                'nature' => 0,
            ],

            [
                'system_key' => 'land',
                'accCode' => '1201',
                'accName' => 'الأراضي',
                'accParentKey' => 'non_current_assets',
                'accLevel' => 3,
                'nature' => 0,
            ],

            [
                'system_key' => 'buildings',
                'accCode' => '1202',
                'accName' => 'المباني',
                'accParentKey' => 'non_current_assets',
                'accLevel' => 3,
                'nature' => 0,
            ],

            [
                'system_key' => 'vehicles',
                'accCode' => '1203',
                'accName' => 'السيارات',
                'accParentKey' => 'non_current_assets',
                'accLevel' => 3,
                'nature' => 0,
            ],

            [
                'system_key' => 'furniture',
                'accCode' => '1204',
                'accName' => 'الأثاث والتجهيزات',
                'accParentKey' => 'non_current_assets',
                'accLevel' => 3,
                'nature' => 0,
            ],

            [
                'system_key' => 'equipment',
                'accCode' => '1205',
                'accName' => 'أجهزة ومعدات',
                'accParentKey' => 'non_current_assets',
                'accLevel' => 3,
                'nature' => 0,
            ],

            [
                'system_key' => 'accumulated_depreciation',
                'accCode' => '1206',
                'accName' => 'مجمع الإهلاك',
                'accParentKey' => 'non_current_assets',
                'accLevel' => 3,
                'nature' => 1,
            ],


            // =========================================
            // 2 - الخصوم
            // =========================================

            [
                'system_key' => 'liabilities',
                'accCode' => '2',
                'accName' => 'الخصوم',
                'accParentKey' => null,
                'accLevel' => 1,
                'nature' => 1,
            ],

            [
                'system_key' => 'current_liabilities',
                'accCode' => '21',
                'accName' => 'الخصوم المتداولة',
                'accParentKey' => 'liabilities',
                'accLevel' => 2,
                'nature' => 1,
            ],

            [
                'system_key' => 'suppliers',
                'accCode' => '2101',
                'accName' => 'الموردون',
                'accParentKey' => 'current_liabilities',
                'accLevel' => 3,
                'nature' => 1,
            ],

            [
                'system_key' => 'creditors',
                'accCode' => '2102',
                'accName' => 'الدائنون',
                'accParentKey' => 'current_liabilities',
                'accLevel' => 3,
                'nature' => 1,
            ],

            [
                'system_key' => 'accrued_expenses',
                'accCode' => '2103',
                'accName' => 'مصروفات مستحقة',
                'accParentKey' => 'current_liabilities',
                'accLevel' => 3,
                'nature' => 1,
            ],

            [
                'system_key' => 'taxes_payable',
                'accCode' => '2104',
                'accName' => 'ضرائب مستحقة',
                'accParentKey' => 'current_liabilities',
                'accLevel' => 3,
                'nature' => 1,
            ],

            [
                'system_key' => 'unearned_revenue',
                'accCode' => '2105',
                'accName' => 'إيرادات مقدمة',
                'accParentKey' => 'current_liabilities',
                'accLevel' => 3,
                'nature' => 1,
            ],

            [
                'system_key' => 'non_current_liabilities',
                'accCode' => '22',
                'accName' => 'الخصوم غير المتداولة',
                'accParentKey' => 'liabilities',
                'accLevel' => 2,
                'nature' => 1,
            ],

            [
                'system_key' => 'long_term_loans',
                'accCode' => '2201',
                'accName' => 'قروض طويلة الأجل',
                'accParentKey' => 'non_current_liabilities',
                'accLevel' => 3,
                'nature' => 1,
            ],

            [
                'system_key' => 'other_long_term_liabilities',
                'accCode' => '2202',
                'accName' => 'التزامات طويلة الأجل',
                'accParentKey' => 'non_current_liabilities',
                'accLevel' => 3,
                'nature' => 1,
            ],


            // =========================================
            // 3 - حقوق الملكية
            // =========================================

            [
                'system_key' => 'equity',
                'accCode' => '3',
                'accName' => 'حقوق الملكية',
                'accParentKey' => null,
                'accLevel' => 1,
                'nature' => 1,
            ],

            [
                'system_key' => 'capital',
                'accCode' => '31',
                'accName' => 'رأس المال',
                'accParentKey' => 'equity',
                'accLevel' => 2,
                'nature' => 1,
            ],

            [
                'system_key' => 'owner_partners_current',
                'accCode' => '32',
                'accName' => 'جاري المالك / الشركاء',
                'accParentKey' => 'equity',
                'accLevel' => 2,
                'nature' => 1,
            ],

            [
                'system_key' => 'reserves',
                'accCode' => '33',
                'accName' => 'الاحتياطيات',
                'accParentKey' => 'equity',
                'accLevel' => 2,
                'nature' => 1,
            ],

            [
                'system_key' => 'retained_earnings',
                'accCode' => '34',
                'accName' => 'الأرباح المحتجزة',
                'accParentKey' => 'equity',
                'accLevel' => 2,
                'nature' => 1,
            ],

            [
                'system_key' => 'net_profit_loss',
                'accCode' => '35',
                'accName' => 'صافي الربح / الخسارة',
                'accParentKey' => 'equity',
                'accLevel' => 2,
                'nature' => 1,
            ],


            // =========================================
            // 4 - الإيرادات
            // =========================================

            [
                'system_key' => 'revenues',
                'accCode' => '4',
                'accName' => 'الإيرادات',
                'accParentKey' => null,
                'accLevel' => 1,
                'nature' => 1,
            ],

            [
                'system_key' => 'main_operating_revenues',
                'accCode' => '41',
                'accName' => 'إيرادات النشاط الرئيسي',
                'accParentKey' => 'revenues',
                'accLevel' => 2,
                'nature' => 1,
            ],

            [
                'system_key' => 'sales_revenue',
                'accCode' => '4101',
                'accName' => 'إيرادات المبيعات',
                'accParentKey' => 'main_operating_revenues',
                'accLevel' => 3,
                'nature' => 1,
            ],

            [
                'system_key' => 'service_revenue',
                'accCode' => '4102',
                'accName' => 'إيرادات الخدمات',
                'accParentKey' => 'main_operating_revenues',
                'accLevel' => 3,
                'nature' => 1,
            ],

            [
                'system_key' => 'sales_returns_discounts',
                'accCode' => '42',
                'accName' => 'مردودات وخصومات المبيعات',
                'accParentKey' => 'revenues',
                'accLevel' => 2,
                'nature' => 0,
            ],

            [
                'system_key' => 'sales_returns',
                'accCode' => '4201',
                'accName' => 'مردودات المبيعات',
                'accParentKey' => 'sales_returns_discounts',
                'accLevel' => 3,
                'nature' => 0,
            ],

            [
                'system_key' => 'allowed_discount',
                'accCode' => '4202',
                'accName' => 'الخصم المسموح به',
                'accParentKey' => 'sales_returns_discounts',
                'accLevel' => 3,
                'nature' => 0,
            ],

            [
                'system_key' => 'other_revenues',
                'accCode' => '43',
                'accName' => 'إيرادات أخرى',
                'accParentKey' => 'revenues',
                'accLevel' => 2,
                'nature' => 1,
            ],

            [
                'system_key' => 'investment_revenues',
                'accCode' => '4301',
                'accName' => 'إيرادات استثمارية',
                'accParentKey' => 'other_revenues',
                'accLevel' => 3,
                'nature' => 1,
            ],

            [
                'system_key' => 'other_revenues_detail',
                'accCode' => '4302',
                'accName' => 'إيرادات أخرى',
                'accParentKey' => 'other_revenues',
                'accLevel' => 3,
                'nature' => 1,
            ],


            // =========================================
            // 5 - المصروفات
            // =========================================

            [
                'system_key' => 'expenses',
                'accCode' => '5',
                'accName' => 'المصروفات',
                'accParentKey' => null,
                'accLevel' => 1,
                'nature' => 0,
            ],

            [
                'system_key' => 'cost_of_sales',
                'accCode' => '51',
                'accName' => 'تكلفة المبيعات',
                'accParentKey' => 'expenses',
                'accLevel' => 2,
                'nature' => 0,
            ],

            [
                'system_key' => 'cost_of_goods_sold',
                'accCode' => '5101',
                'accName' => 'تكلفة البضاعة المباعة',
                'accParentKey' => 'cost_of_sales',
                'accLevel' => 3,
                'nature' => 0,
            ],

            [
                'system_key' => 'cost_of_services',
                'accCode' => '5102',
                'accName' => 'تكلفة الخدمات',
                'accParentKey' => 'cost_of_sales',
                'accLevel' => 3,
                'nature' => 0,
            ],

            [
                'system_key' => 'operating_expenses',
                'accCode' => '52',
                'accName' => 'المصروفات التشغيلية',
                'accParentKey' => 'expenses',
                'accLevel' => 2,
                'nature' => 0,
            ],

            [
                'system_key' => 'salaries_wages',
                'accCode' => '5201',
                'accName' => 'الرواتب والأجور',
                'accParentKey' => 'operating_expenses',
                'accLevel' => 3,
                'nature' => 0,
            ],

            [
                'system_key' => 'rent',
                'accCode' => '5202',
                'accName' => 'الإيجارات',
                'accParentKey' => 'operating_expenses',
                'accLevel' => 3,
                'nature' => 0,
            ],

            [
                'system_key' => 'utilities',
                'accCode' => '5203',
                'accName' => 'الكهرباء والمياه',
                'accParentKey' => 'operating_expenses',
                'accLevel' => 3,
                'nature' => 0,
            ],

            [
                'system_key' => 'communications',
                'accCode' => '5204',
                'accName' => 'الاتصالات',
                'accParentKey' => 'operating_expenses',
                'accLevel' => 3,
                'nature' => 0,
            ],

            [
                'system_key' => 'transportation',
                'accCode' => '5205',
                'accName' => 'النقل والمواصلات',
                'accParentKey' => 'operating_expenses',
                'accLevel' => 3,
                'nature' => 0,
            ],

            [
                'system_key' => 'maintenance',
                'accCode' => '5206',
                'accName' => 'الصيانة',
                'accParentKey' => 'operating_expenses',
                'accLevel' => 3,
                'nature' => 0,
            ],

            [
                'system_key' => 'marketing_advertising',
                'accCode' => '5207',
                'accName' => 'التسويق والإعلان',
                'accParentKey' => 'operating_expenses',
                'accLevel' => 3,
                'nature' => 0,
            ],

            [
                'system_key' => 'administrative_expenses',
                'accCode' => '5208',
                'accName' => 'مصروفات إدارية',
                'accParentKey' => 'operating_expenses',
                'accLevel' => 3,
                'nature' => 0,
            ],

            [
                'system_key' => 'other_expenses',
                'accCode' => '53',
                'accName' => 'مصروفات أخرى',
                'accParentKey' => 'expenses',
                'accLevel' => 2,
                'nature' => 0,
            ],

            [
                'system_key' => 'bank_expenses',
                'accCode' => '5301',
                'accName' => 'مصروفات بنكية',
                'accParentKey' => 'other_expenses',
                'accLevel' => 3,
                'nature' => 0,
            ],

            [
                'system_key' => 'other_expenses_detail',
                'accCode' => '5302',
                'accName' => 'مصروفات أخرى',
                'accParentKey' => 'other_expenses',
                'accLevel' => 3,
                'nature' => 0,
            ],
        ];

        $keys = [];

        foreach ($accounts as $data) {

            $parentId = null;

            if (
                isset($data['accParentKey']) &&
                $data['accParentKey'] !== null
            ) {
                $parentId = $keys[$data['accParentKey']] ?? null;

                if (!$parentId) {
                    throw new \RuntimeException(
                        'الحساب الأب غير موجود: ' .
                        $data['accParentKey']
                    );
                }
            }

            $account = CharAccount::updateOrCreate(
                [
                    'system_key' => $data['system_key'],
                ],
                [
                    'accCode' => $data['accCode'],
                    'accName' => $data['accName'],
                    'accParent' => $parentId,
                    'accLevel' => $data['accLevel'],
                    'nature' => $data['nature'],
                    'isPostable' => 0,
                    'IsActive' => 1,
                    'is_system' => 1,
                ]
            );

            $keys[$data['system_key']] = $account->accountID;
        }
    }
}
<?php

namespace Tests\Feature\Reports;

use App\Reports\ReportRegistry;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * اختبار عقد مركز التقارير (Report Contract).
 *
 * يمر على كل تقرير مسجّل في ReportRegistry ويتحقق من:
 *  (أ) كل عمود في columns() له label و type.
 *  (ب) كل filter.source موجود في ما تعيده sources()
 *      أو ضمن الأنواع المدعومة في نقطة البحث lookup (customers/suppliers/items).
 */
class ReportContractTest extends TestCase
{
    use DatabaseTransactions;

    /** الأنواع التي يقبلها endpoint البحث /lookup/{type} */
    private const LOOKUP_TYPES = ['customers', 'suppliers', 'items'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTablesExist();
        $this->truncateTables();
    }

    private function truncateTables(): void
    {
        DB::table('characcount')->delete();
        DB::table('coins')->delete();
        DB::table('Items')->delete();
        DB::table('Journal_Entries')->delete();
    }

    private function ensureTablesExist(): void
    {
        if (!Schema::hasTable('characcount')) {
            Schema::create('characcount', function (Blueprint $table) {
                $table->id('accountID');
                $table->string('accCode');
                $table->string('accName');
                $table->integer('accParent')->nullable();
                $table->integer('accLevel')->default(1);
                $table->integer('nature')->default(0);
                $table->boolean('isPostable')->default(1);
                $table->string('system_key')->nullable();
            });
        }

        if (!Schema::hasTable('coins')) {
            Schema::create('coins', function (Blueprint $table) {
                $table->id('coinsID');
                $table->string('coinsName');
                $table->string('coinsCode');
                $table->decimal('coinsExchangeRate', 12, 6)->default(1);
                $table->boolean('coinsSystem')->default(1);
            });
        }

        if (!Schema::hasTable('Items')) {
            Schema::create('Items', function (Blueprint $table) {
                $table->id('itemID');
                $table->text('itemName2');
                $table->boolean('is_active')->default(1);
            });
        }

        if (!Schema::hasTable('Journal_Entries')) {
            Schema::create('Journal_Entries', function (Blueprint $table) {
                $table->id('entryID');
                $table->string('entryNo');
                $table->date('entryDate');
                $table->string('docType')->nullable();
                $table->string('docNumber')->nullable();
                $table->text('description2')->nullable();
            });
        }
    }

    public function test_every_report_column_has_label_and_type(): void
    {
        foreach (ReportRegistry::all() as $key => $report) {
            $columns = $report->columns();

            $this->assertNotEmpty($columns, "التقرير {$key}: columns() فارغة");

            foreach ($columns as $column) {
                $this->assertArrayHasKey('key', $column, "التقرير {$key}: عمود بلا key");
                $this->assertArrayHasKey('label', $column, "التقرير {$key}: عمود ({$column['key']}) بلا label");
                $this->assertArrayHasKey('type', $column, "التقرير {$key}: عمود ({$column['key']}) بلا type");
                $this->assertNotEmpty($column['label'], "التقرير {$key}: عمود ({$column['key']}) بـ label فارغ");
                $this->assertNotEmpty($column['type'], "التقرير {$key}: عمود ({$column['key']}) بـ type فارغ");
            }
        }
    }

    public function test_every_report_filter_source_is_available(): void
    {
        $response = $this->getJson('/reports/sources');
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $sourceKeys = array_keys($response->json('sources'));

        foreach (ReportRegistry::all() as $key => $report) {
            foreach ($report->filters() as $filter) {
                if (empty($filter['source'])) {
                    continue;   // أنواع بلا مصدر (date/text) — لا تُفحص هنا
                }

                $this->assertContains(
                    $filter['source'],
                    array_merge($sourceKeys, self::LOOKUP_TYPES),
                    "التقرير {$key}: مصدر الفلتر ({$filter['source']}) غير موجود في sources() ولا في lookup"
                );
            }
        }
    }
}

<?php

namespace Tests\Feature\Export;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use App\Models\Inventory\Unit;
use App\Models\Inventory\Type;
use App\Models\Accounting\Coin;
use App\Models\Accounting\CharAccount;
use App\Models\Accounting\Box;
use App\Models\Accounting\Bank;
use App\Models\Inventory\Stock;
use App\Models\Inventory\Item;
use App\Models\Customer;
use App\Models\Supplier;
use Tests\TestCase;

class EntityExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        DB::purge();

        $this->createTables();
    }

    protected function createTables(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id('UnitID');
            $table->string('UnitName');
            $table->boolean('is_active')->default(1);
        });

        Schema::create('type', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->nullable();
            $table->boolean('is_active')->default(1);
        });

        Schema::create('coins', function (Blueprint $table) {
            $table->bigIncrements('coinsID');
            $table->string('coinsName', 100);
            $table->string('coinsCode', 10);
            $table->decimal('coinsExchangeRate', 18, 6)->nullable();
            $table->tinyInteger('coinsSystem')->default(0);
            $table->tinyInteger('is_active')->default(1);
        });

        Schema::create('characcount', function (Blueprint $table) {
            $table->bigIncrements('accountID');
            $table->string('accCode', 50);
            $table->string('accName', 255);
            $table->unsignedBigInteger('accParent')->nullable();
            $table->unsignedInteger('accLevel')->default(1);
            $table->unsignedBigInteger('accTypeID')->nullable();
            $table->tinyInteger('nature')->default(1);
            $table->tinyInteger('isPostable')->default(0);
            $table->tinyInteger('IsActive')->default(1);
            $table->tinyInteger('is_system')->default(0);
            $table->string('system_key', 50)->nullable();
        });

        Schema::create('boxes', function (Blueprint $table) {
            $table->bigIncrements('boxID');
            $table->unsignedBigInteger('coinsID');
            $table->unsignedBigInteger('accountID');
            $table->string('boxName', 255);
            $table->tinyInteger('is_active')->default(1);
        });

        Schema::create('banks', function (Blueprint $table) {
            $table->bigIncrements('bankID');
            $table->string('bankName', 255);
            $table->unsignedBigInteger('accountID');
            $table->unsignedBigInteger('coinsID')->nullable();
            $table->string('accountNumber', 50)->nullable();
            $table->tinyInteger('is_active')->default(1);
        });

        Schema::create('stocks', function (Blueprint $table) {
            $table->id('StockID');
            $table->string('StockName');
            $table->unsignedBigInteger('accountID');
            $table->boolean('is_active')->default(1);
        });

        Schema::create('Items', function (Blueprint $table) {
            $table->id('itemID');
            $table->text('itemName2');
            $table->boolean('is_active')->default(1);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->bigIncrements('CustomersID');
            $table->unsignedBigInteger('accountID')->nullable();
            $table->text('CustomersName2')->nullable();
            $table->string('CusPhone', 20)->nullable();
            $table->text('CusAddress')->nullable();
            $table->tinyInteger('is_active')->default(1);
        });

        Schema::create('Suppliers', function (Blueprint $table) {
            $table->bigIncrements('suplierID');
            $table->unsignedBigInteger('accountID')->nullable();
            $table->text('supName');
            $table->string('supPhone', 50)->nullable();
            $table->text('supArea')->nullable();
            $table->tinyInteger('is_active')->default(1);
        });
    }

    public function test_ac_b1_1_export_units_returns_csv_with_utf8_bom(): void
    {
        Unit::create(['UnitName' => 'كيلوجرام', 'is_active' => 1]);
        Unit::create(['UnitName' => 'جرام', 'is_active' => 0]);

        $response = $this->get(route('admin.export.units'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('UnitID,UnitName,is_active', $content);
        $this->assertStringContainsString('كيلوجرام', $content);
    }

    public function test_ac_b1_2_export_characcounts_returns_csv_ordered_by_acclevel_asc(): void
    {
        CharAccount::create([
            'accCode' => '1101',
            'accName' => 'الصندوق',
            'accLevel' => 3,
            'IsActive' => 1,
        ]);
        CharAccount::create([
            'accCode' => '1',
            'accName' => 'الأصول',
            'accLevel' => 1,
            'IsActive' => 1,
        ]);
        CharAccount::create([
            'accCode' => '11',
            'accName' => 'الأصول المتداولة',
            'accLevel' => 2,
            'IsActive' => 1,
        ]);

        $response = $this->get(route('admin.export.characcounts'));

        $response->assertStatus(200);
        $content = $response->streamedContent();

        $posLevel1 = strpos($content, 'الأصول');
        $posLevel2 = strpos($content, 'الأصول المتداولة');
        $posLevel3 = strpos($content, 'الصندوق');

        $this->assertTrue($posLevel1 < $posLevel2);
        $this->assertTrue($posLevel2 < $posLevel3);
    }

    public function test_export_all_ten_entities_returns_valid_csv(): void
    {
        Unit::create(['UnitName' => 'متر', 'is_active' => 1]);
        Type::create(['name' => 'نوع 1', 'code' => 'T1', 'is_active' => 1]);
        Coin::create(['coinsName' => 'ريال يمني', 'coinsCode' => 'YER', 'coinsExchangeRate' => 1.0, 'coinsSystem' => 1, 'is_active' => 1]);
        CharAccount::create(['accCode' => '101', 'accName' => 'حساب رئيسب', 'accLevel' => 1]);
        Box::create(['coinsID' => 1, 'accountID' => 1, 'boxName' => 'صندوق رئيسي', 'is_active' => 1]);
        Bank::create(['bankName' => 'بنك التضامن', 'accountID' => 1, 'coinsID' => 1, 'accountNumber' => '123456', 'is_active' => 1]);
        Stock::create(['StockName' => 'المخزن الرئيسي', 'accountID' => 1, 'is_active' => 1]);
        Item::create(['itemName2' => 'صنف تجريبي', 'is_active' => 1]);
        Customer::create(['accountID' => 1, 'CustomersName2' => 'عميل تجريبي', 'CusPhone' => '770000000', 'is_active' => 1]);
        Supplier::create(['accountID' => 1, 'supName' => 'مورد تجريبي', 'supPhone' => '771111111', 'is_active' => 1]);

        $routes = [
            'admin.export.units',
            'admin.export.type',
            'admin.export.coins',
            'admin.export.characcount',
            'admin.export.boxes',
            'admin.export.banks',
            'admin.export.stocks',
            'admin.export.items',
            'admin.export.customers',
            'admin.export.suppliers',
        ];

        foreach ($routes as $routeName) {
            $response = $this->get(route($routeName));
            $response->assertStatus(200);
            $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
            $this->assertStringStartsWith("\xEF\xBB\xBF", $response->streamedContent());
        }
    }

    public function test_entity_filters_work_correctly(): void
    {
        Unit::create(['UnitName' => 'نشط', 'is_active' => 1]);
        Unit::create(['UnitName' => 'موقف', 'is_active' => 0]);

        $responseActive = $this->get(route('admin.export.units', ['is_active' => 1]));
        $contentActive = $responseActive->streamedContent();

        $this->assertStringContainsString('نشط', $contentActive);
        $this->assertStringNotContainsString('موقف', $contentActive);
    }
}

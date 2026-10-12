<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Type;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ============================================================
 *  اختبارات شاشة أنواع الأصناف (الوحدة 2)
 * ============================================================
 *
 *  يغطي:
 *   1. عرض الشاشة والقائمة (عقد JSON + بحث Prefix + meta)
 *   2. full=1 (طباعة/تصدير)
 *   3. الإضافة (برسالة تكرار عربية و422)
 *   4. التعديل (تجاهل الذات في unique + رفض تكرار اسم الغير)
 *   5. تبديل الحالة
 *   6. الحذف الآمن + حماية الحذف عند الاستخدام (فحص مسبق عربي)
 *   7. حقول التتبع في fillable (تصحيح T1)
 *
 *  ملاحظة: بدون middleware (auth/csrf/session) — نفس نمط InventorySystemTest.
 * ============================================================
 */
class TypesCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // تعطيل كل الـ middleware (auth, csrf, session) — نفس نمط InventorySystemTest
        $this->withoutMiddleware();
    }

    // =========================================================
    // READ
    // =========================================================

    public function test_index_returns_view(): void
    {
        $this->get(route('types.index'))
            ->assertOk()
            ->assertViewIs('setting.inventory.types.index');
    }

    public function test_list_returns_json_contract_with_prefix_search(): void
    {
        Type::create(['name' => 'مصنف', 'is_active' => 1]);
        Type::create(['name' => 'معدن', 'is_active' => 1]);
        Type::create(['name' => 'خشب', 'is_active' => 1]);

        // بحث Prefix: "م" يطابق "مصنف" و"معدن" فقط
        $response = $this->getJson(route('types.list', ['search' => 'م']));

        $response->assertOk()->assertJsonPath('success', true);

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertEqualsCanonicalizing(['مصنف', 'معدن'], $names);

        // Prefix وليس Contains: "صنف" وسط الاسم ⇒ لا نتيجة
        $this->getJson(route('types.list', ['search' => 'صنف']))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data');

        // بنية meta للترقيم
        $this->getJson(route('types.list'))
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_list_full_returns_all_rows_without_meta(): void
    {
        Type::create(['name' => 'أول']);
        Type::create(['name' => 'ثاني']);

        $response = $this->getJson(route('types.list', ['full' => 1]));

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertCount(2, $response->json('data'));
        $this->assertArrayNotHasKey('meta', $response->json());
    }

    // =========================================================
    // CREATE
    // =========================================================

    public function test_store_creates_type_with_defaults(): void
    {
        $response = $this->postJson(route('types.store'), [
            'name' => 'نوع جديد',
            'code' => 'T9',
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('type', [
            'name' => 'نوع جديد',
            'code' => 'T9',
            'is_active' => 1,
        ]);
    }

    public function test_store_duplicate_name_returns_422_arabic_message(): void
    {
        Type::create(['name' => 'مكرر']);

        $response = $this->postJson(route('types.store'), ['name' => 'مكرر']);

        $response->assertStatus(422)->assertJsonPath('success', false);
        $this->assertStringContainsString(
            'هذا النوع موجود بالفعل',
            (string) $response->json('message')
        );
    }

    // =========================================================
    // UPDATE
    // =========================================================

    public function test_update_renames_and_allows_own_name_in_unique(): void
    {
        $type = Type::create(['name' => 'الأول']);

        // إرسال الاسم نفسه لذاته ⇒ ينجح (ignore self)
        $this->putJson(route('types.update', ['type' => $type->id]), [
            'name' => 'الأول',
        ])->assertOk()->assertJsonPath('success', true);

        // تغيير الاسم
        $this->putJson(route('types.update', ['type' => $type->id]), [
            'name' => 'الثاني',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('type', ['id' => $type->id, 'name' => 'الثاني']);
    }

    public function test_update_cannot_take_another_type_name(): void
    {
        Type::create(['name' => 'مشغول']);
        $other = Type::create(['name' => 'حر']);

        $response = $this->putJson(route('types.update', ['type' => $other->id]), [
            'name' => 'مشغول',
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    // =========================================================
    // TOGGLE
    // =========================================================

    public function test_toggle_status_flips_and_returns(): void
    {
        $type = Type::create(['name' => 'حالة', 'is_active' => 1]);

        $this->patchJson(route('types.toggleStatus', ['type' => $type->id]))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('type', ['id' => $type->id, 'is_active' => 0]);

        $this->patchJson(route('types.toggleStatus', ['type' => $type->id]))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('type', ['id' => $type->id, 'is_active' => 1]);
    }

    // =========================================================
    // DELETE
    // =========================================================

    public function test_delete_unused_type_succeeds(): void
    {
        $type = Type::create(['name' => 'حذفني']);

        $this->deleteJson(route('types.destroy', ['type' => $type->id]))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('type', ['id' => $type->id]);
    }

    public function test_delete_used_type_returns_422_with_arabic_message(): void
    {
        $type = Type::create(['name' => 'مستخدم']);

        // صف تفاصيل يتيم يستخدم النوع — مع تعطيل فحوصات FK مؤقتاً
        // (فحص الخدمة يعتمد على وجود type_id فقط)
        DB::statement('SET foreign_key_checks = 0');
        DB::table('purchase_invoice_details')->insert([
            'purchase_invoice_id' => 999999,
            'type_id' => $type->id,
        ]);
        DB::statement('SET foreign_key_checks = 1');

        $response = $this->deleteJson(route('types.destroy', ['type' => $type->id]));

        $response->assertStatus(422)->assertJsonPath('success', false);
        $this->assertStringContainsString(
            'مستخدم في فواتير الشراء',
            (string) $response->json('message')
        );

        // النوع لم يُحذف
        $this->assertDatabaseHas('type', ['id' => $type->id]);
    }

    public function test_update_missing_type_returns_422_current_contract(): void
    {
        // العقد الحالي: ModelNotFoundException تندرج تحت RuntimeException
        // ⇒ 422 عبر fail() — يُوثَّق كسلوك قائم (راجع T9 في تقرير الوحدة)
        $this->putJson(route('types.update', ['type' => 999999]), ['name' => 'أي'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    // =========================================================
    // حقول التتبع (تصحيح T1)
    // =========================================================

    public function test_fillable_includes_tracking_fields(): void
    {
        $type = Type::create([
            'name' => 'تتبع',
            'created_by' => 7,
            'updated_by' => 9,
        ]);

        $type->refresh();

        $this->assertSame(7, (int) $type->created_by);
        $this->assertSame(9, (int) $type->updated_by);
    }
}

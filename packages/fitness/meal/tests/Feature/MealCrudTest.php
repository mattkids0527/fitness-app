<?php

namespace Fitness\Meal\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Fitness\Meal\Models\Meal;
use Fitness\Meal\Models\MealItem;
use Fitness\Meal\Models\SavedFood;
use Fitness\Meal\Tests\Concerns\CreatesMeals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 餐點的新增、修改、刪除、驗證與權限。
 */
class MealCrudTest extends TestCase
{
    use CreatesMeals, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-20 12:40:00', 'Asia/Taipei'));
    }

    /**
     * 一份合法的餐點表單，可以覆寫部分欄位。
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return [
            'date' => '2026-11-20',
            'time' => '07:50',
            'meal_type' => 'breakfast',
            'note' => '外食',
            'items' => [
                $this->food('蛋餅', 380, 18, 42, 15, 1.5, '1 份'),
                $this->food('豆漿', 132, 13, 4, 7, null, '1 杯', 400),
            ],
            ...$overrides,
        ];
    }

    public function test_store_creates_meal_with_items(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('fitness.meal.store'), $this->payload(['date' => '2026-11-19', 'time' => '23:30', 'meal_type' => 'snack']))
            ->assertRedirect(route('fitness.meal.index', ['date' => '2026-11-19']))
            ->assertSessionHas('success', '已儲存點心');

        $meal = Meal::with('items')->sole();
        $this->assertSame($user->id, $meal->user_id);
        $this->assertTrue($meal->eaten_at->equalTo(CarbonImmutable::parse('2026-11-19 23:30', 'Asia/Taipei')));
        $this->assertSame('manual', $meal->source);
        $this->assertTrue($meal->isConfirmed());
        $this->assertSame('外食', $meal->note);
        $this->assertSame(['蛋餅', '豆漿'], $meal->items->pluck('name')->all());
        $this->assertSame([0, 1], $meal->items->pluck('position')->all());
        $this->assertSame(400.0, $meal->items[1]->grams);
        $this->assertNull($meal->items[1]->fiber_g);
    }

    public function test_store_validation(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('fitness.meal.store'), $this->payload(['date' => '2026-11-21', 'time' => '25:00', 'meal_type' => 'brunch']))
            ->assertSessionHasErrors(['date', 'time', 'meal_type']);

        $this->post(route('fitness.meal.store'), $this->payload(['items' => []]))
            ->assertSessionHasErrors(['items' => '至少要有一個項目。']);

        $this->post(route('fitness.meal.store'), $this->payload(['items' => [['name' => '', 'kcal' => '']]]))
            ->assertSessionHasErrors(['items.0.name' => '名稱為必填。', 'items.0.kcal' => '熱量為必填。']);

        $this->post(route('fitness.meal.store'), $this->payload(['items' => [[
            ...$this->food(str_repeat('名', 101), 5001, 1001, -1, 0, 0, str_repeat('份', 51), 5001),
        ]]]))->assertSessionHasErrors(['items.0.name', 'items.0.kcal', 'items.0.protein_g', 'items.0.carbs_g', 'items.0.portion', 'items.0.grams']);

        $this->post(route('fitness.meal.store'), $this->payload(['note' => str_repeat('註', 501)]))->assertSessionHasErrors('note');

        $this->post(route('fitness.meal.store'), $this->payload(['items' => array_fill(0, 31, $this->food('項目', 10))]))
            ->assertSessionHasErrors('items');

        $this->assertSame(0, Meal::count());

        $this->post(route('fitness.meal.store'), $this->payload(['items' => array_fill(0, 30, $this->food('項目', 10))]))
            ->assertSessionHasNoErrors();
    }

    public function test_store_touches_only_own_saved_foods(): void
    {
        $user = User::factory()->create();
        $own = $this->savedFood($user, '茶葉蛋', 75);
        $others = $this->savedFood(User::factory()->create(), '別人的蛋', 75);

        $this->actingAs($user)->post(route('fitness.meal.store'), $this->payload(['items' => [
            [...$this->food('茶葉蛋', 150), 'saved_food_id' => $own->id],
            [...$this->food('別人的蛋', 75), 'saved_food_id' => $others->id],
        ]]))->assertSessionHasNoErrors();

        $this->assertTrue($own->fresh()->last_used_at->equalTo(CarbonImmutable::now()));
        $this->assertNull($others->fresh()->last_used_at);
    }

    public function test_update_replaces_items(): void
    {
        $user = User::factory()->create();
        $meal = $this->meal($user, '2026-11-20', '07:50', 'breakfast', [$this->food('蛋餅', 380), $this->food('豆漿', 132)]);
        $oldItemIds = $meal->items->pluck('id')->all();

        $this->actingAs($user)
            ->put(route('fitness.meal.update', $meal->id), $this->payload([
                'date' => '2026-11-18',
                'time' => '08:10',
                'meal_type' => 'snack',
                'note' => null,
                'items' => [$this->food('燕麥', 300, 10, 50, 6, 8)],
            ]))
            ->assertRedirect(route('fitness.meal.index', ['date' => '2026-11-18']))
            ->assertSessionHas('success', '已儲存點心');

        $meal->refresh()->load('items');
        $this->assertSame('snack', $meal->meal_type->value);
        $this->assertNull($meal->note);
        $this->assertSame('2026-11-18 08:10', $meal->eaten_at->setTimezone('Asia/Taipei')->format('Y-m-d H:i'));
        $this->assertSame(['燕麥'], $meal->items->pluck('name')->all());
        $this->assertSame(0, MealItem::whereIn('id', $oldItemIds)->count());
    }

    public function test_destroy_deletes_meal_and_items(): void
    {
        $user = User::factory()->create();
        $meal = $this->meal($user, '2026-11-19', '12:30', 'lunch', [$this->food('牛肉麵', 620)]);

        $this->actingAs($user)
            ->delete(route('fitness.meal.destroy', $meal->id))
            ->assertRedirect(route('fitness.meal.index', ['date' => '2026-11-19']))
            ->assertSessionHas('success', '已刪除午餐');

        $this->assertSame(0, Meal::count());
        $this->assertSame(0, MealItem::count());
    }

    public function test_other_users_meal_returns_404(): void
    {
        $meal = $this->meal(User::factory()->create(), '2026-11-20', '07:50', 'breakfast', [$this->food('蛋餅', 380)]);

        $this->actingAs(User::factory()->create());

        $this->get(route('fitness.meal.edit', $meal->id))->assertNotFound();
        $this->put(route('fitness.meal.update', $meal->id), $this->payload())->assertNotFound();
        $this->delete(route('fitness.meal.destroy', $meal->id))->assertNotFound();

        $this->assertSame(['蛋餅'], $meal->fresh()->items->pluck('name')->all());
    }

    public function test_deleting_user_cascades_meal_data(): void
    {
        $user = User::factory()->create();
        $this->meal($user, '2026-11-20', '07:50', 'breakfast', [$this->food('蛋餅', 380)]);
        $this->savedFood($user, '茶葉蛋');
        $other = User::factory()->create();
        $this->meal($other, '2026-11-20', '07:50', 'breakfast', [$this->food('別人的', 100)]);
        $this->savedFood($other, '別人的常用');

        $user->delete();

        $this->assertSame([$other->id], Meal::pluck('user_id')->all());
        $this->assertSame(1, MealItem::count());
        $this->assertSame([$other->id], SavedFood::pluck('user_id')->all());
    }
}

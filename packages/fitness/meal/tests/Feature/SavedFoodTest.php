<?php

namespace Fitness\Meal\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Fitness\Meal\Models\SavedFood;
use Fitness\Meal\Tests\Concerns\CreatesMeals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * 常用食物的列表、新增、修改、刪除、同名規則與權限。
 */
class SavedFoodTest extends TestCase
{
    use CreatesMeals, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-20 12:40:00', 'Asia/Taipei'));
    }

    public function test_saved_food_crud(): void
    {
        $user = User::factory()->create();
        $this->savedFood($user, '香蕉', 105, '2026-11-18 07:40');
        $this->savedFood($user, '茶葉蛋', 75, '2026-11-20 07:50');
        $this->savedFood($user, '雞胸肉', 165);
        $this->savedFood(User::factory()->create(), '別人的', 100);

        $this->actingAs($user);

        $this->get(route('fitness.meal.saved_foods.index'))->assertInertia(fn (Assert $page) => $page
            ->component('meal/SavedFoods')
            ->where('today', '2026-11-20')
            ->has('saved_foods', 3)
            ->where('saved_foods.0.name', '茶葉蛋')
            ->where('saved_foods.0.last_used_on', '2026-11-20')
            ->where('saved_foods.1.name', '香蕉')
            ->where('saved_foods.2.name', '雞胸肉')
            ->where('saved_foods.2.last_used_on', null)
        );

        $this->post(route('fitness.meal.saved_foods.store'), $this->food('希臘優格', 146, 15, null, null, null, '1 杯', 150))
            ->assertSessionHas('success', '已把「希臘優格」加入常用食物');
        $food = SavedFood::where('name', '希臘優格')->sole();
        $this->assertSame($user->id, $food->user_id);
        $this->assertSame(15.0, $food->protein_g);
        $this->assertNull($food->carbs_g);

        $this->put(route('fitness.meal.saved_foods.update', $food->id), $this->food('希臘優格', 150, 15))
            ->assertSessionHas('success', '已儲存「希臘優格」');
        $this->assertSame(150.0, $food->fresh()->kcal);

        $this->post(route('fitness.meal.saved_foods.store'), ['name' => '', 'kcal' => ''])->assertSessionHasErrors(['name', 'kcal']);

        $this->delete(route('fitness.meal.saved_foods.destroy', $food->id))->assertSessionHas('success', '已刪除「希臘優格」');
        $this->assertNull($food->fresh());
    }

    public function test_saved_food_name_unique_case_insensitive(): void
    {
        $user = User::factory()->create();
        $this->savedFood($user, 'Egg');
        $tea = $this->savedFood($user, '茶葉蛋');

        $this->actingAs($user);

        $this->post(route('fitness.meal.saved_foods.store'), $this->food(' egg ', 70))
            ->assertSessionHasErrors(['name' => '常用食物裡已經有同名的項目。']);
        $this->put(route('fitness.meal.saved_foods.update', $tea->id), $this->food('EGG', 70))
            ->assertSessionHasErrors(['name' => '常用食物裡已經有同名的項目。']);
        $this->assertSame('茶葉蛋', $tea->fresh()->name);

        $this->put(route('fitness.meal.saved_foods.update', $tea->id), $this->food('茶葉蛋', 80))->assertSessionHasNoErrors();

        $this->actingAs(User::factory()->create())
            ->post(route('fitness.meal.saved_foods.store'), $this->food('Egg', 70))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, SavedFood::whereRaw('lower(name) = ?', ['egg'])->count());
    }

    public function test_other_users_saved_food_returns_404(): void
    {
        $food = $this->savedFood(User::factory()->create(), '茶葉蛋', 75);

        $this->actingAs(User::factory()->create());

        $this->put(route('fitness.meal.saved_foods.update', $food->id), $this->food('改掉', 1))->assertNotFound();
        $this->delete(route('fitness.meal.saved_foods.destroy', $food->id))->assertNotFound();

        $this->assertSame('茶葉蛋', $food->fresh()->name);
    }
}

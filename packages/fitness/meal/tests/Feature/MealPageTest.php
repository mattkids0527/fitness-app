<?php

namespace Fitness\Meal\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Fitness\Meal\Tests\Concerns\CreatesMeals;
use Fitness\Profile\Models\WeightLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * 飲食頁、新增／編輯頁的資料：切日、加總、預設值、快速帶入清單、今日頁 meal 區塊。
 */
class MealPageTest extends TestCase
{
    use CreatesMeals, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-20 12:40:00', 'Asia/Taipei'));
    }

    public function test_index_requires_login(): void
    {
        $this->get(route('fitness.meal.index'))->assertRedirect(route('login'));
    }

    public function test_index_shows_day_meals_and_totals(): void
    {
        $user = User::factory()->create();
        WeightLog::create(['user_id' => $user->id, 'weight_kg' => 70.4, 'measured_on' => '2026-11-20']);
        $this->meal($user, '2026-11-20', '12:20', 'lunch', [$this->food('雞胸肉便當', 650, 45, 78, 16, 5)]);
        $this->meal($user, '2026-11-20', '07:50', 'breakfast', [$this->food('蛋餅', 380, 18, 42, 15, 1.5), $this->food('豆漿', 132, 13, 4, 7)]);
        $this->meal($user, '2026-11-19', '18:00', 'dinner', [$this->food('昨天的晚餐', 999, 1, 1, 1)]);
        $this->meal(User::factory()->create(), '2026-11-20', '08:00', 'breakfast', [$this->food('別人的早餐', 500, 1, 1, 1)]);

        $this->actingAs($user)->get(route('fitness.meal.index'))->assertInertia(fn (Assert $page) => $page
            ->component('meal/Index')
            ->where('date', '2026-11-20')
            ->where('today', '2026-11-20')
            ->has('meals', 2)
            ->where('meals.0.time', '07:50')
            ->where('meals.0.meal_type', 'breakfast')
            ->where('meals.0.item_names', ['蛋餅', '豆漿'])
            ->where('meals.0.kcal', 512)
            ->where('meals.1.meal_type', 'lunch')
            ->where('totals', $this->asJson(['kcal' => 1162.0, 'protein_g' => 76.0, 'carbs_g' => 124.0, 'fat_g' => 38.0, 'fiber_g' => 6.5]))
            ->where('has_missing_nutrients', false)
            ->where('calorie_target', fn ($target) => is_int($target) && $target > 0)
            ->has('meal_types', 4)
        );

        $this->meal($user, '2026-11-20', '10:30', 'snack', [$this->food('珍珠奶茶', 450)]);

        $this->get(route('fitness.meal.index'))->assertInertia(fn (Assert $page) => $page
            ->where('totals.kcal', 1612)
            ->where('has_missing_nutrients', true)
        );
    }

    public function test_index_day_boundary_follows_user_timezone(): void
    {
        $user = User::factory()->create();
        $this->meal($user, '2026-11-19', '23:30', 'snack', [$this->food('宵夜', 450, 9, 60, 18)]);
        $this->meal($user, '2026-11-20', '00:10', 'snack', [$this->food('凌晨點心', 100, 1, 1, 1)]);

        $this->actingAs($user);

        $this->get(route('fitness.meal.index', ['date' => '2026-11-19']))->assertInertia(fn (Assert $page) => $page
            ->where('date', '2026-11-19')
            ->has('meals', 1)
            ->where('meals.0.time', '23:30')
            ->where('meals.0.date', '2026-11-19')
            ->where('calorie_target', null)
        );

        $this->get(route('fitness.meal.index'))->assertInertia(fn (Assert $page) => $page
            ->has('meals', 1)
            ->where('meals.0.time', '00:10')
        );
    }

    public function test_index_invalid_or_future_date_falls_back_to_today(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['2026-11-21', '2026-02-31', 'yesterday', '20261119'] as $date) {
            $this->get(route('fitness.meal.index', ['date' => $date]))->assertInertia(fn (Assert $page) => $page->where('date', '2026-11-20'));
        }
    }

    public function test_only_confirmed_meals_are_counted(): void
    {
        $user = User::factory()->create();
        $this->meal($user, '2026-11-20', '08:00', 'breakfast', [$this->food('已確認', 300, 10, 10, 10)]);
        $this->meal($user, '2026-11-20', '12:00', 'lunch', [$this->food('分析中', 800, 10, 10, 10)], 'draft');

        $this->actingAs($user);

        $this->get(route('fitness.meal.index'))->assertInertia(fn (Assert $page) => $page
            ->has('meals', 1)
            ->where('totals.kcal', 300)
        );

        $this->get(route('fitness.meal.create'))->assertInertia(fn (Assert $page) => $page
            ->has('recent_foods', 1)
            ->where('recent_foods.0.name', '已確認')
            ->has('copyable_meals', 1)
        );

        $this->get(route('fitness.daily_summary.show'))->assertInertia(fn (Assert $page) => $page
            ->has('sections.meal.meals', 1)
            ->where('sections.meal.totals.kcal', 300)
        );
    }

    public function test_create_defaults(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('fitness.meal.create'))->assertInertia(fn (Assert $page) => $page
            ->component('meal/Form')
            ->where('meal', null)
            ->where('defaults.date', '2026-11-20')
            ->where('defaults.time', '12:40')
            ->where('defaults.meal_type', 'lunch')
            ->where('defaults.type_chosen', false)
            ->where('defaults.items', [])
            ->where('defaults.copied_from', null)
            ->where('limits', ['max_items' => 30, 'servings' => [0.5, 1, 1.5, 2]])
        );

        $this->get(route('fitness.meal.create', ['date' => '2026-11-18', 'type' => 'dinner']))->assertInertia(fn (Assert $page) => $page
            ->where('defaults.date', '2026-11-18')
            ->where('defaults.time', '18:30')
            ->where('defaults.meal_type', 'dinner')
            ->where('defaults.type_chosen', true)
        );

        $this->get(route('fitness.meal.create', ['date' => '2026-11-18']))->assertInertia(fn (Assert $page) => $page
            ->where('defaults.time', '12:30')
            ->where('defaults.meal_type', 'lunch')
            ->where('defaults.type_chosen', false)
        );

        $this->get(route('fitness.meal.create', ['date' => '2026-12-01', 'type' => 'brunch']))->assertInertia(fn (Assert $page) => $page
            ->where('defaults.date', '2026-11-20')
            ->where('defaults.time', '12:40')
            ->where('defaults.meal_type', 'lunch')
            ->where('defaults.type_chosen', false)
        );
    }

    public function test_create_copy_from(): void
    {
        $user = User::factory()->create();
        $source = $this->meal($user, '2026-11-18', '08:05', 'breakfast', [$this->food('蛋餅', 380, 18, 42, 15, 1.5, '1 份'), $this->food('豆漿', 132)]);
        $others = $this->meal(User::factory()->create(), '2026-11-19', '08:00', 'breakfast', [$this->food('別人的', 100)]);

        $this->actingAs($user);

        $this->get(route('fitness.meal.create', ['copy_from' => $source->id, 'date' => '2026-11-10', 'type' => 'dinner']))->assertInertia(fn (Assert $page) => $page
            ->where('defaults.date', '2026-11-20')
            ->where('defaults.time', '12:40')
            ->where('defaults.meal_type', 'breakfast')
            ->where('defaults.type_chosen', true)
            ->has('defaults.items', 2)
            ->where('defaults.items.0', $this->asJson($this->food('蛋餅', 380, 18, 42, 15, 1.5, '1 份')))
            ->where('defaults.copied_from.id', $source->id)
            ->where('defaults.copied_from.date', '2026-11-18')
        );

        $this->get(route('fitness.meal.create', ['copy_from' => $others->id]))->assertNotFound();
    }

    public function test_edit_excludes_itself_from_copyable_meals(): void
    {
        $user = User::factory()->create();
        $editing = $this->meal($user, '2026-11-20', '07:50', 'breakfast', [$this->food('蛋餅', 380), $this->food('豆漿', 132)]);
        $other = $this->meal($user, '2026-11-19', '12:30', 'lunch', [$this->food('牛肉麵', 620)]);

        $this->actingAs($user)->get(route('fitness.meal.edit', $editing->id))->assertInertia(fn (Assert $page) => $page
            ->component('meal/Form')
            ->where('defaults', null)
            ->where('meal.id', $editing->id)
            ->where('meal.date', '2026-11-20')
            ->where('meal.time', '07:50')
            ->where('meal.meal_type', 'breakfast')
            ->where('meal.items.1.name', '豆漿')
            ->has('copyable_meals', 1)
            ->where('copyable_meals.0.id', $other->id)
        );
    }

    public function test_recent_foods_rules(): void
    {
        $user = User::factory()->create();
        $this->meal($user, '2026-10-21', '12:00', 'lunch', [$this->food('超過 30 天', 100)]);
        $this->meal($user, '2026-10-22', '12:00', 'lunch', [$this->food('剛好 30 天內', 100)]);
        $this->meal($user, '2026-11-18', '08:00', 'breakfast', [$this->food('Egg', 70, null, null, null, null, '1 顆')]);
        $this->meal($user, '2026-11-20', '08:00', 'breakfast', [$this->food('egg', 140, null, null, null, null, '2 顆')]);
        $this->meal(User::factory()->create(), '2026-11-20', '09:00', 'breakfast', [$this->food('別人的', 100)]);

        $this->actingAs($user);

        $this->get(route('fitness.meal.create'))->assertInertia(fn (Assert $page) => $page
            ->has('recent_foods', 2)
            ->where('recent_foods.0.name', 'egg')
            ->where('recent_foods.0.portion', '2 顆')
            ->where('recent_foods.0.last_eaten_on', '2026-11-20')
            ->where('recent_foods.1.name', '剛好 30 天內')
        );

        foreach (range(1, 25) as $index) {
            $this->meal($user, '2026-11-19', sprintf('%02d:%02d', intdiv($index, 60), $index % 60), 'snack', [$this->food("食物 {$index}", 10)]);
        }

        $this->get(route('fitness.meal.create'))->assertInertia(fn (Assert $page) => $page
            ->has('recent_foods', 20)
            ->where('recent_foods.0.name', 'egg')
            ->where('recent_foods.1.name', '食物 25')
        );
    }

    public function test_copyable_meals_within_14_days(): void
    {
        $user = User::factory()->create();
        $this->meal($user, '2026-11-06', '12:00', 'lunch', [$this->food('超過 14 天', 100)]);
        $oldest = $this->meal($user, '2026-11-07', '12:00', 'lunch', [$this->food('剛好 14 天內', 100)]);
        $newest = $this->meal($user, '2026-11-20', '08:00', 'breakfast', [$this->food('今天', 100)]);

        $this->actingAs($user)->get(route('fitness.meal.create'))->assertInertia(fn (Assert $page) => $page
            ->has('copyable_meals', 2)
            ->where('copyable_meals.0.id', $newest->id)
            ->where('copyable_meals.1.id', $oldest->id)
            ->where('copyable_meals.1.items.0.name', '剛好 14 天內')
        );
    }

    public function test_daily_summary_includes_meal_section(): void
    {
        $user = User::factory()->create();
        $this->meal($user, '2026-11-20', '12:20', 'lunch', [$this->food('便當', 650, 45, 78, 16, 5)]);
        $this->meal($user, '2026-11-20', '07:50', 'breakfast', [$this->food('奶茶', 300)]);

        $this->actingAs($user)->get(route('fitness.daily_summary.show'))->assertInertia(fn (Assert $page) => $page
            ->where('sections.meal.totals', $this->asJson(['kcal' => 950.0, 'protein_g' => 45.0, 'carbs_g' => 78.0, 'fat_g' => 16.0, 'fiber_g' => 5.0]))
            ->where('sections.meal.has_missing_nutrients', true)
            ->has('sections.meal.meals', 2)
            ->where('sections.meal.meals.0.time', '07:50')
            ->where('sections.meal.meals.1.item_names', ['便當'])
            ->has('sections.meal.meal_types', 4)
            ->where('sections.meal.meal_types.0.label', '早餐')
        );
    }
}

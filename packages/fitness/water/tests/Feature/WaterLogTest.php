<?php

namespace Fitness\Water\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Fitness\Profile\Models\WeightLog;
use Fitness\Water\Models\WaterLog;
use Fitness\Water\Services\WaterTargetCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * 喝水紀錄的新增、刪除、切日、建議量、共享資料與今日頁 water 區塊。
 */
class WaterLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-20 12:40:00', 'Asia/Taipei'));
    }

    /**
     * 直接寫入一筆喝水紀錄（時間以 Asia/Taipei 解讀）。
     */
    protected function log(User $user, int $amountMl, string $drankAt): WaterLog
    {
        return WaterLog::create(['user_id' => $user->id, 'amount_ml' => $amountMl, 'drank_at' => CarbonImmutable::parse($drankAt, 'Asia/Taipei')]);
    }

    public function test_store_records_now(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('fitness.meal.index'))
            ->post(route('fitness.water.store'), ['amount_ml' => 1500])
            ->assertRedirect(route('fitness.meal.index'))
            ->assertSessionHas('success', '已記錄 1,500 ml');

        $log = WaterLog::sole();
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame(1500, $log->amount_ml);
        $this->assertTrue($log->drank_at->equalTo(CarbonImmutable::now()));
    }

    public function test_store_validation(): void
    {
        $this->actingAs(User::factory()->create());

        foreach ([null, 9, 2001, 250.5, 'abc'] as $amount) {
            $this->post(route('fitness.water.store'), ['amount_ml' => $amount])->assertSessionHasErrors('amount_ml');
        }

        $this->assertSame(0, WaterLog::count());

        $this->post(route('fitness.water.store'), ['amount_ml' => 10])->assertSessionHasNoErrors();
        $this->post(route('fitness.water.store'), ['amount_ml' => 2000])->assertSessionHasNoErrors();
        $this->assertSame(2, WaterLog::count());
    }

    public function test_today_excludes_other_days(): void
    {
        $user = User::factory()->create();
        $this->log($user, 999, '2026-11-19 23:30');
        $this->log($user, 250, '2026-11-20 00:05');
        $this->log($user, 500, '2026-11-20 12:00');
        $this->log(User::factory()->create(), 700, '2026-11-20 09:00');

        $this->actingAs($user)->get(route('fitness.daily_summary.show'))->assertInertia(fn (Assert $page) => $page
            ->where('sections.water.total_ml', 750)
            ->has('sections.water.logs', 2)
        );
    }

    public function test_destroy_only_own_today(): void
    {
        $user = User::factory()->create();
        $yesterday = $this->log($user, 999, '2026-11-19 23:30');
        $today = $this->log($user, 250, '2026-11-20 08:00');
        $others = $this->log(User::factory()->create(), 700, '2026-11-20 09:00');

        $this->actingAs($user);

        $this->delete(route('fitness.water.destroy', $yesterday->id))->assertNotFound();
        $this->delete(route('fitness.water.destroy', $others->id))->assertNotFound();
        $this->delete(route('fitness.water.destroy', $today->id))->assertSessionHas('success', '已刪除這筆紀錄');

        $this->assertEqualsCanonicalizing([$yesterday->id, $others->id], WaterLog::pluck('id')->all());
    }

    public function test_target_calculation(): void
    {
        $calculator = app(WaterTargetCalculator::class);
        $this->assertSame(2750, $calculator->calculate(78.5));
        $this->assertSame(2650, $calculator->calculate(75));
        $this->assertSame(2100, $calculator->calculate(60));

        $user = User::factory()->create();
        $this->assertNull($calculator->forUser($user));

        WeightLog::create(['user_id' => $user->id, 'weight_kg' => 80, 'measured_on' => '2026-11-10']);
        WeightLog::create(['user_id' => $user->id, 'weight_kg' => 78.5, 'measured_on' => '2026-11-19']);
        $this->assertSame(['target_ml' => 2750, 'weight_kg' => 78.5, 'ml_per_kg' => 35], $calculator->forUser($user));
    }

    public function test_shared_water_prop_is_optional(): void
    {
        $user = User::factory()->create();
        $this->log($user, 250, '2026-11-20 08:00');

        $this->actingAs($user);
        $page = $this->get(route('fitness.meal.index'))->viewData('page');
        $this->assertArrayNotHasKey('water', $page['props']);

        $this->get(route('fitness.meal.index'), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $page['version'],
            'X-Inertia-Partial-Component' => 'meal/Index',
            'X-Inertia-Partial-Data' => 'water',
        ])
            ->assertOk()
            ->assertJsonPath('props.water.total_ml', 250)
            ->assertJsonMissingPath('props.meals');
    }

    public function test_daily_summary_includes_water_section(): void
    {
        $user = User::factory()->create();
        WeightLog::create(['user_id' => $user->id, 'weight_kg' => 78.5, 'measured_on' => '2026-11-20']);
        $first = $this->log($user, 500, '2026-11-20 08:10');
        $second = $this->log($user, 250, '2026-11-20 10:30');

        $this->actingAs($user)->get(route('fitness.daily_summary.show'))->assertInertia(fn (Assert $page) => $page
            ->where('sections.water', [
                'total_ml' => 750,
                'target_ml' => 2750,
                'weight_kg' => 78.5,
                'ml_per_kg' => 35,
                'logs' => [
                    ['id' => $second->id, 'amount_ml' => 250, 'time' => '10:30'],
                    ['id' => $first->id, 'amount_ml' => 500, 'time' => '08:10'],
                ],
                'quick_amounts' => [250, 500],
                'min_ml' => 10,
                'max_ml' => 2000,
            ])
        );
    }

    public function test_deleting_user_cascades_water_logs(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->log($user, 250, '2026-11-20 08:00');
        $this->log($other, 500, '2026-11-20 08:00');

        $user->delete();

        $this->assertSame([$other->id], WaterLog::pluck('user_id')->all());
    }
}

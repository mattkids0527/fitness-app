<?php

namespace Fitness\Profile\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Fitness\Profile\Models\WeightLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * 體重紀錄的列表、新增、刪除與權限。
 */
class WeightLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-19 10:00:00', 'Asia/Taipei'));
    }

    /**
     * 新增一筆體重紀錄。
     */
    protected function log(User $user, float $kg, string $date): WeightLog
    {
        return WeightLog::create(['user_id' => $user->id, 'weight_kg' => $kg, 'measured_on' => $date]);
    }

    public function test_weight_logs_are_listed_newest_first_with_deltas(): void
    {
        $user = User::factory()->create();
        $this->log($user, 80.0, '2026-11-10');
        $this->log($user, 79.5, '2026-11-18');
        $this->log($user, 79.8, '2026-11-12');
        $this->log($user, 79.2, '2026-11-18');
        $this->log(User::factory()->create(), 60.0, '2026-11-19');

        $this->actingAs($user)->get(route('fitness.profile.weight_logs.index'))->assertInertia(fn (Assert $page) => $page
            ->component('profile/WeightLogs')
            ->where('history.total', 4)
            ->where('history.has_more', false)
            ->has('history.items', 4)
            ->where('history.items.0.weight_kg', 79.2)
            ->where('history.items.0.delta_kg', -0.3)
            ->where('history.items.1.weight_kg', 79.5)
            ->where('history.items.1.delta_kg', -0.3)
            ->where('history.items.2.measured_on', '2026-11-12')
            ->where('history.items.2.delta_kg', -0.2)
            ->where('history.items.3.delta_kg', null)
            ->where('today', '2026-11-19')
        );
    }

    public function test_weight_logs_load_more_by_page(): void
    {
        config(['fitness.profile.weight_logs_per_page' => 2]);
        $user = User::factory()->create();

        foreach (range(1, 5) as $day) {
            $this->log($user, 70 + $day / 10, sprintf('2026-11-%02d', $day));
        }

        $this->actingAs($user);

        $this->get(route('fitness.profile.weight_logs.index'))->assertInertia(fn (Assert $page) => $page
            ->has('history.items', 2)
            ->where('history.has_more', true)
            ->where('history.items.1.delta_kg', 0.1)
            ->where('page', 1)
        );

        $this->get(route('fitness.profile.weight_logs.index', ['page' => 3]))->assertInertia(fn (Assert $page) => $page
            ->has('history.items', 5)
            ->where('history.has_more', false)
            ->where('page', 3)
        );
    }

    public function test_weight_log_can_be_recorded_and_defaults_to_today(): void
    {
        $user = User::factory()->create(['timezone' => 'Asia/Taipei']);
        $this->travelTo(CarbonImmutable::parse('2026-11-19 16:30:00', 'UTC'));

        $this->actingAs($user)->post(route('fitness.profile.weight_logs.store'), ['weight_kg' => 72.3])->assertRedirect();
        $this->post(route('fitness.profile.weight_logs.store'), ['weight_kg' => 72.0, 'measured_on' => '2026-11-15'])->assertRedirect();

        $this->assertDatabaseHas('weight_logs', ['user_id' => $user->id, 'weight_kg' => 72.3, 'measured_on' => '2026-11-20']);
        $this->assertDatabaseHas('weight_logs', ['user_id' => $user->id, 'weight_kg' => 72.0, 'measured_on' => '2026-11-15']);
    }

    public function test_weight_log_rejects_future_date_and_out_of_range_weight(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('fitness.profile.weight_logs.store'), ['weight_kg' => 19.9])->assertSessionHasErrors('weight_kg');
        $this->post(route('fitness.profile.weight_logs.store'), ['weight_kg' => 300.1])->assertSessionHasErrors('weight_kg');
        $this->post(route('fitness.profile.weight_logs.store'), ['weight_kg' => 70, 'measured_on' => '2026-11-20'])->assertSessionHasErrors('measured_on');

        $this->assertSame(0, WeightLog::count());
    }

    public function test_user_can_delete_own_weight_log(): void
    {
        $user = User::factory()->create();
        $this->log($user, 70.0, '2026-11-17');
        $latest = $this->log($user, 69.8, '2026-11-18');

        $this->actingAs($user)->delete(route('fitness.profile.weight_logs.destroy', $latest->id))->assertRedirect();

        $this->assertModelMissing($latest);
        $this->assertSame(1, WeightLog::where('user_id', $user->id)->count());
    }

    public function test_last_weight_log_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $only = $this->log($user, 70.0, '2026-11-17');

        $this->actingAs($user)
            ->from(route('fitness.profile.weight_logs.index'))
            ->delete(route('fitness.profile.weight_logs.destroy', $only->id))
            ->assertRedirect(route('fitness.profile.weight_logs.index'))
            ->assertSessionHasErrors(['weight_log' => '至少需保留一筆體重紀錄，才能計算基礎代謝與熱量目標。']);

        $this->assertModelExists($only);
    }

    public function test_user_cannot_delete_other_users_weight_log(): void
    {
        $owner = User::factory()->create();
        $this->log($owner, 70.0, '2026-11-17');
        $target = $this->log($owner, 69.8, '2026-11-18');

        $this->actingAs(User::factory()->create())
            ->delete(route('fitness.profile.weight_logs.destroy', $target->id))
            ->assertNotFound();

        $this->assertModelExists($target);
    }

    public function test_guests_are_redirected_from_profile_routes(): void
    {
        $this->get(route('fitness.profile.show'))->assertRedirect(route('login'));
        $this->get(route('fitness.profile.edit'))->assertRedirect(route('login'));
        $this->get(route('fitness.profile.weight_logs.index'))->assertRedirect(route('login'));
        $this->post(route('fitness.profile.weight_logs.store'), ['weight_kg' => 70])->assertRedirect(route('login'));
        $this->get(route('fitness.profile.onboarding.show'))->assertRedirect(route('login'));
    }
}

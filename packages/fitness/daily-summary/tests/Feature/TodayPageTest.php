<?php

namespace Fitness\DailySummary\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Fitness\DailySummary\Events\DailySummaryCollecting;
use Fitness\Profile\Models\WeightLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * 今日頁：依使用者時區切日，並收集各模組加入的摘要區塊。
 */
class TodayPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-19 10:00:00', 'Asia/Taipei'));
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('fitness.daily_summary.show'))->assertRedirect(route('login'));
    }

    public function test_today_page_includes_profile_section(): void
    {
        $user = User::factory()->create();
        WeightLog::create(['user_id' => $user->id, 'weight_kg' => 70.4, 'measured_on' => '2026-11-19']);

        $this->actingAs($user)->get(route('fitness.daily_summary.show'))->assertInertia(fn (Assert $page) => $page
            ->component('daily-summary/Today')
            ->where('date', '2026-11-19')
            ->where('sections.profile.targets.calorie_target', 1948)
            ->where('sections.profile.targets.protein_target_g', 84)
            ->where('sections.profile.current_weight.weight_kg', 70.4)
            ->where('sections.profile.weighed_today', true)
        );
    }

    public function test_weighed_today_is_false_when_latest_log_is_older(): void
    {
        $user = User::factory()->create();
        WeightLog::create(['user_id' => $user->id, 'weight_kg' => 70.4, 'measured_on' => '2026-11-18']);

        $this->actingAs($user)->get(route('fitness.daily_summary.show'))->assertInertia(fn (Assert $page) => $page
            ->where('sections.profile.current_weight.measured_on', '2026-11-18')
            ->where('sections.profile.weighed_today', false)
        );
    }

    public function test_today_follows_user_timezone(): void
    {
        // UTC 16:30 = 台北隔天 00:30
        $this->travelTo(CarbonImmutable::parse('2026-11-19 16:30:00', 'UTC'));

        $this->actingAs(User::factory()->create(['timezone' => 'Asia/Taipei']))
            ->get(route('fitness.daily_summary.show'))
            ->assertInertia(fn (Assert $page) => $page->where('date', '2026-11-20'));

        $this->actingAs(User::factory()->create(['timezone' => 'UTC']))
            ->get(route('fitness.daily_summary.show'))
            ->assertInertia(fn (Assert $page) => $page->where('date', '2026-11-19'));
    }

    public function test_other_modules_can_add_sections(): void
    {
        Event::listen(DailySummaryCollecting::class, function (DailySummaryCollecting $event) {
            $event->addSection('water', ['date' => $event->date->toDateString(), 'user_id' => $event->user->id]);
        });

        $user = User::factory()->create();

        $this->actingAs($user)->get(route('fitness.daily_summary.show'))->assertInertia(fn (Assert $page) => $page
            ->where('sections.water.date', '2026-11-19')
            ->where('sections.water.user_id', $user->id)
            ->has('sections.profile')
        );
    }
}

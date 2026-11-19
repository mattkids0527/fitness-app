<?php

namespace Fitness\Profile\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Fitness\Profile\Models\WeightLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * 首次設定：未完成設定的導向、表單資料、送出與驗證。
 */
class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-19 10:00:00', 'Asia/Taipei'));
    }

    /**
     * 合法的首次設定資料，可用 $overrides 覆寫部分欄位。
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'gender' => 'male',
            'birth_date' => '1990-05-10',
            'height_cm' => 175,
            'weight_kg' => 78.5,
            'activity_level' => 'light',
            'goal' => 'lose',
            'goal_rate_kg_per_week' => 0.5,
            'bmr_override' => null,
            'timezone' => 'Asia/Taipei',
        ], $overrides);
    }

    public function test_not_onboarded_user_is_redirected_to_onboarding(): void
    {
        $this->actingAs(User::factory()->notOnboarded()->create());

        $this->get(route('fitness.daily_summary.show'))->assertRedirect(route('fitness.profile.onboarding.show'));
        $this->get(route('fitness.profile.show'))->assertRedirect(route('fitness.profile.onboarding.show'));
    }

    public function test_not_onboarded_user_can_reach_excepted_routes(): void
    {
        $this->actingAs(User::factory()->notOnboarded()->create());

        $this->get(route('profile.edit'))->assertOk();
        $this->post(route('logout'))->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_onboarded_user_visiting_onboarding_is_redirected_to_today(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('fitness.profile.onboarding.show'))->assertRedirect(route('fitness.daily_summary.show'));
    }

    public function test_onboarding_page_provides_options_and_calculation(): void
    {
        $this->actingAs(User::factory()->notOnboarded()->create());

        $this->get(route('fitness.profile.onboarding.show'))->assertInertia(fn (Assert $page) => $page
            ->component('profile/Onboarding')
            ->where('today', '2026-11-19')
            ->has('options.genders', 2)
            ->has('options.activity_levels', 4)
            ->where('options.activity_levels.0.description', '辦公室工作、很少走動')
            ->has('options.goals', 3)
            ->where('options.calculation.activity_factors.light', 1.3)
            ->where('options.calculation.kcal_per_kg', 7700)
            ->where('options.calculation.protein_g_per_kg.lose', 1.8)
            ->where('options.calculation.calorie_floor.female', 1200)
            ->where('options.calculation.min_age', 13)
        );
    }

    public function test_completing_onboarding_saves_profile_and_first_weight_log(): void
    {
        $user = User::factory()->notOnboarded()->create();

        $this->actingAs($user)
            ->post(route('fitness.profile.onboarding.store'), $this->payload(['bmr_override' => 1650]))
            ->assertRedirect(route('fitness.daily_summary.show'));

        $user->refresh();
        $this->assertNotNull($user->onboarded_at);
        $this->assertSame('male', $user->gender);
        $this->assertSame('1990-05-10', $user->birth_date->toDateString());
        $this->assertSame(175.0, $user->height_cm);
        $this->assertSame('lose', $user->goal);
        $this->assertSame(0.5, $user->goal_rate_kg_per_week);
        $this->assertSame(1650, $user->bmr_override);

        $log = WeightLog::where('user_id', $user->id)->sole();
        $this->assertSame(78.5, $log->weight_kg);
        $this->assertSame('2026-11-19', $log->measured_on->toDateString());
    }

    public function test_onboarding_requires_all_fields(): void
    {
        $user = User::factory()->notOnboarded()->create();

        $this->actingAs($user)
            ->post(route('fitness.profile.onboarding.store'), [])
            ->assertSessionHasErrors(['gender', 'birth_date', 'height_cm', 'weight_kg', 'activity_level', 'goal']);

        $this->assertNull($user->refresh()->onboarded_at);
        $this->assertSame(0, WeightLog::count());
    }

    public function test_onboarding_rejects_out_of_range_values(): void
    {
        $this->actingAs(User::factory()->notOnboarded()->create());

        $this->post(route('fitness.profile.onboarding.store'), $this->payload([
            'birth_date' => '2014-01-01',
            'height_cm' => 99,
            'weight_kg' => 301,
            'goal_rate_kg_per_week' => 1.1,
            'bmr_override' => 499,
        ]))->assertSessionHasErrors(['birth_date', 'height_cm', 'weight_kg', 'goal_rate_kg_per_week', 'bmr_override']);

        $this->post(route('fitness.profile.onboarding.store'), $this->payload([
            'goal' => 'gain',
            'goal_rate_kg_per_week' => 0.6,
            'bmr_override' => 1650.5,
        ]))->assertSessionHasErrors(['goal_rate_kg_per_week', 'bmr_override']);
    }

    public function test_maintain_goal_stores_null_rate(): void
    {
        $user = User::factory()->notOnboarded()->create();

        $this->actingAs($user)
            ->post(route('fitness.profile.onboarding.store'), $this->payload(['goal' => 'maintain', 'goal_rate_kg_per_week' => 0.5]))
            ->assertRedirect(route('fitness.daily_summary.show'));

        $this->assertSame('maintain', $user->refresh()->goal);
        $this->assertNull($user->goal_rate_kg_per_week);
    }
}

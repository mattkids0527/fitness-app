<?php

namespace Fitness\Profile\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Fitness\Profile\Models\WeightLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * 「我的」頁與編輯個人資料。
 */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-19 10:00:00', 'Asia/Taipei'));
    }

    /**
     * 建立已完成設定、有兩筆體重紀錄的使用者（男、1990-01-01、175 cm、久坐、維持）。
     */
    protected function userWithWeights(): User
    {
        $user = User::factory()->create();
        WeightLog::create(['user_id' => $user->id, 'weight_kg' => 71.0, 'measured_on' => '2026-11-15']);
        WeightLog::create(['user_id' => $user->id, 'weight_kg' => 70.4, 'measured_on' => '2026-11-18']);

        return $user;
    }

    public function test_me_page_shows_profile_targets_and_current_weight(): void
    {
        $this->actingAs($this->userWithWeights());

        // 10×70.4 + 6.25×175 − 5×36 + 5 = 1622.75 → 1623；× 1.2 = 1947.6 → 1948
        $this->get(route('fitness.profile.show'))->assertInertia(fn (Assert $page) => $page
            ->component('profile/Show')
            ->where('profile.gender', 'male')
            ->where('profile.birth_date', '1990-01-01')
            ->where('targets.age', 36)
            ->where('targets.bmr', 1623)
            ->where('targets.calorie_target', 1948)
            ->where('targets.protein_target_g', 84)
            ->where('targets.calorie_floor', 1500)
            ->where('targets.below_floor', false)
            ->where('currentWeight.weight_kg', 70.4)
            ->where('currentWeight.measured_on', '2026-11-18')
            ->where('currentWeight.delta_kg', -0.6)
            ->has('options.calculation')
        );
    }

    public function test_edit_page_renders_profile_and_options(): void
    {
        $this->actingAs($this->userWithWeights());

        $this->get(route('fitness.profile.edit'))->assertInertia(fn (Assert $page) => $page
            ->component('profile/Edit')
            ->where('profile.height_cm', 175)
            ->where('currentWeight.weight_kg', 70.4)
            ->where('today', '2026-11-19')
            ->has('options.goal_rate.lose')
        );
    }

    public function test_profile_can_be_updated(): void
    {
        $user = $this->userWithWeights();

        $this->actingAs($user)->put(route('fitness.profile.update'), [
            'gender' => 'female',
            'birth_date' => '1995-03-20',
            'height_cm' => 162.5,
            'activity_level' => 'moderate',
            'goal' => 'gain',
            'goal_rate_kg_per_week' => 0.3,
            'bmr_override' => 1400,
        ])->assertRedirect(route('fitness.profile.show'));

        $user->refresh();
        $this->assertSame('female', $user->gender);
        $this->assertSame('1995-03-20', $user->birth_date->toDateString());
        $this->assertSame(162.5, $user->height_cm);
        $this->assertSame('moderate', $user->activity_level);
        $this->assertSame('gain', $user->goal);
        $this->assertSame(0.3, $user->goal_rate_kg_per_week);
        $this->assertEquals(1400, $user->bmr_override);
    }

    public function test_profile_update_validates_values(): void
    {
        $this->actingAs($this->userWithWeights());

        $this->put(route('fitness.profile.update'), [
            'gender' => 'other',
            'birth_date' => '1990-01-01',
            'height_cm' => 175,
            'activity_level' => 'sedentary',
            'goal' => 'gain',
            'goal_rate_kg_per_week' => 0.8,
            'bmr_override' => 'abc',
        ])->assertSessionHasErrors(['gender', 'goal_rate_kg_per_week', 'bmr_override']);
    }
}

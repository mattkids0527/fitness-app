<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * 底部 Tab 尚未實作的飲食、健身頁顯示「即將推出」。
 */
class ComingSoonTest extends TestCase
{
    use RefreshDatabase;

    public function test_coming_soon_pages_render_for_authenticated_users(): void
    {
        $this->get(route('coming_soon.meals'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create());

        $this->get(route('coming_soon.meals'))->assertInertia(fn (Assert $page) => $page
            ->component('ComingSoon')
            ->where('title', '飲食')
            ->has('items', 3)
        );

        $this->get(route('coming_soon.workouts'))->assertInertia(fn (Assert $page) => $page
            ->component('ComingSoon')
            ->where('title', '健身')
        );
    }
}

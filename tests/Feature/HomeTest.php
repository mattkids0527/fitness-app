<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_visiting_home_are_redirected_to_login()
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_authenticated_users_visiting_home_are_redirected_to_today()
    {
        $this->actingAs(User::factory()->create());

        $this->get('/')->assertRedirect(route('fitness.daily_summary.show'));
    }
}

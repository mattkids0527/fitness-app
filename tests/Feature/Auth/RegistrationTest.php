<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_is_not_available_when_registration_is_disabled()
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_users_cannot_register_when_registration_is_disabled()
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_login_page_hides_register_and_forgot_password_links()
    {
        $this->get('/login')->assertInertia(fn (Assert $page) => $page
            ->component('auth/Login')
            ->where('canRegister', false)
            ->where('canResetPassword', false)
        );
    }
}

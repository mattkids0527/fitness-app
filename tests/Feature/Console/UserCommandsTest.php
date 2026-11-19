<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 帳號指令：user:create 建立帳號、user:password 重設密碼。
 */
class UserCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_create_creates_verified_user_who_must_onboard(): void
    {
        $this->artisan('user:create', ['--name' => '小明', '--email' => 'ming@example.com', '--password' => 'password123'])
            ->expectsOutputToContain('已建立帳號 ming@example.com')
            ->assertSuccessful();

        $user = User::where('email', 'ming@example.com')->sole();
        $this->assertSame('小明', $user->name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->onboarded_at);
        $this->assertTrue(Hash::check('password123', $user->password));

        $this->post(route('login'), ['email' => 'ming@example.com', 'password' => 'password123'])
            ->assertRedirect(route('fitness.daily_summary.show', absolute: false));
        $this->get(route('fitness.daily_summary.show'))->assertRedirect(route('fitness.profile.onboarding.show'));
    }

    public function test_user_create_lowercases_email(): void
    {
        $this->artisan('user:create', ['--name' => '小明', '--email' => '  Ming@Example.COM ', '--password' => 'password123'])->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'ming@example.com']);
    }

    public function test_user_create_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'ming@example.com']);

        $this->artisan('user:create', ['--name' => '小明', '--email' => 'ming@example.com', '--password' => 'password123'])->assertFailed();

        $this->assertSame(1, User::where('email', 'ming@example.com')->count());
    }

    public function test_user_create_rejects_short_password(): void
    {
        $this->artisan('user:create', ['--name' => '小明', '--email' => 'ming@example.com', '--password' => 'short'])->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'ming@example.com']);
    }

    public function test_user_create_can_generate_password(): void
    {
        $exitCode = Artisan::call('user:create', ['--name' => '小明', '--email' => 'ming@example.com', '--generate-password' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertMatchesRegularExpression('/\b([A-Za-z0-9]{16})\b/', $output);
        preg_match('/\b([A-Za-z0-9]{16})\b/', $output, $matches);

        $this->assertTrue(Hash::check($matches[1], User::where('email', 'ming@example.com')->sole()->password));
    }

    public function test_user_password_resets_password(): void
    {
        $user = User::factory()->create(['email' => 'ming@example.com']);

        $this->artisan('user:password', ['email' => 'Ming@example.com', '--password' => 'new-password-1'])
            ->expectsOutputToContain('已重設 ming@example.com 的密碼')
            ->assertSuccessful();

        $user->refresh();
        $this->assertTrue(Hash::check('new-password-1', $user->password));
        $this->assertFalse(Hash::check('password', $user->password));
    }

    public function test_user_password_fails_for_unknown_email(): void
    {
        $this->artisan('user:password', ['email' => 'nobody@example.com', '--password' => 'new-password-1'])
            ->expectsOutputToContain('找不到帳號 nobody@example.com')
            ->assertFailed();
    }
}

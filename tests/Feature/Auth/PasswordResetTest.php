<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_forgot_password_page_can_be_rendered()
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Auth/ForgotPassword')
        );
    }

    public function test_reset_password_link_can_be_requested()
    {
        $user = User::factory()->create(['aktywny' => true]);

        $response = $this->post('/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertSessionHas('status');
    }

    public function test_reset_password_page_can_be_rendered()
    {
        $user = User::factory()->create(['aktywny' => true]);
        $token = Password::createToken($user);

        $response = $this->get('/reset-password/'.$token.'?email='.$user->email);

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Auth/ResetPassword')
            ->where('email', $user->email)
            ->where('token', $token)
        );
    }

    public function test_password_can_be_reset_with_valid_token()
    {
        $user = User::factory()->create(['aktywny' => true]);
        $token = Password::createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertTrue(auth()->guard()->validate([
            'email' => $user->email,
            'password' => 'new-password',
        ]));
    }
}

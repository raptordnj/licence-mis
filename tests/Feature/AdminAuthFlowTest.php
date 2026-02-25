<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AdminAuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_login_and_receive_access_token(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'admin-login@example.com',
        ]);

        $response = $this->postJson('/api/v1/admin/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.admin.email', $admin->email);
        $response->assertJsonPath('data.token_type', 'Bearer');

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_non_admin_user_cannot_login_to_admin_api(): void
    {
        $user = User::factory()->create([
            'email' => 'support-login@example.com',
        ]);

        $response = $this->postJson('/api/v1/admin/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
    }

    public function test_login_requires_2fa_when_admin_has_two_factor_enabled(): void
    {
        $secret = app(Google2FA::class)->generateSecretKey();

        User::factory()->admin()->create([
            'email' => 'admin-2fa-required@example.com',
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => app(TwoFactorService::class)->hashRecoveryCodes(['RECOVER-A']),
        ]);

        $response = $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'admin-2fa-required@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'TWO_FACTOR_REQUIRED');
    }

    public function test_admin_can_login_with_valid_totp_code(): void
    {
        $google2FA = app(Google2FA::class);
        $secret = $google2FA->generateSecretKey();

        User::factory()->admin()->create([
            'email' => 'admin-2fa-totp@example.com',
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => app(TwoFactorService::class)->hashRecoveryCodes(['RECOVER-B']),
        ]);

        $response = $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'admin-2fa-totp@example.com',
            'password' => 'password',
            'two_factor_code' => $google2FA->getCurrentOtp($secret),
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.two_factor_enabled', true);
    }

    public function test_admin_can_login_with_recovery_code_and_recovery_code_is_consumed(): void
    {
        $twoFactorService = app(TwoFactorService::class);
        $recoveryCode = 'RECOVER-C';

        $admin = User::factory()->admin()->create([
            'email' => 'admin-2fa-recovery@example.com',
            'two_factor_secret' => app(Google2FA::class)->generateSecretKey(),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $twoFactorService->hashRecoveryCodes([$recoveryCode]),
        ]);

        $firstLogin = $this->postJson('/api/v1/admin/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
            'recovery_code' => $recoveryCode,
        ]);

        $firstLogin->assertOk();

        $admin->refresh();
        $this->assertSame([], $admin->two_factor_recovery_codes);

        $secondLogin = $this->postJson('/api/v1/admin/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
            'recovery_code' => $recoveryCode,
        ]);

        $secondLogin->assertStatus(422);
        $secondLogin->assertJsonPath('error.code', 'TWO_FACTOR_INVALID');
    }

    public function test_admin_login_is_locked_after_repeated_invalid_attempts(): void
    {
        config()->set('services.admin_auth.max_attempts', 2);
        config()->set('services.admin_auth.lockout_seconds', 600);

        $admin = User::factory()->admin()->create([
            'email' => 'admin-lockout@example.com',
        ]);

        $this->postJson('/api/v1/admin/auth/login', [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ])->assertStatus(401);

        $this->postJson('/api/v1/admin/auth/login', [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ])->assertStatus(401);

        $lockedResponse = $this->postJson('/api/v1/admin/auth/login', [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ]);

        $lockedResponse->assertStatus(429);
        $lockedResponse->assertJsonPath('error.code', 'RATE_LIMITED');
    }

    public function test_admin_can_fetch_profile_and_logout_using_sanctum_token(): void
    {
        $admin = User::factory()->admin()->create();
        $plainTextToken = $admin->createToken('admin-api')->plainTextToken;

        $profile = $this
            ->withHeader('Authorization', 'Bearer '.$plainTextToken)
            ->getJson('/api/v1/admin/auth/me');

        $profile->assertOk();
        $profile->assertJsonPath('data.id', $admin->id);

        $logout = $this
            ->withHeader('Authorization', 'Bearer '.$plainTextToken)
            ->postJson('/api/v1/admin/auth/logout');

        $logout->assertOk();

        $tokenCount = PersonalAccessToken::query()->count();
        $this->assertSame(0, $tokenCount);
    }

    public function test_admin_can_logout_other_devices(): void
    {
        $admin = User::factory()->admin()->create();
        $currentToken = $admin->createToken('current-token')->plainTextToken;
        $admin->createToken('other-device-token')->plainTextToken;

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$currentToken)
            ->postJson('/api/v1/admin/auth/logout-other-devices');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.revoked_tokens_count', 1);

        $tokenCount = PersonalAccessToken::query()->count();
        $this->assertSame(1, $tokenCount);
    }
}

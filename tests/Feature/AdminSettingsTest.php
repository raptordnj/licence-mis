<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AuditEventType;
use App\Enums\SettingKey;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_settings_status(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/admin/settings');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.has_envato_api_token', false);
        $response->assertJsonPath('data.has_license_hmac_key', false);
    }

    public function test_admin_can_update_sensitive_settings_and_log_audit_event(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $envatoToken = 'envato-token-example-abcdef123456';
        $hmacKey = '1234567890abcdef1234567890abcdef';

        $response = $this->putJson('/api/v1/admin/settings', [
            'envato_api_token' => $envatoToken,
            'license_hmac_key' => $hmacKey,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.has_envato_api_token', true);
        $response->assertJsonPath('data.has_license_hmac_key', true);

        $rawTokenValue = (string) DB::table('settings')
            ->where('key', SettingKey::ENVATO_API_TOKEN->value)
            ->value('value');
        $rawHmacValue = (string) DB::table('settings')
            ->where('key', SettingKey::LICENSE_HMAC_KEY->value)
            ->value('value');

        $this->assertNotSame($envatoToken, $rawTokenValue);
        $this->assertNotSame($hmacKey, $rawHmacValue);
        $this->assertSame(
            $envatoToken,
            Setting::query()->where('key', SettingKey::ENVATO_API_TOKEN->value)->value('value'),
        );
        $this->assertSame(
            $hmacKey,
            Setting::query()->where('key', SettingKey::LICENSE_HMAC_KEY->value)->value('value'),
        );

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => AuditEventType::TOKEN_CHANGED->value,
            'actor_id' => $admin->id,
            'license_id' => null,
        ]);
    }

    public function test_admin_can_enable_and_disable_envato_mock_mode(): void
    {
        config()->set('license_manager.envato_mock.mode', false);

        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $enableResponse = $this->putJson('/api/v1/admin/settings', [
            'envato_mock_mode' => true,
        ]);

        $enableResponse->assertOk();
        $enableResponse->assertJsonPath('data.envato_mock_mode', true);

        $this->assertSame(
            '1',
            Setting::query()->where('key', SettingKey::ENVATO_MOCK_MODE->value)->value('value'),
        );

        $disableResponse = $this->putJson('/api/v1/admin/settings', [
            'envato_mock_mode' => false,
        ]);

        $disableResponse->assertOk();
        $disableResponse->assertJsonPath('data.envato_mock_mode', false);

        $this->assertSame(
            '0',
            Setting::query()->where('key', SettingKey::ENVATO_MOCK_MODE->value)->value('value'),
        );
    }

    public function test_admin_payload_with_policy_and_limit_fields_passes_request_contract(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->putJson('/api/v1/admin/settings', [
            'rate_limit_per_minute' => 45,
            'domain_policies' => [
                'treat_www_as_same' => true,
                'allow_localhost' => false,
                'allow_ip_domains' => false,
            ],
            'reset_policies' => [
                'max_resets_allowed' => 5,
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_envato_token_test_succeeds_when_envato_api_is_reachable(): void
    {
        config()->set('license_manager.envato_mock.mode', false);

        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/admin/settings', [
            'envato_api_token' => 'envato-token-example-abcdef123456',
        ])->assertOk();

        Http::fake([
            'https://api.envato.com/*' => Http::response([
                'matches' => [],
            ], 200),
        ]);

        $response = $this->getJson('/api/v1/admin/settings/test-envato-token');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.ok', true);

        Http::assertSent(static function (HttpRequest $request): bool {
            return str_contains($request->url(), 'api.envato.com')
                && data_get($request->data(), 'code') === '00000000-0000-0000-0000-000000000000';
        });
    }

    public function test_envato_token_test_returns_envato_unavailable_on_forbidden_scope(): void
    {
        config()->set('license_manager.envato_mock.mode', false);

        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/admin/settings', [
            'envato_api_token' => 'envato-token-example-abcdef123456',
        ])->assertOk();

        Http::fake([
            'https://api.envato.com/*' => Http::response([
                'error' => 'Unauthorized operation - missing scope.',
            ], 403),
        ]);

        $response = $this->getJson('/api/v1/admin/settings/test-envato-token');

        $response->assertStatus(503);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.code', 'ENVATO_UNAVAILABLE');
        $response->assertJsonPath('error.message', static function (?string $message): bool {
            if (! is_string($message)) {
                return false;
            }

            return str_contains($message, 'Envato rejected token permissions.')
                && str_contains($message, '/buyer/list-purchases');
        });
    }

    public function test_envato_token_test_uses_scope_fallback_endpoints(): void
    {
        config()->set('license_manager.envato_mock.mode', false);

        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/admin/settings', [
            'envato_api_token' => 'envato-token-example-abcdef123456',
        ])->assertOk();

        Http::fake([
            'https://api.envato.com/*/author/sale*' => Http::response([
                'error' => 'scope required sale:verify',
            ], 403),
            'https://api.envato.com/*/buyer/purchase*' => Http::response([
                'error' => 'scope required purchase:verify',
            ], 403),
            'https://api.envato.com/*/buyer/list-purchases*' => Http::response([
                'matches' => [],
            ], 200),
        ]);

        $response = $this->getJson('/api/v1/admin/settings/test-envato-token');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.ok', true);
        Http::assertSent(static fn (HttpRequest $request): bool => str_contains($request->url(), '/author/sale'));
        Http::assertSent(static fn (HttpRequest $request): bool => str_contains($request->url(), '/buyer/purchase'));
        Http::assertSent(static fn (HttpRequest $request): bool => str_contains($request->url(), '/buyer/list-purchases'));
    }

    public function test_envato_token_test_passes_for_author_scope_when_author_sale_returns_not_found(): void
    {
        config()->set('license_manager.envato_mock.mode', false);

        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/admin/settings', [
            'envato_api_token' => 'envato-token-example-abcdef123456',
        ])->assertOk();

        Http::fake([
            'https://api.envato.com/*/author/sale*' => Http::response([
                'error' => 'No sale found for this code.',
            ], 404),
        ]);

        $response = $this->getJson('/api/v1/admin/settings/test-envato-token');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.ok', true);
        Http::assertSent(static fn (HttpRequest $request): bool => str_contains($request->url(), '/author/sale'));
    }

    public function test_support_user_cannot_view_or_update_settings(): void
    {
        $supportUser = User::factory()->create();
        Sanctum::actingAs($supportUser);

        $viewResponse = $this->getJson('/api/v1/admin/settings');
        $updateResponse = $this->putJson('/api/v1/admin/settings', [
            'envato_api_token' => 'envato-token-example-abcdef123456',
        ]);

        $viewResponse->assertStatus(403);
        $viewResponse->assertJsonPath('error.code', 'FORBIDDEN');

        $updateResponse->assertStatus(403);
        $updateResponse->assertJsonPath('error.code', 'FORBIDDEN');
    }
}

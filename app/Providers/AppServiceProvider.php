<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\License;
use App\Models\AuditLog;
use App\Models\EnvatoItem;
use App\Models\Setting;
use App\Models\User;
use App\Policies\AdminUserPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\EnvatoItemPolicy;
use App\Policies\LicensePolicy;
use App\Policies\SettingPolicy;
use App\Repositories\EloquentLicenseRepository;
use App\Repositories\LicenseRepositoryInterface;
use App\Services\Contracts\EnvatoPurchaseValidatorInterface;
use App\Services\Contracts\EnvatoVerifierInterface;
use App\Services\Contracts\SensitiveSettingsStoreInterface;
use App\Services\EnvatoApiPurchaseValidator;
use App\Services\ExternalApiPurchaseValidator;
use App\Services\EnvatoMockModeGuard;
use App\Services\EnvatoVerifierService;
use App\Services\MockPurchaseValidator;
use App\Services\SensitiveSettingService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use PragmaRX\Google2FA\Google2FA;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LicenseRepositoryInterface::class, EloquentLicenseRepository::class);
        $this->app->bind(EnvatoPurchaseValidatorInterface::class, function (): EnvatoPurchaseValidatorInterface {
            $settingsStore = $this->app->make(SensitiveSettingsStoreInterface::class);
            $mockModeEnabled = $settingsStore->isEnvatoMockModeEnabled();

            $this->app->make(EnvatoMockModeGuard::class)->assertRuntimeStateIsSafe(
                mockModeEnabled: $mockModeEnabled,
                source: 'database_or_config',
            );

            if ($mockModeEnabled) {
                return $this->app->make(MockPurchaseValidator::class);
            }

            $envatoValidator = $this->app->make(EnvatoApiPurchaseValidator::class);

            // Wrap with external API validator if enabled
            if ((bool) config('services.external_license_api.enabled', false)) {
                return new ExternalApiPurchaseValidator($envatoValidator);
            }

            return $envatoValidator;
        });
        $this->app->bind(EnvatoVerifierInterface::class, EnvatoVerifierService::class);
        $this->app->bind(SensitiveSettingsStoreInterface::class, SensitiveSettingService::class);
        $this->app->singleton(Google2FA::class, fn (): Google2FA => new Google2FA());
    }

    public function boot(): void
    {
        $this->app->make(EnvatoMockModeGuard::class)->assertConfigurationIsSafe();

        Gate::policy(License::class, LicensePolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(Setting::class, SettingPolicy::class);
        Gate::policy(EnvatoItem::class, EnvatoItemPolicy::class);
        Gate::policy(User::class, AdminUserPolicy::class);

        RateLimiter::for('license-verify', function (Request $request): Limit {
            $purchaseCode = (string) $request->input('purchase_code', '');
            $itemId = (string) $request->input('item_id', '');
            $ipAddress = (string) $request->ip();

            return Limit::perMinute((int) config('services.license.verify_rate_limit', 30))
                ->by("{$ipAddress}|{$purchaseCode}|{$itemId}");
        });

        RateLimiter::for('admin-auth', function (Request $request): Limit {
            $email = mb_strtolower(trim((string) $request->input('email', 'unknown')));
            $ipAddress = (string) $request->ip();

            return Limit::perMinute((int) config('services.admin_auth.max_attempts', 5))
                ->by("{$ipAddress}|{$email}");
        });

        RateLimiter::for('public-license-verify', function (Request $request): Limit {
            $purchaseCode = mb_strtolower(trim((string) $request->input('purchase_code', '')));
            $ipAddress = (string) $request->ip();

            return Limit::perMinute((int) config('license_manager.verify_rate_limit_per_minute', 60))
                ->by("verify|{$ipAddress}|{$purchaseCode}");
        });

        RateLimiter::for('public-license-deactivate', function (Request $request): Limit {
            $purchaseCode = mb_strtolower(trim((string) $request->input('purchase_code', '')));
            $ipAddress = (string) $request->ip();

            return Limit::perMinute((int) config('license_manager.deactivate_rate_limit_per_minute', 30))
                ->by("deactivate|{$ipAddress}|{$purchaseCode}");
        });
    }
}

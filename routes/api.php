<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminAuditLogController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminEnvatoItemController;
use App\Http\Controllers\Admin\AdminManagedLicenseCrudController;
use App\Http\Controllers\Admin\AdminProductCrudController;
use App\Http\Controllers\Admin\AdminPurchaseController;
use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminValidationLogController;
use App\Http\Controllers\Admin\LicenseAdminController;
use App\Http\Controllers\Admin\TwoFactorController;
use App\Http\Controllers\Api\LicenseManagementController;
use App\Http\Controllers\Api\V1\LicenseVerificationController;
use Illuminate\Support\Facades\Route;

Route::post('/licenses/verify', [LicenseManagementController::class, 'verify'])
    ->middleware('throttle:public-license-verify');
Route::post('/licenses/auto-issue', [LicenseManagementController::class, 'autoIssue'])
    ->middleware('throttle:public-license-verify');
Route::post('/licenses/deactivate', [LicenseManagementController::class, 'deactivate'])
    ->middleware('throttle:public-license-deactivate');

Route::prefix('v1')->group(function (): void {
    Route::post('/licenses/verify', [LicenseVerificationController::class, 'verify'])
        ->middleware('throttle:license-verify');

    Route::prefix('admin')->group(function (): void {
        Route::post('/auth/login', [AdminAuthController::class, 'login'])
            ->middleware('throttle:admin-auth');

        Route::middleware(['auth:sanctum'])->group(function (): void {
            Route::get('/auth/me', [AdminAuthController::class, 'me']);
            Route::post('/auth/logout', [AdminAuthController::class, 'logout']);
            Route::post('/auth/logout-other-devices', [AdminAuthController::class, 'logoutOtherDevices']);
            Route::get('/dashboard', [AdminDashboardController::class, 'show']);

            Route::get('/products', [AdminProductCrudController::class, 'index']);
            Route::post('/products', [AdminProductCrudController::class, 'store']);
            Route::put('/products/{product}', [AdminProductCrudController::class, 'update']);

            Route::get('/managed-licenses', [AdminManagedLicenseCrudController::class, 'index']);
            Route::post('/managed-licenses', [AdminManagedLicenseCrudController::class, 'store']);
            Route::patch('/managed-licenses/{license}/status', [AdminManagedLicenseCrudController::class, 'setStatus']);

            Route::get('/items', [AdminEnvatoItemController::class, 'index']);
            Route::post('/items', [AdminEnvatoItemController::class, 'store']);
            Route::put('/items/{envatoItem}', [AdminEnvatoItemController::class, 'update']);
            Route::get('/purchases', [AdminPurchaseController::class, 'index']);
            Route::get('/purchases/{license}', [AdminPurchaseController::class, 'show']);
            Route::get('/licenses', [LicenseAdminController::class, 'index']);
            Route::get('/licenses/{license}', [LicenseAdminController::class, 'show']);
            Route::post('/licenses/{license}/revoke', [LicenseAdminController::class, 'revoke']);
            Route::post('/licenses/{license}/reset-domain', [LicenseAdminController::class, 'resetDomain']);
            Route::get('/validation-logs', [AdminValidationLogController::class, 'index']);
            Route::get('/users', [AdminUserController::class, 'index']);
            Route::post('/users', [AdminUserController::class, 'store']);
            Route::patch('/users/{user}/role', [AdminUserController::class, 'updateRole']);
            Route::get('/audit-logs', [AdminAuditLogController::class, 'index']);
            Route::get('/settings', [AdminSettingsController::class, 'show']);
            Route::put('/settings', [AdminSettingsController::class, 'update']);
            Route::get('/settings/test-envato-token', [AdminSettingsController::class, 'testEnvatoToken']);

            Route::post('/2fa/setup', [TwoFactorController::class, 'setup']);
            Route::post('/2fa/confirm', [TwoFactorController::class, 'confirm']);
        });
    });
});

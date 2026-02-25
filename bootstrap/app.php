<?php

declare(strict_types=1);

use App\Enums\ApiErrorCode;
use App\Exceptions\DomainMismatchException;
use App\Exceptions\EnvatoUnavailableException;
use App\Exceptions\InvalidCredentialsException;
use App\Exceptions\LicenseRevokedException;
use App\Exceptions\PurchaseInvalidException;
use App\Exceptions\TwoFactorInvalidException;
use App\Exceptions\TwoFactorRequiredException;
use App\Support\Api\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                ApiErrorCode::VALIDATION_ERROR,
                $exception->getMessage(),
                422,
            );
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                ApiErrorCode::UNAUTHORIZED,
                'Authentication is required.',
                401,
            );
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                ApiErrorCode::FORBIDDEN,
                'You are not allowed to perform this action.',
                403,
            );
        });

        $exceptions->render(function (AccessDeniedHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                ApiErrorCode::FORBIDDEN,
                'You are not allowed to perform this action.',
                403,
            );
        });

        $exceptions->render(function (TooManyRequestsHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                ApiErrorCode::RATE_LIMITED,
                'Too many requests. Please try again later.',
                429,
            );
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                ApiErrorCode::NOT_FOUND,
                'The requested API endpoint could not be found.',
                404,
            );
        });

        $exceptions->render(function (InvalidCredentialsException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                ApiErrorCode::INVALID_CREDENTIALS,
                'Invalid login credentials.',
                401,
            );
        });

        $exceptions->render(function (TwoFactorRequiredException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                ApiErrorCode::TWO_FACTOR_REQUIRED,
                $exception->getMessage(),
                422,
            );
        });

        $exceptions->render(function (TwoFactorInvalidException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                ApiErrorCode::TWO_FACTOR_INVALID,
                $exception->getMessage(),
                422,
            );
        });

        $exceptions->render(function (DomainMismatchException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                ApiErrorCode::DOMAIN_MISMATCH,
                'License is bound to another domain.',
                409,
            );
        });

        $exceptions->render(function (LicenseRevokedException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                ApiErrorCode::LICENSE_REVOKED,
                'License has been revoked.',
                403,
            );
        });

        $exceptions->render(function (PurchaseInvalidException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                ApiErrorCode::PURCHASE_INVALID,
                $exception->getMessage(),
                422,
            );
        });

        $exceptions->render(function (EnvatoUnavailableException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $message = $request->is('api/v1/admin/settings/test-envato-token')
                ? ($exception->getMessage() !== '' ? $exception->getMessage() : 'Verification provider is currently unavailable.')
                : 'Verification provider is currently unavailable.';

            return ApiResponse::error(
                ApiErrorCode::ENVATO_UNAVAILABLE,
                $message,
                503,
            );
        });

        $exceptions->render(function (\Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            report($exception);

            return ApiResponse::error(
                ApiErrorCode::INTERNAL_ERROR,
                'An unexpected server error occurred.',
                500,
            );
        });
    })->create();

<?php

declare(strict_types=1);

namespace App\Support\Api;

use App\Enums\ApiErrorCode;
use Illuminate\Http\JsonResponse;

final readonly class ApiResponse
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function success(array $data, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'error' => null,
        ], $status);
    }

    public static function error(ApiErrorCode $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'data' => null,
            'error' => [
                'code' => $code->value,
                'message' => $message,
            ],
        ], $status);
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LicenseCheckResult;
use App\Models\LicenseCheck;
use App\Models\LicenseInstance;

readonly class LicenseCheckLoggerService
{
    /**
     * @param  array<string, mixed>  $requestPayload
     * @param  array<string, mixed>  $responsePayload
     */
    public function log(
        ?LicenseInstance $instance,
        LicenseCheckResult $result,
        ?string $reason,
        array $requestPayload,
        array $responsePayload,
    ): LicenseCheck {
        return LicenseCheck::query()->create([
            'license_instance_id' => $instance?->id,
            'checked_at' => now(),
            'result' => $result,
            'reason' => $reason,
            'request_payload' => $requestPayload,
            'response_payload' => $responsePayload,
        ]);
    }
}

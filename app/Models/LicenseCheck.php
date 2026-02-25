<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LicenseCheckResult;
use Database\Factories\LicenseCheckFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $license_instance_id
 * @property Carbon $checked_at
 * @property LicenseCheckResult $result
 * @property string|null $reason
 * @property array<string, mixed> $request_payload
 * @property array<string, mixed> $response_payload
 */
class LicenseCheck extends Model
{
    /** @use HasFactory<LicenseCheckFactory> */
    use HasFactory;

    protected $fillable = [
        'license_instance_id',
        'checked_at',
        'result',
        'reason',
        'request_payload',
        'response_payload',
    ];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
            'result' => LicenseCheckResult::class,
            'request_payload' => 'array',
            'response_payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<LicenseInstance, $this>
     */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(LicenseInstance::class, 'license_instance_id');
    }
}

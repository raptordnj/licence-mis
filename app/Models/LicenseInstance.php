<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LicenseInstanceStatus;
use Database\Factories\LicenseInstanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $license_id
 * @property string $instance_id
 * @property string $domain
 * @property string $app_url
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $activated_at
 * @property Carbon|null $deactivated_at
 * @property LicenseInstanceStatus $status
 */
class LicenseInstance extends Model
{
    /** @use HasFactory<LicenseInstanceFactory> */
    use HasFactory;

    protected $fillable = [
        'license_id',
        'instance_id',
        'domain',
        'app_url',
        'ip',
        'user_agent',
        'last_seen_at',
        'activated_at',
        'deactivated_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'activated_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'status' => LicenseInstanceStatus::class,
        ];
    }

    /**
     * @return BelongsTo<License, $this>
     */
    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    /**
     * @return HasMany<LicenseCheck, $this>
     */
    public function checks(): HasMany
    {
        return $this->hasMany(LicenseCheck::class);
    }
}

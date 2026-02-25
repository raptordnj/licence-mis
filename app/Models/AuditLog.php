<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuditEventType;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property AuditEventType $event_type
 * @property int|null $actor_id
 * @property int|null $license_id
 * @property array<string, mixed>|null $metadata
 * @property User|null $actor
 * @property License|null $license
 */
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    protected $fillable = [
        'event_type',
        'actor_id',
        'license_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => AuditEventType::class,
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return BelongsTo<License, $this>
     */
    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}

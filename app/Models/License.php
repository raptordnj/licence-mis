<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LicenseStatus;
use App\Enums\Marketplace;
use Database\Factories\LicenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $product_id
 * @property string $purchase_code
 * @property string|null $buyer
 * @property Marketplace $marketplace
 * @property int|null $envato_item_id
 * @property LicenseStatus $status
 * @property string|null $notes
 * @property string|null $bound_domain
 * @property Carbon|null $supported_until
 * @property Carbon|null $verified_at
 * @property array<string, mixed>|null $metadata
 * @property Product|null $product
 */
class License extends Model
{
    /** @use HasFactory<LicenseFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'purchase_code',
        'buyer',
        'marketplace',
        'envato_item_id',
        'status',
        'notes',
        'bound_domain',
        'supported_until',
        'verified_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'marketplace' => Marketplace::class,
            'status' => LicenseStatus::class,
            'supported_until' => 'datetime',
            'verified_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * @return HasMany<LicenseInstance, $this>
     */
    public function instances(): HasMany
    {
        return $this->hasMany(LicenseInstance::class);
    }
}

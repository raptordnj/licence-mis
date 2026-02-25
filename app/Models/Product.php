<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $envato_item_id
 * @property string $name
 * @property int $activation_limit
 * @property ProductStatus $status
 * @property bool $strict_domain_binding
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'envato_item_id',
        'name',
        'activation_limit',
        'status',
        'strict_domain_binding',
    ];

    protected function casts(): array
    {
        return [
            'activation_limit' => 'integer',
            'status' => ProductStatus::class,
            'strict_domain_binding' => 'boolean',
        ];
    }

    /**
     * @return HasMany<License, $this>
     */
    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }

    public function isActive(): bool
    {
        return $this->status === ProductStatus::ACTIVE;
    }
}

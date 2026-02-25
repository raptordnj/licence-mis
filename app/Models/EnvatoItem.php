<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EnvatoItemStatus;
use App\Enums\Marketplace;
use Database\Factories\EnvatoItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property Marketplace $marketplace
 * @property int $envato_item_id
 * @property string $name
 * @property EnvatoItemStatus $status
 */
class EnvatoItem extends Model
{
    /** @use HasFactory<EnvatoItemFactory> */
    use HasFactory;

    protected $fillable = [
        'marketplace',
        'envato_item_id',
        'name',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'marketplace' => Marketplace::class,
            'status' => EnvatoItemStatus::class,
        ];
    }

    /**
     * @return HasMany<License, $this>
     */
    public function licenses(): HasMany
    {
        return $this->hasMany(License::class, 'envato_item_id', 'envato_item_id');
    }
}

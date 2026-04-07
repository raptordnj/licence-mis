<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UpdateReleaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $product_id
 * @property string $channel
 * @property string $version
 * @property string|null $min_version
 * @property string|null $max_version
 * @property string|null $release_notes
 * @property string $package_path
 * @property string $checksum
 * @property int $size_bytes
 * @property bool $is_published
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property int|null $created_by
 * @property array<string, mixed>|null $metadata
 */
class UpdateRelease extends Model
{
    /** @use HasFactory<UpdateReleaseFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'channel',
        'version',
        'min_version',
        'max_version',
        'release_notes',
        'package_path',
        'checksum',
        'size_bytes',
        'is_published',
        'published_at',
        'created_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'size_bytes' => 'integer',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'created_by' => 'integer',
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

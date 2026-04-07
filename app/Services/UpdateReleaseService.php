<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\UpdateRelease;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class UpdateReleaseService
{
    /**
     * @return array{path:string,checksum:string,size_bytes:int}
     */
    public function storePackage(UploadedFile $package): array
    {
        $diskName = $this->packageDisk();
        $directory = $this->packageDirectory();

        $disk = Storage::disk($diskName);
        $disk->makeDirectory($directory);

        $filename = sprintf(
            'release-%s-%s.zip',
            now()->format('YmdHis'),
            bin2hex(random_bytes(6)),
        );

        $path = $package->storeAs($directory, $filename, $diskName);

        if (! is_string($path) || $path === '') {
            throw new \RuntimeException('Unable to store update package.');
        }

        $checksum = $this->calculateChecksum($diskName, $path);
        $sizeBytes = (int) $disk->size($path);

        return [
            'path' => $path,
            'checksum' => $checksum,
            'size_bytes' => max(0, $sizeBytes),
        ];
    }

    public function deletePackage(string $path): void
    {
        $normalized = trim($path);
        if ($normalized === '') {
            return;
        }

        $disk = Storage::disk($this->packageDisk());
        if ($disk->exists($normalized)) {
            $disk->delete($normalized);
        }
    }

    public function resolveProduct(?int $productId, ?int $envatoItemId): ?Product
    {
        if ($productId !== null && $productId > 0) {
            return Product::query()->find($productId);
        }

        if ($envatoItemId !== null && $envatoItemId > 0) {
            return Product::query()->where('envato_item_id', $envatoItemId)->first();
        }

        $defaultProductId = config('update_releases.default_product_id');
        $defaultParsed = is_numeric($defaultProductId) ? (int) $defaultProductId : null;
        if ($defaultParsed !== null && $defaultParsed > 0) {
            return Product::query()->find($defaultParsed);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildManifest(
        ?Product $product,
        string $channel,
        ?string $currentVersion = null,
    ): array {
        $normalizedCurrentVersion = $this->normalizeVersion($currentVersion);
        $latestRelease = $this->findLatestPublishedRelease($product, $channel);

        if (! $latestRelease instanceof UpdateRelease) {
            return [
                'channel' => $channel,
                'version' => $normalizedCurrentVersion !== '' ? $normalizedCurrentVersion : '0.0.0',
                'download_url' => '',
                'checksum' => '',
                'min_version' => null,
                'max_version' => null,
                'release_notes' => 'No published release found for this channel.',
                'published_at' => null,
                'is_available' => false,
                'compatible' => true,
            ];
        }

        $releaseVersion = $this->normalizeVersion($latestRelease->version);
        $isAvailable = $normalizedCurrentVersion === ''
            ? true
            : version_compare($releaseVersion, $normalizedCurrentVersion, '>');

        $compatible = true;
        if ($normalizedCurrentVersion !== '') {
            $compatible = $this->isCompatible(
                $normalizedCurrentVersion,
                $latestRelease->min_version,
                $latestRelease->max_version,
            );
        }

        return [
            'channel' => $latestRelease->channel,
            'version' => $latestRelease->version,
            'download_url' => route('updates.releases.download', ['updateRelease' => $latestRelease->id]),
            'checksum' => $latestRelease->checksum,
            'min_version' => $latestRelease->min_version,
            'max_version' => $latestRelease->max_version,
            'release_notes' => $latestRelease->release_notes ?? '',
            'published_at' => $latestRelease->published_at?->toIso8601String(),
            'is_available' => $isAvailable,
            'compatible' => $compatible,
        ];
    }

    public function findLatestPublishedRelease(?Product $product, string $channel): ?UpdateRelease
    {
        $normalizedChannel = $this->normalizeChannel($channel);

        $query = UpdateRelease::query()
            ->where('is_published', true)
            ->where('channel', $normalizedChannel);

        if ($product instanceof Product) {
            $query->where(function ($builder) use ($product): void {
                $builder->where('product_id', $product->id)
                    ->orWhereNull('product_id');
            });
        } else {
            $query->whereNull('product_id');
        }

        /** @var Collection<int, UpdateRelease> $releases */
        $releases = $query->get();

        if ($releases->isEmpty()) {
            return null;
        }

        $productId = $product?->id;

        /** @var array<int, UpdateRelease> $sorted */
        $sorted = $releases->all();
        usort($sorted, function (UpdateRelease $left, UpdateRelease $right) use ($productId): int {
            if ($productId !== null) {
                $leftScore = $left->product_id === $productId ? 0 : 1;
                $rightScore = $right->product_id === $productId ? 0 : 1;

                if ($leftScore !== $rightScore) {
                    return $leftScore <=> $rightScore;
                }
            }

            $versionCompare = version_compare(
                $this->normalizeVersion($right->version),
                $this->normalizeVersion($left->version),
            );

            if ($versionCompare !== 0) {
                return $versionCompare;
            }

            $rightPublished = $right->published_at?->getTimestamp() ?? 0;
            $leftPublished = $left->published_at?->getTimestamp() ?? 0;
            if ($rightPublished !== $leftPublished) {
                return $rightPublished <=> $leftPublished;
            }

            return $right->id <=> $left->id;
        });

        return $sorted[0] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(UpdateRelease $release): array
    {
        return [
            'id' => $release->id,
            'product_id' => $release->product_id,
            'envato_item_id' => $release->product?->envato_item_id,
            'channel' => $release->channel,
            'version' => $release->version,
            'min_version' => $release->min_version,
            'max_version' => $release->max_version,
            'release_notes' => $release->release_notes,
            'checksum' => $release->checksum,
            'size_bytes' => $release->size_bytes,
            'is_published' => $release->is_published,
            'published_at' => $release->published_at?->toIso8601String(),
            'download_url' => route('updates.releases.download', ['updateRelease' => $release->id]),
            'metadata' => $release->metadata,
            'product' => $release->product?->only(['id', 'name', 'envato_item_id']),
            'creator' => $release->creator?->only(['id', 'name', 'email']),
            'created_at' => $release->created_at?->toIso8601String(),
            'updated_at' => $release->updated_at?->toIso8601String(),
        ];
    }

    public function normalizeChannel(?string $channel): string
    {
        $normalized = strtolower(trim((string) $channel));
        if ($normalized === '') {
            $normalized = strtolower((string) config('update_releases.default_channel', 'stable'));
        }

        return $normalized !== '' ? $normalized : 'stable';
    }

    public function normalizeVersion(?string $version): string
    {
        $normalized = trim((string) $version);
        $normalized = ltrim($normalized, 'vV');

        return $normalized;
    }

    private function isCompatible(string $currentVersion, ?string $minVersion, ?string $maxVersion): bool
    {
        $min = $this->normalizeVersion($minVersion);
        $max = $this->normalizeVersion($maxVersion);
        $current = $this->normalizeVersion($currentVersion);

        if ($min !== '' && version_compare($current, $min, '<')) {
            return false;
        }

        if ($max !== '' && version_compare($current, $max, '>')) {
            return false;
        }

        return true;
    }

    private function packageDisk(): string
    {
        $disk = trim((string) config('update_releases.package_disk', 'local'));

        return $disk !== '' ? $disk : 'local';
    }

    private function packageDirectory(): string
    {
        $directory = trim((string) config('update_releases.package_directory', 'updates/releases'), '/');

        return $directory !== '' ? $directory : 'updates/releases';
    }

    private function calculateChecksum(string $diskName, string $path): string
    {
        $disk = Storage::disk($diskName);
        $stream = $disk->readStream($path);
        if (! is_resource($stream)) {
            throw new \RuntimeException('Unable to read package stream for checksum.');
        }

        $context = hash_init('sha256');
        hash_update_stream($context, $stream);
        fclose($stream);

        return hash_final($context);
    }
}

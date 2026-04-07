<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\UpdateRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicUpdateReleaseApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('update_releases.enabled', true);
        config()->set('update_releases.package_disk', 'local');
        config()->set('update_releases.package_directory', 'updates/releases');
        Storage::fake('local');
    }

    public function test_manifest_returns_latest_published_release_for_product_and_channel(): void
    {
        $product = Product::factory()->create([
            'envato_item_id' => 23014826,
        ]);

        UpdateRelease::factory()->for($product)->published()->create([
            'channel' => 'stable',
            'version' => '1.0.0',
            'package_path' => 'updates/releases/stable-1.0.0.zip',
            'checksum' => hash('sha256', 'stable-1.0.0'),
        ]);

        $latest = UpdateRelease::factory()->for($product)->published()->create([
            'channel' => 'stable',
            'version' => '1.2.0',
            'min_version' => '1.0.0',
            'max_version' => '2.0.0',
            'package_path' => 'updates/releases/stable-1.2.0.zip',
            'checksum' => hash('sha256', 'stable-1.2.0'),
        ]);

        UpdateRelease::factory()->for($product)->published()->create([
            'channel' => 'beta',
            'version' => '1.3.0',
            'package_path' => 'updates/releases/beta-1.3.0.zip',
            'checksum' => hash('sha256', 'beta-1.3.0'),
        ]);

        $response = $this->getJson('/api/updates/manifest?product_id='.$product->id.'&channel=stable&current_version=1.0.0');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.version', '1.2.0');
        $response->assertJsonPath('data.is_available', true);
        $response->assertJsonPath('data.compatible', true);
        $response->assertJsonPath('data.channel', 'stable');
        $response->assertJsonPath(
            'data.download_url',
            route('updates.releases.download', ['updateRelease' => $latest->id]),
        );
    }

    public function test_manifest_uses_global_release_when_product_specific_release_is_missing(): void
    {
        $product = Product::factory()->create([
            'envato_item_id' => 1001,
        ]);

        UpdateRelease::factory()->global()->published()->create([
            'channel' => 'stable',
            'version' => '2.1.0',
            'package_path' => 'updates/releases/global-2.1.0.zip',
            'checksum' => hash('sha256', 'global-2.1.0'),
        ]);

        $response = $this->getJson('/api/updates/manifest?envato_item_id=1001&channel=stable&current_version=2.1.0');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.version', '2.1.0');
        $response->assertJsonPath('data.is_available', false);
        $response->assertJsonPath('data.channel', 'stable');
    }

    public function test_manifest_returns_fallback_payload_when_no_release_exists(): void
    {
        $response = $this->getJson('/api/updates/manifest?channel=stable&current_version=3.5.1');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.version', '3.5.1');
        $response->assertJsonPath('data.is_available', false);
        $response->assertJsonPath('data.download_url', '');
        $response->assertJsonPath('data.checksum', '');
    }

    public function test_download_returns_file_for_published_release_and_blocks_unpublished_release(): void
    {
        $published = UpdateRelease::factory()->published()->create([
            'channel' => 'stable',
            'version' => '4.0.0',
            'package_path' => 'updates/releases/stable-4.0.0.zip',
            'checksum' => hash('sha256', 'stable-4.0.0'),
        ]);

        Storage::disk('local')->put($published->package_path, 'zip-content');

        $publishedResponse = $this->get(route('updates.releases.download', ['updateRelease' => $published->id]));

        $publishedResponse->assertOk();
        $publishedResponse->assertHeader('X-Release-Checksum-Sha256', $published->checksum);

        $unpublished = UpdateRelease::factory()->create([
            'is_published' => false,
            'package_path' => 'updates/releases/stable-4.0.1.zip',
            'checksum' => hash('sha256', 'stable-4.0.1'),
        ]);

        Storage::disk('local')->put($unpublished->package_path, 'zip-content-2');

        $unpublishedResponse = $this->getJson(route('updates.releases.download', ['updateRelease' => $unpublished->id]));
        $unpublishedResponse->assertStatus(404);
        $unpublishedResponse->assertJsonPath('success', false);
        $unpublishedResponse->assertJsonPath('error.code', 'NOT_FOUND');
    }
}

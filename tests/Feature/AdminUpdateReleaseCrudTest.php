<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EnvatoItem;
use App\Models\Product;
use App\Models\UpdateRelease;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUpdateReleaseCrudTest extends TestCase
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

    public function test_admin_can_create_update_list_update_and_delete_release(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $product = Product::factory()->create([
            'envato_item_id' => 23014826,
        ]);

        $createResponse = $this->post('/api/v1/admin/update-releases', [
            'product_id' => $product->id,
            'channel' => 'stable',
            'version' => '1.0.0',
            'min_version' => '0.9.0',
            'max_version' => '2.0.0',
            'release_notes' => 'Initial release package',
            'is_published' => true,
            'package' => UploadedFile::fake()->create('release-1.0.0.zip', 64, 'application/zip'),
        ], [
            'Accept' => 'application/json',
        ]);

        $createResponse->assertStatus(201);
        $createResponse->assertJsonPath('success', true);
        $createResponse->assertJsonPath('data.version', '1.0.0');
        $createResponse->assertJsonPath('data.channel', 'stable');
        $createResponse->assertJsonPath('data.product_id', $product->id);
        $createResponse->assertJsonPath('data.is_published', true);

        $releaseId = (int) $createResponse->json('data.id');
        $release = UpdateRelease::query()->findOrFail($releaseId);
        $oldPackagePath = $release->package_path;

        $this->assertDatabaseHas('update_releases', [
            'id' => $releaseId,
            'product_id' => $product->id,
            'channel' => 'stable',
            'version' => '1.0.0',
            'is_published' => true,
        ]);
        Storage::disk('local')->assertExists($oldPackagePath);

        $listResponse = $this->getJson('/api/v1/admin/update-releases?product_id='.$product->id.'&channel=stable');
        $listResponse->assertOk();
        $listResponse->assertJsonPath('success', true);
        $listResponse->assertJsonPath('data.data.0.id', $releaseId);

        $updateResponse = $this->post('/api/v1/admin/update-releases/'.$releaseId, [
            '_method' => 'PUT',
            'version' => '1.0.1',
            'release_notes' => 'Patched release package',
            'is_published' => false,
            'package' => UploadedFile::fake()->create('release-1.0.1.zip', 70, 'application/zip'),
        ], [
            'Accept' => 'application/json',
        ]);

        $updateResponse->assertOk();
        $updateResponse->assertJsonPath('success', true);
        $updateResponse->assertJsonPath('data.version', '1.0.1');
        $updateResponse->assertJsonPath('data.is_published', false);

        $release->refresh();
        Storage::disk('local')->assertMissing($oldPackagePath);
        Storage::disk('local')->assertExists($release->package_path);

        $deleteResponse = $this->deleteJson('/api/v1/admin/update-releases/'.$releaseId);
        $deleteResponse->assertOk();
        $deleteResponse->assertJsonPath('success', true);
        $deleteResponse->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('update_releases', [
            'id' => $releaseId,
        ]);
        Storage::disk('local')->assertMissing($release->package_path);
    }

    public function test_support_user_cannot_manage_update_releases(): void
    {
        $supportUser = User::factory()->create();
        Sanctum::actingAs($supportUser);

        $indexResponse = $this->getJson('/api/v1/admin/update-releases');
        $indexResponse->assertStatus(403);
        $indexResponse->assertJsonPath('error.code', 'FORBIDDEN');

        $storeResponse = $this->post('/api/v1/admin/update-releases', [
            'channel' => 'stable',
            'version' => '1.0.0',
            'package' => UploadedFile::fake()->create('release-1.0.0.zip', 64, 'application/zip'),
        ], [
            'Accept' => 'application/json',
        ]);
        $storeResponse->assertStatus(403);
        $storeResponse->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_admin_can_create_release_by_envato_item_id_and_two_part_versions(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $envatoItem = EnvatoItem::factory()->create([
            'envato_item_id' => 23014826,
        ]);

        $response = $this->post('/api/v1/admin/update-releases', [
            'envato_item_id' => $envatoItem->envato_item_id,
            'channel' => 'stable',
            'version' => '1.1',
            'min_version' => '1.0',
            'max_version' => '1.1',
            'is_published' => true,
            'package' => UploadedFile::fake()->create('release-1.1.0.zip', 64, 'application/zip'),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.version', '1.1.0');
        $response->assertJsonPath('data.min_version', '1.0.0');
        $response->assertJsonPath('data.max_version', '1.1.0');
        $response->assertJsonPath('data.envato_item_id', $envatoItem->envato_item_id);

        $product = Product::query()->where('envato_item_id', $envatoItem->envato_item_id)->first();
        $this->assertNotNull($product);

        $this->assertDatabaseHas('update_releases', [
            'product_id' => $product?->id,
            'channel' => 'stable',
            'version' => '1.1.0',
            'min_version' => '1.0.0',
            'max_version' => '1.1.0',
            'is_published' => true,
        ]);
    }
}

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('update_releases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('channel', 40)->default('stable')->index();
            $table->string('version', 120);
            $table->string('min_version', 120)->nullable();
            $table->string('max_version', 120)->nullable();
            $table->text('release_notes')->nullable();
            $table->string('package_path', 500);
            $table->string('checksum', 128);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->boolean('is_published')->default(false)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'channel', 'is_published']);
            $table->unique(['product_id', 'channel', 'version'], 'update_releases_unique_product_channel_version');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('update_releases');
    }
};

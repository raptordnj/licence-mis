<?php

declare(strict_types=1);

use App\Enums\ProductStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('envato_item_id')->unique();
            $table->string('name');
            $table->unsignedSmallInteger('activation_limit')->default(1);
            $table->string('status', 20)->default(ProductStatus::ACTIVE->value)->index();
            $table->boolean('strict_domain_binding')->default(true);
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

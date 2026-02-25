<?php

declare(strict_types=1);

use App\Enums\EnvatoItemStatus;
use App\Enums\Marketplace;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('envato_items', function (Blueprint $table): void {
            $table->id();
            $table->string('marketplace', 40)->default(Marketplace::ENVATO->value)->index();
            $table->unsignedBigInteger('envato_item_id')->unique();
            $table->string('name');
            $table->string('status', 40)->default(EnvatoItemStatus::ACTIVE->value)->index();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('envato_items');
    }
};

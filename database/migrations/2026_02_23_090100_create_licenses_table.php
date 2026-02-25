<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table): void {
            $table->id();
            $table->string('purchase_code')->unique();
            $table->string('marketplace', 40)->index();
            $table->unsignedBigInteger('envato_item_id')->nullable()->index();
            $table->string('status', 40)->index();
            $table->string('bound_domain')->nullable()->index();
            $table->timestamp('supported_until')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};

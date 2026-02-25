<?php

declare(strict_types=1);

use App\Enums\LicenseInstanceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('license_instances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('license_id')->constrained('licenses')->cascadeOnDelete();
            $table->uuid('instance_id')->index();
            $table->string('domain')->index();
            $table->string('app_url');
            $table->string('ip', 64)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamp('activated_at')->nullable()->index();
            $table->timestamp('deactivated_at')->nullable()->index();
            $table->string('status', 20)->default(LicenseInstanceStatus::ACTIVE->value)->index();
            $table->timestamps();

            $table->unique(['license_id', 'instance_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_instances');
    }
};

<?php

declare(strict_types=1);

use App\Enums\LicenseCheckResult;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('license_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('license_instance_id')->nullable()->constrained('license_instances')->nullOnDelete();
            $table->timestamp('checked_at')->index();
            $table->string('result', 20)->default(LicenseCheckResult::INVALID->value)->index();
            $table->string('reason', 60)->nullable()->index();
            $table->json('request_payload');
            $table->json('response_payload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_checks');
    }
};

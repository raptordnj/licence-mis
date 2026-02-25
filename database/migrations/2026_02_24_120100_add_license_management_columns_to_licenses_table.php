<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table): void {
            if (! Schema::hasColumn('licenses', 'product_id')) {
                $table->foreignId('product_id')->nullable()->after('id')->index();
                $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            }

            if (! Schema::hasColumn('licenses', 'buyer')) {
                $table->string('buyer')->nullable()->after('purchase_code')->index();
            }

            if (! Schema::hasColumn('licenses', 'notes')) {
                $table->text('notes')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table): void {
            if (Schema::hasColumn('licenses', 'product_id')) {
                $table->dropConstrainedForeignId('product_id');
            }

            if (Schema::hasColumn('licenses', 'buyer')) {
                $table->dropColumn('buyer');
            }

            if (Schema::hasColumn('licenses', 'notes')) {
                $table->dropColumn('notes');
            }
        });
    }
};

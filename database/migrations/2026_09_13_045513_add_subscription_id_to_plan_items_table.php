<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a generated plan item back to the subscription it came from (SUB-04).
 *
 * The column is in the original schema but was never created, and a merged migration is
 * never edited, so it arrives here instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_items', function (Blueprint $table): void {
            // Removing a subscription leaves its plan items behind rather than taking a
            // hole out of a finished year; the sync then clears them up.
            $table->foreignId('subscription_id')
                ->nullable()
                ->after('salary_model_id')
                ->constrained('subscriptions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('plan_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('subscription_id');
        });
    }
};

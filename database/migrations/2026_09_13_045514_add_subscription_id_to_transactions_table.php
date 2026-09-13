<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a transaction to the subscription it paid for (SUB-06).
 *
 * Set automatically when the subscription's own subcategory is chosen, so the user never
 * has to think about it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->foreignId('subscription_id')
                ->nullable()
                ->after('account_id')
                ->constrained('subscriptions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('subscription_id');
        });
    }
};

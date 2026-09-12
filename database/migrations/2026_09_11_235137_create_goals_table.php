<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Goals are a milestone 5 feature. The table exists now because provisioning a new
     * account creates its emergency fund goal (USR-04). The nullable financial_year_id
     * that year-end-balance goals need arrives with the financial years table.
     */
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('name');
            $table->unsignedBigInteger('target_amount_cents')->nullable();
            $table->boolean('target_is_custom')->default(false);
            $table->unsignedBigInteger('current_amount_cents')->default(0);
            $table->unsignedBigInteger('monthly_contribution_cents')->nullable();
            $table->date('target_date')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'archived_at']);
        });
    }
};

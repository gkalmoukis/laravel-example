<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('net_worth_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('net_worth_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_year_id')->constrained()->cascadeOnDelete();
            // Month 0 is the opening position: where the year starts, before January.
            $table->unsignedTinyInteger('month');
            // Debts are stored as a positive outstanding balance and subtracted when net
            // worth is computed, so no column ever holds a negative amount.
            $table->unsignedBigInteger('value_cents')->default(0);
            $table->timestamps();

            // Named explicitly: the generated name would exceed MySQL's 64-character
            // identifier limit.
            $table->unique(['net_worth_item_id', 'financial_year_id', 'month'], 'net_worth_snapshots_item_year_month_unique');
            $table->index(['financial_year_id', 'month']);
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Twelve rows per plan item, zero where nothing is planned, so every month of the
     * year is addressable without the reader having to infer absent months.
     */
    public function up(): void
    {
        Schema::create('plan_item_amounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->unsignedBigInteger('amount_cents')->default(0);
            $table->timestamps();

            $table->unique(['plan_item_id', 'month']);
        });
    }
};

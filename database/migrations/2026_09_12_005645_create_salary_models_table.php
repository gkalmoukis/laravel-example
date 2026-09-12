<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_models', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('financial_year_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name')->default('Salary');
            $table->unsignedBigInteger('base_amount_cents');
            // Per-payment settings: whether each bonus applies, in which month, and
            // whether it is a multiple of the salary or a fixed amount.
            $table->json('payments');
            $table->timestamps();
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_years', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->timestamp('setup_completed_at')->nullable();
            // The plan as it stood when setup finished, so deviation can be measured
            // against what was originally intended rather than the plan as edited since.
            $table->json('baseline')->nullable();
            $table->timestamp('baseline_captured_at')->nullable();
            $table->foreignId('copied_from_id')->nullable()->constrained('financial_years')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'year']);
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('financial_year_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('kind');
            // Categories are never deleted, only deactivated, so a plan item can always
            // resolve the category it was filed under.
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->string('name');
            $table->string('frequency');
            $table->unsignedTinyInteger('start_month');
            $table->unsignedTinyInteger('payment_day')->nullable();
            $table->boolean('is_fixed')->default(true);
            $table->string('allocation');
            // Generated items are rebuilt from their source; manual ones are the user's
            // own and are never overwritten.
            $table->string('source');
            $table->foreignId('salary_model_id')->nullable()->constrained()->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['financial_year_id', 'type']);
            $table->index(['financial_year_id', 'category_id']);
            $table->index(['financial_year_id', 'source']);
        });
    }
};

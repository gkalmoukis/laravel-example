<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            // Restricted rather than cascading: a parent with children is deactivated,
            // never deleted, so historical data stays valid (CAT-04).
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->string('type');
            $table->string('name');
            $table->string('system_key')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_essential')->default(false);
            $table->boolean('is_irregular')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            // MySQL treats NULLs as distinct in unique indexes, so neither of these stops
            // duplicates among top-level categories, where parent_id is NULL. They are a
            // backstop; the form requests enforce uniqueness for that case.
            $table->unique(['user_id', 'parent_id', 'name']);
            $table->unique(['user_id', 'system_key']);
            $table->index(['user_id', 'type', 'is_active']);
        });
    }
};

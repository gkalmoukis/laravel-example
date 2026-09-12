<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            // A calendar date, not a moment: a transaction belongs to the month its date
            // falls in, and "today" is decided in the user's own timezone.
            $table->date('occurred_on');
            $table->unsignedBigInteger('amount_cents');
            // Categories are deactivated rather than deleted, so a transaction can always
            // resolve what it was filed under.
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('description');
            $table->text('notes')->nullable();
            // How the entry was made and how long it took, so the promise that a
            // transaction takes seconds can actually be measured (MET-01).
            $table->string('entry_source');
            $table->unsignedInteger('entry_duration_ms')->nullable();
            $table->timestamps();

            // The three shapes every report reads by.
            $table->index(['user_id', 'occurred_on']);
            $table->index(['user_id', 'category_id', 'occurred_on']);
            $table->index(['user_id', 'type', 'occurred_on']);
        });
    }
};

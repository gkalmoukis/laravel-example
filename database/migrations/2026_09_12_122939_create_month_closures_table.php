<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Only completion is stored. A month's status is otherwise derived: it is in progress
     * once anything is recorded in it, and not started before that (MON-01).
     *
     * The table arrives with transactions rather than with the month screens, because
     * editing a transaction in a completed month is refused from the start (TXV-02).
     */
    public function up(): void
    {
        Schema::create('month_closures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('financial_year_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['financial_year_id', 'month']);
        });
    }
};

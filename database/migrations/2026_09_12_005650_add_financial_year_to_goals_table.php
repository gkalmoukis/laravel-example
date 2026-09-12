<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Year-end-balance goals belong to a specific year. The column waited for the
     * financial years table, which did not exist when goals were introduced.
     */
    public function up(): void
    {
        Schema::table('goals', function (Blueprint $table): void {
            $table->foreignId('financial_year_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('goals', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('financial_year_id');
        });
    }
};

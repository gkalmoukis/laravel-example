<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('user_id')->unique()->constrained()->cascadeOnDelete();
            // Fixed in v1 and shown read-only, but stored so they can become editable
            // without a migration (PREF-02).
            $table->char('currency', 3)->default('EUR');
            $table->string('format_locale')->default('el-GR');
            $table->string('timezone')->default('Europe/Athens');
            $table->unsignedTinyInteger('salary_payments')->default(14);
            $table->foreignId('default_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->unsignedTinyInteger('emergency_fund_months')->default(6);
            $table->unsignedTinyInteger('budget_warning_threshold_percent')->default(10);
            $table->timestamps();
        });
    }
};

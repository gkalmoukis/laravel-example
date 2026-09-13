<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('amount_cents');
            $table->string('frequency');
            // Any one charge, past or future. The next billing date is worked out from
            // this rather than stored, so it can never go stale (SUB-03).
            $table->date('billing_anchor_date');
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            // Created from the subscription's own name, so transactions can be recorded
            // against it (SUB-02).
            $table->foreignId('subcategory_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            // The day it stopped being charged. Months after this are zeroed in the plan
            // rather than removed, so the year still shows what was paid (SUB-04).
            $table->date('deactivated_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};

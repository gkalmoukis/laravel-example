<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('email');
            // Only the hash is stored; the plaintext token exists solely in the emailed
            // link, so a database leak cannot be used to accept an invitation (INV-03).
            $table->string('token_hash', 64)->unique();
            // INV-02 does not list this column, but INV-08's `app:invite --admin` and
            // DEPLOY-11's first production admin have no other way to grant the flag, so
            // the invitation carries what the accepted account becomes.
            $table->boolean('is_admin')->default(false);
            $table->foreignUuid('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            // "At most one pending invitation per email" cannot be expressed as a unique
            // index, because pending is a function of three nullable columns plus the
            // clock. CreateInvitation enforces it; this index keeps the lookup cheap.
            $table->index(['email', 'accepted_at', 'revoked_at']);
        });
    }
};

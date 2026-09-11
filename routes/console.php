<?php

declare(strict_types=1);

use App\Models\Invitation;
use Illuminate\Support\Facades\Schedule;

// Expired, revoked and accepted invitations are pruned after their retention period
// (INV-11). Pruning is idempotent, so a missed run simply catches up (ARCH-16).
Schedule::command('model:prune', ['--model' => [Invitation::class]])->daily();

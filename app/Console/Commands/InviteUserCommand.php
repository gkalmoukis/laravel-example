<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\CreateInvitation;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

/**
 * Creates an invitation from the console and prints the link.
 *
 * This is the bootstrap path for the very first account, since there is no public
 * registration, and the same command creates the first production admin over SSH
 * (INV-08, DEPLOY-11).
 */
#[Description('Invite someone to create an account and print their invitation link')]
#[Signature('app:invite {email : The address to invite} {--admin : Allow the new account to invite others}')]
final class InviteUserCommand extends Command
{
    public function handle(CreateInvitation $action): int
    {
        $email = mb_strtolower(mb_trim((string) $this->argument('email')));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            error(sprintf('"%s" is not a valid email address.', $email));

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            error(sprintf('%s already has an account.', $email));

            return self::FAILURE;
        }

        ['token' => $token] = $action->handle($email, null, (bool) $this->option('admin'));

        info(sprintf('Invitation sent to %s.', $email));
        note(route('invitation-acceptance.create', ['token' => $token]));

        return self::SUCCESS;
    }
}

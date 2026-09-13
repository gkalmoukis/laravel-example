<?php

declare(strict_types=1);

use App\Actions\CompleteMonth;
use App\Actions\CreateTransaction;
use App\Enums\EntrySource;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\ValueObjects\Money;
use Carbon\CarbonImmutable;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;

/*
 * NFR-05: what somebody earns, spends and owes never reaches a log, an exception's
 * context, or the health endpoint. The architecture test bans logging calls outright;
 * these check the behaviour around the edges, where a leak would come from the framework
 * rather than from our own code.
 */

const SECRET_DESCRIPTION = 'Divorce lawyer retainer';

const SECRET_CENTS = 987_654;

it('writes nothing to the log while recording a transaction', function (): void {
    [$user] = userWithYear();

    Log::spy();

    resolve(CreateTransaction::class)->handle(
        $user,
        [
            'type' => TransactionType::Expense,
            'category_id' => $user->categories()->where('name', 'Housing')->whereNull('parent_id')->firstOrFail()->id,
            'description' => SECRET_DESCRIPTION,
            'entry_source' => EntrySource::Form,
        ],
        Money::fromCents(SECRET_CENTS),
        CarbonImmutable::parse('2027-03-05'),
    );

    Log::shouldNotHaveReceived('info');
    Log::shouldNotHaveReceived('debug');
    Log::shouldNotHaveReceived('warning');
    Log::shouldNotHaveReceived('error');

    expect($user->transactions()->sole()->description)->toBe(SECRET_DESCRIPTION);
});

it('writes nothing to the log while finishing a month', function (): void {
    [$user, $year] = userWithYear();

    Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => $user->categories()->where('name', 'Housing')->whereNull('parent_id')->firstOrFail()->id,
        'occurred_on' => '2027-01-05',
        'amount_cents' => SECRET_CENTS,
        'description' => SECRET_DESCRIPTION,
    ]);

    Log::spy();

    resolve(CompleteMonth::class)->handle($year, 1, CarbonImmutable::parse('2027-02-01'));

    Log::shouldNotHaveReceived('info');
    Log::shouldNotHaveReceived('error');
});

it('writes nothing to the log while a transaction is recorded through the interface', function (): void {
    [$user] = userWithYear();

    Log::spy();

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => TransactionType::Expense->value,
            'amount' => '9.876,54',
            'occurred_on' => '2027-03-05',
            'category_id' => $user->categories()->where('name', 'Housing')->whereNull('parent_id')->firstOrFail()->id,
            'description' => SECRET_DESCRIPTION,
        ])
        ->assertSessionHasNoErrors();

    Log::shouldNotHaveReceived('info');
    Log::shouldNotHaveReceived('error');
});

it('keeps an amount out of the message when a form is rejected', function (): void {
    [$user] = userWithYear();

    // The message explains the format rather than quoting what was typed, so a validation
    // failure written to a log by any layer above us carries nothing of the user's.
    $this->actingAs($user)
        ->from(route('transactions.index'))
        ->post(route('transactions.store'), [
            'type' => TransactionType::Expense->value,
            'amount' => 'nine hundred and eighty seven',
            'occurred_on' => '2027-03-05',
            'category_id' => $user->categories()->where('name', 'Housing')->whereNull('parent_id')->firstOrFail()->id,
            'description' => SECRET_DESCRIPTION,
        ])
        ->assertSessionHasErrors(['amount' => 'Enter an amount like 1.234,56.']);
});

it('reports an exception with the user id and nothing of what they recorded', function (): void {
    [$user] = userWithYear();

    Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => $user->categories()->where('name', 'Housing')->whereNull('parent_id')->firstOrFail()->id,
        'occurred_on' => '2027-03-05',
        'amount_cents' => SECRET_CENTS,
        'description' => SECRET_DESCRIPTION,
    ]);

    $context = null;

    Log::listen(function (MessageLogged $message) use (&$context): void {
        $context = $message->context;
    });

    $this->actingAs($user);

    // Nothing configures exception context, so what Laravel writes is the signed-in user
    // and the throwable. This test is what stops a context callback quietly adding the
    // request body later on.
    report(new RuntimeException('Something went wrong'));

    $written = json_encode($context === null ? [] : array_keys($context));

    expect($context)->toBeArray()
        ->and($context['userId'] ?? null)->toBe($user->id)
        ->and($written)->not->toContain('amount')
        ->and($written)->not->toContain('description')
        ->and(print_r($context['exception'] ?? '', true))->not->toContain(SECRET_DESCRIPTION);
});

it('says nothing about anyone money on the health endpoint', function (): void {
    [$user] = userWithYear();

    Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => $user->categories()->where('name', 'Housing')->whereNull('parent_id')->firstOrFail()->id,
        'occurred_on' => '2027-03-05',
        'amount_cents' => SECRET_CENTS,
        'description' => SECRET_DESCRIPTION,
    ]);

    $body = $this->get('/up')->assertOk()->getContent();

    expect($body)->not->toContain(SECRET_DESCRIPTION)
        ->and($body)->not->toContain((string) SECRET_CENTS)
        ->and($body)->not->toContain($user->email);
});

it('answers the health endpoint without signing in', function (): void {
    $this->get('/up')->assertOk();
});

<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\BudgetCellController;
use App\Http\Controllers\CategoryActivationController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\FinancialYearController;
use App\Http\Controllers\InvitationAcceptanceController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\InvitationResendController;
use App\Http\Controllers\OpeningPositionController;
use App\Http\Controllers\PlanBaselineController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\PlanItemController;
use App\Http\Controllers\PreferencesController;
use App\Http\Controllers\SalaryModelController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SubcategoryParentController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserEmailResetNotificationController;
use App\Http\Controllers\UserEmailVerificationController;
use App\Http\Controllers\UserEmailVerificationNotificationController;
use App\Http\Controllers\UserPasswordController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\UserTwoFactorAuthenticationController;
use App\Http\Controllers\YearSetupCompletionController;
use App\Http\Controllers\YearSetupController;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

Route::get('/', fn () => Inertia::render('welcome'))->name('home');

// Application routes. Every one of these requires a verified email address (VER-01);
// the only authenticated routes outside this group are the ones a user must be able to
// reach *before* verifying, plus logout.
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', function (Request $request): RedirectResponse|Response {
        $user = $request->user();

        // Nothing to show without a plan, so a new account is sent to make one first
        // rather than shown six empty cards (YEAR-08).
        if ($user instanceof User && $user->financialYears()->doesntExist()) {
            return to_route('financial-years.create');
        }

        return Inertia::render('dashboard');
    })->name('dashboard');

    // User...
    Route::delete('user', [UserController::class, 'destroy'])->name('user.destroy');

    // User Profile...
    Route::redirect('settings', '/settings/profile');
    Route::get('settings/profile', [UserProfileController::class, 'edit'])->name('user-profile.edit');
    Route::patch('settings/profile', [UserProfileController::class, 'update'])->name('user-profile.update');

    // User Password...
    Route::get('settings/password', [UserPasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [UserPasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');

    // Appearance...
    Route::get('settings/appearance', fn () => Inertia::render('appearance/update'))->name('appearance.edit');

    // User Two-Factor Authentication...
    Route::get('settings/two-factor', [UserTwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');

    // Accounts...
    Route::get('settings/accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::post('settings/accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::patch('settings/accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
    Route::delete('settings/accounts/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy');

    // Financial years. {year} binds by the calendar year rather than the id, so the
    // selected year is legible in the address bar (YEAR-07).
    Route::get('years/create', [FinancialYearController::class, 'create'])->name('financial-years.create');
    Route::post('years', [FinancialYearController::class, 'store'])->name('financial-years.store');

    // Setup wizard...
    Route::get('years/{year}/setup/{step}', [YearSetupController::class, 'show'])->name('year-setup.show');
    Route::post('years/{year}/setup/completion', [YearSetupCompletionController::class, 'store'])->name('year-setup-completion.store');

    // Plan...
    Route::patch('years/{year}/opening-position', [OpeningPositionController::class, 'update'])->name('opening-position.update');
    Route::patch('years/{year}/salary', [SalaryModelController::class, 'update'])->name('salary-model.update');
    Route::delete('years/{year}/salary', [SalaryModelController::class, 'destroy'])->name('salary-model.destroy');
    Route::post('years/{year}/baseline', [PlanBaselineController::class, 'store'])->name('plan-baseline.store');
    Route::get('years/{year}/plan/{tab}', [PlanController::class, 'show'])->name('plan.show');
    Route::post('years/{year}/plan-items', [PlanItemController::class, 'store'])->name('plan-items.store');
    Route::patch('years/{year}/plan-items/{planItem}', [PlanItemController::class, 'update'])->name('plan-items.update');
    Route::delete('years/{year}/plan-items/{planItem}', [PlanItemController::class, 'destroy'])->name('plan-items.destroy');
    Route::patch('years/{year}/budget-cell', [BudgetCellController::class, 'update'])->name('budget-cell.update');

    // Transactions. Not scoped to a year: a transaction belongs to whichever year
    // contains its date, and may be recorded before that year exists (TXQ-08).
    Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::post('transactions', [TransactionController::class, 'store'])->name('transactions.store');
    Route::patch('transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
    Route::delete('transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');

    // Preferences...
    Route::get('settings/preferences', [PreferencesController::class, 'edit'])->name('preferences.edit');
    Route::patch('settings/preferences', [PreferencesController::class, 'update'])->name('preferences.update');

    // Categories...
    Route::get('settings/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('settings/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::patch('settings/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('settings/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    Route::post('settings/categories/{category}/activation', [CategoryActivationController::class, 'store'])->name('category-activation.store');
    Route::patch('settings/categories/{category}/parent', [SubcategoryParentController::class, 'update'])->name('subcategory-parent.update');

    // Invitations (admins only, enforced by InvitationPolicy)...
    Route::get('settings/invitations', [InvitationController::class, 'index'])->name('invitations.index');
    Route::post('settings/invitations', [InvitationController::class, 'store'])->name('invitations.store');
    Route::delete('settings/invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');
    Route::post('settings/invitations/{invitation}/resend', [InvitationResendController::class, 'store'])->name('invitation-resend.store');
});

// Invitation acceptance. Rate limited because the token is the only secret, and every
// failure renders the same neutral page (INV-07).
Route::middleware(['guest', 'throttle:10,1'])->group(function (): void {
    Route::get('invitations/{token}', [InvitationAcceptanceController::class, 'create'])
        ->name('invitation-acceptance.create');
    Route::post('invitations/{token}', [InvitationAcceptanceController::class, 'store'])
        ->name('invitation-acceptance.store');
});

Route::middleware('guest')->group(function (): void {
    // User Password...
    Route::get('reset-password/{token}', [UserPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('reset-password', [UserPasswordController::class, 'store'])
        ->name('password.store');

    // User Email Reset Notification...
    Route::get('forgot-password', [UserEmailResetNotificationController::class, 'create'])
        ->name('password.request');
    Route::post('forgot-password', [UserEmailResetNotificationController::class, 'store'])
        ->name('password.email');

    // Session...
    Route::get('login', [SessionController::class, 'create'])
        ->name('login');
    Route::post('login', [SessionController::class, 'store'])
        ->name('login.store');
});

// Reachable while unverified, by necessity.
Route::middleware('auth')->group(function (): void {
    // User Email Verification...
    Route::get('verify-email', [UserEmailVerificationNotificationController::class, 'create'])
        ->name('verification.notice');
    Route::post('email/verification-notification', [UserEmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // User Email Verification...
    Route::get('verify-email/{id}/{hash}', [UserEmailVerificationController::class, 'update'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    // Session...
    Route::post('logout', [SessionController::class, 'destroy'])
        ->name('logout');
});

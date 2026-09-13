<?php

declare(strict_types=1);

use App\Models\SalaryModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;

arch()->preset()->php();
arch()->preset()->strict();
// SalaryModel is exempt from the no-"Model"-suffix rule. The suffix is not redundant
// there: a "salary model" is the domain's own term for the arrangement of payments
// across the year, and the table is named for it too.
arch()->preset()->laravel()->ignoring([
    SalaryModel::class,
]);
arch()->preset()->security()->ignoring([
    'assert',
]);

arch('controllers')
    ->expect('App\Http\Controllers')
    ->not->toBeUsed();

arch('actions are final, readonly and do one thing')
    ->expect('App\Actions')
    ->toBeFinal()
    ->toBeReadonly()
    ->toHaveMethod('handle');

arch('env is only read inside config')
    ->expect('env')
    ->not->toBeUsed();

arch('no debugging helpers survive')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die', 'exit'])
    ->not->toBeUsed();

arch('models do not reach into the request lifecycle')
    ->expect('App\Models')
    ->not->toUse([Request::class, Auth::class]);

it('exposes only resource methods on controllers', function (): void {
    // The seven resource methods (ARCH-02), plus Laravel's own HasMiddleware hook and
    // the constructor, which is how dependencies arrive rather than an exposed action.
    $allowed = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy', 'middleware', '__construct'];

    $controllers = collect(File::allFiles(app_path('Http/Controllers')))
        ->map(fn (SplFileInfo $file): string => 'App\\Http\\Controllers\\'.$file->getFilenameWithoutExtension());

    expect($controllers)->not->toBeEmpty();

    foreach ($controllers as $controller) {
        $methods = collect(new ReflectionClass($controller)->getMethods(ReflectionMethod::IS_PUBLIC))
            ->filter(fn (ReflectionMethod $method): bool => $method->class === $controller)
            ->map(fn (ReflectionMethod $method): string => $method->getName())
            ->values()
            ->all();

        expect(array_diff($methods, $allowed))
            ->toBe([], sprintf('%s exposes a non-resource method.', $controller));
    }
});

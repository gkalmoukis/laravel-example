<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/*
 * Colour is a decision the theme makes, not one each screen makes for itself (§5.2).
 *
 * Before this guard, eight files reached past the semantic tokens for a literal
 * `amber-700` or `emerald-400`, which meant the status palette was defined in one place
 * and contradicted in another — and a theme change could not reach them. The tokens are
 * `--status-ok|warning|over`, `--series-plan|actual|forecast`, and the shadcn set
 * (`destructive`, `muted-foreground`, `accent`, and the rest).
 */

/**
 * Tailwind's built-in colour scales. A utility naming one of these is a colour chosen
 * outside the theme.
 */
function tokenPalettes(): string
{
    return implode('|', [
        'slate', 'gray', 'zinc', 'neutral', 'stone',
        'red', 'orange', 'amber', 'yellow', 'lime', 'green', 'emerald',
        'teal', 'cyan', 'sky', 'blue', 'indigo', 'violet', 'purple',
        'fuchsia', 'pink', 'rose',
    ]);
}

/**
 * The utility prefixes that take a colour.
 */
function tokenProperties(): string
{
    return implode('|', [
        'text', 'bg', 'border', 'ring', 'fill', 'stroke', 'from', 'to', 'via',
        'outline', 'decoration', 'shadow', 'accent', 'caret', 'divide', 'placeholder',
    ]);
}

/**
 * Every hand-written source file. The generated Wayfinder output and the vendored shadcn
 * primitives in `components/ui` are excluded: the first is regenerated and the second is
 * upstream's, already written against the same tokens.
 *
 * @return list<SplFileInfo>
 */
function tokenSourceFiles(): array
{
    $root = base_path('resources/js');

    $files = [];

    foreach (File::allFiles($root) as $file) {
        if (! in_array($file->getExtension(), ['ts', 'tsx'], true)) {
            continue;
        }

        $relative = str_replace($root.DIRECTORY_SEPARATOR, '', $file->getPathname());

        if (str_starts_with($relative, 'actions/') || str_starts_with($relative, 'routes/')) {
            continue;
        }

        if (str_starts_with($relative, 'components/ui/')) {
            continue;
        }

        $files[] = $file;
    }

    return $files;
}

it('picks every colour from the theme rather than a Tailwind palette', function (): void {
    $pattern = '/\b('.tokenProperties().')-('.tokenPalettes().')-[0-9]{2,3}\b/';

    $offenders = [];

    foreach (tokenSourceFiles() as $file) {
        if (preg_match_all($pattern, (string) file_get_contents($file->getPathname()), $matches) > 0) {
            $offenders[$file->getRelativePathname()] = array_unique($matches[0]);
        }
    }

    expect($offenders)->toBe([]);
});

it('keeps the card and the page distinguishable in both themes', function (): void {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    // A card that shares the page's colour is not a card. This held in light mode and
    // was wrong in dark, where --card and --background were the same value.
    foreach (['light' => ':root {', 'dark' => '.dark {'] as $block) {
        $body = tokenBlock($css, $block);

        expect(tokenValue($body, '--card'))->not->toBe(tokenValue($body, '--background'));
    }
});

it('keeps the status hues clear of the brand', function (): void {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    $body = tokenBlock($css, ':root {');

    // Green, amber and red have to stay unmistakable, so none of them may drift onto the
    // brand's hue the way --series-forecast deliberately did.
    foreach (['--status-ok', '--status-warning', '--status-over'] as $token) {
        expect(tokenHue($body, $token))->not->toBe(tokenHue($body, '--primary'));
    }
});

function tokenBlock(string $css, string $opening): string
{
    $start = mb_strpos($css, $opening);

    expect($start)->not->toBeFalse();

    $end = mb_strpos($css, '}', (int) $start);

    expect($end)->not->toBeFalse();

    return mb_substr($css, (int) $start, (int) $end - (int) $start);
}

function tokenValue(string $block, string $token): string
{
    preg_match('/'.preg_quote($token, '/').':\s*([^;]+);/', $block, $matches);

    expect($matches)->not->toBeEmpty("Expected {$token} to be defined.");

    return mb_trim($matches[1]);
}

function tokenHue(string $block, string $token): string
{
    $parts = preg_split('/\s+/', mb_trim(tokenValue($block, $token), 'oklch() '));

    expect($parts)->toHaveCount(3);

    return $parts[2];
}

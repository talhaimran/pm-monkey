<?php

use Symfony\Component\Finder\Finder;

/** @return array<string, string> path => contents of every file under resources/js */
function uiSources(string $pattern = '/\.(tsx|ts|css)$/'): array
{
    $files = [];
    foreach (Finder::create()->files()->in([resource_path('js'), resource_path('css')])->name($pattern) as $file) {
        $files[$file->getRelativePathname()] = $file->getContents();
    }

    return $files;
}

it('has no Jira tokens', function () {
    $config = file_get_contents(base_path('tailwind.config.js'));
    expect($config)->toContain('docs/design-system/tokens.json')
        ->not->toContain('Jira')
        ->not->toContain('loz-');

    $jira = '/\b(bg|text|border|ring|ring-offset|divide|outline|fill|stroke|placeholder|shadow)-(ink-subtle|surface|sunken|raised|input-line|brand|brand-hover|on-brand|selected|neutral|neutral-hover|danger|danger-subtle|warning-subtle|warning-ink|success|loz-[a-z-]+)\b/';
    $hits = [];
    foreach (uiSources() as $path => $source) {
        if (preg_match_all($jira, $source, $m)) {
            $hits[$path] = array_values(array_unique($m[0]));
        }
    }

    expect($hits)->toBe([]);
});

it('exposes every design system colour in light and dark', function () {
    $tokens = json_decode(file_get_contents(base_path('docs/design-system/tokens.json')), true);

    foreach ($tokens['color']['tokens'] as $token) {
        expect($token['value'])->toHaveKeys(['light', 'dark'], "{$token['name']} needs light and dark values");
    }
});

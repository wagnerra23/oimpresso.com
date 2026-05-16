<?php

declare(strict_types=1);

test('Cliente/Create.tsx — structure + AppShellV2 + useForm', function () {
    $tsxPath = __DIR__ . '/../../../resources/js/Pages/Cliente/Create.tsx';
    expect($tsxPath)->toBeReadableFile();

    $contents = file_get_contents($tsxPath);
    expect($contents)
        ->toContain('AppShellV2')
        ->toContain('useForm')
        ->toContain('export default function ClienteCreate')
        ->toContain("post('/contacts'")
        ->toContain('Novo cliente')          // PT-BR
        ->not->toContain(': any');
});

test('Cliente/Create.charter.md — ADR 0149 YAML', function () {
    $charterPath = __DIR__ . '/../../../resources/js/Pages/Cliente/Create.charter.md';
    expect($charterPath)->toBeReadableFile();

    $contents = file_get_contents($charterPath);
    expect($contents)
        ->toContain('mwart_pattern_reuse:')
        ->toContain('Create');
});

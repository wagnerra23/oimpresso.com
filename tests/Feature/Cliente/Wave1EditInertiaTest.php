<?php

declare(strict_types=1);

test('Cliente/Edit.tsx — structure + AppShellV2 + useForm PUT', function () {
    $tsxPath = __DIR__ . '/../../../resources/js/Pages/Cliente/Edit.tsx';
    expect($tsxPath)->toBeReadableFile();

    $contents = file_get_contents($tsxPath);
    expect($contents)
        ->toContain('AppShellV2')
        ->toContain('useForm')
        ->toContain('put(`/contacts/')
        ->toContain('export default function ClienteEdit')
        ->toContain('Editar cliente')
        ->not->toContain(': any');
});

test('Cliente/Edit.charter.md — ADR 0149 YAML', function () {
    $charterPath = __DIR__ . '/../../../resources/js/Pages/Cliente/Edit.charter.md';
    expect($charterPath)->toBeReadableFile();
    expect(file_get_contents($charterPath))
        ->toContain('mwart_pattern_reuse:')
        ->toContain('Edit');
});

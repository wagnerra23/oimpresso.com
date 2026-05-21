<?php

declare(strict_types=1);

// Onda Final.B — Tab Atividades (activity log)
// Teste estrutural: componente + integração + scope subject_id (Spatie\Activitylog).

test('ActivitiesTab.tsx — estrutura mínima componente', function () {
    $path = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/ActivitiesTab.tsx';
    expect($path)->toBeReadableFile();

    $contents = file_get_contents($path);
    expect($contents)
        ->toContain('export default function ActivitiesTab')
        ->toContain('data-testid="activities-tab-root"')
        ->toContain('data-testid="activities-tab-empty"')
        ->toContain('data-testid="activities-tab-skeleton"')
        ->not->toContain(': any');
});

test('ActivitiesTab.tsx — colunas Data/Ação/Por/Nota + badges Automático/from_api', function () {
    $path = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/ActivitiesTab.tsx';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('>Data<')
        ->toContain('>Ação<')
        ->toContain('>Por<')
        ->toContain('>Nota<')
        ->toContain('Automático')
        ->toContain('is_automatic')
        ->toContain('from_api');
});

test('Cliente/Show.tsx — integra ActivitiesTab como 5ª tab', function () {
    $path = __DIR__ . '/../../../../resources/js/Pages/Cliente/Show.tsx';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("import ActivitiesTab")
        ->toContain("'activities'")
        ->toContain("label: 'Atividades'")
        ->toContain('<ActivitiesTab')
        ->toContain('data="activities"');
});

test('ContactController — Show injeta activities defer com scope Spatie subject_id', function () {
    $path = __DIR__ . '/../../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("'activities' => Inertia::defer")
        ->toContain("Activity::forSubject(\$contact)")
        ->toContain("'description_label'")
        ->toContain("is_automatic")
        ->toContain("update_note");
});

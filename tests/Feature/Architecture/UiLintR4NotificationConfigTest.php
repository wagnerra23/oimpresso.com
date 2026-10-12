<?php

declare(strict_types=1);

use App\Console\Commands\UiLintCommand;

function notificationConfigR4(string $path, string $content): array
{
    return (new ReflectionMethod(UiLintCommand::class, 'checkR4'))
        ->invoke(new UiLintCommand, $path, $content);
}

it('aceita o rail de configuração aprovado com PageHeader e sem tabela', function () {
    expect(notificationConfigR4('resources/js/Pages/NotificationTemplate/Index.tsx', '<PageHeader />'))
        ->toBeEmpty();
});

it('continua exigindo PageHeader na configuração', function () {
    $hits = notificationConfigR4('resources/js/Pages/NotificationTemplate/Index.tsx', '<div />');
    expect(array_column($hits, 'match'))->toBe(['no <PageHeader>']);
});

it('continua exigindo tabela nas listas comuns', function () {
    $hits = notificationConfigR4('resources/js/Pages/Clientes/Index.tsx', '<PageHeader />');
    expect(array_column($hits, 'match'))->toBe(['no <DataTable>']);
});

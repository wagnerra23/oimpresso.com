<?php

declare(strict_types=1);

use Illuminate\Support\Collection;

uses(Tests\TestCase::class);

/**
 * Guarda de regressão do bug medido em prod 2026-08-08: o `GovernanceServiceProvider`
 * não chamava `loadMigrationsFrom`, então o `php artisan migrate --force` do deploy
 * (path default) PULAVA as 5 migrations de `Modules/Governance/` — 4 das 6 tabelas do
 * módulo nunca existiram em prod, e o `module:grade-snapshot` (cron 06:05 BRT) morria
 * todo dia com `SQLSTATE[42S02] ... mcp_module_grades_history doesn't exist` (120
 * ocorrências no laravel.log).
 *
 * Este teste morde se alguém remover o registro do path: o `migrator` precisa conhecer
 * `Modules/Governance/Database/Migrations` pra o deploy aplicar as pendentes.
 *
 * A 2ª asserção é o par que faltava no precedente do KB: registrar o PATH não adianta
 * se um arquivo de migration nascer fora dele. Ela ancora o conjunto pelo NOME das
 * tabelas que os crons de governança escrevem — se uma migration sumir da pasta, o
 * teste diz QUAL.
 *
 * NÃO usa DB nem auth (só o container) — é lógica pura por natureza, roda na lane
 * `jana-logica-pura-pest.yml` (SQLite :memory:).
 *
 * Precedente idêntico: `Modules/KB/Tests/Feature/KbMigrationsRegisteredTest.php`
 * (bug 2026-07-23). ⚠️ Aquele teste NÃO é executado por lane nenhuma — medido em
 * 2026-08-08, zero ocorrências de `KbMigrationsRegisteredTest` em `.github/`. Este
 * entrou no `paths:` E na lista de execução da lane no mesmo PR, senão seria teatro
 * (LC-13 — verde por não-execução).
 */
it('registra o path de migrations do modulo Governance no migrator (senao o deploy pula)', function () {
    $path = realpath(base_path('Modules/Governance/Database/Migrations'));

    expect($path)->not->toBeFalse('pasta de migrations do Governance deve existir');

    $registrados = (new Collection(app('migrator')->paths()))
        ->map(fn ($p) => realpath($p))
        ->filter()
        ->all();

    expect($registrados)->toContain($path);
});

it('mantem no path as migrations das tabelas que os crons de governanca escrevem', function () {
    $dir = base_path('Modules/Governance/Database/Migrations');

    $arquivos = (new Collection(glob($dir.'/*.php') ?: []))
        ->map(fn ($f) => basename($f))
        ->implode("\n");

    // Cada tabela abaixo é destino de escrita de um schedule `['live']` do Kernel.
    // Sem a migration no path, o cron morre com SQLSTATE[42S02] — que é exatamente
    // o que aconteceu com `mcp_module_grades_history` por ~3 meses.
    $tabelas = [
        'mcp_module_grades_history',  // module:grade-snapshot        06:05 BRT
        'mcp_scorecard_runs',         // governance:scorecard-snapshot 07:00 BRT
        'mcp_observability_spans',    // governance:observability-aggregate
        'mcp_governance_initiatives', // governance:initiative-sync    08:00 BRT
        'mcp_sdd_scorecard_history',  // governance:sdd-scorecard-snapshot (CT 100)
    ];

    // ⚠️ NÃO usar `toContain($tabela, "mensagem")`: `toContain` é VARIÁDICO no Pest
    // (`Mixins/Expectation.php` — `foreach ($needles as $needle)`), então a mensagem
    // vira um 2º needle e o assert falha SEMPRE. É a lápide §5 2026-07-28 — caí nela
    // ao escrever este arquivo e o CI pegou (`To contain: migration de ... sumiu de`).
    // Aqui o diagnóstico vem do próprio array: a saída do fail nomeia qual tabela.
    $faltando = array_values(array_filter(
        $tabelas,
        fn (string $t): bool => ! str_contains($arquivos, $t)
    ));

    expect($faltando)->toBe([]);
});

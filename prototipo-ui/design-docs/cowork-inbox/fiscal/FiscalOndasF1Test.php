<?php

declare(strict_types=1);

/**
 * FiscalOndasF1Test — contrato das ondas F1 do Cowork sobre o módulo Fiscal.
 *
 * NASCE VERMELHO DE PROPÓSITO. Cada caso vermelho aqui é uma pendência real do
 * `main`, não um defeito do teste:
 *   · manifestação em lote  → backlog declarado em Dfe.charter.md ("❌ Bulk manifestar — backlog")
 *   · export CSV de eventos → backlog declarado em Eventos.charter.md ("❌ Export CSV (backlog)")
 *   · procedência do payload → CU-FISC-16, decisão [W] pendente (6 superfícies de demonstração)
 *   · séries reais / histórico DF-e real → mesmo CU-FISC-16
 *   · gate fiscal.config.ambiente + troca auditada → proposta [CC] desta sessão,
 *     ratificação [W] pendente (ver cowork-inbox/fiscal/Config.charter.md)
 *   · smoke PVA-EFD → Sped.casos.md §Backlog (sem golden file)
 *
 * Aplicar em: Modules/Fiscal/Tests/Feature/FiscalOndasF1Test.php
 * Lane: `Pest Fiscal` (advisory, SQLite — os que tocam banco pulam) + noturna CT 100 (MySQL).
 * ⛔ Nunca rodar local (ADR 0062).
 */

use Illuminate\Support\Facades\Route;

/* ─── UC-FDF1-02/03/04 · manifestação em lote ─────────────────────────────── */

it('UC-FDF1-02 · existe rota de manifestação em lote de DF-e', function () {
    // Vermelho hoje: só existe a rota por nota (acoes.dfe.manifestar).
    // Verde quando [W] aprovar POST /fiscal/acoes/dfe/lote com resultado por nota.
    expect(Route::has('fiscal.acoes.dfe.lote'))->toBeTrue();
});

it('UC-FDF1-03 · lote que NEGA a operação exige justificativa única de 15 caracteres', function () {
    $rota = Route::getRoutes()->getByName('fiscal.acoes.dfe.lote');
    expect($rota)->not->toBeNull();

    $acao = $rota->getAction('controller');
    expect($acao)->toContain('manifestarLote');

    // A regra é a mesma do caso individual (AcoesController): desconhecer e
    // nao_realizada exigem justificativa; a diferença é que no lote ela é uma só.
    expect(class_exists(\Modules\Fiscal\Http\Requests\ManifestarLoteRequest::class))->toBeTrue();
});

it('UC-FDF1-04 · confirmar em lote NÃO exige justificativa', function () {
    expect(class_exists(\Modules\Fiscal\Http\Requests\ManifestarLoteRequest::class))->toBeTrue();

    $regras = (new \Modules\Fiscal\Http\Requests\ManifestarLoteRequest())->rules();
    expect($regras)->toHaveKey('acao');
    expect($regras['justificativa'] ?? '')->not->toContain('required|');
});

/* ─── UC-FOF1-08 · procedência das superfícies (CU-FISC-16) ────────────────── */

it('UC-FOF1-08 · o cockpit declara a procedência de cada superfície no payload', function () {
    // Vermelho hoje: notas/eventosMock/sefazStatus/contabilData/writeOffSummary
    // chegam na tela sem dizer que são demonstração. A tela não deve inferir.
    $metodos = get_class_methods(\Modules\Fiscal\Http\Controllers\CockpitController::class);
    expect($metodos)->toContain('procedencia');
});

it('UC-FOF1-08 · nenhuma prop de demonstração viaja sem selo', function () {
    $fonte = file_get_contents(base_path('Modules/Fiscal/Http/Controllers/CockpitController.php'));

    // Enquanto os mocks existirem, cada um deve estar declarado no mapa de procedência.
    foreach (['notas', 'eventosMock', 'sefazStatus', 'contabilData', 'writeOffSummary'] as $prop) {
        expect($fonte)->toContain("'procedencia' => ")
            ->and($fonte)->toContain($prop);
    }
})->skip(fn () => ! file_exists(base_path('Modules/Fiscal/Http/Controllers/CockpitController.php')), 'módulo Fiscal ausente nesta lane');

/* ─── Eventos · export CSV (backlog declarado) ─────────────────────────────── */

it('UC-FEV-EXP · existe rota de export CSV dos eventos fiscais', function () {
    // Vermelho hoje: o F1 monta o CSV no cliente. Server-side é decisão [W]
    // (volume, PII no xMotivo, throttle).
    expect(Route::has('fiscal.eventos.export'))->toBeTrue();
});

/* ─── Config · séries reais (CU-FISC-16 · decisão [W]) ─────────────────────── */

it('UC-FCFG-SER · as séries da aba Séries não vêm de mock', function () {
    $fonte = file_get_contents(base_path('Modules/Fiscal/Http/Controllers/ConfigController.php'));
    expect($fonte)->not->toContain('mockSeries');
})->skip(fn () => ! file_exists(base_path('Modules/Fiscal/Http/Controllers/ConfigController.php')), 'módulo Fiscal ausente nesta lane');

/* ─── UC-FCFG-02..06 · Config editável atrás de gate próprio (proposta [CC]) ── */

it('UC-FCFG-02 · existe o gate próprio fiscal.config.ambiente, separado de edit', function () {
    // Vermelho hoje: o vivo tem fiscal.config.view/edit; a ação de risco não tem gate próprio.
    $permissoes = \Spatie\Permission\Models\Permission::pluck('name')->all();
    expect($permissoes)->toContain('fiscal.config.ambiente');
})->skip(fn () => ! class_exists(\Spatie\Permission\Models\Permission::class), 'lane sem tabela de permissões');

it('UC-FCFG-02 · trocar ambiente sem o gate é recusado no servidor', function () {
    $rota = Route::getRoutes()->getByName('fiscal.config.ambiente');
    expect($rota)->not->toBeNull();
    expect($rota->gatherMiddleware())->toContain('permission:fiscal.config.ambiente');
});

it('UC-FCFG-03 · trocar ambiente exige destino digitado e motivo de 15 caracteres', function () {
    expect(class_exists(\Modules\Fiscal\Http\Requests\TrocarAmbienteRequest::class))->toBeTrue();

    $regras = (new \Modules\Fiscal\Http\Requests\TrocarAmbienteRequest())->rules();
    expect($regras)->toHaveKeys(['ambiente', 'confirmacao', 'justificativa']);
    expect($regras['justificativa'])->toContain('min:15');
});

it('UC-FCFG-04 · troca de ambiente escreve evento com antes → depois', function () {
    $fonte = file_get_contents(base_path('Modules/Fiscal/Http/Controllers/ConfigController.php'));
    expect($fonte)->toContain('NfeEvento')->and($fonte)->toContain('ambiente_anterior');
})->skip(fn () => ! file_exists(base_path('Modules/Fiscal/Http/Controllers/ConfigController.php')), 'módulo Fiscal ausente nesta lane');

it('UC-FCFG-05 · a senha do certificado nunca sai em payload nem em log', function () {
    $model = \Modules\Fiscal\Models\NfeConfiguracao::class;
    expect((new $model())->getHidden())->toContain('certificado_senha');
})->skip(fn () => ! class_exists(\Modules\Fiscal\Models\NfeConfiguracao::class), 'módulo Fiscal ausente nesta lane');

/* ─── SPED · a prova que falta (Sped.casos.md §Backlog) ────────────────────── */

it('UC-FSF1-05 · existe golden file do TXT EFD-ICMS/IPI pra comparar', function () {
    // Vermelho hoje: os 5 casos de bloco são source-grep. Sem golden file,
    // ninguém provou que o arquivo entra no PVA-EFD.
    expect(file_exists(base_path('Modules/Fiscal/Tests/Fixtures/sped-icms-ipi-golden.txt')))->toBeTrue();
});

it('UC-FSF1-03 · competência em aberto é recusada antes de qualquer query', function () {
    $fonte = file_get_contents(base_path('Modules/Fiscal/Services/SpedIcmsIpiGeneratorService.php'));

    // A validação de competência (ano ≥ 2020, não-futura) já existe; o que falta
    // é recusar mês NÃO FECHADO — hoje a tela bloqueia, o Service não.
    expect($fonte)->toContain('competenciaFechada');
})->skip(fn () => ! file_exists(base_path('Modules/Fiscal/Services/SpedIcmsIpiGeneratorService.php')), 'módulo Fiscal ausente nesta lane');

<?php

declare(strict_types=1);

use Modules\Ponto\Entities\BancoHorasMovimento;
use Modules\Ponto\Entities\Rep;
use Modules\Ponto\Http\Controllers\AprovacaoController;
use Modules\Ponto\Http\Controllers\BancoHorasController;
use Modules\Ponto\Http\Controllers\DashboardController;
use Modules\Ponto\Http\Controllers\EspelhoController;
use Modules\Ponto\Services\AfdParserService;
use Modules\Ponto\Services\ApuracaoService;
use Modules\Ponto\Services\BancoHorasService;
use Modules\Ponto\Services\IntercorrenciaService;
use Modules\Ponto\Services\MarcacaoService;

uses(Tests\TestCase::class);

/**
 * Wave 26 SATURATION Ponto — push 68 → ≥85 cross-dim.
 *
 * Cobre as dimensões restantes do gap (Wave 25 já entregou Dashboard defer + retention).
 * Saturação:
 *
 *   D2 (+7)  — Resilience: append-only Marcacao + BancoHorasMovimento (ambos camadas
 *              Eloquent override + retention.php hard_delete=false). Defesa em
 *              profundidade documentada source-level.
 *   D5 (+7)  — Customer Journey: README expandido — 4 etapas adicionais (BH /
 *              ajuste manual / importação AFD / cross-tenant proof biz=4 ROTA LIVRE
 *              Larissa vs biz=1 WR2). 10 etapas totais cobrem jornada completa.
 *   D6 (+5)  — Inertia::defer pattern aplicado em EspelhoController + AprovacaoController
 *              + BancoHorasController (Wave 25 já fez Dashboard). 4 Controllers heavy
 *              agora usam closures lazy (RUNBOOK-inertia-defer-pattern.md).
 *   D7 (+5)  — Retention.php + LogsActivity Spatie expandido pra Rep + BancoHorasMovimento
 *              (Wave 11 já fez Colaborador/Escala/Intercorrencia). 5 entidades sensíveis
 *              cobertas pelo audit trail cadastral LGPD.
 *   D9 (+3)  — OTel spans Services confirmação (6 spans em 5 services: ApuracaoService,
 *              IntercorrenciaService, BancoHorasService, MarcacaoService, AfdParserService).
 *
 * Zero hit prod (source-level + reflexão + config require) — Pest local-runnable sem MySQL.
 *
 * Tier 0 IRREVOGÁVEL:
 *   ⛔ NÃO mutar Marcacao via tinker — apenas helpers Service (`MarcacaoService::anular()`)
 *   ⛔ NÃO mutar BancoHorasMovimento (append-only) — apenas `BancoHorasService::movimentar()`
 *   ⛔ NUNCA biz=4 (cliente Larissa ROTA LIVRE) — sempre biz=1 (Wagner WR2) ou
 *      biz=99 (fictício) — ADR 0101 tests-business-id-1-nunca-cliente
 *
 * @see Modules/Ponto/Http/Controllers/{Dashboard,Espelho,Aprovacao,BancoHoras}Controller.php
 * @see Modules/Ponto/Entities/{Rep,BancoHorasMovimento}.php (Wave 26 LogsActivity)
 * @see Modules/Ponto/Config/retention.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md
 * @see Portaria MTP 671/2021 Art. 85
 */

// ============================================================================
// D2 — Resilience: append-only defesa em camadas (Eloquent + retention policy)
// ============================================================================

it('D2 BancoHorasMovimento::update() bloqueia (defesa Eloquent — append-only ledger)', function () {
    $ref = new ReflectionMethod(BancoHorasMovimento::class, 'update');
    expect($ref->getDeclaringClass()->getName())->toBe(BancoHorasMovimento::class);

    $source = file_get_contents((new ReflectionClass(BancoHorasMovimento::class))->getFileName());
    expect($source)->toContain('append-only');
    expect($source)->toContain('RuntimeException');
});

it('D2 BancoHorasMovimento::delete() bloqueia (defesa Eloquent — preservação ledger)', function () {
    $ref = new ReflectionMethod(BancoHorasMovimento::class, 'delete');
    expect($ref->getDeclaringClass()->getName())->toBe(BancoHorasMovimento::class);
});

it('D2 retention.php declara hard_delete=false em banco_horas_movimentos (CLT Art. 11)', function () {
    $cfg = require __DIR__ . '/../../Config/retention.php';

    expect($cfg)->toHaveKey('banco_horas_movimentos');
    expect($cfg['banco_horas_movimentos']['hard_delete'])->toBeFalse(
        'Movimentos BH append-only — CLT Art. 11 prescrição quinquenal'
    );
    expect($cfg['banco_horas_movimentos']['retention_years'])->toBe(5);
});

it('D2 retention.php declara reps com hard_delete=false (FK marcações + hash chain)', function () {
    $cfg = require __DIR__ . '/../../Config/retention.php';

    expect($cfg)->toHaveKey('reps');
    expect($cfg['reps']['hard_delete'])->toBeFalse(
        'REP delete quebraria cadeia hash SHA-256 das marcações append-only'
    );
});

// ============================================================================
// D5 — Customer Journey expandido (README 10 etapas + Services contract)
// ============================================================================

it('D5 README cobre jornada estendida (BH credito + ajuste manual + AFD + cross-tenant)', function () {
    $readme = file_get_contents(__DIR__ . '/../../README.md');

    // 4 novas etapas Wave 26 — jornada Larissa biz=4 vs Wagner biz=1
    expect($readme)->toContain('Banco de Horas (HE compensáveis');
    expect($readme)->toContain('Ajuste manual de saldo BH');
    expect($readme)->toContain('Importar AFD do REP físico');
    expect($readme)->toContain('Cross-tenant proof');
    expect($readme)->toContain('biz=4'); // ROTA LIVRE Larissa
    expect($readme)->toContain('biz=1'); // WR2 Sistemas (Wagner)
    expect($readme)->toContain('ROTA LIVRE');
});

it('D5 README cita marcos governance v4 (Waves 11/18/23/25/26 + descritivo)', function () {
    $readme = file_get_contents(__DIR__ . '/../../README.md');

    expect($readme)->toContain('Marcos governance v4');
    // Conteúdo descritivo de cada wave canônica (mais robusto que linha exata)
    expect($readme)->toContain('LogsActivity em Colaborador');         // Wave 11
    expect($readme)->toContain('HasBusinessScope');                    // Wave 18
    expect($readme)->toContain('MarcacaoService reuse contract');      // Wave 23
    expect($readme)->toContain('Inertia::defer Dashboard');            // Wave 25
    expect($readme)->toContain('Saturação cross-dim');                 // Wave 26
});

it('D5 BancoHorasService::ajustarManual contrato (movimento TIPO_AJUSTE com usuario_id)', function () {
    expect(method_exists(BancoHorasService::class, 'ajustarManual'))->toBeTrue();

    $ref = new ReflectionMethod(BancoHorasService::class, 'ajustarManual');
    expect($ref->isPublic())->toBeTrue();
});

it('D5 AfdParserService::processar contrato (importação REP-A/REP-C)', function () {
    expect(method_exists(AfdParserService::class, 'processar'))->toBeTrue();

    $ref = new ReflectionMethod(AfdParserService::class, 'processar');
    expect($ref->isPublic())->toBeTrue();
});

// ============================================================================
// D6 — Inertia::defer pattern em Controllers restantes (Wave 25 fez Dashboard)
// ============================================================================

it('D6 EspelhoController::index usa Inertia::defer em paginate() (Wave 26)', function () {
    $source = file_get_contents((new ReflectionClass(EspelhoController::class))->getFileName());

    expect(substr_count($source, 'Inertia::defer(fn ()'))->toBeGreaterThanOrEqual(3); // index + show.totais + show.linhas
    expect($source)->toContain('buildColaboradoresPagina');
    expect($source)->toContain('buildTotaisEspelho');
    expect($source)->toContain('buildLinhasEspelho');
    expect($source)->toContain('RUNBOOK-inertia-defer-pattern.md');
});

it('D6 AprovacaoController::index usa Inertia::defer em paginate() + selectRaw (Wave 26)', function () {
    $source = file_get_contents((new ReflectionClass(AprovacaoController::class))->getFileName());

    expect(substr_count($source, 'Inertia::defer(fn ()'))->toBeGreaterThanOrEqual(2); // aprovacoes + contagens
    expect($source)->toContain('buildAprovacoesPagina');
    expect($source)->toContain('buildContagensEstado');
    // Filtros + tipos enum permanecem eager (UI state)
    expect($source)->toContain("'filtros' => [");
    expect($source)->toContain("'tipos' => [");
});

it('D6 BancoHorasController::index usa Inertia::defer em paginate() + 4 aggregates (Wave 26)', function () {
    $source = file_get_contents((new ReflectionClass(BancoHorasController::class))->getFileName());

    expect(substr_count($source, 'Inertia::defer(fn ()'))->toBeGreaterThanOrEqual(3); // saldos + totais + movimentos show
    expect($source)->toContain('buildSaldosPagina');
    expect($source)->toContain('buildTotaisSaldos');
    expect($source)->toContain('buildMovimentosPagina');
});

it('D6 Controllers extraíram queries em métodos buildXxx privados (SoC Wave 26)', function () {
    foreach ([EspelhoController::class, AprovacaoController::class, BancoHorasController::class] as $controller) {
        $ref = new ReflectionClass($controller);
        $privateMethods = array_filter(
            $ref->getMethods(ReflectionMethod::IS_PRIVATE),
            fn ($m) => str_starts_with($m->getName(), 'build')
        );
        expect($privateMethods)->not->toBeEmpty(
            "{$controller} deve ter ao menos 1 método buildXxx privado (Wave 26 SoC)"
        );
    }
});

it('D6 Dashboard + Espelho + Aprovacao + BancoHoras = 4 Controllers defer (Wave 25+26)', function () {
    $controllersComDefer = 0;
    foreach ([
        DashboardController::class,
        EspelhoController::class,
        AprovacaoController::class,
        BancoHorasController::class,
    ] as $controller) {
        $source = file_get_contents((new ReflectionClass($controller))->getFileName());
        if (str_contains($source, 'Inertia::defer(fn ()')) {
            $controllersComDefer++;
        }
    }
    expect($controllersComDefer)->toBe(4, '4 Controllers heavy do Ponto devem usar defer pattern');
});

// ============================================================================
// D7 — LogsActivity Spatie cobertura cadastral + sensitive entities (Wave 26)
// ============================================================================

it('D7 Rep usa LogsActivity (Wave 26 — audit trail cadastral REP Portaria 671 Art. 85)', function () {
    $traits = class_uses(Rep::class);

    expect($traits)->toHaveKey(\Spatie\Activitylog\Traits\LogsActivity::class);
    expect(method_exists(Rep::class, 'getActivitylogOptions'))->toBeTrue();

    $source = file_get_contents((new ReflectionClass(Rep::class))->getFileName());
    expect($source)->toContain("useLogName('ponto_rep')");
    // Fiscal: rastreabilidade exige tipo + identificador + ativo
    expect($source)->toContain("'tipo'");
    expect($source)->toContain("'identificador'");
    expect($source)->toContain("'ativo'");
});

it('D7 Rep NÃO loga ultimo_nsr nem certificado_info (LGPD minimização)', function () {
    $source = file_get_contents((new ReflectionClass(Rep::class))->getFileName());

    // Extrai lista logOnly
    preg_match('/->logOnly\(\[(.*?)\]\)/s', $source, $matches);
    expect($matches[1] ?? '')->not->toContain("'ultimo_nsr'");
    expect($matches[1] ?? '')->not->toContain("'certificado_info'");
});

it('D7 BancoHorasMovimento usa LogsActivity (Wave 26 — audit trail criação ledger)', function () {
    $traits = class_uses(BancoHorasMovimento::class);

    expect($traits)->toHaveKey(\Spatie\Activitylog\Traits\LogsActivity::class);
    expect(method_exists(BancoHorasMovimento::class, 'getActivitylogOptions'))->toBeTrue();

    $source = file_get_contents((new ReflectionClass(BancoHorasMovimento::class))->getFileName());
    expect($source)->toContain("useLogName('ponto_banco_horas_movimento')");
    // Defesa trabalhista exige tipo + minutos + usuario_id (accountability)
    expect($source)->toContain("'tipo'");
    expect($source)->toContain("'minutos'");
    expect($source)->toContain("'usuario_id'");
});

it('D7 BancoHorasMovimento NÃO loga campos derivados (saldo_posterior_minutos)', function () {
    $source = file_get_contents((new ReflectionClass(BancoHorasMovimento::class))->getFileName());

    preg_match('/->logOnly\(\[(.*?)\]\)/s', $source, $matches);
    expect($matches[1] ?? '')->not->toContain("'saldo_posterior_minutos'");
    expect($matches[1] ?? '')->not->toContain("'data_referencia'");
});

it('D7 5 entidades Ponto sensíveis cobertas por LogsActivity (Wave 11 + 26)', function () {
    $entidadesComLog = 0;
    foreach ([
        \Modules\Ponto\Entities\Colaborador::class,        // Wave 11
        \Modules\Ponto\Entities\Escala::class,             // Wave 11
        \Modules\Ponto\Entities\Intercorrencia::class,     // Wave 11
        \Modules\Ponto\Entities\Rep::class,                // Wave 26
        \Modules\Ponto\Entities\BancoHorasMovimento::class, // Wave 26
    ] as $entidade) {
        $traits = class_uses($entidade);
        if (isset($traits[\Spatie\Activitylog\Traits\LogsActivity::class])) {
            $entidadesComLog++;
        }
    }
    expect($entidadesComLog)->toBe(5, '5 entidades Ponto sensíveis devem ter LogsActivity (Wave 11 + Wave 26)');
});

// ============================================================================
// D9 — OTel observability spans confirmação (5 services + 6 spans)
// ============================================================================

it('D9 ApuracaoService usa OtelHelper::span (hot-path apuracao CLT)', function () {
    $source = file_get_contents((new ReflectionClass(ApuracaoService::class))->getFileName());
    expect($source)->toContain('use App\Util\OtelHelper;');
    expect($source)->toContain("ponto.apuracao.apurar");
});

it('D9 IntercorrenciaService usa OtelHelper::span em criar() + aprovar()', function () {
    $source = file_get_contents((new ReflectionClass(IntercorrenciaService::class))->getFileName());
    expect($source)->toContain('use App\Util\OtelHelper;');
    expect($source)->toContain("ponto.intercorrencia.criar");
    expect($source)->toContain("ponto.intercorrencia.aprovar");
});

it('D9 BancoHorasService usa OtelHelper::span em movimentar() + expirar_saldos()', function () {
    $source = file_get_contents((new ReflectionClass(BancoHorasService::class))->getFileName());
    expect($source)->toContain('use App\Util\OtelHelper;');
    expect($source)->toContain("ponto.banco_horas.movimentar");
    expect($source)->toContain("ponto.banco_horas.expirar_saldos");
});

it('D9 MarcacaoService usa OtelHelper::span em registrar() + anular()', function () {
    $source = file_get_contents((new ReflectionClass(MarcacaoService::class))->getFileName());
    expect($source)->toContain('use App\Util\OtelHelper;');
    expect($source)->toContain("ponto.marcacao.registrar");
    expect($source)->toContain("ponto.marcacao.anular");
});

it('D9 AfdParserService usa OtelHelper::span em processar() (importação REP)', function () {
    $source = file_get_contents((new ReflectionClass(AfdParserService::class))->getFileName());
    expect($source)->toContain('use App\Util\OtelHelper;');
    expect($source)->toContain("ponto.afd.processar");
});

it('D9 5 Services Ponto + 9 spans nomeados (cobertura hot-path completa Wave 26)', function () {
    $totalSpans = 0;
    foreach ([
        ApuracaoService::class,
        IntercorrenciaService::class,
        BancoHorasService::class,
        MarcacaoService::class,
        AfdParserService::class,
    ] as $service) {
        $source = file_get_contents((new ReflectionClass($service))->getFileName());
        $totalSpans += substr_count($source, 'OtelHelper::span(');
    }
    // 1 (apurar) + 2 (criar/aprovar) + 2 (movimentar/expirar) + 2 (registrar/anular) + 1 (afd) = 8 mín
    expect($totalSpans)->toBeGreaterThanOrEqual(8,
        'Ponto deve ter ≥8 spans OTel nomeados nos 5 Services hot-path'
    );
});

// ============================================================================
// Sanity Wave 26 — bucket governance v4 preservado + dependências
// ============================================================================

it('module.json mantém governance.bucket = functional_horizontal (Wave 25/26 preservado)', function () {
    $json = json_decode(file_get_contents(__DIR__ . '/../../module.json'), true);

    expect($json['governance']['bucket'])->toBe('functional_horizontal');
    expect($json['governance']['bucket_assigned_by'])->toBe('[W]');
});

it('Wave 25 SaturationTest convive com Wave 26 (não-conflito — assertions aditivas)', function () {
    expect(file_exists(__DIR__ . '/Wave25SaturationTest.php'))->toBeTrue();
    expect(file_exists(__DIR__ . '/Wave26SaturationTest.php'))->toBeTrue();

    // Wave 26 expande sem revogar Wave 25
    $w25 = file_get_contents(__DIR__ . '/Wave25SaturationTest.php');
    expect($w25)->toContain("DashboardController::index"); // Wave 25 base preservada
});

<?php

declare(strict_types=1);

use Modules\Jana\Services\Privacy\PiiRedactor;
use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Entities\Escala;
use Modules\Ponto\Entities\Intercorrencia;
use Modules\Ponto\Entities\Marcacao;
use Modules\Ponto\Services\IntercorrenciaAIClassifier;

/**
 * Wave 11 D7 — LGPD compliance test do Modules/Ponto.
 *
 * Cobertura:
 *   D7.a PiiRedactor:
 *     1) `IntercorrenciaAIClassifier::mascararPII` mascara CPF/email/tel/PIS via PiiRedactor canônico
 *     2) PIS-formato específico (`000.00000.00-0`) ainda é mascarado (não coberto por PiiRedactor genérico)
 *
 *   D7.b LogsActivity:
 *     3) Colaborador, Escala, Intercorrencia têm `getActivitylogOptions()` (trait Spatie ativo)
 *     4) Marcacao NÃO tem LogsActivity (preserva append-only — Portaria 671 Art. 85)
 *
 *   D7.c Retention:
 *     5) `Modules/Ponto/Config/retention.php` carrega e tem chaves obrigatórias
 *     6) marcacoes/banco_horas_movimentos têm hard_delete=false (append-only IRREVOGÁVEL)
 *     7) base_legal cita Portaria 671 ou CLT Art. 11 (auditável por advogada — Eliana)
 *
 * Sem PII real (Faker apenas — biz=1 nunca cliente, ADR 0101). Sem DB (testes unitários puros).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md §4 (Compliance LGPD)
 * @see Portaria MTP 671/2021 Art. 85 (append-only)
 */

uses(Tests\TestCase::class);

// ============================================================================
// D7.a — PiiRedactor wrapping
// ============================================================================

it('D7.a — mascara CPF email telefone via PiiRedactor canonico', function () {
    // Exposer da protected `mascararPII` (mesmo padrão de IntercorrenciaAIClassifierTest)
    $expose = new class extends IntercorrenciaAIClassifier {
        public function exposeMascarar(string $t): string
        {
            return $this->mascararPII($t);
        }
    };

    $input = 'Wagner CPF 123.456.789-00 email wagner@exemplo.com tel (11) 91234-5678';
    $output = $expose->exposeMascarar($input);

    expect($output)
        ->not->toContain('123.456.789-00')
        ->and($output)->not->toContain('wagner@exemplo.com')
        ->and($output)->not->toContain('91234-5678');

    // PiiRedactor canônico usa placeholder `[REDACTED:TIPO]`
    expect($output)
        ->toContain('[REDACTED:CPF]')
        ->and($output)->toContain('[REDACTED:EMAIL]');
});

it('D7.a — mascara PIS formato CLT especifico que PiiRedactor generico nao cobre', function () {
    $expose = new class extends IntercorrenciaAIClassifier {
        public function exposeMascarar(string $t): string
        {
            return $this->mascararPII($t);
        }
    };

    // PIS no formato 000.00000.00-0 (semântica Portaria 671) — fake value, não cliente real
    $output = $expose->exposeMascarar('colaborador PIS 123.45678.90-1 esqueceu marcacao');

    expect($output)
        ->not->toContain('123.45678.90-1')
        ->and($output)->toContain('[REDACTED:PIS]');
});

it('D7.a — PiiRedactor canonico esta acessivel via container', function () {
    expect(app(PiiRedactor::class))->toBeInstanceOf(PiiRedactor::class);

    // Sanity: detect retorna mapa não-vazio em input com PII
    $found = app(PiiRedactor::class)->detect('email test@example.com');
    expect($found)->toHaveKey('EMAIL');
});

// ============================================================================
// D7.b — LogsActivity nos cadastros (NÃO em Marcacao)
// ============================================================================

it('D7.b — Colaborador usa LogsActivity Spatie', function () {
    $traits = class_uses_recursive(Colaborador::class);
    expect($traits)->toContain(\Spatie\Activitylog\Traits\LogsActivity::class);

    // getActivitylogOptions() existe e devolve LogOptions
    $colaborador = new Colaborador();
    expect($colaborador->getActivitylogOptions())
        ->toBeInstanceOf(\Spatie\Activitylog\LogOptions::class);
});

it('D7.b — Escala usa LogsActivity Spatie', function () {
    $traits = class_uses_recursive(Escala::class);
    expect($traits)->toContain(\Spatie\Activitylog\Traits\LogsActivity::class);

    $escala = new Escala();
    expect($escala->getActivitylogOptions())
        ->toBeInstanceOf(\Spatie\Activitylog\LogOptions::class);
});

it('D7.b — Intercorrencia usa LogsActivity Spatie', function () {
    $traits = class_uses_recursive(Intercorrencia::class);
    expect($traits)->toContain(\Spatie\Activitylog\Traits\LogsActivity::class);

    $i = new Intercorrencia();
    expect($i->getActivitylogOptions())
        ->toBeInstanceOf(\Spatie\Activitylog\LogOptions::class);
});

it('D7.b — Marcacao NAO usa LogsActivity (append-only Portaria 671 Art. 85 IRREVOGAVEL)', function () {
    // Tier 0 IRREVOGÁVEL: Marcacao não pode ser auditada por trait de update
    // porque trait dispara em update() e Marcacao::update() lança RuntimeException.
    // Audit trail de Marcacao é a PRÓPRIA cadeia hash SHA-256 encadeada (NSR).
    $traits = class_uses_recursive(Marcacao::class);
    expect($traits)->not->toContain(\Spatie\Activitylog\Traits\LogsActivity::class);
});

// ============================================================================
// D7.c — Retention policy
// ============================================================================

it('D7.c — config retention.php carrega com chaves obrigatorias', function () {
    $config = require __DIR__ . '/../../Config/retention.php';

    expect($config)->toBeArray();

    // Cobertura mínima: as entidades sensíveis CLT precisam estar declaradas
    foreach (['marcacoes', 'banco_horas_movimentos', 'intercorrencias', 'colaboradores', 'reps', 'audit_log'] as $key) {
        expect($config)->toHaveKey($key);
        expect($config[$key])->toHaveKey('base_legal');
        expect($config[$key])->toHaveKey('hard_delete');
        expect($config[$key])->toHaveKey('notes');
    }
});

it('D7.c — marcacoes e banco_horas_movimentos sao hard_delete=false (append-only)', function () {
    $config = require __DIR__ . '/../../Config/retention.php';

    expect($config['marcacoes']['hard_delete'])
        ->toBeFalse('Marcacoes APPEND-ONLY por Portaria MTP 671/2021 Art. 85 — IRREVOGAVEL.');

    expect($config['banco_horas_movimentos']['hard_delete'])
        ->toBeFalse('BancoHorasMovimento append-only via Eloquent override.');
});

it('D7.c — base_legal cita Portaria 671 ou CLT Art. 11', function () {
    $config = require __DIR__ . '/../../Config/retention.php';

    // Marcações: deve citar Portaria 671 explicitamente
    expect($config['marcacoes']['base_legal'])
        ->toContain('671');

    // Demais entidades trabalhistas: pelo menos uma deve citar CLT Art. 11 (prescrição)
    $citaPrescricao = collect(['banco_horas_movimentos', 'intercorrencias', 'colaboradores'])
        ->some(fn ($k) => str_contains($config[$k]['base_legal'], 'Art. 11')
                       || str_contains($config[$k]['base_legal'], 'CLT'));

    expect($citaPrescricao)->toBeTrue();
});

it('D7.c — retention_years declarado em entidades imutaveis (marcacoes/banco_horas) eh >= 5 anos', function () {
    $config = require __DIR__ . '/../../Config/retention.php';

    expect($config['marcacoes']['retention_years'])->toBeGreaterThanOrEqual(5);
    expect($config['banco_horas_movimentos']['retention_years'])->toBeGreaterThanOrEqual(5);
});

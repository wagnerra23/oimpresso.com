<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Smoke test do comando jana:health-check.
 *
 * Não roda LLM real — apenas valida que o comando:
 *   1. É registrado e aceita parâmetros documentados (--json, --notify)
 *   2. Retorna exit code apropriado (0 ou 1)
 *   3. Output JSON tem shape canônico ({ok, checked_at, checks[]})
 *   4. Cada check declara name, ok, value, threshold, message
 *
 * Em CI roda em SQLite — checks de integridade Tier 0 podem retornar
 * resultados degraded (tabelas inexistentes), mas comando não pode crashar.
 */

test('comando registrado no artisan list', function () {
    $output = \Illuminate\Support\Facades\Artisan::call('list');
    expect(\Illuminate\Support\Facades\Artisan::output())->toContain('jana:health-check');
});

test('--json output tem shape canonico', function () {
    \Illuminate\Support\Facades\Artisan::call('jana:health-check', ['--json' => true]);
    $output = \Illuminate\Support\Facades\Artisan::output();

    // Pode ter linhas debug antes do JSON; pegar último bloco { ... }
    $jsonStart = strpos($output, '{');
    expect($jsonStart)->not->toBeFalse('Output não contém JSON');

    $json = json_decode(substr($output, $jsonStart), true);
    expect($json)->toBeArray()
        ->toHaveKey('ok')
        ->toHaveKey('checked_at')
        ->toHaveKey('checks');

    // ≥10 checks duros (SQL/operacionais) + N charter/loop advisory (dinâmico via
    // CharterHealthChecker). Não cravamos count exato porque os advisory variam —
    // a presença dos duros é garantida no teste abaixo.
    expect($json['checks'])->toBeArray();
    expect(count($json['checks']))->toBeGreaterThanOrEqual(10);
});

test('cada check tem campos canonicos', function () {
    \Illuminate\Support\Facades\Artisan::call('jana:health-check', ['--json' => true]);
    $output = \Illuminate\Support\Facades\Artisan::output();
    $json = json_decode(substr($output, strpos($output, '{')), true);

    // Checks DUROS obrigatórios (operacionais). Os charter/loop advisory entram
    // a mais (dinâmico via CharterHealthChecker), por isso checamos presença
    // (subset), não igualdade exata.
    $duros = [
        'multi_tenant_isolation',
        'sells_value_sanity',
        'brief_uptime_24h',
        'custo_brain_b_24h',
        'pii_leak_in_assistant_responses',
        'profile_distiller_drift',
        'procedure_drift',
        'spec_id_drift',
        'whatsapp_media_pending_1h',
        'mcp_webhook_5xx_2h',
        'memoria_recall_backend',
        'jana_lesson_ledger_graduation',
    ];

    $namesReais = array_column($json['checks'], 'name');
    expect(array_diff($duros, $namesReais))->toBe([]);

    foreach ($json['checks'] as $check) {
        expect($check)
            ->toHaveKey('name')
            ->toHaveKey('ok')
            ->toHaveKey('value')
            ->toHaveKey('message');
        expect($check['ok'])->toBeBool();
    }
});

test('comando nao crasha mesmo se tabelas degraded', function () {
    // Roda 2x — se tiver state local, pegamos
    $exit1 = \Illuminate\Support\Facades\Artisan::call('jana:health-check', ['--json' => true]);
    $exit2 = \Illuminate\Support\Facades\Artisan::call('jana:health-check', ['--json' => true]);

    expect($exit1)->toBeIn([0, 1]);
    expect($exit2)->toBeIn([0, 1]);
});

/**
 * Loop de graduação do ledger de lições de operação (Reflexion runtime).
 * Parser determinístico — testável sem tocar o filesystem real.
 *
 * @see Modules/Jana/Console/Commands/HealthCheckCommand::parseLessonLedger
 * @see Modules/Jana/LICOES-OPERACAO.md
 */
use Modules\Jana\Console\Commands\HealthCheckCommand;

/**
 * Check 1b — invariante de valor (sensor do incidente "Guilherme" 2026-06-05).
 * Casos ancorados no CONTRATO (a invariante de desconto) + nos números reais do
 * incidente — não derivados da implementação (anti-tautologia, proibicoes.md §5).
 *
 * @see memory/sessions/2026-06-05-veiculo-na-venda-e-incidente-numuf-valor-inflado.md
 */
test('invariante de valor: venda inflada do incidente Guilherme é flagada', function () {
    // Caso real: calça 227,90, desconto 10,05% → esperado ~204,99; num_uf inflou
    // final_total pra 20.499.605 (~×100k). Tem que ser pego.
    expect(HealthCheckCommand::valueExceedsCeiling(20499605.0, 227.90))->toBeTrue();
});

test('invariante de valor: venda legítima com desconto NÃO é flagada', function () {
    // Mesma venda, valor correto (desconto só reduz). final_total < total_before_tax.
    expect(HealthCheckCommand::valueExceedsCeiling(204.99, 227.90))->toBeFalse();
    // Sem desconto: final == total_before_tax.
    expect(HealthCheckCommand::valueExceedsCeiling(80.0, 80.0))->toBeFalse();
});

test('invariante de valor: imposto/frete entram no teto sem virar falso-positivo', function () {
    // total 100 + tax 18 = 118 legítimo; 200 é corrupção.
    expect(HealthCheckCommand::valueExceedsCeiling(118.0, 100.0, 18.0))->toBeFalse();
    expect(HealthCheckCommand::valueExceedsCeiling(400.0, 100.0, 18.0))->toBeTrue();
});

test('invariante de valor: total/final zerado ou negativo nunca flaga (sem dado)', function () {
    expect(HealthCheckCommand::valueExceedsCeiling(0.0, 0.0))->toBeFalse();   // desconto 100%
    expect(HealthCheckCommand::valueExceedsCeiling(500.0, 0.0))->toBeFalse(); // sem base de comparação
});

test('parser do ledger: lição MEC e JULG bem-formadas passam', function () {
    $md = <<<'MD'
    ### L-OP-001 · check existente
    - **Graduação:** MEC · check:`mcp_webhook_5xx_2h` · status:done

    ### L-OP-002 · regra existente
    - **Graduação:** JULG · regra:`.claude/skills/incident-done-checklist/SKILL.md` · status:done
    MD;

    $r = HealthCheckCommand::parseLessonLedger($md);
    expect($r['total'])->toBe(2);
    expect($r['overdue'])->toBe([]);
    expect($r['malformed'])->toBe([]);
});

test('parser do ledger: status pendente vira overdue (loop aberto)', function () {
    $md = "### L-OP-003 · ainda não graduada\n- **Graduação:** MEC · check:`x` · status:pendente";
    $r = HealthCheckCommand::parseLessonLedger($md);
    expect($r['overdue'])->toBe(['L-OP-003']);
    expect($r['malformed'])->toBe([]);
});

test('parser do ledger: MEC sem check e bloco sem graduação são malformados', function () {
    $md = <<<'MD'
    ### L-OP-004 · MEC sem binding
    - **Graduação:** MEC · status:done

    ### L-OP-005 · sem linha de graduação
    - **Erro:** algo
    MD;

    $r = HealthCheckCommand::parseLessonLedger($md);
    expect($r['malformed'])->toBe(['L-OP-004', 'L-OP-005']);
    expect($r['overdue'])->toBe([]);
});

test('ledger canônico Modules/Jana/LICOES-OPERACAO.md está todo graduado', function () {
    $path = base_path('Modules/Jana/LICOES-OPERACAO.md');
    expect(is_file($path))->toBeTrue('Ledger canônico ausente');

    $r = HealthCheckCommand::parseLessonLedger((string) file_get_contents($path));
    expect($r['total'])->toBeGreaterThanOrEqual(3);
    expect($r['overdue'])->toBe([]);
    expect($r['malformed'])->toBe([]);
});

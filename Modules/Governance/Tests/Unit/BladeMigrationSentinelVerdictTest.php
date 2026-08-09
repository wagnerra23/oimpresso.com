<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Modules\Governance\Console\Commands\BladeMigrationSentinelCommand;

uses(Tests\TestCase::class);

/**
 * Peça 2 da rota de migração Blade→React (ADR 0277): a COBRANÇA.
 *
 * Testa a parte PURA do sentinela (`avaliar`) — sem DB, sem node, sem filesystem.
 * A data de "hoje" é injetada, então o teste não apodrece com o calendário.
 *
 * Contexto: o contrato da 0277 ("migrado = route Blade morto") ficou 56 dias sem
 * máquina. O medidor nasceu no #5424; sem este elo ele é só um número que alguém
 * precisa LEMBRAR de olhar — o mesmo defeito que parou a rota em junho.
 */
function baselineFake(array $porEscopo, int $total, ?string $geradoEm = '2026-07-01'): array
{
    $b = ['total_blade' => $total, 'por_escopo' => []];
    foreach ($porEscopo as $esc => $n) {
        $b['por_escopo'][$esc] = ['blade' => $n, 'hibrido' => 0];
    }
    if ($geradoEm !== null) {
        $b['gerado_em'] = $geradoEm;
    }

    return $b;
}

function censoFake(array $porEscopo, int $total): array
{
    $a = ['total_blade' => $total, 'por_escopo' => []];
    foreach ($porEscopo as $esc => $n) {
        $a['por_escopo'][$esc] = ['blade' => $n, 'hibrido' => 0];
    }

    return $a;
}

// ── BITE: os dois motivos de alarme ─────────────────────────────────────────

it('MORDE: escopo que SOBE é regressão (rota Blade nova)', function () {
    $v = BladeMigrationSentinelCommand::avaliar(
        censoFake(['Crm' => 96, 'core' => 250], 346),
        baselineFake(['Crm' => 94, 'core' => 250], 344),
        '2026-07-10',
    );

    expect($v['veredito'])->toBe('regressao')
        ->and($v['regressoes'])->toHaveKey('Crm')
        ->and($v['regressoes']['Crm'])->toBe(['de' => 94, 'para' => 96])
        ->and($v['resumo'])->toContain('Crm 94→96');
});

it('MORDE: escopo NOVO que não existia no baseline conta como regressão 0→N', function () {
    $v = BladeMigrationSentinelCommand::avaliar(
        censoFake(['core' => 250, 'ModuloNovo' => 3], 253),
        baselineFake(['core' => 250], 250),
        '2026-07-10',
    );

    expect($v['veredito'])->toBe('regressao')
        ->and($v['regressoes']['ModuloNovo'])->toBe(['de' => 0, 'para' => 3]);
});

it('MORDE: total parado além do limite é estagnação', function () {
    $v = BladeMigrationSentinelCommand::avaliar(
        censoFake(['core' => 250], 250),
        baselineFake(['core' => 250], 250, '2026-06-01'),
        '2026-07-10', // 39 dias depois
    );

    expect($v['veredito'])->toBe('estagnado')
        ->and($v['dias'])->toBe(39)
        ->and($v['resumo'])->toContain('ESTAGNADA');
});

// ── CONTROLES NEGATIVOS: o alarme precisa poder ficar QUIETO ────────────────

it('NEG: queda no total é progresso, não alarme', function () {
    $v = BladeMigrationSentinelCommand::avaliar(
        censoFake(['core' => 240], 240),
        baselineFake(['core' => 250], 250, '2026-06-01'),
        '2026-07-10',
    );

    expect($v['veredito'])->toBe('progresso')
        ->and($v['delta'])->toBe(-10)
        ->and($v['resumo'])->toContain('10 endpoint(s) saíram');
});

it('NEG: parado DENTRO do limite não alarma', function () {
    $v = BladeMigrationSentinelCommand::avaliar(
        censoFake(['core' => 250], 250),
        baselineFake(['core' => 250], 250, '2026-07-01'),
        '2026-07-10', // 9 dias < 30
    );

    expect($v['veredito'])->toBe('ok');
});

it('NEG: baseline sem gerado_em não quebra nem inventa estagnação', function () {
    $v = BladeMigrationSentinelCommand::avaliar(
        censoFake(['core' => 250], 250),
        baselineFake(['core' => 250], 250, null),
        '2026-07-10',
    );

    expect($v['veredito'])->toBe('ok')->and($v['dias'])->toBe(0);
});

// ── PRECEDÊNCIA: regressão vence progresso (anti whack-a-mole, ADR 0277 §1) ─

it('regressão VENCE progresso — escopo piora escondido atrás da queda alheia', function () {
    // total caiu 10, mas o Crm subiu 2: a dívida nova não pode sumir na média.
    $v = BladeMigrationSentinelCommand::avaliar(
        censoFake(['core' => 230, 'Crm' => 96], 326),
        baselineFake(['core' => 250, 'Crm' => 94], 344),
        '2026-07-10',
    );

    expect($v['veredito'])->toBe('regressao')
        ->and($v['delta'])->toBeLessThan(0); // o progresso existe, mas não silencia
});

it('limite de estagnação é configurável e o default não é hardcoded no veredito', function () {
    $args = [censoFake(['core' => 250], 250), baselineFake(['core' => 250], 250, '2026-06-25'), '2026-07-10'];

    expect(BladeMigrationSentinelCommand::avaliar(...$args)['veredito'])->toBe('ok')          // 15d < 30
        ->and(BladeMigrationSentinelCommand::avaliar(...[...$args, 10])['veredito'])->toBe('estagnado'); // 15d > 10
});

// ── CENSO AUSENTE (`null`): não pode virar verde nem virar progresso ────────
// No cron do Hostinger o node NÃO é alcançável (medido em prod 2026-08-08: PATH
// herdado `/usr/local/bin:/usr/bin`, node só no nvm). Então `$atual === null` é
// estado de ROTINA, não exceção — e o instrumento não pode afirmar o que não mediu
// (§5 2026-07-29 "instrumento afirmar verde quando não conseguiu MEDIR").

it('MORDE: sem censo e sem estagnação NÃO diz ok — diz cego e nomeia o que não conferiu', function () {
    $v = BladeMigrationSentinelCommand::avaliar(
        null,
        baselineFake(['core' => 250], 250, '2026-07-01'),
        '2026-07-10', // 9 dias < 30 → não há estagnação a cobrar
    );

    expect($v['veredito'])->toBe('cego')
        ->and($v['resumo'])->toContain('CEGO')
        ->and($v['resumo'])->toContain('regressão')   // nomeia o eixo sem medida
        ->and($v['resumo'])->toContain('--ratchet')   // e diz quem cobre esse eixo
        ->and($v['resumo'])->not->toContain('sem regressão'); // nunca a frase do "ok"
});

it('MORDE: sem censo, ESTAGNAÇÃO ainda é cobrada — ela sai do baseline, não do node', function () {
    $v = BladeMigrationSentinelCommand::avaliar(
        null,
        baselineFake(['core' => 471], 471, '2026-06-01'),
        '2026-07-10', // 39 dias > 30
    );

    // É o valor ÚNICO do sentinela: a catraca do CI ignora tempo de propósito.
    expect($v['veredito'])->toBe('estagnado')
        ->and($v['dias'])->toBe(39)
        ->and($v['total'])->toBe(471)               // total vem do baseline
        ->and($v['resumo'])->toContain('censo não medido'); // declarado, não escondido
});

it('NEG: sem censo NÃO fabrica progresso (delta 0 por ignorância ≠ queda medida)', function () {
    // ⚠️ O total do baseline tem de ser NÃO-ZERO. A 1ª versão deste caso usava
    // `baselineFake(['core' => 0], 0)` e era DEGENERADA: com total 0 o `delta` dá 0
    // sob qualquer mutação, então o caso passava mesmo com a proteção removida —
    // nome prometendo mais do que verifica (§5 2026-07-28). Achado por mutação
    // adversarial. Com 471, quebrar as DUAS pernas (o `$totalAtual = $mediu ? …`
    // e a guarda `$mediu &&` do ramo) produz `progresso delta=-471` e o caso morde.
    $v = BladeMigrationSentinelCommand::avaliar(
        null,
        baselineFake(['core' => 471], 471, '2026-07-01'),
        '2026-07-10',
    );

    expect($v['veredito'])->not->toBe('progresso')
        ->and($v['delta'])->toBe(0)
        ->and($v['total'])->toBe(471); // total conhecido vem do baseline, não de medida
});

it('NEG: censo presente e saudável segue dando ok — o cego não virou carimbo', function () {
    $v = BladeMigrationSentinelCommand::avaliar(
        censoFake(['core' => 250], 250),
        baselineFake(['core' => 250], 250, '2026-07-01'),
        '2026-07-10',
    );

    expect($v['veredito'])->toBe('ok')->and($v['total'])->toBe(250);
});

it('NEG: sem censo, regressão fica sem medida — não inventa escopo culpado', function () {
    $v = BladeMigrationSentinelCommand::avaliar(
        null,
        baselineFake(['core' => 250, 'Crm' => 94], 344, '2026-07-01'),
        '2026-07-10',
    );

    expect($v['regressoes'])->toBe([]);
});

// ── REGISTRO: o comando existe pro artisan, não só no disco ─────────────────
// Mede `Artisan::all()` (o registry vivo), NUNCA `app(Classe::class)` — o container
// resolve qualquer classe concreta do disco, então aquele assert daria VERDE com o
// comando desregistrado. Foi o falso-verde de 2 comandos por ~2,4 meses (§5 2026-07-28).

it('MORDE: o comando está REGISTRADO no artisan (não só existe no disco)', function () {
    expect(array_keys(Artisan::all()))->toContain('governance:blade-migration-sentinel');
});

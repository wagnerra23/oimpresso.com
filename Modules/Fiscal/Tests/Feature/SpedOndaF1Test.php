<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Modules\Fiscal\Http\Controllers\SpedController;
use Modules\Fiscal\Services\SpedIcmsIpiGeneratorService;

uses(Tests\TestCase::class);

/**
 * SpedOndaF1Test — os dois UC da Onda 9 (F1 Cowork) sobre a tela SPED & Livros.
 *
 * Origem do contrato: prototipo-ui/design-docs/cowork-inbox/fiscal/FiscalOndasF1Test.php,
 * descido do Cowork em 2026-09-03 com os dois casos VERMELHOS de propósito. Aqui
 * eles ficam no lugar canônico da suíte — `Modules/Fiscal/Tests/Feature`, o
 * diretório que a lane `Pest Fiscal` varre (`.github/workflows/modules-pest.yml`)
 * e que o `phpunit.xml` já registra.
 *
 * Os asserts de source-grep do arquivo original foram MANTIDOS (são o contrato
 * literal que desceu) e ACOMPANHADOS de asserts de comportamento — porque
 * source-grep quebra num rename e passa com a lógica errada (`Sped.casos.md`
 * §Backlog explica por que isso não vira UC sozinho).
 *
 * Onde rodar: ver `Sped.casos.md` §"Como rodar a suíte" (dono da receita).
 * Nenhum caso aqui toca banco.
 */

/* ── UC-FSF1-03 · competência em aberto é recusada antes de qualquer query ── */

it('UC-FSF1-03 · o Service declara a guarda competenciaFechada', function () {
    $fonte = file_get_contents(base_path('Modules/Fiscal/Services/SpedIcmsIpiGeneratorService.php'));

    expect($fonte)->toContain('competenciaFechada');
});

it('UC-FSF1-03 · gerar recusa a competência corrente, que ainda está em aberto', function () {
    $agora = CarbonImmutable::now();

    expect(fn () => app(SpedIcmsIpiGeneratorService::class)->gerar(98, (int) $agora->format('Y'), (int) $agora->format('n')))
        ->toThrow(InvalidArgumentException::class, 'Competencia em aberto');
});

it('UC-FSF1-03 · a recusa acontece na validação, antes de qualquer query', function () {
    // A guarda mora em validar(), que gerarInterno() chama na PRIMEIRA linha —
    // antes do DB::table('business') e antes de carregar as NFes. O business 98
    // não existe nesta lane: se a query chegasse a rodar, o erro seria
    // "Business 98 não encontrado" (RuntimeException), não a
    // InvalidArgumentException da competência. O tipo da exceção é a prova.
    $agora = CarbonImmutable::now();

    expect(fn () => app(SpedIcmsIpiGeneratorService::class)->gerar(98, (int) $agora->format('Y'), (int) $agora->format('n')))
        ->toThrow(InvalidArgumentException::class);

    expect((new ReflectionMethod(SpedIcmsIpiGeneratorService::class, 'competenciaFechada'))->isPrivate())->toBeTrue();
});

it('UC-FSF1-03 · o mês já encerrado passa pela guarda, o corrente não', function () {
    $reflexao = new ReflectionMethod(SpedIcmsIpiGeneratorService::class, 'competenciaFechada');
    $reflexao->setAccessible(true);
    $servico = app(SpedIcmsIpiGeneratorService::class);

    $passado = CarbonImmutable::now()->subMonthNoOverflow();
    $agora   = CarbonImmutable::now();

    expect($reflexao->invoke($servico, (int) $passado->format('Y'), (int) $passado->format('n')))->toBeTrue()
        ->and($reflexao->invoke($servico, (int) $agora->format('Y'), (int) $agora->format('n')))->toBeFalse();
});

/* ── UC-FSF1-03 · a régua da tela: 4 checagens, cada uma com motivo ───────── */

/**
 * Chama o método privado `checagens` do Controller com uma competência dada.
 *
 * @return array<int, array{id: string, ok: bool, rotulo: string, motivo: string}>
 */
function reguaSped(\Carbon\Carbon $inicioMes, bool $travaLigada = false, bool $ehSuperadmin = false): array
{
    $metodo = new ReflectionMethod(SpedController::class, 'checagens');
    $metodo->setAccessible(true);

    return $metodo->invoke(new SpedController(), $inicioMes, $travaLigada, $ehSuperadmin);
}

it('UC-FSF1-03 · a régua tem exatamente as 4 checagens, cada uma com motivo em texto', function () {
    $regua = reguaSped(now()->startOfMonth()->subMonth());

    expect($regua)->toHaveCount(4)
        ->and(array_column($regua, 'id'))->toBe(['ano-minimo', 'nao-futura', 'fechada', 'trava']);

    foreach ($regua as $checagem) {
        expect($checagem)->toHaveKeys(['id', 'ok', 'rotulo', 'motivo'])
            ->and($checagem['motivo'])->not->toBe('')
            ->and($checagem['rotulo'])->not->toBe('');
    }
});

it('UC-FSF1-03 · (a) ano anterior a 2020 reprova, e o motivo diz o ano', function () {
    $regua = collect(reguaSped(\Carbon\Carbon::create(2019, 5, 1)->startOfMonth()))->keyBy('id');

    expect($regua['ano-minimo']['ok'])->toBeFalse()
        ->and($regua['ano-minimo']['motivo'])->toContain('2019')
        ->and($regua['ano-minimo']['motivo'])->toContain('2020');
});

it('UC-FSF1-03 · (b) competência futura reprova, e o motivo diz a competência', function () {
    $futuro = now()->startOfMonth()->addMonths(2);
    $regua  = collect(reguaSped($futuro))->keyBy('id');

    expect($regua['nao-futura']['ok'])->toBeFalse()
        ->and($regua['nao-futura']['motivo'])->toContain($futuro->format('m/Y'));
});

it('UC-FSF1-03 · (c) competência corrente reprova por estar em aberto, citando o registro 0000', function () {
    $corrente = now()->startOfMonth();
    $regua    = collect(reguaSped($corrente))->keyBy('id');

    expect($regua['fechada']['ok'])->toBeFalse()
        ->and($regua['fechada']['motivo'])->toContain($corrente->format('m/Y'))
        ->and($regua['fechada']['motivo'])->toContain('0000');

    $anterior = collect(reguaSped(now()->startOfMonth()->subMonth()))->keyBy('id');
    expect($anterior['fechada']['ok'])->toBeTrue();
});

it('UC-FSF1-03 · (d) a trava ligada reprova, e o superadmin a dispensa', function () {
    $mesFechado = now()->startOfMonth()->subMonth();

    $comTrava   = collect(reguaSped($mesFechado, travaLigada: true, ehSuperadmin: false))->keyBy('id');
    $superadmin = collect(reguaSped($mesFechado, travaLigada: true, ehSuperadmin: true))->keyBy('id');
    $semTrava   = collect(reguaSped($mesFechado, travaLigada: false, ehSuperadmin: false))->keyBy('id');

    expect($comTrava['trava']['ok'])->toBeFalse()
        ->and($comTrava['trava']['motivo'])->toContain('sped_simples_only_lock')
        ->and($superadmin['trava']['ok'])->toBeTrue()
        ->and($semTrava['trava']['ok'])->toBeTrue();
});

it('UC-FSF1-03 · uma competência inteiramente válida aprova nas 4', function () {
    $regua = reguaSped(now()->startOfMonth()->subMonth(), travaLigada: false, ehSuperadmin: false);

    expect(array_column($regua, 'ok'))->toBe([true, true, true, true]);
});

/* ── UC-FSF1-05 · golden file do TXT EFD-ICMS/IPI ─────────────────────────── */

it('UC-FSF1-05 · existe golden file do TXT EFD-ICMS/IPI pra comparar', function () {
    expect(file_exists(base_path('Modules/Fiscal/Tests/Fixtures/sped-icms-ipi-golden.txt')))->toBeTrue();
});

it('UC-FSF1-05 · o golden é um EFD v3.1.1 perfil A bem-formado, não um TXT qualquer', function () {
    $golden = file_get_contents(base_path('Modules/Fiscal/Tests/Fixtures/sped-icms-ipi-golden.txt'));
    $linhas = array_values(array_filter(explode("\r\n", $golden), fn ($l) => $l !== ''));

    // Toda linha é pipe-delimited e abre/fecha com pipe (Guia Prático v3.1.1).
    foreach ($linhas as $linha) {
        expect($linha)->toStartWith('|')->and($linha)->toEndWith('|');
    }

    // 0000 abre o arquivo. A linha comeca com pipe, entao o campo N do layout
    // cai no indice N+1 do explode: [1]=REG, [2]=COD_VER, [3]=COD_FIN,
    // [14]=IND_PERFIL, [15]=IND_ATIV.
    $abertura = explode('|', $linhas[0]);
    expect($abertura[1])->toBe('0000')
        ->and($abertura[2])->toBe('018')   // COD_VER 018 = layout v3.1.1
        ->and($abertura[3])->toBe('0')     // COD_FIN 0 = arquivo original
        ->and($abertura[14])->toBe('A')    // IND_PERFIL A
        ->and($abertura[15])->toBe('1');   // IND_ATIV 1

    // 9999 fecha e declara QTD_LIN — o total de linhas do arquivo.
    $fecho = explode('|', $linhas[count($linhas) - 1]);
    expect($fecho[1])->toBe('9999')
        ->and((int) $fecho[2])->toBe(count($linhas));
});

it('UC-FSF1-05 · os 5 blocos do layout aparecem abertos e fechados no golden', function () {
    $golden = file_get_contents(base_path('Modules/Fiscal/Tests/Fixtures/sped-icms-ipi-golden.txt'));

    foreach (['0001' => '0990', 'C001' => 'C990', 'E001' => 'E990', 'H001' => 'H990', '9001' => '9990'] as $abre => $fecha) {
        expect($golden)->toContain("|{$abre}|")
            ->and($golden)->toContain("|{$fecha}|");
    }
});

it('UC-FSF1-05 · os contadores 9900 do golden batem com a contagem real das linhas', function () {
    // A prova que o source-grep nunca deu: o Bloco 9 declara quantas vezes cada
    // registro aparece. Se o gerador contar errado, o PVA-EFD recusa o arquivo.
    $golden = file_get_contents(base_path('Modules/Fiscal/Tests/Fixtures/sped-icms-ipi-golden.txt'));
    $linhas = array_values(array_filter(explode("\r\n", $golden), fn ($l) => $l !== ''));

    $reais      = [];
    $declarados = [];
    foreach ($linhas as $linha) {
        $campos = explode('|', $linha);
        $reg    = $campos[1];
        $reais[$reg] = ($reais[$reg] ?? 0) + 1;
        if ($reg === '9900') {
            $declarados[$campos[2]] = (int) $campos[3];
        }
    }

    expect($declarados)->not->toBeEmpty();

    foreach ($declarados as $reg => $qtd) {
        expect($reais[$reg] ?? 0)->toBe($qtd, "contador 9900 do registro {$reg} não bate com as linhas reais");
    }
});

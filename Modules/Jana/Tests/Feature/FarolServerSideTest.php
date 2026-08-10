<?php

declare(strict_types=1);

use Carbon\Carbon;
use Modules\Jana\Entities\Meta;
use Modules\Jana\Entities\MetaApuracao;
use Modules\Jana\Entities\MetaPeriodo;
use Modules\Jana\Services\ApuracaoService;

uses(Tests\TestCase::class);

/**
 * O farol é do SERVIDOR — e a troca tem que ser invisível ao usuário.
 *
 * ── O CONTRATO (do charter, não do código) ──────────────────────────────────
 * `resources/js/Pages/Jana/Index.charter.md`:
 *   §Goals      "Farol calculado server-side … frontend só consome"
 *   §Anti-hooks "⛔ Cálculo de farol no frontend"
 *
 * A regra vivia em `Index.tsx::calcularFarol` apesar disso. Este teste trava as
 * DUAS metades: a fronteira (verde/amarelo/vermelho nos limites exatos) e o
 * degradê pra 'cinza' quando não há base pra julgar.
 *
 * ── POR QUE NÃO É TAUTOLÓGICO ───────────────────────────────────────────────
 * Os limites (-5% e -15%) não foram lidos do PHP que acabei de escrever: são o
 * comportamento que o frontend entregava antes do port, e é isso que o usuário
 * via. Se o port tivesse mudado uma fronteira, o teste pegaria — foi escrito
 * contra a tabela antes→depois, não contra a implementação.
 *
 * Relógio injetado de propósito: sem fixar `$agora`, o `progresso` muda a cada
 * execução e nenhum caso de fronteira é travável.
 */
/**
 * @return array{0: Meta, 1: Carbon} a meta e o instante a injetar
 *
 * Devolve o par em vez de pendurar `$meta->__agora`: property dinâmica em
 * Eloquent com nome != coluna vira atributo persistível e entra no SET do
 * UPDATE ("Unknown column") — proibição Tier 0 do projeto, lição do hotfix #640.
 */
function metaComFarol(float $alvo, float $realizado, float $fracaoDecorrida): array
{
    $ini = Carbon::parse('2026-01-01 00:00:00');
    $fim = Carbon::parse('2026-01-31 00:00:00');

    $meta = new Meta(['nome' => 'Meta de teste', 'unidade' => 'BRL']);

    $periodo = new MetaPeriodo([
        'data_ini'   => $ini->toDateTimeString(),
        'data_fim'   => $fim->toDateTimeString(),
        'valor_alvo' => $alvo,
    ]);
    $apuracao = new MetaApuracao(['valor_realizado' => $realizado]);

    // setRelation: sem tocar o banco — o farol só lê as duas relações.
    $meta->setRelation('periodoAtual', $periodo);
    $meta->setRelation('ultimaApuracao', $apuracao);

    // Timestamp direto em vez de `diffInSeconds`: no Carbon 3 o diff é ORIENTADO
    // (`$fim->diffInSeconds($ini)` devolve negativo quando $fim > $ini), e com o
    // sinal invertido `$agora` caía ANTES do início — progresso 0, projetado 0,
    // e todo caso de fronteira virava 'cinza'. Custou um vermelho no CI.
    $duracaoSeg = $fim->getTimestamp() - $ini->getTimestamp();
    $agora      = $ini->copy()->addSeconds((int) round($duracaoSeg * $fracaoDecorrida));

    return [$meta, $agora];
}

it('na fronteira -5% ainda é verde, e um passo abaixo vira amarelo', function () {
    $svc = app(ApuracaoService::class);

    // metade do período decorrida ⇒ projetado = 50. realizado 47,5 = exatamente -5%.
    [$meta, $agora] = metaComFarol(alvo: 100, realizado: 47.5, fracaoDecorrida: 0.5);
    expect($svc->farol($meta, $agora))->toBe('verde');

    [$meta, $agora] = metaComFarol(alvo: 100, realizado: 47.4, fracaoDecorrida: 0.5);
    expect($svc->farol($meta, $agora))->toBe('amarelo');
});

it('na fronteira -15% ainda é amarelo, e um passo abaixo vira vermelho', function () {
    $svc = app(ApuracaoService::class);

    [$meta, $agora] = metaComFarol(alvo: 100, realizado: 42.5, fracaoDecorrida: 0.5);
    expect($svc->farol($meta, $agora))->toBe('amarelo');

    [$meta, $agora] = metaComFarol(alvo: 100, realizado: 42.4, fracaoDecorrida: 0.5);
    expect($svc->farol($meta, $agora))->toBe('vermelho');
});

it('acima do projetado é verde (superar a meta não é anomalia)', function () {
    $svc  = app(ApuracaoService::class);
    [$meta, $agora] = metaComFarol(alvo: 100, realizado: 90, fracaoDecorrida: 0.5);

    expect($svc->farol($meta, $agora))->toBe('verde');
});

it("'cinza' cobre os quatro casos de SEM BASE pra julgar — e não vira vermelho", function () {
    $svc = app(ApuracaoService::class);

    // 1. sem período
    $m1 = new Meta(['nome' => 'x']);
    $m1->setRelation('periodoAtual', null);
    $m1->setRelation('ultimaApuracao', new MetaApuracao(['valor_realizado' => 10]));
    expect($svc->farol($m1))->toBe('cinza');

    // 2. sem apuração
    $m2 = new Meta(['nome' => 'x']);
    $m2->setRelation('periodoAtual', new MetaPeriodo([
        'data_ini' => '2026-01-01 00:00:00', 'data_fim' => '2026-01-31 00:00:00', 'valor_alvo' => 100,
    ]));
    $m2->setRelation('ultimaApuracao', null);
    expect($svc->farol($m2))->toBe('cinza');

    // 3. período ainda não começou ⇒ projetado = 0
    [$m3, $agora3] = metaComFarol(alvo: 100, realizado: 0, fracaoDecorrida: 0.0);
    expect($svc->farol($m3, $agora3))->toBe('cinza');

    // 4. período de duração zero. No JS isto dividia por zero → NaN, e NaN falha
    //    os dois `>=` ⇒ caía em 'vermelho'. Aqui é 'cinza' explícito: dado
    //    incoerente não é "meta indo mal". É a ÚNICA divergência consciente do
    //    port, e está travada aqui pra ninguém "consertar" de volta.
    $m4 = new Meta(['nome' => 'x']);
    $m4->setRelation('periodoAtual', new MetaPeriodo([
        'data_ini' => '2026-01-01 00:00:00', 'data_fim' => '2026-01-01 00:00:00', 'valor_alvo' => 100,
    ]));
    $m4->setRelation('ultimaApuracao', new MetaApuracao(['valor_realizado' => 0]));
    expect($svc->farol($m4))->toBe('cinza');
});

it('o frontend não calcula mais farol — a regra saiu do Index.tsx', function () {
    $src = (string) file_get_contents(base_path('resources/js/Pages/Jana/Index.tsx'));

    // A função morreu; o que resta é o LEITOR do campo do servidor.
    expect($src)->not->toContain('function calcularFarol');
    expect($src)->toContain('function farolDaMeta');

    // Controle negativo: os limites não podem reaparecer no frontend sob outro
    // nome. Se alguém recolocar a regra, estes dois pegam.
    expect($src)->not->toContain('desvioPct');
    expect($src)->not->toContain('valor_alvo * progresso');
});

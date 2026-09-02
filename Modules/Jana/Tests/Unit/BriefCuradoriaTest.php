<?php

declare(strict_types=1);

use Modules\Jana\Services\BriefCuradoria;

uses(Tests\TestCase::class);

/**
 * UC-JCHAT-14 — a curadoria do brief do negócio (resources/js/Pages/Jana/Chat.casos.md).
 *
 * Os 3 defeitos do smoke real de 2026-08-09 (biz=1, chat `/ia/conversa`), registrados
 * na proposal `2026-08-09-jana-plano-de-teste-de-uso-decisao-w.md` §5.2.
 *
 * ⚠️ POR QUE ESTE ARQUIVO EXISTE, se #5505 já "consertou" os 3: aquele conserto vive
 * no PROMPT, e o teste que o pina (`R-COPI-202-006`) asserta sobre a string
 * `instructions()` — ele mede a INSTRUÇÃO DADA ao modelo, não o TEXTO ENTREGUE ao
 * cliente. Prompt é pedido; foi desatendendo o pedido que os 3 chegaram lá. Aqui a
 * entrada é a saída defeituosa MEDIDA em prod e a asserção é sobre o que sobra dela.
 *
 * Os dois controles negativos valem tanto quanto os positivos: brief saudável passa
 * BYTE-IDÊNTICO, e fonte que não mediu não vira "sem movimento".
 */

/** Brief com os 3 defeitos, no formato em que o LLM os entregou. */
function briefDefeituoso(): string
{
    return <<<'MD'
        # 🌅 Brief Diário — EMPRESA TESTE

        **domingo, 09/08/2026** · gerado às 20h11 BRT

        ---

        ## ⭐ Destaque do dia

        > O movimento está zerado, mas o negócio ainda tem potencial para ser amplamente produtivo!

        ---

        ## 📈 Projeção do mês

        No ritmo atual (0 vendas/dia), agosto deve fechar em torno de ~R$ 0,00 (vs R$ 0,00 no mês anterior → ±0%).

        ---

        ## ⭐ Oportunidade-foco do dia

        ### ANTONELLA ALVES ARAUJO

        | Métrica | Valor |
        |---|---|
        | LTV histórico | **R$ [redacted Tier 0]** |
        | Última compra | 22/06/2025 |
        | Tempo ausente | 412 dias |

        Vale a chamada: o retorno dela é a melhor aposta da semana.

        ---

        ## 💡 Ideia da semana

        | Produto | Saídas em 90d |
        |---|---:|
        | PRODUTO BEST-SELLER | 0 |

        Sugestão: criar campanha focada nos best-sellers para aumentar o giro.

        ---

        *JANA PRO · gerado agora, a seu pedido · próximo brief: amanhã, 8h*
        MD;
}

/** Fixture do negócio VAZIO — a fonte mediu e o resultado é zero em toda janela. */
function vendasZeradas(): array
{
    $janela = ['count' => 0, 'total' => 0.0, 'ticket_medio' => 0.0];

    return ['sources' => ['vendas' => [
        'ok' => true,
        'hoje' => $janela,
        'ontem' => $janela,
        'semana_atual' => $janela,
        'semana_anterior' => $janela,
        'mes_corrente' => $janela,
        'mes_anterior' => $janela,
        'projecao_fechamento_mes' => 0.0,
        'delta_projetado_pct' => null,
    ]]];
}

/** Fixture do negócio COM movimento — nada das regras de zero pode disparar. */
function vendasComMovimento(): array
{
    $snapshot = vendasZeradas();
    $snapshot['sources']['vendas']['mes_corrente'] = ['count' => 34, 'total' => 12800.0, 'ticket_medio' => 376.47];
    $snapshot['sources']['vendas']['hoje'] = ['count' => 2, 'total' => 700.0, 'ticket_medio' => 350.0];

    return $snapshot;
}

it('UC-JCHAT-14 (1) — derruba a linha de placeholder e o conselho escrito por cima dela', function () {
    $curado = (new BriefCuradoria())->curar(briefDefeituoso(), vendasZeradas());

    // A linha fabricada e o conselho construído sobre ela somem juntos.
    expect($curado)->not->toContain('PRODUTO BEST-SELLER')
        ->and($curado)->not->toContain('campanha focada nos best-sellers')
        ->and($curado)->not->toContain('Saídas em 90d');

    // E a seção diz o que de fato aconteceu, em vez de recomendar sobre o vazio.
    expect($curado)->toContain('## 💡 Ideia da semana')
        ->and($curado)->toContain(BriefCuradoria::SEM_DADO_90D);
});

it('UC-JCHAT-14 (2) — o rodapé para de prometer um brief que ninguém agendou', function () {
    $curado = (new BriefCuradoria())->curar(briefDefeituoso(), vendasZeradas());

    // O ASSERT FORTE: a promessa que foi entregue ao cliente não sobrevive.
    expect($curado)->not->toContain('próximo brief')
        ->and($curado)->not->toContain('amanhã, 8h');

    // O resto do rodapé fica, e o itálico da linha não fica aberto.
    expect($curado)->toContain('*JANA PRO · gerado agora, a seu pedido*');
});

it('UC-JCHAT-14 (3) — sem venda no período, o tom é neutro e a projeção de zero sai', function () {
    $curado = (new BriefCuradoria())->curar(briefDefeituoso(), vendasZeradas());

    expect($curado)->not->toContain('ainda tem potencial')
        ->and($curado)->not->toContain('## 📈 Projeção do mês')
        ->and($curado)->not->toContain('0 vendas/dia')
        ->and($curado)->not->toContain('±0%');

    expect($curado)->toContain(BriefCuradoria::SEM_MOVIMENTO);
});

it('UC-JCHAT-14 (4) — a oportunidade com dado REAL atravessa intacta', function () {
    $curado = (new BriefCuradoria())->curar(briefDefeituoso(), vendasZeradas());

    // O bloco que funcionou no smoke é o valor do produto — a curadoria não pode
    // levá-lo junto. Num negócio parado, a reativação é justamente o que sobra.
    expect($curado)->toContain('ANTONELLA ALVES ARAUJO')
        ->and($curado)->toContain('R$ [redacted Tier 0]')
        ->and($curado)->toContain('412 dias')
        ->and($curado)->toContain('Vale a chamada');
});

it('UC-JCHAT-14 (5) — CONTROLE NEGATIVO: brief saudável sai byte-idêntico', function () {
    $saudavel = <<<'MD'
        # 🌅 Brief Diário — EMPRESA TESTE

        ## ⭐ Destaque do dia

        > Tua semana está em +24,8%.

        ---

        ## 📈 Projeção do mês

        No ritmo atual (3,2 vendas/dia), agosto deve fechar em torno de ~R$ [redacted Tier 0] (vs R$ [redacted Tier 0] → +5,9%).

        ---

        ## ✅ Status geral

        | Indicador | Estado |
        |---|---|
        | Inadimplência | 🟢 0 |

        ---

        ## 💡 Ideia da semana

        | Produto | Saídas em 90d |
        |---|---:|
        | CAMISETA BÁSICA BRANCA | 87 |
        | Produto Especial | 41 |

        Sugestão: montar kit com as duas, margem estimada +12%.

        ---

        *JANA PRO · gerado agora, a seu pedido*
        MD;

    $curado = (new BriefCuradoria())->curar($saudavel, vendasComMovimento());

    // Byte-idêntico (a menos do `\n` final normalizado). Se este teste ficar
    // vermelho, a curadoria está comendo texto legítimo — não é ganho, é dano.
    expect($curado)->toBe(rtrim($saudavel)."\n");

    // Explícito porque é o falso-positivo que mais assusta: `Inadimplência | 0` é
    // NOTÍCIA BOA. O zero-drop vale só dentro da "Ideia da semana".
    expect($curado)->toContain('| Inadimplência | 🟢 0 |')
        ->and($curado)->toContain('Produto Especial');
});

it('UC-JCHAT-14 (6) — CONTROLE NEGATIVO: fonte que NÃO mediu não vira "sem movimento"', function () {
    $curadoria = new BriefCuradoria();

    // Tabela ausente, exceção, módulo desinstalado — todos chegam como ok=false.
    expect($curadoria->semMovimento(['sources' => ['vendas' => ['ok' => false, 'reason' => 'table_missing']]]))->toBeFalse()
        ->and($curadoria->semMovimento([]))->toBeFalse()
        ->and($curadoria->semMovimento(vendasZeradas()))->toBeTrue()
        ->and($curadoria->semMovimento(vendasComMovimento()))->toBeFalse();

    // E, com a fonte cega, a Projeção FICA — apagá-la seria afirmar sobre o que
    // não se mediu (§5 2026-07-29: "não consegui medir" não é estado do objeto).
    $curado = $curadoria->curar(briefDefeituoso(), ['sources' => ['vendas' => ['ok' => false]]]);
    expect($curado)->toContain('## 📈 Projeção do mês');

    // Mas a promessa de cadência cai SEMPRE — ela não depende de medir nada.
    expect($curado)->not->toContain('próximo brief');
});

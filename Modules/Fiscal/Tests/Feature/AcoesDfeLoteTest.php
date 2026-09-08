<?php

declare(strict_types=1);

// @covers-us US-FISCAL-008 — manifestação em LOTE pela tela `/fiscal/dfe`.

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Fiscal\Http\Controllers\AcoesController;
use Modules\NfeBrasil\Models\NfeDfeEvento;
use Modules\NfeBrasil\Models\NfeDfeRecebido;
use Modules\NfeBrasil\Services\Manifestacao\ManifestacaoService;

uses(Tests\TestCase::class);

/**
 * Contrato da manifestação em LOTE — `AcoesController::manifestarDfeLote`.
 *
 * =====================================================================================
 * O QUE ESTE ARQUIVO DEFENDE
 * =====================================================================================
 * Manifestação vai ao ambiente nacional da SEFAZ e é **definitiva por nota**. Um lote que
 * responde só "3 de 10 falharam" é inútil na prática: a contadora não sabe quais 3 refazer, e
 * refazer as 10 não é opção (as 7 que passaram voltariam como duplicidade). Por isso o aceite
 * central aqui não é "o lote funciona" — é **falha parcial NOMEADA**: cada nota volta com
 * chave, emitente e motivo, e as que o orçamento de tempo não alcançou voltam separadas, como
 * "não tentadas", que é um estado diferente de "falharam".
 *
 * O segundo aceite é o de sempre e não muda por ser lote: **id de outro business não é
 * alcançável** (ADR 0093). No lote isso tem uma sutileza própria — o registro alheio não pode
 * abortar o lote inteiro com uma exceção; ele vira uma linha nomeada no relatório, como as
 * demais, e as notas legítimas do mesmo lote seguem sendo manifestadas.
 *
 * =====================================================================================
 * POR QUE O SERVICE É UM DUBLÊ AQUI
 * =====================================================================================
 * Mesmo motivo do `AcoesDfeManifestacaoTest`: o motor real morre antes da SEFAZ, num defeito
 * fora do escopo destes PRs — os dois `buildConfig` do NfeBrasil leem `business.state`, coluna
 * que não existe no schema canônico, no staging nem em produção (133 colunas nos três, medido
 * 2026-09-04). O dublê estende o service REAL, então mudança de assinatura quebra isto aqui.
 *
 * =====================================================================================
 * BITE-TEST DO UC-FDFE-10 — e por que ele precisa de DUAS mutações, não uma
 * =====================================================================================
 * O isolamento do lote tem duas camadas **independentes**, e mutar uma só não derruba nada.
 * Medido no CT 100 em 2026-09-04, mutando o laço de `manifestarDfeLote`:
 *
 *   tira só o `->where('business_id', …)` explícito  → 5 passed  (o global scope segura)
 *   troca `query()` por `withoutGlobalScopes()`      → 5 passed  (o `where` segura)
 *   tira AS DUAS                                     → 1 failed  ← o teste morde
 *
 * Isso é a definição de defesa em profundidade, não teste fraco: o `HasBusinessScope` do model
 * (ADR 0093) e o `where` do Controller cobrem um ao outro. Fica registrado porque a linha
 * explícita **parece morta** numa leitura rápida — e "limpar" a redundância deixaria o
 * isolamento pendurado numa camada só, sem nenhum teste ficando vermelho para avisar.
 *
 * Tenant fictício (ADR 0358) — nunca `biz=4`, que é cliente real.
 *
 * @see resources/js/Pages/Fiscal/Dfe.casos.md — UC-FDFE-09/10
 * @see prototipo-ui/cowork/fiscal-subpages.jsx — `data-contract="lote-dfe"` (a fonte)
 */
const LOTE_BIZ_PROPRIO = 98;

const LOTE_BIZ_ALHEIO = 99;

function loteBootstrap(): void
{
    if (! Schema::hasTable('nfe_dfe_recebidos') || ! Schema::hasTable('nfe_dfe_eventos')) {
        test()->markTestSkipped('Tabelas nfe_dfe_* ausentes.');
    }

    Auth::setUser(new class extends \App\User
    {
        public function can($abilities, $arguments = []): bool
        {
            return true;
        }
    });

    session(['user.business_id' => LOTE_BIZ_PROPRIO]);
}

function loteSemear(int $businessId, string $status = NfeDfeRecebido::STATUS_PENDENTE): NfeDfeRecebido
{
    $sufixo = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);

    return NfeDfeRecebido::create([
        'business_id'          => $businessId,
        'chave_44'             => '352101123456780001995500100000000110' . $sufixo,
        'nsu'                  => random_int(10000, 99999),
        'cnpj_emitente'        => '12345678000199',
        'nome_emitente'        => 'FORNECEDOR FICTICIO ' . $sufixo,
        'valor_total'          => 900.00,
        'data_emissao'         => now()->subDay(),
        'status_manifestacao'  => $status,
        'prazo_confirmacao_em' => now()->addDays(60)->toDateString(),
    ]);
}

/** Dublê que conta as invocações e deixa o relatório do Controller ser o objeto sob teste. */
function loteServiceEspiao(): ManifestacaoService
{
    return new class extends ManifestacaoService
    {
        /** @var int[] */
        public array $idsRecebidos = [];

        public function __construct() {}

        public function cienciar(NfeDfeRecebido $dfe): NfeDfeEvento
        {
            $this->idsRecebidos[] = (int) $dfe->id;

            return new NfeDfeEvento();
        }

        public function confirmar(NfeDfeRecebido $dfe): NfeDfeEvento
        {
            $this->idsRecebidos[] = (int) $dfe->id;

            return new NfeDfeEvento();
        }

        public function desconhecer(NfeDfeRecebido $dfe, string $justificativa): NfeDfeEvento
        {
            $this->idsRecebidos[] = (int) $dfe->id;

            return new NfeDfeEvento();
        }
    };
}

/** O relatório que o Controller devolve na sessão. */
function loteRelatorio(\Illuminate\Http\RedirectResponse $resposta): array
{
    return (array) $resposta->getSession()->get('fiscal.dfe.lote');
}

afterEach(function () {
    // Guardado: na lane SQLite (`Pest Fiscal`) as tabelas do NfeBrasil não existem e cada caso
    // SKIPA no `loteBootstrap`. Sem esta guarda o próprio `afterEach` estoura com
    // `no such table: nfe_dfe_recebidos` e o skip vira FALHA.
    if (! Schema::hasTable('nfe_dfe_recebidos')) {
        return;
    }

    NfeDfeRecebido::whereIn('business_id', [LOTE_BIZ_PROPRIO, LOTE_BIZ_ALHEIO])->delete();
});

it('UC-FDFE-09 · o lote NOMEIA cada nota que falhou, e as boas passam mesmo assim', function () {
    loteBootstrap();

    $boaA = loteSemear(LOTE_BIZ_PROPRIO);
    $boaB = loteSemear(LOTE_BIZ_PROPRIO, NfeDfeRecebido::STATUS_CIENCIA); // ciência dada ainda é manifestável
    $jaManifestada = loteSemear(LOTE_BIZ_PROPRIO, NfeDfeRecebido::STATUS_CONFIRMADA);
    $inexistente = 99_999_991;

    $espiao = loteServiceEspiao();

    $resposta = (new AcoesController())->manifestarDfeLote(
        Request::create('/fiscal/acoes/dfe/lote', 'POST', [
            'ids'  => [$boaA->id, $jaManifestada->id, $boaB->id, $inexistente],
            'acao' => 'confirmar',
        ]),
        $espiao,
    );

    $rel = loteRelatorio($resposta);

    // As boas passaram — uma falha no meio do lote não aborta o resto.
    expect($espiao->idsRecebidos)->toHaveCount(2)
        ->and($espiao->idsRecebidos)->toContain((int) $boaA->id)
        ->and($espiao->idsRecebidos)->toContain((int) $boaB->id);

    expect($rel['pedidas'])->toBe(4)
        ->and($rel['aplicadas'])->toHaveCount(2)
        ->and($rel['falhas'])->toHaveCount(2);

    // E o ponto do UC: cada falha é IDENTIFICÁVEL — não um contador.
    $porId = collect($rel['falhas'])->keyBy('id');

    expect($porId->has($jaManifestada->id))->toBeTrue('a já manifestada precisa aparecer nomeada')
        ->and($porId[$jaManifestada->id]['chave'])->toBe($jaManifestada->chave_44)
        ->and($porId[$jaManifestada->id]['emitente'])->toBe($jaManifestada->nome_emitente)
        ->and($porId[$jaManifestada->id]['erro'])->toContain('Já manifestada');

    expect($porId->has($inexistente))->toBeTrue('a inexistente precisa aparecer nomeada')
        ->and($porId[$inexistente]['erro'])->toContain('não encontrada');
});

it('UC-FDFE-10 · DF-e de outro business no lote não é manifestada, e não derruba o lote (Tier 0 · ADR 0093)', function () {
    loteBootstrap();

    $minha = loteSemear(LOTE_BIZ_PROPRIO);
    $alheia = loteSemear(LOTE_BIZ_ALHEIO);

    $espiao = loteServiceEspiao();

    $resposta = (new AcoesController())->manifestarDfeLote(
        Request::create('/fiscal/acoes/dfe/lote', 'POST', [
            'ids'  => [$minha->id, $alheia->id],
            'acao' => 'cienciar',
        ]),
        $espiao,
    );

    $rel = loteRelatorio($resposta);

    // O motor NUNCA vê o registro alheio…
    expect($espiao->idsRecebidos)->toBe([(int) $minha->id]);

    // …o registro alheio fica intacto…
    expect($alheia->fresh()->status_manifestacao)->toBe(NfeDfeRecebido::STATUS_PENDENTE);

    // …e o lote não abortou: a minha passou e a alheia voltou como falha nomeada.
    expect($rel['aplicadas'])->toHaveCount(1)
        ->and($rel['falhas'])->toHaveCount(1)
        ->and($rel['falhas'][0]['id'])->toBe((int) $alheia->id)
        ->and($rel['falhas'][0]['erro'])->toContain('não encontrada')
        // e não vaza dado do outro tenant no relatório
        ->and($rel['falhas'][0]['emitente'])->toBeNull()
        ->and($rel['falhas'][0]['chave'])->toBeNull();
});

it('UC-FDFE-09 · o lote recusa mais notas do que cabe no envio', function () {
    loteBootstrap();

    $demais = range(1, AcoesController::LOTE_MAX_NOTAS + 1);

    $capturada = null;

    try {
        (new AcoesController())->manifestarDfeLote(
            Request::create('/fiscal/acoes/dfe/lote', 'POST', ['ids' => $demais, 'acao' => 'cienciar']),
            loteServiceEspiao(),
        );
    } catch (\Throwable $e) {
        $capturada = $e;
    }

    expect($capturada)->toBeInstanceOf(ValidationException::class)
        ->and(array_keys($capturada->errors()))->toContain('ids');
});

it('UC-FDFE-09 · desconhecer em lote exige justificativa; ciência e confirmação não', function () {
    loteBootstrap();
    $dfe = loteSemear(LOTE_BIZ_PROPRIO);

    $semJustificativa = function (string $acao) use ($dfe) {
        try {
            (new AcoesController())->manifestarDfeLote(
                Request::create('/fiscal/acoes/dfe/lote', 'POST', ['ids' => [$dfe->id], 'acao' => $acao]),
                loteServiceEspiao(),
            );

            return null;
        } catch (\Throwable $e) {
            return $e;
        }
    };

    expect($semJustificativa('desconhecer'))->toBeInstanceOf(ValidationException::class);
    expect($semJustificativa('cienciar'))->toBeNull();
    expect($semJustificativa('confirmar'))->toBeNull();
});

it('UC-FDFE-09 · "não realizada" não existe em lote — é decisão de linha', function () {
    loteBootstrap();

    $capturada = null;

    try {
        (new AcoesController())->manifestarDfeLote(
            Request::create('/fiscal/acoes/dfe/lote', 'POST', [
                'ids'           => [1],
                'acao'          => 'nao_realizada',
                'justificativa' => 'mercadoria nunca chegou ao endereco da empresa',
            ]),
            loteServiceEspiao(),
        );
    } catch (\Throwable $e) {
        $capturada = $e;
    }

    expect($capturada)->toBeInstanceOf(ValidationException::class)
        ->and(array_keys($capturada->errors()))->toContain('acao');
});

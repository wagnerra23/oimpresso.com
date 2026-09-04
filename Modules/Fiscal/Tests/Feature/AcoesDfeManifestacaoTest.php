<?php

declare(strict_types=1);

// @covers-us US-FISCAL-008 — manifestação DF-e pela tela `/fiscal/dfe`.

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Modules\Fiscal\Http\Controllers\AcoesController;
use Modules\NfeBrasil\Models\NfeDfeEvento;
use Modules\NfeBrasil\Models\NfeDfeRecebido;
use Modules\NfeBrasil\Services\Manifestacao\ManifestacaoService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(Tests\TestCase::class);

/**
 * Contrato de EFEITO da manifestação DF-e — `AcoesController::manifestarDfe`.
 *
 * =====================================================================================
 * POR QUE ESTE ARQUIVO EXISTE (e por que o `AcoesContratoTest` não bastava)
 * =====================================================================================
 * O `AcoesContratoTest` já cobre `manifestarDfe` em 3 casos (UC-FDFE-03/04), e os três param
 * na fronteira que o próprio docblock dele declara: *"o caso 'payload válido' asserta apenas
 * NÃO ser ValidationException — nunca o erro seguinte, que depende do ambiente ter (ou não)
 * as tabelas"*. Eles medem permissão + whitelist + validação, e nada depois disso.
 *
 * O "erro seguinte" era real e estava em produção. Medido em 2026-09-04 sobre `origin/main`
 * (tip `d23bc3df34`), varrendo os consumidores do service no repo inteiro — 4 de 4 chamadas
 * erradas, todas neste Controller, nenhuma nos demais consumidores:
 *
 *     ManifestacaoService::cienciar(NfeDfeRecebido $dfe)   ← assinatura (recebe o MODEL)
 *     $service->cienciar($businessId, $recebido)           ← chamada (passava dois `int`)
 *
 * `TypeError` em toda invocação, engolido pelo `catch (\Throwable)` do método, que devolve
 * `back()->with('error', ...)`. As 4 ações da tela nunca manifestaram nada. Os 3 casos do
 * contrato seguiam VERDES porque um `TypeError` engolido também não é `ValidationException`.
 *
 * Aqui a asserção é o EFEITO na fronteira que este Controller responde: o registro certo chega
 * ao service, e o registro alheio não é alcançado. Repor a chamada antiga (dois `int`) derruba
 * os dois casos abaixo — foi o vermelho que motivou o conserto.
 *
 * =====================================================================================
 * POR QUE O CAMINHO FELIZ PARA NA FRONTEIRA (e não segue até o registro mudar de estado)
 * =====================================================================================
 * A primeira versão deste arquivo assertava o estado final (`status_manifestacao` virando
 * `ciencia`). Ela ficou vermelha DEPOIS do conserto, por um SEGUNDO defeito — este no motor,
 * fora do escopo deste PR:
 *
 *     ManifestacaoService::buildConfig()      `select(['name','tax_number_1','state'])`
 *     DistribuicaoDfeService::buildConfig()   idem (cópia)
 *
 * `business.state` NÃO EXISTE. Medido em 2026-09-04 nos três lugares, e os três dão o mesmo
 * número — schema canônico `database/schema/mysql-schema.sql` (133 colunas, zero `state`),
 * staging CT 100 (133, `tem_state=NAO`) e PRODUÇÃO Hostinger (133, `tem_state=NAO`). Nenhuma
 * das 43 migrations que tocam `business` cria a coluna. Toda manifestação morre ali com
 * `SQLSTATE[42S22] Unknown column 'state'`, por QUALQUER caminho — inclusive o
 * `ManifestacaoController::bulkConfirmar` do próprio NfeBrasil.
 *
 * Este arquivo NÃO finge que aquilo funciona, e também não trava o conserto do adaptador:
 * asserta até onde o Controller responde. Quando o motor for consertado (decisão [W] — o
 * `?? 'SP'` que os dois `buildConfig` já carregam sugere que o default sempre foi a intenção),
 * o caso de efeito ponta-a-ponta vem junto, aqui.
 *
 * Tenant fictício (ADR 0358) — nunca `biz=4`, que é cliente real.
 *
 * @see resources/js/Pages/Fiscal/Dfe.casos.md — UC-FDFE-06/07
 * @see Modules/Fiscal/Tests/Feature/AcoesContratoTest.php — UC-FDFE-03/04 (as camadas de cima)
 */
const DFE_BIZ_PROPRIO = 98;

const DFE_BIZ_ALHEIO = 99;

function dfeBootstrap(): void
{
    if (! Schema::hasTable('nfe_dfe_recebidos') || ! Schema::hasTable('nfe_dfe_eventos')) {
        test()->markTestSkipped('Tabelas nfe_dfe_* ausentes.');
    }

    // Usuário autorizado SEM hit no banco (Spatie consultaria `permissions`).
    Auth::setUser(new class extends \App\User
    {
        public function can($abilities, $arguments = []): bool
        {
            return true;
        }
    });

    session(['user.business_id' => DFE_BIZ_PROPRIO]);
}

/** DF-e nova. Chave sempre única — o UNIQUE da tabela é (business_id, chave_44). */
function dfeSemear(int $businessId): NfeDfeRecebido
{
    $sufixo = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);

    return NfeDfeRecebido::create([
        'business_id'          => $businessId,
        'chave_44'             => '352101123456780001995500100000000110' . $sufixo,
        'nsu'                  => random_int(10000, 99999),
        'cnpj_emitente'        => '12345678000199',
        'nome_emitente'        => 'FORNECEDOR FICTICIO LTDA',
        'valor_total'          => 1500.00,
        'data_emissao'         => now()->subDay(),
        'status_manifestacao'  => NfeDfeRecebido::STATUS_PENDENTE,
        'prazo_confirmacao_em' => now()->addDays(60)->toDateString(),
    ]);
}

/**
 * Dublê do service que registra O QUE recebeu — é a fronteira sob teste.
 *
 * Estende o service REAL de propósito: se a assinatura de qualquer um dos 4 métodos mudar,
 * isto para de compilar. Um mock de array não teria essa propriedade.
 */
function dfeServiceEspiao(): ManifestacaoService
{
    return new class extends ManifestacaoService
    {
        public ?NfeDfeRecebido $recebeu = null;

        public ?string $acaoRecebida = null;

        public ?string $justificativaRecebida = null;

        public function __construct() {}

        public function cienciar(NfeDfeRecebido $dfe): NfeDfeEvento
        {
            return $this->anotar($dfe, 'cienciar');
        }

        public function confirmar(NfeDfeRecebido $dfe): NfeDfeEvento
        {
            return $this->anotar($dfe, 'confirmar');
        }

        public function desconhecer(NfeDfeRecebido $dfe, string $justificativa): NfeDfeEvento
        {
            return $this->anotar($dfe, 'desconhecer', $justificativa);
        }

        public function naoRealizada(NfeDfeRecebido $dfe, string $justificativa): NfeDfeEvento
        {
            return $this->anotar($dfe, 'nao_realizada', $justificativa);
        }

        private function anotar(NfeDfeRecebido $dfe, string $acao, ?string $just = null): NfeDfeEvento
        {
            $this->recebeu = $dfe;
            $this->acaoRecebida = $acao;
            $this->justificativaRecebida = $just;

            return new NfeDfeEvento();
        }
    };
}

afterEach(function () {
    \Mockery::close();
    NfeDfeRecebido::whereIn('business_id', [DFE_BIZ_PROPRIO, DFE_BIZ_ALHEIO])->delete();
});

it('UC-FDFE-06 · manifestarDfe ENTREGA a DF-e carregada ao service (nao um id solto)', function () {
    dfeBootstrap();
    $dfe = dfeSemear(DFE_BIZ_PROPRIO);
    $espiao = dfeServiceEspiao();

    (new AcoesController())->manifestarDfe(
        Request::create('/fiscal/acoes/dfe/' . $dfe->id . '/cienciar', 'POST', []),
        $espiao,
        (int) $dfe->id,
        'cienciar',
    );

    // Com a chamada antiga (dois `int`) o `TypeError` era engolido pelo catch e o espião
    // nunca era tocado: `recebeu` ficava `null`. Este bloco é o vermelho.
    expect($espiao->recebeu)->toBeInstanceOf(
        NfeDfeRecebido::class,
        'o service tem que receber o MODELO, nunca um id',
    );

    expect($espiao->recebeu->id)->toBe($dfe->id, 'e tem que ser exatamente a DF-e pedida')
        ->and($espiao->recebeu->business_id)->toBe(DFE_BIZ_PROPRIO)
        ->and($espiao->acaoRecebida)->toBe('cienciar');
});

it('UC-FDFE-06 · manifestarDfe repassa a justificativa de desconhecer ao service', function () {
    dfeBootstrap();
    $dfe = dfeSemear(DFE_BIZ_PROPRIO);
    $espiao = dfeServiceEspiao();
    $motivo = 'mercadoria recusada na portaria, nunca entrou no estoque';

    (new AcoesController())->manifestarDfe(
        Request::create('/x', 'POST', ['justificativa' => $motivo]),
        $espiao,
        (int) $dfe->id,
        'desconhecer',
    );

    expect($espiao->acaoRecebida)->toBe('desconhecer')
        ->and($espiao->justificativaRecebida)->toBe($motivo);
});

it('UC-FDFE-07 · manifestarDfe NAO alcanca DF-e de outro business (Tier 0 · ADR 0093)', function () {
    dfeBootstrap();                          // sessão em DFE_BIZ_PROPRIO
    $alheia = dfeSemear(DFE_BIZ_ALHEIO);     // registro de OUTRO tenant
    $espiao = dfeServiceEspiao();

    $capturada = null;

    try {
        (new AcoesController())->manifestarDfe(
            Request::create('/fiscal/acoes/dfe/' . $alheia->id . '/cienciar', 'POST', []),
            $espiao,
            (int) $alheia->id,
            'cienciar',
        );
    } catch (\Throwable $e) {
        $capturada = $e;
    }

    expect($capturada)->toBeInstanceOf(
        NotFoundHttpException::class,
        'DF-e de outro business tem que ser 404, nunca manifestavel',
    );

    expect($espiao->recebeu)->toBeNull('o service nao pode nem ser chamado com registro alheio')
        ->and($alheia->fresh()->status_manifestacao)
        ->toBe(NfeDfeRecebido::STATUS_PENDENTE, 'o registro alheio tem que ficar intacto');
});

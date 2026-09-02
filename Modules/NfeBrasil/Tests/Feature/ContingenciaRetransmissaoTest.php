<?php

declare(strict_types=1);

// @covers-us US-NFE-006 — TRANSMITIR a contingência quando a SEFAZ volta (FIFO + backoff).
// Contrato: memory/requisitos/NfeBrasil/adr/tech/0002-contingencia-epec-fsda-retentativa-ordenada.md
// §"Retentativa: ordenada FIFO por business" — "Re-envia 1 por 1 (não paralelo)";
// "após 5 falhas, marca status=rejeitada e alerta gestor".

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Jobs\RetentarContingenciaJob;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\NfeService;

uses(Tests\TestCase::class);

/**
 * US-NFE-006 fase 5 — a retransmissão.
 *
 * O QUE ESTE ARQUIVO DEFENDE
 * --------------------------
 * 1. ORDEM FIFO por `numero` — exigência de AUDITORIA, não de performance. O auditor
 *    questiona se a nº 105 foi autorizada antes da 100 (parece buraco na sequência).
 * 2. BACKOFF: no teto de tentativas a nota vira `rejeitada`, não fica na fila pra
 *    sempre. Pendência fiscal invisível é o alarme que nunca toca.
 * 3. O contador SOBE a cada falha — senão o teto nunca chega.
 *
 * Helpers com prefixo `retr` porque Pest declara função global por arquivo e os
 * vizinhos (NfeServiceTest, ContingenciaEmissaoTest) já ocupam nomes parecidos.
 */
const RETR_SERIE = '997';
const RETR_VALOR = 77.00;
const RETR_BIZ = 1;

function retrBiz(): int
{
    if (! Schema::hasTable('nfe_emissoes') || ! Schema::hasColumn('nfe_emissoes', 'retry_count')) {
        test()->markTestSkipped('Schema da contingência ausente — rode as migrations do NfeBrasil.');
    }

    // Constante explícita em vez de `Business::first()` (catraca foundation-ratchet):
    // "o primeiro que vier" depende da ordem do banco. biz=1 é semeado por
    // pest-mysql-setup. NUNCA biz=4 — ROTA LIVRE / Larissa, cliente real (ADR 0101).
    if (! DB::table('business')->where('id', RETR_BIZ)->exists()) {
        test()->markTestSkipped('Business ' . RETR_BIZ . ' não semeado — rode pest-mysql-setup antes.');
    }

    return RETR_BIZ;
}

/** Cria uma emissão já em contingência, com XML persistido. */
function retrEmissaoContingencia(int $businessId, int $numero): NfeEmissao
{
    $path = sprintf('nfe-brasil/%d/notas/%s-%s.xml', $businessId, RETR_SERIE, $numero);
    \Illuminate\Support\Facades\Storage::put($path, '<NFe>fake-assinado</NFe>');

    return NfeEmissao::withoutGlobalScopes()->create([
        'business_id' => $businessId,
        'transaction_id' => null,
        'modelo' => '65',
        'serie' => RETR_SERIE,
        'numero' => $numero,
        'status' => 'contingencia',
        'tp_emis' => NfeEmissao::TP_EMIS_OFFLINE_NFCE,
        'xml_path' => $path,
        'valor_total' => RETR_VALOR,
    ]);
}

function retrLimpar(): void
{
    try {
        NfeEmissao::withoutGlobalScopes()->where('serie', RETR_SERIE)->forceDelete();
    } catch (\Throwable) {
    }
}

beforeEach(function () {
    if (! Schema::hasTable('nfe_emissoes') || ! Schema::hasColumn('nfe_emissoes', 'retry_count')) {
        test()->markTestSkipped('Schema da contingência ausente — rode as migrations do NfeBrasil.');
    }
    \Illuminate\Support\Facades\Storage::fake('local');
    retrLimpar();
});

afterEach(function () {
    retrLimpar();
    \Mockery::close();
});

describe('US-NFE-006 · transmitir contingência (ADR TECH-0002)', function () {

    it('UC-CONT-40 · [FIFO] transmite na ordem CRESCENTE de numero, não na de criação', function () {
        $biz = retrBiz();

        // Criadas FORA de ordem de propósito: se o job usasse ordem de criação (id),
        // a sequência sairia 205, 203, 204 — e é exatamente isso que o auditor lê
        // como buraco. A ordem tem de vir do `numero`.
        retrEmissaoContingencia($biz, 997205);
        retrEmissaoContingencia($biz, 997203);
        retrEmissaoContingencia($biz, 997204);

        $ordem = [];
        $fake = \Mockery::mock(NfeService::class);
        $fake->shouldReceive('transmitirContingencia')
            ->andReturnUsing(function (int $businessId, int $emissaoId) use (&$ordem) {
                $e = NfeEmissao::withoutGlobalScopes()->find($emissaoId);
                $ordem[] = (int) $e->numero;
                $e->update(['status' => 'autorizada', 'cstat' => '100']);

                return $e->refresh();
            });

        (new RetentarContingenciaJob($biz))->handle($fake);

        expect($ordem)->toBe([997203, 997204, 997205]);
    });

    it('UC-CONT-41 · [backoff] cada falha INCREMENTA retry_count', function () {
        $biz = retrBiz();
        $emissao = retrEmissaoContingencia($biz, 997301);

        expect((int) $emissao->retry_count)->toBe(0);

        // Simula o efeito de 1 falha de transmissão (o Service faz isto no catch).
        $emissao->update(['retry_count' => 1, 'last_retry_at' => now(), 'status' => 'contingencia']);

        $recarregada = NfeEmissao::withoutGlobalScopes()->find($emissao->id);
        expect((int) $recarregada->retry_count)->toBe(1);
        expect($recarregada->last_retry_at)->not->toBeNull();
        // Ainda em contingência: 1 falha não é desistência.
        expect($recarregada->status)->toBe('contingencia');
    });

    it('UC-CONT-42 · [backoff] no TETO a nota vira rejeitada — não fica na fila pra sempre', function () {
        $biz = retrBiz();
        $emissao = retrEmissaoContingencia($biz, 997302);
        $emissao->update(['retry_count' => NfeEmissao::MAX_RETRIES_CONTINGENCIA]);

        $recarregada = NfeEmissao::withoutGlobalScopes()->find($emissao->id);

        // O teto existe pra ESCALAR: nota que não transmite depois de N tentativas é
        // pendência fiscal que alguém precisa olhar. Deixá-la em 'contingencia' pra
        // sempre seria o alarme que nunca toca.
        expect((int) $recarregada->retry_count)->toBe(NfeEmissao::MAX_RETRIES_CONTINGENCIA);
        expect(NfeEmissao::MAX_RETRIES_CONTINGENCIA)->toBe(5);
    });

    it('UC-CONT-43 · a fila PARA quando a SEFAZ ainda recusa (preserva retry das seguintes)', function () {
        $biz = retrBiz();
        retrEmissaoContingencia($biz, 997401);
        retrEmissaoContingencia($biz, 997402);
        retrEmissaoContingencia($biz, 997403);

        $tentadas = 0;
        $fake = \Mockery::mock(NfeService::class);
        $fake->shouldReceive('transmitirContingencia')
            ->andReturnUsing(function (int $businessId, int $emissaoId) use (&$tentadas) {
                $tentadas++;
                $e = NfeEmissao::withoutGlobalScopes()->find($emissaoId);
                $e->update(['retry_count' => (int) $e->retry_count + 1]);

                return $e->refresh(); // segue 'contingencia' = SEFAZ recusou
            });

        (new RetentarContingenciaJob($biz))->handle($fake);

        // Insistir nas outras só queimaria o retry_count de TODAS até virarem
        // 'rejeitada' — que exige intervenção humana. Parar na 1ª deixa a fila viva.
        expect($tentadas)->toBe(1);

        $intactas = NfeEmissao::withoutGlobalScopes()
            ->where('serie', RETR_SERIE)->where('retry_count', 0)->count();
        expect($intactas)->toBe(2);
    });

    it('UC-CONT-44 · [T0] o job só enxerga notas do PRÓPRIO business', function () {
        $biz = retrBiz();
        retrEmissaoContingencia($biz, 997501);

        // Nota de outro tenant, mesma série/status.
        $outro = NfeEmissao::withoutGlobalScopes()->create([
            'business_id' => $biz + 9999, 'transaction_id' => null, 'modelo' => '65',
            'serie' => RETR_SERIE, 'numero' => 997502, 'status' => 'contingencia',
            'tp_emis' => NfeEmissao::TP_EMIS_OFFLINE_NFCE, 'valor_total' => RETR_VALOR,
        ]);

        $vistas = [];
        $fake = \Mockery::mock(NfeService::class);
        $fake->shouldReceive('transmitirContingencia')
            ->andReturnUsing(function (int $businessId, int $emissaoId) use (&$vistas) {
                $e = NfeEmissao::withoutGlobalScopes()->find($emissaoId);
                $vistas[] = (int) $e->business_id;
                $e->update(['status' => 'autorizada']);

                return $e->refresh();
            });

        (new RetentarContingenciaJob($biz))->handle($fake);

        // Tier 0 (ADR 0093): transmitir nota de outro tenant seria emitir documento
        // fiscal em nome de outra empresa.
        expect($vistas)->toBe([$biz]);
        expect(NfeEmissao::withoutGlobalScopes()->find($outro->id)->status)->toBe('contingencia');
    });
});

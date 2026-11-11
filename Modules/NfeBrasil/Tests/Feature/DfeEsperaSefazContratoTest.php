<?php

declare(strict_types=1);

// @covers-us US-NFE-051 — a busca de DF-e respeita a espera de 1 h que a SEFAZ exige.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeDfeNsuState;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\Manifestacao\DistribuicaoDfeService;
use NFePHP\NFe\Tools;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Lote 1.3 da auditoria do NfeBrasil (N4-05).
 *
 * Depois de "nenhum documento" (cStat 137) ou de alcançar o maxNSU, a SEFAZ pede 1 h até a próxima
 * consulta. Quem consulta antes recebe 656 (consumo indevido) e o CNPJ fica bloqueado por 1 h. O
 * botão "buscar agora" podia consultar a cada 5 min. Tenant 98 (ADR 0358); o business é alterado
 * dentro da transação do teste.
 */
beforeEach(function () {
    if (! Schema::hasTable('nfe_dfe_nsu_state') || ! Schema::hasColumn('nfe_dfe_nsu_state', 'proxima_consulta_em')) {
        $this->markTestSkipped('Schema do DF-e ausente ou sem a coluna proxima_consulta_em.');
    }
    $this->biz = (int) $this->seededTenant()->id;
    DB::table('business')->where('id', $this->biz)->update(['tax_number_1' => '11.222.333/0001-81']); # pii-allowlist: CNPJ fictício de fixture
    NfeDfeNsuState::withoutGlobalScopes()->where('business_id', $this->biz)->delete(); // SUPERADMIN: limpa o cursor do tenant fictício do teste
});

function esperaRetorno(string $cStat, int $ult, int $max): string
{
    return '<retDistDFeInt versao="1.01" xmlns="http://www.portalfiscal.inf.br/nfe"><tpAmb>2</tpAmb><verAplic>AN-1.0</verAplic>'
        . "<cStat>{$cStat}</cStat><xMotivo>Retorno</xMotivo><dhResp>2026-11-10T12:00:00-03:00</dhResp>"
        . "<ultNSU>{$ult}</ultNSU><maxNSU>{$max}</maxNSU></retDistDFeInt>";
}

/** @param array<int,string> $respostas uma por chamada à SEFAZ */
function esperaServico(array $respostas, int &$chamadas): DistribuicaoDfeService
{
    $cert = Mockery::mock(CertificadoService::class);
    $cert->shouldReceive('carregarParaSefaz')->andReturn(['pfx_binary' => 'x', 'senha' => 'x', 'valido_ate' => null, 'source' => 'teste']);

    return new DistribuicaoDfeService($cert, function () use ($respostas, &$chamadas): Tools {
        $tools = Mockery::mock(Tools::class);
        $tools->shouldReceive('sefazDistDFe')->andReturnUsing(function () use ($respostas, &$chamadas) {
            return $respostas[min($chamadas++, count($respostas) - 1)];
        });

        return $tools;
    });
}

function esperaEstado(int $biz): NfeDfeNsuState
{
    return NfeDfeNsuState::withoutGlobalScopes()->where('business_id', $biz)->firstOrFail(); // SUPERADMIN: lê o cursor do tenant fictício do teste
}

it('UC-FDFE · depois de "nenhum documento" (137) a próxima consulta só sai 1 h depois', function () {
    $chamadas = 0;
    esperaServico([esperaRetorno('137', 100, 100)], $chamadas)->puxarLote($this->biz);

    expect(esperaEstado($this->biz)->proxima_consulta_em?->isAfter(now()->addMinutes(55)))->toBeTrue();

    $this->travelTo(now()->addMinutes(10));
    $volta = esperaServico([esperaRetorno('137', 100, 100)], $chamadas)->puxarLote($this->biz);

    expect($volta['skipped_throttle'] ?? false)->toBeTrue();
    expect($chamadas)->toBe(1);

    $this->travelTo(now()->addMinutes(55));
    esperaServico([esperaRetorno('137', 100, 100)], $chamadas)->puxarLote($this->biz);
    expect($chamadas)->toBe(2);
});

it('UC-FDFE · consumo indevido (656) também espera 1 h e não mexe no cursor', function () {
    NfeDfeNsuState::create(['business_id' => $this->biz, 'last_nsu' => 50]);
    $chamadas = 0;

    $volta = esperaServico([esperaRetorno('656', 0, 0)], $chamadas)->puxarLote($this->biz);

    expect($volta['last_nsu'])->toBe(50);
    expect(esperaEstado($this->biz)->last_nsu)->toBe(50);
    expect(esperaEstado($this->biz)->proxima_consulta_em?->isAfter(now()->addMinutes(55)))->toBeTrue();
});

it('UC-FDFE · lote que alcançou o maxNSU espera 1 h; lote parcial segue no intervalo de 5 min', function () {
    $chamadas = 0;
    esperaServico([esperaRetorno('138', 200, 200)], $chamadas)->puxarLote($this->biz);
    expect(esperaEstado($this->biz)->proxima_consulta_em)->not->toBeNull();

    NfeDfeNsuState::withoutGlobalScopes()->where('business_id', $this->biz)->delete(); // SUPERADMIN: recomeça o cursor do tenant fictício
    $chamadas = 0;
    esperaServico([esperaRetorno('138', 200, 900), esperaRetorno('138', 200, 900)], $chamadas)->puxarLote($this->biz, 1);
    expect(esperaEstado($this->biz)->proxima_consulta_em)->toBeNull();
});

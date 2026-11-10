<?php

declare(strict_types=1);

// @covers-us US-NFE-073 — envio mensal do XML ao contador (R-NFE-038).
// @covers-us US-NFE-051 — distribuição DF-e (cursor NSU por empresa).

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\NfeBrasil\Mail\PacoteXmlContadorMail;
use Modules\NfeBrasil\Models\NfeContador;
use Modules\NfeBrasil\Models\NfeDfeNsuState;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\Manifestacao\DistribuicaoDfeService;
use Modules\NfeBrasil\Services\PacoteXmlContadorService;
use Modules\NfeBrasil\Services\Tributacao\ContadorService;
use NFePHP\NFe\Tools;

uses(Tests\TestCase::class);

/**
 * Isolamento entre empresas (ADR 0093) das três tabelas do NfeBrasil que não tinham teste
 * cross-tenant: `nfe_contadores`, `nfe_envios_contador` e `nfe_dfe_nsu_state`
 * (auditoria NfeBrasil 2026-11-05, pendência N1-05).
 *
 * Tenant 98 (dono) × 99 (vizinho), ADR 0358. Cada caso grava dado nas DUAS empresas e mede
 * as duas: o dado do vizinho existe (senão o isolamento passaria por ausência) e não é lido
 * nem alterado pela ação da empresa dona. Números 9_741_xxx em `nfe_emissoes` são deste arquivo.
 */

beforeEach(function () {
    if (DB::getDriverName() !== 'mysql'
        || ! Schema::hasTable('nfe_emissoes')
        || ! Schema::hasTable('nfe_contadores')
        || ! Schema::hasTable('nfe_envios_contador')
        || ! Schema::hasTable('nfe_dfe_nsu_state')) {
        $this->markTestSkipped('precisa do schema MySQL de nfe_contadores, nfe_envios_contador e nfe_dfe_nsu_state');
    }
    Storage::fake();
    Storage::fake('nfe_fiscal');
    Mail::fake();
    $this->a = $this->seededTenant()->id;
    $this->b = $this->seededSupportClientTenant()->id;
    isoCtdLimpar([$this->a, $this->b]);
});

afterEach(function () {
    if (isset($this->a) && Schema::hasTable('nfe_dfe_nsu_state')) {
        isoCtdLimpar([$this->a, $this->b]);
    }
    \Mockery::close();
});

function isoCtdLimpar(array $bizs): void
{
    DB::table('nfe_emissoes')->whereBetween('numero', [9_741_000, 9_741_999])->delete();
    DB::table('nfe_envios_contador')->whereIn('business_id', $bizs)->delete();
    DB::table('nfe_contadores')->whereIn('business_id', $bizs)->delete();
    DB::table('nfe_dfe_nsu_state')->whereIn('business_id', $bizs)->delete();
}

function isoCtdContador(int $biz, string $email): void
{
    DB::table('nfe_contadores')->insert([
        'business_id' => $biz, 'nome' => "Contador Fixture {$biz}", 'email' => $email,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function isoCtdDistService(int $ultNsu): DistribuicaoDfeService
{
    $xml = <<<XML
    <retDistDFeInt versao="1.01" xmlns="http://www.portalfiscal.inf.br/nfe">
      <tpAmb>2</tpAmb><verAplic>AN-1.0</verAplic>
      <cStat>137</cStat>
      <xMotivo>Nenhum documento localizado</xMotivo>
      <ultNSU>{$ultNsu}</ultNSU>
      <maxNSU>{$ultNsu}</maxNSU>
    </retDistDFeInt>
    XML;

    $cert = \Mockery::mock(CertificadoService::class);
    $cert->shouldReceive('carregarParaSefaz')->andReturn([
        'pfx_binary' => 'fake-binary', 'senha' => 'fake-pass', 'valido_ate' => null, 'source' => 'test',
    ]);

    return new DistribuicaoDfeService($cert, function () use ($xml): Tools {
        $tools = \Mockery::mock(Tools::class);
        $tools->shouldReceive('model')->andReturnNull();
        $tools->shouldReceive('setEnvironment')->andReturnNull();
        $tools->shouldReceive('sefazDistDFe')->andReturn($xml);

        return $tools;
    });
}

it('nfe_contadores · usuário da empresa vê e grava só o contador dela', function () {
    isoCtdContador($this->a, 'contador-a@example.test');
    isoCtdContador($this->b, 'contador-b@example.test');

    $user = $this->usuarioComPermissoes([], $this->seededTenant());
    $this->actingAs($user);
    session(['user.business_id' => $this->a, 'business.id' => $this->a]);

    // O vizinho existe na tabela: o filtro abaixo é do scope, não da ausência de dado.
    expect(DB::table('nfe_contadores')->where('business_id', $this->b)->count())->toBe(1);
    expect(NfeContador::query()->pluck('business_id')->map(fn ($id) => (int) $id)->all())->toBe([$this->a]);
    expect(app(ContadorService::class)->doBusiness($this->a)?->email)->toBe('contador-a@example.test');

    app(ContadorService::class)->salvar($this->a, ['nome' => 'Novo', 'email' => 'novo-a@example.test'], $user->id);

    expect(DB::table('nfe_contadores')->where('business_id', $this->a)->value('email'))->toBe('novo-a@example.test');
    expect(DB::table('nfe_contadores')->where('business_id', $this->b)->value('email'))->toBe('contador-b@example.test');
});

it('nfe_envios_contador · envio registrado no vizinho não marca a empresa como já enviada', function () {
    isoCtdContador($this->a, 'contador-a@example.test');
    isoCtdContador($this->b, 'contador-b@example.test');
    $path = "nfe-brasil/{$this->a}/notas/974-9741001.xml";
    Storage::put($path, '<nfeProc><numero>9741001</numero></nfeProc>');
    DB::table('nfe_emissoes')->insert([
        'business_id' => $this->a, 'modelo' => '55', 'serie' => '974', 'numero' => 9_741_001,
        'status' => 'autorizada', 'valor_total' => 10.00, 'xml_path' => $path,
        'emitido_em' => '2026-09-03 10:00:00', 'created_at' => '2026-09-03 10:00:00', 'updated_at' => '2026-09-03 10:00:00',
    ]);
    DB::table('nfe_envios_contador')->insert([
        'business_id' => $this->b, 'competencia' => '2026-09', 'email' => 'contador-b@example.test',
        'qtd_xml' => 7, 'status' => 'enviado', 'enviado_em' => '2026-10-05 07:00:00',
        'created_at' => '2026-10-05 07:00:00', 'updated_at' => '2026-10-05 07:00:00',
    ]);

    expect(app(PacoteXmlContadorService::class)->enviar($this->a, '2026-09'))->toBe('enviado');

    Mail::assertSent(PacoteXmlContadorMail::class, fn ($m) => $m->hasTo('contador-a@example.test'));
    Mail::assertNotSent(PacoteXmlContadorMail::class, fn ($m) => $m->hasTo('contador-b@example.test'));
    $doVizinho = DB::table('nfe_envios_contador')->where('business_id', $this->b)->where('competencia', '2026-09')->first();
    expect((int) $doVizinho->qtd_xml)->toBe(7);
    expect($doVizinho->email)->toBe('contador-b@example.test');
    expect((int) DB::table('nfe_envios_contador')->where('business_id', $this->a)->value('qtd_xml'))->toBe(1);
});

it('nfe_dfe_nsu_state · o cursor e o intervalo de consulta são da empresa, não do vizinho', function () {
    $checkVizinho = now()->subMinute()->startOfSecond();
    NfeDfeNsuState::query()->create([
        'business_id' => $this->b, 'last_nsu' => 500, 'ultimo_check_em' => $checkVizinho,
        'total_xmls_processados' => 40,
    ]);

    // O vizinho consultou há 1 minuto (dentro do intervalo de 5): a empresa dona não herda a espera,
    // e o cursor dela parte do zero dela, não do 500 do vizinho.
    $r = isoCtdDistService(100)->puxarLote($this->a, loopLimit: 1);

    expect($r['skipped_throttle'] ?? false)->toBeFalse();
    expect($r['last_nsu'])->toBe(100);
    expect((int) NfeDfeNsuState::query()->where('business_id', $this->a)->value('last_nsu'))->toBe(100);

    $vizinho = NfeDfeNsuState::query()->where('business_id', $this->b)->first();
    expect((int) $vizinho->last_nsu)->toBe(500);
    expect((int) $vizinho->total_xmls_processados)->toBe(40);
    expect($vizinho->ultimo_check_em->equalTo($checkVizinho))->toBeTrue();

    // E o vizinho segue com a espera dele, mesmo depois da consulta da empresa dona.
    expect(isoCtdDistService(999)->puxarLote($this->b)['skipped_throttle'] ?? false)->toBeTrue();
});

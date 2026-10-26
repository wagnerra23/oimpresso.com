<?php

declare(strict_types=1);

// @covers-us US-NFE-051 — a busca de DF-e usa o CNPJ certo e a falha vira alarme visível (R-NFE-041 · R-NFE-042).

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Fiscal\Http\Controllers\CockpitController;
use Modules\NfeBrasil\Jobs\BuscarDfesRecebidosJob;
use Modules\NfeBrasil\Support\CnpjDoEmitente;

uses(Tests\TestCase::class);

/**
 * Casos derivados da medição de produção do #9833 (job de DF-e falhando todo dia desde 2026-07-03,
 * a última falha por CNPJ vazio do biz=1, que só tinha `business.cnpj`). Tenant 98 e vizinho 99
 * (ADR 0358); o que o teste muda no `business` e no certificado é restaurado no finally.
 */

beforeEach(function () {
    if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('nfe_certificados') || ! Schema::hasTable('mcp_alertas_eventos')) {
        $this->markTestSkipped('precisa do schema MySQL de business, nfe_certificados e mcp_alertas_eventos');
    }
    $this->a = $this->seededTenant()->id;
    $this->b = $this->seededSupportClientTenant()->id;
    $this->antes = DB::table('business')->whereIn('id', [$this->a, $this->b])->get(['id', 'cnpj', 'tax_number_1'])->keyBy('id');
    DB::table('mcp_alertas_eventos')->whereIn('business_id', [$this->a, $this->b])->where('tipo', BuscarDfesRecebidosJob::TIPO_ALERTA)->delete();
});

afterEach(function () {
    if (! isset($this->antes)) {
        return;
    }
    foreach ($this->antes as $id => $b) {
        DB::table('business')->where('id', $id)->update(['cnpj' => $b->cnpj, 'tax_number_1' => $b->tax_number_1]);
    }
    DB::table('mcp_alertas_eventos')->whereIn('business_id', [$this->a, $this->b])->where('tipo', BuscarDfesRecebidosJob::TIPO_ALERTA)->delete();
});

function cnpj41(int $biz, ?string $cnpj, ?string $tax1): void
{
    DB::table('business')->where('id', $biz)->update(['cnpj' => $cnpj ?? '00.000.000/0000-00', 'tax_number_1' => $tax1]);
}

it('UC-FNFE-44 · sem certificado, o CNPJ vem do business.cnpj quando o tax_number_1 está vazio (o caso do biz=1)', function () {
    if (DB::table('nfe_certificados')->where('business_id', $this->a)->where('ativo', true)->whereNotNull('cnpj_titular')->exists()) {
        $this->markTestSkipped('o tenant 98 tem certificado ativo com titular neste banco');
    }
    cnpj41($this->a, '11.222.333/0001-81', null); # pii-allowlist: CNPJ fictício de fixture

    expect(CnpjDoEmitente::resolver($this->a))->toBe('11222333000181');
});

it('UC-FNFE-44 · o valor padrão 00.000.000/0000-00 não conta; cai no tax_number_1', function () {
    if (DB::table('nfe_certificados')->where('business_id', $this->a)->where('ativo', true)->whereNotNull('cnpj_titular')->exists()) {
        $this->markTestSkipped('o tenant 98 tem certificado ativo com titular neste banco');
    }
    cnpj41($this->a, null, '11.222.333/0001-81'); # pii-allowlist: CNPJ fictício de fixture

    expect(CnpjDoEmitente::resolver($this->a))->toBe('11222333000181');
});

it('UC-FNFE-45 · sem CNPJ em lugar nenhum a busca é recusada com a saída, nunca vai vazia', function () {
    if (DB::table('nfe_certificados')->where('business_id', $this->a)->where('ativo', true)->whereNotNull('cnpj_titular')->exists()) {
        $this->markTestSkipped('o tenant 98 tem certificado ativo com titular neste banco');
    }
    cnpj41($this->a, null, null);

    expect(CnpjDoEmitente::resolver($this->a))->toBeNull();
    expect(fn () => CnpjDoEmitente::obrigatorio($this->a))
        ->toThrow(RuntimeException::class, 'não configurado — preencha em Fiscal › Configuração');
});

it('UC-FNFE-46 · a falha definitiva do job grava um alerta por empresa e por dia', function () {
    $job = new BuscarDfesRecebidosJob($this->a);
    $job->failed(new RuntimeException('CNPJ da empresa não configurado'));
    $job->failed(new RuntimeException('Certificado vencido'));

    $alertas = DB::table('mcp_alertas_eventos')->where('business_id', $this->a)->where('tipo', BuscarDfesRecebidosJob::TIPO_ALERTA)->get();
    expect($alertas)->toHaveCount(1);
    expect($alertas[0]->status)->toBe('aberto');
    expect($alertas[0]->severidade)->toBe('high');
    expect($alertas[0]->descricao)->toBe('Certificado vencido'); // o último erro do dia
    expect(DB::table('mcp_alertas_eventos')->where('business_id', $this->b)->where('tipo', BuscarDfesRecebidosJob::TIPO_ALERTA)->count())->toBe(0);
});

it('UC-FNFE-46 · o cockpit Fiscal mostra a falha só para a própria empresa, e some quando arquivada', function () {
    (new BuscarDfesRecebidosJob($this->a))->failed(new RuntimeException('Certificado vencido'));

    $m = new ReflectionMethod(CockpitController::class, 'ultimaFalhaBuscaDfe');
    $m->setAccessible(true);
    $c = new CockpitController();

    expect($m->invoke($c, $this->a)?->descricao)->toBe('Certificado vencido');
    expect($m->invoke($c, $this->b))->toBeNull();

    DB::table('mcp_alertas_eventos')->where('business_id', $this->a)->where('tipo', BuscarDfesRecebidosJob::TIPO_ALERTA)->update(['status' => 'arquivado']);
    expect($m->invoke($c, $this->a))->toBeNull();
});

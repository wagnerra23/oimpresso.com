<?php

declare(strict_types=1);

// @covers-us US-NFE-051 — a busca de DF-e usa o CNPJ certo e a falha vira alarme visível (R-NFE-041 · R-NFE-042).

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Fiscal\Http\Controllers\CockpitController;
use Modules\NfeBrasil\Jobs\BuscarDfesRecebidosJob;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\Manifestacao\DistribuicaoDfeService;
use Modules\NfeBrasil\Services\Manifestacao\ManifestacaoService;
use Modules\NfeBrasil\Support\CnpjDoEmitente;
use NFePHP\NFe\Tools;

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
    DB::table('business')->where('id', $biz)->update(['cnpj' => $cnpj ?? '00.000.000/0000-00', 'tax_number_1' => $tax1]); # pii-allowlist: valor padrão da coluna business.cnpj
}

it('UC-FDFE-11 · sem certificado, o CNPJ vem do business.cnpj quando o tax_number_1 está vazio (o caso do biz=1)', function () {
    if (DB::table('nfe_certificados')->where('business_id', $this->a)->where('ativo', true)->whereNotNull('cnpj_titular')->exists()) {
        $this->markTestSkipped('o tenant 98 tem certificado ativo com titular neste banco');
    }
    cnpj41($this->a, '11.222.333/0001-81', null); # pii-allowlist: CNPJ fictício de fixture

    expect(CnpjDoEmitente::resolver($this->a))->toBe('11222333000181');
});

it('UC-FDFE-11 · o valor padrão da coluna (só zeros) não conta; cai no tax_number_1', function () {
    if (DB::table('nfe_certificados')->where('business_id', $this->a)->where('ativo', true)->whereNotNull('cnpj_titular')->exists()) {
        $this->markTestSkipped('o tenant 98 tem certificado ativo com titular neste banco');
    }
    cnpj41($this->a, null, '11.222.333/0001-81'); # pii-allowlist: CNPJ fictício de fixture

    expect(CnpjDoEmitente::resolver($this->a))->toBe('11222333000181');
});

it('UC-FDFE-12 · sem CNPJ em lugar nenhum a busca é recusada com a saída, nunca vai vazia', function () {
    if (DB::table('nfe_certificados')->where('business_id', $this->a)->where('ativo', true)->whereNotNull('cnpj_titular')->exists()) {
        $this->markTestSkipped('o tenant 98 tem certificado ativo com titular neste banco');
    }
    cnpj41($this->a, null, null);

    expect(CnpjDoEmitente::resolver($this->a))->toBeNull();
    expect(fn () => CnpjDoEmitente::obrigatorio($this->a))
        ->toThrow(RuntimeException::class, 'não configurado — preencha em Fiscal › Configuração');
});

it('UC-FDFE-13 · a falha definitiva do job grava um alerta por empresa e por dia', function () {
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

it('UC-FCKP-14 · o cockpit Fiscal mostra a falha só para a própria empresa, e some quando arquivada', function () {
    (new BuscarDfesRecebidosJob($this->a))->failed(new RuntimeException('Certificado vencido'));

    $m = new ReflectionMethod(CockpitController::class, 'ultimaFalhaBuscaDfe');
    $m->setAccessible(true);
    $c = new CockpitController();

    expect($m->invoke($c, $this->a)?->descricao)->toBe('Certificado vencido');
    expect($m->invoke($c, $this->b))->toBeNull();

    DB::table('mcp_alertas_eventos')->where('business_id', $this->a)->where('tipo', BuscarDfesRecebidosJob::TIPO_ALERTA)->update(['status' => 'arquivado']);
    expect($m->invoke($c, $this->a))->toBeNull();
});

/** Certificado A1 autoassinado, só para o NFePHP conseguir ler (nada vai à SEFAZ). */
function dfeAlarmePfx(string $senha): string
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $csr = openssl_csr_new(['commonName' => 'TESTE DFE FICTICIO'], $key, ['digest_alg' => 'sha256']);
    $x509 = openssl_csr_sign($csr, null, $key, 365, ['digest_alg' => 'sha256']);
    openssl_pkcs12_export($x509, $pfx, $key, $senha);

    return $pfx;
}

it('UC-FDFE-18 · a busca e a manifestação montam o Tools do NFePHP com o modelo 55, sem TypeError', function (string $classe) {
    // Em produção (2026-11-24) toda busca morria em "Tools::model(): Argument #1 ($model) must be of
    // type ?int, string given": os dois services são strict_types e passavam '55'. Os outros testes
    // injetam um toolsFactory e nunca chegavam ao NFePHP de verdade; aqui o Tools é o real.
    cnpj41($this->a, '11.222.333/0001-81', null); # pii-allowlist: CNPJ fictício de fixture
    $cert = Mockery::mock(CertificadoService::class);
    $cert->shouldReceive('carregarParaSefaz')->andReturn(['pfx_binary' => dfeAlarmePfx('1234'), 'senha' => '1234']);

    $m = new ReflectionMethod($classe, 'buildTools');
    $m->setAccessible(true);
    $tools = $m->invoke(new $classe($cert), $this->a);

    expect($tools)->toBeInstanceOf(Tools::class);
    expect($tools->model())->toBe(55);
})->with([DistribuicaoDfeService::class, ManifestacaoService::class]);

it('UC-FCKP-25 · o alerta da falha não mostra o caminho do servidor, nem os gravados antes da correção', function () {
    $erro = 'NFePHP\NFe\Common\Tools::model(): Argument #1 ($model) must be of type ?int, string given, '
        . 'called in /home/conta/domains/exemplo/Modules/NfeBrasil/Services/Manifestacao/DistribuicaoDfeService.php on line 363';

    (new BuscarDfesRecebidosJob($this->a))->failed(new TypeError($erro));
    $gravado = DB::table('mcp_alertas_eventos')->where('business_id', $this->a)->where('tipo', BuscarDfesRecebidosJob::TIPO_ALERTA)->value('descricao');
    expect($gravado)->toBe('NFePHP\NFe\Common\Tools::model(): Argument #1 ($model) must be of type ?int, string given');

    // Alerta gravado antes da correção, com o caminho inteiro: o cockpit limpa na hora de mostrar.
    DB::table('mcp_alertas_eventos')->where('business_id', $this->a)->where('tipo', BuscarDfesRecebidosJob::TIPO_ALERTA)->update(['descricao' => $erro]);
    session(['user.business_id' => $this->a]);
    $alertas = new ReflectionMethod(CockpitController::class, 'computeAlerts');
    $alertas->setAccessible(true);
    $sub = collect($alertas->invoke(new CockpitController(), ['cert' => null, 'dfeCount' => 0]))->firstWhere('title', 'A busca das notas recebidas na SEFAZ falhou')['sub'] ?? null;

    expect($sub)->not->toBeNull();
    expect($sub)->not->toContain('/home/');
    expect($sub)->not->toContain('called in');
});

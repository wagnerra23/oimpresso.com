<?php

declare(strict_types=1);

// @covers-us US-NFE-TPL-001 — "Configurar pelo certificado" (leitura). Playbook Fiscal thread 21.
// Contrato: UC-NFTR-14 · UC-NFTR-16 · R-NFE-028 (21-certificado-template-backend.md) +
// ADR 0186 (autoridade por campo, invariantes 1/5/7) + Index.charter.md ("❌ Auto-aplicar
// template sem clique"). Derivado do contrato, não da implementação (proibicoes §5 2026-06-05).

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\EmpresaFiscalLookupService;
use Modules\NfeBrasil\Services\SefazConsultaCadastroService;

uses(Tests\TestCase::class);

const EFL_CNPJ_MEU = '11222333000181';
const EFL_CNPJ_OUTRO = '44555666000172';

/**
 * SEFAZ falsa: o contrato do `consultar` (ADR 0186 inv. 6) é o que se exerce; a chain de
 * certificado em si é provada por SefazConsultaCadastroChainTest/SefazInvariantes (que
 * seguem verdes — a thread não toca o serviço). Aqui provamos o CONSUMIDOR: que business
 * ele passa e de quem ele aceita IE/situação.
 */
function eflSefazFalsa(?array $retorno, ?string $motivo = null): object
{
    $fake = new class(app(CertificadoService::class)) extends SefazConsultaCadastroService
    {
        public ?array $retorno = null;

        public ?string $motivo = null;

        /** @var array<int, array{cnpj: string, uf: string, business_id: int}> */
        public array $chamadas = [];

        public function consultar(string $cnpj, string $uf, int $businessId, ?string &$reason = null): ?array
        {
            $this->chamadas[] = ['cnpj' => $cnpj, 'uf' => $uf, 'business_id' => $businessId];
            $reason = $this->retorno === null ? $this->motivo : null;

            return $this->retorno;
        }
    };
    $fake->retorno = $retorno;
    $fake->motivo = $motivo;
    app()->instance(SefazConsultaCadastroService::class, $fake);

    return $fake;
}

function eflBrasilApi(array $corpo): void
{
    Http::fake(['brasilapi.com.br/*' => Http::response($corpo + [
        'uf' => 'SP', 'razao_social' => 'GRAFICA RECEITA LTDA', 'cnae_fiscal' => 1813001,
        'cnaes_secundarios' => [['codigo' => 4761003, 'descricao' => 'papelaria']],
    ], 200)]);
}

function eflSefazOk(string $regimeApuracao = 'SIMPLES NACIONAL'): array
{
    return [
        'ie' => '123456789111', 'situacao' => '0', 'situacao_label' => 'habilitado',
        'nome' => 'GRAFICA SEFAZ LTDA', 'uf' => 'SP', 'fonte' => 'sefaz_sp',
        'cert_source' => 'nfe_brasil', 'regime_apuracao' => $regimeApuracao,
    ];
}

function eflCert(int $businessId, string $cnpj): string
{
    $uuid = (string) Str::uuid();
    DB::table('nfe_certificados')->insert([
        'business_id' => $businessId, 'uuid' => $uuid, 'cnpj_titular' => $cnpj,
        'valido_ate' => now()->addYear()->toDateString(), 'ativo' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $uuid;
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('MySQL-only: isolamento multi-tenant exige schema real (ADR 0358; ver nfebrasil-pest.yml).');
    }
    if (! Schema::hasTable('nfe_certificados') || ! Schema::hasTable('nfe_business_configs')) {
        $this->markTestSkipped('Tabelas do NfeBrasil ausentes — rode as migrations do módulo.');
    }

    config(['cache.default' => 'array']);  // a BrasilAPI é cacheada 30d — cada caso começa limpo

    // biz=99: empresa FICTÍCIA criada pelo helper (nunca a real). O vizinho é o tenant canônico.
    $this->biz = $this->seededSupportClientTenant()->id;
    $this->outro = $this->seededTenant()->id;
    $this->uuids = [eflCert($this->biz, EFL_CNPJ_MEU), eflCert($this->outro, EFL_CNPJ_OUTRO)];
});

afterEach(function () {
    if (! empty($this->uuids)) {
        DB::table('nfe_certificados')->whereIn('uuid', $this->uuids)->delete();
    }
});

it('UC-NFTR-14 · leitura pelo certificado não grava', function () {
    eflBrasilApi(['opcao_pelo_simples' => true]);
    eflSefazFalsa(eflSefazOk());
    $user = $this->usuarioComPermissoes(['nfe.tributacao.manage'], \App\Business::find($this->biz));

    $foto = fn () => [
        DB::table('nfe_business_configs')->where('business_id', $this->biz)->get()->toArray(),
        DB::table('nfe_fiscal_rules')->where('business_id', $this->biz)->get()->toArray(),
        (array) DB::table('business')->where('id', $this->biz)->first(),
    ];
    $antes = $foto();

    // `?business_id=` do vizinho é IGNORADO: o tenant vem só da sessão (ADR 0093).
    $resp = $this->actingAs($user)
        ->getJson("/nfe-brasil/tributacao/empresa-fiscal?business_id={$this->outro}")
        ->assertOk();

    expect($resp->json('campos.cnpj.valor'))->toBe(EFL_CNPJ_MEU)
        ->and($resp->json('campos.cnpj.fonte'))->toBe('certificado')
        ->and($resp->json('campos.regime.valor'))->toBe('simples');

    expect($foto())->toEqual($antes);  // o caso: NENHUMA linha mudou

    // Controle positivo cross-tenant: a leitura do vizinho devolve o CNPJ dele, não o meu.
    $doVizinho = app(EmpresaFiscalLookupService::class)->ler($this->outro);
    expect($doVizinho['campos']['cnpj']['valor'])->toBe(EFL_CNPJ_OUTRO);
});

it('UC-NFTR-16 · divergência não é resolvida sozinha', function () {
    eflBrasilApi(['opcao_pelo_simples' => true, 'opcao_pelo_mei' => false]);
    eflSefazFalsa(eflSefazOk('NORMAL - REGIME PERIODICO DE APURACAO'));

    $regime = app(EmpresaFiscalLookupService::class)->ler($this->biz)['campos']['regime'];

    expect($regime['divergente'])->toBeTrue()
        ->and($regime['valor'])->toBeNull()
        ->and(array_column($regime['opcoes'], 'valor'))->toEqualCanonicalizing(['simples', 'normal']);

    // Controle positivo: com as fontes concordando, o regime volta preenchido.
    eflSefazFalsa(eflSefazOk('SIMPLES NACIONAL'));
    $regime = app(EmpresaFiscalLookupService::class)->ler($this->biz)['campos']['regime'];
    expect($regime['divergente'])->toBeFalse()
        ->and($regime['valor'])->toBe('simples');
});

it('R-NFE-028 · chain e autoridade da ADR 0186', function () {
    eflBrasilApi(['opcao_pelo_simples' => true]);
    $sefaz = eflSefazFalsa(eflSefazOk());

    $campos = app(EmpresaFiscalLookupService::class)->ler($this->biz)['campos'];

    // A consulta vai em nome do PRÓPRIO business — a chain (primário → legado → institucional)
    // é decidida dentro do serviço da ADR 0186, a partir deste id; nunca do vizinho.
    expect($sefaz->chamadas)->toHaveCount(1)
        ->and($sefaz->chamadas[0]['business_id'])->toBe($this->biz)
        ->and($sefaz->chamadas[0]['cnpj'])->toBe(EFL_CNPJ_MEU);

    // Autoridade por campo (inv. 5): IE/situação só da SEFAZ; razão social SEFAZ antes da BrasilAPI;
    // CNAE da BrasilAPI.
    expect($campos['ie']['valor'])->toBe('123456789111')
        ->and($campos['ie']['fonte'])->toBe('sefaz_sp')
        ->and($campos['situacao']['valor'])->toBe('habilitado')
        ->and($campos['razao_social']['valor'])->toBe('GRAFICA SEFAZ LTDA')
        ->and($campos['cnaes']['valor'])->toContain('1813-0/01')
        ->and($campos['cnaes']['fonte'])->toBe('brasilapi');

    // UF fora de config/fiscal.php → IE/situação vazios com motivo, pra digitar (inv. 7) —
    // e a BrasilAPI NÃO preenche a IE no lugar da SEFAZ.
    eflSefazFalsa(null, 'uf_unsupported');
    $campos = app(EmpresaFiscalLookupService::class)->ler($this->biz)['campos'];
    expect($campos['ie']['valor'])->toBeNull()
        ->and($campos['ie']['motivo'])->toBe('uf_unsupported')
        ->and($campos['situacao']['motivo'])->toBe('uf_unsupported')
        ->and($campos['razao_social']['valor'])->toBe('GRAFICA RECEITA LTDA');  // fallback BrasilAPI
});

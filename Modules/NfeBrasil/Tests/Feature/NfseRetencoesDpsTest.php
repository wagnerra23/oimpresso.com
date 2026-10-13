<?php

declare(strict_types=1);

// @covers-us R-NFSE-003 — a marcação "serviço sujeito à retenção" leva PIS/COFINS/CSLL à DPS (NT SE/CGNFS-e nº 007) e à nota.

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\NFSe\Adapters\SnNfseAdapter;
use Modules\NFSe\Contracts\NfseProviderInterface;
use Modules\NFSe\DTO\NfseEmissaoPayload;
use Modules\NFSe\DTO\NfseResultado;
use Modules\NFSe\Models\NfseEmissao;
use Modules\NFSe\Services\NfseEmissaoService;

uses(Tests\TestCase::class);

/**
 * UC-NFSEM-04 · R-NFSE-003. Decisão [W] 2026-10-13: quem emite marca o serviço como do
 * art. 30 da Lei 10.833/2003; desmarcado, a nota fica igual a antes.
 * Tenant 98 (Simples) × 99 (lucro presumido) — ADR 0358. Valores em centavos no DTO.
 */
const NFSD_BIZ = 98;
const NFSD_BIZ_OUTRO = 99;
const NFSD_CNPJ_TOMADOR = '11222333000181';

function nfsdDados(array $extra = []): array
{
    return array_merge([
        'competencia'    => '2026-10',
        'tomador_nome'   => 'Tomador Teste',
        'tomador_cnpj'   => NFSD_CNPJ_TOMADOR,
        'descricao'      => 'Manutenção predial — teste R-NFSE-003',
        'lc116_codigo'   => '7.10',
        'valor_servicos' => '1500.00',
        'aliquota_iss'   => '0.05',
    ], $extra);
}

/**
 * Estrutura da DPS do payload. Sem config de provider no teste, o payload vem sem CNPJ do
 * prestador — que a DPS exige (R-NFSE-005); o helper o acrescenta.
 */
function nfsdDps(NfseEmissaoPayload $p): array
{
    return (new SnNfseAdapter())->buildDps(new NfseEmissaoPayload(...array_merge(get_object_vars($p), ['prestadorCnpj' => '11222333000181'])));
}

function nfsdService(): NfseEmissaoService
{
    return new NfseEmissaoService(new SnNfseAdapter());
}

beforeEach(function () {
    foreach (['nfe_business_configs', 'nfse_emissoes', 'nfse_provider_configs', 'nfe_certificados'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("{$t} ausente — rode as migrations do NfeBrasil/NFSe.");
        }
    }
    if (! Schema::hasColumn('nfse_emissoes', 'valor_ret_csll')) {
        $this->markTestSkipped('migração 2026_10_13_000001 (retenções na nfse_emissoes) não rodou.');
    }

    DB::table('nfe_business_configs')->whereIn('business_id', [NFSD_BIZ, NFSD_BIZ_OUTRO])->delete();
    $agora = now();
    DB::table('nfe_business_configs')->insert([
        ['business_id' => NFSD_BIZ, 'regime' => 'simples', 'tributacao_default' => '{}', 'created_at' => $agora, 'updated_at' => $agora],
        ['business_id' => NFSD_BIZ_OUTRO, 'regime' => 'lucro_presumido', 'tributacao_default' => '{}', 'created_at' => $agora, 'updated_at' => $agora],
    ]);
});

afterEach(function () {
    if (Schema::hasTable('nfe_business_configs')) {
        DB::table('nfe_business_configs')->whereIn('business_id', [NFSD_BIZ, NFSD_BIZ_OUTRO])->delete();
    }
});

it('UC-NFSEM-04 marcado, presumido, tomador CNPJ: 1.500 reais retém 975 + 4500 + 1500 centavos', function () {
    $p = nfsdService()->montarPayload(nfsdDados(['retencao_federal_sujeito' => true]), NFSD_BIZ_OUTRO);

    expect($p->retencaoFederalSujeito)->toBeTrue();
    expect($p->retPisCentavos)->toBe(975);
    expect($p->retCofinsCentavos)->toBe(4500);
    expect($p->retCsllCentavos)->toBe(1500);
    expect($p->retencaoFederalCentavos())->toBe(6975);
});

it('UC-NFSEM-04 a DPS leva os três somados em vRetCSLL, tpRetPisCofins 3, e não usa vPis/vCofins (NT 007)', function () {
    $p = nfsdService()->montarPayload(nfsdDados(['retencao_federal_sujeito' => true]), NFSD_BIZ_OUTRO);
    $dps = nfsdDps($p);
    $fed = $dps['infDPS']['valores']['trib']['tribFed'] ?? null;

    expect($fed)->not->toBeNull();
    expect($fed['vRetCSLL'])->toBe('69.75');
    expect($fed['piscofins']['tpRetPisCofins'])->toBe('3');
    expect($fed['piscofins']['CST'])->toBe('01');
    expect($fed['piscofins'])->not->toHaveKey('vPis');
    expect($fed['piscofins'])->not->toHaveKey('vCofins');
    // o ISS segue intacto ao lado
    expect($dps['infDPS']['valores']['trib']['tribMun']['tribISSQN'])->toBe('1');
});

it('UC-NFSEM-04 desmarcado: sem retenção, sem grupo federal, e a chave de idempotência é a de antes', function () {
    $p = nfsdService()->montarPayload(nfsdDados(), NFSD_BIZ_OUTRO);
    $dps = nfsdDps($p);

    expect($p->retencaoFederalSujeito)->toBeFalse();
    expect($p->retencaoFederalCentavos())->toBe(0);
    expect($dps['infDPS']['valores']['trib']['tribFed'])->toBeNull();

    $chaveAntiga = hash('sha256', implode('|', [NFSD_BIZ_OUTRO, NFSD_CNPJ_TOMADOR, 1500.0, $p->descricao, '2026-10']));
    expect($p->idempotencyKey())->toBe($chaveAntiga);

    $marcado = nfsdService()->montarPayload(nfsdDados(['retencao_federal_sujeito' => true]), NFSD_BIZ_OUTRO);
    expect($marcado->idempotencyKey())->not->toBe($chaveAntiga);
});

it('UC-NFSEM-04 Simples (98) não herda o presumido do 99: marcado, nada retido e sem grupo', function () {
    $p = nfsdService()->montarPayload(nfsdDados(['retencao_federal_sujeito' => true]), NFSD_BIZ);
    $dps = nfsdDps($p);

    expect($p->retencaoFederalCentavos())->toBe(0);
    expect($dps['infDPS']['valores']['trib']['tribFed'])->toBeNull();
});

it('UC-NFSEM-04 dispensa do art. 31 § 3º (150 reais) e tomador sem CNPJ não retêm', function () {
    $pequeno = nfsdService()->montarPayload(nfsdDados(['retencao_federal_sujeito' => true, 'valor_servicos' => '150.00']), NFSD_BIZ_OUTRO);
    expect($pequeno->retencaoFederalCentavos())->toBe(0);

    $pf = nfsdService()->montarPayload(nfsdDados([
        'retencao_federal_sujeito' => true, 'tomador_cnpj' => null, 'tomador_cpf' => '52998224725',
    ]), NFSD_BIZ_OUTRO);
    expect($pf->retencaoFederalCentavos())->toBe(0);
    expect(nfsdDps($pf)['infDPS']['valores']['trib']['tribFed'])->toBeNull();
});

describe('a nota grava o que foi retido', function () {
    beforeEach(function () {
        $this->seededSupportClientTenant(); // FKs de nfe_certificados/nfse_provider_configs → business 99
        DB::table('nfse_provider_configs')->where('business_id', NFSD_BIZ_OUTRO)->delete();
    });

    /**
     * Cadastra cert + config DEPOIS de montar o payload: o montarPayload chama
     * pfxDecriptado()/senhaDecriptada(), que não existem em NfeCertificado (achado
     * registrado no _saida da thread — fora do escopo). Sem config, ele monta sem cert.
     */
    function nfsdCadastrarConfig(object $t): void
    {
        $t->certId = DB::table('nfe_certificados')->insertGetId([
            'business_id' => NFSD_BIZ_OUTRO, 'uuid' => (string) Str::uuid(), 'cnpj_titular' => '11222333000181',
            'valido_ate' => now()->addYear()->toDateString(), 'ativo' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $t->configId = DB::table('nfse_provider_configs')->insertGetId([
            'business_id' => NFSD_BIZ_OUTRO, 'municipio_codigo_ibge' => '4218707', 'serie_default' => 'RPS',
            'ambiente' => 'homologacao', 'cert_id' => $t->certId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    afterEach(function () {
        // Roda mesmo quando o beforeEach pulou o teste (lane SQLite sem as tabelas).
        if (! Schema::hasTable('nfse_emissoes')) {
            return;
        }
        DB::table('nfse_emissoes')->where('business_id', NFSD_BIZ_OUTRO)
            ->where('descricao', 'like', '%R-NFSE-003%')->delete();
        DB::table('nfse_provider_configs')->where('id', $this->configId ?? 0)->delete();
        DB::table('nfe_certificados')->where('id', $this->certId ?? 0)->delete();
    });

    it('UC-NFSEM-04 emitir() grava PIS/COFINS/CSLL retidos e a marcação na nfse_emissoes', function () {
        $provider = new class implements NfseProviderInterface {
            public ?NfseEmissaoPayload $recebido = null;

            public function emitir(NfseEmissaoPayload $payload): NfseResultado
            {
                $this->recebido = $payload;

                return NfseResultado::sucesso(numero: '1', protocolo: 'P-TESTE');
            }

            public function consultar(string $protocolo): NfseResultado
            {
                return NfseResultado::sucesso(numero: '1', protocolo: $protocolo);
            }

            public function cancelar(string $numero, string $motivo): bool
            {
                return true;
            }
        };

        $svc = new NfseEmissaoService($provider);
        $payload = $svc->montarPayload(nfsdDados(['retencao_federal_sujeito' => true]), NFSD_BIZ_OUTRO);
        nfsdCadastrarConfig($this);
        $nota = $svc->emitir($payload);

        $linha = NfseEmissao::withoutGlobalScopes()->findOrFail($nota->id);
        expect($linha->business_id)->toBe(NFSD_BIZ_OUTRO);
        expect($linha->retencao_federal_sujeito)->toBeTrue();
        expect((string) $linha->valor_ret_pis)->toBe('9.75');
        expect((string) $linha->valor_ret_cofins)->toBe('45.00');
        expect((string) $linha->valor_ret_csll)->toBe('15.00');
        expect((string) $linha->valor_servicos)->toBe('1500.00');
        expect($provider->recebido?->retencaoFederalCentavos())->toBe(6975);
    });
});

<?php

declare(strict_types=1);

use App\Rules\BR\CpfCnpj;
use App\Support\BR\Cnpj;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\NfeBrasil\Models\NfeBusinessConfig;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\NfeService;

uses(Tests\TestCase::class);

/**
 * R-NFE-033 · Aceita CNPJ alfanumérico válido e recusa o inválido (playbook Fiscal, thread 27).
 *
 * Contrato: IN RFB nº 2.229/2024 — "O CNPJ adotará o formato alfanumérico composto por
 * quatorze posições, conforme disposto no Anexo XV, com previsão de implementação a
 * partir de julho de 2026." Regra do DV em `App\Support\BR\Cnpj`.
 *
 * Todos os CNPJs abaixo são FICTÍCIOS: `12ABC34501DE35` é o exemplo publicado pela
 * Receita; `11222333000181` é o exemplo numérico de documentação já usado nesta suíte.
 *
 * Tenant fictício 98 (ADR 0358). Os casos de XML leem `nfe_business_configs` e são
 * MySQL-only, como o `SerializacaoIbsCbsTest` de onde o helper foi copiado.
 */

const CNPJ_ALFA_BIZ = 98;
const CNPJ_ALFA_VALIDO = '12.ABC.345/01DE-35';
const CNPJ_ALFA_DV_ERRADO = '12.ABC.345/01DE-36';
const CNPJ_NUM_VALIDO = '11.222.333/0001-81'; // pii-allowlist (CNPJ fictício de documentação)

function cnpjAlfaValida(string $valor): \Illuminate\Validation\Validator
{
    return Validator::make(['cpf_cnpj' => $valor], ['cpf_cnpj' => ['nullable', new CpfCnpj]]);
}

/** XML montado por NfeService::buildXml (private), sem SEFAZ nem certificado. */
function cnpjAlfaXml(string $docDestinatario): string
{
    $svc = new NfeService(\Mockery::mock(CertificadoService::class));

    $business = (object) [
        'id' => CNPJ_ALFA_BIZ, 'cidade_id' => 0, 'regime' => 3, 'ambiente' => 2,
        'numero_serie_nfe' => '1', 'ncm_padrao' => '61091000',
    ];
    $emitOverride = [
        'uf' => 'SP', 'cod_municipio' => '3550308', 'municipio' => 'SAO PAULO',
        'cnpj' => '11222333000181', 'razao_social' => 'EMPRESA TESTE CNPJ LTDA',
        'nome_fantasia' => 'TESTE CNPJ', 'ie' => '110042490114', 'crt' => 3,
        'logradouro' => 'RUA DE TESTE', 'numero_end' => '100', 'bairro' => 'CENTRO',
        'cep' => '01001000', 'ambiente' => 2,
    ];

    $emissao = new NfeEmissao();
    $emissao->business_id    = CNPJ_ALFA_BIZ;
    $emissao->transaction_id = null;
    $emissao->modelo         = '55';
    $emissao->serie          = '1';
    $emissao->numero         = 1;

    $v = 100.00;
    $dadosNfe = [
        'transaction_id' => null,
        'nat_op'         => 'VENDA DE MERCADORIA',
        'dest' => [
            'nome' => 'CLIENTE TESTE LTDA', 'cnpj' => $docDestinatario, 'ind_ie_dest' => '9',
            'logradouro' => 'AV DO CLIENTE', 'numero' => '200', 'bairro' => 'CENTRO',
            'municipio' => 'SAO PAULO', 'cod_municipio' => '3550308', 'uf' => 'SP', 'cep' => '01310100',
        ],
        'dets' => [[
            'cprod' => 'TESTE-1', 'xprod' => 'PRODUTO TESTE', 'ncm' => '61091000', 'cfop' => '5102',
            'ucm' => 'UN', 'qcom' => 1.0, 'vuncom' => $v, 'vprod' => $v, 'utrib' => 'UN',
            'qtrib' => 1.0, 'vuntrib' => $v, 'ind_tot' => 1,
            // mesmo item do SerializacaoIbsCbsTest (CRT 3 · CST 00) — caminho já provado no buildXml
            'icms'   => ['cst_csosn' => '00', 'orig' => 0, 'modbc' => 3, 'vbc' => $v, 'picms' => 18.00, 'vicms' => 18.00],
            'pis'    => ['cst' => '01', 'vbc' => $v, 'ppis' => 1.65, 'vpis' => 1.65],
            'cofins' => ['cst' => '01', 'vbc' => $v, 'pcofins' => 7.60, 'vcofins' => 7.60],
        ]],
        'total' => [
            'v_prod' => $v, 'v_bc_icms' => $v, 'v_icms' => 18.00, 'v_pis' => 1.65, 'v_cofins' => 7.60,
            'v_nf' => $v, 'v_desc' => 0, 'v_frete' => 0,
        ],
        'pag'         => [['tpag' => '01', 'vpag' => $v]],
        'valor_total' => $v,
    ];

    $m = new ReflectionMethod(NfeService::class, 'buildXml');
    $m->setAccessible(true);

    return (string) $m->invoke($svc, $business, $emissao, $dadosNfe, $emitOverride);
}

/** Conteúdo do bloco <dest>…</dest> (o emitente também tem <CNPJ>). */
function cnpjAlfaDest(string $xml): string
{
    return preg_match('#<dest>(.*?)</dest>#s', $xml, $mm) ? $mm[1] : '';
}

afterEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        NfeBusinessConfig::withoutGlobalScopes()->where('business_id', CNPJ_ALFA_BIZ)->delete();
    }
    \Mockery::close();
});

// ── R-NFE-033 · dígito verificador ──────────────────────────────────────────

it('R-NFE-033 · DV do CNPJ alfanumérico confere com o exemplo da Receita', function () {
    expect(Cnpj::valido(CNPJ_ALFA_VALIDO))->toBeTrue()
        ->and(Cnpj::valido('12abc34501de35'))->toBeTrue()      // minúscula é normalizada
        ->and(Cnpj::valido(CNPJ_ALFA_DV_ERRADO))->toBeFalse()
        ->and(Cnpj::valido('12ABC34501D!35'))->toBeFalse()     // caractere fora de [0-9A-Z]
        ->and(Cnpj::valido('12ABC34501DEAB'))->toBeFalse()     // DV com letra
        ->and(Cnpj::valido('AAAAAAAAAAAA00'))->toBeFalse();
})->group('nfe', 'R-NFE-033');

it('R-NFE-033 · controle positivo: CNPJ só numérico continua com o mesmo DV de antes', function () {
    expect(Cnpj::valido(CNPJ_NUM_VALIDO))->toBeTrue()
        ->and(Cnpj::valido('11222333000182'))->toBeFalse();
})->group('nfe', 'R-NFE-033');

// ── R-NFE-033 · cadastro (a rule que StoreContactRequest/UpdateContactRequest usam) ──

it('R-NFE-033 · cadastro aceita CNPJ alfanumérico válido', function () {
    expect(cnpjAlfaValida(CNPJ_ALFA_VALIDO)->fails())->toBeFalse();
})->group('nfe', 'R-NFE-033');

it('R-NFE-033 · cadastro recusa DV errado com mensagem clara (o FormRequest devolve 422)', function () {
    $v = cnpjAlfaValida(CNPJ_ALFA_DV_ERRADO);

    expect($v->fails())->toBeTrue();
    $this->assertStringContainsString(
        'CNPJ alfanumérico válido',
        (string) $v->errors()->first('cpf_cnpj'),
        'a mensagem deve dizer que é o CNPJ alfanumérico que não confere'
    );
})->group('nfe', 'R-NFE-033');

it('R-NFE-033 · controle positivo: cadastro segue aceitando CNPJ numérico e recusando o inválido', function () {
    expect(cnpjAlfaValida(CNPJ_NUM_VALIDO)->fails())->toBeFalse()
        ->and(cnpjAlfaValida('11.222.333/0001-82')->fails())->toBeTrue(); // pii-allowlist (fictício, DV errado)
})->group('nfe', 'R-NFE-033');

// ── R-NFE-033 · XML ─────────────────────────────────────────────────────────

it('R-NFE-033 · documento fiscal preserva as letras e não muda CPF nem CNPJ numérico', function () {
    expect(Cnpj::documentoFiscal(CNPJ_ALFA_VALIDO))->toBe('12ABC34501DE35')
        ->and(Cnpj::documentoFiscal(CNPJ_NUM_VALIDO))->toBe('11222333000181')
        ->and(Cnpj::documentoFiscal('529.982.247-25'))->toBe('52998224725') // pii-allowlist (CPF fictício de documentação)
        // fora de forma: comportamento antigo (só dígitos), para não mudar o que já funcionava
        ->and(Cnpj::documentoFiscal('CPF 529.982.247-25'))->toBe('52998224725') // pii-allowlist (CPF fictício)
        // forma de CNPJ alfanumérico com DV errado não vira CNPJ no XML
        ->and(Cnpj::documentoFiscal(CNPJ_ALFA_DV_ERRADO))->toBe('123450136');
})->group('nfe', 'R-NFE-033');

it('R-NFE-033 · XML leva o CNPJ alfanumérico do destinatário como está', function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('SQLite-incompatível: nfe_business_configs requer schema MySQL (ADR 0101)');
    }
    session(['business.id' => CNPJ_ALFA_BIZ]);

    $dest = cnpjAlfaDest(cnpjAlfaXml(CNPJ_ALFA_VALIDO));

    // Antes deste fix as letras eram arrancadas ("1234501") e <dest> saía sem <CNPJ>.
    $this->assertStringContainsString('<CNPJ>12ABC34501DE35</CNPJ>', $dest, 'dest sem o CNPJ alfanumérico');
})->group('nfe', 'R-NFE-033');

it('R-NFE-033 · controle positivo: XML com CNPJ numérico do destinatário não muda', function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('SQLite-incompatível: nfe_business_configs requer schema MySQL (ADR 0101)');
    }
    session(['business.id' => CNPJ_ALFA_BIZ]);

    $dest = cnpjAlfaDest(cnpjAlfaXml(CNPJ_NUM_VALIDO));

    $this->assertStringContainsString('<CNPJ>11222333000181</CNPJ>', $dest, 'dest sem o CNPJ numérico');
})->group('nfe', 'R-NFE-033');

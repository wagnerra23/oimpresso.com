<?php

declare(strict_types=1);

// @covers-us R-NFSE-006 · UC-NFSEM-05 — cTribNac pela tabela nacional (Anexo B oficial): item + subitem LC 116 + desdobro; escolha obrigatória quando há vários.

use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Modules\NFSe\Adapters\SnNfseAdapter;
use Modules\NFSe\DTO\NfseEmissaoPayload;
use Modules\NFSe\Exceptions\NfseException;
use Modules\NFSe\Http\Requests\StoreNfseRequest;
use Modules\NfeBrasil\Services\Nfse\ListaServicoNacional;

uses(Tests\TestCase::class);

/**
 * R-NFSE-006. Até aqui o cTribNac era item + subitem + "01" fixo — errado em 62 dos 201
 * subitens da lista nacional. O 7.10 é o exemplo: 071001 é "vias e logradouros
 * públicos" e 071002 é "imóveis, chaminés, piscinas".
 */
beforeEach(function () {
    $this->lista = new ListaServicoNacional();
});

it('R-NFSE-006 a tabela versionada é a do Anexo B v1.01: 338 códigos, 201 subitens, 62 com vários desdobros', function () {
    $json = json_decode((string) file_get_contents(base_path(ListaServicoNacional::ARQUIVO)), true);

    expect($json['fonte'])->toBe('anexo_b-nbs2-lista_servico_nacional-snnfse-v1-01-20260122.xlsx');
    expect($json['fonte_sha256'])->toBe('e74b0be8ad204e458932efb1716225a3fb5703a84e5475ce5c4c6789d1c61b1a');
    expect($this->lista->todos())->toHaveCount(338);
    expect($json['total'])->toBe(338);

    $subitens = [];
    foreach (array_keys($this->lista->todos()) as $codigo) {
        expect((string) $codigo)->toMatch('/^\d{6}$/');
        $subitens[substr(str_pad((string) $codigo, 6, '0', STR_PAD_LEFT), 0, 4)] = true;
    }
    expect($subitens)->toHaveCount(201);
    expect($this->lista->subitensComVariosDesdobros())->toHaveCount(62);
});

it('R-NFSE-006 subitem de desdobro único resolve sozinho', function () {
    expect($this->lista->resolver('1.05'))->toBe('010501');
    expect($this->lista->resolver('17.06'))->toBe('170601');
    expect($this->lista->resolver('01.05'))->toBe('010501');
});

it('R-NFSE-006 UC-NFSEM-05 subitem com vários desdobros exige escolha, e a escolha precisa ser dele', function () {
    expect($this->lista->desdobros('7.10'))->toHaveKeys(['071001', '071002']);
    expect($this->lista->resolver('7.10', '071002'))->toBe('071002');

    expect(fn () => $this->lista->resolver('7.10'))->toThrow(InvalidArgumentException::class, 'tem 2 desdobros nacionais');
    expect(fn () => $this->lista->resolver('7.10', '010501'))->toThrow(InvalidArgumentException::class, 'não pertence ao item 7.10');
    expect(fn () => $this->lista->resolver('99.99'))->toThrow(InvalidArgumentException::class, 'não está na lista');
});

it('R-NFSE-006 UC-NFSEM-05 a tela recebe os subitens com vários desdobros no formato "7.10"', function () {
    $grupos = $this->lista->subitensComVariosDesdobros();

    expect($grupos)->toHaveKey('7.10');
    expect(array_column($grupos['7.10'], 'codigo'))->toBe(['071001', '071002']);
    expect($grupos)->not->toHaveKey('1.05');
});

it('R-NFSE-006 a DPS leva o desdobro escolhido e falha visível sem escolha', function () {
    $payload = fn (?string $c) => new NfseEmissaoPayload(
        businessId: 99, rpsNumero: '1', competencia: Carbon::create(2026, 10, 1), tomadorNome: 'T',
        tomadorCnpj: '11222333000181', tomadorCpf: null, tomadorEmail: null, descricao: 'Manutenção predial',
        lc116Codigo: '7.10', valorServicos: 100.0, aliquotaIss: 0.05, prestadorCnpj: '11222333000181',
        regimePrestador: 'lucro_presumido', cTribNac: $c,
    );

    expect((new SnNfseAdapter())->buildDps($payload('071002'))['infDPS']['serv']['cServ']['cTribNac'])->toBe('071002');

    try {
        (new SnNfseAdapter())->buildDps($payload(null));
        $this->fail('esperava NfseException');
    } catch (NfseException $e) {
        expect($e->codigo)->toBe('CTRIBNAC');
        expect($e->getMessage())->toContain('071001, 071002');
    }
});

it('R-NFSE-006 UC-NFSEM-05 o formulário recusa item fora da lista e desdobro faltando ou de outro item', function () {
    $erros = function (array $dados) {
        $base = [
            'competencia' => '2026-10', 'tomador_nome' => 'T', 'descricao' => 'd',
            'valor_servicos' => '100', 'aliquota_iss' => '0.05',
        ];
        $req = StoreNfseRequest::create('/nfse/emitir', 'POST', array_merge($base, $dados));
        $v = Validator::make($req->all(), $req->rules());
        $req->withValidator($v);
        $v->passes();

        return $v->errors()->toArray();
    };

    expect($erros(['lc116_codigo' => '1.05']))->toBe([]);
    expect($erros(['lc116_codigo' => '7.10', 'ctrib_nac' => '071002']))->toBe([]);
    expect($erros(['lc116_codigo' => '7.10']))->toHaveKey('ctrib_nac');
    expect($erros(['lc116_codigo' => '7.10', 'ctrib_nac' => '010501']))->toHaveKey('ctrib_nac');
    expect($erros(['lc116_codigo' => '99.99']))->toHaveKey('lc116_codigo');
});

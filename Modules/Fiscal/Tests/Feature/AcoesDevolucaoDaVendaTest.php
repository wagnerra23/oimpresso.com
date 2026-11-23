<?php

declare(strict_types=1);

// @covers-us US-NFE-064 — onda 1 do protótipo Devolução V3: a NF-e de devolução sai da devolução
// de venda registrada (RUNBOOK-sell-return-add §6.2).

use Illuminate\Support\Facades\Auth;
use Modules\Fiscal\Http\Controllers\AcoesController;
use Modules\NfeBrasil\Services\DevolucaoService;
use Modules\NfeBrasil\Services\NfeService;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(Tests\TestCase::class);

/**
 * O imposto da devolução continua inteiro no DevolucaoService (provado em NfeDevolucaoTest). O que
 * esta onda acrescenta é a TRADUÇÃO: `quantity_returned` por linha da venda → quantidade por item
 * da NF-e de origem, declarando só o saldo. Os casos abaixo não usam banco: o XML é escrito à mão
 * no formato que a SEFAZ autoriza (só os campos que o serviço lê).
 */

/** Item do XML de origem: [cProd, qCom, vProd, vICMS] com ICMS00 a 18% e CFOP 5102. */
function dvvXml(array $itens): string
{
    $dets = '';
    foreach ($itens as $i => [$cprod, $q, $vprod, $vicms]) {
        $n = $i + 1;
        $dets .= "<det nItem=\"{$n}\"><prod><cProd>{$cprod}</cProd><xProd>PRODUTO {$n}</xProd><NCM>61091000</NCM>"
            . "<CFOP>5102</CFOP><uCom>UN</uCom><qCom>{$q}</qCom><vUnCom>10.00</vUnCom><vProd>{$vprod}</vProd>"
            . "<uTrib>UN</uTrib><qTrib>{$q}</qTrib><vUnTrib>10.00</vUnTrib><indTot>1</indTot></prod>"
            . "<imposto><ICMS><ICMS00><orig>0</orig><CST>00</CST><modBC>3</modBC><vBC>{$vprod}</vBC>"
            . "<pICMS>18.0000</pICMS><vICMS>{$vicms}</vICMS></ICMS00></ICMS></imposto></det>";
    }

    return '<?xml version="1.0" encoding="UTF-8"?><NFe xmlns="http://www.portalfiscal.inf.br/nfe">'
        . '<infNFe Id="NFe35261111222333000181550010000000011000000011" versao="4.00">'
        . "<dest><CNPJ>11222333000181</CNPJ><xNome>CLIENTE TESTE LTDA</xNome><indIEDest>9</indIEDest></dest>" // pii-allowlist (CNPJ fictício de documentação)
        . $dets . '</infNFe></NFe>';
}

function dvvLinha(int $id, string $cprod, float $q): array
{
    return ['sell_line_id' => $id, 'cprod' => $cprod, 'quantidade' => $q];
}

function dvvServico(): DevolucaoService
{
    return new DevolucaoService(\Mockery::mock(NfeService::class));
}

function dvvErro(callable $fn): ?Throwable
{
    try {
        $fn();

        return null;
    } catch (Throwable $e) {
        return $e;
    }
}

it('UC-FNFE-73 · a quantidade devolvida de cada linha vira a quantidade do item da NF-e', function () {
    $xml = dvvXml([['SKU1', '10.0000', '100.00', '18.00'], ['SKU2', '5.0000', '50.00', '9.00']]);
    $linhas = [dvvLinha(501, 'SKU1', 10), dvvLinha(502, 'SKU2', 5)];

    $itens = dvvServico()->quantidadesPorItem($xml, $linhas, [501 => 4.0, 502 => 0.0], []);

    // Só a linha 1 voltou: item 1 com 4, item 2 fora da nota.
    expect($itens)->toBe([1 => 4.0]);
});

it('UC-FNFE-74 · declara só o saldo ainda não declarado em outra NF-e de devolução', function () {
    $xml = dvvXml([['SKU1', '10.0000', '100.00', '18.00']]);

    // Devolução registrada: 6. Já declarado numa NF-e anterior: 4. Saldo: 2.
    $itens = dvvServico()->quantidadesPorItem($xml, [dvvLinha(501, 'SKU1', 10)], [501 => 6.0], [1 => 4.0]);

    expect($itens)->toBe([1 => 2.0]);
});

it('UC-FNFE-75 · devolução reduzida depois da NF-e é recusada, nunca compensada', function () {
    $xml = dvvXml([['SKU1', '10.0000', '100.00', '18.00']]);

    $e = dvvErro(fn () => dvvServico()->quantidadesPorItem($xml, [dvvLinha(501, 'SKU1', 10)], [501 => 3.0], [1 => 4.0]));

    expect($e)->toBeInstanceOf(RuntimeException::class);
    expect($e->getMessage())->toContain('menor do que a NF-e de devolução já declarada');
});

it('UC-FNFE-76 · item da nota que não é a linha da venda recusa (produto ou quantidade mudou)', function () {
    $xml = dvvXml([['SKU1', '10.0000', '100.00', '18.00']]);

    $produto = dvvErro(fn () => dvvServico()->quantidadesPorItem($xml, [dvvLinha(501, 'SKU9', 10)], [501 => 1.0], []));
    $quantidade = dvvErro(fn () => dvvServico()->quantidadesPorItem($xml, [dvvLinha(501, 'SKU1', 12)], [501 => 1.0], []));
    $contagem = dvvErro(fn () => dvvServico()->quantidadesPorItem(
        $xml, [dvvLinha(501, 'SKU1', 10), dvvLinha(502, 'SKU2', 1)], [501 => 1.0], [],
    ));

    expect($produto?->getMessage())->toContain('não é a linha 1 da venda');
    expect($quantidade?->getMessage())->toContain('não é a linha 1 da venda');
    expect($contagem?->getMessage())->toContain('não batem com as linhas da venda');

    // Controle positivo: a mesma chamada com a linha certa passa.
    expect(dvvServico()->quantidadesPorItem($xml, [dvvLinha(501, 'SKU1', 10)], [501 => 1.0], []))->toBe([1 => 1.0]);
});

it('UC-FNFE-77 · sem nada a declarar a NF-e não é emitida', function () {
    $xml = dvvXml([['SKU1', '10.0000', '100.00', '18.00']]);

    $e = dvvErro(fn () => dvvServico()->quantidadesPorItem($xml, [dvvLinha(501, 'SKU1', 10)], [501 => 4.0], [1 => 4.0]));

    expect($e?->getMessage())->toContain('Nada a declarar');
});

it('UC-FNFE-78 · valor: 4 de 10 unidades devolvem 4/10 do produto e do ICMS da origem', function () {
    // REGRA MESTRE, caminho 1 (à mão): venda 10 un × 10,00 = 100,00 · ICMS 18% = 18,00.
    // Devolver 4: vProd = 100,00 × 4/10 = 40,00 · vBC = 40,00 · vICMS = 18,00 × 4/10 = 7,20.
    // Caminho 2 (código): a tradução desta onda alimenta o montarDevolucao que já existe.
    $svc = dvvServico();
    $xml = dvvXml([['SKU1', '10.0000', '100.00', '18.00']]);

    $itens = $svc->quantidadesPorItem($xml, [dvvLinha(501, 'SKU1', 10)], [501 => 4.0], []);
    $nota = $svc->montarDevolucao($xml, $itens);

    expect($nota['dets'])->toHaveCount(1);
    expect($nota['dets'][0]['qcom'])->toBe(4.0);
    expect($nota['dets'][0]['vprod'])->toBe(40.0);
    expect($nota['dets'][0]['icms']['vbc'])->toBe(40.0);
    expect($nota['dets'][0]['icms']['vicms'])->toBe(7.2);
    expect($nota['dets'][0]['cfop'])->toBe('1202');
    expect($nota['total']['v_nf'])->toBe(40.0);
});

it('UC-FNFE-79 · com a flag desligada a rota responde 404, antes de qualquer outra coisa', function () {
    config(['nfebrasil.devolucao_pela_venda' => false]);

    $e = dvvErro(fn () => (new AcoesController())->devolucaoDaVenda(dvvServico(), 1));

    expect($e)->toBeInstanceOf(HttpException::class);
    expect($e->getStatusCode())->toBe(404);
});

it('UC-FNFE-80 · com a flag ligada, sem a permissão fiscal.nfe.acoes é recusada (403)', function () {
    config(['nfebrasil.devolucao_pela_venda' => true]);
    Auth::setUser(new class extends \App\User
    {
        public function can($abilities, $arguments = []): bool
        {
            return false;
        }
    });

    $e = dvvErro(fn () => (new AcoesController())->devolucaoDaVenda(dvvServico(), 1));

    expect($e)->toBeInstanceOf(HttpException::class);
    expect($e->getStatusCode())->toBe(403);
});

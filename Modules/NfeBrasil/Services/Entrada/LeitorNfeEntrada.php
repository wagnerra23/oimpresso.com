<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services\Entrada;

use InvalidArgumentException;
use SimpleXMLElement;

/**
 * Lê o XML de uma NF-e recebida (fornecedor emitiu contra o CNPJ da empresa).
 *
 * ADR `proposals/2026-10-06-entrada-xml-compra.md` D2 + fase 2: a DF-e que chega por NSU e o
 * arquivo enviado pela pessoa passam pelo MESMO leitor. Aceita `nfeProc` (NF-e + protocolo) e
 * `NFe` sem protocolo. Não toca banco nem decide nada: devolve o que está no XML.
 *
 * Por item, além do que já era lido: `cProd` (código do item no fornecedor, chave do de-para
 * D3), `cEAN`, `uCom`, CST ou CSOSN e o ICMS destacado (`vICMS`).
 */
final class LeitorNfeEntrada
{
    /**
     * @return array{chave_44: string, cnpj_emitente: string, nome_emitente: string,
     *     cnpj_destinatario: string, valor_total: float, num_protocolo: string,
     *     data_emissao: ?\DateTimeImmutable, itens: list<array<string, mixed>>}
     *
     * @throws InvalidArgumentException quando o texto não é XML de NF-e
     */
    public static function ler(string $xml): array
    {
        $raiz = self::carregar($xml);

        return self::dadosDaNota($raiz);
    }

    /** Mesmo que {@see ler}, para quem já tem o `SimpleXMLElement` do `procNFe`. */
    public static function dadosDaNota(SimpleXMLElement $raiz): array
    {
        $nfe = $raiz->getName() === 'NFe' ? $raiz : ($raiz->NFe ?? null);
        $infNFe = $nfe?->infNFe;
        if (! $infNFe || ! isset($infNFe['Id'])) {
            throw new InvalidArgumentException('O arquivo não é uma NF-e: falta o grupo infNFe.');
        }

        $itens = [];
        foreach ($infNFe->det as $det) {
            $prod = $det->prod;
            if (! $prod) {
                continue;
            }
            [$cst, $vIcms] = self::icms($det->imposto->ICMS ?? null);
            $ean = trim((string) $prod->cEAN);
            $itens[] = [
                'codigo_fornecedor' => trim((string) $prod->cProd) ?: null,
                'ean'               => ($ean === '' || strtoupper($ean) === 'SEM GTIN') ? null : $ean,
                'unidade'           => trim((string) $prod->uCom) ?: null,
                'ncm'               => (string) $prod->NCM,
                'cfop'              => (string) $prod->CFOP,
                'cst_csosn'         => $cst,
                'descricao'         => (string) $prod->xProd,
                'quantidade'        => (float) $prod->qCom,
                'valor_unitario'    => (float) $prod->vUnCom,
                'valor_total'       => (float) $prod->vProd,
                'icms_valor'        => $vIcms,
            ];
        }

        $dest = $infNFe->dest;

        return [
            'chave_44'          => substr((string) $infNFe['Id'], 3),
            'cnpj_emitente'     => (string) $infNFe->emit->CNPJ,
            'nome_emitente'     => (string) $infNFe->emit->xNome,
            'cnpj_destinatario' => (string) ($dest->CNPJ ?? ''),
            'valor_total'       => (float) $infNFe->total->ICMSTot->vNF,
            'num_protocolo'     => (string) ($raiz->protNFe->infProt->nProt ?? ''),
            'data_emissao'      => isset($infNFe->ide->dhEmi) ? new \DateTimeImmutable((string) $infNFe->ide->dhEmi) : null,
            'itens'             => $itens,
        ];
    }

    private static function carregar(string $xml): SimpleXMLElement
    {
        $anterior = libxml_use_internal_errors(true);
        try {
            // LIBXML_NONET: o arquivo vem de fora; nada de buscar entidade na rede.
            $raiz = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($anterior);
        }
        if (! $raiz) {
            throw new InvalidArgumentException('O arquivo não é um XML válido.');
        }

        return $raiz;
    }

    /**
     * O grupo ICMS tem um filho por situação (ICMS00, ICMS60, ICMSSN102...). CST no regime
     * normal, CSOSN no Simples. `vICMS` só existe onde há imposto destacado.
     *
     * @return array{0: ?string, 1: ?float}
     */
    private static function icms(?SimpleXMLElement $grupo): array
    {
        if (! $grupo) {
            return [null, null];
        }
        foreach ($grupo->children() as $situacao) {
            $codigo = (string) ($situacao->CST ?? '') ?: (string) ($situacao->CSOSN ?? '');
            if (strlen($codigo) === 2) {
                $codigo = ((string) $situacao->orig) . $codigo; // CST com origem: 0 + 60 = "060"
            }

            return [$codigo !== '' ? $codigo : null, isset($situacao->vICMS) ? (float) $situacao->vICMS : null];
        }

        return [null, null];
    }
}

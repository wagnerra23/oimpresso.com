<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services\Nfse;

use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use NFePHP\Common\Certificate;
use NFePHP\Common\Signer;

/**
 * DPS da NFS-e Nacional em XML assinado — R-NFSE-005.
 *
 * Leiaute: esquemas XSD oficiais v1.01 (pacote `nfse-esquemas_xsd-v1-01-20260209`,
 * gov.br/nfse, baixado em 2026-10-13): elemento raiz `DPS` (tipo TCDPS) no namespace
 * `http://www.sped.fazenda.gov.br/nfse`, atributo `versao` ("1.00" | "1.01"), filho
 * `infDPS` com atributo `Id` (padrão `DPS[0-9]{42}`) e, depois dele, `ds:Signature`.
 *
 * Transporte (Manual dos Contribuintes — API do Emissor Público Nacional, v1.2):
 * `POST /nfse` com a DPS assinada, comprimida em GZip e codificada em Base64.
 *
 * A estrutura de entrada é um array na ORDEM do XSD (xs:sequence): chave = nome do
 * elemento, valor = texto ou array filho; chave começando com "@" vira atributo; null
 * é omitido. Quem monta a estrutura é o `SnNfseAdapter::buildDps` — aqui só se
 * serializa, assina e empacota, sem regra fiscal.
 *
 * Mora no NfeBrasil por D-NFSE-CONSOLIDA ([W] 2026-10-13).
 */
final class DpsXml
{
    public const NAMESPACE = 'http://www.sped.fazenda.gov.br/nfse';

    public const VERSAO = '1.01';

    /**
     * @param array<string, mixed> $dps ['infDPS' => [...]]
     */
    public static function serializar(array $dps, string $versao = self::VERSAO): string
    {
        if (! isset($dps['infDPS']) || ! is_array($dps['infDPS'])) {
            throw new InvalidArgumentException('Estrutura da DPS sem o grupo infDPS.');
        }

        return self::documento('DPS', 'infDPS', $dps['infDPS'], $versao);
    }

    /**
     * Pedido de Registro de Evento (`pedRegEvento`, tipo TCPedRegEvt de
     * `tiposEventos_v1.01.xsd`): raiz `pedRegEvento` com `versao` e o grupo `infPedReg`
     * (atributo `Id`, padrão `PRE[0-9]{56}`), na mesma regra de estrutura da DPS.
     * Quem monta o grupo é o `SnNfseAdapter::buildPedRegEventoCancelamento`.
     *
     * @param array<string, mixed> $pedido ['infPedReg' => [...]]
     */
    public static function serializarPedRegEvento(array $pedido, string $versao = self::VERSAO): string
    {
        if (! isset($pedido['infPedReg']) || ! is_array($pedido['infPedReg'])) {
            throw new InvalidArgumentException('Pedido de registro de evento sem o grupo infPedReg.');
        }

        return self::documento('pedRegEvento', 'infPedReg', $pedido['infPedReg'], $versao);
    }

    /**
     * Assina o `infPedReg` (referência pelo atributo Id). O Anexo II (LeiautesRN_Eventos)
     * exige assinatura digital no pedido de registro de evento.
     */
    public static function assinarPedRegEvento(string $xml, string $pfxBinario, string $senha, int $algoritmo = OPENSSL_ALGO_SHA256): string
    {
        $certificado = Certificate::readPfx($pfxBinario, $senha);

        return Signer::sign($certificado, $xml, 'infPedReg', 'Id', $algoritmo);
    }

    /** @param array<string, mixed> $grupo */
    private static function documento(string $raizNome, string $grupoNome, array $grupo, string $versao): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;

        $raiz = $doc->createElementNS(self::NAMESPACE, $raizNome);
        $raiz->setAttribute('versao', $versao);
        $doc->appendChild($raiz);

        self::anexar($doc, $raiz, $grupoNome, $grupo);

        return (string) $doc->saveXML($doc->documentElement);
    }

    /**
     * Assina o `infDPS` (referência pelo atributo Id) com o certificado A1.
     *
     * @param string $pfxBinario conteúdo binário do .pfx (não base64)
     * @param int    $algoritmo  OPENSSL_ALGO_SHA256 (padrão) ou OPENSSL_ALGO_SHA1
     */
    public static function assinar(string $xml, string $pfxBinario, string $senha, int $algoritmo = OPENSSL_ALGO_SHA256): string
    {
        $certificado = Certificate::readPfx($pfxBinario, $senha);

        return Signer::sign($certificado, $xml, 'infDPS', 'Id', $algoritmo);
    }

    /** DPS assinada → GZip → Base64, o formato que a Sefin Nacional recebe. */
    public static function empacotar(string $xmlAssinado): string
    {
        $gz = gzencode($xmlAssinado);
        if ($gz === false) {
            throw new InvalidArgumentException('Falha ao comprimir a DPS.');
        }

        return base64_encode($gz);
    }

    /** O inverso do empacotar — usado no retorno (`nfseXmlGZipB64`) e nos testes. */
    public static function desempacotar(string $gzipBase64): string
    {
        $bin = base64_decode($gzipBase64, true);
        $xml = $bin === false ? false : gzdecode($bin);
        if ($xml === false) {
            throw new InvalidArgumentException('Conteúdo GZip/Base64 inválido.');
        }

        return $xml;
    }

    /** @param mixed $valor */
    private static function anexar(DOMDocument $doc, DOMElement $pai, string $nome, $valor): void
    {
        if ($valor === null) {
            return;
        }

        $el = $doc->createElementNS(self::NAMESPACE, $nome);

        if (is_array($valor)) {
            foreach ($valor as $chave => $filho) {
                $chave = (string) $chave;
                if (str_starts_with($chave, '@')) {
                    if ($filho !== null) {
                        $el->setAttribute(substr($chave, 1), (string) $filho);
                    }
                    continue;
                }
                self::anexar($doc, $el, $chave, $filho);
            }
        } else {
            $el->appendChild($doc->createTextNode((string) $valor));
        }

        $pai->appendChild($el);
    }
}

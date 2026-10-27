<?php

declare(strict_types=1);

namespace Modules\Fiscal\Services\Sped;

use InvalidArgumentException;
use Modules\NfeBrasil\Support\UfBrasil;

/**
 * US-FISCAL-028 fatia 2 — quem é o emitente nos registros 0000 e 0005.
 *
 * Mesmas fontes que a NF-e usa no grupo `emit` (`NfeService::buildXml`), porque o 0000 tem de
 * identificar o mesmo estabelecimento que emitiu as notas do bloco C:
 *  - CNPJ: `CnpjDoEmitente::resolver` (certificado → business.cnpj → tax_number_1, sem o zerado);
 *  - razão social, IE e endereço: `business` (razao_social, ie, rua, numero, bairro, cep);
 *  - UF: primeira `business_locations.state`, aceitando o nome por extenso (`UfBrasil`);
 *  - COD_MUN: `cidades.officeimpresso_codigo` pelo `business.cidade_id`.
 *
 * Onde a NF-e cai em padrão ('SP', '9999999', CNPJ zerado), aqui o arquivo é RECUSADO com a lista
 * do que falta: um 0000 com identificação inventada é um arquivo válido no formato e falso no
 * conteúdo (proposta 2026-09-04, §5.3).
 */
final class EmitenteSped
{
    private function __construct(
        public readonly string $nome,
        public readonly string $fantasia,
        public readonly string $cnpj,
        public readonly string $ie,
        public readonly string $uf,
        public readonly string $codMun,
        public readonly string $cep,
        public readonly string $logradouro,
        public readonly string $numero,
        public readonly string $bairro,
        public readonly string $fone,
        public readonly string $email,
    ) {
    }

    /**
     * @param  object       $business  linha de `business`
     * @param  string|null  $cnpj      já resolvido por `CnpjDoEmitente::resolver`
     * @param  string|null  $ufLocal   `business_locations.state` da primeira location
     * @param  string|null  $codMun    `cidades.officeimpresso_codigo`
     */
    public static function de(object $business, ?string $cnpj, ?string $ufLocal, ?string $codMun, ?string $email = null): self
    {
        $faltas = [];

        $cnpj = preg_replace('/\D/', '', (string) $cnpj) ?? '';
        if (strlen($cnpj) !== 14) {
            $faltas[] = 'CNPJ';
        }

        $ie = preg_replace('/\D/', '', (string) ($business->ie ?? '')) ?? '';
        if ($ie === '' || trim($ie, '0') === '') {
            $faltas[] = 'inscrição estadual';
        }

        $uf = UfBrasil::sigla($ufLocal);
        if ($uf === null) {
            $faltas[] = 'UF do estabelecimento';
        }

        $codMun = preg_replace('/\D/', '', (string) $codMun) ?? '';
        if (strlen($codMun) !== 7) {
            $faltas[] = 'município (código IBGE)';
        }

        $cep = substr(preg_replace('/\D/', '', (string) ($business->cep ?? '')) ?? '', 0, 8);
        $rua = self::semAsterisco((string) ($business->rua ?? ''), 60);
        $bairro = self::semAsterisco((string) ($business->bairro ?? ''), 60);
        if (strlen($cep) !== 8 || trim($cep, '0') === '' || $rua === '' || $bairro === '') {
            // CEP, END e BAIRRO são obrigatórios no 0005 (Guia Prático v3.2.3).
            $faltas[] = 'endereço (CEP, logradouro e bairro)';
        }

        if ($faltas !== []) {
            throw new InvalidArgumentException(
                'Emitente incompleto para a EFD: falta ' . implode(', ', $faltas)
                . '. Preencha em Fiscal › Configuração — o arquivo não sai com identificação inventada.'
            );
        }

        $razao = trim((string) ($business->razao_social ?? ''));
        $nome = ($razao === '' || $razao === '*') ? (string) ($business->name ?? '') : $razao;

        return new self(
            nome: mb_strtoupper(mb_substr($nome, 0, 100)),
            fantasia: mb_substr((string) ($business->name ?? $nome), 0, 60),
            cnpj: $cnpj,
            ie: $ie,
            uf: $uf,
            codMun: $codMun,
            cep: $cep,
            logradouro: $rua,
            numero: self::semAsterisco((string) ($business->numero ?? ''), 10),
            bairro: $bairro,
            fone: substr(preg_replace('/\D/', '', (string) ($business->telefone ?? '')) ?? '', 0, 11),
            email: (string) ($email ?? ''),
        );
    }

    /** As colunas legadas nascem com '*' de padrão; isso não é endereço. */
    private static function semAsterisco(string $valor, int $max): string
    {
        $valor = trim($valor);

        return $valor === '*' ? '' : mb_substr($valor, 0, $max);
    }
}

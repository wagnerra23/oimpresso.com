<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\CustomerMemory\Sources;

/**
 * US-WA-VOZ-002 — Contrato pra lookup de cliente em fonte externa Firebird
 * (OfficeImpresso legacy WR Sistemas).
 *
 * Driver-agnostic: implementações podem ser JSON pré-exportado (caso atual,
 * `JsonFileFirebirdSource`), PDO direto se Hostinger ganhar suporte futuro,
 * tunnel HTTP via Python local, etc.
 *
 * Resolução por telefone — phone E.164 sem '+' (ex: '5548999872822').
 *
 * Retorno padronizado:
 *   - Array vazio = sem match
 *   - 1+ items = candidates com chaves obrigatórias:
 *     - cliente_id (int)         CLIENTES.CODIGO
 *     - nome (string)            CLIENTES.RAZAO_SOCIAL ou CLIENTES.NOME
 *     - fone1 (?string)          E.164 normalizado
 *     - fone2 (?string)
 *     - email (?string)
 *     - bloqueado (bool)         BLOQUEADO='S'
 *     - cpf_cnpj (?string)
 *     - cidade (?string)
 *     - data_cadastro (?string)  ISO 8601
 *
 * @see Modules/Whatsapp/Services/CustomerMemory/Sources/JsonFileFirebirdSource.php
 * @see memory/requisitos/Officeimpresso/OFFICEIMPRESSO-FIREBIRD-SCHEMA.md §2.5
 * @see scripts/firebird/export-customers.py
 */
interface FirebirdLookupSourceContract
{
    /**
     * Lookup por telefone E.164 sem '+'.
     *
     * @return array<int, array{cliente_id:int,nome:string,fone1:?string,fone2:?string,email:?string,bloqueado:bool,cpf_cnpj:?string,cidade:?string,data_cadastro:?string}>
     */
    public function lookupByPhone(string $phoneE164): array;

    /**
     * Health check — fonte está disponível agora?
     */
    public function isHealthy(): bool;

    /**
     * Identificador legível da fonte (pra logs + external_sources.source).
     * Ex: 'firebird_office_json:2026-05-15', 'firebird_office_pdo'.
     */
    public function sourceLabel(): string;
}

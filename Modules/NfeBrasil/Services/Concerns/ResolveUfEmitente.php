<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * UF do emitente para o `siglaUF` da config do `Tools` (sped-nfe).
 *
 * =====================================================================================
 * POR QUE EXISTE
 * =====================================================================================
 * Os dois serviços de manifestação liam `business.state`:
 *
 *     DB::table('business')->select(['name', 'tax_number_1', 'state'])
 *
 * Essa coluna **não existe**. Medido em 2026-09-04 nos três lugares, e os três dão o mesmo
 * número: schema canônico `database/schema/mysql-schema.sql` (133 colunas), staging CT 100
 * (133) e produção Hostinger (133) — nenhuma chamada `state`, e nenhuma das 43 migrations que
 * tocam `business` a cria. Toda manifestação morria em `SQLSTATE[42S22] Unknown column 'state'`,
 * por qualquer caminho — a tela `/fiscal/dfe` e o `ManifestacaoController::bulkConfirmar`.
 *
 * A UF do emitente **sempre** morou em `business_locations.state`, e o caminho de emissão já
 * lia dali: `NfeService::resolverUF()` faz exatamente isto — primeira location do business,
 * validação de formato, fallback `SP`. A manifestação é que nunca usou.
 *
 * =====================================================================================
 * POR QUE UM TRAIT, E POR QUE O `NfeService` NÃO FOI MIGRADO JUNTO
 * =====================================================================================
 * São dois consumidores com o mesmo defeito. Corrigir um só é a lápide §5 2026-08-02 ("corrigir
 * UMA de N implementações duplicadas") — daí um dono único em vez de duas cópias consertadas.
 *
 * O `NfeService::resolverUF()` **fica onde está**, deliberadamente: ele funciona, serve o
 * caminho de EMISSÃO (Tier 0 — a UF entra no XML que vai à SEFAZ e influencia tributação), e
 * migrá-lo exigiria a prova dupla da regra de valor/estoque, que é outro PR e outro mandato.
 * A duplicação é consciente e está declarada aqui, que é o que a lápide pede quando não se
 * unifica. Ele recebe `object $business` já carregado; este recebe `int $businessId`, que é o
 * que os dois `buildConfig` têm em mãos.
 *
 * @see Modules/NfeBrasil/Services/NfeService.php — `resolverUF()`, a implementação irmã
 * @see Modules/NfeBrasil/Tests/Feature/ResolveUfEmitenteTest.php
 */
trait ResolveUfEmitente
{
    /** UF quando o business não tem location com UF válida. Manifestação é nacional; o Tools só exige um cUF. */
    private const UF_PADRAO = 'SP';

    /**
     * UF de duas letras do emitente, a partir da primeira location do business.
     *
     * Escopo explícito por `business_id` — `business_locations` é consultada por query builder,
     * que não passa por global scope de Eloquent (ADR 0093).
     */
    protected function resolverUfEmitente(int $businessId): string
    {
        $uf = DB::table('business_locations')
            ->where('business_id', $businessId)
            ->orderBy('id')
            ->value('state');

        $uf = strtoupper(trim((string) $uf));

        // `business_locations.state` é `varchar(100)` e aceita nome por extenso ("São Paulo"),
        // que o Tools rejeitaria. Só a sigla de 2 letras passa; o resto cai no padrão.
        return preg_match('/^[A-Z]{2}$/', $uf) === 1 ? $uf : self::UF_PADRAO;
    }
}

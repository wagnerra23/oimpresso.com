<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * US-CRM-080 -- backfill IDEMPOTENTE do endereco plano dos contatos existentes
 * pra 1 linha `contact_addresses` is_default=true por contato.
 *
 * Origem: campos planos de `contacts` (address_line_1/2, numero, neighborhood,
 * city, city_code, state, zip_code, country) + shipping_address (text livre)
 * vira `label='Principal'` quando os campos estruturados estao vazios mas existe
 * shipping_address texto (preserva o dado de entrega livre sem perder).
 *
 * IDEMPOTENTE: so insere se o contato AINDA NAO tem nenhum endereco. Rodar 2x
 * nao duplica. NAO dropa colunas velhas (rollback safety + accessor compat).
 *
 * Sem session aqui (migration CLI) -> insert direto com business_id explicito
 * (Garantia 3 ADR 0093). NAO loga endereco (PII LGPD).
 *
 * down(): remove apenas linhas marcadas como geradas pelo backfill
 * (label='Principal' AND created_at = updated_at heuristica fraca -> usamos
 * coluna sentinela via observacao: aqui optamos por NAO reverter dados
 * (backfill e seguro/aditivo); o create-table down() ja dropa a tabela inteira
 * em rollback completo). down() e no-op intencional documentado.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
return new class extends Migration
{
    public function up(): void
    {
        // Processa em chunks pra nao estourar memoria em prod (biz=4 muitos contatos).
        DB::table('contacts')
            ->select([
                'id', 'business_id',
                'address_line_1', 'address_line_2', 'numero', 'neighborhood',
                'city', 'city_code', 'state', 'zip_code', 'country',
                'shipping_address',
            ])
            ->orderBy('id')
            ->chunkById(500, function ($contacts) {
                $now = now();
                $rows = [];

                foreach ($contacts as $c) {
                    // Idempotencia: pula contato que ja tem endereco.
                    $exists = DB::table('contact_addresses')
                        ->where('business_id', $c->business_id)
                        ->where('contact_id', $c->id)
                        ->exists();
                    if ($exists) {
                        continue;
                    }

                    $hasStructured = $c->address_line_1 || $c->city || $c->zip_code;
                    $hasShippingText = ! empty($c->shipping_address);

                    // Nada de endereco -> nao cria linha vazia.
                    if (! $hasStructured && ! $hasShippingText) {
                        continue;
                    }

                    $rows[] = [
                        'business_id' => $c->business_id,
                        'contact_id' => $c->id,
                        'label' => 'Principal',
                        'recipient' => null,
                        'phone' => null,
                        // Quando ha so shipping_address texto livre e nenhum campo
                        // estruturado, joga o texto em address_line_1 (preserva).
                        'address_line_1' => $c->address_line_1
                            ?: ($hasStructured ? null : $c->shipping_address),
                        'address_line_2' => $c->address_line_2,
                        'numero' => $c->numero,
                        'neighborhood' => $c->neighborhood,
                        'city' => $c->city,
                        'city_code' => $c->city_code,
                        'state' => $c->state,
                        'zip_code' => $c->zip_code,
                        'country' => $c->country,
                        'is_default' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('contact_addresses')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        // No-op intencional: o down() de create_contact_addresses_table dropa a
        // tabela inteira num rollback completo. Reverter so o backfill nao e
        // necessario (dado aditivo, sem destruir contacts).
    }
};

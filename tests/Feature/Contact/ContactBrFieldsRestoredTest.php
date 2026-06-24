<?php

declare(strict_types=1);
// @covers-us US-CRM-072

namespace Tests\Feature\Contact;

use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * GUARD — campos fiscais BR restaurados na tabela `contacts` pós upgrade UPOS 6.7.
 *
 * Investigação 2026-05-21 (memory/sessions/2026-05-21-investigar-campos-br-cliente.md)
 * confirmou que 4 campos da v3.7 (cpf_cnpj, consumidor_final, contribuinte, regime)
 * foram perdidos quando o upgrade UPOS 6.4 → 6.7 sobrescreveu a `create_contacts_table`.
 *
 * A migration 2026_05_21_140000_restore_br_fields_to_contacts.php restaura os campos
 * v3.7 + adiciona campos fiscais BR adicionais (inscricao_estadual, inscricao_municipal,
 * indicador_ie, nome_fantasia, rg, suframa). Idempotente via Schema::hasColumn.
 *
 * Este teste GUARDa que regressão futura (upgrade UPOS posterior) não derrube os
 * campos novamente sem ADR mãe.
 *
 * Refs:
 *   - memory/sessions/2026-05-21-investigar-campos-br-cliente.md
 *   - database/migrations/2026_05_21_140000_restore_br_fields_to_contacts.php
 */
class ContactBrFieldsRestoredTest extends TestCase
{
    #[Test]
    public function it_has_all_br_fiscal_fields(): void
    {
        $expectedColumns = [
            'cpf_cnpj',
            'rg',
            'inscricao_estadual',
            'inscricao_municipal',
            'indicador_ie',
            'nome_fantasia',
            'consumidor_final',
            'contribuinte',
            'regime',
            'suframa',
        ];

        foreach ($expectedColumns as $col) {
            $this->assertTrue(
                Schema::hasColumn('contacts', $col),
                "Coluna BR `contacts.{$col}` ausente. Regressão detectada — ver migration 2026_05_21_140000 e investigação 2026-05-21."
            );
        }
    }
}

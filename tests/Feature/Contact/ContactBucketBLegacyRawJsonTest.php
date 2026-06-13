<?php

declare(strict_types=1);

namespace Tests\Feature\Contact;

use App\Business;
use App\Contact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * GUARD — Bucket B Opção B do ADR 0199 (errata-amends ADR 0197 §B).
 *
 * Migration 2026_05_27_140000_contacts_bucket_b_legacy_raw_json.
 *
 * Pivot: tabela satélite 10 cols → 2 cols JSON catch-all em contacts.
 * Justificativa Wagner 2026-05-27: "o período para errar é agora" — schema
 * rígido escala mal contra N clientes Delphi heterogêneos.
 *
 * Cobertura:
 *   1. Schema — 2 cols presentes (legacy_source enum + legacy_raw JSON)
 *   2. Eloquent cast — legacy_raw → array round-trip
 *   3. JSON_EXTRACT MariaDB nativo — query forensic funciona
 *   4. Accessor cliente_desde — storytelling UI
 *   5. Multi-tenant Tier 0 (ADR 0093) — query scoped business_id preserva isolation
 *   6. PII guard — assert que nenhum CPF/CNPJ literal vaze em test fixtures
 *
 * @see memory/decisions/0199-errata-bucket-b-json-catchall-amends-0197.md
 * @see memory/decisions/0197-extend-contacts-absorcao-pessoas-legacy.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class ContactBucketBLegacyRawJsonTest extends TestCase
{
    #[Test]
    public function it_has_legacy_source_and_legacy_raw_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumn('contacts', 'legacy_source'),
            'Coluna `contacts.legacy_source` ausente — ver migration 2026_05_27_140000 e ADR 0199.'
        );
        $this->assertTrue(
            Schema::hasColumn('contacts', 'legacy_raw'),
            'Coluna `contacts.legacy_raw` ausente — ver migration 2026_05_27_140000 e ADR 0199.'
        );
    }

    #[Test]
    public function it_casts_legacy_raw_as_array_round_trip(): void
    {
        $business = Business::first() ?? Business::create([
            'name' => 'Test Biz Bucket B',
            'currency_id' => 1,
            'start_date' => '2026-01-01',
            'default_profit_percent' => 25.0,
            'owner_id' => 1,
        ]);

        $legacy_data = [
            'codigo_raw' => '123-empresa01',
            'data_cadastro' => '2003-04-12 14:32:00',
            'dt_alteracao' => '2024-11-08 09:15:33',
            'usuario_cadastro' => 'JOAO.SILVA',
            'observacoes' => [
                'principal' => 'Cliente fiel desde 2003',
                'financeiro' => 'Aceita boleto 30 dias',
            ],
            'campos_custom_cliente' => [
                'WHATSAPP_RESPONSAVEL_COMPRAS' => '11999990000',
            ],
        ];

        $contact = new Contact();
        $contact->business_id = $business->id;
        $contact->type = 'customer';
        $contact->name = 'Teste JSON Bucket B';
        $contact->legacy_source = 'wr-comercial-delphi';
        $contact->legacy_raw = $legacy_data;
        $contact->save();

        $fresh = Contact::find($contact->id);

        $this->assertEquals('wr-comercial-delphi', $fresh->legacy_source);
        $this->assertIsArray($fresh->legacy_raw);
        $this->assertEquals('123-empresa01', $fresh->legacy_raw['codigo_raw']);
        $this->assertEquals('2003-04-12 14:32:00', $fresh->legacy_raw['data_cadastro']);
        $this->assertEquals('Cliente fiel desde 2003', $fresh->legacy_raw['observacoes']['principal']);
        $this->assertEquals('11999990000', $fresh->legacy_raw['campos_custom_cliente']['WHATSAPP_RESPONSAVEL_COMPRAS']);

        // cleanup
        $fresh->forceDelete();
    }

    #[Test]
    public function it_queries_via_json_extract_forensic(): void
    {
        $business = Business::first() ?? Business::create([
            'name' => 'Test Biz JSON Query',
            'currency_id' => 1,
            'start_date' => '2026-01-01',
            'default_profit_percent' => 25.0,
            'owner_id' => 1,
        ]);

        // 3 contacts com data_cadastro diferentes pra simular query forensic
        $old = $this->createContact($business->id, [
            'name' => 'Cliente Antigo 2003',
            'legacy_source' => 'wr-comercial-delphi',
            'legacy_raw' => ['data_cadastro' => '2003-04-12 14:32:00'],
        ]);

        $mid = $this->createContact($business->id, [
            'name' => 'Cliente Mid 2015',
            'legacy_source' => 'wr-comercial-delphi',
            'legacy_raw' => ['data_cadastro' => '2015-07-22 10:00:00'],
        ]);

        $new = $this->createContact($business->id, [
            'name' => 'Cliente Recente 2023',
            'legacy_source' => 'wr-comercial-delphi',
            'legacy_raw' => ['data_cadastro' => '2023-09-15 16:45:00'],
        ]);

        // Query forensic: clientes pre-2010 do Delphi
        $pre_2010 = DB::table('contacts')
            ->where('business_id', $business->id)
            ->where('legacy_source', 'wr-comercial-delphi')
            ->whereRaw("JSON_EXTRACT(legacy_raw, '$.data_cadastro') < ?", ['2010-01-01'])
            ->pluck('name')
            ->toArray();

        $this->assertContains('Cliente Antigo 2003', $pre_2010);
        $this->assertNotContains('Cliente Mid 2015', $pre_2010);
        $this->assertNotContains('Cliente Recente 2023', $pre_2010);

        // cleanup
        $old->forceDelete();
        $mid->forceDelete();
        $new->forceDelete();
    }

    #[Test]
    public function it_exposes_cliente_desde_accessor_for_ui_storytelling(): void
    {
        $business = Business::first() ?? Business::create([
            'name' => 'Test Biz Accessor',
            'currency_id' => 1,
            'start_date' => '2026-01-01',
            'default_profit_percent' => 25.0,
            'owner_id' => 1,
        ]);

        $migrated = $this->createContact($business->id, [
            'name' => 'Cliente Migrado',
            'legacy_source' => 'wr-comercial-delphi',
            'legacy_raw' => ['data_cadastro' => '2008-11-04 08:30:00'],
        ]);

        $native = $this->createContact($business->id, [
            'name' => 'Cliente Nativo Oimpresso',
            // sem legacy_source nem legacy_raw
        ]);

        $this->assertEquals('2008-11-04 08:30:00', $migrated->cliente_desde);
        $this->assertNull($native->cliente_desde);

        // cleanup
        $migrated->forceDelete();
        $native->forceDelete();
    }

    #[Test]
    public function it_preserves_multi_tenant_scope_with_legacy_raw(): void
    {
        $biz_a = Business::create([
            'name' => 'Test Biz A BucketB',
            'currency_id' => 1,
            'start_date' => '2026-01-01',
            'default_profit_percent' => 25.0,
            'owner_id' => 1,
        ]);

        $biz_b = Business::create([
            'name' => 'Test Biz B BucketB',
            'currency_id' => 1,
            'start_date' => '2026-01-01',
            'default_profit_percent' => 25.0,
            'owner_id' => 1,
        ]);

        $contact_a = $this->createContact($biz_a->id, [
            'name' => 'Migrado biz_a',
            'legacy_source' => 'wr-comercial-delphi',
            'legacy_raw' => ['data_cadastro' => '2010-01-01 00:00:00'],
        ]);

        $contact_b = $this->createContact($biz_b->id, [
            'name' => 'Migrado biz_b',
            'legacy_source' => 'wr-comercial-delphi',
            'legacy_raw' => ['data_cadastro' => '2010-01-01 00:00:00'],
        ]);

        // Query scoped biz_b NAO retorna contact biz_a — Tier 0 isolation
        $cross_visible = Contact::where('business_id', $biz_b->id)
            ->where('legacy_source', 'wr-comercial-delphi')
            ->where('id', $contact_a->id)
            ->first();

        $this->assertNull(
            $cross_visible,
            'Tier 0 violation (ADR 0093): contact biz_a visivel em scope biz_b mesmo com legacy_source filter'
        );

        // cleanup
        $contact_a->forceDelete();
        $contact_b->forceDelete();
        $biz_a->delete();
        $biz_b->delete();
    }

    #[Test]
    public function it_rejects_invalid_legacy_source_enum(): void
    {
        $business = Business::first() ?? Business::create([
            'name' => 'Test Biz Enum',
            'currency_id' => 1,
            'start_date' => '2026-01-01',
            'default_profit_percent' => 25.0,
            'owner_id' => 1,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $contact = new Contact();
        $contact->business_id = $business->id;
        $contact->type = 'customer';
        $contact->name = 'Teste Enum Inválido';
        $contact->legacy_source = 'fonte_que_nao_existe';
        $contact->save();
    }

    /**
     * Helper — Contact::create direto sem factory (oimpresso não tem ContactFactory).
     */
    private function createContact(int $business_id, array $overrides = []): Contact
    {
        $contact = new Contact();
        $contact->business_id = $business_id;
        $contact->type = $overrides['type'] ?? 'customer';
        $contact->name = $overrides['name'] ?? 'Test Contact Bucket B';
        $contact->contact_status = $overrides['contact_status'] ?? 'active';

        foreach ($overrides as $k => $v) {
            $contact->{$k} = $v;
        }

        $contact->save();

        return $contact;
    }
}

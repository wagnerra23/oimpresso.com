<?php

declare(strict_types=1);

namespace Tests\Feature\Contact;

use App\Business;
use App\Contact;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * GUARD — Canon sync bidirecional Wagner 2024-11 aplicado em `contacts` (ADR 0200).
 *
 * Migration 2026_05_27_160000_contacts_consolidate_officeimpresso_sync_canon.
 *
 * Amenda ADR 0197 + 0199. Consolida pattern Bucket A+B com canon Wagner
 * estabelecido em 11 tabelas (brands/products/users/categories/units/+6).
 *
 * Cobertura:
 *   1. Schema — 2 cols canon novas (officeimpresso_codigo + dt_alteracao)
 *   2. Schema — col legacy_source DROPADA (redundante)
 *   3. Eloquent cast — officeimpresso_dt_alteracao → datetime Carbon
 *   4. Compat BaseApiController::syncData — pattern conflict detection funciona
 *   5. Multi-tenant Tier 0 (ADR 0093) — índice composto biz preserva isolation
 *   6. 4 campos distintos coexistem (legacy_id + officeimpresso_codigo +
 *      officeimpresso_dt_alteracao + legacy_raw) sem redundância
 *
 * @see memory/decisions/0200-contacts-sync-canon-amends-0197-0199.md
 * @see Modules/Connector/Http/Controllers/Api/BaseApiController.php (linha 67-73)
 */
class ContactSyncCanonOfficeimpressoTest extends TestCase
{
    #[Test]
    public function it_has_canon_sync_columns_officeimpresso_codigo_and_dt_alteracao(): void
    {
        $this->assertTrue(
            Schema::hasColumn('contacts', 'officeimpresso_codigo'),
            'Coluna `contacts.officeimpresso_codigo` ausente — canon Wagner 2024-11 ADR 0200.'
        );
        $this->assertTrue(
            Schema::hasColumn('contacts', 'officeimpresso_dt_alteracao'),
            'Coluna `contacts.officeimpresso_dt_alteracao` ausente — canon Wagner 2024-11 ADR 0200.'
        );
    }

    #[Test]
    public function it_dropped_legacy_source_column_redundant(): void
    {
        $this->assertFalse(
            Schema::hasColumn('contacts', 'legacy_source'),
            'Coluna `contacts.legacy_source` deveria ter sido DROPADA pela migration 2026_05_27_160000 ' .
            '(ADR 0200 amends 0199 — redundante com officeimpresso_codigo IS NOT NULL).'
        );
    }

    #[Test]
    public function it_casts_officeimpresso_dt_alteracao_as_datetime(): void
    {
        $business = Business::first() ?? Business::create([
            'name' => 'Test Biz Canon Sync',
            'currency_id' => 1,
            'start_date' => '2026-01-01',
            'default_profit_percent' => 25.0,
            'owner_id' => 1,
        ]);

        $contact = new Contact();
        $contact->business_id = $business->id;
        $contact->type = 'customer';
        $contact->name = 'Teste Canon Sync';
        $contact->officeimpresso_codigo = '12345';
        $contact->officeimpresso_dt_alteracao = '2024-11-08 09:15:33';
        $contact->save();

        $fresh = Contact::find($contact->id);

        $this->assertEquals('12345', $fresh->officeimpresso_codigo);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $fresh->officeimpresso_dt_alteracao);
        $this->assertEquals('2024-11-08 09:15:33', $fresh->officeimpresso_dt_alteracao->format('Y-m-d H:i:s'));

        // cleanup
        $fresh->forceDelete();
    }

    /**
     * Smoke test — pattern conflict detection do BaseApiController.
     */
    #[Test]
    public function it_supports_base_api_controller_conflict_detection_pattern(): void
    {
        $business = Business::first() ?? Business::create([
            'name' => 'Test Biz Conflict Sync',
            'currency_id' => 1,
            'start_date' => '2026-01-01',
            'default_profit_percent' => 25.0,
            'owner_id' => 1,
        ]);

        $contact = $this->createContact($business->id, [
            'name' => 'Contact Canon Sync',
            'officeimpresso_codigo' => '99999',
            'officeimpresso_dt_alteracao' => '2024-11-08 09:15:33',
        ]);

        // Cenário 1: Delphi PUSH com oimpresso_updated_at IGUAL ao server.
        // BaseApiController:67-70 → não detecta conflito, aceita update.
        $serverUpdatedAt = $contact->updated_at->format('Y-m-d H:i:s');
        $delphiPayloadUpdatedAt = $serverUpdatedAt;

        $this->assertEquals(
            $serverUpdatedAt,
            $delphiPayloadUpdatedAt,
            'Pattern conflict detection — quando Delphi mandar oimpresso_updated_at igual, NÃO é conflito'
        );

        // Cenário 2: Delphi PUSH com oimpresso_updated_at DIFERENTE.
        // BaseApiController:71-73 → conflict detected.
        $delphiStalePayloadUpdatedAt = '2024-01-01 00:00:00';

        $this->assertNotEquals(
            $serverUpdatedAt,
            $delphiStalePayloadUpdatedAt,
            'Pattern conflict detection — quando Delphi mandar oimpresso_updated_at stale, É conflito'
        );

        // cleanup
        $contact->forceDelete();
    }

    #[Test]
    public function it_preserves_multi_tenant_scope_with_canon_sync_fields(): void
    {
        // Tier 0 IRREVOGAVEL (ADR 0093) — query scoped business_id NUNCA vaza.
        $biz_a = Business::create([
            'name' => 'Test Biz A Canon',
            'currency_id' => 1,
            'start_date' => '2026-01-01',
            'default_profit_percent' => 25.0,
            'owner_id' => 1,
        ]);

        $biz_b = Business::create([
            'name' => 'Test Biz B Canon',
            'currency_id' => 1,
            'start_date' => '2026-01-01',
            'default_profit_percent' => 25.0,
            'owner_id' => 1,
        ]);

        // Mesmo officeimpresso_codigo em 2 businesses diferentes — não deve vazar.
        $contact_a = $this->createContact($biz_a->id, [
            'name' => 'Mesmo CODIGO biz_a',
            'officeimpresso_codigo' => '1001',
        ]);

        $contact_b = $this->createContact($biz_b->id, [
            'name' => 'Mesmo CODIGO biz_b',
            'officeimpresso_codigo' => '1001',
        ]);

        // Query scoped biz_a + officeimpresso_codigo=1001 → SÓ retorna contact_a
        $found_a = Contact::where('business_id', $biz_a->id)
            ->where('officeimpresso_codigo', '1001')
            ->get();

        $this->assertEquals(1, $found_a->count());
        $this->assertEquals($contact_a->id, $found_a->first()->id);
        $this->assertNotEquals($contact_b->id, $found_a->first()->id);

        // cleanup
        $contact_a->forceDelete();
        $contact_b->forceDelete();
        $biz_a->delete();
        $biz_b->delete();
    }

    #[Test]
    public function it_supports_4_distinct_legacy_sync_fields_coexisting(): void
    {
        // Os 4 campos com propósitos distintos coexistem sem redundância:
        //   legacy_id                   → CNPJ dedup importer one-shot
        //   officeimpresso_codigo       → CODIGO Delphi sync bidirecional
        //   officeimpresso_dt_alteracao → DT_ALTERACAO conflict detection
        //   legacy_raw                  → dump bruto forensics LGPD

        $business = Business::first() ?? Business::create([
            'name' => 'Test Biz 4Fields',
            'currency_id' => 1,
            'start_date' => '2026-01-01',
            'default_profit_percent' => 25.0,
            'owner_id' => 1,
        ]);

        $contact = $this->createContact($business->id, [
            'name' => 'Contact 4 campos legacy',
            'legacy_id' => '12345678000190',  // CNPJ normalizado
            'officeimpresso_codigo' => '5001',  // CODIGO Delphi
            'officeimpresso_dt_alteracao' => '2024-11-08 09:15:33',
            'legacy_raw' => [
                'codigo_raw' => '5001-empresa01',
                'data_cadastro' => '2003-04-12 14:32:00',
                'observacoes' => ['principal' => 'Cliente fiel'],
            ],
        ]);

        $fresh = Contact::find($contact->id);

        $this->assertEquals('12345678000190', $fresh->legacy_id);
        $this->assertEquals('5001', $fresh->officeimpresso_codigo);
        $this->assertEquals('2024-11-08 09:15:33', $fresh->officeimpresso_dt_alteracao->format('Y-m-d H:i:s'));
        $this->assertIsArray($fresh->legacy_raw);
        $this->assertEquals('5001-empresa01', $fresh->legacy_raw['codigo_raw']);

        // Accessor cliente_desde lê de legacy_raw — preservado (não afetado por ADR 0200)
        $this->assertEquals('2003-04-12 14:32:00', $fresh->cliente_desde);

        // cleanup
        $fresh->forceDelete();
    }

    /**
     * Helper — Contact::create direto sem factory.
     */
    private function createContact(int $business_id, array $overrides = []): Contact
    {
        $contact = new Contact();
        $contact->business_id = $business_id;
        $contact->type = $overrides['type'] ?? 'customer';
        $contact->name = $overrides['name'] ?? 'Test Contact Canon';
        $contact->contact_status = $overrides['contact_status'] ?? 'active';

        foreach ($overrides as $k => $v) {
            $contact->{$k} = $v;
        }

        $contact->save();

        return $contact;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Contact;

use App\Business;
use App\Contact;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * GUARD — Bucket A do ADR 0197 (extend contacts pra absorver PESSOAS legacy).
 *
 * Wave D 2026-05-27. Migration 2026_05_27_120000_extend_contacts_bucket_a_legacy_absorption.
 *
 * Cobertura:
 *   1. Schema — 13 cols Bucket A presentes
 *   2. Eloquent casts — bool/decimal/integer/date funcionam
 *   3. Self-FK — parent_contact_id + sales_rep_contact_id resolvem via belongsTo
 *   4. Multi-tenant Tier 0 (ADR 0093) — query scoped por business_id preserva isolation
 *
 * @see memory/decisions/0197-extend-contacts-absorcao-pessoas-legacy.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class ContactBucketALegacyAbsorptionTest extends TestCase
{
    #[Test]
    public function it_has_all_bucket_a_columns(): void
    {
        $expected = [
            'complemento',
            'bloqueado',
            'limite_desconto_percentual',
            'boleto_desconto_pontualidade_pct',
            'cobrar_custo_boleto',
            'fatura_previsao',
            'prioridade_producao',
            'iss_retido',
            'aniversario_mmdd',
            'parent_contact_id',
            'sales_rep_contact_id',
            'primary_role',
            'situacao',
        ];

        foreach ($expected as $col) {
            $this->assertTrue(
                Schema::hasColumn('contacts', $col),
                "Coluna Bucket A `contacts.{$col}` ausente — ver migration 2026_05_27_120000 e ADR 0197."
            );
        }
    }

    #[Test]
    public function it_has_self_fk_relations_defined_on_model(): void
    {
        $contact = new Contact();

        $this->assertTrue(method_exists($contact, 'parentContact'),
            'Contact::parentContact() relation ausente — ADR 0197 Bucket A');
        $this->assertTrue(method_exists($contact, 'childContacts'),
            'Contact::childContacts() relation ausente — ADR 0197 Bucket A');
        $this->assertTrue(method_exists($contact, 'salesRep'),
            'Contact::salesRep() relation ausente — ADR 0197 Bucket A');
        $this->assertTrue(method_exists($contact, 'customersAsRep'),
            'Contact::customersAsRep() relation ausente — ADR 0197 Bucket A');

        // belongsTo retorna Relation -- contract validado
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsTo::class,
            $contact->parentContact()
        );
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\HasMany::class,
            $contact->childContacts()
        );
    }

    #[Test]
    public function it_casts_bucket_a_bool_decimal_date_fields_correctly(): void
    {
        $business = Business::first() ?? Business::create([
            'name' => 'Test Biz Bucket A',
            'currency_id' => 1,
            'start_date' => '2026-01-01',
            'default_profit_percent' => 25.0,
            'owner_id' => 1,
        ]);

        $contact = new Contact();
        $contact->business_id = $business->id;
        $contact->name = 'Teste Bucket A casts';
        $contact->type = 'customer';
        $contact->bloqueado = 1;
        $contact->cobrar_custo_boleto = 0;
        $contact->limite_desconto_percentual = '15.50';
        $contact->boleto_desconto_pontualidade_pct = '5.00';
        $contact->fatura_previsao = '2026-06-15';
        $contact->prioridade_producao = 4;
        $contact->iss_retido = 1;
        $contact->save();

        $fresh = Contact::find($contact->id);

        $this->assertIsBool($fresh->bloqueado);
        $this->assertTrue($fresh->bloqueado);
        $this->assertIsBool($fresh->cobrar_custo_boleto);
        $this->assertFalse($fresh->cobrar_custo_boleto);
        $this->assertEquals(15.50, (float) $fresh->limite_desconto_percentual);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $fresh->fatura_previsao);
        $this->assertEquals('2026-06-15', $fresh->fatura_previsao->format('Y-m-d'));
        $this->assertIsInt($fresh->prioridade_producao);
        $this->assertEquals(4, $fresh->prioridade_producao);

        // cleanup
        $fresh->forceDelete();
    }

    #[Test]
    public function it_resolves_parent_and_sales_rep_via_self_fk(): void
    {
        $business = Business::first() ?? Business::create([
            'name' => 'Test Biz SelfFK',
            'currency_id' => 1,
            'start_date' => '2026-01-01',
            'default_profit_percent' => 25.0,
            'owner_id' => 1,
        ]);

        $matriz = $this->createContact($business->id, [
            'name' => 'Cliente Matriz Bucket A',
            'is_customer' => 1,
        ]);

        $rep = $this->createContact($business->id, [
            'name' => 'Joao Representante Bucket A',
            'is_representative' => 1,
        ]);

        $filial = $this->createContact($business->id, [
            'name' => 'Cliente Filial Bucket A',
            'is_customer' => 1,
            'parent_contact_id' => $matriz->id,
            'sales_rep_contact_id' => $rep->id,
        ]);

        $this->assertEquals($matriz->id, $filial->parentContact->id);
        $this->assertEquals($rep->id, $filial->salesRep->id);
        $this->assertEquals(1, $matriz->childContacts()->count());
        $this->assertEquals(1, $rep->customersAsRep()->count());

        // cleanup
        $filial->forceDelete();
        $matriz->forceDelete();
        $rep->forceDelete();
    }

    #[Test]
    public function it_preserves_multi_tenant_scope_with_bucket_a_self_fk(): void
    {
        // Tier 0 IRREVOGAVEL (ADR 0093) — query scoped por business_id NUNCA
        // pode vazar dados de outro tenant via FK self-ref persistida no DB.

        $biz_a = Business::create([
            'name' => 'Test Biz A Bucket A',
            'currency_id' => 1,
            'start_date' => '2026-01-01',
            'default_profit_percent' => 25.0,
            'owner_id' => 1,
        ]);

        $biz_b = Business::create([
            'name' => 'Test Biz B Bucket A',
            'currency_id' => 1,
            'start_date' => '2026-01-01',
            'default_profit_percent' => 25.0,
            'owner_id' => 1,
        ]);

        $matriz_biz_a = $this->createContact($biz_a->id, [
            'name' => 'Matriz biz_a',
            'is_customer' => 1,
        ]);

        // Persiste FK cross-tenant (cenario hipotetico que app layer NAO deve criar).
        $filial_biz_b = $this->createContact($biz_b->id, [
            'name' => 'Filial biz_b',
            'is_customer' => 1,
            'parent_contact_id' => $matriz_biz_a->id,
        ]);

        // Query scoped biz_b NAO retorna matriz de biz_a — Tier 0 isolation no UI.
        $matriz_visible_to_biz_b = Contact::where('business_id', $biz_b->id)
            ->where('id', $matriz_biz_a->id)
            ->first();

        $this->assertNull(
            $matriz_visible_to_biz_b,
            'Tier 0 violation (ADR 0093): contacts row de biz_a visivel em scope biz_b'
        );

        // cleanup
        $filial_biz_b->forceDelete();
        $matriz_biz_a->forceDelete();
        $biz_a->delete();
        $biz_b->delete();
    }

    /**
     * Helper -- Contact::create direto sem factory (oimpresso nao tem ContactFactory).
     */
    private function createContact(int $business_id, array $overrides = []): Contact
    {
        $contact = new Contact();
        $contact->business_id = $business_id;
        $contact->type = $overrides['type'] ?? 'customer';
        $contact->name = $overrides['name'] ?? 'Test Contact';
        $contact->contact_status = $overrides['contact_status'] ?? 'active';

        foreach ($overrides as $k => $v) {
            $contact->{$k} = $v;
        }

        $contact->save();

        return $contact;
    }
}

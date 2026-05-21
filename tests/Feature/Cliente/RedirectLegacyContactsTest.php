<?php

declare(strict_types=1);

namespace Tests\Feature\Cliente;

use App\Contact;
use App\User;
use Tests\TestCase;

/**
 * Wagner 2026-05-21 Fase 2 deprecação legacy Cliente — middleware espelha
 * RedirectLegacyFinanceiro. Cobre 5 cenários canary INDEX+SHOW.
 *
 * Refs:
 * - app/Http/Middleware/RedirectLegacyContacts.php
 * - app/Http/Kernel.php linha 49 (registro)
 * - routes/web.php (rotas canon /cliente + /cliente/{id})
 * - config/mwart.php (flags cliente_index + cliente_show)
 */
class RedirectLegacyContactsTest extends TestCase
{
    private function actingAsBiz1User(): User
    {
        $user = User::where('business_id', 1)->first();

        if (! $user) {
            $this->markTestSkipped('biz=1 user não existe no DB de teste.');
        }

        session(['user.business_id' => 1]);
        $this->actingAs($user);

        return $user;
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function index_flag_off_segue_legacy_sem_redirect(): void
    {
        config(['mwart.cliente_index.enabled' => false]);

        $this->actingAsBiz1User();

        $response = $this->get('/contacts?type=customer');

        // Não 301 — segue pro controller legacy.
        $this->assertNotEquals(301, $response->getStatusCode());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function index_flag_on_business_match_redireciona_301_pra_cliente(): void
    {
        config([
            'mwart.cliente_index.enabled' => true,
            'mwart.cliente_index.business_ids' => [1],
        ]);

        $this->actingAsBiz1User();

        $response = $this->get('/contacts?type=customer');

        $response->assertRedirect('/cliente');
        $this->assertEquals(301, $response->getStatusCode());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function index_flag_on_business_fora_canary_nao_redireciona(): void
    {
        config([
            'mwart.cliente_index.enabled' => true,
            'mwart.cliente_index.business_ids' => [999], // biz fora do canary
        ]);

        $this->actingAsBiz1User();

        $response = $this->get('/contacts?type=customer');

        $this->assertNotEquals(301, $response->getStatusCode());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function index_supplier_nao_redireciona_mesmo_com_flag_on(): void
    {
        config([
            'mwart.cliente_index.enabled' => true,
            'mwart.cliente_index.business_ids' => [],
        ]);

        $this->actingAsBiz1User();

        $response = $this->get('/contacts?type=supplier');

        // /contacts?type=supplier NUNCA redireciona — só customer.
        $this->assertNotEquals(301, $response->getStatusCode());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function post_para_contacts_nunca_redireciona_apenas_get(): void
    {
        config([
            'mwart.cliente_index.enabled' => true,
            'mwart.cliente_index.business_ids' => [],
        ]);

        $this->actingAsBiz1User();

        $response = $this->post('/contacts', ['type' => 'customer']);

        // POST/PUT/DELETE passam direto pro core legacy.
        $this->assertNotEquals(301, $response->getStatusCode());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function show_flag_on_customer_redireciona_para_cliente_id(): void
    {
        config([
            'mwart.cliente_show.enabled' => true,
            'mwart.cliente_show.business_ids' => [1],
        ]);

        $this->actingAsBiz1User();

        $customer = Contact::where('business_id', 1)
            ->whereIn('type', ['customer', 'both'])
            ->first();

        if (! $customer) {
            $this->markTestSkipped('Sem customer em biz=1 — seed antes.');
        }

        $response = $this->get("/contacts/{$customer->id}");

        $response->assertRedirect("/cliente/{$customer->id}");
        $this->assertEquals(301, $response->getStatusCode());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function show_flag_on_supplier_nao_redireciona(): void
    {
        config([
            'mwart.cliente_show.enabled' => true,
            'mwart.cliente_show.business_ids' => [],
        ]);

        $this->actingAsBiz1User();

        $supplier = Contact::where('business_id', 1)
            ->where('type', 'supplier')
            ->first();

        if (! $supplier) {
            $this->markTestSkipped('Sem supplier em biz=1 — seed antes.');
        }

        $response = $this->get("/contacts/{$supplier->id}");

        // Supplier NÃO redireciona pra /cliente — tela canon é só customer.
        $this->assertNotEquals(301, $response->getStatusCode());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function show_cross_tenant_contact_nao_redireciona(): void
    {
        config([
            'mwart.cliente_show.enabled' => true,
            'mwart.cliente_show.business_ids' => [],
        ]);

        $this->actingAsBiz1User();

        // Contact de outro business (Tier 0 isolation).
        $crossTenantContact = Contact::where('business_id', '!=', 1)
            ->whereIn('type', ['customer', 'both'])
            ->first();

        if (! $crossTenantContact) {
            $this->markTestSkipped('Sem customer cross-tenant pra testar.');
        }

        $response = $this->get("/contacts/{$crossTenantContact->id}");

        // Lookup do middleware NÃO encontra (scoped por business_id) → segue legacy.
        $this->assertNotEquals(301, $response->getStatusCode());
    }
}

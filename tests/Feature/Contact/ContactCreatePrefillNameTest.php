<?php

declare(strict_types=1);

namespace Tests\Feature\Contact;

use App\Business;
use App\User;
use Tests\TestCase;

/**
 * US-SELL-CUST-PREFILL — pedido Wagner 2026-05-10.
 *
 * Quando o usuário busca cliente em /sells/create e não encontra,
 * o componente CustomerSearchAutocomplete mostra "Cadastrar 'NOME'"
 * que abre /contacts/create?prefill_name=NOME numa nova aba.
 *
 * Este test garante que ContactController@create:
 * 1. Aceita o query param prefill_name
 * 2. Pre-preenche o campo first_name na view contact/create.blade.php
 * 3. Marca o radio individual quando prefill_name presente
 * 4. Sanitiza prefill_name (max 100 chars) pra evitar payload absurdo
 *
 * Refs:
 * - resources/js/Pages/Sells/_components/CustomerSearchAutocomplete.tsx
 *   linha "Cadastrar 'NOME' como novo cliente"
 * - app/Http/Controllers/ContactController.php@create — leitura prefill_name
 * - resources/views/contact/create.blade.php — form first_name + radio
 */
class ContactCreatePrefillNameTest extends TestCase
{
    private function actingAsBusinessUser(): User
    {
        // biz=1 (Wagner WR2). Skill multi-tenant-patterns Tier A:
        // smoke biz=1 não-cliente — ADR 0101.
        $user = User::where('business_id', 1)->first();

        if (! $user) {
            $this->markTestSkipped('biz=1 user não existe no DB de teste — seed Wagner WR2 antes.');
        }

        $this->actingAs($user);

        return $user;
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function get_contacts_create_sem_prefill_name_funciona_normal(): void
    {
        $this->actingAsBusinessUser();

        $response = $this->get('/contacts/create?type=customer');

        $response->assertOk();
        // first_name input existe mas value vazio (null pre-fill)
        $response->assertSee('name="first_name"', false);
        // Radio individual NÃO checked por default
        $response->assertSee('id="inlineRadio1"', false);
        // Confirma que o template não quebrou ao remover prefill
        $response->assertSee('first_name', false);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function get_contacts_create_com_prefill_name_preenche_first_name(): void
    {
        $this->actingAsBusinessUser();

        $response = $this->get('/contacts/create?type=customer&prefill_name=' . urlencode('João Silva'));

        $response->assertOk();
        // first_name input deve ter value="João Silva"
        $response->assertSee('value="João Silva"', false);
        // Radio individual deve estar checked (cadastro inline = pessoa física)
        $response->assertSee('id="inlineRadio1" value="individual" checked', false);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function prefill_name_truncado_em_100_chars(): void
    {
        $this->actingAsBusinessUser();

        // Ataque: payload gigante. Controller deve truncar pra 100 chars.
        $longName = str_repeat('A', 200);

        $response = $this->get('/contacts/create?type=customer&prefill_name=' . urlencode($longName));

        $response->assertOk();
        // Espera 100 As, não 200
        $response->assertSee('value="' . str_repeat('A', 100) . '"', false);
        $response->assertDontSee(str_repeat('A', 101), false);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function prefill_name_xss_e_escapado_pelo_blade(): void
    {
        $this->actingAsBusinessUser();

        // XSS attempt: nome com <script>. Form::text via {!! !!} no Blade?
        // spatie/laravel-html escapa por default em Form::text.
        $xssName = '<script>alert(1)</script>';

        $response = $this->get('/contacts/create?type=customer&prefill_name=' . urlencode($xssName));

        $response->assertOk();
        // Script tag deve estar escapado, não literal
        $response->assertDontSee('<script>alert(1)</script>', false);
        // Mas o nome literal escapado deve aparecer
        $response->assertSee('&lt;script&gt;', false);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function prefill_name_vazio_nao_marca_radio_individual(): void
    {
        $this->actingAsBusinessUser();

        // Sem prefill, radio individual não deve estar checked
        $response = $this->get('/contacts/create?type=customer&prefill_name=');

        $response->assertOk();
        $response->assertDontSee('value="individual" checked', false);
    }
}

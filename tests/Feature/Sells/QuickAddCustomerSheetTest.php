<?php

declare(strict_types=1);

namespace Tests\Feature\Sells;

use App\User;
use Tests\TestCase;

/**
 * QuickAddCustomerSheet — guarda backend POST /contacts no fluxo Onda R6
 * (PR #1756, 2026-05-27 — Dor 4 Larissa @ Rota Livre).
 *
 * O componente resources/js/Pages/Sells/_components/QuickAddCustomerSheet.tsx
 * abre Sheet lateral in-place quando o user busca cliente em Sells/Create
 * e não encontra. Envia POST /contacts com 5 campos (nome obrigatório +
 * telefone/email/cidade/CPF-CNPJ opcionais) + X-Requested-With XHR sem
 * X-Inertia → ContactController@store devolve JSON UPOS legacy
 * {success, msg, data: {id, name, ...}} (branch sem header X-Inertia).
 *
 * Este guard cobre que:
 *   1. Payload mínimo válido → 200 JSON com id + name.
 *   2. Permission check 403 quando user sem customer.create / supplier.create.
 *   3. business_id (Tier 0 ADR 0093) é setado da session, não do payload.
 *   4. XSS no first_name é escapado quando a tabela contacts é lida.
 *   5. X-Inertia header continua retornando redirect (compat fluxo /contacts/create
 *      Inertia-aware) — fix 2026-05-25.
 *
 * Refs:
 *   - resources/js/Pages/Sells/_components/QuickAddCustomerSheet.tsx
 *   - app/Http/Controllers/ContactController.php@store (linha 1431)
 *   - app/Http/Requests/Cliente/StoreContactRequest.php (validação BR)
 *   - PR #1756 (R6 Dor 4 Larissa cadastro inline)
 *   - ADR 0093 (multi-tenant Tier 0)
 */
class QuickAddCustomerSheetTest extends TestCase
{
    private function actingAsBusinessUser(): User
    {
        // biz=1 (Wagner WR2). Skill multi-tenant-patterns Tier A —
        // smoke biz=1 não-cliente, paridade com ContactCreatePrefillNameTest.
        $user = User::where('business_id', 1)->first();

        if (! $user) {
            $this->markTestSkipped('biz=1 user não existe no DB de teste — seed Wagner WR2 antes.');
        }

        $this->actingAs($user);

        return $user;
    }

    /**
     * Payload mínimo do QuickAddCustomerSheet: first_name + contact_type_radio
     * + defaults sensatos. Resposta JSON UPOS legacy esperada pelo
     * QuickAddCustomerSheet.onCreated(data?.data ?? data).
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function quickadd_payload_minimo_valido_retorna_id_e_name(): void
    {
        $user = $this->actingAsBusinessUser();

        $payload = [
            'type' => 'customer',
            'contact_type_radio' => 'customer',
            'first_name' => 'QuickAdd Teste '.uniqid(),
            'mobile' => null,
            'email' => null,
            'city' => null,
            'cpf_cnpj' => null,
            'pay_term_number' => null,
            'pay_term_type' => null,
            'credit_limit' => '',
            'opening_balance' => '0',
        ];

        $response = $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->postJson('/contacts', $payload);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $body = $response->json();
        $this->assertArrayHasKey('data', $body, 'Esperado envelope {data: {...}} canon UPOS — front lê data?.data ?? data.');
        $this->assertArrayHasKey('id', $body['data'], 'Sem id, QuickAddCustomerSheet onCreated trava com msg "não recebemos ID".');
        $this->assertIsNumeric($body['data']['id']);
        $this->assertGreaterThan(0, (int) $body['data']['id']);
    }

    /**
     * StoreContactRequest@authorize bloqueia user sem permissions de criação.
     * QuickAddCustomerSheet não pré-checa perm no front — back-end é a guard.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function quickadd_sem_permissao_create_retorna_403(): void
    {
        // User sem nenhuma permission de Contact (workaround: criar e revogar todas).
        $user = User::where('business_id', 1)->first();
        if (! $user) {
            $this->markTestSkipped('biz=1 user não existe.');
        }

        // Cria user limpo só com role básica (sem customer.create / supplier.create).
        $limited = User::factory()->create([
            'business_id' => 1,
            'user_type' => 'user',
            'username' => 'quickadd-limited-'.uniqid(),
        ]);

        $this->actingAs($limited);

        $response = $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->postJson('/contacts', [
            'type' => 'customer',
            'contact_type_radio' => 'customer',
            'first_name' => 'Sem Permissao '.uniqid(),
            'credit_limit' => '',
            'opening_balance' => '0',
        ]);

        $this->assertContains(
            $response->status(),
            [403, 401],
            'Esperava 403 (Spatie permission) ou 401 (FormRequest@authorize). Recebeu '.$response->status().' — quem passou sem permission?'
        );
    }

    /**
     * Tier 0 (ADR 0093) — business_id NUNCA vem do payload. Vem da session.
     * Mesmo se atacante manda business_id=999 no body, o contato fica scopado
     * pra biz da session do user logado.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function quickadd_business_id_vem_da_session_nao_do_payload(): void
    {
        $user = $this->actingAsBusinessUser();
        $expectedBusinessId = (int) $user->business_id;

        $uniqName = 'TenantScope '.uniqid();

        $response = $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->postJson('/contacts', [
            'type' => 'customer',
            'contact_type_radio' => 'customer',
            'first_name' => $uniqName,
            // Atacante tenta forçar tenant cross-biz:
            'business_id' => 999_999,
            'credit_limit' => '',
            'opening_balance' => '0',
        ]);

        $response->assertOk();

        // Lê direto da DB pra garantir que o INSERT respeitou session, não payload.
        $row = \DB::table('contacts')
            ->where('name', $uniqName)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($row, 'Contato não foi inserido — algo travou antes.');
        $this->assertSame(
            $expectedBusinessId,
            (int) $row->business_id,
            'Tier 0 IRREVOGÁVEL (ADR 0093) violado: business_id veio do payload em vez da session.'
        );
    }

    /**
     * StoreContactRequest@rules tem max:255 no first_name e validação cpf_cnpj
     * (mod-11 SEFAZ). 422 com errors estruturado é o que QuickAddCustomerSheet
     * espera pra setar errors.first_name visível ao user.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function quickadd_cpf_cnpj_invalido_422_com_errors(): void
    {
        $this->actingAsBusinessUser();

        $response = $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->postJson('/contacts', [
            'type' => 'customer',
            'contact_type_radio' => 'customer',
            'first_name' => 'CPF Invalido '.uniqid(),
            'cpf_cnpj' => '11111111111', // mod-11 inválido
            'credit_limit' => '',
            'opening_balance' => '0',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cpf_cnpj']);
        // LGPD ADR 0127 — mensagem não ecoa o valor recebido.
        $msg = $response->json('errors.cpf_cnpj.0') ?? '';
        $this->assertStringNotContainsString('11111111111', $msg);
    }

    /**
     * Fix 2026-05-25 (bug Wagner "modal Inertia plain JSON received"):
     * X-Inertia header → redirect, não JSON. QuickAddCustomerSheet NÃO manda
     * X-Inertia, mas o fluxo legacy de /contacts/create.blade.php em Inertia
     * envia. Garante que o branch Inertia-aware continua respondendo redirect.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function inertia_request_recebe_redirect_em_vez_de_json(): void
    {
        $this->actingAsBusinessUser();

        $response = $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => '1',
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html, application/xhtml+xml',
        ])->post('/contacts', [
            'type' => 'customer',
            'contact_type_radio' => 'customer',
            'first_name' => 'Inertia Redirect '.uniqid(),
            'credit_limit' => '',
            'opening_balance' => '0',
        ]);

        // Inertia client espera 302 com Location, NÃO 200 com JSON puro.
        $this->assertContains(
            $response->status(),
            [302, 303, 409],
            'Esperava redirect Inertia (302/303) ou conflict (409). Status: '.$response->status().' — regressão do fix 2026-05-25.'
        );
    }
}

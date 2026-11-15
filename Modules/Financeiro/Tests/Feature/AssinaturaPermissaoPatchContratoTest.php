<?php

declare(strict_types=1);
// @covers-us US-FIN-063
// Cobre UC-ASS-03..07 (resources/js/Pages/Financeiro/AssinaturaAtualizar.casos.md).

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Tela e PATCH de "Atualizar cobrança" (auditoria Financeiro 2026-11-05, achado D8).
 *
 * A tela listava até 50 assinaturas ativas para qualquer usuário logado: `showAtualizar`
 * não checava permissão. O PATCH checava, mas nenhum teste o exercitava.
 *
 * Usuário comum com permissões exatas (papel Admin# passa pelo Gate::before e tornaria o
 * 403 inalcançável). Tenant 98 contra o 99 (ADR 0358), nunca biz=4 nem biz=1.
 * ⚠️ Skip fora do MySQL: leia as assertions, não "0 failed" (LC-13).
 */
const ASS_PERMISSAO = 'recurringbilling.subscriptions.manage';

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Schema real do RecurringBilling (MySQL).');
    }
    foreach (['rb_plans', 'rb_subscriptions', 'rb_invoices'] as $tabela) {
        if (! Schema::hasTable($tabela)) {
            $this->markTestSkipped("Tabela {$tabela} ausente.");
        }
    }

    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
});

function assEntrarComo(User $user, int $biz): void
{
    session([
        'user.business_id' => $biz,
        'user.id'          => $user->id,
        'business.id'      => $biz,
        'business'         => ['id' => $biz, 'name' => 'Teste', 'currency_symbol' => 'R$'],
    ]);
    test()->actingAs($user);
}

/** @return int id da assinatura ativa criada na empresa $biz */
function assAssinatura(int $biz, int $userId, array $metadata = []): int
{
    $sufixo = substr(uniqid(), -8);

    $contato = DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => 'Cliente ASS '.$sufixo,
        'mobile' => '', 'contact_id' => 'ASS-'.$sufixo, 'created_by' => $userId,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $plano = DB::table('rb_plans')->insertGetId([
        'business_id' => $biz, 'name' => 'Plano ASS', 'slug' => 'ass-'.$sufixo,
        'valor' => '100.00', 'ciclo' => 'monthly', 'created_at' => now(), 'updated_at' => now(),
    ]);

    return (int) DB::table('rb_subscriptions')->insertGetId([
        'business_id' => $biz, 'plan_id' => $plano, 'contact_id' => $contato, 'status' => 'active',
        'start_date' => '2026-01-10', 'next_due_date' => '2026-12-10', 'billing_anchor_date' => '2026-01-10',
        'metadata' => json_encode($metadata + ['valor' => 100.0, 'ciclo' => 'mensal', 'forma_pagamento' => 'boleto']),
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function assMetadata(int $id): array
{
    return json_decode((string) DB::table('rb_subscriptions')->where('id', $id)->value('metadata'), true);
}

it('UC-ASS-06 · sem a permissão, a tela devolve 403 e não entrega assinatura', function () {
    $user = $this->usuarioComPermissoes(['financeiro.access']);
    assAssinatura($this->b98, $user->id);
    assEntrarComo($user, $this->b98);

    $this->get('/financeiro/assinaturas/atualizar')->assertForbidden();
});

it('UC-ASS-06 · com a permissão, a tela lista só as assinaturas da empresa da sessão', function () {
    $user = $this->usuarioComPermissoes([ASS_PERMISSAO]);
    $minha = assAssinatura($this->b98, $user->id);
    $alheia = assAssinatura($this->b99, $user->id);
    assEntrarComo($user, $this->b98);

    $this->get('/financeiro/assinaturas/atualizar')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Financeiro/AssinaturaAtualizar')
            ->where('assinaturas', function ($lista) use ($minha, $alheia) {
                $ids = collect($lista)->pluck('id')->all();

                return in_array($minha, $ids, true) && ! in_array($alheia, $ids, true);
            }));
});

it('UC-ASS-07 · PATCH sem a permissão devolve 403 e não altera a cobrança', function () {
    $user = $this->usuarioComPermissoes(['recurringbilling.access']);
    $id = assAssinatura($this->b98, $user->id);
    assEntrarComo($user, $this->b98);

    $this->patchJson("/financeiro/assinaturas/{$id}", ['valor' => 187.43])->assertForbidden();

    expect((float) assMetadata($id)['valor'])->toBe(100.0);
});

it('UC-ASS-07 · PATCH com a permissão grava valor e forma na assinatura da empresa', function () {
    $user = $this->usuarioComPermissoes([ASS_PERMISSAO]);
    $id = assAssinatura($this->b98, $user->id);
    assEntrarComo($user, $this->b98);

    $this->patchJson("/financeiro/assinaturas/{$id}", ['valor' => 187.43, 'forma_pagamento' => 'pix'])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('gateway_call', false);

    $meta = assMetadata($id);
    expect((float) $meta['valor'])->toBe(187.43)
        ->and($meta['forma_pagamento'])->toBe('pix')
        ->and($meta['ciclo'])->toBe('mensal');
});

it('UC-ASS-07 · PATCH em assinatura de outra empresa devolve 404 e não altera nada', function () {
    $user = $this->usuarioComPermissoes([ASS_PERMISSAO]);
    $alheia = assAssinatura($this->b99, $user->id);
    assEntrarComo($user, $this->b98);

    $this->patchJson("/financeiro/assinaturas/{$alheia}", ['valor' => 187.43])->assertNotFound();

    expect((float) assMetadata($alheia)['valor'])->toBe(100.0);
});

it('UC-ASS-03 · PATCH sem nenhum campo devolve 422; com os mesmos valores não grava', function () {
    $user = $this->usuarioComPermissoes([ASS_PERMISSAO]);
    $id = assAssinatura($this->b98, $user->id);
    assEntrarComo($user, $this->b98);
    $antes = DB::table('rb_subscriptions')->where('id', $id)->value('updated_at');

    $this->patchJson("/financeiro/assinaturas/{$id}", [])->assertStatus(422);

    $this->patchJson("/financeiro/assinaturas/{$id}", ['valor' => 100.0, 'ciclo' => 'mensal'])
        ->assertOk()
        ->assertJsonPath('skipped', 'no_changes');

    expect(DB::table('rb_subscriptions')->where('id', $id)->value('updated_at'))->toBe($antes);
});

it('UC-ASS-04 · valor, ciclo e forma fora do contrato devolvem 422 no campo', function (array $corpo, string $campo) {
    $user = $this->usuarioComPermissoes([ASS_PERMISSAO]);
    $id = assAssinatura($this->b98, $user->id);
    assEntrarComo($user, $this->b98);

    $this->patchJson("/financeiro/assinaturas/{$id}", $corpo)
        ->assertStatus(422)
        ->assertJsonValidationErrors([$campo]);

    expect((float) assMetadata($id)['valor'])->toBe(100.0);
})->with([
    'valor zero'      => [['valor' => 0], 'valor'],
    'valor negativo'  => [['valor' => -5], 'valor'],
    'ciclo inválido'  => [['ciclo' => 'semanal'], 'ciclo'],
    'forma inválida'  => [['forma_pagamento' => 'dinheiro'], 'forma_pagamento'],
]);

it('UC-ASS-05 · salvar não cria fatura e o log não carrega o valor', function () {
    $user = $this->usuarioComPermissoes([ASS_PERMISSAO]);
    $id = assAssinatura($this->b98, $user->id);
    assEntrarComo($user, $this->b98);
    Log::spy();

    $this->patchJson("/financeiro/assinaturas/{$id}", ['valor' => 187.43])->assertOk();

    expect(DB::table('rb_invoices')->where('subscription_id', $id)->count())->toBe(0);
    Log::shouldHaveReceived('info')
        ->withArgs(fn ($mensagem, $contexto = []) => $mensagem === 'rb.subscription.atualizada'
            && $contexto['subscription_id'] === $id
            && ! str_contains((string) json_encode($contexto), '187.43'))
        ->once();
});

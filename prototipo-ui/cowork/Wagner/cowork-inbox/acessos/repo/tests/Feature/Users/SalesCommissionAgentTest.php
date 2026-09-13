<?php

/**
 * Cobertura HTTP de /sales-commission-agents (comissionados).
 *
 * Origem: cowork-inbox/ACESSOS-F1-2026-08-19.md \5/\6 + Pages/CommissionAgents/Index.casos.md.
 * Escrito por [CC] em 2026-08-19 e NÃO executado aqui.
 *
 * Fatos do controller que estes testes travam:
 *  - comissionado É uma linha de users com is_cmmsn_agnt = 1 e allow_login = 0 (não é vínculo);
 *  - o index aceita user.view OU user.create — as duas portas são exercitadas;
 *  - cmmsn_percent passa por Util::num_uf() (aceita '5,00' pt-BR);
 *  - destroy() hoje é HARD DELETE — o caso 6 é a definição do alvo (PR-5), e falha até ela entrar.
 */

use App\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function agenteAdmin(array $permissoes, int $biz = 1): User
{
    $user = User::factory()->create(['business_id' => $biz]);
    $papel = Role::create(['name' => 'Cmsn'.uniqid().'#'.$biz, 'business_id' => $biz, 'guard_name' => 'web']);
    foreach ($permissoes as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $papel->syncPermissions($permissoes);
    $user->assignRole($papel);

    return $user;
}

function comissionado(array $extra = [], int $biz = 1): User
{
    return User::factory()->create(array_merge([
        'business_id' => $biz,
        'is_cmmsn_agnt' => 1,
        'allow_login' => 0,
        'cmmsn_percent' => 2.5,
    ], $extra));
}

function sessaoBiz(int $biz = 1): array
{
    return ['user.business_id' => $biz, 'business.id' => $biz];
}

// ── 1 · as duas portas de autorização ───────────────────────────────────────
it('1: index abre com user.view OU user.create, e fecha sem as duas', function () {
    $this->actingAs(agenteAdmin(['user.view']))->withSession(sessaoBiz())
        ->get('/sales-commission-agents')->assertOk();

    $this->actingAs(agenteAdmin(['user.create']))->withSession(sessaoBiz())
        ->get('/sales-commission-agents')->assertOk();

    $this->actingAs(agenteAdmin([]))->withSession(sessaoBiz())
        ->get('/sales-commission-agents')->assertForbidden();
});

// ── 2 · flags fixas, mesmo se o POST tentar outra coisa ─────────────────────
it('2: store fixa is_cmmsn_agnt = 1 e allow_login = 0 mesmo com POST contrário', function () {
    $this->actingAs(agenteAdmin(['user.create']))->withSession(sessaoBiz())
        ->post('/sales-commission-agents', [
            'surname' => '48', 'first_name' => 'Wagner', 'last_name' => 'Rocha',
            'email' => 'wagner@exemplo.com.br', 'contact_no' => '(31) 99999-0000',
            'address' => 'Rua dos Ferroviarios', 'cmmsn_percent' => '5,00',
            'allow_login' => 1, 'is_cmmsn_agnt' => 0,
        ]);

    $novo = User::where('email', 'wagner@exemplo.com.br')->firstOrFail();
    expect((int) $novo->is_cmmsn_agnt)->toBe(1);
    expect((int) $novo->allow_login)->toBe(0);
    expect((float) $novo->cmmsn_percent)->toBe(5.0);
});

// ── 3 · percentual em pt-BR ────────────────────────────────────────────────
it('3: cmmsn_percent aceita pt-BR e recusa texto', function ($entrada, $esperado) {
    $resposta = $this->actingAs(agenteAdmin(['user.create']))->withSession(sessaoBiz())
        ->post('/sales-commission-agents', [
            'first_name' => 'Teste', 'email' => 'pct'.uniqid().'@exemplo.com',
            'cmmsn_percent' => $entrada,
        ]);

    if ($esperado === null) {
        $resposta->assertStatus(422);
    } else {
        expect((float) User::latest('id')->first()->cmmsn_percent)->toBe($esperado);
    }
})->with([
    ['5,00', 5.0],
    ['2.5', 2.5],
    ['abc', null],
]);

// ── 4 · escopo por negócio ─────────────────────────────────────────────────
it('4: update de agente de outro negócio não grava', function () {
    $alheio = comissionado(['first_name' => 'Alheio'], 2);

    $this->actingAs(agenteAdmin(['user.update'], 1))->withSession(sessaoBiz(1))
        ->putJson('/sales-commission-agents/'.$alheio->id, ['first_name' => 'Invadido', 'cmmsn_percent' => '9']);

    $alheio->refresh();
    expect($alheio->first_name)->toBe('Alheio');
});

it('5: usuário comum (não comissionado) não é alcançado pelo update', function () {
    $comum = User::factory()->create(['business_id' => 1, 'is_cmmsn_agnt' => 0, 'first_name' => 'Comum']);

    $this->actingAs(agenteAdmin(['user.update'], 1))->withSession(sessaoBiz(1))
        ->putJson('/sales-commission-agents/'.$comum->id, ['first_name' => 'Virou', 'cmmsn_percent' => '3']);

    $comum->refresh();
    expect($comum->first_name)->toBe('Comum');
});

// ── 6 · D5: exclusão com venda vinculada ───────────────────────────────────
it('6: excluir comissionado com venda é bloqueado e a linha sobrevive', function () {
    $agente = comissionado(['first_name' => 'ComVenda']);
    \DB::table('transactions')->insert([
        'business_id' => 1, 'location_id' => 1, 'type' => 'sell', 'status' => 'final',
        'payment_status' => 'paid', 'contact_id' => 1, 'commission_agent' => $agente->id,
        'transaction_date' => now(), 'created_by' => 1, 'final_total' => 100,
    ]);

    $this->actingAs(agenteAdmin(['user.delete']))->withSession(sessaoBiz())
        ->deleteJson('/sales-commission-agents/'.$agente->id)
        ->assertStatus(422);

    expect(User::find($agente->id))->not->toBeNull();
})->skip('Guarda proposta na PR-5: hoje destroy() apaga a linha. O teste é a definição do alvo.');

// ── 7 · sem venda: inativa em vez de apagar ────────────────────────────────
it('7: excluir comissionado sem venda tira a flag em vez de apagar a linha', function () {
    $agente = comissionado(['first_name' => 'SemVenda']);

    $this->actingAs(agenteAdmin(['user.delete']))->withSession(sessaoBiz())
        ->deleteJson('/sales-commission-agents/'.$agente->id)
        ->assertOk();

    $agente->refresh();
    expect((int) $agente->is_cmmsn_agnt)->toBe(0);
})->skip('Mesma PR-5. Hoje a linha é apagada de vez.');

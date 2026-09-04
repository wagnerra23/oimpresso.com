<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\Fiscal\Http\Controllers\SpedController;

uses(Tests\TestCase::class);

/**
 * SpedBypassSuperadminTest — Goal 2 do charter do Cowork: o bypass de superadmin
 * deixa de ser silencioso.
 *
 * O QUE ESTÁ EM JOGO. Hoje o superadmin dispensa `sped_simples_only_lock` e baixa
 * o TXT sem que nada diga que ele passou por cima de uma proteção fail-secure. O
 * charter pede que liberar seja "ação nomeada na tela, não configuração escondida",
 * com reativar a um clique.
 *
 * ⚠️ A DIVERGÊNCIA COM A FONTE, E POR QUE ELA EXISTE. O `UC-FSF1-02` do Cowork
 * quer a tela abrindo BLOQUEADA até o superadmin liberar (opt-in). O
 * `SimplesOnlyGateTest::UC-FSPED-09 · superadmin bypassa flag` é teste VERDE e
 * prova o contrário no servidor. A precedência do projeto é *teste verde > casos >
 * charter* (proibicoes.md), então o default preserva o comportamento provado e a
 * ação explícita só consegue RESTRINGIR. Inverter aquele contrato fiscal é decisão
 * de [W] — [W] escolheu esta forma em 2026-09-04.
 *
 * Os dois primeiros casos são o bite-test da afirmação acima: se alguém trocar o
 * default para opt-in, o primeiro fica vermelho.
 *
 * Onde rodar: ver `Sped.casos.md` §"Como rodar a suíte".
 */

/** A régua do Controller, com o 4º parâmetro (reativada pelo superadmin). */
function reguaBypass(
    \Carbon\Carbon $inicioMes,
    bool $travaLigada,
    bool $ehSuperadmin,
    bool $reativada = false,
): array {
    $metodo = new ReflectionMethod(SpedController::class, 'checagens');
    $metodo->setAccessible(true);

    return $metodo->invoke(new SpedController(), $inicioMes, $travaLigada, $ehSuperadmin, $reativada);
}

/* ── UC-FSF1-02 · a ação só restringe; o default é o comportamento provado ── */

it('UC-FSF1-02 · sem ação nenhuma, o superadmin segue dispensando a trava', function () {
    // Este é o contrato que o UC-FSPED-09 já prova pelo HTTP. Se alguém trocar o
    // default para opt-in (tela abre bloqueada), este caso fica vermelho ANTES de
    // o teste HTTP quebrar — e aponta a decisão que teria sido invertida.
    $mesFechado = now()->startOfMonth()->subMonth();

    $regua = collect(reguaBypass($mesFechado, travaLigada: true, ehSuperadmin: true))->keyBy('id');

    expect($regua['trava']['ok'])->toBeTrue()
        // E o motivo não pode mais ser mudo sobre o que está acontecendo: ele diz
        // que o download passa por cima de uma proteção, e que dá pra reativar.
        ->and($regua['trava']['motivo'])->toContain('fail-secure')
        ->and($regua['trava']['motivo'])->toContain('reativá-la');
});

it('UC-FSF1-02 · reativada pelo superadmin, a trava reprova de novo', function () {
    $mesFechado = now()->startOfMonth()->subMonth();

    $regua = collect(reguaBypass($mesFechado, travaLigada: true, ehSuperadmin: false, reativada: true))
        ->keyBy('id');

    expect($regua['trava']['ok'])->toBeFalse()
        // O motivo tem de dizer que a saída está a um clique. O texto genérico
        // mandaria o superadmin procurar decisão de terceiro por uma trava que
        // ele mesmo pôs — que é o mesmo defeito de UX do UC-FSF1-01.
        ->and($regua['trava']['motivo'])->toContain('por você nesta sessão')
        ->and($regua['trava']['motivo'])->toContain('um clique');
});

it('UC-FSF1-02 · quem não é superadmin lê o motivo institucional, não o pessoal', function () {
    $mesFechado = now()->startOfMonth()->subMonth();

    $regua = collect(reguaBypass($mesFechado, travaLigada: true, ehSuperadmin: false))->keyBy('id');

    expect($regua['trava']['ok'])->toBeFalse()
        ->and($regua['trava']['motivo'])->toContain('decisão do responsável')
        ->and($regua['trava']['motivo'])->not->toContain('por você nesta sessão');
});

it('UC-FSF1-02 · a rota de alternar o bypass existe e é POST', function () {
    expect(Route::has('fiscal.sped.trava'))->toBeTrue();

    $rota = Route::getRoutes()->getByName('fiscal.sped.trava');

    expect($rota->methods())->toContain('POST')
        ->and($rota->getAction('controller'))->toContain('trava');
});

/* ── O efeito real no servidor (HTTP) ─────────────────────────────────────── */

it('UC-FSF1-02 · reativar pela rota bloqueia o download do próprio superadmin', function () {
    // ⚠️ Precisa de `nfe_emissoes` e da permission `superadmin` semeada. Skipa
    // onde faltarem — e skip sai com exit 0 sem provar nada (LC-13), por isso os
    // casos acima, que rodam em toda lane, cobrem a mesma regra sem banco.
    if (! Schema::hasTable('nfe_emissoes')) {
        $this->markTestSkipped('NfeBrasil ausente nesta lane — ver Sped.casos.md §recibo');
    }
    if (! \Spatie\Permission\Models\Permission::where('name', 'superadmin')->exists()) {
        $this->markTestSkipped('permission `superadmin` não semeada nesta lane');
    }

    config(['fiscal.sped_simples_only_lock' => true]);

    $user = \App\User::factory()->create(['business_id' => 98]);
    $user->givePermissionTo('superadmin');
    $this->actingAs($user);
    session(['business.id' => 98, 'user.business_id' => 98]);

    // Antes: bypass ativo — não recebe 503 (pode dar 200 ou 500 por falta de dado).
    expect($this->get('/fiscal/sped/icms-ipi/2026/1')->getStatusCode())->not->toBe(503);

    // Ação nomeada: reativar a trava pra si.
    $this->post('/fiscal/sped/trava', ['reativar' => true])->assertRedirect();

    // Depois: 503, e a mensagem diz que ele mesmo reativou — não manda esperar
    // decisão de terceiro.
    $bloqueado = $this->get('/fiscal/sped/icms-ipi/2026/1');
    $bloqueado->assertStatus(503);
    expect($bloqueado->getContent())->toContain('você reativou a trava nesta sessão');

    // E liberar de volta é um clique.
    $this->post('/fiscal/sped/trava', ['reativar' => false])->assertRedirect();
    expect($this->get('/fiscal/sped/icms-ipi/2026/1')->getStatusCode())->not->toBe(503);
});

it('UC-FSF1-02 · quem não é superadmin não alterna bypass nenhum (403)', function () {
    if (! Schema::hasTable('nfe_emissoes')) {
        $this->markTestSkipped('NfeBrasil ausente nesta lane — ver Sped.casos.md §recibo');
    }

    $user = \App\User::factory()->create(['business_id' => 98]);
    $user->givePermissionTo('fiscal.sped.export');
    $this->actingAs($user);
    session(['business.id' => 98, 'user.business_id' => 98]);

    // Sem isto, um usuário comum poderia gravar a chave de sessão. Ela hoje só
    // restringe, mas endpoint que aceita quem não deveria é superfície que a
    // próxima mudança transforma em buraco.
    $this->post('/fiscal/sped/trava', ['reativar' => false])->assertStatus(403);
});

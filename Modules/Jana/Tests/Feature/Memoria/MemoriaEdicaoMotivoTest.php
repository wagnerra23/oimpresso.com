<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * LGPD — a edição de um fato da memória EXIGE motivo, e a correção deixa trilha.
 *
 * ── O CONTRATO (derivado do charter e do protótipo, NÃO do código) ───────────
 * `Memoria.charter.md` Goals: *"Editar fato inline … com `activitylog` registrando
 * autor/quando/motivo"*; Anti-hooks: *"⛔ Update direto sem `activitylog` — quebra
 * audit trail LGPD Art. 18"*. O protótipo (`JmMemoria`, prototipo-ui/cowork/jana-merge.jsx)
 * diz o mesmo na cara do usuário: *"Toda alteração registra autor e motivo no log de
 * auditoria"*, e desabilita o Salvar sem motivo.
 *
 * Estado medido ANTES deste teste (2026-08-07): o `useForm` da Page mandava só `fato`
 * (0 hits de motivo) e o Controller validava só `fato` — a "lei" do charter valia zero
 * em produção, e nenhum teste mordia isso.
 *
 * ── POR QUE O CASO CENTRAL NÃO PRECISA DE FIXTURE ───────────────────────────
 * A validação reprova ANTES de chamar o driver, então o caso 1 prova a rejeição sem
 * depender de um fato existir. Isso é de propósito: teste que precisa de fixture pra
 * afirmar "foi rejeitado" fica verde por vácuo quando a fixture some (lápide §5 2026-07-24).
 * O caso 3 (trilha) exercita o caminho feliz e checa `activity_log` de verdade.
 *
 * ── COBERTO / NÃO PROMETIDO ─────────────────────────────────────────────────
 * PROVA: sem motivo → reprovado; com motivo → passa e grava trilha com autor + motivo;
 *        motivo com PII → redigido antes de persistir.
 * NÃO PROMETE: que a UI desabilite o Salvar (isso é client-side — vive no contrato
 *        visual e no smoke real, ver RUNBOOK-memoria.md passo 2).
 */
function memoriaMotivoBootstrap(): array
{
    try {
        $business = Business::first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: '.$e->getMessage());
    }
    if (! $business) {
        test()->markTestSkipped('Sem business no banco — rode o seeder UPos antes.');
    }

    // O grupo /ia exige `can:jana.access` (JanaAccessGateTest). O Gate::before de
    // AuthServiceProvider libera qualquer ability pra quem tem Admin#{biz} — então o
    // admin é o caminho mais curto e estável pra exercitar a rota.
    $user = User::where('business_id', $business->id)
        ->get()
        ->first(fn (User $u) => $u->hasRole('Admin#'.$business->id));

    if (! $user) {
        test()->markTestSkipped("Sem usuário Admin#{$business->id} — não dá pra exercitar /ia/memoria.");
    }

    return [$business, $user];
}

/** Autentica e devolve o id usado na rota. Id inexistente é DE PROPÓSITO nos casos de
 *  validação: o driver retorna cedo pra id não encontrado, então o que sobra sob teste
 *  é exatamente a regra de validação — sem fixture pra apodrecer. */
function memoriaMotivoActing(): int
{
    [$business, $user] = memoriaMotivoBootstrap();
    test()->actingAs($user);
    session(['user.business_id' => $business->id]);

    return 999_999_999;
}

it('UC-MEM-01 · rejeita a edição SEM motivo (LGPD — charter Anti-hook do activitylog)', function () {
    $id = memoriaMotivoActing();

    $r = test()->from('/ia/memoria')->patch("/ia/memoria/{$id}", [
        'fato' => 'Cheque só é depositado na terça e na quinta.',
        // motivo AUSENTE — é isto que está sob teste
    ]);

    $r->assertSessionHasErrors('motivo');

    // Anti-vácuo: prova que a requisição chegou no validador em vez de morrer antes
    // (403 do gate / 404 de rota deixariam o assert acima passar por engano).
    expect($r->status())->toBe(302);
});

it('UC-MEM-02 · rejeita motivo vazio ou curto demais (espaço em branco não é motivo)', function () {
    $id = memoriaMotivoActing();

    foreach (['', '   ', 'ok'] as $motivoRuim) {
        test()->from('/ia/memoria')
            ->patch("/ia/memoria/{$id}", ['fato' => 'texto qualquer', 'motivo' => $motivoRuim])
            ->assertSessionHasErrors('motivo');
    }
});

it('UC-MEM-03 · aceita a edição COM motivo e grava a trilha com autor + motivo', function () {
    $id = memoriaMotivoActing();
    $motivo = 'Cliente corrigiu o dia da rotina por telefone';

    $antes = DB::table('activity_log')->where('log_name', 'jana_memoria_fato_editado')->count();

    test()->from('/ia/memoria')
        ->patch("/ia/memoria/{$id}", ['fato' => 'Cheque é depositado só na quinta.', 'motivo' => $motivo])
        ->assertSessionHasNoErrors();

    $depois = DB::table('activity_log')->where('log_name', 'jana_memoria_fato_editado')->count();
    expect($depois)->toBe($antes + 1);

    $linha = DB::table('activity_log')
        ->where('log_name', 'jana_memoria_fato_editado')
        ->orderByDesc('id')->first();

    $props = json_decode((string) $linha->properties, true);

    expect($linha->causer_id)->not->toBeNull()          // autor
        ->and($props['motivo'])->toBe($motivo)          // motivo
        ->and($props['memoria_id'])->toBe($id);
});

it('UC-MEM-04 · redige PII do motivo antes de persistir na trilha', function () {
    $id = memoriaMotivoActing();

    test()->from('/ia/memoria')->patch("/ia/memoria/{$id}", [
        'fato' => 'texto qualquer',
        'motivo' => 'titular 529.982.247-25 pediu por telefone', // pii-allowlist (CPF sintético de teste)
    ])->assertSessionHasNoErrors();

    $props = json_decode((string) DB::table('activity_log')
        ->where('log_name', 'jana_memoria_fato_editado')
        ->orderByDesc('id')->first()->properties, true);

    expect($props['motivo'])->toContain('[REDACTED:CPF]')
        ->and($props['motivo'])->not->toContain('529.982.247-25'); // pii-allowlist (mesmo dado sintético)
});

it('UC-MEM-05 · esquecer um fato também deixa trilha (auditoria sem exclusão é quebrada)', function () {
    $id = memoriaMotivoActing();

    $antes = DB::table('activity_log')->where('log_name', 'jana_memoria_fato_esquecido')->count();

    test()->from('/ia/memoria')->delete("/ia/memoria/{$id}")->assertSessionHasNoErrors();

    expect(DB::table('activity_log')->where('log_name', 'jana_memoria_fato_esquecido')->count())
        ->toBe($antes + 1);
});

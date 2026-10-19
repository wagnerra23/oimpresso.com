<?php

declare(strict_types=1);
// @covers-us US-FISIO-007
// Cobre UC-FPP-30..UC-FPP-32 (Fisio/Public/Portal.casos.md) — fila offline do check-in, etapa 02 §3.6 (T-13 parte 2).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Sem rede, o check-in fica na fila do aparelho LEMBRADO e é reenviado quando a internet volta.
 * O servidor é quem garante as três regras que importam:
 *  - a mesma `chave` de novo não grava nada (a resposta é que se perdeu) e responde sucesso;
 *  - envio marcado `offline` de aparelho não lembrado é recusado: dado clínico não fica em
 *    celular que não foi lembrado;
 *  - check-in de outro dia (`dia`) é recusado: o relato de ontem não vira o de hoje.
 *
 * Tenant 98 (ADR 0358). ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_portal_devices'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    Carbon::setTestNow('2026-10-14 19:00:00');
    $this->b98 = (int) $this->seededTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    F::pacote([$this->b98]);
    $this->p = F::paciente($this->b98, $this->u98->id);
    $this->prog = F::programa($this->p, [F::exercicio(null, 'Ponte'), F::exercicio(null, 'Prancha')], $this->u98->id);
    $this->itens = F::itens($this->prog);
    $this->g = F::emitirPortal($this->p);
    F::consentir($this->p, 'teleatendimento');
});

afterEach(fn () => Carbon::setTestNow());

function ffoAparelhoNovo($t): void
{
    session()->flush();
    (fn () => $this->defaultCookies = [])->call($t);
}

/** Entra com "lembrar este aparelho" e devolve o cookie do aparelho, já sem a sessão do PIN. */
function ffoLembrar($t): array
{
    ffoAparelhoNovo($t);
    $entrada = $t->postJson('/fisio/p/'.$t->g['token'].'/pin', ['pin' => $t->g['pin'], 'lembrar' => true])->assertOk();
    $linha = (int) DB::table('fisio_portal_tokens')->where('patient_id', $t->p->id)->whereNull('revoked_at')->value('id');
    $nome = 'fisio_aparelho_'.$linha;
    $segredo = $entrada->getCookie($nome)?->getValue();
    expect($segredo)->toBeString();
    ffoAparelhoNovo($t);

    return [$nome => $segredo];
}

function ffoEnviar($t, array $cookies, array $mudar = [])
{
    return $t->withCookies($cookies)->postJson('/fisio/p/'.$t->g['token'].'/checkin', array_merge([
        'done_item_ids' => $t->itens,
        'pain' => 3,
        'effort' => 'adequado',
        'note' => null,
        'red_flags' => [],
        'chave' => 'fila-0001-aaaa',
        'dia' => '2026-10-14',
        'offline' => true,
    ], $mudar));
}

function ffoProps($t, array $cookies): array
{
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $r = $t->withCookies($cookies)->get('/fisio/p/'.$t->g['token'], ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => (string) $versao]);
    $r->assertOk();

    return (array) $r->json('props');
}

it('UC-FPP-30 · reenvio da fila com a mesma chave responde sucesso e não grava de novo', function () {
    $aparelho = ffoLembrar($this);

    ffoEnviar($this, $aparelho)->assertCreated();
    $xp = F::xp($this->p);
    $registros = DB::table('fisio_records')->where('patient_id', $this->p->id)->count();
    expect($registros)->toBe(1);

    // A fila reenvia a MESMA chave. O corpo diferente prova que quem barra é a chave, não o
    // "relato idêntico" do UC-FPP-10: se o servidor gravasse, a dor viraria 5 e nasceria adendo.
    $r = ffoEnviar($this, $aparelho, ['pain' => 5]);
    $r->assertOk();
    expect($r->json('modo'))->toBe('normal')
        ->and((int) $r->json('xp_ganho'))->toBe(0);

    $linhas = DB::table('fisio_checkins')->where('business_id', $this->b98)->where('patient_id', $this->p->id)->get();
    expect($linhas)->toHaveCount(1)
        ->and((int) $linhas[0]->pain)->toBe(3)
        ->and($linhas[0]->client_key)->toBe('fila-0001-aaaa')
        ->and((int) $r->json('checkin_id'))->toBe((int) $linhas[0]->id);
    expect(DB::table('fisio_records')->where('patient_id', $this->p->id)->count())->toBe($registros)
        ->and(F::xp($this->p))->toBe($xp);

    // Chave NOVA no mesmo dia é um envio novo: atualiza o dia, como sempre (UC-FPP-09).
    ffoEnviar($this, $aparelho, ['pain' => 5, 'chave' => 'fila-0002-bbbb'])->assertOk();
    $linha = DB::table('fisio_checkins')->where('patient_id', $this->p->id)->first();
    expect((int) $linha->pain)->toBe(5)
        ->and($linha->client_key)->toBe('fila-0002-bbbb')
        ->and(DB::table('fisio_checkins')->where('patient_id', $this->p->id)->count())->toBe(1);
});

it('UC-FPP-31 · só aparelho lembrado guarda check-in: a tela avisa e o servidor recusa o resto', function () {
    // PIN só na sessão: a tela diz para não guardar, e um envio da fila é recusado sem gravar.
    ffoAparelhoNovo($this);
    $this->postJson('/fisio/p/'.$this->g['token'].'/pin', ['pin' => $this->g['pin'], 'lembrar' => false])->assertOk();
    $sessao = ffoProps($this, []);
    expect($sessao['etapa'])->toBe('hoje')
        ->and($sessao['guardar_offline'])->toBeFalse();

    $r = ffoEnviar($this, []);
    $r->assertForbidden();
    expect($r->json('erro'))->toBe('fila_so_aparelho_lembrado')
        ->and(DB::table('fisio_checkins')->where('patient_id', $this->p->id)->count())->toBe(0)
        ->and(DB::table('fisio_records')->where('patient_id', $this->p->id)->count())->toBe(0);

    // O mesmo check-in enviado com rede (sem `offline`) segue normal nessa sessão.
    ffoEnviar($this, [], ['offline' => false])->assertCreated();

    // Aparelho lembrado: a tela autoriza a fila.
    $aparelho = ffoLembrar($this);
    expect(ffoProps($this, $aparelho)['guardar_offline'])->toBeTrue();
});

it('UC-FPP-32 · check-in guardado de outro dia é recusado e não cai no dia de hoje', function () {
    $aparelho = ffoLembrar($this);

    $r = ffoEnviar($this, $aparelho, ['dia' => '2026-10-13']);
    $r->assertStatus(422);
    expect($r->json('erro'))->toBe('dia_passou')
        ->and(DB::table('fisio_checkins')->where('patient_id', $this->p->id)->count())->toBe(0)
        ->and(DB::table('fisio_records')->where('patient_id', $this->p->id)->count())->toBe(0);

    // O mesmo item com o dia certo entra.
    ffoEnviar($this, $aparelho)->assertCreated();
});

<?php

declare(strict_types=1);
// @covers-us US-FISIO-007
// Cobre UC-FPP-22..UC-FPP-25 (Fisio/Public/Portal.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Services\PortalTokenService;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Portal do paciente, US-FISIO-007b — o que a 007a não cobria:
 *  - "Lembrar este aparelho" (etapa 02 T-07a): cookie httpOnly cifrado, ligado à linha do
 *    token; o PIN volta em aparelho novo, depois do prazo e depois de revogação ou de link
 *    novo. Cookie de outro link não abre este.
 *  - Tela Hoje: dose e cuidados da biblioteca, estado do jogo, só o relato de hoje, isolamento.
 *  - Dia de crise (protocolo 11): só quando o profissional escreveu a versão de crise.
 *
 * "Aparelho novo" = sessão vazia e sem cookie. Tenant 98 contra 99. ⚠️ SKIP em SQLite (LC-13).
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
    $this->prog = F::programa($this->p, [F::exercicio(null, 'Ponte', ['default_sets' => 3, 'default_reps' => 12, 'safety_notes' => ['Não prenda a respiração']])], $this->u98->id);
    $this->g = F::emitirPortal($this->p);
    F::consentir($this->p, 'teleatendimento');
});

afterEach(fn () => Carbon::setTestNow());

function fpaLinhaViva($t): int
{
    return (int) DB::table('fisio_portal_tokens')->where('patient_id', $t->p->id)->whereNull('revoked_at')->value('id');
}

/** Entra com PIN; devolve o segredo (decifrado) do cookie de aparelho, se houver. */
function fpaEntrar($t, bool $lembrar): ?string
{
    $r = $t->postJson('/fisio/p/'.$t->g['token'].'/pin', ['pin' => $t->g['pin'], 'lembrar' => $lembrar])->assertOk();
    $cookie = $r->getCookie('fisio_aparelho_'.fpaLinhaViva($t));

    return $cookie?->getValue();
}

/**
 * Abre o portal como um APARELHO NOVO (sessão vazia), levando só o cookie dado. Devolve a etapa
 * (null = estado vazio de link inválido).
 *
 * @param  array<string,string>  $cookies
 */
function fpaEtapa($t, string $token, array $cookies = []): ?string
{
    session()->flush();
    (fn () => $this->defaultCookies = [])->call($t);
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $r = $t->withCookies($cookies)->get('/fisio/p/'.$token, ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => (string) $versao]);
    $r->assertOk();
    (fn () => $this->defaultCookies = [])->call($t);

    return $r->json('props.etapa');
}

it('UC-FPP-22 · "lembrar este aparelho" dispensa o PIN no mesmo aparelho; banco guarda só o hash', function () {
    $linha = fpaLinhaViva($this);
    $segredo = fpaEntrar($this, true);
    expect($segredo)->toBeString()->toMatch('/^[a-f0-9]{64}$/');

    $dev = DB::table('fisio_portal_devices')->where('token_id', $linha)->first();
    expect($dev)->not->toBeNull()
        ->and((int) $dev->business_id)->toBe($this->b98)
        ->and($dev->device_hash)->toBe(hash('sha256', (string) $segredo))
        ->and(Carbon::parse($dev->remembered_until)->toDateString())->toBe('2026-11-13');

    expect(fpaEtapa($this, $this->g['token'], ['fisio_aparelho_'.$linha => (string) $segredo]))->toBe('hoje');

    // Controle negativo: aparelho novo sem cookie volta ao PIN; cookie forjado também.
    expect(fpaEtapa($this, $this->g['token']))->toBe('pin')
        ->and(fpaEtapa($this, $this->g['token'], ['fisio_aparelho_'.$linha => str_repeat('a', 64)]))->toBe('pin');
});

it('UC-FPP-22 · sem marcar "lembrar", nenhum aparelho é gravado e o PIN volta no aparelho novo', function () {
    expect(fpaEntrar($this, false))->toBeNull()
        ->and(DB::table('fisio_portal_devices')->where('token_id', fpaLinhaViva($this))->count())->toBe(0)
        ->and(fpaEtapa($this, $this->g['token']))->toBe('pin');
});

it('UC-FPP-23 · cookie de outro link não vale, e passado o prazo o PIN volta', function () {
    config(['fisio.portal.lembrar_aparelho_dias' => 10]);
    $linha = fpaLinhaViva($this);
    $segredo = (string) fpaEntrar($this, true);

    // Outro paciente da mesma clínica, com o segredo deste aparelho no cookie do link DELE: não abre.
    $outro = F::paciente($this->b98, $this->u98->id);
    F::programa($outro, [F::exercicio(null)], $this->u98->id);
    F::consentir($outro, 'teleatendimento');
    $gOutro = F::emitirPortal($outro);
    $linhaOutro = (int) DB::table('fisio_portal_tokens')->where('patient_id', $outro->id)->value('id');
    expect(fpaEtapa($this, $gOutro['token'], ['fisio_aparelho_'.$linhaOutro => $segredo]))->toBe('pin');

    Carbon::setTestNow('2026-10-23 19:00:00'); // 9 dias: ainda lembrado
    expect(fpaEtapa($this, $this->g['token'], ['fisio_aparelho_'.$linha => $segredo]))->toBe('hoje');
    Carbon::setTestNow('2026-10-25 19:00:00'); // 11 dias: PIN de novo (o link ainda vale)
    expect(fpaEtapa($this, $this->g['token'], ['fisio_aparelho_'.$linha => $segredo]))->toBe('pin');
});

it('UC-FPP-23 · o profissional revogar ou gerar outro link revoga o aparelho lembrado', function () {
    $linha = fpaLinhaViva($this);
    fpaEntrar($this, true);

    app(PortalTokenService::class)->revogar($this->p);
    expect(DB::table('fisio_portal_devices')->where('token_id', $linha)->whereNull('revoked_at')->count())->toBe(0);

    $this->g = F::emitirPortal($this->p);
    $linha2 = fpaLinhaViva($this);
    session()->flush();
    $segredo2 = (string) fpaEntrar($this, true);
    expect(DB::table('fisio_portal_devices')->where('token_id', $linha2)->whereNull('revoked_at')->count())->toBe(1);

    F::emitirPortal($this->p); // link novo revoga o anterior e os aparelhos dele
    expect(DB::table('fisio_portal_devices')->where('token_id', $linha2)->whereNull('revoked_at')->count())->toBe(0)
        ->and(fpaEtapa($this, $this->g['token'], ['fisio_aparelho_'.$linha2 => $segredo2]))->toBeNull();
});

it('UC-FPP-24 · dia de crise: só com versão de crise escrita; grava crisis=true e conta para a sequência', function () {
    fpaEntrar($this, false); // consentimento já dado na clínica (beforeEach)
    $token = $this->g['token'];

    $this->postJson("/fisio/p/{$token}/checkin", ['done_item_ids' => [], 'pain' => 4, 'effort' => 'facil', 'crisis' => true])
        ->assertStatus(422)->assertJsonValidationErrors('crisis');
    expect(DB::table('fisio_checkins')->where('patient_id', $this->p->id)->count())->toBe(0)
        ->and(F::portalProps($this, $token)['crise']['itens'])->toBe([]);

    $this->prog->update(['crisis_items' => ['Respiração diafragmática 5 min', 'Gato-camelo 1×8']]);
    expect(F::portalProps($this, $token)['crise']['itens'])->toBe(['Respiração diafragmática 5 min', 'Gato-camelo 1×8']);

    $this->postJson("/fisio/p/{$token}/checkin", ['done_item_ids' => [], 'pain' => 4, 'effort' => 'facil', 'crisis' => true])->assertCreated();
    expect((bool) DB::table('fisio_checkins')->where('patient_id', $this->p->id)->value('crisis'))->toBeTrue()
        ->and(F::portalProps($this, $token)['jogo']['sequencia'])->toBe(1);
});

it('UC-FPP-25 · Hoje traz dose e cuidados da biblioteca, o jogo e só o relato de hoje; nada de outra clínica', function () {
    fpaEntrar($this, false); // consentimento já dado na clínica (beforeEach)
    $token = $this->g['token'];
    $item = F::itens($this->prog)[0];

    $props = F::portalProps($this, $token);
    expect($props['modo'])->toBe('normal')
        ->and($props['registrado_hoje'])->toBeFalse()
        ->and($props['relato'])->toBeNull()
        ->and($props['hoje']['itens'][0])->toMatchArray(['id' => $item, 'sets' => 3, 'reps' => 12, 'times_per_day' => 1])
        ->and($props['hoje']['itens'][0]['cuidados'])->toBe(['Não prenda a respiração'])
        ->and(array_keys($props['jogo']))->toEqualCanonicalizing(['xp', 'nivel', 'xp_no_nivel', 'xp_para_proximo', 'sequencia', 'em_pausa', 'dias_cumpridos']);

    $this->postJson("/fisio/p/{$token}/checkin", ['done_item_ids' => [$item], 'pain' => 2, 'effort' => 'adequado', 'note' => 'ok'])->assertCreated();
    $depois = F::portalProps($this, $token);
    expect($depois['registrado_hoje'])->toBeTrue()
        ->and($depois['relato'])->toMatchArray(['feitos' => [$item], 'dor' => 2, 'esforco' => 'adequado', 'observacao' => 'ok']);

    // Isolamento: o token da 98 não grava em paciente da 99 (o business vem do token, não do corpo).
    $b99 = (int) $this->seededSupportClientTenant()->id;
    $u99 = $this->usuarioComPermissoes([F::PERM_ACESSO], $this->seededSupportClientTenant());
    F::trocarPacote($b99, true);
    $p99 = F::paciente($b99, $u99->id);
    $prog99 = F::programa($p99, [F::exercicio(null, 'Ponte da 99')], $u99->id);
    $this->postJson("/fisio/p/{$token}/checkin", ['done_item_ids' => F::itens($prog99), 'pain' => 1, 'effort' => 'facil', 'business_id' => $b99, 'patient_id' => $p99->id])
        ->assertStatus(422);
    expect(DB::table('fisio_checkins')->where('patient_id', $p99->id)->count())->toBe(0)
        ->and(json_encode(F::portalProps($this, $token)))->not->toContain('Ponte da 99');
});

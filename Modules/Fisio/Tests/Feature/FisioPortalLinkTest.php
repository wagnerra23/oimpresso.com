<?php

declare(strict_types=1);
// @covers-us US-FISIO-007
// Cobre UC-FPS-33, UC-FPS-34, UC-FPS-35 (Fisio/Pacientes/Show.casos.md) e UC-FPP-21 (Fisio/Public/Portal.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Services\PortalTokenService;
use Modules\Fisio\Tests\Support\FisioFixtures as F;
use Spatie\Activitylog\Models\Activity;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Link do portal gerado pela clínica, na ficha (US-FISIO-007a), e os consentimentos opcionais
 * do portal (WhatsApp e câmera), cada um com o seu carimbo.
 *
 * O link e o PIN em claro saem UMA vez na resposta de quem gerou; o banco e o activity_log
 * guardam só hash e o evento. Tenant 98 contra 99, os dois COM o pacote (o 403 do módulo não
 * pode mascarar o isolamento). ⚠️ SKIP em SQLite (LC-13): conte os passed.
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['activity_log'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    config(['fisio.consent_versions.camera' => 'cam-v1']);
    Carbon::setTestNow('2026-10-14 09:00:00');
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->fisio = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->leitor = $this->usuarioComPermissoes([F::PERM_ACESSO], $this->seededTenant());
    $this->outra = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    F::pacote([$this->b98, $this->b99]);
    $this->p = F::paciente($this->b98, $this->fisio->id);
    F::programa($this->p, [F::exercicio(null, 'Ponte')], $this->fisio->id);
});

afterEach(fn () => Carbon::setTestNow());

function fplLinks(Patient $p)
{
    return DB::table('fisio_portal_tokens')->where('patient_id', $p->id)->orderBy('id')->get();
}

/** Token que vem dentro da URL devolvida à fisioterapeuta. */
function fplToken(string $url): string
{
    expect($url)->toContain('/fisio/p/');

    return substr($url, strpos($url, '/fisio/p/') + strlen('/fisio/p/'));
}

it('UC-FPS-33 · gerar devolve link e PIN uma vez; o banco guarda só hash, no business do paciente', function () {
    F::como($this, $this->fisio);

    $r = $this->postJson("/fisio/pacientes/{$this->p->id}/portal")->assertCreated();
    $token = fplToken((string) $r->json('url'));
    $pin = (string) $r->json('pin');

    expect($pin)->toMatch('/^\d{4}$/')
        ->and(Carbon::parse((string) $r->json('expires_at'))->equalTo(now()->addDays(30)))->toBeTrue();

    $linhas = fplLinks($this->p);
    expect($linhas)->toHaveCount(1)
        ->and((int) $linhas[0]->business_id)->toBe($this->b98)
        ->and($linhas[0]->token_hash)->toBe(hash('sha256', $token))
        ->and($linhas[0]->pin_hash)->not->toBe($pin)
        ->and(Hash::check($pin, $linhas[0]->pin_hash))->toBeTrue()          // hash do PIN, não o PIN
        ->and(json_encode($linhas[0]))->not->toContain($token);

    // O que a clínica entregou abre o portal de verdade (controle de ponta a ponta).
    auth()->logout();
    expect(app(PortalTokenService::class)->validar($token)?->id)->toBe($this->p->id);
    $this->postJson('/fisio/p/'.$token.'/pin', ['pin' => $pin])->assertOk();
});

it('UC-FPS-33 · gerar de novo revoga o link anterior e fica no activity_log sem token nem PIN', function () {
    F::como($this, $this->fisio);
    $velho = $this->postJson("/fisio/pacientes/{$this->p->id}/portal")->assertCreated()->json();
    $novo = $this->postJson("/fisio/pacientes/{$this->p->id}/portal")->assertCreated()->json();

    expect(fplLinks($this->p)->whereNull('revoked_at'))->toHaveCount(1)
        ->and(app(PortalTokenService::class)->validar(fplToken($velho['url'])))->toBeNull()
        ->and(app(PortalTokenService::class)->validar(fplToken($novo['url']))?->id)->toBe($this->p->id);

    $log = Activity::query()->where('subject_type', $this->p->getMorphClass())->where('subject_id', $this->p->id)
        ->where('description', 'portal_link_gerado')->get();
    expect($log)->toHaveCount(2)
        ->and((int) $log[0]->causer_id)->toBe((int) $this->fisio->id)
        ->and($log->every(fn (Activity $a) => ! $a->properties->has('pin') && ! $a->properties->has('token')))->toBeTrue()
        ->and(json_encode($log->toArray()))->not->toContain(fplToken($novo['url']));
});

it('UC-FPS-34 · revogar derruba o link vivo e fica registrado', function () {
    F::como($this, $this->fisio);
    $g = $this->postJson("/fisio/pacientes/{$this->p->id}/portal")->assertCreated()->json();

    $this->deleteJson("/fisio/pacientes/{$this->p->id}/portal")->assertOk();

    expect(fplLinks($this->p)->whereNull('revoked_at'))->toHaveCount(0)
        ->and(fplLinks($this->p))->toHaveCount(1)                 // a linha fica, com revoked_at
        ->and(app(PortalTokenService::class)->validar(fplToken($g['url'])))->toBeNull()
        ->and(Activity::query()->where('subject_type', $this->p->getMorphClass())->where('subject_id', $this->p->id)->where('description', 'portal_link_revogado')->count())->toBe(1);
});

it('UC-FPS-35 · sem fisio.editar é 403 e nada é gravado', function () {
    F::como($this, $this->leitor);

    $this->postJson("/fisio/pacientes/{$this->p->id}/portal")->assertForbidden();
    $this->deleteJson("/fisio/pacientes/{$this->p->id}/portal")->assertForbidden();
    expect(fplLinks($this->p))->toHaveCount(0);
});

it('UC-FPS-35 · paciente de outra clínica é 404; paciente bloqueado não recebe link', function () {
    F::como($this, $this->outra);
    $this->postJson("/fisio/pacientes/{$this->p->id}/portal")->assertNotFound();
    $this->deleteJson("/fisio/pacientes/{$this->p->id}/portal")->assertNotFound();
    expect(fplLinks($this->p))->toHaveCount(0);

    // Controle negativo: a 98 gera para o próprio paciente.
    F::como($this, $this->fisio);
    $this->postJson("/fisio/pacientes/{$this->p->id}/portal")->assertCreated();

    $bloq = F::paciente($this->b98, $this->fisio->id, ['blocked_at' => now()]);
    $this->postJson("/fisio/pacientes/{$bloq->id}/portal")->assertStatus(422)->assertJsonPath('erro', 'paciente_bloqueado');
    expect(fplLinks($bloq))->toHaveCount(0);
});

it('UC-FPP-21 · WhatsApp e câmera são opcionais, independentes e carimbados; sem PIN nada se grava', function () {
    $g = F::emitirPortal($this->p);

    // Sem PIN aceito na sessão: consentir é recusado e não grava.
    $this->postJson('/fisio/p/'.$g['token'].'/consentimentos', ['purpose' => 'camera', 'aceito' => true])->assertForbidden();
    expect(DB::table('fisio_consents')->where('patient_id', $this->p->id)->count())->toBe(0);

    $this->postJson('/fisio/p/'.$g['token'].'/pin', ['pin' => $g['pin']])->assertOk();
    $this->postJson('/fisio/p/'.$g['token'].'/consentimentos', ['purpose' => 'teleatendimento', 'aceito' => true])->assertCreated();
    $this->postJson('/fisio/p/'.$g['token'].'/consentimentos', ['purpose' => 'camera', 'aceito' => true])->assertCreated();
    $this->postJson('/fisio/p/'.$g['token'].'/consentimentos', ['purpose' => 'camera', 'aceito' => true])->assertOk(); // repetir não duplica
    $this->postJson('/fisio/p/'.$g['token'].'/consentimentos', ['purpose' => 'whatsapp', 'aceito' => false])->assertStatus(422);

    $linhas = DB::table('fisio_consents')->where('patient_id', $this->p->id)->get()->keyBy('purpose');
    expect($linhas->keys()->sort()->values()->all())->toBe(['camera', 'teleatendimento'])
        ->and($linhas['camera']->version)->toBe('cam-v1')
        ->and($linhas['camera']->channel)->toBe('portal')
        ->and($linhas['camera']->granted_at)->not->toBeNull()
        ->and((int) $linhas['camera']->business_id)->toBe($this->b98);

    // Revogar a câmera não fecha o portal.
    $this->deleteJson('/fisio/p/'.$g['token'].'/consentimentos/camera')->assertOk();
    expect(F::portalProps($this, $g['token'])['etapa'])->toBe('hoje')
        ->and(DB::table('fisio_consents')->where('patient_id', $this->p->id)->where('purpose', 'camera')->value('revoked_at'))->not->toBeNull();
});
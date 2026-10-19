<?php

declare(strict_types=1);
// @covers-us US-FISIO-007
// Cobre UC-FPP-01..UC-FPP-07 (Fisio/Public/Portal.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Services\PortalTokenService;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Portal público do paciente (`/fisio/p/{token}`) — precedente: link de aprovação da
 * OficinaAuto (token HMAC + PIN + lockout, mesmo estado vazio para toda falha). Diferença:
 * o estado mora em `fisio_portal_tokens` (token_hash, pin_hash, attempts, locked_until,
 * expires_at, revoked_at), não em cache.
 *
 * Rota pública, SEM auth: o business viaja assinado no token, nunca como parâmetro.
 * Tenant 98 contra 99. ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip()) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    Carbon::setTestNow('2026-10-14 09:00:00');
    $this->b98 = (int) $this->seededTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    F::pacote([$this->b98]);
    $this->p = F::paciente($this->b98, $this->u98->id);
    $this->prog = F::programa($this->p, [F::exercicio(null, 'Ponte')], $this->u98->id);
});

afterEach(fn () => Carbon::setTestNow());

/** Toda falha de link mostra o MESMO estado vazio — sem revelar qual condição quebrou (UC-OAP-04). */
function fppLinkInvalido($t, string $token): void
{
    $props = F::portalProps($t, $token);
    expect($props['erro'])->toBe('link_invalido')
        ->and($props)->not->toHaveKey('hoje')
        ->and($props)->not->toHaveKey('paciente')
        ->and($props)->not->toHaveKey('jogo');
    $t->postJson('/fisio/p/'.$token.'/pin', ['pin' => '0000'])->assertNotFound()->assertJsonPath('erro', 'link_invalido');
}

it('UC-FPP-01 · link válido abre a etapa de PIN sem mostrar nenhum dado clínico', function () {
    $g = F::emitirPortal($this->p);

    $props = F::portalProps($this, $g['token']);
    expect($props['erro'])->toBeNull()
        ->and($props['etapa'])->toBe('pin')
        ->and($props['tentativas_restantes'])->toBe(5)
        ->and($props)->not->toHaveKey('hoje')
        ->and($props)->not->toHaveKey('paciente');

    // Só o hash vai para o banco: nem o token nem o PIN em claro.
    $linha = DB::table('fisio_portal_tokens')->where('patient_id', $this->p->id)->first();
    expect($linha->token_hash)->not->toBe($g['token'])->and($linha->pin_hash)->not->toBe($g['pin'])
        ->and((int) $linha->business_id)->toBe($this->b98)
        ->and(json_encode($linha))->not->toContain($g['pin']);
});

it('UC-FPP-02 · token adulterado, desconhecido ou de formato errado cai no mesmo estado vazio', function () {
    $g = F::emitirPortal($this->p);
    [$payload] = explode('.', $g['token'].'.');

    fppLinkInvalido($this, 'lixo_sem_ponto');
    fppLinkInvalido($this, $payload.'.'.str_repeat('0', 64));
    fppLinkInvalido($this, substr($g['token'], 0, -1).(str_ends_with($g['token'], 'a') ? 'b' : 'a'));
});

it('UC-FPP-02 · token expirado ou revogado cai no mesmo estado vazio', function () {
    $g = F::emitirPortal($this->p);
    Carbon::setTestNow(now()->addDays(31));
    DB::table('fisio_portal_tokens')->where('patient_id', $this->p->id)->update(['expires_at' => now()->subMinute()]);
    fppLinkInvalido($this, $g['token']);

    Carbon::setTestNow('2026-10-14 09:00:00');
    $g2 = F::emitirPortal($this->p);
    app(PortalTokenService::class)->revogar($this->p);
    fppLinkInvalido($this, $g2['token']);
});

it('UC-FPP-02 · emitir novo link revoga o anterior', function () {
    $velho = F::emitirPortal($this->p);
    $novo = F::emitirPortal($this->p);

    fppLinkInvalido($this, $velho['token']);
    expect(F::portalProps($this, $novo['token'])['erro'])->toBeNull()
        ->and(DB::table('fisio_portal_tokens')->where('patient_id', $this->p->id)->whereNull('revoked_at')->count())->toBe(1);
});

it('UC-FPP-03 · PIN errado conta tentativas; na 5ª trava, e travado recusa até o PIN certo', function () {
    $g = F::emitirPortal($this->p);
    $errado = $g['pin'] === '0000' ? '1111' : '0000';

    for ($i = 4; $i >= 1; $i--) {
        $this->postJson('/fisio/p/'.$g['token'].'/pin', ['pin' => $errado])->assertStatus(422)
            ->assertJsonPath('erro', 'pin_invalido')->assertJsonPath('tentativas_restantes', $i);
    }
    $this->postJson('/fisio/p/'.$g['token'].'/pin', ['pin' => $errado])->assertStatus(423)->assertJsonPath('erro', 'bloqueado');
    $this->postJson('/fisio/p/'.$g['token'].'/pin', ['pin' => $g['pin']])->assertStatus(423);

    $linha = DB::table('fisio_portal_tokens')->where('patient_id', $this->p->id)->first();
    expect((int) $linha->attempts)->toBe(5)
        ->and(Carbon::parse($linha->locked_until)->equalTo(now()->addMinutes(30)))->toBeTrue()
        ->and(F::portalProps($this, $g['token'])['etapa'])->toBe('pin');
});

it('UC-FPP-03 · PIN fora do formato de 4 dígitos é recusado e também conta tentativa', function () {
    $g = F::emitirPortal($this->p);

    $this->postJson('/fisio/p/'.$g['token'].'/pin', ['pin' => 'abcd'])->assertStatus(422)->assertJsonPath('tentativas_restantes', 4);
});

it('UC-FPP-04 · o lockout expira: depois de 30 min o PIN certo entra e o contador zera', function () {
    $g = F::emitirPortal($this->p);
    $errado = $g['pin'] === '0000' ? '1111' : '0000';
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/fisio/p/'.$g['token'].'/pin', ['pin' => $errado]);
    }

    Carbon::setTestNow(now()->addMinutes(29));
    $this->postJson('/fisio/p/'.$g['token'].'/pin', ['pin' => $g['pin']])->assertStatus(423);

    Carbon::setTestNow(now()->addMinutes(2));
    $this->postJson('/fisio/p/'.$g['token'].'/pin', ['pin' => $g['pin']])->assertOk();
    expect((int) DB::table('fisio_portal_tokens')->where('patient_id', $this->p->id)->value('attempts'))->toBe(0);
});

it('UC-FPP-05 · PIN certo sem consentimento de teleatendimento → etapa consentimento, nenhum dado clínico', function () {
    $g = F::emitirPortal($this->p);
    $this->postJson('/fisio/p/'.$g['token'].'/pin', ['pin' => $g['pin']])->assertOk();

    $props = F::portalProps($this, $g['token']);
    expect($props['etapa'])->toBe('consentimento')
        ->and($props['consentimento']['purpose'])->toBe('teleatendimento')
        ->and($props['consentimento']['version'])->toBe('tele-v1')
        ->and($props)->not->toHaveKey('hoje')
        ->and($props)->not->toHaveKey('jogo')
        ->and(json_encode($props))->not->toContain('Lombalgia');

    // Check-in sem consentimento também é barrado no servidor.
    $this->postJson('/fisio/p/'.$g['token'].'/checkin', ['done_item_ids' => F::itens($this->prog), 'pain' => 2, 'effort' => 'adequado'])
        ->assertForbidden()->assertJsonPath('erro', 'consentimento_pendente');
    expect(DB::table('fisio_checkins')->where('patient_id', $this->p->id)->count())->toBe(0);
});

it('UC-FPP-05 · consentimento gravado com purpose, versão vigente, IP e canal; depois o Hoje aparece', function () {
    $g = F::emitirPortal($this->p);
    $this->postJson('/fisio/p/'.$g['token'].'/pin', ['pin' => $g['pin']])->assertOk();

    $this->postJson('/fisio/p/'.$g['token'].'/consentimentos', ['purpose' => 'teleatendimento', 'aceito' => true, 'version' => 'v-velha'])
        ->assertCreated();

    $c = DB::table('fisio_consents')->where('patient_id', $this->p->id)->where('purpose', 'teleatendimento')->first();
    expect($c->version)->toBe('tele-v1')               // versão vem da config, nunca do corpo
        ->and((int) $c->business_id)->toBe($this->b98)
        ->and($c->granted_at)->not->toBeNull()
        ->and($c->revoked_at)->toBeNull()
        ->and($c->ip)->toBe('127.0.0.1')
        ->and($c->channel)->toBe('portal');

    $props = F::portalProps($this, $g['token']);
    expect($props['etapa'])->toBe('hoje')->and($props['hoje']['itens'])->toHaveCount(1);
});

it('UC-FPP-05 · consentimento de versão antiga não vale: nova versão pede aceite de novo', function () {
    F::consentir($this->p, 'teleatendimento', 'tele-v0');
    $g = F::emitirPortal($this->p);
    $this->postJson('/fisio/p/'.$g['token'].'/pin', ['pin' => $g['pin']])->assertOk();

    expect(F::portalProps($this, $g['token'])['etapa'])->toBe('consentimento');
});

it('UC-FPP-06 · consentimentos são independentes; revogar teleatendimento pelo portal fecha o Hoje', function () {
    $token = F::portalPronto($this, $this->p);
    $this->postJson('/fisio/p/'.$token.'/consentimentos', ['purpose' => 'whatsapp', 'aceito' => true])->assertCreated();
    $this->postJson('/fisio/p/'.$token.'/consentimentos', ['purpose' => 'inventado', 'aceito' => true])->assertStatus(422);

    // Revogar WhatsApp não mexe no teleatendimento.
    $this->deleteJson('/fisio/p/'.$token.'/consentimentos/whatsapp')->assertOk();
    expect(F::portalProps($this, $token)['etapa'])->toBe('hoje');

    // Revogar teleatendimento: o registro fica (com revoked_at) e o portal volta ao consentimento.
    $this->deleteJson('/fisio/p/'.$token.'/consentimentos/teleatendimento')->assertOk();
    $tele = DB::table('fisio_consents')->where('patient_id', $this->p->id)->where('purpose', 'teleatendimento')->get();
    expect($tele)->toHaveCount(1)->and($tele[0]->revoked_at)->not->toBeNull()
        ->and(F::portalProps($this, $token)['etapa'])->toBe('consentimento');
});

it('UC-FPP-07 · paciente bloqueado não acessa o portal, nem com token válido e PIN certo', function () {
    $token = F::portalPronto($this, $this->p);
    expect(F::portalProps($this, $token)['etapa'])->toBe('hoje');

    F::como($this, $this->u98);
    $this->delete("/fisio/pacientes/{$this->p->id}")->assertRedirect();
    auth()->logout();

    fppLinkInvalido($this, $token);
    $this->postJson('/fisio/p/'.$token.'/checkin', ['done_item_ids' => F::itens($this->prog), 'pain' => 1, 'effort' => 'facil'])->assertNotFound();
});

it('UC-FPP-07 · token de um paciente da 98 não abre dado da 99 (business assinado no token)', function () {
    $u99 = $this->usuarioComPermissoes([F::PERM_ACESSO], $this->seededSupportClientTenant());
    $p99 = F::paciente((int) $u99->business_id, $u99->id, ['main_complaint' => 'Queixa secreta da 99']);
    $token = F::portalPronto($this, $this->p);

    $props = F::portalProps($this, $token);
    expect(json_encode($props))->not->toContain('Queixa secreta da 99')
        ->and(app(PortalTokenService::class)->validar($token)?->id)->toBe($this->p->id)
        ->and(app(PortalTokenService::class)->validar($token)?->id)->not->toBe($p99->id);
});

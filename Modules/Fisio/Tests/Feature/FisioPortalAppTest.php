<?php

declare(strict_types=1);
// @covers-us US-FISIO-007
// Cobre UC-FPP-26..UC-FPP-29 (Fisio/Public/Portal.casos.md) — app instalável, etapa 02 §3.6 (T-13).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * O portal vira app instalável sem deixar de ser link + PIN:
 *  - manifesto por link, público e sem o nome da clínica (ele vem antes do PIN);
 *  - a tela do dia só pode ficar guardada no aparelho (para abrir sem rede) quando o aparelho
 *    está LEMBRADO; com o PIN só na sessão, ou fora da tela Hoje, o servidor manda não guardar;
 *  - o service worker obedece o servidor, apaga a cópia de link que deixou de abrir e nunca
 *    guarda POST nem cookie.
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
    F::programa($this->p, [F::exercicio(null, 'Ponte')], $this->u98->id);
    $this->g = F::emitirPortal($this->p);
    F::consentir($this->p, 'teleatendimento');
});

afterEach(fn () => Carbon::setTestNow());

/** Abre o portal (visita Inertia) só com os cookies dados e devolve a resposta. */
function fpiAbrir($t, array $cookies = [])
{
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $r = $t->withCookies($cookies)->get('/fisio/p/'.$t->g['token'], ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => (string) $versao]);
    $r->assertOk();

    return $r;
}

function fpiAparelhoNovo($t): void
{
    session()->flush();
    (fn () => $this->defaultCookies = [])->call($t);
}

it('UC-FPP-26 · manifesto por link: abre no programa do paciente e não diz o nome da clínica', function () {
    $token = $this->g['token'];
    $r = $this->get('/fisio/p/'.$token.'/manifest.webmanifest');

    $r->assertOk();
    expect($r->headers->get('Content-Type'))->toContain('application/manifest+json');
    $m = $r->json();
    expect($m['start_url'])->toBe('/fisio/p/'.$token)
        ->and($m['scope'])->toBe('/fisio/p/')
        ->and($m['display'])->toBe('standalone')
        ->and($m['lang'])->toBe('pt-BR');

    $tamanhos = array_column($m['icons'], 'sizes');
    expect($tamanhos)->toContain('192x192')->toContain('512x512')
        ->and(array_column($m['icons'], 'purpose'))->toContain('maskable');
    foreach ($m['icons'] as $icone) {
        expect(is_file(public_path(ltrim($icone['src'], '/'))))->toBeTrue("ícone {$icone['src']} precisa existir em public/");
    }

    $clinica = (string) DB::table('business')->where('id', $this->b98)->value('name');
    expect($clinica)->not->toBe('');
    $this->assertStringNotContainsString($clinica, $r->getContent(), 'o manifesto vem antes do PIN e não pode nomear a clínica');
});

it('UC-FPP-27 · a tela do dia só fica guardada no aparelho quando o aparelho está lembrado', function () {
    // Antes do PIN: nada a guardar (o SW apaga qualquer cópia deste link).
    fpiAparelhoNovo($this);
    expect(fpiAbrir($this)->json('props.etapa'))->toBe('pin');
    expect(fpiAbrir($this)->headers->get('X-Fisio-Offline'))->toBeNull();

    // PIN só na sessão: vê o programa, mas ele não fica no aparelho.
    $this->postJson('/fisio/p/'.$this->g['token'].'/pin', ['pin' => $this->g['pin'], 'lembrar' => false])->assertOk();
    $sessao = fpiAbrir($this);
    expect($sessao->json('props.etapa'))->toBe('hoje')
        ->and($sessao->headers->get('X-Fisio-Offline'))->toBe('nao-guardar');

    // Aparelho lembrado: a tela Hoje pode ficar guardada.
    fpiAparelhoNovo($this);
    $entrada = $this->postJson('/fisio/p/'.$this->g['token'].'/pin', ['pin' => $this->g['pin'], 'lembrar' => true])->assertOk();
    $linha = (int) DB::table('fisio_portal_tokens')->where('patient_id', $this->p->id)->whereNull('revoked_at')->value('id');
    $segredo = $entrada->getCookie('fisio_aparelho_'.$linha)?->getValue();
    expect($segredo)->toBeString();

    fpiAparelhoNovo($this);
    $lembrado = fpiAbrir($this, ['fisio_aparelho_'.$linha => $segredo]);
    expect($lembrado->json('props.etapa'))->toBe('hoje')
        ->and($lembrado->headers->get('X-Fisio-Offline'))->toBe('guardar');
});

it('UC-FPP-27 · link inválido não autoriza guardar nada', function () {
    fpiAparelhoNovo($this);
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $r = $this->get('/fisio/p/token-que-nao-existe', ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => (string) $versao]);

    $r->assertOk();
    expect($r->json('props.erro'))->toBe('link_invalido')
        ->and($r->headers->get('X-Fisio-Offline'))->toBeNull();
});

it('UC-FPP-28 · o service worker obedece o servidor e nunca guarda POST nem cookie', function () {
    $sw = (string) file_get_contents(public_path('sw-fisio-portal.js'));

    // Só GET passa pelo cache; o resto (PIN, consentimento, check-in) vai direto à rede.
    expect($sw)->toContain("request.method !== 'GET'")
        // Guarda só com a autorização do servidor; sem ela, APAGA a cópia daquele link.
        ->and($sw)->toContain("=== 'guardar'")
        ->and($sw)->toContain('cache.delete(chave)')
        // A sessão nunca fica no aparelho.
        ->and($sw)->toContain("headers.delete('set-cookie')")
        // Escopo do portal.
        ->and($sw)->toContain("'/fisio/p/'");

    $portal = (string) file_get_contents(base_path('Modules/Fisio/Resources/js/Pages/Fisio/Public/_components/portal.ts'));
    expect($portal)->toContain("register('/sw-fisio-portal.js', { scope: '/fisio/p/' })");
});

it('UC-FPP-29 · a entrada do app da loja só pede o link da clínica, sem dado nenhum', function () {
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $r = $this->get('/fisio/app', ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => (string) $versao]);

    $r->assertOk();
    expect($r->json('component'))->toBe('Fisio/Public/Portal')
        ->and($r->json('props.erro'))->toBe('sem_link')
        ->and($r->json('props.etapa'))->toBeNull()
        ->and($r->json('props.token'))->toBe('');
    foreach (['hoje', 'jogo', 'clinica', 'relato', 'consentimento'] as $chave) {
        expect($r->json("props.{$chave}"))->toBeNull();
    }
});

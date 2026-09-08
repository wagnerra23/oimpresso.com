<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — SMOKE GENÉRICO do Design Sync em ambiente controlado (ADR 0390).
 *
 * ── O QUE É ──────────────────────────────────────────────────────────────────
 * Renderiza, LOGADO como o admin do biz=1 do seed do CI, cada tela que o Design Sync marca
 * como `tested|validated` e salva um PNG por tela em `storage/design-smokes/<slug>.png`.
 * O workflow `design-smoke-ci.yml` publica esses PNGs + `manifest.json` na branch órfã
 * `governance/design-smokes`; `scripts/design-sync/smoke-consumir.mjs` transforma cada um em
 * recibo `--record-smoke … --host ci` — e é ESSE recibo que leva a tela a `validated`.
 *
 * Nasceu porque o D-6 da ADR 0384 só aceitava smoke de PRODUÇÃO, e produção exige login
 * humano que o agente não digita: 0/93 validadas por construção. Aqui o login é o
 * auth-bridge `/_visreg-login/{id}?to=` (routes/web.php, allowlist `local|testing`), o mesmo
 * dos gates visuais.
 *
 * ── O QUE PROVA / NÃO PROVA ───────────────────────────────────────────────────
 *   PROVA  a rota renderiza autenticada no biz=1 em 1280×800 (Larissa/ROTA LIVRE) sem cair no
 *          /login e sem página de erro do Laravel, e existe um PNG durável dela.
 *   NÃO    conteúdo, pixel, a11y nem contrato — cada um tem dono (PixelBaselineTest, casos-gate,
 *          A11yAxeBrowserTest). Isto é o que a 0384 chama de smoke: "abriu, está de pé, foto".
 *
 * ── ENTRADA ──────────────────────────────────────────────────────────────────
 * `DESIGN_SMOKE_SCREENS` = JSON `[{slug, route, target, source}]`, gerado por
 * `node scripts/design-sync/smoke-consumir.mjs --select --json` (rota derivada do report ou do
 * charter — nunca inventada aqui). Vazio → 1 caso SKIPPED visível, nunca verde-vazio (LC-13).
 * `slug` passa por allowlist `[a-z0-9-]` porque vira nome de arquivo.
 *
 * ── TENANT ───────────────────────────────────────────────────────────────────
 * `Business::orderBy('id')->first()` = biz 1 FICTÍCIO do VisregTenantSeeder no MySQL
 * `oimpresso_test`, não produção. `biz=4` é PROIBIDO em teste (ADR 0358) e o recibo continua
 * `tenant: 1` em qualquer host (ADR 0390). Mesmo idioma do AuthBridgeSmokeTest.
 *
 * ── EXECUÇÃO (CI / CT 100 — nunca local: memory/proibicoes.md + ADR 0062) ────
 *   DESIGN_SMOKE_SCREENS='[…]' ./vendor/bin/pest tests/Browser/DesignSmoke/DesignSmokeTest.php
 *
 * HONESTIDADE: este arquivo NÃO foi executado antes do PR — não há `vendor/` nem Playwright
 * na worktree que o escreveu. Só usa API já provada verde nos Browser tests deste repo:
 * `visit` · `resize` · `script` · `wait` · `assertDontSee` · `screenshot(false, $nome)` (o mesmo
 * chamado em tests/Browser/Support/VisregThreshold.php:163) e
 * `VisregThreshold::aguardarFontesReais`. O 1º run do workflow é a prova.
 *
 * 1º RUN (main, 2026-09-06, run 34033586088): 4 failed / 0 fotografadas — as 4 na MESMA linha,
 * `aguardarFontesReais` com `document.fonts.check = false`. Não era fonte quebrada: o self-host
 * @fontsource só começa a baixar quando algum texto que a usa é RENDERIZADO, e este arquivo
 * checava a fonte logo depois do `visit`, antes de o React montar a tela — `fonts.ready`
 * resolvia sem pendência e o check saía false. O PixelBaselineTest (mesmo helper, verde) faz
 * `assertSee($ancora)` antes, que espera o conteúdo; aqui a tela não tem âncora declarada,
 * então a espera é genérica: Inertia montada + fonte disponível, com teto. Segunda diferença
 * herdada dele: a role `Admin#{biz}` — `/arquivos` está atrás de `can:` e o seed do CI cria o
 * user sem role, logo sem ela o smoke fotografaria um 403 com cara de tela.
 *
 * @see scripts/design-sync/smoke-consumir.mjs (seleção + manifesto + consumo)
 * @see .github/workflows/design-smoke-ci.yml (quem invoca e publica)
 * @see memory/decisions/0390-emenda-0384-smoke-em-ambiente-controlado.md
 */

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\Browser\Support\VisregThreshold;

$designSmokeScreens = array_values(array_filter(
    json_decode(getenv('DESIGN_SMOKE_SCREENS') ?: '[]', true, 512, JSON_THROW_ON_ERROR),
    static fn ($s): bool => is_array($s)
        && isset($s['slug'], $s['route'], $s['target'])
        && preg_match('/^[a-z0-9-]{1,120}$/', (string) $s['slug']) === 1
        && str_starts_with((string) $s['route'], '/'),
));

beforeEach(function () {
    // CROSS-PROCESS DB (idêntico AuthBridge/Caixa): o browser usa MySQL (.env), o test process
    // usa sqlite :memory: (phpunit.xml) — realinha pro MESMO MySQL pra resolver o admin.
    config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'oimpresso_test']);
    DB::purge('mysql');
});

/** Admin do tenant fictício do gate. Falha ALTO: sem tenant não há smoke, e skip seria verde-vazio. */
function designSmokeAdmin(): User
{
    $business = Business::orderBy('id')->first();
    if (! $business) {
        throw new RuntimeException('Sem business seedado: o VisregTenantSeeder não rodou.');
    }
    $admin = User::where('business_id', $business->id)->orderBy('id')->first();
    if (! $admin) {
        throw new RuntimeException('Sem user no business seedado: não dá pra autenticar.');
    }

    // Mesmo idioma do PixelBaselineTest: o `Gate::before` do UPos libera qualquer ability pra
    // quem tem `Admin#{biz}`; o seed cria o user sem role. Sufixo obrigatório (roles.business_id
    // NOT NULL + FK). Idempotente.
    $roleName = 'Admin#'.$business->id;
    if (! $admin->hasRole($roleName)) {
        $admin->assignRole(Role::firstOrCreate([
            'name'        => $roleName,
            'business_id' => $business->id,
            'guard_name'  => 'web',
        ]));
    }

    return $admin;
}

/**
 * Espera a tela EXISTIR antes de medir: Inertia montada (root com data-page + texto no body) e a
 * fonte self-hosted disponível — o `fonts.check` só vira true depois que algum texto que a usa foi
 * renderizado. Teto de ~10s; quem falha alto é o `aguardarFontesReais` logo depois, com a
 * mensagem canônica.
 */
function designSmokeAguardarMontagem(object $page): void
{
    for ($tentativa = 0; $tentativa < 20; $tentativa++) {
        $pronto = $page->script(<<<'JS'
            (async () => {
              const root = document.querySelector('[data-page]');
              const texto = (document.body && document.body.innerText || '').trim().length;
              await document.fonts.ready;
              return !!root && texto > 50 && document.fonts.check('16px "IBM Plex Sans"');
            })()
        JS);
        if ($pronto === true) {
            return;
        }
        $page->wait(0.5);
    }
}

if ($designSmokeScreens === []) {
    it('design-smoke · nenhuma tela elegível em DESIGN_SMOKE_SCREENS', function () {
        $this->markTestSkipped('DESIGN_SMOKE_SCREENS vazio — nada tested|validated com rota derivável (smoke-consumir.mjs --select).');
    });
} else {
    $dataset = [];
    foreach ($designSmokeScreens as $s) {
        $dataset[(string) $s['slug']] = [(string) $s['slug'], (string) $s['route'], (string) $s['target']];
    }

    it('design-smoke · renderiza autenticado no biz=1 e salva o PNG durável', function (string $slug, string $route, string $target) {
        $admin = designSmokeAdmin();

        $page = visit('/_visreg-login/'.$admin->id.'?to='.urlencode($route))->resize(1280, 800);

        // O auth-bridge redireciona pra rota; se caiu no /login, a sessão não pegou e a foto
        // seria da tela errada — recibo de nada.
        $pathname = (string) $page->script('(() => window.location.pathname)()');
        expect($pathname)->not->toStartWith('/login');

        // Status HTTP da navegação final (Chromium expõe responseStatus no Navigation Timing).
        // 403/404/500 renderizam página com cara de tela; o smoke não pode fotografar isso.
        $status = $page->script('(() => { const n = performance.getEntriesByType("navigation")[0]; return n && typeof n.responseStatus === "number" ? n.responseStatus : null; })()');
        if ($status !== null) {
            expect((int) $status)->toBe(200, "rota {$route} respondeu HTTP {$status} — não é tela de pé");
        }

        // Página de erro do Laravel/Whoops tem esses textos; tela viva não tem.
        $page->assertDontSee('Server Error')->assertDontSee('Whoops, looks like something went wrong');

        // Espera a tela montar (Inertia + fonte usada por texto real) e só então exige a fonte —
        // a ordem inversa foi a causa do 1º run vermelho (ver cabeçalho).
        designSmokeAguardarMontagem($page);
        VisregThreshold::aguardarFontesReais($page);
        $page->wait(1.5);

        // `screenshot(false, $nome)` grava em tests/Browser/Screenshots/<nome>.png
        // (VisregThreshold::screenshotPath). Copio pro destino durável que o workflow publica.
        $page->screenshot(false, 'design-smoke-'.$slug);
        $origem = base_path('tests/Browser/Screenshots/design-smoke-'.$slug.'.png');
        expect(is_file($origem))->toBeTrue("screenshot não foi gravado em {$origem}");

        $dir = storage_path('design-smokes');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $destino = $dir.'/'.$slug.'.png';
        expect(copy($origem, $destino))->toBeTrue();
        expect(filesize($destino))->toBeGreaterThan(0);

        // Sidecar legível pro manifesto/step summary: onde a foto foi tirada de fato.
        file_put_contents($dir.'/'.$slug.'.json', json_encode([
            'slug' => $slug, 'route' => $route, 'target' => $target, 'renderedPath' => $pathname,
            'viewport' => '1280x800', 'capturedAt' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    })->with($dataset);
}

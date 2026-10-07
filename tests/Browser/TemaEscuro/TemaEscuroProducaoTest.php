<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — superfície CLARA no tema escuro, nas Pages Inertia de produção.
 * Playbook `tema-escuro` thread 02 (a thread 01 mede o espelho do protótipo; esta mede o React).
 *
 * ── O QUE FAZ ────────────────────────────────────────────────────────────────
 * Para cada tela de `tests/Browser/visreg-screens.json` (o dono das Pages que renderizam no seed
 * do CI, com rota medida pelos gates visuais): loga como o admin do biz=1 FICTÍCIO do
 * VisregTenantSeeder com `ui_theme=dark`, espera a tela montar e estabilizar, e grava em
 * `storage/tema-escuro/<slug>.json` todo fundo não-transparente de `main.main-body`
 * (≥10×6 px, visível, fora da ALLOWLIST). Também mede o estado "linha selecionada" (1ª checkbox
 * de `tbody`), onde mora a barra de lote. Na 1ª tela grava a SANIDADE: um `<div>` branco
 * injetado tem de ser julgado claro, senão a sonda está cega.
 *
 * ── QUEM JULGA ────────────────────────────────────────────────────────────────
 * Este arquivo NÃO decide o que é "claro". O corte (L>0,78, C<0,1, alfa≥0,5) e a ALLOWLIST moram
 * só em `scripts/design/tema-escuro-probe.mjs`: a allowlist entra aqui por `--allowlist`, e o
 * veredito sai de `--producao storage/tema-escuro` (1 linha por Page). Copiar o corte para PHP
 * seria um segundo dono do mesmo limiar.
 *
 * ── O QUE O TESTE AFIRMA ─────────────────────────────────────────────────────
 * Só que a tela RENDERIZOU no escuro e foi medida. Superfície clara NÃO reprova aqui: a lista é o
 * insumo da thread 03 e não há baseline de produção (baseline nova é decisão [W]). Tela que cai no
 * /login, responde ≠200 ou não escurece fica registrada com o estado — nunca como "0 claros".
 *
 * ── TENANT / TEMA ────────────────────────────────────────────────────────────
 * biz=1 fictício (`oimpresso_test`); biz=4 é proibido em teste (ADR 0358). O `ui_theme` do admin é
 * gravado como `dark` e RESTAURADO no `finally` — os steps seguintes do job usam o mesmo admin.
 *
 * ── EXECUÇÃO (CI — nunca local: memory/proibicoes.md + ADR 0062) ─────────────
 *   TEMA_ESCURO_SCREENS='["Sells/CreateV3"]' (opcional, filtra) ./vendor/bin/pest tests/Browser/TemaEscuro/
 *   node scripts/design/tema-escuro-probe.mjs --producao storage/tema-escuro
 *
 * HONESTIDADE: escrito numa worktree sem `vendor/` nem PHP; não foi executado antes do PR. Usa só
 * API já verde nos Browser tests deste repo (`visit`·`resize`·`script`·`wait`), o auth-bridge
 * `/_visreg-login` e o mesmo alinhamento de DB do DesignSmokeTest. O 1º run do step é a prova.
 *
 * @see scripts/design/tema-escuro-probe.mjs (allowlist + julgamento)
 * @see prototipo-ui/cowork/Wagner/cowork-inbox/tema-escuro/playbook/02-producao.md
 */

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

$temaEscuroFiltro = json_decode(getenv('TEMA_ESCURO_SCREENS') ?: '[]', true) ?: [];
$temaEscuroTelas = array_values(array_filter(
    json_decode((string) file_get_contents(__DIR__.'/../visreg-screens.json'), true, 512, JSON_THROW_ON_ERROR),
    static fn ($s): bool => is_array($s)
        && isset($s['screen'], $s['route'])
        && str_starts_with((string) $s['route'], '/')
        && ($temaEscuroFiltro === [] || in_array($s['screen'], $temaEscuroFiltro, true)),
));

beforeEach(function () {
    // CROSS-PROCESS DB (idêntico DesignSmoke/AuthBridge): o browser usa o MySQL, o processo de
    // teste usa sqlite :memory: (phpunit.xml) — realinha pro MESMO MySQL pra mexer no admin.
    config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'oimpresso_test']);
    DB::purge('mysql');
});

function temaEscuroAdmin(): User
{
    $business = Business::orderBy('id')->first();
    if (! $business) {
        throw new RuntimeException('Sem business seedado: o VisregTenantSeeder não rodou.');
    }
    $admin = User::where('business_id', $business->id)->orderBy('id')->first();
    if (! $admin) {
        throw new RuntimeException('Sem user no business seedado: não dá pra autenticar.');
    }
    $roleName = 'Admin#'.$business->id;
    if (! $admin->hasRole($roleName)) {
        $admin->assignRole(Role::firstOrCreate([
            'name' => $roleName, 'business_id' => $business->id, 'guard_name' => 'web',
        ]));
    }

    return $admin;
}

/** ALLOWLIST do dono (a sonda). Sem ela a medida mudaria de régua em silêncio — falha alto. */
function temaEscuroAllowlist(): array
{
    static $lista = null;
    if ($lista === null) {
        $saida = shell_exec('node '.escapeshellarg(base_path('scripts/design/tema-escuro-probe.mjs')).' --allowlist');
        $lista = json_decode((string) $saida, true);
        if (! is_array($lista) || $lista === []) {
            throw new RuntimeException('NÃO MEDI — não consegui ler a ALLOWLIST de tema-escuro-probe.mjs --allowlist.');
        }
    }

    return $lista;
}

/** Coletor que roda NA PÁGINA (mesmo recorte do `coletarNaPagina` da sonda, raiz do AppShellV2). */
function temaEscuroColetorJs(array $allow): string
{
    $allowJson = json_encode($allow, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    return <<<JS
        (() => {
          const allow = {$allowJson};
          const main = document.querySelector('main.main-body');
          if (!main) return null;
          const vistos = new Map();
          for (const e of main.querySelectorAll('*')) {
            const cs = getComputedStyle(e);
            if (cs.display === 'none' || cs.visibility === 'hidden' || +cs.opacity === 0) continue;
            const bg = cs.backgroundColor;
            if (!bg || bg === 'rgba(0, 0, 0, 0)' || bg === 'transparent') continue;
            const r = e.getBoundingClientRect();
            if (r.width < 10 || r.height < 6) continue;
            if (allow.some((s) => { try { return e.closest(s); } catch (_) { return false; } })) continue;
            const cls = [...e.classList].filter((c) => /^[a-zA-Z_-]/.test(c)).slice(0, 2).map((c) => '.' + CSS.escape(c)).join('');
            const sel = e.tagName.toLowerCase() + cls;
            const k = sel + '|' + bg;
            const v = vistos.get(k);
            if (v) v.n++; else vistos.set(k, { seletor: sel, bg, n: 1 });
          }
          return [...vistos.values()];
        })()
    JS;
}

/** Tela montada (Inertia + texto) e DOM estável (2 leituras iguais do nº de nós), teto ~12s. */
function temaEscuroAguardar(object $page): void
{
    $anterior = -1;
    for ($i = 0; $i < 24; $i++) {
        $n = $page->script(<<<'JS'
            (() => {
              const root = document.querySelector('[data-page]');
              const texto = (document.body && document.body.innerText || '').trim().length;
              return root && texto > 50 ? document.querySelectorAll('*').length : -1;
            })()
        JS);
        if (is_int($n) && $n > 0 && $n === $anterior) {
            return;
        }
        $anterior = $n;
        $page->wait(0.5);
    }
}

if ($temaEscuroTelas === []) {
    it('tema-escuro produção · nenhuma tela no visreg-screens.json (ou filtro vazio)', function () {
        $this->markTestSkipped('Sem telas — TEMA_ESCURO_SCREENS não casou nada. Isto NÃO é "tema ok".');
    });
} else {
    $dataset = [];
    foreach ($temaEscuroTelas as $i => $s) {
        $dataset[(string) $s['screen']] = [(string) $s['screen'], (string) $s['route'], $i === 0];
    }

    it('tema-escuro produção · mede superfície clara no escuro', function (string $screen, string $route, bool $comSanidade) {
        $admin = temaEscuroAdmin();
        $allow = temaEscuroAllowlist();
        $original = $admin->ui_theme;
        $dir = storage_path('tema-escuro');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $slug = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $screen));
        $registro = ['screen' => $screen, 'route' => $route, 'estado' => 'ok', 'escuro' => false, 'fundos' => []];

        $admin->ui_theme = 'dark';
        $admin->save();
        try {
            $page = visit('/_visreg-login/'.$admin->id.'?to='.urlencode($route))->resize(1280, 900);

            $pathname = (string) $page->script('(() => window.location.pathname)()');
            $status = $page->script('(() => { const n = performance.getEntriesByType("navigation")[0]; return n && typeof n.responseStatus === "number" ? n.responseStatus : null; })()');
            if (str_starts_with($pathname, '/login')) {
                $registro['estado'] = 'login';
            } elseif ($status !== null && (int) $status !== 200) {
                $registro['estado'] = 'http-'.(int) $status;
            } else {
                temaEscuroAguardar($page);
                $registro['escuro'] = (bool) $page->script(
                    '(() => document.documentElement.classList.contains("dark") && !!document.querySelector(".cockpit[data-theme=dark]"))()'
                );
                $base = $page->script(temaEscuroColetorJs($allow));
                if ($base === null) {
                    $registro['estado'] = 'sem-main';
                } else {
                    $fundos = is_array($base) ? $base : [];
                    // Estado escondido: linha selecionada (barra de lote).
                    $selecionou = $page->script('(() => { const cb = document.querySelector("main.main-body tbody input[type=checkbox]"); if (!cb) return false; cb.click(); return true; })()');
                    if ($selecionou === true) {
                        $page->wait(0.4);
                        $sel = $page->script(temaEscuroColetorJs($allow));
                        $fundos = array_merge($fundos, is_array($sel) ? $sel : []);
                        $registro['estados'] = ['selecao'];
                    }
                    $registro['fundos'] = $fundos;
                    if ($comSanidade) {
                        $page->script('(() => { const m = document.querySelector("main.main-body"); const d = document.createElement("div"); d.className = "tema-escuro-sanidade"; d.style.cssText = "background:#fff;width:40px;height:20px"; m.prepend(d); })()');
                        $registro['sanidade'] = $page->script(temaEscuroColetorJs($allow)) ?: [];
                    }
                }
            }
        } finally {
            $admin->ui_theme = $original;
            $admin->save();
            file_put_contents($dir.'/'.$slug.'.json', json_encode($registro, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
        }

        expect($registro['estado'])->toBe('ok', "{$screen} ({$route}) não renderizou: {$registro['estado']}");
        expect($registro['escuro'])->toBeTrue("{$screen} ({$route}) não aplicou o tema escuro no <html>/.cockpit");
    })->with($dataset);
}

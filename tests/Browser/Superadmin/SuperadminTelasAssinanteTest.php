<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — as outras 6 telas do Superadmin no navegador, com axe (auditoria do
 * Superadmin, lote L7, PR 2 de 3). O PR 1 é o `SuperadminTelasPlataformaTest`.
 *
 * ── AS 6 TELAS, E POR QUE ESTÃO JUNTAS ───────────────────────────────────────
 *   - Páginas, Usuário 360° (lista) e Usuário 360° (raio-X): atrás do middleware `superadmin`;
 *   - Minha assinatura e Pagar assinatura: do DONO do negócio (permissão
 *     `superadmin.access_package_subscriptions`), rotas fora do prefixo `/superadmin`;
 *   - Site/Pricing: vitrine pública, sem login.
 *
 * ── COMO ENTRA ───────────────────────────────────────────────────────────────
 * Usuário `VisregEmptyTenantSeeder::USER_EMPTY` (id 98, business 98 — tenant fictício,
 * ADR 0358; NUNCA biz=4). Para as telas `superadmin`, o `beforeEach` põe o username dele em
 * `constants.administrator_usernames` — o workflow não define ADMINISTRATOR_USERNAMES (ver o
 * docblock do `SuperadminTelasPlataformaTest`). Para Minha assinatura e Pagar, o papel
 * `Admin#98` já concede a permissão pelo `Gate::before`. O servidor do Pest Browser roda no
 * mesmo processo do teste, então `config()` e `app()->instance()` chegam às requisições.
 *
 * ── PAGAR ────────────────────────────────────────────────────────────────────
 * A tela React fica atrás da flag `useV2SuperadminPagar` (UC-SAPG-01). Aqui ela é ligada por
 * stub do `FeatureFlagService`, o mesmo desenho do `SuperadminPagarContratoTest`. O caso semeia
 * um pacote pago marcado pelo nome, `app_currency_id` se faltar e o pagamento offline ligado,
 * e desfaz tudo no `->after()`.
 *
 * ── O QUE PROVA ──────────────────────────────────────────────────────────────
 *   1. a tela monta, sem a página de erro 403;
 *   2. as âncoras que existem em toda carga chegam ao DOM e nenhum skeleton sobra;
 *   3. zero violação axe CRITICAL (`level: 0`, o piso do `A11yAxeBrowserTest`).
 * Regra de negócio é dos `*ContratoTest` do módulo; pixel não é medido aqui.
 *
 * ── EXECUÇÃO (CI / CT 100 — nunca local) ─────────────────────────────────────
 * Step "E2E de render · Superadmin assinante (ADVISORY)" do `visual-regression.yml`.
 *
 * @see Modules/Superadmin/Resources/js/Pages/superadmin/Paginas/Index.tsx
 * @see Modules/Superadmin/Resources/js/Pages/superadmin/Usuario360/Index.tsx
 * @see Modules/Superadmin/Resources/js/Pages/superadmin/Usuario360/Show.tsx
 * @see Modules/Superadmin/Resources/js/Pages/superadmin/MinhaAssinatura/Index.tsx
 * @see Modules/Superadmin/Resources/js/Pages/superadmin/Pagar/Index.tsx
 * @see Modules/Superadmin/Resources/js/Pages/Site/Pricing.tsx
 */

use App\User;
use Database\Seeders\VisregEmptyTenantSeeder;
use Illuminate\Support\Facades\DB;

const SP_ASS_PACOTE = 'Pacote ficticio browser pagar L7';

beforeEach(function () {
    config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'oimpresso_test']);
    DB::purge('mysql');

    $super = User::find(VisregEmptyTenantSeeder::USER_EMPTY);
    if (! $super || (int) $super->business_id !== VisregEmptyTenantSeeder::BIZ_EMPTY) {
        throw new RuntimeException('Sem o usuario 98 do business 98: o VisregEmptyTenantSeeder nao rodou.');
    }
    config(['constants.administrator_usernames' => $super->username]);
});

/** Probe: 403 visível, âncoras que faltam, textos que faltam, skeleton que sobra — ou PRONTO. */
function spAssProntoJs(array $contratos, array $textos): string
{
    $sel = json_encode(array_map(fn ($c) => '[data-contract="'.$c.'"]', $contratos));
    $txt = json_encode($textos);

    return "(() => { const t = document.body ? document.body.innerText : '';"
        ." if (/Unauthorized action/i.test(t)) return 'ERRO-403';"
        ." const falta = {$sel}.filter((s) => !document.querySelector(s));"
        ." if (falta.length) return 'FALTA ' + falta.join(',');"
        ." const semTexto = {$txt}.filter((x) => !t.includes(x));"
        ." if (semTexto.length) return 'SEM-TEXTO ' + semTexto.join(',');"
        ." const sk = document.querySelectorAll('[data-slot=\"skeleton\"]').length;"
        ." return sk ? 'SKELETON ' + sk : 'PRONTO'; })()";
}

/** Abre a rota (logado como o usuário 98, ou anônimo) e espera estabilizar. */
function spAssAbrir(string $rota, array $contratos, array $textos = [], bool $logado = true)
{
    $url = $logado
        ? '/_visreg-login/'.VisregEmptyTenantSeeder::USER_EMPTY.'?to='.urlencode($rota)
        : $rota;
    $page = visit($url)->resize(1280, 800);

    $js = spAssProntoJs($contratos, $textos);
    $ultimo = null;
    for ($i = 0; $i < 40; $i++) {
        $ultimo = (string) $page->script($js);
        if ($ultimo === 'PRONTO') {
            return $page;
        }
        $page->wait(0.25);
    }

    throw new RuntimeException("A tela {$rota} nao estabilizou — ultimo estado: '{$ultimo}'.");
}

/** Pacote pago e ativo, marcado pelo nome; devolve o id. Mesma forma do contrato de Pagar. */
function spAssPacote(): int
{
    $id = DB::table('packages')->where('name', SP_ASS_PACOTE)->value('id');

    return (int) ($id ?? DB::table('packages')->insertGetId([
        'name' => SP_ASS_PACOTE, 'description' => 'So para o teste de navegador de Pagar assinatura.',
        'location_count' => 2, 'user_count' => 0, 'product_count' => 0, 'invoice_count' => 0,
        'interval' => 'months', 'interval_count' => 1, 'trial_days' => 15, 'price' => 99.9,
        'created_by' => 1, 'sort_order' => 99, 'is_active' => 1, 'is_private' => 0, 'is_one_time' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]));
}

it('UC-SAPAG-01 · Páginas monta em React como superadmin, com a lista resolvida e sem violação axe CRITICAL', function () {
    $page = spAssAbrir('/superadmin/frontend-pages', ['superadmin.paginas.nova-botao'], ['Páginas do site']);

    $page->assertNoAccessibilityIssues(level: 0);
});

it('UC-SAUL-01 · Usuário 360° (lista) monta em Inertia como superadmin e sem violação axe CRITICAL', function () {
    // Sem busca a tela pede uma: o estado inicial é o "Comece uma busca", nunca a lista inteira.
    $page = spAssAbrir('/superadmin/usuarios', [], ['Usuário 360°', 'Comece uma busca']);

    $page->assertNoAccessibilityIssues(level: 0);
});

it('UC-SAUX-01 · Usuário 360° (raio-X) monta em Inertia como superadmin e sem violação axe CRITICAL', function () {
    // O raio-X é do admin do tenant visreg (id 1), não do próprio usuário 98.
    $alvo = User::where('business_id', 1)->orderBy('id')->first();
    if (! $alvo) {
        throw new RuntimeException('Sem user no business 1: o VisregTenantSeeder nao rodou.');
    }

    $page = spAssAbrir("/superadmin/usuarios/{$alvo->id}/360", [], ['Usuário 360°']);

    $page->assertNoAccessibilityIssues(level: 0);
});

it('UC-SAMA-01 · Minha assinatura monta em Inertia para o dono do negócio e sem violação axe CRITICAL', function () {
    $page = spAssAbrir('/subscription', [
        'superadmin.minha-assinatura.situacao', 'superadmin.minha-assinatura.pacotes',
    ], ['Minha assinatura']);

    $page->assertNoAccessibilityIssues(level: 0);
});

it('UC-SAPG-01 · Pagar assinatura monta em Inertia com a flag ligada e sem violação axe CRITICAL', function () {
    app()->instance(\App\Services\FeatureFlagService::class, new class extends \App\Services\FeatureFlagService
    {
        public function __construct() {}

        public function isOn(string $flag, array $attrs = []): bool
        {
            return $flag === 'useV2SuperadminPagar';
        }
    });

    $semeado = [];
    if (! DB::table('system')->where('key', 'app_currency_id')->exists()) {
        DB::table('system')->insert(['key' => 'app_currency_id', 'value' => (string) DB::table('currencies')->orderBy('id')->value('id')]);
        $semeado[] = 'app_currency_id';
    }
    $this->spAssOfflineAntes = DB::table('system')->where('key', 'enable_offline_payment')->value('value');
    $this->spAssSemeado = $semeado;
    DB::table('system')->updateOrInsert(['key' => 'enable_offline_payment'], ['value' => '1']);

    $page = spAssAbrir('/subscription/'.spAssPacote().'/pay', [
        'superadmin.pagar.pacote', 'superadmin.pagar.formas',
    ], ['Pagar assinatura']);

    $page->assertNoAccessibilityIssues(level: 0);
})->after(function () {
    // `->after()` roda mesmo se o corpo parou antes de semear: só desfaz o que foi feito.
    foreach ($this->spAssSemeado ?? [] as $chave) {
        DB::table('system')->where('key', $chave)->delete();
    }
    if (property_exists($this, 'spAssOfflineAntes')) {
        $this->spAssOfflineAntes === null
            ? DB::table('system')->where('key', 'enable_offline_payment')->delete()
            : DB::table('system')->where('key', 'enable_offline_payment')->update(['value' => $this->spAssOfflineAntes]);
    }
    DB::table('packages')->where('name', SP_ASS_PACOTE)->delete();
    app()->forgetInstance(\App\Services\FeatureFlagService::class);
});

it('UC-SPRIC-01 · Site/Pricing abre sem login e sem violação axe CRITICAL', function () {
    $page = spAssAbrir('/pricing', [], ['Pague só pelo que sua operação'], logado: false);

    $page->assertNoAccessibilityIssues(level: 0);
});

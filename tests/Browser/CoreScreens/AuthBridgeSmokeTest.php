<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — SMOKE AUTENTICADO via AUTH BRIDGE cross-process (US-GOV-013 Fase B).
 *
 * Destrava o que estava bloqueado: as telas AUTENTICADAS (99% do app, onde mora o risco
 * visual) agora rodam no gate. Espelha tests/Browser/Public/PublicSmokeTest.php (Fase A),
 * mas atravessa o auth:
 *
 *   - O browser Playwright roda em SUBPROCESSO → a sessão do test process não cruza.
 *   - A rota /_visreg-login/{id}?to=<tela> (routes/web.php, env-guarded !isProduction)
 *     loga o user DENTRO do subprocesso do server e redireciona pra tela → 1 visit só,
 *     já autenticada.
 *   - Requer SESSION_DRIVER=file no .env do gate (array não persiste cross-request).
 *   - Dados committados (browser NÃO usa RefreshDatabase — ver tests/Pest.php); biz=1 +
 *     permissions vêm do schema-squash (#2221). `firstOrCreate` da permission é cinto+
 *     suspensório caso o slug não esteja seedado.
 *
 * SEM guard de skip (igual PublicSmokeTest): roda só pelo path que o workflow invoca
 * explicitamente (chromium garantido). Locators por TEXTO, nunca classe CSS (L-24).
 *
 * @see .github/workflows/visual-regression.yml
 * @see routes/web.php (rota _visreg-login, guard !isProduction)
 */

use App\User;
use Spatie\Permission\Models\Permission;

beforeEach(fn () => \Carbon\Carbon::setTestNow('2026-06-06 12:00:00'));
afterEach(fn () => \Carbon\Carbon::setTestNow());

/** Tela => [rota, slug-permissão, âncora-de-texto que prova que montou (não 403/login/erro)]. */
$screens = [
    'Financeiro/Unificado' => ['/financeiro/unificado', 'financeiro.unificado.access', 'Financeiro'],
    'Venda/Lista'          => ['/sells',                 'sell.view',                   'Vendas'],
];

foreach ($screens as $nome => [$rota, $permissao, $ancora]) {
    it("{$nome} renderiza AUTENTICADA sem erro de console (auth bridge)", function () use ($rota, $permissao, $ancora) {
        $user = User::factory()->create(['business_id' => 1]);
        Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'web']);
        try {
            $user->givePermissionTo($permissao);
        } catch (\Throwable) {
            // slug pode variar por módulo — não falha o smoke por isso (a âncora é o gate).
        }

        // 1 visit: loga no subprocesso + redireciona pra tela → carrega autenticada.
        visit('/_visreg-login/' . $user->id . '?to=' . urlencode($rota))
            ->assertSee($ancora)
            ->assertNoConsoleLogs();
    });
}

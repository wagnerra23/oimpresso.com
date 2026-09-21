<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — hospeda o LOTE DE PARIDADE no servidor do Pest (ADR 0408).
 *
 * ── POR QUE ESTE ARQUIVO EXISTE ───────────────────────────────────────────────
 * O `scripts/design/design-diff-lote.mjs` renderiza protótipo e vivo com a MESMA sonda e
 * precisa de um HTTP de pé em `--base-url`. A primeira tentativa subiu `php artisan serve` no
 * próprio step do workflow, e ela falhou QUATRO vezes seguidas, sempre igual: 23 ERRO de
 * `net::ERR_CONNECTION_REFUSED` em 23 telas tentadas, com o servidor VIVO e registrando
 * respostas no log (runs 35590027120 · 35590936821 · 35592105902).
 *
 * A causa é de arquitetura, não de configuração, e está no `composer.lock`: o
 * `pestphp/pest-plugin-browser` v4.3.1 traz **`amphp/http-server`** — servidor assíncrono,
 * multiplexado. O `php artisan serve` usa o servidor EMBUTIDO do PHP, single-threaded; com
 * `--no-reload` + `PHP_CLI_SERVER_WORKERS` ele até forka, mas não sustentou a carga real de
 * cada tela (documento + redirect do auth-bridge + assets do bundle Inertia em paralelo).
 * O servidor aceitava a primeira conexão e recusava as seguintes — por isso o log mostrava
 * `/_visreg-login/1` RESPONDIDO e o Playwright, na mesma URL, dizia refused.
 *
 * Decisão [W] em 2026-09-21, depois de ver as 4 runs: **"usa o servidor do Pest Browser"**.
 * É o que este arquivo faz. E a evidência de que funciona já existia na MESMA run que falhava:
 * o `DesignSmokeTest` fotografa telas com sucesso, logado, no mesmo CI — eu é que estava
 * subindo um segundo servidor ao lado de um que já servia.
 *
 * ── O QUE FAZ ─────────────────────────────────────────────────────────────────
 *   1. `visit('/login')` — obriga o Pest a subir o servidor Amp;
 *   2. lê `window.location.origin` DO PRÓPRIO navegador. A URL é MEDIDA, não adivinhada: não
 *      há porta fixa nem env a supor, e se o Pest mudar de porta isto continua certo;
 *   3. roda o lote por subprocesso contra essa origin, com o admin do tenant fictício.
 *
 * ── O QUE NÃO FAZ ─────────────────────────────────────────────────────────────
 *   • Não dá veredito de fidelidade: quem julga é o `design-diff --check`, dentro do lote.
 *   • Não falha por DIVERGÊNCIA. Este teste só falha quando a MEDIÇÃO não aconteceu —
 *     é a diferença entre "as telas divergem" (dado, advisory) e "não consegui medir"
 *     (defeito). Render pareado NUNCA bloqueia merge (ADR 0290 segue recusada; a 0408 libera
 *     medir, não bloquear).
 *   • Não escreve baseline: o lote grava em `governance/design/targets/medidas/`, o workflow
 *     sobe como artifact e NADA é commitado ([W] vetou baseline congelada).
 *
 * ── TENANT ────────────────────────────────────────────────────────────────────
 * biz 1 FICTÍCIO do `VisregTenantSeeder` no MySQL `oimpresso_test`. `biz=4` é PROIBIDO em
 * teste (ADR 0358). Mesmo idioma do `DesignSmokeTest` ao lado.
 *
 * ── EXECUÇÃO (CI — nunca local: memory/proibicoes.md + ADR 0062) ──────────────
 *   PARIDADE_LOTE=1 ./vendor/bin/pest tests/Browser/DesignSmoke/ParidadeLoteTest.php
 *
 * Sem `PARIDADE_LOTE=1` o teste é SKIPPED com motivo visível — ele leva ~20min e não pode
 * entrar de carona nas lanes Pest comuns. Skip explícito, nunca verde-vazio (LC-13).
 *
 * @see scripts/design/design-diff-lote.mjs   (a medição)
 * @see scripts/design/lote-resumo-ci.mjs     (o consumidor do resultado)
 * @see .github/workflows/design-smoke-ci.yml (quem invoca)
 * @see memory/decisions/0408-medicao-de-paridade-agendada-advisory-emenda-0290.md
 */

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // CROSS-PROCESS DB (idêntico ao DesignSmokeTest ao lado): o browser usa MySQL (.env), o
    // processo de teste usa sqlite :memory: (phpunit.xml) — realinha pro MESMO MySQL.
    config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'oimpresso_test']);
    DB::purge('mysql');
});

/** Admin do tenant fictício. Falha ALTO: sem tenant não há medição, e skip seria verde-vazio. */
function paridadeLoteAdmin(): User
{
    $business = Business::orderBy('id')->first();
    if (! $business) {
        throw new RuntimeException('Sem business seedado: o VisregTenantSeeder não rodou.');
    }
    $admin = User::where('business_id', $business->id)->orderBy('id')->first();
    if (! $admin) {
        throw new RuntimeException('Sem user no business seedado: não dá pra autenticar.');
    }

    // O `Gate::before` do UPos libera qualquer ability pra quem tem `Admin#{biz}`; o seed cria
    // o user sem role. Sufixo obrigatório (roles.business_id NOT NULL + FK). Idempotente.
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

it('mede a paridade protótipo × vivo em lote, contra o servidor do Pest', function () {
    if (getenv('PARIDADE_LOTE') !== '1') {
        test()->markTestSkipped('PARIDADE_LOTE != 1 — o lote leva ~20min e só roda quando pedido (schedule/dispatch).');
    }

    $admin = paridadeLoteAdmin();

    // (1) Sobe o servidor Amp do Pest e (2) MEDE a origin dele no próprio navegador.
    // Nada de porta fixa: se o Pest escolher outra, isto continua certo.
    $page   = visit('/login');
    $origin = $page->script('window.location.origin');
    $origin = is_array($origin) ? ($origin[0] ?? null) : $origin;

    expect($origin)->toBeString()
        ->and($origin)->toStartWith('http');

    // (3) O lote, contra o servidor que JÁ está de pé e já provou aguentar as telas.
    // 75min de teto: 23 telas executáveis × 2 renders, com folga pra CDN lenta. O lote nunca
    // trava sozinho — cada tela tem timeout próprio e vira NÃO MEDI/ERRO no RESUMO.
    // `path(base_path())` NÃO é enfeite: o lote resolve `prototipo-ui/`, `governance/` e o
    // `application-report.json` por caminho RELATIVO, e o cwd do processo de teste não é
    // garantido. Sem isto ele mediria a partir do diretório errado — e o modo de falha seria
    // "0 telas selecionadas", que se lê como "nada a medir" em vez de "medi no lugar errado".
    $resultado = Process::path(base_path())->timeout(4500)->run(sprintf(
        'node scripts/design/design-diff-lote.mjs --base-url %s --user-id %d',
        escapeshellarg($origin),
        $admin->id,
    ));

    // O stdout do lote é o diagnóstico por tela. Vai pro log do CI inteiro, não truncado:
    // foi lendo ESTE texto que as 4 falhas anteriores foram diagnosticadas.
    echo $resultado->output();
    if ($resultado->errorOutput() !== '') {
        echo "\n--- stderr ---\n".$resultado->errorOutput();
    }

    $resumo = base_path('governance/design/targets/medidas/RESUMO.md');
    expect(is_file($resumo))->toBeTrue('o lote não gravou RESUMO.md — não houve medição (ausência de medida não é "tudo igual", §5 2026-07-29)');

    // FALHA SÓ QUANDO NÃO MEDIU. Divergência é DADO (advisory); "não consegui medir" é DEFEITO.
    // `ERRO` no RESUMO é erro de NAVEGAÇÃO (refused/timeout), não de fidelidade — foi
    // exatamente o que mascarou as 4 runs anteriores, que saíam `success` com 23/23 ERRO.
    $linhas = preg_grep('/^\| /', file($resumo, FILE_IGNORE_NEW_LINES)) ?: [];
    $linhas = preg_grep('/^\| (Tela|-{3})/', $linhas, PREG_GREP_INVERT) ?: [];
    $erros  = count(preg_grep('/\| ERRO \|/', $linhas) ?: []);

    expect($linhas)->not->toBeEmpty('RESUMO.md sem nenhuma linha de tela — o lote não mediu nada.');
    expect($erros)->toBe(
        0,
        "{$erros} de ".count($linhas)." telas falharam na NAVEGAÇÃO (ERRO), não na comparação. ".
        'Isso é falha de medição, não divergência de design — ver o stdout do lote acima.',
    );
})->group('paridade-lote');

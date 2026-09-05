<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — E2E DE COMPORTAMENTO da ação **Etiquetas** em `Purchase/Index`.
 *
 * ── A REGRESSÃO QUE ISTO IMPEDE (já aconteceu, com data e relato) ─────────────
 * A ação existia no dropdown Blade de `/purchases` — incondicional, sem permissão — e
 * **não foi portada** na migração MWART para React. Larissa (ROTA LIVRE, biz=4, 99% do
 * volume de vendas) reportou por WhatsApp em 2026-06-17: *"cadastrei umas peças e não tem
 * opção de imprimir as etiquetas das compras"*. Foi corrigida (`Index.tsx:303`), e até este
 * arquivo **nada impedia que voltasse**: medido em `origin/main`, nenhum assert do repo cita
 * `labels/show`, `purchase_id=` ou `Barcode` no contexto desta tela. O contrato vivia só em
 * prosa, no RUNBOOK §2 — e prosa não impede regressão.
 *
 * O dual-path **esconde a falta**: quem confere pelo Blade vê a ação e conclui que está tudo
 * certo. É por isso que esta classe some sem alarme — e por isso a defesa tem de ser no React.
 *
 * ── POR QUE O ASSERT É `window.open`, E NÃO UM LINK ──────────────────────────
 * Medido antes de escrever: a ação **não é** um `<a href>`. É um `<Button>` com
 * `onClick={() => window.open('/labels/show?purchase_id=' + id, '_blank')}`
 * (`Index.tsx:303`). Nenhuma URL chega ao DOM — procurar `a[href*="labels/show"]` daria
 * falso-negativo garantido, e casar a string no fonte seria mais um presence-gate (LC-11,
 * a classe que o `casos.md` desta tela já declara como dívida).
 *
 * Então o probe **substitui `window.open`, clica de verdade e lê o que foi chamado**. Isso
 * cobre os dois UC de uma vez, e é o mesmo mecanismo que os prova:
 *   - remover o botão            → `ACAO-AUSENTE`
 *   - trocar a URL               → string diferente
 *   - trocar por `router.visit`  → `NAO-CHAMOU`  ← este é o UC-PURIDX-05
 *
 * ── CONTROLE POSITIVO (o que impede este teste de ser vácuo) ─────────────────
 * O botão **Imprimir** (`/purchases/print/{id}`) usa o MESMO mecanismo. O caso 2 captura os
 * dois na mesma montagem: se o interceptador estivesse quebrado, ele devolveria `NAO-CHAMOU`
 * para ambos e o teste ficaria verde por engano. Exigir que os dois sejam capturados **e**
 * que apontem para URLs distintas é o que torna o verde informativo.
 *
 * ── O QUE ESTE ARQUIVO **NÃO** PROVA (resíduo declarado, não maquiado) ───────
 *   - **A incondicionalidade da ação.** O contrato diz "não depende de permissão"; o
 *     auth-bridge loga o ADMIN do tenant, que tem todas. Com um único usuário não dá para
 *     separar "incondicional" de "condicionado a uma permissão que este usuário tem". Provar
 *     isso exige um user sem `purchase.view`/`update` — fixture que esta casa de teste não
 *     tem hoje. Fica como resíduo, não como assert que finge.
 *   - **O conteúdo de `/labels/show`.** O teste prova que a tela PEDE a rota certa; não que a
 *     etiqueta imprime. A rota é Blade e tem dono próprio.
 *   - **As 4 ações ainda só no Blade** (pagamento, devolução, mudança de status, e-mail —
 *     RUNBOOK §2 marca `⚠️`). É gap **conhecido e aceito**, escopo de [W], não defeito.
 *   - **Baseline de pixel.** Nenhum `screenshot()` aqui: Purchase está `0 de 4` em VRT e
 *     fechar isso é trabalho próprio, com molde próprio (`ComprasFlowBaselineTest`).
 *
 * ── DADO: REUSA o seeder do Compras, não cria um novo ────────────────────────
 * `VisregComprasFlowSeeder` já semeia a compra `VISREG-COM-001` (biz=1, `type=purchase`,
 * `location_id=1`) e é o dado dos fluxos visuais de `/compras`. Medido: `/purchases` sai da
 * MESMA query (`TransactionUtil::getListPurchases`, `PurchaseController:247`) e **não filtra
 * por data** por default — só se `start_date` E `end_date` vierem no request. Logo a mesma
 * linha aparece nas duas telas, e um seeder novo seria um segundo dono do mesmo dado.
 *
 * ── ROTA: `?v=2` É OBRIGATÓRIO ───────────────────────────────────────────────
 * `PurchaseController@index:74` só entrega Inertia com header `X-Inertia` **ou** `?v=2`. Um
 * GET normal — que é o que o redirect do auth-bridge faz — cai no **Blade legacy**. Sem a
 * query, este teste mediria a tela errada, e o probe devolveria `SEM-BUSCA-REACT`.
 *
 * ── TENANT ───────────────────────────────────────────────────────────────────
 * `Business::orderBy('id')->first()` = biz 1 do gate visual, tenant FICTÍCIO do
 * `VisregTenantSeeder` no MySQL `oimpresso_test`. **biz=4 (ROTA LIVRE) é PROIBIDO** em teste,
 * fixture ou smoke (ADR 0358) — ela é a cliente que reportou a regressão, não cobaia dela.
 * Mesmo idioma de `AuthBridgeSmokeTest` e `RecipesIndexTest`.
 *
 * ── EXECUÇÃO (CT 100 / CI — nunca local: memory/proibicoes.md + ADR 0062) ────
 *   tailscale ssh root@ct100-mcp "docker exec oimpresso-staging ./vendor/bin/pest tests/Browser/Purchase/IndexEtiquetaTest.php"
 *
 * ⚠️ Em 2026-09-05 o CT 100 **não conseguia** rodar isto: `oimpresso-staging` tem Pest e o plugin
 * Browser, mas está sem `public/build/manifest.json` e sem os browsers do Playwright em
 * `~/.cache/ms-playwright` — não renderiza Inertia nem abre Chromium. Quem executou primeiro foi
 * o CI. (Fato datado: confira antes de concluir que o comando acima não serve.)
 *
 * ── RECIBO DO 1º VERDE (não é promessa) ──────────────────────────────────────
 * run 33941339625, 2026-09-05: `PASS Tests\Browser\Purchase\IndexEtiquetaTest` ·
 * **3 passed (7 assertions)** · 8,14s. O contador de ASSERTIONS é o que prova que executou —
 * um teste pulado sairia com `0 assertions` e sem falhar (LC-13). Nasce advisory; a ADR 0336
 * pede 2 verdes que EXECUTARAM antes da promoção, e a promoção é flip [W].
 *
 *   @covers-uc UC-PURIDX-04  a ação Etiquetas existe no React e aponta pra /labels/show
 *   @covers-uc UC-PURIDX-05  rota Blade sai por window.open, nunca por router.visit
 *
 * @see resources/js/Pages/Purchase/Index.tsx (tela sob teste — ação em :303)
 * @see resources/js/Pages/Purchase/Index.casos.md (UC-PURIDX-04/05 + a dívida que isto paga)
 * @see memory/requisitos/Compras/_telas/RUNBOOK-purchase-index.md (§2 paridade Blade × React)
 * @see database/seeders/VisregComprasFlowSeeder.php (dado determinístico reusado)
 * @see .github/workflows/visual-regression.yml (step que invoca)
 */

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;

/** `?v=2` é o que faz o controller devolver Inertia em vez do Blade legacy. */
const PUR_ROTA = '/purchases?v=2';

/** Ref. da compra do `VisregComprasFlowSeeder` — a âncora da linha no DOM. */
const PUR_REF = 'VISREG-COM-001';

/**
 * Sinal de PRONTIDÃO com DUAS pernas, porque há dois modos de falhar em silêncio: a tela
 * pode ser o **Blade** (se o `?v=2` se perder no redirect) ou pode ser o React **sem linha**
 * (se o seed não chegou ao browser). As sentinelas separam os dois — e nenhuma delas é
 * `false`, para que "não achei" nunca vire "está tudo bem".
 *
 * O input de busca é a marca do React: `Index.tsx:245` é o único lugar da tela com esse
 * placeholder, e o Blade legacy não o tem.
 */
const PUR_JS_PRONTO = <<<'JS'
(() => {
  const busca = document.querySelector('input[placeholder*="Buscar ref"]');
  if (!busca) return 'SEM-BUSCA-REACT';
  const linhas = document.querySelectorAll('tbody tr').length;
  if (linhas === 0) return 'SEM-LINHA';
  return 'PRONTO';
})()
JS;

/**
 * Captura o que uma ação da linha PEDE ao navegador.
 *
 * Substitui `window.open`, clica no botão de verdade e restaura o original no `finally` (o
 * `try` importa: um erro no handler não pode deixar a página com o `open` sequestrado para o
 * caso seguinte). Devolve `url @ alvo # rota-depois-do-clique`.
 *
 * O terceiro campo é o controle de `router.visit`: se a ação passasse a navegar por Inertia,
 * o `pathname` mudaria — e o teste veria a diferença mesmo num mundo onde `window.open`
 * também tivesse sido chamado por outro motivo.
 *
 * Ancorado no `title` do botão (o `Button` do DS espalha `{...props}`, então o atributo chega
 * ao DOM), nunca em classe de estilo.
 */
const PUR_JS_ACAO = <<<'JS'
((titulo) => {
  const linha = [...document.querySelectorAll('tbody tr')]
    .find((tr) => (tr.textContent || '').indexOf('%REF%') >= 0);
  if (!linha) return 'LINHA-AUSENTE';
  const botao = linha.querySelector('button[title="' + titulo + '"]');
  if (!botao) return 'ACAO-AUSENTE';

  const original = window.open;
  let capturado = 'NAO-CHAMOU';
  window.open = function (url, alvo) { capturado = String(url) + ' @ ' + String(alvo); return null; };
  try { botao.click(); } finally { window.open = original; }

  return capturado + ' # ' + window.location.pathname;
})('%TITULO%')
JS;

beforeEach(function () {
    // CROSS-PROCESS DB (idêntico ao AuthBridgeSmokeTest/RecipesIndexTest): o browser usa o
    // MySQL do .env, o processo de teste usa o de phpunit.xml — realinha os dois para o
    // MESMO banco do gate, senão o id da compra lido aqui não é o id renderizado lá.
    config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'oimpresso_test']);
    DB::purge('mysql');
});

/** Admin do tenant fictício do gate. Falha ALTO: tenant ausente não pode virar verde
 *  silencioso — um smoke que não renderizou seria falso-positivo (idem RecipesIndexTest). */
function purAdmin(): User
{
    $business = Business::orderBy('id')->first();
    if (! $business) {
        throw new RuntimeException('Sem business seedado: o VisregTenantSeeder nao rodou.');
    }
    $admin = User::where('business_id', $business->id)->orderBy('id')->first();
    if (! $admin) {
        throw new RuntimeException('Sem user no business seedado: nao da pra autenticar.');
    }

    return $admin;
}

/** Semeia (idempotente) e devolve o **id real** da compra. O id entra na URL esperada, então
 *  o assert é de igualdade exata contra o dado — não um regex frouxo que passaria com
 *  `purchase_id=` vazio ou com o id de outra linha. */
function purCompraId(): int
{
    (new \Database\Seeders\VisregComprasFlowSeeder())->run();

    $id = DB::table('transactions')
        ->where('business_id', 1)
        ->where('type', 'purchase')
        ->where('ref_no', PUR_REF)
        ->value('id');

    if (! $id) {
        throw new RuntimeException('Compra ' . PUR_REF . ' nao existe apos o seeder: sem ela o teste seria vacuo.');
    }

    return (int) $id;
}

/** Monta o probe de uma ação, injetando ref e título. O nowdoc não interpola de propósito —
 *  é o que mantém o JS literal, sem escape sobrevivendo ao transporte. */
function purProbeAcao(string $titulo): string
{
    return str_replace(['%REF%', '%TITULO%'], [PUR_REF, $titulo], PUR_JS_ACAO);
}

/** Abre a lista já autenticada e espera o React montar COM linha. */
function purAbrirLista(int $largura = 1280, int $altura = 800)
{
    $admin = purAdmin();

    $page = visit('/_visreg-login/' . $admin->id . '?to=' . urlencode(PUR_ROTA))
        ->resize($largura, $altura);

    $ultimo = null;
    for ($i = 0; $i < 24; $i++) {
        $ultimo = (string) $page->script(PUR_JS_PRONTO);
        if ($ultimo === 'PRONTO') {
            return $page;
        }
        $page->wait(0.25);
    }

    // A mensagem NOMEIA o modo de falha: 'SEM-BUSCA-REACT' = caiu no Blade (o `?v=2` se
    // perdeu); 'SEM-LINHA' = React montou mas o seed não chegou ao browser.
    throw new RuntimeException("Lista de compras nao ficou pronta — ultimo sinal: '{$ultimo}'.");
}

it('UC-PURIDX-04 · a acao Etiquetas existe na linha e pede /labels/show com o id da compra', function (int $w, int $h) {
    $id = purCompraId();
    $page = purAbrirLista($w, $h);

    // Igualdade EXATA, com o id real do banco: prova a URL, o alvo `_blank` e — no terceiro
    // campo — que a página NÃO navegou (a lista continua montada por trás da nova aba).
    expect($page->script(purProbeAcao('Imprimir etiquetas')))
        ->toBe("/labels/show?purchase_id={$id} @ _blank # /purchases");

    $page->assertNoConsoleLogs();
})->with([[1280, 800], [1440, 900]]);

it('UC-PURIDX-05 · a rota Blade sai por window.open — e o controle positivo prova o probe', function () {
    $id = purCompraId();
    $page = purAbrirLista();

    // CONTROLE POSITIVO: o botão "Imprimir" usa o MESMO mecanismo. Se o interceptador
    // estivesse quebrado, os dois voltariam 'NAO-CHAMOU' e o caso acima passaria por engano.
    expect($page->script(purProbeAcao('Imprimir')))
        ->toBe("/purchases/print/{$id} @ _blank # /purchases");

    // E as duas ações têm de pedir rotas DISTINTAS — senão um copy-paste que apontasse
    // "Etiquetas" para a rota de impressão passaria nos dois asserts isolados.
    expect($page->script(purProbeAcao('Imprimir etiquetas')))
        ->not->toBe($page->script(purProbeAcao('Imprimir')));

    $page->assertNoConsoleLogs();
});

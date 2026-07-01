# Sessão 2026-07-01 — "a devolução do ROTA LIVRE parou de funcionar"

**Gatilho (Wagner):** *"a devolução da rotalivre parou de funcionar, acho que saiu do sidebar."*
**Cliente:** ROTA LIVRE (biz=4, Larissa). Diagnóstico via Modo Suporte (ADR 0305, auditado).

## Diagnóstico (o que era vs o que Wagner achava)

Wagner supôs "saiu do sidebar". **Não era.** Investigação (código + Modo Suporte + `laravel.log` prod) achou **dois problemas distintos**:

### Problema 1 — lista `/sell-return` estourava HTTP 500 (global)
- **Sintoma:** o link de Devolução ESTAVA no sidebar (popover de "Vendas") pra Larissa (Admin, bypass `Gate::before`); a **lista** é que dava 500 no Ajax da DataTable `sell_return_table`.
- **Causa-raiz:** 5 módulos (`OficinaAuto`/`Whatsapp`/`ComunicacaoVisual`/`ConsultaOs`/`ADS`) declaravam `protected $namespace` no `RouteServiceProvider`. O `boot()` do Laravel chama `setRootControllerNamespace($this->namespace)`, que seta o **root controller namespace GLOBAL** do `UrlGenerator`. O último módulo a bootar "vencia" (OficinaAuto) e prefixava toda `action('App\Http\Controllers\...@metodo')` legada (string sem `\` inicial) → `Action ... not defined` → 500. Atingia qualquer tela legada com esse padrão, não só devolução.
- **Evidência prod:** `laravel.log` biz=4: `Action Modules\OficinaAuto\Http\Controllers\App\Http\Controllers\SellReturnController@show not defined` + `GET /sell-return?draw=1…` = 500.
- **Fix ([#3488](https://github.com/wagnerra23/oimpresso.com/pull/3488)):** remover `protected $namespace` + `->namespace()` dos 5 providers (rotas já usam FQCN `[Controller::class,'m']` — verificado, remoção segura; restaura root namespace vazio). + teste de arquitetura `ModuleRouteProviderNoGlobalNamespaceTest` (falha se o padrão voltar). **Smoke prod:** `/sell-return` = 200 em biz=1 e biz=4, console limpo.

### Problema 2 — menu "Ações" por linha (com Devolução) sumiu da lista React
- Wagner: *"tinha um menu na lista de venda… venda retorno… se perdeu em algum PR."* **Confirmado no git:** o dropdown "Ações" por linha nasceu no commit `d6f4dddcdc` e foi **removido no rewrite Cowork KB-9.75 (#1032)** — drift código↔charter que deixou a lista sem o ponto de entrada da devolução.
- **Fix ([#3494](https://github.com/wagnerra23/oimpresso.com/pull/3494)):** `ActionsMenu` (kebab ⋮) restaurado fiel na `SellsTabelaUnificada` — Ver detalhes · Editar · Adicionar pagamento (se não pago) · Imprimir nota · **Devolução** (`/sell-return/add/{id}`) · Excluir (`variant=destructive`). Backend `urls.sell_return_add` gated por permissão real; botão tb no drawer `SaleSheet`. **Smoke prod:** menu ⋮ renderiza em todas as linhas; Devolução → `/sell-return/add/81965` = 200.

## Governança (charter + enforcement)

- **[#3499](https://github.com/wagnerra23/oimpresso.com/pull/3499):** `Sells/Index.charter.md` v6→v7 registra o menu (o #3494 tinha mergeado sem tocar o charter — mesmo drift do #1032). Fechou o advisory `charter-us` declarando `related_us`.
- **Pergunta do Wagner: "isso deveria ser hook? ou como?"** → veredito **adversarial ancorado em regra consolidada**:
  - **Não** hook de runtime nem gate de presença de charter (`charter-sync-gate` que eu havia proposto) — seria **"presença ≠ correção"** (L-24; e o escape foi pego por Wagner, não por máquina) e teatro como os gates que a **ADR 0271** deletou.
  - **Sim** o mecanismo já consolidado da **ADR 0264**: contrato da tela = `casos.md` (UC) enforçado por teste (G-2) + Playwright (G-3), `casos-gate`+`dominio-gate` já `required`. O que pega a regressão real (#1032) é **UC + teste de comportamento** (remover o menu quebra o teste), cego a qual arquivo mudou.
- **[#3501](https://github.com/wagnerra23/oimpresso.com/pull/3501) + [#3506](https://github.com/wagnerra23/oimpresso.com/pull/3506):** `UC-S11 · "Da lista, iniciar devolução"` no `Sells/Index.casos.md` + spec Playwright que assere ⋮ → Devolução → `/sell-return/add/{id}` (role/nome, sem CSS — L-24). Fica no `sells-venda-balcao.spec.ts` **após UC-S01** (venda real garante linha na lista; a lista vazia foi a 1ª falha do e2e).

## Incidente de processo — merge-stale (#3501)

O #3501 mergeou com o **head do PR travado no commit velho** (`23ccfb49`): o GitHub não sincronizou o fix (`feec2a54`) a tempo, e o merge levou a versão quebrada (UC-S11 rodando em lista vazia) pro main. **E2E é não-required** → não bloqueou o time (corrigi meu alarme exagerado), mas deixou o e2e vermelho. Hotfix **#3506** (cherry-pick do fix) restaurou. **Lição:** antes de mergear, conferir `PR head == commit local`.

## Item aberto (fora de escopo)
- **US-COPI-129** (criada): `jana:recall-eval (mock)` vermelho no main — pré-existente, não-required, `estrutura_ok:false`/10 violações. Não tocar Jana sem OK Wagner.

## PRs
#3488 (500 fix) · #3494 (menu) · #3499 (charter v7) · #3501 (UC-S11, mergeou stale) · #3506 (hotfix UC-S11). Todos merged. Main são (e2e verde no conteúdo do #3506).

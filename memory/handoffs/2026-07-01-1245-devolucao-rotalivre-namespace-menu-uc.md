# Handoff 2026-07-01 12:45 — Devolução ROTA LIVRE: namespace 500 + menu Ações restaurado + UC-S11

## Estado no fechamento (verificável, incident-driven — não é snapshot de cycle)

- **Main são.** 5 PRs merged nesta sessão (ordem): #3488 → #3494 → #3499 → #3501 (stale) → #3506 (hotfix do stale).
- **Task nova:** `US-COPI-129` (Jana) criada via MCP — `jana:recall-eval (mock)` vermelho no main (não-required, pré-existente). Não iniciada.
- **Prod:** `/sell-return` volta a 200 (biz=1+biz=4, smoke feito); menu ⋮ na lista de vendas com Devolução (smoke feito, `/sell-return/add/{id}`=200).
- **NÃO rodei** `cycles-active`/`my-work` completos (sessão foi incidente, não trabalho de cycle) — sem fingir snapshot.

## O que foi feito

1. **500 da lista de devolução (#3488):** causa = 5 `RouteServiceProvider` de módulo (`OficinaAuto`/`Whatsapp`/`ComunicacaoVisual`/`ConsultaOs`/`ADS`) com `protected $namespace` → `setRootControllerNamespace()` polui o root controller namespace GLOBAL do `UrlGenerator` → toda `action('App\Http\Controllers\...')` legada (string sem `\`) quebra. Fix: remover `$namespace`+`->namespace()` (rotas são FQCN). Guard: `ModuleRouteProviderNoGlobalNamespaceTest`.
2. **Menu "Ações" por linha (#3494):** sumiu no rewrite Cowork #1032 (nasceu em `d6f4dddcdc`). Restaurado `ActionsMenu` (kebab ⋮) na `SellsTabelaUnificada` + Devolução no drawer `SaleSheet` + `urls.sell_return_add` gated por permissão.
3. **Charter v7 (#3499):** `Sells/Index.charter.md` registra o menu (+`related_us`).
4. **UC-S11 (#3501→#3506):** `Sells/Index.casos.md` UC "Da lista, iniciar devolução" + spec Playwright (`sells-venda-balcao.spec.ts`, após UC-S01) — trava a regressão do #1032 no eixo consolidado (ADR 0264), não em gate de presença.

## Lições (perenes)

- **merge-stale:** o #3501 mergeou com o head do PR travado no commit velho (GitHub não sincronizou o fix a tempo) → main pegou versão quebrada. **Antes de mergear: conferir `PR head == commit local`** (`gh pr view N --json headRefOid` vs `git rev-parse origin/<branch>`).
- **enforcement = UC+teste, não gate de presença** (presença≠correção, L-24) — catalogado em `proibicoes.md` §5 (2026-07-01) + `LICOES_CC` L-27.
- **E2E Playwright NÃO é required** no main (só `casos-gate`+`dominio-gate` dessa família) — corrige a suposição de que e2e vermelho bloqueia o time.

## Próximo passo sugerido
- `US-COPI-129` (Jana recall-eval): investigar `estrutura_ok:false` — golden stale vs regressão real vs gate-teatro a aposentar (ADR 0271). Não tocar Jana sem OK Wagner.

## Estado MCP no momento do fechamento
- `US-COPI-129` criada (Jana SPEC.md, commitada neste PR). Demais tasks: não consultadas (incidente). Sessão log: [`memory/sessions/2026-07-01-incidente-devolucao-rotalivre-namespace-menu.md`](../sessions/2026-07-01-incidente-devolucao-rotalivre-namespace-menu.md).

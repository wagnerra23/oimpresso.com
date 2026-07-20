---
date: "2026-07-20"
time: "1516"
topic: "US-GOV-052 ENCERRADO — lote 5 SessionStart portado + limpeza dos 10 .ps1 órfãos + aposentadoria da camada PS-smoke (0 .ps1 de hook em main)"
author: "[CC]"
related_adrs: [0062-separacao-runtime-hostinger-ct100, 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura, 0224-hooks-block-vs-advisory-claude-4.8-aware]
---

# Handoff 2026-07-20 15:16 — US-GOV-052 encerrado: camada `.ps1` de hooks 100% aposentada

Continuação direta do handoff [2026-07-20 11:49](2026-07-20-1149-us-gov-052-port-hooks-adversario.md) (lotes 1-4, chip `task_b1ad598f`). Esta sessão fechou os **3 restantes** + limpou tudo. **Estado final de `main`: ZERO `.ps1` de hook.**

## O que foi feito (3 PRs MERGED por [W])

| PR | Conteúdo | Merge |
|---|---|---|
| [#4590](https://github.com/wagnerra23/oimpresso.com/pull/4590) | **Lote 5** — porta os 3 hooks SessionStart `.ps1`→`.mjs`: `brief-fetch-curl`, `tier-a-banner`, `handoff-inline` (era comando PowerShell **inline** no settings.json) | 17:25Z |
| [#4592](https://github.com/wagnerra23/oimpresso.com/pull/4592) | **Limpeza** — deleta os 10 `.ps1` órfãos dos lotes 3-5 (−819 linhas) + fecha a **mina do Check P** | 17:59Z |
| [#4594](https://github.com/wagnerra23/oimpresso.com/pull/4594) | **Aposenta a camada PS-smoke** — deleta `test-all-hooks-smoke.ps1` + reshape do `gate-selftest.yml` (windows→ubuntu, node-only) — supersede #4593 | 18:11Z |

## Adversário-antes-de-portar (regra dura [W] 2026-07-20) — 3× GO

O subagente cético (read-only, repo VIVO) verificou e deu 3× GO. Re-verifiquei **cada afirmação load-bearing no código** (o triagem canônico já errou análise de cobertura antes):
- **Token**: `.mcp.json` confirma "cada dev cola seu Bearer token em `.claude/settings.local.json` (gitignored)" → time Mac/Linux **tem** o token out-of-band; o port **sucede** pra eles, não só cai no fallback.
- **Endpoint** `https://mcp.oimpresso.com/api/mcp` alcançável por `fetch` nativo; o `curl.exe` do `.ps1` era só workaround do **bug UTF-8 do PowerShell 5.1** (Invoke-RestMethod decodifica como CP1252) — **não** do node. Smoke real provou (brief real puxado, acentos corretos).
- **Banner fork → Opção A** (estática de-numerada), NÃO B (derivada): o gerador `skills-index-generate.mjs` asserta **presença de slug** (não o número) → manter slugs literais + remover "5"/"6" mata o único número que dritava (§5 2026-07-17) sem parse no SessionStart. Reapontei a 4ª-fonte do gerador `.ps1`→`.mjs` (senão, ao deletar o `.ps1`, o `existsSync` faria o check **sumir em silêncio** — furo que o adversário pegou).

## Os 6 requisitos duros do `brief-fetch-curl.mjs` (todos testados)
1. **`AbortSignal.timeout(10s)`** — o `fetch` do node NÃO tem timeout default; sem ele um servidor lento **pendura o SessionStart**. (Teste prova que aborta ~120ms, não trava.)
2. **Path cross-plataforma** — walk-up até achar `.claude/settings.local.json` (sem `D:/oimpresso.com/…` hardcoded, que era fallback morto no Mac/Linux).
3. **Redação de token POR CONSTRUÇÃO** — razões de fallback são categorias **fixas**; nunca interpola `Authorization`/headers/`err.message`. O selftest injeta o token no `err.message` e prova que **não vaza** em nenhum caminho.
4. **8 saídas fail-open** + try/catch blindado (exit 0 sempre).
5. **fetch nativo** — dropa `curl.exe` + arquivo temporário.
6. **Selftest hermético** (fetch injetado, zero rede real): 34/34.

## Pegadinhas / lições desta sessão

- **Stacked-após-squash**: o #4593 (base = branch de limpeza, stacked no #4592) ficou `CONFLICTING` assim que o #4592 foi **squash-merged** (o squash reescreve o SHA; o branch stacked ainda tinha os commits originais → conflito no `_HOOKS-INDEX.md`/`AUTOMATIONS.md`). **Fix**: `git rebase origin/main` (o commit de limpeza virou vazio por patch-id match) → republicar como branch limpa (**#4594**) + fechar #4593 como superseded.
- **Meu próprio `block-destructive.mjs` funcionou** (um dos hooks portados nesta série): bloqueou o `git push --force-with-lease` do rebase. O hook não tem escape-valve — segui a *"abordagem alternativa"* que ele mesmo sugere (branch nova + push normal), NÃO forcei bypass.
- **A mina do Check P**: o **lote 3 nunca atualizou o `AUTOMATIONS.md`** — 3 refs (`check-skills-fresh`/`modulo-preflight-warning`/`loop-fechar-check`) ainda apontavam pro `.ps1`. Deletar sem consertar = Check P (required) 🔴 dead-ref (§5 2026-07-12). Reapontadas pra `.mjs` no #4592.
- **`node --test .claude/hooks/` regride em node ≥21** (trata o diretório como módulo — MODULE_NOT_FOUND; reproduzi no node 24 local). Troquei pro **glob explícito** `.claude/hooks/*.test.mjs` (bash do ubuntu expande) — robusto em node 20 e 24. Foi o achado da revisão que [W] pediu.
- **`hooks-selftest` é advisory** (baseline linha 12, textual "SEGUE advisory") — reshapeá-lo é seguro; NÃO toquei o job **required** `gate selftest · GT-G6` nem o `gate-selftest.mjs`.
- **MSYS colon-mangling** (`git show origin/main:<path>` → `origin\main;...`) fabricou um falso "não está em main" no verify final; re-verifiquei com `MSYS_NO_PATHCONV=1` (mesma pegadinha catalogada + LC-08).
- **Classificador de segurança (Write/Bash/Edit) oscilou pesado a sessão inteira** (5-11 tentativas por escrita) — Read/Grep/Glob nunca precisam dele.

## Estado MCP no momento do fechamento

Não consultado via tools MCP `cycles-active`/`my-work` (trabalho 100% em `.claude/hooks` + governança + CI, fora do fluxo cycle/tasks — mesmo padrão do handoff 11:49). Snapshot do brief #387 (SessionStart, gerado há ~2h): Cycle "—", HITL pending [W] = 2 (FIN-004 cobrança ROTA LIVRE, runbook on-prem pós-Gold), Brain B 0%, 0 incidentes/24h. Nenhuma task MCP criada/fechada (infra de agente não tem US no backlog; US-GOV-052 é o guarda-chuva). Chips herdados: `task_b1ad598f` (fechado por este trabalho — cadeia SessionStart portada), `task_0e2e00c8` (mcp-first-warning — **já resolvido**: aposentado em #4587, "[W] aceitou residual").

## Placar final US-GOV-052

**18 `.ps1` wired portados** (lotes 1-5) **+ 1 aposentado** (block-serving-branch-switch, Herd morto) **+ 11 `.ps1` deletados** (10 twins + o smoke runner) **+ a camada PS-smoke do CI retirada**. `main` roda **0 hooks `.ps1`** — o time Mac/Linux (Felipe/Maiara/Luiz) tem brief/handoff/banner + toda a defesa de hooks cross-platform.

## Follow-ups / abertos

- **Nenhum bloqueador de US-GOV-052.** A épica está encerrada.
- Herdado (não desta sessão): a formalização da morte do Herd (ADR supersede da R8/0233) é decisão [W] — segue append-only, sem objeto vivo.
- Higiene: branches merged da sessão auto-deletadas pelo GitHub; a `retire-ps-smoke-layer` (do #4593 fechado) deletada manualmente. Sobram centenas de `claude/*` stale no remoto (de outras sessões — **fora de escopo**, não mexer sem varredura dedicada).

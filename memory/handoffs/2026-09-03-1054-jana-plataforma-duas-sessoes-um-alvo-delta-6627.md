---
date: "2026-09-03"
time: "10:54 BRT"
slug: jana-plataforma-duas-sessoes-um-alvo-delta-6627
tldr: "PR #6627 (aba Plataforma da Jana) estava CONFLICTING porque o #6609 mergeou a MESMA tela primeiro. Resolvido como DELTA sobre o trio do main: bug de produção das filhas fora do escopo + 2 UC novos + errata da porta user_type + teste órfão do #6421 na lane. CI verde exceto o watchdog G6 herdado (advisory). MERGEADO por [W] às 13:45 UTC (843a183cae)."
prs: [6627, 6609, 6421]
decided_by: [W]
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
next_steps:
  - "#6627 MERGEADO (843a183cae, 13:45 UTC) — fica o smoke R1 e o registro LC-19"
  - "Smoke R1 pós-merge: /ia/superadmin/metas com meta+período+apuração num tenant de teste (nunca biz=4) — as 3 tabelas têm 0 linhas em prod (31/08)"
  - "Lição LC-19 pendente de registro no ledger: duas sessões construíram a mesma tela em paralelo; whats-active não rodou em nenhuma (MCP fora)"
---

# Handoff — #6627 vira delta: duas sessões, um alvo (aba Plataforma da Jana)

## Estado MCP no momento

MCP `brief-fetch` **não respondeu** no SessionStart (timeout) — snapshot via `gh`, não via tools:

| Item | Valor (medido 2026-09-03 ~13:50 UTC) |
|---|---|
| PR #6627 | head `02365ac3` · **79 pass · 1 fail · 2 skipping** · **MERGED** por [W] às 13:45 UTC → `main` `843a183cae` |
| O 1 fail | `crons de governança vivos? (watchdog G6 · ADR 0317)` — `jana-ragas*-baseline.json` parados há 64d; **não** está em `required-checks-baseline.json` nem no ruleset; mesmo vermelho no #6608 |
| Lane `jana-pest` (dispatch manual, run 33762536376) | `872 passed · 10 skipped · 3174 assertions` — UC-PLAT-03/04 e os 4 casos do cross-tenant verdes **por nome** |
| Sessões vivas tocando Jana | `beautiful-leavitt-60e1a0` (dona do #6627, parada 12:46 UTC) · "Tratar o vermelho crônico do watchdog G6" (dona do vermelho herdado) |

## O que aconteceu

1. O pedido era rebase do #6627 sobre `main`. Ao chegar, o PR já estava `MERGEABLE` (a sessão dona tinha mergeado `main` às 12:43). Enquanto o CI rodava, o **#6609** (stacked em #6607/#6608) mergeou às 13:15 **a mesma tela inteira** — `Plataforma.tsx`, charter, casos, contrato, RUNBOOK, e2e, `PlataformaContratoTest`. O PR voltou a `CONFLICTING` com 12 conflitos, 7 deles `add/add`.
2. Precedência (`proibicoes.md`): teste verde > casos > charter > SPEC. O trio mergeado e verde ficou; o #6627 virou **delta** com o que o main não tinha:
   - **Bug de produção** no `SuperadminController` do main: `MetaPeriodo`/`MetaApuracao` usam `BelongsToBusinessViaParent`, e o `->with('periodoAtual','ultimaApuracao')` filtrava as filhas pela sessão — `periodo`/`ultima` voltavam `null` para todo tenant alheio. `withoutGlobalScopes()` na closure também cai (`latestOfMany` reinstancia o model). Conserto: 2 queries explícitas sem escopo, sem N+1. O UC-PLAT-01 do main **não pegava** (meta alheia sem filhas → `null` esperado).
   - `PlataformaContratoTest`: **UC-PLAT-03** (morde o bug) + **UC-PLAT-04** (payload sem agregação).
   - `SuperadminMetasCrossTenantTest` (P0 #6421) era **órfão** — nunca rodou em lane. Entrou no `jana-pest.yml` com a errata: a porta `user_type` é **inalcançável** no grupo `/ia` (`CheckUserLogin` aborta antes). `DataController::podeVerPlataforma` virou público com `?User $user = null` para o controle positivo.
3. Descartado da branch: `SuperadminPlataformaContratoTest` (dobrava o do main), a versão própria de tsx/charter/casos/contrato/RUNBOOK/e2e, e um merge anterior da branch que tinha **desfeito** o fix do dropdown legado do #6609.
4. Tier 0 conferido: gate `hasPermissionTo('jana.superadmin')` intacto; `CheckUserLogin` com diff zero.
5. A lane `jana-pest` **não dispara em `synchronize`** (#6622) — precisou de `gh workflow run` manual no head novo; sem isso os testes novos ficariam sem veredito com o PR "verde".

## Artefatos gerados

- Branch `claude/jana-plataforma-superadmin` @ `02365ac3` — 9 arquivos, +207/−15 vs `main` (controller, DataController, 2 testes, lane, casos, charter, RUNBOOK §3/§4, BRIEFING).
- PR #6627 com título e corpo reescritos para o delta.
- Este handoff. Sem session log (sessão curta, heurística da skill).

## Persistência

- **git:** branch do PR pushada; handoff neste PR. **MCP:** propaga pelo webhook após o merge. **BRIEFING Jana:** já atualizado dentro do #6627.

## Próximos passos pra retomar

```bash
gh pr checks 6627 && gh pr view 6627 --json mergeStateStatus
```

## Lições catalogadas

- **LC-19 (máquina paralela a um tema com dono) — instância de SESSÃO, não de artefato:** duas sessões construíram a mesma tela no mesmo dia. O `whats-active` (Tier 1, ADR 0119) não rodou em nenhuma — o MCP estava fora. A emenda §5 2026-08-13 já nomeia a saída (rodar `whats-active` antes de abrir PR); o que faltou foi o oráculo estar de pé. **Registro no ledger fica pendente** — precisa do `ciclo-adversary` antes de virar lápide.
- **Lane que não dispara em `synchronize` produz PR "verde" sem veredito dos testes novos.** Antes de declarar verde, conferir que o run da lane é do **head atual** (`gh run list --workflow jana-pest.yml --branch <b>`), não o SHA anterior.
- **Comentário não conta pro guard `WithoutGlobalScopes`** — ele pula linhas `//`/`*`; a réplica local por `awk` acusa 2 falsos-positivos em comentário. Ler o predicado antes de "consertar".

## Pointers detalhados

- PR: https://github.com/wagnerra23/oimpresso.com/pull/6627 (corpo tem a lista completa do que entrou/saiu e os gates locais rodados)
- Errata da porta (b) + bug das filhas: `memory/requisitos/Jana/RUNBOOK-plataforma.md` §3 e §4
- Casos: `resources/js/Pages/Jana/Plataforma.casos.md` UC-PLAT-03/04

---
title: Governança do conhecimento — de "quais telas arrumar?" à rede de gates enforçada (ADR 0256/0257/0258)
date: 2026-06-07
time: 21:10 BRT
owner: Wagner [W]
slug: governanca-conhecimento-rede-enforcement
prs: [2378, 2379, 2380, 2381, 2382, 2383, 2385, 2386, 2387, 2388, 2390, 2391, 2392, 2393, 2394, 2395, 2396, 2397, 2398, 2399, 2400, 2401, 2402]
related_adrs: [0256-knowledge-survival-meia-vida-catraca-sentinela, 0257-adr-status-lifecycle-kind-modelo-canonico, 0258-processo-adr-estado-arte-indice-gerado-supersede-atomico, 0061-conhecimento-canonico-git-mcp-zero-automem]
---

# Handoff — Governança do conhecimento + rede de gates enforçada

> Sessão longa (Wagner). Começou em "quais telas precisa arrumar?" e virou faxina de memória + processo de ADR estado-da-arte + 5 gaps fechados + a rede de enforcement pendurada. Tudo mergeado no `main`.

## A virada (o arco)
1. **"Quais telas arrumar?"** → o dashboard dizia 46, mas as notas seed (30/mai) estavam DEFASADAS. Re-avaliação: **5 reais**, 37 fantasma, 2 stub. Lição: nota de tela não é verdade sem cruzar com a data do `.tsx`.
2. **"Como sobrevive no tempo?"** → ADR 0256 (Knowledge Survival): catraca + sentinela + gate + cadência.
3. **Auditoria de conflitos** (4 agentes) → 2 camadas de memória legada, 13 colisões ADR, fatos stale, **10 arquivos com SEGREDO EM CLARO** no git (memory/claude).
4. **Faxina** → purga memory/claude (+ desativa cron memcofre que ressuscitava), 3 fatos reference/ corrigidos, fósseis raiz bannerizados, conflito UI-CATALOG resolvido.
5. **Processo de ADR estado-da-arte** (pesquisa Log4brains/adr-tools/AWS) → ADR 0257 (status/lifecycle/kind + exceção gate) + 0258 (modelo) + gerador de índice determinístico + comando supersede atômico + erratas.
6. **Revisão dos 5 gaps** (método fitness-function: detector+gate-duro+meta-teste) → todos fechados.
7. **"Pendura a rede"** → umbrella Governance Gate + ruleset enforçado.

## Estado VIVO (o que está no ar no main)
- **Ruleset id 17379186** `Governance Gate — main` ATIVO: required-check = `Governance Gate (índice + memory-health + meta-teste)`; break-glass = admin bypass mode "always" (logado). Reverter: `gh api -X DELETE repos/wagnerra23/oimpresso.com/rulesets/17379186`.
- **Gates determinísticos** (scripts/governance/): `adr-index-generate.mjs` (catraca índice + supersede-integrity), `memory-health.mjs` (enforce + baseline ratchet Check C/F), `adr-supersede.mjs` (supersede atômico). Meta-testes: `tests/governanceAdrScripts.spec.ts` (9, `npm run test:adr-governance`).
- **Índice canônico** = `memory/decisions/_INDEX-GENERATED.md` (gerado). 265 ADRs · 223 ativos · 0 alertas de supersessão. Os 4 índices manuais bannerizados → ponteiro.
- ADR ativos = **`lifecycle: ativo` no índice gerado** (resposta única, reproduzível).

## ⚠️ PENDÊNCIAS (ação Wagner)
1. **🔴 Rotacionar 9 segredos** que vazaram (Vaultwarden ADMIN_TOKEN, Proxmox/CT100, Hostinger DNS, VoIP, KingHost) — saíram do tree mas seguem no git HISTORY. Tratar como comprometidos.
2. **Tightening do break-glass** (opcional) — trocar bypass "always"→"pull_request"/remover quando tiver colaboradores / após semanas limpo.
3. **GAP 4 (dívida schema ADRs ancestrais)** — latente, auto-limpa on-touch pelo memory-schema-gate. Backfill em massa = risco>retorno; deixado como débito.
4. **Telas (5 reais)** — Manufacturing/Extrato/Contador/ComVis/JobSheet (cor crua→token) — pausadas, prontas pra o loop test-first.

## Pegadinhas catalogadas
- NUNCA `npm i` no worktree (suja package.json → quebra `npm ci` → derruba todo CI build). Validar dep via npx/dir isolado. (Incidente revertido em #2398.)
- Append-only ADR: editar metadados (status/lifecycle/kind/superseded_by) só via label `adr-metadata-normalization` (exceção do gate, ADR 0257).
- Memórias locais (~/.claude): screen-grades-baseline-stale, screen-qa-loop-seguro, incidente-credenciais-memory-claude, knowledge-survival-framework, adr-index-generator, decisao-pendente-enforcement-gates.

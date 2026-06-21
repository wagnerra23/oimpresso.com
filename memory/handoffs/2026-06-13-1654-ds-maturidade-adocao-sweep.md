---
date: "2026-06-13"
time: "16:54 BRT"
slug: "ds-maturidade-adocao-sweep"
tldr: "Auditoria sênior DS 61/100 → Onda M1 (6 PRs: catraca ds-canon-color-guard im-regressível + hsl→oklch pixel-idêntico + motion) → Cliente tela-linda (2 PRs, gate visual via widget pq render down) → adoção em massa via pipeline 68-agentes paralelos (35 varredura → 33 adversário matou ~9% → 327 edits/130 arq #2666). 169 incertos + Cliente mistos + Financeiro/Unificado pendentes (pedem app rodando)."
decided_by: [W]
cycle: CYCLE-08
prs: [2641, 2643, 2644, 2645, 2651, 2654, 2655, 2660, 2666]
next_steps: ["169 incertos da varredura (roadmap sessions/2026-06-13-ds-adoption-sweep-roadmap.md) — pedem app rodando (Wagner loga prod ou sobe Herd)", "Financeiro/Unificado/Index.tsx — fora do sweep (tem casos.md), tokeniza com re-prova", "Cliente mistos (IATab violet, KpiStripClickable, tabs blue) — olho do Wagner", "M2 (DTCG D2=15) ou M3 (VRT) — próximas ondas de maturidade"]
duration: "~sessão épica (9 PRs + 3 workflows)"
authors: [Wagner, Claude Code]
---

# Handoff — DS: maturidade M1 + adoção em massa (sweep paralelo verificado por adversário)

## Estado MCP no momento

- **Cycle:** CYCLE-08 "Receita — Onda A" (2026-05-31→06-28, 46% decorrido). Goals = receita/migração carteira legacy. O trabalho desta sessão é **infra de DS** (não task trackada do cycle).
- **my-work:** sem task DS aberta (trabalho infra, dirigido pelo desafio do Wagner).

## O que aconteceu

Wagner desafiou a completude da auditoria adversarial ("que nota? quantos pontos? quantas ondas?"). Disso saiu:

1. **Auditoria sênior de maturidade do DS** (14 dimensões ponderadas vs Linear/Stripe/Material 3): **61/100**, 6 ondas. Achou a "arma fumegante": a camada canônica se autocontradizia (`badge.tsx` hardcodava paleta crua que `ds/*` proíbe nas Pages).
2. **Onda M1 inteira** (6 PRs): badge/KpiCard/EmptyState/StatusBadge tokenizados + **catraca `ds-canon-color-guard`** (im-regressível) + tokens de motion + hsl→oklch (pixel-idêntico provado) + proposta de versionamento. DS **61→~65**.
3. **Cliente tela-linda** (2 PRs): coração (Pills.tsx — gate visual aprovado via widget antes/depois, render local down) + 9 componentes limpo-semânticos. Categoria preservada (decisão "B").
4. **Adoção em massa via paralelismo** (Wagner: "fazer threads, ~30" + "o adversário tem que estar aqui"): pipeline 3 fases, **68 agentes paralelos**:
   - 35 threads VARREDURA → 360 edits propostos + 190 categoria + 169 incertos
   - 33 threads ADVERSÁRIO → matou 33 (~9%), 326 sobreviventes
   - aplicação → **327 edits / 130 arquivos / 31 módulos**, PR #2666 mergeado (16/16 required verdes)

## Artefatos gerados

- **9 PRs mergeados:** #2641 #2643 #2644 #2645 #2651 #2654 (M1) · #2655 #2660 (Cliente) · #2666 (sweep)
- **Roadmap dos 169 incertos:** [`memory/sessions/2026-06-13-ds-adoption-sweep-roadmap.md`](../sessions/2026-06-13-ds-adoption-sweep-roadmap.md) (280 linhas, por-módulo + cada incerto com a pergunta)
- **Auditoria sênior:** [`memory/sessions/2026-06-13-auditoria-senior-maturidade-ds-oimpresso.md`](../sessions/2026-06-13-auditoria-senior-maturidade-ds-oimpresso.md)
- **Proposta de versionamento DS:** `memory/decisions/proposals/2026-06-13-ds-maturidade-onda-m1-elevacao-versionamento.md` (status proposed — Wagner formaliza)
- **Catraca nova:** `scripts/ds-canon-color-guard.mjs` + workflow + registro em `gates-registry.json`

## Persistência

- **git:** 9 PRs no main (deployados). Este handoff + roadmap via PR próprio.
- **MCP:** webhook GitHub→MCP propaga ~2min após push.

## Próximos passos pra retomar (precisam da app rodando — render foi o gargalo)

1. **Wagner loga no prod** (ou sobe Herd local) → seguir os **169 incertos** com screenshot real (roadmap tem cada um).
2. **Financeiro/Unificado/Index.tsx** — ficou fora do sweep (tem `.casos.md`, evitar stale-results); tokeniza com re-prova do teste.
3. **Cliente mistos** (IATab violet, KpiStripClickable, tabs blue) — olho do Wagner.
4. **M2 (DTCG machine-readable, D2=15)** ou **M3 (VRT)** — próximas ondas de maturidade.

## Lições catalogadas

- **35 threads simultâneas estouram o rate-limit do servidor** (não o limite de uso) → chunk em ondas de 4 resolve + evita retry-storm de tokens.
- **Mega-PR (132 arq) tropeça em gates de diff** (PII em máscara de input não-allowlisted; casos stale-results ao tocar tela com `.casos.md`). Resolvido honesto: allowlist a máscara + tirar a 1 tela-com-casos do PR. Lição: sweep amplo deve **excluir telas com `.casos.md`** (re-prova própria) e checar PII-allowlist antes.
- **Adversário matou ~9%** dos edits da varredura → o par varredura+adversário pegou categoria-disfarçada que a varredura sozinha aprovaria.

## Pointers detalhados

- Pipeline + scripts: PR #2666 descrição. Edits/verditos: outputs dos workflows `whsy51zwn`/`wrh7ulisk`/`w7m2s6yqp` (temp, efêmero).
- Catraca M1: `scripts/ds-canon-color-guard.mjs` (provada que morde).

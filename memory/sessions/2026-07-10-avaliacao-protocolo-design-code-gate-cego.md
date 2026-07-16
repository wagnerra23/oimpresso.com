---
date: "2026-07-10"
topic: "Avaliação adversarial do protocolo design→code: gate visual cego 3 dias (achado por prova contrafactual) + pacote dark + flip L2 abortado com evidência"
authors: [W, C]
prs: [4079, 4081, 4082, 4083, 4086, 4087, 4088, 4089]
us: [US-COM-021]  # + US-_DESIGNSYSTEM-035/036 (underscore do módulo viola o pattern us[] do session.schema — quirk conhecido; IDs citados no corpo)
outcomes:
  - "Fix do gate cego mergeado (#4088) — pixel L1/L2/Tier0/axe/smoke voltam a rodar em todo PR"
  - "Caixa Unificada no gate L2 default+dark com smoke real biz=1; baselines frescas 17 células"
  - "Anchor-maps hotspots: caixa 13/13 + vendas 5/6 por grep real; Sells re-destilada pelo distiller CT100"
  - "Flip L2→enforcing abortado com evidência (assert binário flaky) → US-036 p1 double-threshold"
  - "Medições: 0,5 fix visual/entrega (70% em 2 telas) · doc-set do protocolo 67k tokens"
---

# Sessão 2026-07-10 — Avaliação adversarial do protocolo design→code + gate cego + pacote dark

> Pedido [W]: *"avalie a eficiência do protocolo do design para code"* → *"adversário e faça"* → pacote dark aprovado → *"testou o processo para ver se o workflow está tudo funcionando em ordem?"* — a resposta a essa última pergunta rendeu o achado do dia.

## 1. Avaliação adversarial (5 recomendações → 4 refutadas pelo estado real)

Verificação contra `origin/main` fresco (checkout da sessão estava −5025): pixel-gate já enforcing (#3277) e apontado pelo protocolo (#3892); fingerprint multi-eixo+região já mecanizado (`render-proto-baseline.mjs`, 09/jul); humano-no-merge já decidido (ADR 0283); contrafactual já é lei viva (0314/0275/sdd-avaliar). **Sobreviveu:** docs cacheando contagens de branch protection divergentes (1 check ≠ 18 ≠ 23 vs 24 vivo) → padrão "não cacheia, aponta" pro baseline enforced — **PR #4079 merged**.

## 2. Medições

- **M1 — re-trabalho visual pós-merge (12/jun–10/jul):** 232 commits em telas · 93 fixes · **~48 visuais (52%)** ≈ **0,5 fix visual/entrega**. Concentração: Financeiro/Unificada (19) + Caixa Unificada (15) = **70% do re-trabalho**. Vetores: (a) dark não validado, (b) CSS bespoke de bundle vs tokens, (c) elemento perdido em rewrite sem contrato de casos.
- **M2 — custo de reconciliação da doc do protocolo:** 14 docs, 269KB ≈ **67k tokens** (quarteto mínimo ~24k).

## 3. Pacote dark (aprovado [W] "pode fazer para resolver e teste")

| Entrega | PR | Estado |
|---|---|---|
| Caixa Unificada no manifesto L2 (default+dark) + `smoke:` real prod biz=1 (gate `charter-live-signal` MORDEU e exigiu) | #4082 | MERGED |
| Baselines regeneradas runner canônico (12 snaps + caixa default/dark) | #4081 · #4086 | MERGED |
| Anchor-maps hotspots (caixa 13/13 · vendas 5/6, grep real, anti-fabricação) + gaps com `prototipo:` morto corrigidos + **Sells re-destilada pelo distiller REAL CT100** (ratchet `distiller_freshness` mordeu e exigiu) | #4087 | MERGED |
| **Fix do gate cego** (ver §4) + L2 segue advisory com flake documentado | #4088 | MERGED |
| Adiamentos registrados: US-COM-021 (flakiness Compras) + US-_DESIGNSYSTEM-035 (Showcase no VRT) | #4083 | MERGED |

Adiado consciente: Compras dark (poda por flakiness no charter — não re-adicionar sem prova de reprodutibilidade); Showcase (rota superadmin + sem charter).

## 4. 🔴 POST-MORTEM — gate visual CEGO em todo pull_request (07→10/jul)

**Sintoma:** prova contrafactual (#4089, dark quebrado de propósito) passou VERDE em ~1min.
**Raiz:** #3933 (modo update, 07/jul 17:38) introduziu no step `Modo de execução`: `ui=${{ steps.mode.outputs.ui }}` — **auto-referência** (id do próprio step) em vez de `steps.changes.outputs.ui` (paths-filter). Render = vazio → `ui=''` → **todo pull_request pulava** pixel L1 "ENFORCING", L2, Tier0-render L3, axe, smoke — job concluía verde via skip-as-pass. Só `workflow_dispatch` rodava de verdade.
**Impacto:** 3 dias de required verde vazio; a "prova anti-#3297" dos PRs de baselines era nula; lentes reais em PR = 1 de 8 (só o lint L2, que roda antes do filtro).
**Fix:** 1 linha (#4088) + comentário forense no workflow.
**Lição de classe:** *skip-as-pass é ponto único de cegueira* — a variável que decide o skip precisa de contrafactual próprio. Lente que nunca ficou vermelha não conta como lente.

## 5. Flip L2→enforcing ABORTADO com evidência (anti-elefante-branco)

Com o gate de olhos abertos, o braço de controle (#4088, workflow-only, zero UI) reprovou `oficina-os default+dark` no attempt 1 e `oficina-os×2 + compras` no re-run do MESMO SHA → **assert binário (`assertScreenshotMatches`, sem zona cinza) é não-reproduzível run-a-run**. O L1 (double-threshold L7) passou estável nos mesmos runs. Flip revertido dentro do #4088; pré-requisito virou **US-_DESIGNSYSTEM-036 (p1)**: portar 3-bandas pro `IsolatedStatesBaselineTest` + prova de 3 runs estáveis + contrafactual não-contaminado. Retratação registrada no #4089: o "vermelho" do round 2 era flake da oficina, não a quebra (superfície ~zero — painéis filhos pintam por cima).

## 6. Bugs de processo achados (além do gate cego)

1. **PR criado pelo `GITHUB_TOKEN` do runner não dispara CI** (anti-recursão GitHub) → PRs de baselines ficavam órfãos (#3994/#3987 fechados). Workaround provado: close/reopen. Fix permanente (PAT) = chip.
2. **Gaps com `prototipo:` apontando pra pastas mortas** (`prototipos/{vendas,caixa-unificada}/` → espelho em `cowork/`) → `prototipo_sha=sem-arquivo` silencioso (#4087 corrigiu).
3. Docs de branch protection divergentes (#4079 corrigiu — "não cacheia, aponta").

## 7. Lentes antes × agora (resposta ao [W])

Nominal ontem: 8 lentes no `visual-regression`. **Real em PR ontem: 1** (lint L2). **Agora: 8 rodando** (L1 pixel + smoke + Tier0 + lint enforcing; L2 estados + axe + conformance + L5 advisory). Células snapshotadas 15→17 (dark em 5/6 telas). Maps por região 1→3 telas.

## PRs da sessão

#4079 (docs bp) · #4082 (caixa L2+smoke) · #4081/#4086 (baselines) · #4083 (US adiamentos) · #4087 (maps+distill) · #4088 (fix gate cego) · #4089 (contrafactual, fechado sem merge — propósito cumprido). US criadas: US-COM-021 · US-_DESIGNSYSTEM-035 · US-_DESIGNSYSTEM-036.

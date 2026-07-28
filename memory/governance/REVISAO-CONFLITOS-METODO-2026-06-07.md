---
id: governance-revisao-conflitos-metodo-2026-06-07
---

# Revisão de Conflitos — método de remoção definitiva (2026-06-07)

> Pedido por Wagner: "está na hora de revisão, saber o que melhorar e remover os conflitos definitivamente — tem método?".
> SIM. Método = **fitness functions / invariantes** (ADR 0256 + pesquisa "operationalizing ADRs").

## O método

Um conflito só é **removido definitivamente** quando tem os **3**:
1. **Detector determinístico** (acha o estado ruim sem LLM)
2. **Gate DURO** (bloqueia merge — não advisory)
3. **Meta-teste** (controle-negativo: já foi visto vermelho)

Com os 3, o conflito **não pode recorrer**. Faltando qualquer um = band-aid que volta.

## Matriz (verificada no main 2026-06-07)

| Classe de conflito | Detector | Gate duro | Meta-teste | Status |
|---|---|---|---|---|
| Índice de ADR mente (drift) | `adr-index-generate --check` | ✅ | ✅ | ✅ **DEFINITIVO** |
| Colisão de nº ADR (novas) | `AdrNumberCollisionTest` (Pest) | ✅ | ✅ | ✅ **DEFINITIVO** (existentes = drift aceito ADR 0180) |
| Supersede/lifecycle drift | gerador supWarn | ✅ **(GAP 1 fechado)** | ✅ | ✅ **DEFINITIVO** |
| Auto-mem ressuscita | memory-health Check F | 🟡 vira duro com GAP 2 | ✅ **(GAP 3)** | 🟡 quase |
| Segredo em `memory/` | memory-health Check C + secrets:scan | ❌ advisory / regex fraca | ❌ | 🔴 GAP 2 |
| Schema ADRs ancestrais | memory-schema-gate | ⚠️ duro mas pula velhos | — | 🔴 GAP 4 (vermelho atual) |
| ADRs ativas contradizem (corpo) | nenhum | ❌ | ❌ | 🟡 GAP 5 (semântico) |

## Os 5 gaps → plano de remoção definitiva

| GAP | O quê | Estado |
|---|---|---|
| **1** | supersede-integrity vira gate duro (`--check` falha se supWarn>0) + meta-teste | ✅ **FEITO (este PR)** |
| **3** | invariante anti-ressurreição (Check F: sem `memory/claude/` + cron `memcofre` off) + meta-teste | ✅ **FEITO (este PR)** |
| **2** | memory-health `--warn-only` → **enforce** + baseline (aceita os 18 secrets-default/Firebird) + meta-teste. Torna Check C/F duros. | 🟡 próximo PR |
| **4** | backfill schema das ADRs ancestrais (related número→slug, datas com aspas) — zera o vermelho `memory-schema-gate`. Exige expandir a exceção do gate pra `related/title/decided_at`. | 🟡 próximo PR |
| **5** | convenção ERRATA pras contradições de corpo (0091 Brief-modelo, 0182 PageHeader-cor) — supersessão parcial, append-only proíbe editar corpo → ADR-errata curta. | 🟡 próximo PR |

## Não-conflitos (confirmados OK)
Broadcaster (Reverb→Centrifugo), deploy runtime, sidebar light/dark, multi-tenant, MWART, stack AI, DS v4/v5/v6 — supersessão correta. `UI Canon Notify` vermelho é **pré-existente** (não tocamos UI).

## Lição operacional gravada
NUNCA rodar `npm i` no worktree de trabalho — suja o `package.json`, quebra `npm ci` e derruba TODO o CI de build (Vite/ESLint/Stylelint/jscpd rodam `npm ci` primeiro). Incidente 2026-06-07 (revertido em PR #2398). Validar deps via `npx` ou dir isolado.

## Refs
ADR 0256 (survival) · 0257 (status/lifecycle) · 0258 (processo) · scripts/governance/{adr-index-generate,adr-supersede,memory-health}.mjs · tests/governanceAdrScripts.spec.ts

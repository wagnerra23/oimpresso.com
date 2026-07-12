---
status: proposal
title: "Conceito único de estrutura de memory/ — schema-dono por família + normalização mecânica sob append-only"
proposed_by: Wagner + Claude
proposed_at: 2026-07-12
relates_to:
  - 0130-handoff-append-only-mcp-first
  - 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0094-constituicao-v2-7-camadas-8-principios
---

# PROPOSAL — Conceito único de estrutura de `memory/`

> **Status:** `proposal` — Wagner promove a ADR aceita após revisão.
> **Origem (2026-07-12):** Wagner *"tem que estruturar os arquivos antigos e deixar
> todos com mesmo conceito. Não importa o custo. Em thread"* — pra a máquina-matriz
> (`system-map.mjs`) poder ler status/owner/related_adrs confiável em vez de só frescor-git.

## Contexto

O corpus `memory/` cresceu organicamente: **7 famílias (~1.537 arquivos), ~683 com passivo
divergente** (frontmatter ausente ou em 5 dialetos por família). Dois globs de família
(**BRIEFING**, **reference**) nunca tiveram schema-dono no `memory-schema-gate.yml`.
Auditoria: workflow `estrutura-canon-memoria` (8 agentes, 2026-07-12).

## Decisão

1. **1 família = 1 schema-dono** em `scripts/memory-schemas/`. Criar os 2 ausentes
   (`briefing.schema.json`, `reference.schema.json` — feito nesta Fase 0); estender os 6
   existentes só via PR. **Nunca schema paralelo** ("1 tema = 1 schema").
2. **Campos transversais têm grafia única:** data → `updated_at` (SPEC mantém `last_updated`;
   session/handoff `date`; ADR `decided_at`); referência a ADR → `related_adrs` (lista inline
   de slugs); módulo → `module`; autoria por enum `W/F/M/L/E` (+`C` onde há autoria de sessão).
3. **Enforcement = AJV (enum fechado + regex), jamais presence-gate** de campo auto-declarado
   (proibicoes.md §5 / L-24). Ex: o valor do BRIEFING é o `status` enum validável, não "tem status".
4. **Dois trilhos de normalização:**
   - **MECÂNICO** (codemod, 1 PR isolado ≤300 linhas por família) para as **mutáveis**:
     SPEC, session, reference, charter, BRIEFING.
   - **APPEND-ONLY** (ADR, handoff): o **corpo é imutável** ([ADR 0130](../0130-handoff-append-only-mcp-first.md) +
     Constituição Art. 3). Histórico **não é reescrito em massa** — normalização só via labels
     sancionados (0257/0297, corpo byte-idêntico) ou **forward-only** (arquivos novos nascem no padrão).
5. **`DistillerModuloVerdade.php`** passa a emitir `status`+`updated_at` no frontmatter que gera
   (fecha o **regen-loop**: senão a próxima `jana:distill-module-truth` desfaz a normalização).
   Ship junto da wave BRIEFING.

## Fronteira honesta (o asterisco do "todos com o mesmo conceito")

Vale **100% pras famílias mutáveis** (novos + backfill). Pro **histórico append-only**
(ADRs/handoffs antigos), vale **forward-only** — reescrevê-los em massa violaria a regra
Tier 0 que protege a trilha de decisão. Os `.schema.json` usam `additionalProperties: true`
pra **não reprovar retroativamente** arquivos válidos hoje.

## Enforcement (rollout, sem gate prematuro)

Os 2 schemas novos entram **primeiro como conceito-alvo** (esta Fase 0) — **não fiados** à
matrix bloqueante. Wiring = **grace** (`JANA_VALIDATE_MEMORY_STRICT=false`, warn-only,
diff-aware) → **required** por família **só depois do backfill zerar o falso-positivo**
([ADR 0314](../0314-poda-gates-onda-2-lei-fusoes.md): required = decisão deliberada, nunca merge no calado).

## Plano de migração (ordenado por impacto-na-matriz × segurança)

- **Fase 0** (esta): criar `briefing`+`reference` schema + README + este ADR. Zero dado tocado.
- **Fase 1** SPEC (`owner→owners`, `related_adrs` inline, `anchor_format`, quoting) — alto ROI.
- **Fase 2** Reference (sinônimos → `updated_at`/`related_adrs`/`authority`) — pula os gerados.
- **Fase 3** Sessions (rename `title→topic`/`data→date`, quotar date, backfill mínimo dos 149).
- **Fase 4** Charters (dedup de chaves-variante nos 103 com `related_us`).
- **Fase 5** ADR/Handoff — **forward-only** + labels sancionados; **sem backfill retroativo** sem OK Wagner.

MECÂNICO e CURADORIA (status/nomes) são **PRs distintos** por família.

## Riscos Tier 0

- **Append-only** (o risco-mestre): não reescrever ADR/handoff antigos → `governance-gate` bloqueia.
- **memory-schema-gate**: valida só changed files; wiring em grace evita reddear PRs do time.
- **Regen-loop**: patchar o distiller junto da wave BRIEFING (senão drift de volta).
- **Links/anchors**: renomear filename quebra inbound links (handoff não-editável p/ consertar) →
  preferir **não** renomear; renumeração de ADR duplicado = ADR-errata, não frontmatter.
- **Windows**: CRLF/BOM inflam diff/quebram parse — usar Edit tool / newline correto.

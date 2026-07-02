---
date: "2026-07-02"
time: "22:45 BRT"
slug: onda0-consolidacao-estoque
tldr: "Onda 0 do Estoque (docs-only): 3 PRs — #3677 dedup SPEC Inventory (sobrevivente Inventory/SPEC.md porque anchor-lint só varre <Mod>/SPEC.md e ele carrega as 25 âncoras), #3678 corrige 3 drifts (baixa OS LIVE, BOM Fase 1 shipada, âncoras parking), #3679 fecha 6 ponteiros mortos da repartição. Premissa do PR3 inverteu (Purchase é o RUNBOOK novo, não o velho) → merge Purchase→Compras fica ADIADO com Wagner. Gates change-relevant verdes. NÃO mergeado (R10)."
prs: [3677, 3678, 3679]
related_adrs:
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0105-cliente-como-sinal-guiar-sem-mandar
next_steps:
  - "Wagner revisa+merge na ordem #3677 → #3678 (stacked) → #3679 (independente)"
  - "Decidir o merge revisado Purchase→Compras (fatia ADIADA alto custo/risco)"
---

# Handoff — Onda 0 consolidação do domínio Estoque

> Hora: relógio ambíguo entre sessões paralelas (worktree marcou ~21:40 BRT; PRs criados 21:30 UTC). Filename `2245` pra manter o índice monotônico após o `2230` já em main.

## Estado MCP no momento do fechamento

- **`cycles-active` (COPI):** nenhum cycle ATIVO.
- **`my-work` (@wagner):** 30 tasks ativas (8 REVIEW, 8 BLOCKED, 14 TODO). **Nenhuma é de Estoque/Inventory** — o cluster está ADIADO/gated, fora do WIP. HITL Wagner: FIN-4 (cobrança ROTA LIVRE) + dormentes Gold.
- **`decisions-search "estoque inventory anchor":`** sem ADR nova hoje pela Onda 0 (é docs-only, não gerou decisão arquitetural). Relevantes existentes: 0273 (anchor formato), 0303 (anchor wired), 0302 (doneness fonte única).
- **Handoffs irmãos hoje:** 2230-sdd-avaliacao, 1920-e2e3-identidade, 1700-arquivos-audit, 1619-ragas, 1530-protocol-v2 (sessões paralelas — não colidem; Estoque estava fora delas).

## O que aconteceu

3 PRs docs-only fecham a **Onda 0** do [plano de consolidação do Estoque](../sessions/2026-07-02-plano-consolidacao-estoque.md) §4. Narrativa completa: [session log](../sessions/2026-07-02-onda0-consolidacao-estoque.md).

- **[#3677](https://github.com/wagnerra23/oimpresso.com/pull/3677)** dedup SPEC Inventory (sobrevivente = `Inventory/SPEC.md`, tomba a cópia `_telas/`).
- **[#3678](https://github.com/wagnerra23/oimpresso.com/pull/3678)** (stacked em #3677) corrige 3 drifts: baixa da OS **LIVE** (não revertida), BOM Fase 1 **shipada**, âncoras `_pendente_` = parking honesto.
- **[#3679](https://github.com/wagnerra23/oimpresso.com/pull/3679)** corrige 6 ponteiros `runbook:` mortos (repartição) + nota honesta em `Purchase/BRIEFING.md`.

## Decisão que ficou pendente (Wagner)

**Merge revisado Purchase→Compras** — a premissa inverteu: os RUNBOOKs de compra em `Purchase/` são os **mais novos/canônicos**, e as cópias `Compras/_telas/` são mais velhas **mas com conteúdo complementar** (Props/Persona). Não dá pra sobrescrever nem tombar cegamente — é merge de conteúdo + fix de profundidade de link + passe de compliance de schema nos 2 `Compras/_telas/RUNBOOK-purchase-*.md` legados (hoje sem title/owner/last_validated + `spec_ref` errado). É a fatia **ADIADA "alto custo/risco"** — não forcei. Documentado em `Purchase/BRIEFING.md`.

## Persistência (3 canais)

- **git:** branches `claude/estoque-onda0-pr1/-pr2/-pr3` pushados; PRs #3677/#3678/#3679 abertos. Este handoff+session em `claude/estoque-onda0-handoff`.
- **MCP:** webhook GitHub→MCP propaga ~2min após merge deste branch.
- **BRIEFING:** não aplicável (Onda 0 não mexeu capacidade de módulo; só canon docs).

## Próximos passos pra retomar

1. Wagner revisa e mergeia **#3677 → #3678 → #3679** (nessa ordem; #3678 re-aponta pra main sozinho após #3677).
2. Quando quiser a fatia ADIADA: abrir tarefa dedicada pro **merge Purchase→Compras** (com passe de compliance dos RUNBOOK legados).
3. Onda 1 (`stock_movements` + custo médio móvel) só começa após decisão do Wagner sobre método de valoração (§6 do plano) — fora desta sessão.

## Lições

- **Verificar a premissa contra `origin/main` antes de "terminar a repartição"** — o mapa dizia Purchase-velho, o disco dizia Purchase-novo. O guard-rail da tarefa ("conferir antes de sobrescrever") evitou descartar o RUNBOOK canônico.
- **anchor-lint é path-sensível** (`<Mod>/SPEC.md` only): a escolha do sobrevivente numa dedup se decide pela regra da ferramenta, não pela "pasta certa".
- **Gate diff-aware** (`memory-schema-gate`): tocar RUNBOOK legado não-compliant o puxa pro gate → deixei 2 `spec_ref` errados de fora (entram no merge).

## Pointers detalhados

- Session log narrativo: [2026-07-02-onda0-consolidacao-estoque.md](../sessions/2026-07-02-onda0-consolidacao-estoque.md)
- Plano-mãe + insumos: [plano](../sessions/2026-07-02-plano-consolidacao-estoque.md) · [arte](../sessions/2026-07-02-arte-estoque-inventory-smb-2026.md) · [mapa-interno](../sessions/2026-07-02-mapa-interno-estoque-verticais.md) (no branch da #3672)
- Triagem cluster: [`_TRIAGEM-IDENTIDADE-2026-06.md`](../requisitos/_TRIAGEM-IDENTIDADE-2026-06.md) P5/P6/P7 (ADIADO)

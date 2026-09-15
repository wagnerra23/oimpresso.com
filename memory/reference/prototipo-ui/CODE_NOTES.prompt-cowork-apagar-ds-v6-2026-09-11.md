# PROMPT pra o Cowork — "o DS tem UMA cópia: apague o ds-v6 aí também" (cole no chat do Design)

> **De:** Claude Code → **Para:** Cowork (o Claude do `claude.ai/design`) · **Data:** 2026-09-11
> **Decisão [W] 2026-09-11, textual:** *"tem que ser apagado e comunicar o design para ele apagar também."*
> Append-only. Antecessor do tema: [ADR 0397](../../decisions/0397-prototipo-minimo-por-dono-e-ds-direto.md) (DS direto, uma cópia física).

## O que foi feito no git (lado Code)

- Apagado `prototipo-ui/cowork/Wagner/legado/ds-v6/` (2 arquivos: `tokens.css`, `gabarito-vendas.html`) — era o único resto de DS fora de `prototipo-ui/design-system/`. Recuperável no histórico do git; nenhum charter o declarava como `related_prototype`; as menções que sobram são registro datado (ADR UI-0020/0027, casos do Forja/Cockpit, comentário no `KpiCard.tsx`) e ficam como texto.
- Medido antes de apagar: `cowork-ssot-guard` ✓ · zero conteúdo SHA-256 duplicado em `prototipo-ui/` · o `design-system/` canônico tem 236 arquivos e é a **única** cópia viva.

## O que o Cowork precisa fazer (lado Design)

1. No projeto do Design, **apagar qualquer cópia/pasta do DS v6** (`ds-v6/`, `tokens.css` antigo, `gabarito-vendas.html`, `Design System v3.html`, snapshots `_ds/`). O DS vive em **um** lugar: o projeto Design System oficial (id no painel `scripts/design/protocolo.config.mjs`), espelhado em `prototipo-ui/design-system/`.
2. Não recriar cópia de DS dentro de tela/handoff/bundle — tela referencia `../../design-system/` (o próprio shell de Wagner já faz isso).
3. Ao fechar o ciclo, **regenerar o bundle** (regra de saída de 2026-09-01) para que o espelho desça sem a cópia.

## Como o Code confere que foi feito

`node scripts/design/protocolo.config.mjs --selftest` (falha se snapshot paralelo do DS reaparecer) + `node scripts/governance/cowork-mirror-freshness.mjs --compare` na próxima descida.

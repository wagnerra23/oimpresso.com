---
page: governance/ModuleGrades/Show
route: /governance/module-grades/{name}
status: live
owner: [W]
adrs: [0153]
runbook: memory/requisitos/Governance/RUNBOOK-module-grades.md
---

# Charter — `/governance/module-grades/{name}` (Show)

## Mission

Drill-down de **um módulo específico** — mostrar nota grande, breakdown das 5 dimensões com evidências, top 10 gaps ordenados por perda de pontos, e botão **"Evoluir"** que abre modal com batch de tasks-create sugeridas + copy-as-markdown.

## Goals

1. Header com nota grande (5xl) + bucket badge colorido
2. Grid 3 colunas (responsivo) com 5 cards de dimensão (D1-D5) — cada card lista breakdown sub-items com score/max + evidência
3. Lista "Top gaps" ordenada por `lost` desc — mostra perda + prioridade (P0/P1/P2/P3) + key + desc + evidence
4. Botão **"Evoluir"** primário (verde, alto contraste) — abre Dialog com tasks suggested + markdown copiável
5. Markdown gerado é colável direto no Claude Code pra criar tasks via `tasks-create` MCP

## Non-Goals

- ❌ NÃO criar tasks direto via API (Fase B — MVP é copy/paste)
- ❌ NÃO spawn agents Brain B aqui (custo + risco)
- ❌ NÃO editar nota inline (read-only — rubrica é o ground-truth)

## UX targets

- Nota visualmente proeminente (5xl) com cor por bucket
- Cards dimensão com evidência por sub-item (transparência total — Wagner vê o porquê)
- Gaps com perda em vermelho destacado
- Modal Evoluir scrollável + accordion pro markdown raw
- Botão "Copiar Markdown" com feedback visual "✓ Copiado!"

## Anti-hooks

- ❌ NÃO recarregar grade na rota se já tem cache (5min TTL via Cache::remember)
- ❌ NÃO usar `<a href>` puro pra voltar (usar `<Link>` Inertia)
- ❌ NÃO mostrar markdown raw fora do `<details>` (poluído visualmente)
- ❌ NÃO disparar AJAX automático ao abrir modal — gerado client-side via useMemo

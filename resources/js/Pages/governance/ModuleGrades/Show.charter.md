---
page: governance/ModuleGrades/Show
route: /governance/module-grades/{name}
status: live
owner: [W]
adrs: [0153, 0154, 0155]
runbook: memory/requisitos/Governance/RUNBOOK-module-grades.md
---

# Charter — `/governance/module-grades/{name}` (Show)

## Mission

Drill-down de **um módulo específico** — mostrar nota grande, breakdown das 9 dimensões (ADR 0155 v3) com evidências, top 10 gaps ordenados por perda de pontos, e botão **"Evoluir"** que abre modal com batch de tasks-create sugeridas + copy-as-markdown.

## Goals

1. Header com nota grande (5xl) `/100` normalizada + `score_v3_raw /118` em fonte pequena (ADR 0155 v3) + bucket badge colorido + (ADR 0154 v2) contador "X de N dimensões com N/A justificado" quando aplicável + placeholder sparkline "Evolução 7d: not available yet (Wave 4 entrega)"
2. Grid responsivo (1 col mobile / 2 col tablet / 3 col desktop) com até 9 cards de dimensão (D1-D9) — cada card lista breakdown sub-items com score/max + evidência. Compat retroativa: só renderiza dimensões realmente presentes no payload (módulos avaliados em v1/v2 ainda mostram D1-D5).
3. **ADR 0155 v3 — Dimensões novas D6-D9:** D6 Performance (purple-400 border-left), D7 LGPD (pink-400), D8 Security (indigo-400), D9 Observability (cyan-400). Cada card v3 nova ganha badge violet "NOVO v3" ao lado do label pra Wagner identificar de relance.
4. **ADR 0154 v2 — N/A justificado:** Card de dimensão ganha badge verde "N/A justificado" + score exibe "N/A" + cor emerald-600 + fundo emerald-50/30 quando todos sub-itens são `na_justified`. Sub-item N/A mostra ícone "✓ N/A" verde + razão em italic verde abaixo do desc (substitui evidence padrão).
5. Lista "Top gaps" ordenada por `lost` desc — mostra perda + prioridade (P0/P1/P2/P3) + key + desc + evidence
6. Botão **"Evoluir"** primário (verde, alto contraste) — abre Dialog com tasks suggested + markdown copiável
7. Markdown gerado é colável direto no Claude Code pra criar tasks via `tasks-create` MCP
8. **Gate CI anti-regressão surfaced no rodapé** (Wave 2 ADR 0155 §"Gate CI"). Wagner e time MCP entram no drill-down do módulo X e veem que **se a nota deste módulo cair em PR, merge é bloqueado**. Card sky discreto explica comportamento + label de override `module-grades-allowed-regression` + 4 links externos (workflow + baseline JSON + RUNBOOK + ADR 0155). Texto adaptado pro contexto módulo único ("deste módulo" não "qualquer módulo" como no Index).

## Non-Goals

- ❌ NÃO criar tasks direto via API (Fase B — MVP é copy/paste)
- ❌ NÃO spawn agents Brain B aqui (custo + risco)
- ❌ NÃO editar nota inline (read-only — rubrica é o ground-truth)
- ❌ NÃO renderizar sparkline 7d real ainda (Wave 4 entrega — gate CI vai gravar history). Placeholder visível pra preparar o time.

## UX targets

- Nota visualmente proeminente (5xl) com cor por bucket
- Cards dimensão com evidência por sub-item (transparência total — Wagner vê o porquê)
- Gaps com perda em vermelho destacado
- Modal Evoluir scrollável + accordion pro markdown raw
- Botão "Copiar Markdown" com feedback visual "✓ Copiado!"
- **ADR 0154 v2 N/A justificado:** cor emerald-600 dominante em dimensões/sub-itens N/A (não vermelho/amarelo). Razão SEMPRE visível pra Wagner saber por que é N/A — transparência total, sem "nota artificialmente alta" sem justificativa.
- Banner gate CI rodapé Show — card sky compact (mt-4, border-sky-200, bg-sky-50/50), ícone Shield (Lucide, `w-4 h-4 text-sky-700`), texto adaptado "deste módulo", 4 links externos com `target="_blank" rel="noreferrer"`, fica abaixo do Dialog Evoluir como nota de rodapé visual

## Anti-hooks

- ❌ NÃO recarregar grade na rota se já tem cache (5min TTL via Cache::remember)
- ❌ NÃO usar `<a href>` puro pra voltar (usar `<Link>` Inertia)
- ❌ NÃO mostrar markdown raw fora do `<details>` (poluído visualmente)
- ❌ NÃO disparar AJAX automático ao abrir modal — gerado client-side via useMemo
- ❌ NÃO esconder a razão do N/A (ADR 0154 v2) — Wagner precisa ver o **porquê** pra confiar na nota; "verde silencioso" é anti-transparência
- ❌ NÃO contar dimensão N/A como "pontuação cheia silenciosamente" — sempre rotular "N/A" no score (não inflar com "100%")
- ❌ NÃO adicionar interação no banner gate CI (read-only links externos apenas — sem botão, sem dialog, sem state)
- ❌ NÃO promover banner gate CI a posição acima do Header/nota — é nota de rodapé contextual, não headline do módulo

---
slug: 0102-s6-charter-capterra-postmortem-s7-backlog
number: 102
title: "Sprint S6 Charter-Capterra postmortem + S7 backlog (5 itens, ~24h)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
quarter: 2026-Q2
decided_at: "2026-05-08"
decided_by: [W]
accepted_at: "2026-05-08"
decided_by: [W]
module: governance
tier: CANON
related_adrs: ["0089-capterra-driven-module-evolution", "0094-constituicao-v2-7-camadas-8-principios", "0095-skills-tiers-convencao-interna", "0101-sistema-charter-capterra-governanca-escopo"]
parent_charter: mission.charter-system
authors: [wagner, opus]
---

# ADR 0102 — Sprint S6 Charter-Capterra postmortem + S7 backlog

> **Status:** ✅ ACEITA em 2026-05-08 por Wagner ("i" pra gerar ADR consolidando postmortem após merge #232).
>
> Fecha formalmente o Sprint S6 (Sistema Charter-Capterra) e abre o S7 com backlog priorizado de ~24h pra completar o que ficou em stub honesto.

---

## Contexto

[ADR 0101](0101-sistema-charter-capterra-governanca-escopo.md) (mãe do sistema) foi aceita em 2026-05-07. Sprint S6 entregou as 4 fases (Foundation + Tooling + Capterra v2 + Performance Testing) em **4 PRs sequenciais ao longo de ~1 dia de wall-clock** (sessão maratona Wagner ↔ Opus 2026-05-07/08), totalizando **~3000 linhas de governança em git**:

| PR | Fase | Conteúdo |
|---|---|---|
| [#228](https://github.com/wagnerra23/oimpresso.com/pull/228) | Pré-S6 | ADR 0101 + S6 plan + charter exemplo `/repair/dashboard` |
| [#229](https://github.com/wagnerra23/oimpresso.com/pull/229) | F1 Foundation | 5 specs + 4 charters Tier A + CI gate workflow soft |
| [#230](https://github.com/wagnerra23/oimpresso.com/pull/230) | F2 Tooling partial | Skill `charter-first` ATIVA + `charter-write` skill + 2 artisans + cron daily |
| [#231](https://github.com/wagnerra23/oimpresso.com/pull/231) | F3 Capterra v2 | Skill v2.0 (3 eixos) + template + 5 fichas + inventário v2 RB |
| [#232](https://github.com/wagnerra23/oimpresso.com/pull/232) | F4 Performance Testing | 5 specs + Pest agregador + `charter:metrics` artisan + postmortem |

Esta ADR formaliza o que ficou entregue, mede baseline e abre S7 pra fechar lacunas honestamente sinalizadas.

---

## Decisão

Aceitar **Sprint S6 como concluído** com cobertura **5/6 peças** (1 deploy CT 100 sinalizado pra ação humana separada). Abrir **Sprint S7** com 5 itens priorizados (~24h spread) pra completar telemetria + dashboard + skills de evolução.

### Métricas baseline F4 (estado em 2026-05-08)

| # | Métrica | Resultado | Alvo | Status |
|---|---|---|---|---|
| **M2** | Charter GUARD pass rate | **100%** (5/5) | ≥95% | ✅ green |
| **M3** | Charter coverage Tier A | **100%** (5/5) | ≥80% | ✅ green |
| M1 | Token economy | null | -50% | ⏸️ stub (telemetria) |
| M4 | Goal drift rate | null | <5% | ⏸️ stub (telemetria + heurística) |
| M5 | Detector latency | null | p95 <120s | ⏸️ stub (cron GH API) |
| M6 | Anti-hallucination ratchet | null | ≤baseline | ⏸️ stub (ramp-up 7d + canary) |

### Cobertura entregue (5 charters Tier A em prod)

1. `/repair/dashboard` ([#228](https://github.com/wagnerra23/oimpresso.com/pull/228))
2. `/repair/job-sheet` ([#229](https://github.com/wagnerra23/oimpresso.com/pull/229)) — completo
3. `/financeiro/extrato/{id}` ([#229](https://github.com/wagnerra23/oimpresso.com/pull/229)) — completo
4. `/repair/status` ([#229](https://github.com/wagnerra23/oimpresso.com/pull/229)) — stub estruturado
5. `/financeiro/contas-bancarias` ([#229](https://github.com/wagnerra23/oimpresso.com/pull/229)) — stub estruturado

### Skills + artefatos canônicos

- **Skill `charter-first` Tier A ATIVA** (`enabled: true`, lê filesystem por enquanto)
- **Skill `charter-write` (Tier B)** — gera draft + PARA aguardando humano (anti-alucinação)
- **Skill `comparativo-do-modulo` v2.0** — 3 eixos (features + UX + automação)
- **Workflow `charter-gate.yml`** — soft mode (warn-only)
- **Artisan commands**: `charter:audit`, `charter:health`, `charter:metrics`
- **Cron daily 06:30 BRT** — `charter:health --notify`
- **`tests/Charter/CharterMetricsTest.php`** — Pest agregador M2 + M3
- **`tests/Charter/baseline.json`** — vazio (modo soft mantido)
- **5 fichas Capterra v2** — RB completa + 4 placeholder TODO
- **1 inventário v2** — `RecurringBilling/CAPTERRA-INVENTARIO-v2.md`

---

## Justificativa

### Por que fechar com 5/6 peças (não 6/6)

A 1 peça pendente é **deploy do tool MCP `charter-fetch` no CT 100 Proxmox**. [ADR 0062](0062-separacao-runtime-hostinger-ct100.md) (Tier 0) determina que MCP server tools rodam **APENAS no CT 100** — exige SSH humano + restart FrankenPHP, fora do escopo de PR (publication-policy). Skill `charter-first` funciona **sem** a tool (lê filesystem) — tool é otimização de cache + telemetria, não bloqueador.

### Por que stubs honestos (vs implementação parcial)

4 das 6 métricas (M1/M4/M5/M6) dependem de:
- Telemetria que ainda não existe em `mcp_audit_log` (`charter_present`, `tools_used`)
- Ramp-up 7d em prod canary (M6)
- Cron agregador GitHub Actions API (M5)

Implementar parcial e retornar dados sintéticos seria pior que retornar `null` + `reason`. Stubs **honestos** preservam credibilidade do dashboard quando virar UI.

### Por que abrir S7 separado (vs apender ao S6)

Cada item do S7 backlog é **escopo independente** e tem riscos diferentes (telemetria toca `mcp_audit_log` schema; UI dashboard toca tela existente; skills `charter-evolve` exige cron). Ondas separadas em PRs distintos preservam reversibilidade.

---

## S7 Backlog — 5 itens priorizados

| # | Tarefa | Esforço | Bloqueio | Destrava |
|---|---|---|---|---|
| **S7-1** | Telemetria `mcp_audit_log.charter_present` + `tools_used` | ~6h | precisa schema migration + hook PostToolUse | M1, M4 |
| **S7-2** | Cron M5 detector latency (agregador GH Actions API) | ~2h | precisa GH token + storage local SQLite | M5 |
| **S7-3** | M6 ratchet baseline populado + Pest GUARD em prod canary | ~4h | precisa S7-1 + ramp-up 7d | M6 |
| **S7-4** | Dashboard charter cards no `/copiloto/admin/qualidade` | ~6h | precisa S7-1 (alimenta cards) | UI ⇒ Wagner mede sem CLI |
| **S7-5** | Skill `charter-evolve` implementação (spec em [19-charter-evolve-skill.md](../sprints/s6-charter-capterra/19-charter-evolve-skill.md)) | ~6h | precisa S7-1 (telemetria pra detectar drift) | L2 propose automation |

**Total S7:** ~24h spread em 2-3 cycles. Pode ser dividido em **S7a** (telemetria + métricas: S7-1, S7-2, S7-3) e **S7b** (UI + automação: S7-4, S7-5).

### Tier 0 / publication-policy ratchet

- ❌ **Charter-fetch deploy CT 100** continua fora de S7 — sempre humano
- ❌ Promoção workflow soft → hard só após Wagner aceitar baseline + 7d soft
- ❌ Auto-merge skill `charter-evolve` segue proibido (sempre PR draft)

---

## Lições aprendidas (pra S7+)

### O que funcionou bem
1. **Estrutura "Ondas" (A/B/C+)** com diagnóstico antes de executar — Wagner validou ("ficou ótimo")
2. **Auto-correção honesta** — admiti que referência NeurIPS 2025 estava exagerada (não tem paper canônico) e corrigi na própria ADR + criei M4 como alvo de medição própria
3. **PRs pequenos por fase** (~1000 linhas cada) — review humano viável em 1 sessão
4. **Stubs honestos** com TODO explícito pra peças que dependem de telemetria — não inventar dados
5. **Reuso de pattern existente** (`HealthCheckCommand`, `CharterAuditCommand`) — entrega rápida sem reinventar
6. **Bug fix preventivo** durante F2 — `glob('**/*')` não funciona em PHP, troquei por `RecursiveIteratorIterator` antes do commit (lint catch)

### O que poderia ter sido diferente
1. **F2 partial sinalizado tarde** — deveria ter declarado deploy CT 100 como "fora de F2" no início, não em arquivo separado depois
2. **Skill `charter-write` over-defensive** — talvez skill que sempre PARA aguardando humano seja excessiva pra Tier B; próximo uso real vai dizer
3. **5 fichas Capterra v2 placeholder** — tentei resistir à tentação de "preencher genérico pra ficar bonito" e mantive TODO honesto. Decisão correta mas precisa virar pesquisa real em sprint pesquisa S7.5
4. **Discovery do `/copiloto/admin/qualidade`** — descobri tarde que dashboard existente é Copiloto/Memória (não charter). Apender lá vs criar tela nova precisa decisão UX

---

## Consequências

### Positivas
- ADR 0101 (Sistema Charter-Capterra) operacionalizada — princípio #3 da Constituição V2 (Charter > Spec) deixa de ser dormente
- 5 charters Tier A em prod com Tier 1 GUARD verde (M2 100%)
- Skill `charter-first` Tier A ATIVA — agente IA tem contrato vivo da tela antes de editar
- Capterra v2 (3 eixos) operacional — comparativo com mercado vai além de features
- Backlog S7 priorizado com esforço estimado — Wagner sabe o caminho
- Pattern "Ondas + diagnóstico + auto-correção honesta" validado pra usos futuros

### Negativas / Trade-offs
- 4 das 6 métricas ainda em stub — dashboard real precisa S7
- Workflow charter-gate em soft mode — não bloqueia merge ainda
- 4 fichas Capterra v2 (NfeBrasil/Project/ProjectMgmt/Whatsapp) com UX/Auto vazios — Wagner cura trimestralmente
- S6 consumiu 1 dia de sessão Opus — alto custo cognitivo, não-replicável fácil

### Mitigações
- Stubs honestos não enganam dashboard quando UI nascer
- Soft mode coleta baseline 7d antes de virar hard (decisão Wagner)
- Capterra v2 placeholder não bloqueia skill v2 — só pula eixo no inventário
- Ondas como pattern documentado vira receita reusável pra próximos sprints maratona

---

## Critério de "S6 fechado de verdade"

- [x] 4 PRs S6 mergeados em main (#229, #230, #231, #232)
- [x] ADR 0101 (sistema mãe) aceita
- [x] Esta ADR (0102 postmortem) aceita
- [ ] Charter-fetch CT 100 deploy (sinalizado como ação humana separada)
- [ ] S7 backlog rastreado em `mcp_tasks` (Wagner cria tasks-create quando quiser)

---

## Referências

- [ADR 0101](0101-sistema-charter-capterra-governanca-escopo.md) — Sistema Charter-Capterra (mãe)
- [ADR 0089](0089-capterra-driven-module-evolution.md) — Capterra-driven module evolution (raiz Capterra v1)
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição V2 §princípio #3 Charter > Spec
- [ADR 0095](0095-skills-tiers-convencao-interna.md) — Skills Tier A/B/C
- Sprint S6 dossier: [memory/sprints/s6-charter-capterra/](../sprints/s6-charter-capterra/)
- Postmortem detalhado: [20-postmortem-s6-baseline.md](../sprints/s6-charter-capterra/20-postmortem-s6-baseline.md)
- PRs: [#228](https://github.com/wagnerra23/oimpresso.com/pull/228), [#229](https://github.com/wagnerra23/oimpresso.com/pull/229), [#230](https://github.com/wagnerra23/oimpresso.com/pull/230), [#231](https://github.com/wagnerra23/oimpresso.com/pull/231), [#232](https://github.com/wagnerra23/oimpresso.com/pull/232)

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-08 | Wagner + Opus | ADR criada e aceita após merge #232. Consolida 4 PRs S6 + abre S7 backlog (5 itens, ~24h). |

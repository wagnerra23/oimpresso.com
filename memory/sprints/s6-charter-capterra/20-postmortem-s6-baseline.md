# 20 — Postmortem S6 (baseline F4)

> **Fechamento do Sprint S6 — Sistema Charter-Capterra.**
> 4 fases entregues em 4 PRs, ~1 dia de wall-clock (sessão maratona 2026-05-07/08), ADR 0101 mergeada.

---

## O que entregou

### F1 Foundation ([PR #229](https://github.com/wagnerra23/oimpresso.com/pull/229))
- 5 specs em `memory/sprints/s6-charter-capterra/01-05.md`
- 5 charters Tier A em prod (1 + 4 novos: 2 completos + 2 stubs)
- Workflow `charter-gate.yml` em modo soft

### F2 Tooling partial ([PR #230](https://github.com/wagnerra23/oimpresso.com/pull/230))
- Skill `charter-first` Tier A ATIVA (`enabled: true`)
- Skill `charter-write` (Tier B)
- Artisan `charter:audit` + `charter:health`
- Cron daily 06:30 BRT
- `tests/Charter/baseline.json` vazio (modo soft mantido)
- Doc `06-charter-fetch-deploy-pendente.md` (deploy CT 100 sinalizado)

### F3 Capterra v2 ([PR #231](https://github.com/wagnerra23/oimpresso.com/pull/231))
- Skill `comparativo-do-modulo` v1.0 → v2.0 (3 eixos)
- Template apendado com `ux_heuristics:` + `automation_targets:`
- 5 fichas atualizadas (RB com dados reais; 4 placeholder TODO)
- Inventário v2 prova de conceito (RB) com diagnóstico em 3 eixos

### F4 Performance Testing (este PR)
- 5 specs em `memory/sprints/s6-charter-capterra/16-20.md` (este doc é o 20)
- 1 Pest agregador `tests/Charter/CharterMetricsTest.php` (M2 + M3 reais)
- 1 artisan `charter:metrics` (JSON output)
- Stubs honestos pra M1/M4/M5/M6 (depende telemetria/CT 100)

---

## Métricas baseline (F4 final)

### M2 — GUARD pass rate
**Resultado:** 5/5 charters Tier A passam Tier 1 GUARD (frontmatter + 8 sections + ❌ em Non-Goals).
**Status:** ✅ verde.

### M3 — Charter coverage Tier A
**Resultado:** 5/5 telas Tier A com charter (`/repair/dashboard`, `/repair/job-sheet`, `/financeiro/extrato/{id}`, `/repair/status`, `/financeiro/contas-bancarias`).
**Status:** ✅ 100%, supera alvo 80%.

### M1, M4, M5, M6
**Resultado:** N/A — depende de telemetria `mcp_audit_log` + ramp-up 7d em prod.
**Status:** ⏸️ aguarda S7.

---

## O que NÃO entregou (sinalizado)

| Peça | Por quê | Fica em |
|---|---|---|
| Tool MCP `charter-fetch` no CT 100 | Exige SSH humano (publication-policy) | Deploy separado pelo Wagner |
| Workflow `charter-gate.yml` hard mode | Precisa baseline aceito + 7d soft | F5 / S7 |
| Dashboard charter no `/copiloto/admin/qualidade` | UI separada do dashboard atual (Copiloto/Memória) | S7 |
| M1 token economy real | Telemetria `mcp_audit_log.charter_present` | S7 |
| M4 goal drift real | Telemetria `mcp_audit_log.tools_used` | S7 |
| M5 detector latency agregado | Cron que pega GitHub Actions API | S7 |
| M6 anti-hallucination ratchet | Baseline populado + Pest GUARD em prod canary | F5 ramp-up |
| 4 fichas Capterra v2 com UX/Auto reais (NfeBrasil/Project/ProjectMgmt/Whatsapp) | Curadoria humana + pesquisa de mercado | Wagner — trimestral |

---

## Aprendizados (pra próximas sessões S7+)

### O que funcionou bem
- **Estrutura "Ondas" (A/B/C+)** com diagnóstico antes de executar — Wagner validou o padrão ("ficou ótimo")
- **Auto-correção honesta** (NeurIPS reference exagerada → "convergência distribuída") — manteve credibilidade
- **PR pequenos por fase** (~1000 linhas cada) em vez de 1 PR gigante — review humano viável
- **Stubs honestos** com TODO claro pra peças que dependem de telemetria/CT 100 — não inventar

### O que poderia ter sido diferente
- **F2 partial** — eu deveria ter sinalizado deploy CT 100 logo no início da F2, não em arquivo separado depois. Mais transparente.
- **Skill `charter-write`** — escrita meio defensiva (PARA aguardando Wagner). Talvez seja over-engineered. Próximo uso vai dizer.
- **5 fichas v2 placeholder** — tentação de "preencher genérico pra ficar bonito" foi grande. Resisti, mas vale registrar pra próximas Capterra v2 conversions.

### Tier 0 ratchet
Nenhuma violação:
- Multi-tenant (`business_id`) — todos os charters mencionam isolamento
- Hostinger ≠ CT 100 — F2 partial respeitou (não tocou MCP no Hostinger)
- ADRs append-only — ADR 0101 nasceu nova; 0094, 0089, 0095 referenciadas mas não editadas
- ZERO auto-mem privada — toda governança canônica em git/MCP

---

## Próximo sprint (S7 sugerido)

S7 fecharia o que F4 deixou em stub:
1. Telemetria `mcp_audit_log.charter_present` + `tools_used` (~6h)
2. Cron M5 (latência) agregador GH API (~2h)
3. M6 ratchet baseline populado + Pest GUARD em prod canary (~4h)
4. Dashboard charter cards no `/copiloto/admin/qualidade` (~6h)
5. Skill `charter-evolve` implementação (~6h, spec em [19-charter-evolve-skill.md](19-charter-evolve-skill.md))

Total S7 ~24h spread em 2-3 cycles. Pode ser dividido em S7a (telemetria) + S7b (UI + automação).

---

## ADR de fechamento sugerido

Após Wagner aprovar este postmortem, criar **ADR 0102 — S6 Charter-Capterra postmortem + S7 backlog** consolidando:
- O que foi entregue vs original ADR 0101
- Métricas baseline (M2 100%, M3 100%, demais N/A)
- 5 itens pra S7 listados acima
- Lições aprendidas (3 itens acima)

ADR 0102 fica `accepted-historical` quando S7 completar.

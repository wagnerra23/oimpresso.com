---
id: requisitos-jana-briefing
module: Jana
status: producao
updated_at: "2026-09-24"
distilled_at: "2026-09-24"
distilled_by: jana:distill-module-truth
---

# BRIEFING — Jana (verdade destilada)

## Estado atual
O módulo Jana atua como a camada de IA do oimpresso, fornecendo chat com memória persistente, brief diário, sugestões de metas e avaliações. Em produção, ele mantém qualidade controlada por avaliações sistemáticas. A recente atualização incluiu a implementação de uma nova tela de superadmin e reorganização das fronteiras de código; o código MCP continua no módulo.

## Capacidades
- Implementação de chat com memória persistente, integrando `MemoriaContrato` e `MeilisearchDriver`.
- Disponibilidade de diversos agents para clarificação e sugestões de metas.
- Geração automatizada de brief diário e decisão via `HitlEscalationService`.
- Avaliações regulares (RAGAS), CI canary e telemetria em funcionamento.
- Telas Inertia implementadas: Index, Chat, Memoria, Alertas, Ações, Pro, Plataforma.

## Gaps
- Necessidade de melhorar a função de `context_recall`, que opera abaixo do ideal.
- Aperfeiçoamento na gestão de dados nas telas da Plataforma, onde tabelas de meta atualmente aparecem vazias.
- Aumento na cobertura de testes para minimizar falsos-positivos.

## Última mudança
Em 2026-09-21 e 2026-09-22 o Painel (`/ia`) foi reaproximado da âncora de design: grade de Análises em 3 colunas (#7638), grade e card de META como réplica da âncora (#7646), ritmo vertical de 18px (#7653), KPIs quebrando no breakpoint da âncora (#7655), gráficos da âncora (#7678) e o h1 a 600 por réplica local (#7681). O tier Pro passou a governar brief, análises e ações do Painel (#7587), e business sem histórico vê um estado de página em vez de 6 caixas vazias (#7591). Antes disso, em 2026-09-08, a onda 7 de paridade do lote Crm+Jana+Forja; e em 2026-09-03 a tela de superadmin substituiu o Blade anterior.

## Proveniência (destilado de)

- audit `requisitos/Jana/AUDIT-GAPS-2026-08-10.md` — AUDIT-GAPS-2026-08-10.md
- audit `requisitos/Jana/AUDIT-SENIOR-2026-05-25.md` — AUDIT-SENIOR-2026-05-25.md
- audit `requisitos/Jana/AUDITORIA-IA-OS-2026-06-06.md` — AUDITORIA-IA-OS-2026-06-06.md
- audit `requisitos/Jana/AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13.md` — AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13.md
- audit `requisitos/Jana/AUDITORIA-MODO-C-2026-05-09.md` — AUDITORIA-MODO-C-2026-05-09.md
- audit `requisitos/Jana/AUDITORIA-SESSION-HANDOFF-2026-05-13.md` — AUDITORIA-SESSION-HANDOFF-2026-05-13.md
- audit `requisitos/Jana/AUDITORIA-design-as-code-token-driven-2026-06-22.md` — AUDITORIA-design-as-code-token-driven-2026-06-22.md
- audit `requisitos/Jana/AUDITORIA-fidelidade-anti-drift-codegen-2026-06-22.md` — AUDITORIA-fidelidade-anti-drift-codegen-2026-06-22.md
- audit `requisitos/Jana/AUDITORIA-reconciliacao-tripla-analise-por-setor-2026-06-22.md` — AUDITORIA-reconciliacao-tripla-analise-por-setor-2026-06-22.md
- handoff `handoffs/2026-09-21-1730-grade-analises-3-colunas-e-a-tabela-que-mentia.md` (2026-09-21) — 2026-09-21-1730-grade-analises-3-colunas-e-a-tabela-que-mentia.md
- handoff `handoffs/2026-09-21-1815-ponteiros-de-fechamento-no-doc-de-paridade-da-jana.md` (2026-09-21) — 2026-09-21-1815-ponteiros-de-fechamento-no-doc-de-paridade-da-jana.md
- session `sessions/2026-09-18-triagem-uc-orfaos-de-lane.md` (2026-09-18) — 2026-09-18-triagem-uc-orfaos-de-lane.md
- handoff `handoffs/2026-09-15-1915-distiller-freshness-uniao-e-floor-zero.md` (2026-09-15) — 2026-09-15-1915-distiller-freshness-uniao-e-floor-zero.md
- session `sessions/2026-09-08-onda7-paridade-crm-jana-forja.md` (2026-09-08) — 2026-09-08-onda7-paridade-crm-jana-forja.md
- handoff `handoffs/2026-09-08-0924-onda7-lote-crm-jana-forja.md` (2026-09-08) — 2026-09-08-0924-onda7-lote-crm-jana-forja.md
- handoff `handoffs/2026-09-07-0810-descida-inline-0389-jana-cowork-sem-disco.md` (2026-09-07) — 2026-09-07-0810-descida-inline-0389-jana-cowork-sem-disco.md
- session `sessions/2026-09-06-alvo-jana-painel-exportado-por-maquina.md` (2026-09-06) — 2026-09-06-alvo-jana-painel-exportado-por-maquina.md
- session `sessions/2026-09-06-refutacao-gt-g5-distill-12-portas.md` (2026-09-06) — 2026-09-06-refutacao-gt-g5-distill-12-portas.md
- handoff `handoffs/2026-09-06-0924-alvo-jana-exportado-maquina-espera-fases.md` (2026-09-06) — 2026-09-06-0924-alvo-jana-exportado-maquina-espera-fases.md
- session `sessions/2026-09-04-ragas-agosto-lead-refutado-juiz-mudo.md` (2026-09-04) — 2026-09-04-ragas-agosto-lead-refutado-juiz-mudo.md
- session `sessions/2026-09-03-ct100-breaker-sync-memory-e-veredito-unico.md` (2026-09-03) — 2026-09-03-ct100-breaker-sync-memory-e-veredito-unico.md
- session `sessions/2026-09-03-watchdog-g6-tres-achados-ragas.md` (2026-09-03) — 2026-09-03-watchdog-g6-tres-achados-ragas.md
- handoff `handoffs/2026-09-03-0920-ct100-fora-breaker-e-veredito-unico.md` (2026-09-03) — 2026-09-03-0920-ct100-fora-breaker-e-veredito-unico.md
- handoff `handoffs/2026-09-03-1054-jana-plataforma-duas-sessoes-um-alvo-delta-6627.md` (2026-09-03) — 2026-09-03-1054-jana-plataforma-duas-sessoes-um-alvo-delta-6627.md
- session `sessions/2026-09-02-jana-abas-paridade-3-prs.md` (2026-09-02) — 2026-09-02-jana-abas-paridade-3-prs.md
- session `sessions/2026-09-02-ragas-real-colapso-diagnostico-bloqueado-ct100.md` (2026-09-02) — 2026-09-02-ragas-real-colapso-diagnostico-bloqueado-ct100.md
- handoff `handoffs/2026-09-02-2140-jana-abas-alertas-acoes-plataforma.md` (2026-09-02) — 2026-09-02-2140-jana-abas-alertas-acoes-plataforma.md
- session `sessions/2026-08-31-jana-p0-vazamento-e-d0-identidade-view.md` (2026-08-31) — 2026-08-31-jana-p0-vazamento-e-d0-identidade-view.md
- handoff `handoffs/2026-08-31-1054-jana-p0-tier0-faxina-e-d0-identidade.md` (2026-08-31) — 2026-08-31-1054-jana-p0-tier0-faxina-e-d0-identidade.md

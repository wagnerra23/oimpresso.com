---
id: requisitos-jana-briefing
module: Jana
status: producao
updated_at: "2026-10-02"
distilled_at: "2026-10-02"
distilled_by: jana:distill-module-truth
---

# BRIEFING — Jana (verdade destilada)

## Estado atual
O módulo Jana funciona como a camada de IA do Oimpresso, oferecendo chat com memória persistente, brief diário, sugestões de metas e avaliações. Atualmente em produção, o módulo possui controle de qualidade através de avaliações regulares e incorpora uma nova tela de superadmin, além de reorganização das fronteiras de código.

## Capacidades
- Chat com memória persistente, integrado com `MemoriaContrato` e `MeilisearchDriver`.
- Diversos agents disponíveis para sugestões de metas e esclarecimentos.
- Geração automatizada de briefs diários e decisões via `HitlEscalationService`.
- Avaliações regulares (RAGAS) e telemetria funcionando.
- Telas Inertia implementadas: Index, Chat, Memoria, Alertas, Ações, Pro, Plataforma.

## Gaps
- Necessidade de aprimorar a função `context_recall`, que ainda não opera de forma ideal.
- Melhoria na gestão de dados nas telas da Plataforma, onde as tabelas de meta estão vazias.
- Aumento na cobertura de testes para reduzir falsos positivos.

## Última mudança
Desde a destilação anterior (2026-09-24): a permissão da Jana foi provada por teste (UC-JPERM-01..06, #7863) e a trava de `jana.chat` e `jana.metas.manage` foi ligada (#7895); as fixtures ganharam `jana.metas.manage` e o teste de 404 cross-tenant voltou a medir de fato na lane jana-pest (#8015); install/uninstall/update da Jana saíram de GET (#8411); e o webhook `sync-memory` passou a atualizar só `memory/`, com código de produção chegando só pelo deploy (#8547, ADR 0425).

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

---
id: requisitos-jana-briefing
module: Jana
status: producao
updated_at: "2026-09-15"
distilled_at: "2026-09-15"
distilled_by: jana:distill-module-truth
---

# BRIEFING — Jana (verdade destilada)

## Estado atual
O módulo Jana é a camada de IA do oimpresso, proporcionando um chat com memória persistente, brief diário, sugestões de metas e avaliações. Está em produção e sua qualidade é monitorada via avaliações específicas, com recente atualização em permissões e estrutura de telas. A nova tela de superadmin foi implementada em 2026-09-03 e as fronteiras do código foram reorganizadas, enquanto o código MCP permanece no módulo.

## Capacidades
- Chat com memória persistente utilizando `MemoriaContrato` e `MeilisearchDriver`.
- Diversos agents disponíveis para funções como clarificação e sugestões de metas.
- Brief diário automatizado e mecanismo de decisão via `HitlEscalationService`.
- Avaliações constantes (RAGAS) e canary em CI, junto com monitoramento via telemetria.
- Telas Inertia implementadas (Index, Chat, Memoria, Alertas, Acoes, Pro, Plataforma).

## Gaps
- Melhoria necessária em `context_recall`, que está abaixo do alvo.
- Necessidade de aperfeiçoar a gestão de dados nas telas da Plataforma, que atualmente apresentam tabelas de meta vazias.
- Aumentar a cobertura de testes para reduzir falsos-positivos.

## Última mudança
Onda 7 de paridade inventário↔tela no lote Crm+Jana+Forja (2026-09-08), junto com o drawer da meta absorvendo `metas/show` e `fontes/show`, o comando artisan que revoga o scope `admin_only` concedido antes do filtro, e a correção do pós-login — o ramo `jana.access` mandava admin pra um Painel vazio. Antes disso, em 2026-09-03, a tela de superadmin substituiu o Blade anterior e as fronteiras de Governance/MCP foram reorganizadas.

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
- session `sessions/2026-08-18-visreg-manifesto-cobertura-vs-escalonamento.md` (2026-08-18) — 2026-08-18-visreg-manifesto-cobertura-vs-escalonamento.md
- session `sessions/2026-08-17-jana-chat-gaps-do-card-tres-ja-existiam.md` (2026-08-17) — 2026-08-17-jana-chat-gaps-do-card-tres-ja-existiam.md
- handoff `handoffs/2026-08-17-1810-jana-instrumentos-que-calam-e-o-outage.md` (2026-08-17) — 2026-08-17-1810-jana-instrumentos-que-calam-e-o-outage.md

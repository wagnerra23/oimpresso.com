---
id: requisitos-jana-briefing
module: Jana
status: producao
updated_at: "2026-10-14"
distilled_at: "2026-10-14"
distilled_by: jana:distill-module-truth
---

# BRIEFING — Jana (verdade destilada)

## Estado atual
O módulo Jana opera como a camada de inteligência artificial do Oimpresso, providenciando um chat com memória persistente, briefs diários, sugestões de metas e avaliações. Está atualmente em produção, com controle de qualidade ativo e melhorias na interface, incluindo uma nova tela de superadmin e reorganização do código.

## Capacidades
- Chat com memória persistente, interagindo com `MemoriaContrato` e `MeilisearchDriver`.
- Disponibilidade de diversos agents para sugestões de metas.
- Geração automatizada de briefs diários e decisões por meio do `HitlEscalationService`.
- Avaliações regulares (RAGAS) e sistema de telemetria implementados.
- Telas Inertia em funcionamento: Index, Chat, Memoria, Alertas, Ações, Pro e Plataforma.

## Gaps
- Aprimoramento necessário na função `context_recall`, que ainda não atende plenamente às expectativas.
- Gestão de dados nas telas da Plataforma requer melhorias, pois as tabelas de meta estão sem informações.
- Expansão da cobertura de testes para minimizar a ocorrência de falsos positivos.

## Última mudança
Desde a destilação anterior (2026-10-02): o chat da Jana chegou ao app (tela 25, #8596), e as telas 39 e 25 passaram a falar com a Jana por contrato do núcleo (#8614); a Jana passou a sugerir tributação no NfeBrasil (#8997), com a catraca de acoplamento NfeBrasil/Jana fechada (#9052); o SPEC das sessões CC fixou que `cc.read.team` vê só as próprias sessões (#9091); e o `FonteApuraRecorteCorretoTest` deixou de depender do dia do mês (#8613). Os contratos Pest das telas de governança Custos e QualidadeIa, que testam `CustosService` e `MemoriaMetrica`, passaram a morar em `Modules/Jana/Tests/Feature/Governance/` e rodam na lane `jana-pest` (#9368).

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

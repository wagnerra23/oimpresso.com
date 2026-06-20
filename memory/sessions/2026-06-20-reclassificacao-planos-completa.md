---
date: "2026-06-20"
topic: "Reclassificação COMPLETA de planos do oimpresso — varredura de 61 docs-plano + 257 sessions/handoffs via 39 agentes adversariais (status real verificado contra git). Achado-mãe: o universo real de planos é ~43 docs + ~155 iniciativas PERDIDAS em session logs (nunca viraram doc rastreado), vs apenas 15 no PLANS-INDEX hand-made. Recomenda tornar o índice GERADO + triagem da cauda perdida."
authors: [C]
related_adrs: ["0256-knowledge-survival-meia-vida-catraca-sentinela", "0070-jira-style-task-management-current-md-removed", "0105-cliente-como-sinal-guiar-sem-mandar"]
prs: []
---

# Reclassificação completa de planos (2026-06-20)

> Método: varredura mecânica achou o universo (117 arquivos plan-name + 257 sessions/handoffs com conteúdo de plano). 39 agentes em 2 streams: (A) 61 docs-plano reclassificados por **status real verificado contra git** (não o rótulo do doc); (B) 257 sessions/handoffs minerados por planos embutidos/perdidos. Fonte-única dos vereditos: workflow `reclassificar-planos-tudo` (run wf_e55706a2). Verificação cruzada com os 7 planos do `verificar-planos-ativos`.

## Saúde do universo de planos

| Métrica | Valor |
|---|---|
| Docs varridos por nome | 61 (dos 117 brutos; resto = DB-schema/dominios) |
| **Docs que são plano de verdade** | **43** (18 são ADR/runbook/feedback/dossiê) |
| No PLANS-INDEX hand-made | **15** → **~28 docs-plano fora do índice** |
| **Iniciativas PERDIDAS em sessions** | **~155 distintas** (de 349 mineradas) sem doc rastreado |
| **Universo real de planos** | **~43 docs + ~155 perdidas ≈ 200**, vs 15 indexados |

Status real dos 43 docs-plano: **6 em-execução · 11 ativo-parado · 13 proposto · 7 concluído · 4 superseded · 2 pausado**. → Só **6/43 estão vivos e movendo**; 13 concluídos/superseded ainda figuram como "ativos" por drift.

## Os 6 planos REALMENTE ativos (is_plan & is_active, verificado)

| Plano | % | Estado real |
|---|--:|---|
| [roadmap-tecnico-12m-2026-2027](memory/decisions/proposals/roadmap-tecnico-12m-2026-2027.md) | 5% | **De-facto master roadmap** (Cycle 25 batendo os goals) — nunca promovido a ADR. → **PROMOVER ao índice** |
| [Accounting/DEPRECATION-PLAN](memory/requisitos/Accounting/DEPRECATION-PLAN.md) | 75% | Ondas 0-5 feitas; Onda 6 (DROP) aguarda janela 90d (~ago/2026) |
| [OficinaAuto/ROADMAP](memory/requisitos/OficinaAuto/ROADMAP.md) | 60% | Fase 3 ativa há 31d; Vargas V1 pendente |
| [_Roadmap_Faturamento](memory/requisitos/_Roadmap_Faturamento.md) | 45% | Financeiro/NfeBrasil/RecurringBilling/Jana todos com implementação substancial |
| [Fiscal/PLANO-TESTES-FISCAL](memory/requisitos/Fiscal/PLANO-TESTES-FISCAL.md) | 35% | Ondas 1+2+6 feitas; Ondas 3,4,5,7 não iniciadas (gap IBS/CBS) |
| [ComunicacaoVisual/ROADMAP](memory/requisitos/ComunicacaoVisual/ROADMAP.md) | 25% | Fase 1 (scaffold) feita; Fase 2 aguarda sinal de cliente (ADR 0105) |

## Correções ao PLANS-INDEX atual (drift dos 15)

- **marcar concluído (7):** PaymentGateway/PLANO-ONDA5, Financeiro/PLANO-FASE1-CONCILIACAO, Infra/PLANO-MIGRACAO-AUTOMEM, decisions/0016, 0037, 0055, 0169.
- **revisar (rótulo errado):** Financeiro/PLANO_DETALHADO → na verdade **superseded** (módulo passou em Onda 2-3); Copiloto/JANA-PRO → **ativo-parado** (JANA-A 20%, falta Job/schedule); Mwart/ONDA-1-PDV → **ativo-parado** (travado em [W]).
- **arquivar (16):** 07-roadmap (stale), 0036/0069 (superseded), proposals/daas-operacional, proposals/roadmap-jana-12m, PLANO-DESIGN-TELAS, Autopecas/PLANO-MIGRACAO-VARGAS, Comissao/Garantia/FinanceiroAvancado/ROADMAP, Jana/ONDA-5-DOSSIER, demo-martinho/plano-paralelizacao, AUTOMATION-ROADMAP, 2× rollback-plan de sprint, TaskRegistry/0001.
- **marcar abandonado (1):** Crm/ROADMAP.
- **falsos-positivos (18 não-planos):** ADRs/dossiês/runbooks/feedback que casaram por nome.

## ~155 planos PERDIDOS em session logs (a cauda invisível)

Iniciativas estratégicas propostas em sessões que **nunca viraram doc rastreado nem entraram no índice** — agrupadas por tema. (one-liner + estado; evidência completa no transcript do run.)

### SDD / Governança (~18)
sdd-memoria-unificada-porta-unica · sdd-uniao-keystone-minima (33/100) · sdd-gate-required · sdd-porta-indice (13 índices→1) · sdd-distiller-modulo-verdade (em-andamento) · sdd-kl-e2-e3-lanes-b-c · sdd-lane-d-cauda-longa (~1078 falhas) · sdd-reestruturacao-4-ondas · sdd-sqlite-corruptors-burn-down (237) · sdd-spec-anchored-full-suite-verde · sdd-us-gov-020-revert-a2 (p0 net-harmful) · sdd-without-global-scopes-enforcement (~89 violações) · sdd-refutadores-d1 · sdd-d4-d5 · gates-onda2-item7-fusao-cor · governance-sprint-2-cleanup · governance-v3-wave-13-14 · governance-wave-23-24 · knowledge-drift-rename-propagation · memory-index-regen-automatico · module-state-mcp-tool · subagent-orchestration-nativo-piloto

### Design System (~14)
ds-maturidade-6-ondas (61→84) · ds-identidade-elevacao-66-85 · ds-critic-wrapper-veto · ds-adocao-169-incertos-sweep · ds-rollout-ledger-pr2682 · manual-css-js-roadmap-f0-f7 (em-andamento) · css-hex-drift-fase2-158 · f3-primitivos-layout · listpage-pt01-as-code · mapa-identidade-erp-fases · rotinas-design-consolidacao-g1-g6 · r3-ds-regression-gate-ci · design-review-fase2-juiz-llm · determinizacao-llm-judge · design-request-ledger-mcp

### WhatsApp / Atendimento (~16)
whatsapp-channel-reliability-roadmap (em-andamento, 5/10) · whatsapp-channel-reliability-slo · whatsapp-channel-reliability-consolidar · whatsapp-loggedout-fase-a · wa-bot-policy-engine · wa-anti-cross-contact-p0-recovery-sql · whatsapp-channel-access-cleanup-prod · atendimento-automatico-bot-cliente · caixa-os-vinculada (C1/C2) · caixa-unificada-os-vinculada-tom-canal · customer-360-sidebar · customer-memory-roadmap-ondas-2-6 · customer-memory-inferencias-onda3 · voc-omnichannel-gaps · voz-cliente-5-ondas · cmd-k-global-palette · oficina-whatsapp-aprovacao-orcamento

### Financeiro / Bancos / Pagamentos (~16)
recurring-billing-gateway-ativacao (109 assinaturas) · recurring-billing-backend-cached-history-onda24 · paymentgateway-onda-5-dogfooding · paymentgateway-onda-6-cleanup · financeiro-advisor-portal-fase-2 · financeiro-onda24-25-polish · finstatstrip-rollout-6-telas · integracao-open-banking-top5-bancos · top5-bancos-rest-api-drivers · banco-bb-pix-automatico · santander-pix-automatico · sicoob-api-migracao-biz164 · itau-bolecode-onda4f1b · boleto-ocr-gpt4o-vision-prod · timezone-format-date-migracao · caixa-rest-api-driver-futuro

### Fiscal / NFe (~5)
fiscal-sped-wave-10-pis-cofins · nfe-foundation-us-nfe-040 (16h débito) · nfse-500-fix-schema-race · gold-recuperacao-manifestacao-destinatario · (Fiscal testes ondas 3-7 — doc ativo)

### Migração legacy / clientes (~9)
migracao-6-saudaveis-officeimpresso · migracao-vargas-autopecas · migracao-estoque-catalogo-martinho-biz164 · migracao-martinho-vendas-faltantes-2k · migracao-firebird-boletos-contratos (30k boletos/313 contratos) · migracao-legacy-plano-agentes-entidades · legacy-migration-vargas-gold-extreme · jana-saas-onboarding-flow-firebird · roadmap-2-semanas-reconquista-martinho

### Sells / PDV (~7)
sells-v2-paridade-blade-biz4 · sells-create-design-arte-ux · sells-edit-parking-lot-p1-p5 · sells-nfce-inline-create · sells-cowork-onda7-plus · fsm-rollout-vendas-legadas-biz1 / us-sell-036-fsm-bulk-start · ux-blade-decommission-5fases

### KB (~6)
kb-acl-aware-rag (P0) · kb-content-gap-stale-detection · kb-onda3-block-editor · kb-onda6-erp-grafo · kb-voice-whatsapp-pt-br · kb-bench-v3-23-dimensoes

### Jana / IA / ADS (~10)
jana-roadmap-12m-habit-multi-vertical · ia-os-onda2-endurecer · ia-os-onda3-estrategico · ia-enable-tier0-prod · ia-champion-makers-tier0 · ads-fsm-bridge-cobradora-piloto · hitl-audit-card-ui · prompt-caching-live-laravel-ai · bi-temporal-event-time-adr0074 · adr0270-ciclo-vida-f1-f5

### Vendas / Marketing (~6)
blog-editorial-30-posts-seo · linkedin-outbound-playbook · canais-setoriais-abicomv · programa-afiliados · pricing-3-ajustes-urgentes · integracoes-estrategicas-12m

### Módulos novos / Infra / Testes (~15)
modules-equipe-slack-interno (aprovado, nunca iniciado) · novos-modulos-workflow-scaffold · garantia-cross-vertical-workflow (6 decisões W) · compras-blade-inertia-migration · repair-roadmap-3-fases · vestuario-paridade-linx-2q · pontowr2-fase3-relatorios · pontowr2-fase4-esocial-mobile · api-docs-mvp-swagger (~20h) · dam-nativo-waiting-list · network-effect-engine · mcp-server-vertical-brasileiro · contract-tests-tier2-browser · tdad-lite-cobertura · plano-testing-ondas123 (em-andamento) · visual-regression-pixel-baselines (em-andamento) · screen-qa-dim16-sentinela · deploy-sha-webhook-fix · content-reconciler-safe-heal · anti-regressao-codigo-pecas-3 · drawer-760-wave-h · admin-center-3pr-roadmap · cowork-diagnostico-fase2

## Recomendação (aproveitar os índices)

O `PLANS-INDEX` v1 hand-made captura só 15 de ~200. Para "não perder" de verdade:

1. **Tornar o índice GERADO** — `plans-index` generator (mesmo padrão do índice de ADR já existente, ADR 0256), lendo um bloco `## Status vivo` de cada doc-plano. Fonte-única = o plano; o índice é derivado. Sentinela `plan-health` no Daily Brief flaga drift/órfão/stale.
2. **Aplicar as dispositions acima** aos 43 docs (7 concluídos, 16 arquivar, 1 abandonado, correções de rótulo) — zera o drift do índice atual.
3. **Triar a cauda de ~155 perdidas** — a maioria é backlog real (ex: recurring-billing-gateway-ativacao 109 assinaturas, kb-acl-aware-rag P0, sdd-us-gov-020 p0 net-harmful). Decisão: virar task MCP (`parent_plan`) OU doc-plano OU descartar conscientemente. Não dá pra fazer cego — precisa do seu filtro por sinal de cliente (ADR 0105).

> Artefato gerado por workflow `reclassificar-planos-tudo` (39 agentes, 2.8M tokens). Vereditos crus + evidência por item no transcript do run wf_e55706a2.

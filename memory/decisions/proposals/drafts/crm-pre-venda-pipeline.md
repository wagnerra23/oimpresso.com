---
slug: crm-pre-venda-pipeline
title: "CRM Pré-venda — estender Modules/Crm legacy + ponte Whatsapp + Jana intencao + handoff FSM Sells"
type: adr-proposal
status: proposed
proposed_by: opus-4.7
proposed_at: 2026-05-12
proposed_for_decision_by: [W]
module: Crm
authority: canonical
lifecycle: draft
tier: CANON
tags: [crm, pipeline, pre-venda, whatsapp, jana-ia, fsm, multi-tenant, lgpd]
related_adrs: [0093, 0094, 0096, 0104, 0105, 0117, 0121, 0143]
related_modules: [Whatsapp, Sells, Jana, RecurringBilling]
parent_spec: memory/requisitos/Crm/SPEC.md
review_triggers:
  - "Wagner aprovar/recusar D1-D5"
  - "1º cliente externo (não-ROTA-LIVRE) pedir CRM ativo"
  - "RD Station ou Pipedrive lançar Brazil-only API de integração"
  - "Volume leads >100/mês em qualquer business — revisar scoring de regras pra ML"
pii: false
---

# ADR proposal — CRM Pré-venda integrado

## Status

**Proposed** (2026-05-12). Aguarda decisão Wagner D1-D5 antes de criar tasks MCP.

> ⚠️ Esta ADR é **draft** em `memory/decisions/proposals/drafts/`. Quando aprovada, vira `memory/decisions/0NNN-crm-pre-venda-pipeline.md` com `status: accepted` e cadeia `supersedes_partially: [Modules/Crm/adr/arq/0001]` se modificar princípio "estende contacts".

## Contexto

### Sinal qualificado (ADR 0105)

Wagner em 2026-05-12 solicitou plano CRM pré-venda. Sinais convergentes:

1. **Post-mortem Gold** (`memory/research/clientes-legacy-officeimpresso/04-gold-comvis/01-perfil.md`): OfficeImpresso perdeu Gold pra Mubisys parcialmente por **falta de follow-up estruturado** — vendedor não acompanhou após orçamento.
2. **6 OfficeImpresso saudáveis em pipeline pré-vendas** (Vargas, Extreme, Gold, Zoom, Fixar, Produart) — sem CRM hoje viram churn risk.
3. **WR2 (biz=1) opera 2 números Whatsapp** (Comercial + Financeiro, ADR 0117) — entrada de lead via Whatsapp é fluxo principal mas hoje NÃO vira contacts/lead automaticamente.
4. **FSM Sells live em prod** (ADR 0143, marco 2026-05-12) — pipeline canônico inicia em stage `quote_draft` mas hoje nada antecede ele formalmente; conversão lead→customer é flip de coluna sem disparar FSM.

### Discovery do legado (resumo §0 SPEC)

`Modules/Crm/` já tem 21 controllers + 11 entities + 26 migrations cobrindo Lead CRUD, Kanban, Follow-up + lembrete cron, Proposta, Campanha, Call log, Marketplace, Portal cliente, Dashboard, Relatórios. **NÃO é greenfield** — duplicar seria desperdício.

Gaps reais identificados (do que mercado tem e oimpresso NÃO):
1. Whatsapp ↔ CRM desconectado (`grep -r Crm Modules/Whatsapp` = zero matches)
2. `LeadController::convertToCustomer` não dispara FSM Sells `quote_draft`
3. Sem Jana IA classificador de intenção mensagem entrante
4. Sem lead scoring (hot/warm/cold automático)
5. Sem SLA novo lead (vendedor não é alertado)
6. Sem motivo-perda estruturado (perde Gold sem saber por que)
7. Sem LGPD compliance formal (opt-in/out, direito esquecimento)
8. Sem API pública captura externa (form landing)
9. Stack legacy Blade+jQuery+DataTables (MWART pendente, prioridade baixa)

## Decisão (proposta)

**Estender `Modules/Crm/` legacy** com 7 blocos de US (A-G, 21 user stories no SPEC §6), priorizando ponte Whatsapp + handoff FSM como P0. NÃO criar `Modules/CrmPipeline` paralelo.

### Decisões a fechar Wagner (D1-D5)

#### D1 — Estender Modules/Crm OU criar Modules/CrmPipeline?

| Opção | Prós | Contras |
|---|---|---|
| **A — Estender Modules/Crm** (recomendado) | Preserva 21 controllers + 26 migrations funcionais; segue princípio ADR 0011 imitação; menos sobrescrita | Modules/Crm é grande (52 routes, MWART risco alto) — alguma feature nova pode esbarrar em legacy quirks |
| B — Modules/CrmPipeline novo | Liberdade arquitetural total; React-first do dia 1 | Duplica conceitos (lead, follow-up, dashboard); migração 2 caminhos em paralelo; viola ADR 0121 (módulo vertical é especialização, não duplicação core) |

**Recomendação**: A. Estender.

#### D2 — Conversão Lead → Customer automática (dispara FSM) ou manual (vendedor cria Transaction depois)?

| Opção | Prós | Contras |
|---|---|---|
| **A — Automática** via `ConvertLeadToSaleService` (recomendado) | Zero retrabalho vendedor; vínculo bidirecional `won_transaction_id`; FSM Sells inicia 100% dos casos; respeita ADR 0143 spirit | Vendedor pode mover stage="Ganho" prematuramente — UI precisa exigir confirmação |
| B — Manual | Vendedor decide quando criar Transaction | Risco lead "Ganho" sem Transaction = forecast errado; quebra audit trail end-to-end |

**Recomendação**: A. Automática com modal confirmação.

#### D3 — Lead scoring: regras simples agora OU ML quando tiver dados?

| Opção | Prós | Contras |
|---|---|---|
| **A — Regras simples Sprint 1 + ML Sprint N (recomendado)** | Operacional dia 1; transparente (Wagner ajusta peso); ML após 6m dados reais | Acurácia inicial limitada — ajustes mensais |
| B — ML direto | Sofisticado | Cold start sem dados; black box dificulta debug; viola ADR 0107 (Tier 0 explicabilidade) |

**Recomendação**: A. Regras simples + ML quando >500 leads/business/mês.

#### D4 — SLA novo lead: hard-block (não move stage) OU soft-alert (avisa superior)?

| Opção | Prós | Contras |
|---|---|---|
| **A — Soft-alert (recomendado)** | Não bloqueia operação; alerta gerente | Vendedor lazy pode ignorar |
| B — Hard-block | Força resposta dentro SLA | Cliente esperando pode ficar pior |

**Recomendação**: A. Soft-alert + dashboard exibe SLA% per user.

#### D5 — Integração externa (RD Station, Pipedrive, HubSpot): nativa OU webhook genérico?

| Opção | Prós | Contras |
|---|---|---|
| **A — Webhook genérico (recomendado)** | Funciona com qualquer CRM externo via Zapier/Make; sem custo manutenção API per-vendor | Cliente final precisa configurar Zapier (curva aprendizado) |
| B — Integração nativa RD Station | UX perfeita pra usuários RD existentes | 1 vendor de cada vez, custo manutenção, ADR 0105 — só construir com cliente pagante |

**Recomendação**: A. Webhook genérico. Nativa só quando cliente pagar antes (ADR 0105).

## Consequências (se aceito)

### Positivas

- ✅ Wagner para de perder leads via WhatsApp ROTA LIVRE (99% volume oimpresso) por falta de follow-up estruturado
- ✅ Handoff `Lead Won → Sells FSM quote_draft` fecha loop ADR 0143
- ✅ Audit trail completo lead → venda → faturamento (LGPD compliance + post-mortem clientes perdidos)
- ✅ Jana IA aproveita mensagens WhatsApp pra qualificar lead (sinal alto valor)
- ✅ Diferencial vs Bling/Conta Azul CRM (que não tem IA classificadora BR-PT nativa)

### Negativas

- ❌ Sprint 1+2+3 (~80h IA-pair fator 10x = ~8h de Wagner+Claude)
- ❌ Modules/Crm legacy fica MAIS denso antes de MWART (mais débito) — mitigação: MWART crm:lead-kanban inclusa US-CRM-060
- ❌ JanaIntencaoAgent custo Groq ~$5-20/business/mês — gating ADS-route obrigatório (ADR 0107)
- ❌ Risco: round-robin com 1 vendedor (caso WR2 tem Wagner+Eliana+Maiara+Felipe+Luiz mas só Wagner pega Comercial hoje) pode realocar mal — mitigação: configuração per business via `crm_settings.round_robin_users[]`

### Riscos (não-mitigados ainda)

1. **R1 — Jana classifica errado** (lead virou cobrança ou spam) → vendedor não vê lead real. **Mitigação**: confidence threshold + HITL queue + métrica `jana_intencao_acuracia` daily.
2. **R2 — Volume WhatsApp entrante alto explode tabela `crm_lead_interactions`** (append-only). **Mitigação**: índice `(business_id, contact_id, occurred_at)` + partition por mês após 1M rows.
3. **R3 — LGPD opt-in via WhatsApp não conforme ANPD** (consentimento assíncrono). **Mitigação**: counsel jurídico antes US-CRM-050; Eliana lifeline (advogada, time interno) revisa.

## Alternativas consideradas

### Alt 1 — Reescrever Modules/Crm do zero em Inertia/React

❌ Rejeitado. 21 controllers + 11 entities + 26 migrations + 68 views funcionais — reescrita = 200H+ sem entregar nada novo. MWART incremental tela-a-tela respeita ADR 0104.

### Alt 2 — Integrar RD Station como CRM externo (oimpresso só vira ERP)

❌ Rejeitado. (a) Cliente paga 2 SaaS, (b) leads ficam fora de governança oimpresso (LGPD shared responsibility), (c) Jana não vê dados pra dar contexto, (d) ROTA LIVRE não usa RD Station — adicionar fricção sem upside.

### Alt 3 — Pipedrive embed via iframe

❌ Rejeitado. UX ruim, sem integração FSM, custo USD per vendedor (Pipedrive Advanced US$29/user/mês × N vendedores).

### Alt 4 — Esperar 1º cliente pagar antes de construir

🟡 Considerado. Argumento: ADR 0105 cliente como sinal. **Contraponto**: WR2 (biz=1, time interno Wagner+Eliana+Maiara+Felipe+Luiz) JÁ é o cliente — eles operam o oimpresso comercial e perdem leads hoje. Sinal qualificado interno. Recomendação: avançar US bloco B (Whatsapp↔CRM) + bloco C (handoff FSM) como P0; outros blocos esperam sinal externo.

## Cadeia de aprovação

1. Wagner lê SPEC `memory/requisitos/Crm/SPEC.md`
2. Wagner decide D1-D5 + lê MATRIZ-ROI + ROADMAP
3. Se aprovar: Claude renomeia este draft pra `memory/decisions/0NNN-crm-pre-venda-pipeline.md` com `status: accepted` + sequência número canon
4. Cria tasks MCP via `tasks-create` com refs `US-CRM-001..062`
5. Sprint 1 inicia com bloco A+B+C (P0)

## Refs

- Discovery completo: `memory/requisitos/Crm/SPEC.md §0`
- Pesquisa mercado: `memory/requisitos/Crm/MATRIZ-ROI.md`
- Plano fases: `memory/requisitos/Crm/ROADMAP.md`
- ADR 0093 multi-tenant Tier 0
- ADR 0096 Whatsapp módulo mãe
- ADR 0105 cliente como sinal
- ADR 0117 multi-número Whatsapp por business
- ADR 0143 FSM Pipeline live (marco 2026-05-12)

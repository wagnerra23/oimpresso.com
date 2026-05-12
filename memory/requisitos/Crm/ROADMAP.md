---
module: Crm
type: roadmap
status: draft-proposal
generated_at: 2026-05-12
by: opus-4.7
parent_spec: SPEC.md
parent_adr_proposal: memory/decisions/proposals/drafts/crm-pre-venda-pipeline.md
---

# ROADMAP — CRM Pré-venda

> 5 fases sequenciais. Estimates ADR 0106 (fator 10x IA-pair Wagner+Claude). H = horas síncronas Wagner.

> ⚠️ NÃO iniciar Fase 1 sem Wagner aprovar ADR proposta D1-D5 + criar tasks MCP via `tasks-create`.

---

## Fase 0 — Aprovação + decisões (semana 1)

**Objetivo**: fechar decisões Wagner antes de qualquer código.

**Custo**: 1-2H síncronas Wagner.

**Entregáveis**:
1. Wagner lê `SPEC.md` + `MATRIZ-ROI.md` + ADR proposal
2. Wagner decide D1-D5 (comentários inline na ADR ou async)
3. Se aprovar:
   - Claude renomeia ADR draft → `memory/decisions/0NNN-crm-pre-venda-pipeline.md` accepted
   - Claude cria batch tasks MCP via `tasks-create` (todas US-CRM-001..062 com refs SPRINT-N)
   - Wagner valida lista tasks
4. Counsel jurídico LGPD revisa US-CRM-050 (Eliana lifeline) — opcional mas recomendado antes Sprint 2

**Gates de saída**:
- [ ] ADR `accepted`
- [ ] Tasks MCP criadas com módulo, prio, owner
- [ ] Decisão Wagner registrada em comments

---

## Fase 1 — Foundation + ponte Whatsapp + handoff FSM (Sprint 1)

**Objetivo**: estender legacy Modules/Crm + capturar lead WhatsApp + handoff `Ganho → Sells FSM quote_draft`.

**Duração**: 2 semanas calendário · ~50H IA-pair (5H síncronas Wagner ADR 0106).

**Escopo (10 US, P0)**:

| US | Título | H | Owner sugerido |
|---|---|---|---|
| US-CRM-001 | Migration estender `contacts` (pipeline columns) | 2 | [F] ou [W+C] |
| US-CRM-002 | `crm_motivos_perda` catálogo + seeder padrão | 2 | [L+C] |
| US-CRM-003 | `crm_lead_interactions` timeline append-only + observers | 4 | [F+C] |
| US-CRM-010 | Listener `WhatsappMessageReceived` cria/resolve lead | 4 | [W+C] |
| US-CRM-011 | `JanaIntencaoAgent` classifica intenção (laravel/ai Groq) | 6 | [W+C] |
| US-CRM-012 | `CrmLeadFromWhatsappService.createOrUpdate` + round-robin | 4 | [F+C] |
| US-CRM-014 | Vincular `whatsapp_conversations.contact_id` ao Lead | 2 | [L+C] |
| US-CRM-020 | `ConvertLeadToSaleService` cria Transaction + dispara FSM | 6 | [W+C] (toca ADR 0143 — sensível) |
| US-CRM-021 | UI kanban "Ganho" dispara conversão + redireciona SaleSheet | 4 | [F+C] |
| US-CRM-022 | UI kanban "Perdido" modal motivo + data reabordagem | 4 | [F+C] |
| US-CRM-050 | LGPD opt-in/out + direito esquecimento (foundation) | 6 | [W+C+E] (Eliana revisa jurídico) |
| **US-CRM-070** (novo) | **Pest suite cross-tenant biz=1+biz=99 CrmLeadCrossTenantTest** | 4 | [F+C] |
| **US-CRM-071** (novo) | **Smoke biz=1 (cliente real WR2) — mensagem Whatsapp → lead → kanban → Ganho → Transaction** | 4 | [W+C] |

**Total**: 52H IA-pair ≈ 5.2H Wagner síncrono.

**Critérios de saída Fase 1**:
- [ ] Mensagem WhatsApp entrante de mobile desconhecido em biz=1 vira `crm_lead` automático
- [ ] Vendedor arrasta card pra "Ganho" → cria Transaction com `current_stage_id=quote_draft` (ADR 0143 LIVE)
- [ ] Vendedor arrasta card pra "Perdido" → modal motivo obrigatório
- [ ] Pest cross-tenant verde
- [ ] Smoke biz=1 verde (NUNCA biz=4 cliente em smoke — feedback Wagner 2026-05)
- [ ] LGPD opt-in registrado em todo lead criado via WhatsApp ou API
- [ ] Charter `resources/js/Pages/Crm/Kanban.charter.md` rascunhado (preparação Fase 4 MWART)

**Riscos Fase 1**:
- R1 Jana classifica errado → vendedor perde lead. **Mitigação**: HITL queue + métrica acurácia daily + threshold confidence 0.6.
- R2 Modal motivo perda atrita vendedor. **Mitigação**: pré-popular motivos comuns + permitir "outro" texto livre.

---

## Fase 2 — Inteligência + escala (Sprint 2)

**Objetivo**: lead scoring + SLA alerta + tags + dashboard KPIs novos.

**Duração**: 2 semanas · ~34H IA-pair.

**Escopo (8 US, P1)**:

| US | Título | H |
|---|---|---|
| US-CRM-004 | `crm_lead_tags` + pivot | 2 |
| US-CRM-013 | Auto-resposta template WhatsApp ao criar lead | 2 |
| US-CRM-023 | Cron `lead:reactivate-cold` reabre leads timing-vencido | 2 |
| US-CRM-030 | `crm_lead_scoring_log` + cron `lead:rescore` | 6 |
| US-CRM-031 | SLA novo lead → alerta superior vencido | 4 |
| US-CRM-032 | Dashboard pipeline forecast (sum valor_estimado/stage) | 6 |
| US-CRM-040 | API REST pública `/api/v1/crm/leads` (token + reCAPTCHA + rate-limit) | 6 |
| US-CRM-072 (novo) | Hardening LGPD — counsel feedback aplicado | 4 |

**Total**: 32H IA-pair ≈ 3.2H Wagner.

**Critérios de saída Fase 2**:
- [ ] Lead scoring `hot/warm/cold` calculado diário, exposto kanban
- [ ] Vendedor recebe alerta Whatsapp/in-app quando SLA vence sem resposta
- [ ] Dashboard pipeline mostra valor agregado per stage + forecast 30/60/90d
- [ ] API pública aceita lead de form externo com auth + rate-limit
- [ ] Counsel LGPD validou opt-in async via WhatsApp

---

## Fase 3 — Captura externa + auto-resposta (Sprint 3)

**Objetivo**: aquisição inbound (landing form embed) + auto-resposta inteligente.

**Duração**: 1 semana · ~14H IA-pair.

**Escopo (3 US, P2)**:

| US | Título | H |
|---|---|---|
| US-CRM-041 | Form embed JS oimpresso.com landing | 4 |
| US-CRM-073 (novo) | Webhook genérico CRM externo (Zapier/Make compatível) | 4 |
| US-CRM-074 (novo) | A/B test auto-resposta template (qual converte melhor?) | 6 |

**Total**: 14H IA-pair ≈ 1.4H Wagner.

**Critérios de saída Fase 3**:
- [ ] Form landing público funcionando, capturando lead via API
- [ ] Webhook bi-direcional Zapier app published
- [ ] A/B test rodando 2+ semanas, melhor template ganha

---

## Fase 4 — MWART (Migração Blade → Inertia/React)

**Objetivo**: modernizar UI seguindo Cockpit Pattern V2 + ADR 0104 (5 fases obrigatórias).

**Duração**: 3 semanas · ~34H IA-pair (3.4H Wagner).

**Pré-req obrigatório**: charter aprovado + visual comparison F3 estado-da-arte (skill `mwart-comparative V4` Tier A) — 10min síncrono Wagner por tela.

**Escopo (3 US, P1-P2)**:

| US | Título | H |
|---|---|---|
| US-CRM-060 | MWART Lead Kanban (substitui Blade jKanban) | 12 |
| US-CRM-061 | MWART Lead Show drawer cockpit V2 (ADR 0110) | 12 |
| US-CRM-062 | MWART CrmDashboard com Recharts | 10 |

**Total**: 34H IA-pair ≈ 3.4H Wagner.

**Critérios de saída Fase 4**:
- [ ] 3 telas Inertia/React em prod
- [ ] Charter `*.charter.md` aprovado per tela
- [ ] Visual comparison F3 estado-da-arte aprovado por SCREENSHOT (não tabela — ADR 0107)
- [ ] Pest paridade visual e funcional vs Blade legacy verde
- [ ] Smoke biz=1 verde
- [ ] Canary 7d biz=1 sem regressão antes cutover biz=4 (ROTA LIVRE 99% volume)

**Risco Fase 4**:
- R1 Tela kanban drag-drop = alta complexidade (ADR 0104 §3 lista 5 padrões bug MWART recorrentes). Mitigação: skill `mwart-quality` 9 pré-flight checks.

---

## Fase 5 — Hardening + extensões opcionais (sob demanda cliente)

**Objetivo**: refinamentos pós-prod + integrações nativas (ADR 0105 — só com cliente pagante).

**Duração**: variável (sob demanda).

**Escopo possível**:
- Proposta tracking abertura + assinatura digital (US-CRM-015 = 12H)
- Email 2-way Gmail/Outlook (~20H+) — só se 3+ clientes pedirem
- Integração nativa RD Station / Pipedrive (10-15H per vendor) — só se cliente pagar
- ML scoring (substitui regras) — só após 6m dados >500 leads/business
- Modules/Crm `/copiloto/admin/team` tab "CRM analytics" Jana-driven (visão Wagner cross-business)

**Critério de entrada Fase 5**: cliente externo paga upfront + métrica drift detecta necessidade (ADR 0105).

---

## Cronograma calendário sugerido

| Período | Fase | Status |
|---|---|---|
| Sem 1 (13-19/mai) | Fase 0 — aprovação + tasks MCP | pendente Wagner |
| Sem 2-3 (20/mai-02/jun) | Fase 1 — foundation + Whatsapp + FSM | bloqueado Fase 0 |
| Sem 4-5 (03-16/jun) | Fase 2 — inteligência + escala | bloqueado Fase 1 |
| Sem 6 (17-23/jun) | Fase 3 — captura externa | bloqueado Fase 2 |
| Sem 7-9 (24/jun-14/jul) | Fase 4 — MWART | bloqueado Fase 3 |
| Sem 10+ | Fase 5 — sob demanda | aguarda sinal cliente |

**Total estimate Fases 0-4**: ~13H síncronas Wagner ao longo de 9 semanas calendário (ADR 0106 fator 10x permite paralelizar tarefas codáveis com IA-pair).

---

## Métricas de sucesso (medir em prod biz=1 WR2)

| Métrica | Baseline atual | Meta Fase 1 | Meta Fase 4 |
|---|---:|---:|---:|
| Lead WhatsApp capturado auto | 0/dia | 5+/dia | 10+/dia |
| Tempo resposta primeiro contato | ? (sem medir) | <30min p75 | <15min p75 |
| Conversão Lead → Customer | ? (sem dado) | 15% | 25% |
| Lead "Perdido" sem motivo registrado | 100% | 0% | 0% |
| Forecast pipeline accuracy (vs realizado 30d) | N/A | ±30% | ±15% |
| LGPD opt-in registrado | 0% | 100% novos | 100% novos |
| Jana intencao acurácia | N/A | >75% | >90% |

---

## Anti-padrões / NÃO fazer

❌ NÃO criar `Modules/CrmPipeline` paralelo — viola decisão D1 + ADR 0121

❌ NÃO substituir `LeadController` legacy por reescrita full — MWART incremental tela-a-tela

❌ NÃO usar biz=4 (ROTA LIVRE) em smoke — sempre biz=1 (WR2) (feedback Wagner 2026-05, ADR 0101)

❌ NÃO commitar PII real cliente em PR/log/commit — `PiiRedactor` + skill `commit-discipline` Tier A

❌ NÃO marcar US-CRM-050 LGPD `done` sem counsel jurídico revisar — bloqueador formal

❌ NÃO ativar `JanaIntencaoAgent` no Hostinger — apenas CT 100 (ADR 0062 runtime split)

❌ NÃO criar Transaction sem `business_id` em jobs assíncronos — passar `$businessId` no constructor sempre (skill `multi-tenant-patterns` Tier A)

❌ NÃO confiar em `auth()->user()->business_id` em Listeners/Observers — pode rodar em contexto job sem session

---

_Gerado 2026-05-12 — opus-4.7. Aguarda Wagner aprovar ADR proposta D1-D5._

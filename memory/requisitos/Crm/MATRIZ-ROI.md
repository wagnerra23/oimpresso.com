---
module: Crm
type: matriz-roi
status: draft-proposal
generated_at: 2026-05-12
by: opus-4.7
parent_spec: SPEC.md
parent_adr_proposal: memory/decisions/proposals/drafts/crm-pre-venda-pipeline.md
---

# MATRIZ-ROI — CRM Pré-venda

> 20 features × ROI (custo construção × valor diferencial × pricing concorrente).
> Features **JÁ EXISTENTES** no `Modules/Crm/` legacy estão marcadas "JÁ TEMOS — não duplicar" (cita código).
> Estimates seguem ADR 0106 (fator 10x IA-pair + margem 2x). H = horas IA-pair Wagner+Claude.

## Concorrentes pesquisados (PME BR 2026)

| Concorrente | Foco | Pricing | WhatsApp | IA |
|---|---|---|---|---|
| **RD Station CRM** | PME BR líder (Marketing+Vendas integrado) | R$ 50-200/user/mês | Nativo (oficial Meta provider 2026) | "Rê" assistente IA voz+texto |
| **Pipedrive** | Global SMB | US$14-64/user/mês | Via Zapier/Make/API | Smart Docs, AI sales assistant |
| **HubSpot Free CRM** | Freemium SMB | $0-1.500/mês | Add-on pago Service Hub | ChatSpot AI |
| **Salesforce Starter** | Enterprise→SMB | US$25-330/user/mês | Via integrador | Einstein GPT (Enterprise) |
| **Agendor** | BR PME B2B consultivo | R$ 53-203/user/mês | Via Make/Zapier | Score conversa |
| **Bitrix24** | All-in-one freemium | $0-249/total/mês | Nativo | Sim |
| **Ploomes** | BR PME consultivo médio | sob consulta | Via integrador | Sim |
| **Bling CRM** | Vinculado ERP Bling | Bundle ERP | Limitado | Não |
| **Conta Azul CRM** | Vinculado ERP Conta Azul | Bundle ERP | Limitado | Não |
| **Moskit** | BR PME | R$ 50-150/user/mês | Nativo (mais maduro segundo Seasy 2026) | Sim |

**Insight chave**: integração WhatsApp nativa é must-have BR 2026 (Moskit + RD Station são líderes). Bling/Conta Azul fracos = oportunidade oimpresso. Bling/Conta Azul ganham por preço bundle (CRM "incluso" com ERP).

---

## Matriz 20 features

Legenda:
- 🟢 **GAP** — não existe no Modules/Crm; CONSTRUIR
- 🟡 **PARCIAL** — existe mas precisa estender/migrar
- ❌ **JÁ TEMOS** — não duplicar (cita código)
- **ROI**: A (alto, P0) / B (médio, P1) / C (baixo, P2/P3)

| # | Feature | Status oimpresso | Mercado tem | Custo (H) | Valor diferencial | ROI |
|---|---|---|---|---|---|---|
| 1 | **Lead capture form manual** | ❌ JÁ TEMOS — `LeadController@create/store` + Blade `contact.create` (linha 314) | Todos | 0 | — | — |
| 2 | **Kanban drag-drop pipeline** | 🟡 PARCIAL — `LeadController@index lead_view=kanban` Blade jKanban (linha 215-282); falta migrar MWART | Todos | 12 (MWART) | UX moderna React em vez de jQuery legado | B |
| 3 | **WhatsApp ↔ CRM auto (entrada lead)** | 🟢 GAP — `grep -r Crm Modules/Whatsapp` retorna 0 matches | RD Station "Rê", Moskit | 10 (US-CRM-010+012+014) | **Diferencial #1 BR** — captura sem cópia manual | **A** |
| 4 | **Jana IA classifica intenção (lead vs cliente vs spam)** | 🟢 GAP | RD Station "Rê" IA, HubSpot ChatSpot | 6 (US-CRM-011) | Diferencial IA conversacional PT-BR nativa (Bling/Conta Azul NÃO têm) | **A** |
| 5 | **Conversão Lead→Customer auto cria Transaction FSM `quote_draft`** | 🟢 GAP CRÍTICO — `LeadController::convertToCustomer` só flipa `contacts.type`, NÃO cria Transaction | N/A (concorrente CRM-only não tem ERP); Bling/Conta Azul integrados | 6 (US-CRM-020) | **Diferencial #2** — handoff zero-friction ADR 0143; concorrente CRM-only força double-entry | **A** |
| 6 | **Round-robin atribuição vendedor** | 🟢 GAP — só atribuição manual via `crm_lead_users` pivot | Todos | 4 (US-CRM-012 inclui) | Operacional pra time >2 vendedores | A |
| 7 | **SLA novo lead → alerta superior se vencido** | 🟢 GAP | RD Station, Pipedrive workflow | 4 (US-CRM-031) | Reduz lead frio por inatividade | A |
| 8 | **Motivo perda rastreado (taxonomia obrigatória)** | 🟢 GAP — `LeadController::destroy` deleta lead sem motivo | Pipedrive (Lost Reason obrigatório), Agendor | 4 (US-CRM-002 + US-CRM-022) | Responde "por que perdemos Gold?" (post-mortem real) | **A** |
| 9 | **Lead scoring automático (hot/warm/cold)** | 🟢 GAP | HubSpot, RD Station | 6 (US-CRM-030) | Foco do vendedor em quente; dashboard gerencial | B |
| 10 | **Reabordagem 90/180d (lead frio reativação)** | 🟢 GAP | RD Station workflow | 2 (US-CRM-023) | Pipeline não morre; "timing" perdido vira oportunidade | B |
| 11 | **Histórico interações 360º (timeline unificado WhatsApp+call+email+follow-up)** | 🟡 PARCIAL — dados existem fragmentados (`crm_call_logs`, `crm_schedules`, `crm_proposals`, `whatsapp_messages`), zero view unificada | Todos timeline unificado | 4 (US-CRM-003) | UX vendedor — vê 1 lead com tudo | A |
| 12 | **Follow-up agendado + lembrete cron** | ❌ JÁ TEMOS — `Schedule` model + `crm:send-follow-up-reminders` 15min cron (ADR TECH-0001) | Todos | 0 | — | — |
| 13 | **Follow-up notify via WhatsApp (além de SMS/Email)** | 🟡 PARCIAL — `Schedule::notify_via` aceita só sms/mail | RD Station nativo | 2 (extend `Schedule::followUpNotifyViaDropdown`) | Aproveitar canal já configurado Whatsapp | B |
| 14 | **Proposta comercial (template + envio)** | ❌ JÁ TEMOS — `Proposal` + `ProposalTemplate` + envio email CC/BCC | Todos | 0 | — | — |
| 15 | **Proposta tracking abertura + aceite digital** | 🟢 GAP | Pipedrive Smart Docs, DocuSign integração | 12 (não no escopo Sprint 1-3) | Visibilidade pós-envio | C |
| 16 | **Lead value estimado + forecast pipeline** | 🟢 GAP — `contacts` não tem `valor_estimado` | Pipedrive Deal Value, Agendor | 6 (US-CRM-001 + US-CRM-032) | Visão gerencial Wagner — "quanto temos em pipeline?" | A |
| 17 | **Dashboard pipeline kanban + KPIs** | 🟡 PARCIAL — `CrmDashboardController` + `ReportController` Blade legacy; falta KPIs ciclo médio + valor pipeline + forecast | Todos | 10 (US-CRM-032+062 MWART) | KPI gerencial moderno | B |
| 18 | **Tags livres lead** | 🟢 GAP — só `categories` rígido | Todos | 2 (US-CRM-004) | Segmentação ad-hoc vendedor | B |
| 19 | **API REST pública captura externa (form landing)** | 🟡 PARCIAL — `CrmMarketplaceController::importLeads` específico p/ marketplace B2B | Todos | 6 (US-CRM-040) | Embed landing oimpresso.com | B |
| 20 | **Form embed JS oimpresso.com landing** | 🟢 GAP — landing não captura lead hoje | RD Station, HubSpot | 4 (US-CRM-041) | Aquisição inbound | C |
| 21 | **Campanha massa (SMS/Email broadcast)** | ❌ JÁ TEMOS — `Campaign` + `CampaignController@sendNotification` | Todos | 0 | — | — |
| 22 | **Campanha massa WhatsApp** | 🟢 GAP (não cobrir — risco ban Meta + UX ruim) | RD Station via API oficial; Z-API custa | — | NÃO escopo (ADR 0096 Non-Goal: "marketing em massa Whatsapp") | — |
| 23 | **Portal cliente (contact login)** | ❌ JÁ TEMOS — `ContactLoginController` + `/contact/*` routes | Bling, Conta Azul | 0 | — | — |
| 24 | **Comissão por contact-person** | ❌ JÁ TEMOS — `CrmContactPersonCommission` | Ploomes | 0 | — | — |
| 25 | **Importação leads marketplace B2B externo** | ❌ JÁ TEMOS — `CrmMarketplaceController::importLeads` | RD Station via integração | 0 | — | — |
| 26 | **LGPD opt-in/out + direito esquecimento** | 🟢 GAP CRÍTICO — sem `lgpd_consent_at` | RD Station consentimento explícito | 6 (US-CRM-050) | **Bloqueador escala** — sem isso passivo legal | **A** |
| 27 | **MWART Lead Kanban (Blade → Inertia/React)** | 🟡 PARCIAL — Blade jKanban legacy | N/A (concorrente nasce React) | 12 (US-CRM-060) | Modernização UI; pré-req cockpit V2 | B |
| 28 | **MWART CrmDashboard (Blade → Recharts)** | 🟡 PARCIAL | Todos React | 10 (US-CRM-062) | Modernização | C |
| 29 | **Integração externa CRM (Zapier/Make webhook)** | 🟢 GAP | Todos têm Zapier app | 4 (futuro, sem US) | Cliente externo conecta o que quer | C |
| 30 | **Email integration nativa (Gmail/Outlook 2-way)** | 🟢 GAP | Pipedrive, HubSpot | 20+ (não escopo) | UX vendedor — email no CRM | C |

---

## Top features ROI A (P0 — Sprint 1)

1. **WhatsApp ↔ CRM auto** (#3) — diferencial #1 BR; 10H
2. **Jana IA classifica intenção** (#4) — diferencial IA PT-BR; 6H
3. **Conversão Lead → Transaction FSM** (#5) — fecha loop ADR 0143; 6H
4. **Motivo perda rastreado** (#8) — post-mortem Gold; 4H
5. **Round-robin + SLA novo lead** (#6 + #7) — 8H
6. **Histórico 360º timeline** (#11) — UX vendedor; 4H
7. **Lead value estimado + forecast** (#16) — KPI gerencial; 6H
8. **LGPD compliance** (#26) — bloqueador escala; 6H

**Total Sprint 1 P0**: ~50H IA-pair (ADR 0106) ≈ 5h síncrono Wagner.

## Features ROI B (P1 — Sprint 2)

- Lead scoring automático (#9) — 6H
- Reabordagem cold (#10) — 2H
- Tags livres (#18) — 2H
- API REST pública (#19) — 6H
- Follow-up Whatsapp notify (#13) — 2H
- Dashboard KPIs novos (#17) — 4H
- MWART kanban (#27) — 12H

**Total Sprint 2 P1**: ~34H.

## Features ROI C (P2/P3 — Sprint 3+)

- Form embed landing (#20) — 4H
- MWART Dashboard (#28) — 10H
- MWART Lead Show drawer (US-CRM-061) — 12H
- Webhook Zapier (#29) — 4H
- Proposta tracking abertura (#15) — 12H
- Email 2-way (#30) — não escopo curto prazo

---

## O que JÁ TEMOS (não duplicar)

Lista consolidada das 7 features prontas — economia ~150H vs concorrente CRM-only que tem que tudo construir:

1. **Lead CRUD** (`LeadController` + 9 actions)
2. **Kanban por life stage** (`LeadController@index lead_view=kanban`)
3. **Follow-up agendado + lembrete cron** (`Schedule` + `crm:send-follow-up-reminders`)
4. **Proposta + template + envio CC/BCC** (`Proposal` + `ProposalTemplate`)
5. **Campanha massa** (`Campaign` + `CampaignController@sendNotification`)
6. **Portal cliente** (`ContactLoginController` + `OrderRequestController`)
7. **Comissão contact-person** (`CrmContactPersonCommission`)

Concorrente CRM-only (RD Station, Pipedrive, Agendor, Moskit, Ploomes) precisa construir TODAS essas 7 + integrar com ERP externo. oimpresso ganha em integração (item #5 conversão FSM) e ERP-bundle (Bling/Conta Azul vencem em preço mas perdem em IA + WhatsApp custom + customização vertical).

---

## Posicionamento sugerido vs mercado

| Eixo | oimpresso CRM proposto | Líder mercado | Diferencial |
|---|---|---|---|
| **Preço** | Bundle oimpresso (sem add-on user) | RD Station R$ 50-200/user/mês | Bundle wins PME early-stage |
| **WhatsApp integração** | Nativa (Baileys custom CT 100 + Z-API + Meta Cloud) | RD Station nativo, Moskit nativo | Paridade, mas oimpresso multi-driver |
| **IA conversacional** | Jana IA classifica + auto-resposta + lembrete vendedor | RD "Rê" assistente | Paridade BR-PT |
| **ERP integration** | **Nativo** (Sells FSM `quote_draft` direto) | Bling/Conta Azul tem (mas weak CRM) | **Diferencial #1** oimpresso |
| **Multi-tenant Tier 0** | ADR 0093 IRREVOGÁVEL | SaaS multi-tenant todos | Paridade |
| **Customização vertical** | Modules/Vestuario, ComVisual, OficinaAuto | RD Station horizontal | Diferencial setorial (gráficas/lojas) |
| **Time to value** | 0 (já é parte do ERP) | RD Station 1-3 semanas onboarding | Diferencial bundle |

---

_Gerado 2026-05-12 — opus-4.7. Aguarda Wagner aprovar ADR proposta D1-D5._

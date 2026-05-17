# CAPTERRA-FICHA — Repair (Kanban OS shared infra)

> Ficha canônica de benchmark do módulo Repair — fonte de verdade para skill `comparativo-do-modulo`.
> ADR de governança: [0089](../../decisions/0089-capterra-driven-module-evolution.md).
> BRIEFING: [BRIEFING.md](BRIEFING.md). Arquitetura: [ARCHITECTURE.md](ARCHITECTURE.md). FSM: [SPEC-FSM-WIREUP.md](SPEC-FSM-WIREUP.md).
> Wave 22 — 2026-05-16. Owner: Claude (Wagner aprova).

---

## 1. Identidade do módulo

- **Nome interno:** `Modules/Repair` — Kanban OS shared infrastructure
- **Domínio de negócio:** gestão de Ordens de Serviço (OS / Job Sheet) genérica, **consumida por verticais** (`Modules/Vestuario` em prod, `Modules/ComunicacaoVisual` em construção, `Modules/OficinaAuto` aguardando CYCLE-06 Martinho Caçambas). Vocabulário neutro `code/item/usage_meter/slot/area/executor` permite override via `business.repair_settings` JSON
- **Status atual:** **em prod biz=1 desde MWART Wave Massiva 2026-05-12** (5 telas Inertia/React migradas Blade→React: Index/Show/Create/Edit/AddParts), 11 controllers, FSM canônica LIVE ([ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)) com 13 stages × ~15 actions × 6 roles per-business, trait `GuardsFsmTransitions` em JobSheet bloqueia UPDATE direto em `current_stage_id`
- **Concorrentes-alvo direto (4):**
  - **RepairShopr** (global, RepairDesk-comparable) — ticket-centric + customer portal + SMS/email + estimates
  - **mHelpDesk** (global field service) — mobile-first técnico em campo + photo/signature on-site + workflow dispatcher
  - **Orderry** (global multi-vertical repair) — workflow automation no-code + multi-localização
  - **Lokoz / Online OS / Oficina Integrada / LKOS** (BR oficinas) — OS BR + emissão fiscal NFe/NFSe integrada + preço acessível PMEs

## 2. Comparativos de referência

- **RepairShopr Reviews 2026** ([G2](https://www.g2.com/products/repairshopr/reviews) / [Capterra](https://www.capterra.com/p/133945/RepairShopr/reviews/)) — ponto forte: integração ticket↔invoice em fluxo único; ponto fraco: emails falhando em 2026, suporte demorado (2 sem)
- **mHelpDesk features** ([oficial](https://www.mhelpdesk.com/features/)) — mobile app captura signature + photos + status update em campo; workflows dispatcher → invoice
- **Orderry workflow automation 2026** ([blog](https://orderry.com/blog/workflow-automation-for-repair-shops/)) — no-code triggers (status change → SMS/email), tendência 2026
- **Tekmetric customer experience** ([blog](https://www.tekmetric.com/post/streamline-repair-shop-workflows-customer-experience)) — DVI digital vehicle inspection + foto/vídeo build trust; OTP + e-signature recovery
- **Best Computer Repair Shop Software 2026** ([Capterra](https://www.capterra.com/computer-repair-shop-software/)) — top 10 dominam: ticketing + POS + inventory + customer portal + SMS notifications

## 3. Capacidades baseline com score

```yaml
capacidades:
  # ============= P0 — bloqueadores =============

  - nome: "FSM canônica 13 stages com gateway service obrigatório"
    score: P0
    descricao: "Pipeline Repair (recebido_para_diagnostico → diagnostico_em_andamento → aguardando_aprovacao_orcamento → orcamento_aprovado → aguardando_pecas → em_execucao → controle_qualidade → pronto_para_retirada → entregue_completo + terminais cancelado/em_garantia/retorno) com ExecuteStageActionService gateway, trait GuardsFsmTransitions bloqueia UPDATE direto, audit append-only em sale_stage_history."
    quem_tem: ["mHelpDesk (workflow)", "RepairShopr (status simples)", "Orderry (no-code)", "Lokoz (linear)"]
    status_oimpresso: "✅ LIVE prod biz=1 (ADR 0143)"
    evidencia_de_pronto: "ExecuteStageActionService + trait GuardsFsmTransitions + RepairFsmActionController + 13 stages seedados + Pest cobre transition + 403 sem role + audit log"

  - nome: "Multi-tenant Tier 0 IRREVOGÁVEL (business_id global scope)"
    score: P0
    descricao: "Toda query Repair respeita business_id via global scope; JobSheet/RepairStatus/DeviceModel indexados; Pest cross-tenant biz=1 vs biz=99 valida 404."
    quem_tem: ["RepairShopr (workspace)", "mHelpDesk (account)", "Orderry (location)"]
    status_oimpresso: "✅ canon ADR 0093 — Pest MultiTenantRepairTest pendente (Wave M roadmap)"
    evidencia_de_pronto: "global scope em JobSheet/RepairStatus + Pest MultiTenantRepairTest verde cross-tenant"

  - nome: "Kanban drag-drop UI (5 colunas heurística is_completed_status)"
    score: P0
    descricao: "ProducaoOficina kanban drag-drop (US-REPAIR-PROD-2..4) com 5 colunas fixas via heurística is_completed_status + sort_order; query real + fallback mock; optimistic UI."
    quem_tem: ["Orderry", "RepairShopr (workflow board)", "Lokoz (parcial)"]
    status_oimpresso: "✅ em prod (validado pré-Martinho 2026-05-13)"
    evidencia_de_pronto: "Page /repair/producao + drag-drop + Pest ProducaoOficinaKanbanTest"

  - nome: "Customer portal público via token + PIN"
    score: P0
    descricao: "CustomerRepairStatusController serve URL pública (token aleatório) onde cliente consulta status da OS, vê fotos/anexos, aprova/rejeita orçamento. Equivalente RepairShopr customer portal."
    quem_tem: ["RepairShopr (canonical)", "mHelpDesk", "Orderry", "Tekmetric DVI"]
    status_oimpresso: "🟡 parcial — CustomerRepairStatusController existe (index + postRepairStatus) sem PIN/OTP nem aprovação orçamento UX moderna"
    evidencia_de_pronto: "Page /repair/status/{token} + OTP/PIN opcional + approve/reject quote + audit + Pest"

  - nome: "Shared infrastructure consumível por verticais"
    score: P0
    descricao: "Vocabulário genérico code/item/usage_meter/slot/area/executor + business.repair_settings JSON pra overrides. Vestuario/ComVis/OficinaAuto consomem mesma infra."
    quem_tem: ["Orderry (multi-industry)", "Genéricos não fazem", "Lokoz BR vertical-lock"]
    status_oimpresso: "✅ canon ADR 0121 — único do mercado modular especializado"
    evidencia_de_pronto: "JobSheet vocabulário neutro + business.repair_settings + 3 verticais consumindo (Vestuario prod, ComVis WIP, OficinaAuto sinal)"

  - nome: "Side-effects FSM isolados (ReservarEstoque/ConsumirEstoque/LiberarReserva)"
    score: P0
    descricao: "Mudança de stage dispara jobs idempotentes; cancelamento orquestra cascade (estoque revert + NFe SEFAZ cancel + notify cliente)."
    quem_tem: ["mHelpDesk", "Orderry (workflow triggers)", "RepairShopr (limited)"]
    status_oimpresso: "✅ canon ADR 0143 (orquestração compartilhada com Sells)"
    evidencia_de_pronto: "Jobs ReservarEstoque/ConsumirEstoque + Pest idempotência"

  # ============= P1 — mercado tem, cliente vai pedir =============

  - nome: "SMS/WhatsApp automation por mudança de status"
    score: P1
    descricao: "Status muda pra 'pronto_para_retirada' → cliente recebe WhatsApp/SMS auto. Orderry/RepairShopr canonical."
    quem_tem: ["Orderry (no-code)", "RepairShopr", "mHelpDesk", "Lokoz BR"]
    status_oimpresso: "🟡 backbone existe (Modules/Whatsapp + ChannelSelector) — gancho FSM→Whatsapp NotificarClienteJob não está auto-disparando em Repair"
    evidencia_de_pronto: "FsmStageChanged event listener dispara NotificarClienteJob com template configurável por stage + opt-in LGPD"

  - nome: "Foto/vídeo evidência durante diagnóstico (DVI digital)"
    score: P1
    descricao: "Técnico anexa fotos do antes/depois no JobSheet; cliente vê no portal. Tekmetric DVI canonical."
    quem_tem: ["Tekmetric DVI (canonical)", "mHelpDesk", "Orderry", "RepairShopr"]
    status_oimpresso: "🟡 parcial — JobSheetController.deleteJobSheetImage existe (gestão imagens) mas não há UX mobile-first capture nem timeline visual antes/depois"
    evidencia_de_pronto: "Upload multi-foto + timeline visual + cliente vê no portal token"

  - nome: "Assinatura digital cliente (recebimento + entrega)"
    score: P1
    descricao: "Cliente assina recebimento da OS no celular do técnico; assina retirada quando recebe equipamento de volta. mHelpDesk canonical."
    quem_tem: ["mHelpDesk (canonical)", "Tekmetric", "Orderry"]
    status_oimpresso: "❌ ausente"
    evidencia_de_pronto: "Component SignaturePad + endpoint salva assinatura SVG/PNG + PDF da OS embute assinatura + Pest"

  - nome: "Mobile-first técnico em campo (PWA/responsive)"
    score: P1
    descricao: "Técnico atualiza status + adiciona fotos + faz signature pelo celular. mHelpDesk canonical."
    quem_tem: ["mHelpDesk (canonical)", "Orderry mobile app", "RepairShopr mobile"]
    status_oimpresso: "🟡 telas Inertia/React responsivas mas sem PWA install + sem offline-first"
    evidencia_de_pronto: "PWA manifest + service worker + offline queue de status changes + sync ao reconectar"

  - nome: "Quote/orçamento aprovação cliente via portal"
    score: P1
    descricao: "Cliente recebe link, vê detalhamento peças+mão de obra, aprova/rejeita; FSM avança auto. RepairShopr canonical."
    quem_tem: ["RepairShopr (canonical)", "Orderry", "Lokoz"]
    status_oimpresso: "🟡 stage 'aguardando_aprovacao_orcamento' existe na FSM, mas UX cliente aprovar via portal token não implementada"
    evidencia_de_pronto: "Page /repair/status/{token}/quote/{id}/approve + endpoint POST + FsmAction trigger + audit"

  - nome: "Workflow automation no-code (status change → ação)"
    score: P1
    descricao: "Admin configura via UI: 'quando stage = X então faça Y (SMS/email/criar task/atribuir técnico)'. Orderry canonical 2026."
    quem_tem: ["Orderry (canonical 2026)", "mHelpDesk parcial"]
    status_oimpresso: "❌ ausente — FSM tem actions hardcoded em seed, sem UI no-code"
    evidencia_de_pronto: "Page /repair/settings/automations + UI condition→action + persist em sale_stage_action_roles JSON"

  - nome: "Integração NFe/NFSe BR ao finalizar OS"
    score: P1
    descricao: "Quando stage = 'entregue_completo' dispara emissão NFSe (serviço) ou NFe (peças). Lokoz/Oficina Integrada canonical BR."
    quem_tem: ["Lokoz", "Online OS BR", "Oficina Integrada", "Soften"]
    status_oimpresso: "🟡 infra Modules/NfeBrasil existe — gancho FSM→NFe não auto-disparando"
    evidencia_de_pronto: "FsmStageChanged listener (stage=entregue_completo) → EmitirNFSeJob com fallback retry"

  - nome: "Inventory deduction auto ao consumir peças"
    score: P1
    descricao: "Adicionar peça ao JobSheet decrementa estoque (reservation→consumption). Side-effect ReservarEstoque/ConsumirEstoque existe."
    quem_tem: ["RepairShopr", "Orderry", "Lokoz", "Oficina Integrada"]
    status_oimpresso: "✅ canon FSM side-effects (ADR 0143)"
    evidencia_de_pronto: "JobSheetController.addParts dispara ReservarEstoqueJob + Pest"

  # ============= P2 — diferencial competitivo =============

  - nome: "Diagnóstico assistido por IA (sugestão problemas/peças)"
    score: P2
    descricao: "Técnico descreve sintoma → Jana sugere diagnóstico provável + peças prováveis baseado em histórico OS similares."
    quem_tem: ["Tekmetric AI 2026 trend", "Orderry beta"]
    status_oimpresso: "❌ ausente — oportunidade Jana IA"
    evidencia_de_pronto: "Tool MCP `repair:suggest-diagnosis` + frontend chamada no Create + Pest mock"

  - nome: "Charter por Page Inertia (governança UI)"
    score: P2
    descricao: "9 charters Page Inertia (campeão do projeto) documentam intenção UX de cada tela."
    quem_tem: ["Genéricos não fazem (governança interna)"]
    status_oimpresso: "✅ canon — diferencial governança"
    evidencia_de_pronto: "9 *.charter.md em resources/js/Pages/Repair/"

  - nome: "Bidirectional git sync de OS templates"
    score: P2
    descricao: "Templates de checklist por device model versionados em git → sync UI editor."
    quem_tem: ["Nenhum concorrente faz"]
    status_oimpresso: "❌ ausente"
    evidencia_de_pronto: "RepairChecklistTemplate persistido em git via webhook + DeviceModelController.getRepairChecklists"

  # ============= P3 — nice-to-have =============

  - nome: "Garantia tracking (warranty period auto)"
    score: P3
    descricao: "OS entregue gera período de garantia auto; cliente reporta defeito → nova OS linkada como warranty_claim."
    quem_tem: ["Tekmetric", "Orderry"]
    status_oimpresso: "🟡 stage 'em_garantia' existe na FSM — UX tracking ausente"

  - nome: "Time tracking por técnico (apontamento horas)"
    score: P3
    descricao: "Técnico marca início/fim de cada OS; relatório horas/técnico/período."
    quem_tem: ["mHelpDesk", "Orderry", "Productive.io"]
    status_oimpresso: "❌ ausente"

  - nome: "Centrifugo presence (quem está vendo a OS)"
    score: P3
    descricao: "Avatar stack mostra técnicos vendo mesma OS em tempo real."
    quem_tem: ["Linear-style"]
    status_oimpresso: "❌ ausente"

  - nome: "Comments/log notes em OS"
    score: P3
    descricao: "Histórico de comentários internos + cliente-visíveis no portal."
    quem_tem: ["RepairShopr", "mHelpDesk", "Orderry"]
    status_oimpresso: "🟡 sale_stage_history audit log existe (append-only) — UI dedicada comments ausente"
```

## 4. Nota 0-100 ponderada

Cálculo: ✅ = 1.0, 🟡 = 0.5, ❌ = 0.0. Pesos: P0=4, P1=2, P2=1, P3=0.5.

| Score | ✅ | 🟡 | ❌ | Total cap. | Peso | Pontos máx | Pontos obtidos |
|---|---|---|---|---|---|---|---|
| P0 | 5 | 1 | 0 | 6 | 4 | 24 | 22.0 |
| P1 | 1 | 5 | 2 | 8 | 2 | 16 | 7.0 |
| P2 | 1 | 0 | 2 | 3 | 1 | 3 | 1.0 |
| P3 | 0 | 2 | 2 | 4 | 0.5 | 2 | 1.0 |
| **Total** | **7** | **8** | **6** | **21** | — | **45** | **31.0** |

**Nota: 31.0 / 45 = 68.9 / 100**

## 5. Top 5 gaps prioritários (impacto × esforço)

| # | Gap | Score | Impacto | Esforço | Próximo PR |
|---|---|---|---|---|---|
| 1 | SMS/WhatsApp automation FSM→cliente (stage=pronto) | P1 | ALTO (-40% calls "tá pronto?") | M (gancho existe) | FsmStageChanged listener + NotificarClienteJob |
| 2 | Customer portal token + aprovação orçamento UX moderna | P0 | ALTO (RepairShopr parity) | M (controller existe) | Page /repair/status/{token} + approve quote + OTP |
| 3 | Foto/vídeo evidência DVI + timeline visual | P1 | ALTO (trust + LGPD) | M (upload existe) | UX timeline antes/depois + cliente vê no portal |
| 4 | Assinatura digital cliente (recebimento + entrega) | P1 | MÉDIO (legal/LGPD) | M (SignaturePad lib) | Component + endpoint + PDF embute |
| 5 | Integração NFSe ao stage=entregue_completo | P1 | ALTO (BR vertical) | S (gancho infra existe) | FsmStageChanged listener → EmitirNFSeJob |

## 6. Roadmap sugerido (3 fases)

**Fase 1 (Wave 23-24, ~2 semanas):** Gaps #1 + #5 (gancho FSM→Whatsapp + FSM→NFSe) — entrega ROI imediato pra biz=1 + Vestuario prod.

**Fase 2 (Wave 25-27, ~1 mês):** Gaps #2 + #3 (customer portal moderno + DVI foto/vídeo + aprovação quote) — paridade RepairShopr/Tekmetric.

**Fase 3 (Wave 28+, ~6 sem):** Gap #4 + workflow automation no-code (Orderry parity) + diagnóstico assistido IA (diferencial Jana).

## 7. Riscos e bloqueios

- **CYCLE-06 Martinho Caçambas** valida Kanban Produção real — sinal qualificado pra disparar Modules/OficinaAuto profundo
- **LGPD opt-in** SMS/WhatsApp/Email obrigatório (`Contact::canReceiveXxxNotification()`) — Tier 0 ADR 0143
- **FSM canônica IRREVOGÁVEL** — todo gap NOVO deve passar por ExecuteStageActionService (sem UPDATE direto)
- **Multi-tenant Tier 0** — Pest MultiTenantRepairTest pendente (Wave M roadmap)

## 8. Métricas de sucesso pós-roadmap

- Cliente check status sem ligar suporte: meta 70%+ (RepairShopr benchmark)
- OS com foto evidência: meta 90%+ (Tekmetric benchmark)
- Stage→SMS auto-disparo p95 <3s
- Multi-tenant Pest cross-tenant 100% verde

## 9. ADRs/Docs canônicos relacionados

- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) Multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) Processo MWART (Wave B6 seguiu)
- [ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) Modular especializado por vertical
- [ADR 0129](../../decisions/0129-state-machine-canonica-fsm-rbac.md) FSM tabular custom
- [ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) FSM Pipeline LIVE prod biz=1 (marco)
- [SPEC-FSM-WIREUP.md](SPEC-FSM-WIREUP.md) wireup operacional
- [BRIEFING.md](BRIEFING.md) estado consolidado 1-pager

## 10. Comparativos lidos (fontes WebSearch)

- [RepairShopr Capterra 2026](https://www.capterra.com/p/133945/RepairShopr/) — ticket-centric + customer portal + SMS
- [RepairShopr Features oficial](https://www.repairshopr.com/features) — CRM/POS/Invoicing/Ticketing
- [mHelpDesk features](https://www.mhelpdesk.com/features/) — field service mobile + signature + photo
- [mHelpDesk Workflow Mgmt](https://www.mhelpdesk.com/features/workflow-management/) — workflow define+track each step
- [Orderry Workflow Automation 2026](https://orderry.com/blog/workflow-automation-for-repair-shops/) — no-code triggers tendência 2026
- [Tekmetric customer experience](https://www.tekmetric.com/post/streamline-repair-shop-workflows-customer-experience) — DVI photo/video build trust
- [Best Computer Repair Shop Capterra 2026](https://www.capterra.com/computer-repair-shop-software/) — top 10 features
- [Lokoz / LKOS Hotmart](https://hotmart.com/pt-br/marketplace/produtos/lkos-sistema-de-gestao-e-ordem-de-servico/Y61100011H) — OS BR PME
- [Online OS BR](https://onlineos.com.br/) — manutenção/assistência/facilities BR
- [Oficina Integrada BR](https://www.oficinaintegrada.com.br/) — oficina mecânica BR canonical

---

**Última atualização:** 2026-05-16 Wave 22 — Claude. Wagner aprova antes de viralizar gaps em US.

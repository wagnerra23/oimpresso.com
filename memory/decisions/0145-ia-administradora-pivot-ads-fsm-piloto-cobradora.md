---
slug: 0145-ia-administradora-pivot-ads-fsm-piloto-cobradora
number: 145
title: "IA Administradora do oimpresso — pivot ADS↔FSM + piloto Cobradora ROTA LIVRE"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: 2026-05-15
module: ADS
tags: [visao, ia-administradora, ads, fsm, cobradora, lgpd-art-20, anpd-nt-12-2025, multi-tenant, hitl, audit-card, rota-livre, piloto, cycle-07]
supersedes: []
supersedes_partially: []
amends: [0094]
superseded_by: []
related: [0035, 0048, 0053, 0061, 0093, 0094, 0101, 0104, 0106, 0129, 0131, 0140, 0143]
pii: false
review_triggers:
  - "Piloto Cobradora rodou 30d em biz=4 — revisar KPIs e decidir GA pra outros biz"
  - "ANPD publicar Nota Técnica nova alterando Art. 20 LGPD — revisar Audit Card UI"
  - "Asaas mudar API refund ou cobrança automática — revisar CobradoraAgent tools"
  - "Concorrente BR (Bling/Tiny/Omie/ContaAzul) lançar agente nativo no ERP — revisar diferencial competitivo"
  - "Anthropic deprecar laravel/ai SDK OU lançar feature que invalide LaravelAiSdkDriver — revisar stack (preserva ADR 0048)"
---

# ADR 0145 — IA Administradora do oimpresso — pivot ADS↔FSM + piloto Cobradora ROTA LIVRE

## Status

**Aceito 2026-05-15.** Wagner aprovou em sessão do dia, após estado-da-arte 2026 entregue por agent `estado-da-arte` ([memory/sessions/2026-05-15-arte-ia-administradora-empresa.md](../sessions/2026-05-15-arte-ia-administradora-empresa.md)).

## Contexto

### Gatilho

Wagner perguntou em 2026-05-15: *"como criar uma ia para ser administrador da empresa?"* — exploração estratégica sobre criar agente(s) IA que **decidam e executem** ações administrativas no oimpresso (não só atendimento conversacional).

### Estado-da-arte 2026 — síntese (doc completo no session log)

Levantamento de 7 players (Salesforce Agentforce Operations, Microsoft Copilot Studio, SAP Joule, HubSpot Breeze, Cognition Devin, Lindy.ai, Daylit/Stuut/HighRadius categoria AR) cruzado com 15 dimensões resulta em **nota global oimpresso 62/100** (líderes 2026 ~80-85).

Insight central: **oimpresso já tem 70% da espinha dorsal que líderes 2026 cobram caro**:

| Camada | Líder 2026 (ref) | Oimpresso hoje |
|---|---|---|
| Memória persistente | SAP Knowledge Graph + Anthropic Dreaming | Modules/Copiloto + MCP server (ADR 0053) |
| Decisão escalada | Agentforce Atlas Reasoning | Modules/ADS — Policy 4 outcomes + HITL 4 níveis |
| Executor auditável | Agentforce Operations / SAP Joule | FSM Pipeline LIVE biz=1 (ADR 0143) |
| Multi-tenant | Geralmente "feature de Enterprise plan" | Tier 0 IRREVOGÁVEL (ADR 0093) — supera líderes |

**Gap real ≠ arquitetura. É integração end-to-end de 1 caso administrativo concreto.**

ADS hoje roteia só atendimento WhatsApp — **nunca foi conectado ao FSM**. Líderes ganham porque o Router decide E o executor age **no mesmo trilho auditável**. Oimpresso tem os dois trilhos prontos, desconectados.

### Janela competitiva

Busca não retornou nada concreto de agente autônomo nativo em Bling/Tiny/Omie/ContaAzul em 2026 — aparecem como "plataformas com APIs viram alvo de orquestração via n8n+IA externa". **Chegar primeiro com agente IA NATIVO no ERP BR-PME é diferencial defensável** — não bolted-on via Zapier.

### Compliance virou regulação real (2026)

ANPD Nota Técnica 12/2025 (publicada abril 2026) regulamenta LGPD Art. 20: toda decisão exclusivamente automatizada que afete interesse do titular exige (a) informar "decidido por IA" e (b) canal de revisão humana. Multa até 2% receita ou R$ [redacted Tier 0]M. Backend oimpresso (`mcp_dual_brain_decisions` audit) está pronto; **UI de cliente final visível não existe**.

### MIT 2026

Estudo MIT 2026 mostrou modelos generativos são **34% mais confiantes quando alucinam**. Confidence threshold sozinho não basta — policy determinística + audit + HITL pra alto blast radius (que ADS já faz; manter rigor).

## Decisão

### O quê

**Pivotar Modules/ADS de "roteador de atendimento" pra "roteador de ações administrativas auditáveis", conectando-o ao FSM Pipeline (ADR 0143) via service-bridge único, e validar o pivot com 1 piloto end-to-end (Cobradora ROTA LIVRE) ANTES de generalizar.**

A "IA Administradora do oimpresso" passa a ser uma **categoria de agentes especializados** (CobradoraAgent, AuditoraDiariaAgent, etc) que rodam dentro de Modules/Copiloto, são roteados por Modules/ADS, e executam ações reais no negócio via FSM Pipeline. Não há framework novo. Não há reescrita de Jana, ADS ou FSM — só conexão + 1 agente novo.

### Princípios duros desta visão (extensão Constituição v2)

1. **Bridge unicamente via FSM.** Toda ação administrativa de agente IA passa por `ExecuteStageActionService` (ADR 0143). Não criar atalhos. Não fazer `Model::update()` direto. Não bypassar `GuardsFsmTransitions`. Se uma ação não cabe em stage/action FSM existente, abrir ADR pra modelar stage/action — não improvisar.
2. **Audit Card visível ao cliente final.** Toda decisão automatizada que afete cliente final tem (a) rodapé "decisão automatizada — revisar humanamente em X" na mensagem enviada, (b) URL `/copiloto/decisoes/{id}/revisao` funcional, (c) entrada em `mcp_dual_brain_decisions` com `client_visible: true`. LGPD Art. 20 / ANPD NT 12/2025 não é opt-in — é Tier 0.
3. **Multi-tenant intransigente.** Agente do biz=4 só lê/age em dados biz=4. Pest cross-tenant biz=1 vs biz=99 obrigatório pra CADA agente novo (ADR 0093 + 0101).
4. **HITL adjustável por confidence + blast radius.** Cobrança WhatsApp <R$ [redacted Tier 0] → L0 (auto). >R$ [redacted Tier 0] OU cliente VIP OU primeira cobrança do mês → L2 (Wagner aprova 1-click). Bloqueio cliente → L3 (Wagner + Eliana aprovam). Limiares per-agent declarados em config + auditáveis.
5. **Reusar stack canônica.** `laravel/ai` + `LaravelAiSdkDriver` (ADR 0035) — Vizra/CrewAI/LangGraph rejeitados (ADR 0048 preservada). Tools internas reusam `ToolRegistry` ADS existente. Memória via Modules/Copiloto (ADR 0053). Sem novas dependências até CYCLE-07 fechar.
6. **Aborto explícito.** Se piloto Cobradora não bater critérios de sucesso após 30d em biz=4 (KPIs §Sucesso/§Falha abaixo), pausar generalização, escrever ADR de retrospectiva, decidir se evolui ou descarta visão. Não inflar.
7. **Append-only audit.** Toda decisão IA + toda execução FSM + todo Audit Card click registrados append-only. Bloqueio cliente, refund, cancelamento NFe — eventos críticos com payload completo em `sale_stage_history` + `mcp_dual_brain_decisions`.
8. **PT-BR em tudo que vai pro cliente final.** Mensagens cobrança, Audit Card UI, e-mail de revisão — todos PT-BR brasileiro neutro, sem regionalismo SP/SC, sem inglês.

### Escopo do piloto (CYCLE-07 ou primeiro cycle hábil pós-aprovação)

**Caso de uso único: Cobradora ROTA LIVRE.**

Agente "CobradoraAgent" lê faturas vencidas (`recurring_invoices` + `transactions` com `due_date < today`) do biz=4, decide:
- **canal** (WhatsApp via Baileys CT 100 OU email via SMTP Hostinger OU "esperar humano"),
- **tom** (cordial-1 / firme-2 / formal-3 — baseado em quantos contatos prévios + valor + relacionamento),
- **timing** (manhã útil 09-11h / tarde 14-16h / nunca fim-semana),
- **escalation** (sem resposta após N dias → próximo canal/tom; sem resposta após M dias → L2 Wagner aprova bloqueio Asaas).

Cada decisão dispara FSM action `cobrar_fatura` (a criar no seed Sells process) ou stage transition apropriada via `ExecuteStageActionService`. Audit Card visível em todas mensagens.

**Escopo explicitamente FORA do piloto:**

- ❌ Cobrança autônoma fora ROTA LIVRE (biz=1 fica em modo observação/dry-run; outros biz só via opt-in explícito)
- ❌ Aprovação de despesas, sugestão de preço, decisão de compra, triagem CRM, folha/ponto, jurídico (todos backlog ADR feature-wish)
- ❌ Voice/multimodal (gap #7 backlog)
- ❌ Knowledge graph estilo SAP (gap #9 backlog)
- ❌ Dreaming-equivalente (gap #4 backlog — Pattern Learning ARQ-0007 ativado real fica out-of-scope)
- ❌ DPO formal / counsel LGPD externo (gap #5 — Eliana sinaliza quando decidir estudar; piloto roda com Tier 0 atual)

## Arquitetura (camadas)

```
┌─────────────────────────────────────────────────────────────────┐
│  Larissa (ROTA LIVRE biz=4) — Wagner (HITL L2/L3 aprovador)     │
└─────────────────────────────────────────────────────────────────┘
                                ↑
                          Audit Card UI
                  /copiloto/decisoes/{id}/revisao
                                ↑
┌─────────────────────────────────────────────────────────────────┐
│  Modules/ADS — Router + PolicyEngine + HITL 4 níveis            │
│  (existente — só adicionar action_type "cobranca_fatura")       │
└─────────────────────────────────────────────────────────────────┘
                                ↓
┌─────────────────────────────────────────────────────────────────┐
│  Modules/ADS/Services/FsmActionBridge (NOVO — gap #1)           │
│  Recebe RoutingDecision → traduz pra ExecuteStageActionService  │
└─────────────────────────────────────────────────────────────────┘
                                ↓
┌─────────────────────────────────────────────────────────────────┐
│  app/Domain/Fsm/Services/ExecuteStageActionService (ADR 0143)   │
│  Action key: cobrar_fatura · Side-effects: EnviarCobrancaJob   │
└─────────────────────────────────────────────────────────────────┘
                                ↓
┌─────────────────────────────────────────────────────────────────┐
│  Modules/Copiloto/Ai/Agents/CobradoraAgent (NOVO — gap #2)      │
│  Tools: ListarFaturasVencidas / EscolherCanal /                 │
│         RedigirMensagem / AgendarEscalation / RegistrarTentativa│
└─────────────────────────────────────────────────────────────────┘
                                ↓
┌─────────────────────────────────────────────────────────────────┐
│  Whatsapp daemon CT 100 / SMTP Hostinger / Asaas API            │
└─────────────────────────────────────────────────────────────────┘
```

## Decomposição em US (pra criar via `tasks-create` MCP)

> Estimates fator 10x IA-pair (ADR 0106). Margem 2x já embutida.

### Wave A — Bridge + fundação (pré-req tudo)

**US-ADS-070** · M (~16-24h) · owner `[F+C]` · prio P0
**Bridge ADS → FSM** — criar `Modules/ADS/Services/FsmActionBridge.php` que recebe `RoutingDecision` (do PolicyEngine) com `action_key` + `subject_type` + `subject_id` + `payload` e invoca `ExecuteStageActionService::execute()`. Tradução de identidades: `RoutingDecision.actor_user_id` → `User` model, `subject` → `Transaction`/`JobSheet`/`RecurringInvoice`. Falha de execução não silenciosa: vira `ActionDispatchFailed` event + retry policy declarada per-action. Pest cross-tenant biz=1 vs biz=99 obrigatório.

**US-ADS-071** · S (~4-8h) · owner `[C]` · prio P0
**Audit Card UI cliente final** — view Inertia em `/copiloto/decisoes/{id}/revisao` lendo `mcp_dual_brain_decisions` com campos `client_visible=true`, mostra "decisão automatizada por [agent name]", payload-snapshot legível em PT-BR, botão "solicitar revisão humana" que abre formulário (envia `RevisaoSolicitada` event → notifica Wagner). Permission `copiloto.decisao.revisar` pública pra contact do biz. PT-BR neutro. Mobile-first (Larissa 1280px).

**US-ADS-072** · S (~4-6h) · owner `[F]` · prio P0
**Action FSM `cobrar_fatura`** — adicionar action no seed `FsmProcessoVendaComProducaoSeeder` (e Repair se aplicável) com `is_critical=true`, role `vendas.cobranca#{biz}`, side-effect `EnviarCobrancaJob`. Migration nullable se necessário. Documentar payload schema (canal, tom, mensagem, contact_id).

**US-ADS-073** · S (~6h) · owner `[C]` · prio P1
**RoutingDecision payload schema v2** — extender `mcp_dual_brain_decisions` com colunas `client_visible boolean`, `audit_card_url varchar nullable`, `revision_requested_at timestamp nullable`, `agent_name varchar`. Migration + atualizar `DualBrainDecision` model + ADS PolicyEngine factory. Backfill `client_visible=false` em decisões antigas.

### Wave B — CobradoraAgent + tools

**US-COPI-080** · M (~20-30h) · owner `[F+C]` · prio P0
**CobradoraAgent + 5 tools** — criar `Modules/Copiloto/Ai/Agents/CobradoraAgent.php` herdando `LaravelAiSdkDriver` Agent base (ADR 0048 padrão). 5 tools internas em `Modules/Copiloto/Ai/Tools/`: `ListarFaturasVencidasTool` (recurring_invoices + transactions, scope business), `EscolherCanalTool` (WhatsApp/email/esperar via Contact consent + histórico tentativas), `RedigirMensagemCobrancaTool` (3 templates PT-BR cordial/firme/formal + variáveis), `AgendarEscalationTool` (cron next-attempt + escalation matrix), `RegistrarTentativaTool` (append `cobranca_tentativas` log table). Charter próprio em `Modules/Copiloto/Ai/Agents/CobradoraAgent.charter.md` (Mission/Goals/Non-Goals/UX/Anti-hooks). Pest cobertura ≥80%.

**US-COPI-081** · S (~6-10h) · owner `[F]` · prio P0
**EnviarCobrancaJob** — job assíncrono que recebe `business_id`, `contact_id`, `canal`, `mensagem`, `decisao_id` e dispara WhatsappJob OR Mail dispatch. Respeita `Contact::canReceiveWhatsappNotification()` / `canReceiveEmailNotification()` (ADR 0143 §LGPD consent). Adiciona Audit Card link no final da mensagem. Log estruturado + retry exponencial.

**US-COPI-082** · S (~4-6h) · owner `[F]` · prio P1
**Tabela `cobranca_tentativas`** — migration nova (`business_id`, `recurring_invoice_id` OR `transaction_id`, `contact_id`, `canal`, `mensagem_snapshot`, `tom`, `enviada_at`, `respondida_at nullable`, `decisao_id` FK → mcp_dual_brain_decisions). Append-only (Tier 0 lei-fiscal análoga). Index composto (biz, due_date, contact).

**US-ADS-074** · S (~4h) · owner `[C]` · prio P1
**PolicyEngine config "cobranca_fatura"** — adicionar action_type em `config/ads.php` com matriz HITL: valor <R$ [redacted Tier 0] = L0; valor R$ [redacted Tier 0]-R$ [redacted Tier 0] = L1; valor >R$ [redacted Tier 0] OR cliente VIP OR primeira cobrança mês = L2; bloqueio Asaas/cancelamento = L3. Thresholds documentados + auditáveis. Pest matriz completa.

### Wave C — Observabilidade + smoke

**US-COPI-083** · S (~4-6h) · owner `[F]` · prio P1
**Dashboard Cobradora ROTA LIVRE** — view Inertia `/copiloto/admin/cobradora` mostra (a) faturas vencidas com status próxima ação, (b) últimas 50 tentativas (timeline), (c) KPIs day-over-day: taxa resposta, taxa pagamento pós-cobrança, custo Brain B, decisões REQUIRE_HUMAN_REVIEW. Filtro per-biz default biz=user-logado.

**US-COPI-084** · S (~4h) · owner `[F+C]` · prio P1
**OTel GenAI métricas Cobradora** — exportar `cobradora.decisao.confidence`, `cobradora.action.dispatched`, `cobradora.tentativa.respondida_at`, `cobradora.brain_b.cost_brl`. Sink em `mcp_otel_genai_metrics` existente. Drift detection alerta se custo Brain B sobe >50% week-over-week.

**US-COPI-085** · M (~8-12h) · owner `[F+C]` · prio P0
**Smoke biz=4 end-to-end** — script Pest `tests/Feature/Smoke/CobradoraE2eTest.php` simula fatura vencida real (snapshot anonimizado biz=4), CobradoraAgent decide canal WhatsApp tom cordial, dispatcha FsmActionBridge → ExecuteStageActionService → EnviarCobrancaJob → Whatsapp daemon (mocked em CI; real em smoke local). Verifica `cobranca_tentativas` append, `mcp_dual_brain_decisions` audit, Audit Card URL acessível. Roteiro de smoke real em Hostinger documentado em RUNBOOK próprio.

### Wave D — Canary + gate ramp-up

**US-COPI-086** · M (~6-10h) · owner `[W+C]` · prio P0
**Canary biz=4 + GA gate** — config flag `COBRADORA_ATIVA_BIZ_4=true` em `.env` Hostinger. Dia 1-7: agent decide MAS não dispatcha (dry-run, Wagner revisa decisões). Dia 8-14: dispatcha L0 e L1 só. Dia 15-30: full HITL ladder. Gate de GA per-biz: 90%+ taxa resposta ≥média anterior; 0 incidente LGPD; <2 escalation/dia média. Decisão GA em ADR retro pós-30d.

**US-DOC-090** · S (~2-4h) · owner `[C]` · prio P2
**RUNBOOK Cobradora ROTA LIVRE** — `memory/requisitos/Copiloto/RUNBOOK-cobradora-rotalivre.md` no padrão Cockpit (skill `cockpit-runbook`) — 11 seções obrigatórias, comandos rollback (desligar canary), troubleshoot (mensagem não enviada, escalation perdida, Audit Card 404).

**US-DOC-091** · S (~2h) · owner `[C]` · prio P2
**Charter Audit Card** — `resources/js/Pages/Copiloto/Decisoes/Revisao.charter.md` (Mission/Goals/Non-Goals/UX targets/Anti-hooks).

## Sucesso (gates pra GA além de ROTA LIVRE)

KPIs medidos via dashboard US-COPI-083 + OTel US-COPI-084, snapshot semanal em `mcp_briefs`:

1. **Taxa resposta cobranças** ≥ baseline 30d pré-piloto (ROTA LIVRE histórico)
2. **DSO (Days Sales Outstanding) biz=4** redução mensurável (alvo -15%, líderes 2026 batem -25%)
3. **Audit Card click-through** ≥1 cliente clicou e revisão foi atendida humanamente (prova caminho LGPD funcional)
4. **Custo Brain B mensal** <R$ [redacted Tier 0] pro biz=4 (controle financeiro — se exceder, reroute pra Brain A/Haiku)
5. **Zero incidente LGPD** (cliente reclamou, ANPD notificou, ou consent violado)
6. **Wagner aprova L2/L3** dentro de 4h média (HITL não vira bottleneck)
7. **Zero `withoutGlobalScopes` violation** em PR (multi-tenant intransigente)
8. **Pest cobertura agent + bridge + UI** ≥80%

## Critérios de aborto (escrever ADR retro + pausar generalização)

- Larissa pede pra desligar 2× em 30d
- Qualquer cliente final reclama formalmente ou aciona LGPD/ANPD
- 5+ falsos positivos materiais (cobrança disparada em fatura já paga, contato errado, valor errado)
- Custo Brain B >R$ [redacted Tier 0]/mês biz=4 (3x orçado)
- Tier 0 violado (qualquer agent toca dado cross-tenant)
- Wagner faz >10 overrides/dia (sinal: agent não confia)

## Não-Goals (importante distinguir)

- ❌ Não é "Jana fica autônoma" — Jana segue copiloto conversacional (Modules/Copiloto). CobradoraAgent é um agente especializado SEPARADO que vive em Modules/Copiloto/Ai/Agents/.
- ❌ Não é "ERP autônomo" agora — é piloto unitário. ERP autônomo R$ [redacted Tier 0]M/24m segue norte estratégico, não escopo deste ADR.
- ❌ Não é "Jana Pro Brief" (ADR 0140) — produto diferente (brief narrativo recall-only, sem ação FSM).
- ❌ Não é DPO formal — Eliana sem pressão (ADR 0094 §regras-time + decisão Wagner 2026-05-09).
- ❌ Não é framework agent novo — usa `laravel/ai` + `LaravelAiSdkDriver` (preserva ADR 0048).
- ❌ Não é knowledge graph — gap #9 fica pra depois de 2-3 agentes provarem necessidade.
- ❌ Não promover skill `ads-route` pra Tier A — manter dormente até 2-3 casos rodarem.

## Alternativas consideradas (rejeitadas)

1. **Pesquisar mais agent frameworks** (LangGraph/CrewAI/AutoGen/Swarm) — rejeitada. ADR 0048 fechou. Foco em integração.
2. **Construir 3+ agentes em paralelo (Cobradora + Auditora + Triadora)** — rejeitada. Piloto unitário valida arquitetura antes de scale. Reflexion paper N=1 (sessão 2026-05-13) mostra perigo de inflar prematuro.
3. **Começar por aprovação de despesa** — rejeitada. Caso menos commoditizado no estado-da-arte 2026 (Daylit/Stuut etc cravam dunning/AR como mais maduro).
4. **DPO interno Eliana antes de piloto** — rejeitada. Wagner decidiu 2026-05-09 sem pressão Eliana. Counsel externo segue necessário, mas piloto pode rodar com Tier 0 atual + Audit Card UI cobrindo Art. 20.
5. **Agent SaaS de prateleira (Lindy/Bardeen + Asaas integrar)** — rejeitada. Não dá Tier 0 multi-tenant nem audit append-only nem PT-BR contextual ROTA LIVRE. Diferencial competitivo BR perdido.

## Consequências

### Positivas

1. **Diferencial competitivo BR defensável** — agente IA NATIVO no ERP, primeiro entre Bling/Tiny/Omie/ContaAzul.
2. **Compliance Art. 20 LGPD ativada** — Audit Card visível protege contra ANPD NT 12/2025.
3. **Reuso máximo da fundação** — Jana+ADS+FSM já em prod, só conecta.
4. **Showcase comercial ROTA LIVRE** — caso real Larissa vira material de vendas pra ComVis/OficinaAuto.
5. **Pivot ADS legitimado** — ADS deixa de ser "skill dormente" e vira camada produtiva real.
6. **Pattern reusável** — Wave A (FsmActionBridge + Audit Card) serve a TODO agente administrativo futuro.

### Negativas / Trade-offs

1. **Risco regulatório LGPD residual** — sem DPO formal, Wagner responde pessoalmente em fiscalização ANPD até Eliana decidir estudar.
2. **Custo Brain B operacional** — orçamento R$ [redacted Tier 0]/mês biz=4 (controlável, mas é novo OPEX).
3. **Wagner vira bottleneck L2/L3 30d** — durante piloto, HITL aprovação é manual. Mitigação: dashboard mobile + threshold ajustáveis.
4. **Reframing semântico ADS** — antes "atendimento WhatsApp router"; agora "router universal de ações administrativas". Documentar bem na skill `ads-decision-flow`.
5. **Atrasa gaps #4 (Dreaming), #7 (voice), #9 (KG)** — Wagner decide depois se backporta.

## Relação com ADRs existentes

- **Amends [0094](0094-constituicao-v2-7-camadas-8-principios.md)** — adiciona princípio "Audit Card visível ao cliente final" como Tier 0 quando há decisão automatizada (extensão do princípio 7 Transparência).
- **Reusa [0035](0035-stack-ai-canonica-wagner-2026-04-26.md), [0048](0048-framework-agentes-laravel-ai-vizra-rejeitada.md), [0053](0053-mcp-server-governanca-como-produto.md), [0093](0093-multi-tenant-isolation-tier-0.md), [0143](0143-fsm-pipeline-live-prod-marco-2026-05-12.md)** — sem mudança.
- **Distingue de [0140](0140-jana-pro-produto-comercial-saas.md)** — Jana Pro = brief comercial recall-only; este ADR = agente executor administrativo.
- **Calibrado por [0106](0106-recalibracao-velocidade-fator-10x-ia-pair.md)** — estimates IA-pair fator 10x + margem 2x.

## Próximos passos imediatos (pós-aprovação Wagner)

1. **Wagner aprovar ADR + criar tasks via MCP `tasks-create`** pra US-ADS-070..074, US-COPI-080..086, US-DOC-090..091 (12 tasks total). Owner sugestão: F+C predominante, alguns C-only e W+C no canary gate.
2. **Cycle assignment** — atribuir tasks ao próximo cycle hábil (CYCLE-07 ou successor) via `cycle-tasks-add`.
3. **Skill `ads-decision-flow`** — atualizar descrição refletindo pivot administrativo (ainda Tier B).
4. **Memory sync** — `sync-mem` propaga ADR pro MCP server via webhook GitHub.
5. **Reportar ROTA LIVRE Larissa** — comunicar piloto antes do canary dia 1 (transparência + opt-out se ela quiser).

---

*Próxima revisão obrigatória: 2026-06-15 (30d pós-aprovação) — ADR de retrospectiva decidindo GA / pivotar / abortar conforme KPIs §Sucesso/§Falha.*

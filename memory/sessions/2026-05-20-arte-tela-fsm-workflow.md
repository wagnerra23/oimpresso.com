# Estado-da-arte — Tela de FSM (visualização de workflow de estados)

**Data:** 2026-05-20
**Branch:** `claude/adr-0171-oficinaauto-ativacao-martinho`
**Escopo:** UI que visualiza o grafo de estados FSM (não engine — engine é canônica via [ADR 0129](../decisions/0129-state-machine-canonica-fsm-rbac.md) + [ADR 0143](../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md))
**Contexto disparador:** ativação Martinho (oficina mecânica) — OS percorre RECEBIDO → DIAGNÓSTICO → ORÇAMENTO → APROVADO → EXECUÇÃO → FINALIZADO
**Princípio fundador:** "criar especialista + comparar com os melhores" (Wagner 2026-05-13)

---

## Fase 1 — Pesquisa estado-da-arte (limpa, sem contaminar)

5 referências canônicas 2026 de visualização de workflow de estados. Cada uma resolve um pedaço diferente do problema — junto, são o teto da indústria.

| # | Player | Como resolve (mecanismo concreto) | Por que é referência |
|---|---|---|---|
| 1 | **Camunda Operate + Modeler** | Render BPMN 2.0 do processo com **heatmap de tokens vivos** sobreposto ao grafo (qual instância está em qual nó AGORA). Incidentes destacados inline no diagrama. SLA + branch analysis = bottleneck visível em segundos. API REST retorna estado das instâncias pra UI custom. | Líder enterprise BPM 15+ anos. BPMN é o padrão ISO/IEC 19510 — toda ferramenta de processo do mundo lê o XML. |
| 2 | **Temporal Web UI** | **Timeline view chronological** dos eventos (≈40 tipos: scheduled, started, completed, signaled, terminated). Compact view = progressão linear. **Live updates** — eventos novos aparecem em real-time. Operações safe (retry, terminate, signal) com confirmação. | Padrão code-first workflow 2024+. Empresas como Stripe/Snap rodam orquestração crítica em Temporal. UI nasceu pra debug de runtime, não desenho. |
| 3 | **Linear** | **Single-entity FSM mais polido do mercado.** Status property com categorias rígidas (Backlog/Todo/In Progress/Done/Canceled), reorder DENTRO de categoria mas não cross-category. Keyboard-first (`S` muda status). **Automação cross-tool** (PR aberto no GitHub → issue avança auto). UI refresh 2026-03 consolidou navegação. | Benchmark de UX state transition. Opinionated > flexível — força consistência sem fricção. |
| 4 | **n8n** | **Visual editor + execution trace replay.** Cada nó mostra input/output adjacente ao config. **Re-run com data de execução anterior** (debug failed prod sem re-triggar evento). Logs visuais por step. Workflow history versionado. | Líder open-source automation 2025-2026, 200k+ stars. Padrão "visual + replayable" virou expectativa de mercado pra workflow tools. |
| 5 | **Stately/XState Inspector** | **Statechart formal** (Harel) com hierarchical states, parallel regions, guards explícitos no diagrama. **Inspector live** = roda app real e vê transição acontecendo. Editor visual exporta código TypeScript. Compositional — máquinas se compõem em actor model. | Canônico developer-facing. Quando dev quer modelar FSM "do jeito certo", Stately é referência. XState ~3M downloads/semana. |

**Padrões transversais** que emergem das 5:
- **Live state** — UI não é PDF de manual; muda quando estado muda (Operate heatmap, Temporal liveness, Linear realtime sync).
- **Append-only audit** com user + when + payload (todos têm).
- **Visual + manual override** — grafo bonito serve operação, não só docs. Botão "Avançar" próximo do nó.
- **Guards/permissions explícitos** — por que NÃO posso transitar é tão importante quanto poder.
- **Replay/debug** — produção quebrou → quero rodar de novo com mesmo input (n8n, Temporal explícitos).

---

## Fase 2 — Compare com o que o oimpresso tem

### Backend FSM oimpresso — robusto, canônico, LIVE em prod biz=1

Fundação cataloga em [ADR 0143](../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) (40+ PRs em 10h, 2026-05-12):

- 5 tabelas: `sale_processes`, `sale_process_stages`, `sale_stage_actions`, `sale_stage_action_roles`, `sale_stage_history`
- `ExecuteStageActionService` ([app/Domain/Fsm/Services/ExecuteStageActionService.php](../../app/Domain/Fsm/Services/ExecuteStageActionService.php)) — 6 responsabilidades: resolve action, valida tenancy, RBAC, transação atômica, update stage, log history
- Side-effects desacoplados em classes próprias (`SideEffects/CancelarVendaCascade`, `ReservarEstoque`, `IniciarLocacaoCacamba`, etc — 15 catalogadas)
- Trait `GuardsFsmTransitions` + `FsmAuthorizationFlag` singleton bloqueia UPDATE direto em `current_stage_id` (fail-secure US-SELL-032)
- Multi-tenant Tier 0 via `HasBusinessScope` + double-check `business_id` no service (ADR 0093)
- `fsm:scan-drift` daily 03:00 BRT detecta estados inválidos
- 2 processos seed produtivos: "Venda Com Produção" (11 stages × 21 actions) + "OS Reparo Padrão" (13 stages × ~15 actions)

**Backend bate o estado-da-arte em rigor estrutural** — RBAC granular per-transição + multi-tenant + audit append-only + side-effect desacoplado já é melhor que Linear/n8n e equipara Temporal/Camunda em isolation e auditability.

### Frontend FSM oimpresso — funcional mas pobre visualmente

Componentes existentes:

| Componente | Path | O que faz |
|---|---|---|
| `ServiceOrderFsmActionPanel.tsx` | `resources/js/Pages/OficinaAuto/ServiceOrders/_components/` | Lista botões de actions executáveis no stage atual + modal confirm crítico + StartPipelineEmptyState. Bom UX inline mas sem grafo. |
| `SaleTimeline.tsx` | `resources/js/Pages/Sells/_components/` | Timeline cronológica de `sale_stage_history` (lista vertical com from_stage → to_stage + user + when + payload). Boa pra Sells, **não existe equivalente em OficinaAuto** (Show.tsx é V0 scaffold sem timeline). |
| `SaleAuditTrail.tsx` | `resources/js/Pages/Sells/_components/` | Audit trail com fallback determinístico → fetch real `sale_stage_history`. Ícones por kind. |
| `ServiceOrderSheet.tsx` | OficinaAuto drawer | Embute `ServiceOrderFsmActionPanel` mas **não embute timeline de stages** — só dados da OS. |
| `ProducaoOficina/Index.tsx` | Kanban caçambas | 5 colunas (Disponível/Locada/Aguardando/Manutenção/Pronta) com drag-drop → mapeia pra action FSM. **Kanban-por-caçamba existe, kanban-por-OS-stage não.** |

**Nenhuma tela do oimpresso renderiza o GRAFO** (nodes + edges) da máquina de estados. Renderiza:
- estado atual (badge),
- timeline linear (Sells/SaleTimeline),
- botões de transições disponíveis (FsmActionPanel),
- kanban por status de entidade (caçamba — não por stage do processo).

Comparação dimensão por dimensão:

| Dimensão | Estado-da-arte | oimpresso hoje | Distância |
|---|---|---|---|
| Visualização do grafo (nodes+edges) | Camunda/Stately renderizam SVG do processo | **Ausente** | longa |
| Estado atual destacado | Heatmap Operate, badge Linear, highlight Stately | Badge no Sheet/header ✅ | curta |
| Timeline/histórico transições | Temporal Timeline view, SaleAuditTrail oimpresso | ✅ em Sells; **ausente em OficinaAuto Show** | média (port copy) |
| Transição manual | Todos | ✅ FsmActionPanel | curta |
| Guards/validações visíveis | Stately mostra guards no grafo, Camunda mostra incidents | Backend valida (`is_critical` + roles); UI só esconde action se `can_execute=false` + texto "X ação(ões) oculta(s) por falta de permissão" | média |
| Side effects declarados | Camunda mostra na BPMN, n8n trace replay | ✅ FsmActionPanel mostra ícone Zap + texto "dispara efeitos colaterais" no modal | curta |
| Bulk transitions | Linear shift-select N issues → muda status | **Ausente** (apenas `fsm:bulk-start-pipeline` CLI) | média |
| Filtros por estado | Linear filtros, Notion views | ✅ Index OS filtra por `status` legacy; **não filtra por `current_stage_id` FSM** | média |
| SLA / tempo no estado | Camunda slack indicators, Linear cycle time | Apenas `is_overdue` em locações (não SLA por stage) | longa |
| Audit trail completo | Todos | ✅ canônico (`sale_stage_history` append-only + ADR 0143) | nula |
| Permissions por transição | Linear hide, Camunda candidate users | ✅ Backend (`SaleStageActionRole` + Spatie); UI esconde botão se `can_execute=false` | curta |
| Workflow customization (sem deploy) | Camunda Modeler, ServiceNow Studio | ✅ Schema permite (tabelas per-business); **UI admin pra editar processo NÃO EXISTE** — só via seeder/SQL | longa |
| Mobile/responsivo | Linear mobile app, Camunda Tasklist mobile | Drawer Sheet responsivo mas grafo seria desafio | média |
| Integrations (webhook por transição) | Todos | ✅ event_class por action (Laravel events), mas sem UI configurar | média |
| **Multi-tenant `business_id` Tier 0** | N/A (single-tenant maioria) ou hard sep (Camunda enterprise) | ✅ `HasBusinessScope` global scope + double-check + `fsm:scan-drift` daily | **oimpresso supera mercado SaaS PME** |

### Nota ponderada

Peso 1-5 × nota oimpresso 0-10:

| Dimensão | Peso | Nota oimpresso | Peso×Nota |
|---|---|---|---|
| Multi-tenant Tier 0 | 5 | 10 | 50 |
| Audit trail append-only | 5 | 10 | 50 |
| Transição manual | 5 | 9 | 45 |
| Estado atual destacado | 4 | 8 | 32 |
| Permissions por transição | 5 | 8 | 40 |
| Side effects declarados | 4 | 8 | 32 |
| Timeline/histórico (em Sells) | 4 | 7 | 28 |
| Guards visíveis | 3 | 6 | 18 |
| Integrations (event_class) | 3 | 6 | 18 |
| Filtros por estado | 3 | 5 | 15 |
| Mobile/responsivo | 3 | 6 | 18 |
| Bulk transitions | 2 | 2 | 4 |
| Visualização do grafo | 4 | 2 | 8 |
| SLA tempo no estado | 4 | 2 | 8 |
| Workflow customization UI | 3 | 1 | 3 |
| **Total** (max = soma pesos × 10 = **570**) | 57 | — | **369** |

**Nota final: 369/570 = 65/100** — backend mata o mercado, frontend está em ~50% do teto.

---

## Fase 3 — Avalie o que está faltando (Top 5 gaps)

| # | Gap | Impacto | Esforço IA-pair ([ADR 0106](../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)) | Pré-req? |
|---|---|---|---|---|
| 1 | **Timeline FSM em OficinaAuto Show/Sheet** — port do `SaleTimeline.tsx` pra `ServiceOrderSheet` (componente já existe, backend `sale_stage_history` já tem dados via `transaction_id` ou polimórfico) | **ALTO** Martinho precisa ver "quando o orçamento foi aprovado, por quem" | 1-2h | Endpoint `/oficina-auto/service-orders/{id}/history` (pode usar polymorphic ou nova rota; backend trivial) |
| 2 | **Visualização do grafo (mini-mapa de stages)** — SVG/CSS horizontal com 6 nodes do processo OS, current_stage destacado, próximos stages alcançáveis em cor distinta. NÃO BPMN completo — só "você está aqui" pictórico | **ALTO** descomplica explicação pra Martinho ("a OS tá no orçamento, falta aprovar pra ir pra execução"). UX educativa | 3-4h | Nenhum bloqueante — dados já no `fsm/actions` endpoint |
| 3 | **Filtros por `current_stage_id` no Index OS** — sidebar/chips com stages do processo + contador por stage (estilo Linear) | **MÉDIO** Martinho quer ver "todas OS em ORÇAMENTO" pra cobrar aprovação | 1-2h | Adicionar relação `currentStage` no query Index + UI chips |
| 4 | **Kanban por stage FSM (não só por status caçamba)** — view alternativa Index OS com colunas = stages do processo, drag-drop dispara action FSM (igual ProducaoOficina mas pra OS) | **MÉDIO** vista operacional ótima — Wagner já validou pattern em ProducaoOficina caçamba | 4-6h | Mapping drag → action (já existe pattern em `resolveDragMapping`) |
| 5 | **SLA por stage + slack time** — alerta "OS X há 5 dias em DIAGNÓSTICO (SLA 2 dias)" + dashboard agregado | **MÉDIO** controle operacional sério — diferencia oimpresso vs concorrente PME | 6-8h | Coluna nova `sla_hours` em `sale_process_stages` + computed accessor + cronjob ou view |

### Gaps menores (não Top 5 mas anotados pra evolução)

- Bulk transitions (selecionar N OS no Index → avançar todas) — 3-4h, esforço médio, impacto baixo no MVP Martinho
- UI admin pra editar processo (criar stages/actions sem seeder) — 16-24h, impacto longo prazo (autoatendimento cliente PME), pré-req: design system de form complex
- Replay/debug execução FSM tipo n8n — 8-12h, impacto baixo (caso raro de bug operação, devs preferem log SQL)
- Grafo BPMN-completo via `bpmn-js` ou `react-flow` — 24-40h, impacto baixo (over-engineering pra PME)

---

## Recomendação Wagner

**Comece por Gap #1 (Timeline OficinaAuto) — alto-impacto-baixo-esforço, sem pré-req bloqueante.**

Razão: Martinho na demo vai abrir uma OS no Sheet e perguntar "quem aprovou esse orçamento?". Hoje o `ServiceOrderSheet` mostra estado + botões + dados — não mostra HISTÓRICO. `SaleTimeline.tsx` já é canônico em Sells (PR #623), só falta port + endpoint OS.

**Subset MVP Martinho (ordem de execução, hoje + amanhã):**

1. **Gap #1 — Timeline em OficinaAuto Sheet** (1-2h IA-pair) — DEMO-CRÍTICO
2. **Gap #3 — Filtros por stage no Index** (1-2h) — completa loop "ver todas em ORÇAMENTO"
3. **Gap #2 — Mini-grafo horizontal de stages** (3-4h) — diferencial visual, UX educativa

Total ~7h IA-pair (≈70h humano calibrado) — cabe num ciclo de 2 dias.

**Pós-piloto (fica pra evolução, NÃO entra na ativação Martinho):**
- Gap #4 (Kanban por stage) — espera validação Martinho do pattern atual
- Gap #5 (SLA) — espera Martinho reportar "demorou demais" como sinal qualificado ([ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md))

**Próxima ação hoje:** abrir spec ADR-0171 ramo `claude/adr-0171-oficinaauto-ativacao-martinho` e adicionar **US-OFICINA-TIMELINE-001** com:
- Backend: `OficinaAuto\Http\Controllers\ServiceOrderHistoryController@index` retornando shape `TimelineResponse` (mesmo de `SaleHistoryController`)
- Rota `GET /oficina-auto/service-orders/{id}/history`
- Frontend: copiar `SaleTimeline.tsx` → `ServiceOrderTimeline.tsx`, ajustar tipos (`service_order_id` em vez de `transaction_id`), embutir no `ServiceOrderSheet` abaixo do `ServiceOrderFsmActionPanel`

---

## Restrições respeitadas

- Multi-tenant Tier 0 ([ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md)): qualquer gap depende de `business_id` scope — endpoint history precisa global scope `HasBusinessScope`, filtros precisam respeitar tenant
- Sem PII real (nenhum CPF/CNPJ/razão social em queries WebSearch)
- Nada commitado, só Write neste doc
- Sem task MCP criada (publication-policy — Wagner aprova depois)

## Referências

- [ADR 0129 — State Machine canônica](../decisions/0129-state-machine-canonica-fsm-rbac.md)
- [ADR 0143 — FSM Pipeline LIVE prod 2026-05-12](../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
- [ADR 0171 — OficinaAuto ativação Martinho faseada](../decisions/0171-oficinaauto-ativacao-piloto-martinho-faseada.md)
- [ADR 0093 — Multi-tenant Tier 0](../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0106 — Recalibração 10x IA-pair](../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)
- [ADR 0105 — Cliente como sinal](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)
- Componente canon: `resources/js/Pages/Sells/_components/SaleTimeline.tsx`
- Componente canon: `resources/js/Pages/OficinaAuto/ServiceOrders/_components/ServiceOrderFsmActionPanel.tsx`

### Fontes externas (Fase 1)

- [Camunda Operate API monitoring](https://camunda.com/blog/2023/09/monitor-process-instance-progress-bpmn-diagram-operate-api/)
- [Camunda Tasklist](https://www.camunda.com/platform/tasklist/)
- [Temporal Web UI docs](https://docs.temporal.io/web-ui)
- [Temporal Timeline View blog](https://temporal.io/blog/lets-visualize-a-workflow)
- [Linear Issue Status docs](https://linear.app/docs/configuring-workflows)
- [Linear UI refresh 2026-03](https://linear.app/changelog/2026-03-12-ui-refresh)
- [n8n debug & re-run executions](https://docs.n8n.io/workflows/executions/debug/)
- [n8n features](https://n8n.io/features/)
- [Stately XState Visualizer](https://stately.ai/viz)
- [Stately Inspector docs](https://stately.ai/docs/inspector)
- [ServiceNow Workflow Studio diagramming](https://www.servicenow.com/community/developer-articles/flow-diagramming-revolutionizing-workflow-visualization-with/ta-p/2819961)
- [Notion database automations 2026](https://www.notion.com/help/database-automations)
- [Agent audit trail best practices 2026](https://www.digitalapplied.com/blog/agent-audit-trail-design-7-best-practices-2026)

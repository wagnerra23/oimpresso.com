# Cobradora piloto ROTA LIVRE — batch de tasks pra criar no MCP

> **Fonte:** [ADR 0145](../../decisions/0145-ia-administradora-pivot-ads-fsm-piloto-cobradora.md) §Decomposição em US
> **Decisão Wagner 2026-05-15:** aprovado ADR + tasks devem ser criadas. Wagner cria via tool MCP `tasks-create` (tool não exposta na worktree atual).
> **Ajustes Wagner 2026-05-15:** (a) feature PAGA / add-on monetizado — ver `cobradora_ativa` flag billing; (b) DRY-RUN total até Wagner confiar — Larissa não recebe mensagem real, comunicação a ela só DEPOIS de Wagner virar flag.

## Como criar no MCP

Pra cada bloco abaixo, rodar (exemplo schema — ajustar nomes de campo conforme tool real):

```
tasks-create
  title: "<title>"
  module: "<module>"
  priority: "<P0|P1|P2|P3>"
  estimate: "<S|M|L>"
  owner: "<owner-suggestion>"
  type: "us"
  spec_ref: "ADR-0145"
  depends_on: ["<US-ID>", ...]
  description: |
    <multiline markdown>
```

Wagner ajusta owner/cycle/sprint na hora de criar. Estimates já têm margem 2x sobre fator 10x IA-pair ([ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)).

---

## Wave A — Bridge + fundação (sem ela, nada anda)

### US-ADS-070 · Bridge ADS → FSM

- **module:** ADS
- **priority:** P0
- **estimate:** M (~16-24h IA-pair)
- **owner sugerido:** F+C
- **depends_on:** [US-ADS-072, US-ADS-073] *(action_key + schema v2 antes do bridge usá-los)*

**Description:**

Criar `Modules/ADS/Services/FsmActionBridge.php` que recebe `RoutingDecision` (PolicyEngine output) com `action_key`, `subject_type` (Transaction/JobSheet/RecurringInvoice), `subject_id`, `payload`, e invoca `ExecuteStageActionService::execute(subject, action_key, user, payload)`.

**Acceptance criteria:**

- [ ] Bridge traduz `RoutingDecision.actor_user_id` → `User` model real
- [ ] Bridge resolve `subject_type` + `subject_id` → Eloquent model (Transaction / JobSheet / RecurringInvoice) com scope `business_id`
- [ ] Falha do `ExecuteStageActionService` → emit `ActionDispatchFailed` event + log estruturado + retry policy declarada per-action
- [ ] Pest cross-tenant biz=1 vs biz=99 obrigatório (skill `multi-tenant-patterns`)
- [ ] Pest cobertura ≥80% do service
- [ ] Documentar payload schema esperado por action_key em PHPDoc + ADR companion se necessário

---

### US-ADS-071 · Audit Card UI cliente final

- **module:** ADS
- **priority:** P0
- **estimate:** S (~4-8h IA-pair)
- **owner sugerido:** C
- **depends_on:** [US-ADS-073] *(schema v2 com `client_visible` + `audit_card_url`)*

**Description:**

View Inertia em `/copiloto/decisoes/{id}/revisao` lendo `mcp_dual_brain_decisions` onde `client_visible=true`. PT-BR neutro brasileiro. Mobile-first (Larissa 1280px). LGPD Art. 20 / ANPD NT 12/2025 = Tier 0.

**Acceptance criteria:**

- [ ] Mostra "decisão automatizada por [agent_name]" em destaque
- [ ] Payload-snapshot legível em PT-BR (não JSON cru)
- [ ] Botão "solicitar revisão humana" abre formulário → dispara `RevisaoSolicitada` event → notifica Wagner via inbox
- [ ] Permission `copiloto.decisao.revisar` pública pra contact do biz (não exige login user)
- [ ] Acessibilidade WCAG AA (skill `mwart-quality`)
- [ ] Charter `Revisao.charter.md` (criado em US-DOC-091)

---

### US-ADS-072 · Action FSM `cobrar_fatura`

- **module:** Sells (FSM seed)
- **priority:** P0
- **estimate:** S (~4-6h IA-pair)
- **owner sugerido:** F
- **depends_on:** []

**Description:**

Adicionar action `cobrar_fatura` no seed `FsmProcessoVendaComProducaoSeeder` (e equivalente Repair se cabível) com `is_critical=true`, role `vendas.cobranca#{biz}`, side-effect `EnviarCobrancaJob` (US-COPI-081).

**Acceptance criteria:**

- [ ] Action cadastrada no seed pra biz=1 + biz=4 (qualquer biz que rodar seed)
- [ ] Role `vendas.cobranca#{biz}` cadastrada per-business (Spatie suffix)
- [ ] Payload schema documentado: `{canal, tom, mensagem, contact_id, decisao_id}`
- [ ] Pest verifica fail-secure: action sem role = `UnauthorizedActionException`
- [ ] Action permitida em stages: `invoiced`, `paid` (vencidas com `due_date` passado), `delivered` mas não pago
- [ ] Migration nullable se schema FSM precisar de coluna nova

---

### US-ADS-073 · Schema v2 `mcp_dual_brain_decisions` + payload audit card

- **module:** ADS
- **priority:** P0
- **estimate:** S (~6h IA-pair)
- **owner sugerido:** C
- **depends_on:** []

**Description:**

Migration adiciona colunas em `mcp_dual_brain_decisions`: `client_visible boolean default false`, `audit_card_url varchar nullable`, `revision_requested_at timestamp nullable`, `agent_name varchar nullable`. Atualizar Model `DualBrainDecision` + factory + ADS PolicyEngine pra setar `client_visible` quando aplicável.

**Acceptance criteria:**

- [ ] Migration up + down idempotente
- [ ] Backfill decisões antigas com `client_visible=false` (não-quebra atendimento WhatsApp existente)
- [ ] Model com casts apropriados
- [ ] Factory + Pest fixtures atualizados
- [ ] PolicyEngine seta `client_visible=true` quando subject afeta cliente final (não decisão técnica interna)

---

### US-ADS-074 · PolicyEngine config "cobranca_fatura"

- **module:** ADS
- **priority:** P1
- **estimate:** S (~4h IA-pair)
- **owner sugerido:** C
- **depends_on:** [US-ADS-072]

**Description:**

Adicionar `cobranca_fatura` em `config/ads.php` com matriz HITL declarativa per-valor:

```php
'cobranca_fatura' => [
    'thresholds' => [
        ['max_value_brl' => 50, 'hitl' => 'L0'],
        ['max_value_brl' => 500, 'hitl' => 'L1'],
        ['max_value_brl' => null, 'hitl' => 'L2'], // > 500
    ],
    'force_l3' => ['bloqueio_asaas', 'cancelamento_nfe'],
    'force_l2_on' => ['cliente_vip', 'primeira_cobranca_mes'],
],
```

**Acceptance criteria:**

- [ ] Config carregável + cached (`config:cache` OK)
- [ ] PolicyEngine consulta matriz com fallback determinístico
- [ ] Pest cobre matriz completa (10+ scenarios: <50, 50-500, >500, VIP, primeira-mês, bloqueio, cancel)
- [ ] Thresholds documentados em comentário PHPDoc com link pro ADR 0145 §HITL

---

## Wave B — CobradoraAgent + tools

### US-COPI-080 · CobradoraAgent + 5 tools

- **module:** Copiloto
- **priority:** P0
- **estimate:** M (~20-30h IA-pair)
- **owner sugerido:** F+C
- **depends_on:** [US-ADS-070, US-ADS-074, US-COPI-082]

**Description:**

Criar `Modules/Jana/Ai/Agents/CobradoraAgent.php` herdando padrão `LaravelAiSdkDriver` Agent ([ADR 0048](../../decisions/0048-framework-agentes-laravel-ai-vizra-rejeitada.md)). 5 tools internas em `Modules/Jana/Ai/Tools/`.

**5 tools obrigatórias:**

1. `ListarFaturasVencidasTool` — query `recurring_invoices` + `transactions` com `due_date < today` scope `business_id`, retorna array `{id, contact_id, valor, dias_atraso, tentativas_count}`
2. `EscolherCanalTool` — heurística canal por (Contact consent + histórico tentativas + dia/hora atual): WhatsApp / email / "esperar humano"
3. `RedigirMensagemCobrancaTool` — 3 templates PT-BR `cordial-1` / `firme-2` / `formal-3`, variáveis `{nome, valor, dias_atraso, link_pagamento}`
4. `AgendarEscalationTool` — agenda próxima tentativa: 3 dias após canal 1, 7 dias após canal 2, L2 Wagner após canal 3 sem resposta
5. `RegistrarTentativaTool` — append em `cobranca_tentativas` (US-COPI-082)

**Acceptance criteria:**

- [ ] Charter `Modules/Jana/Ai/Agents/CobradoraAgent.charter.md` (Mission/Goals/Non-Goals/UX targets/Anti-hooks) — skill `charter-write`
- [ ] Agent registra decisão em `mcp_dual_brain_decisions` com `agent_name='CobradoraAgent'` + `client_visible=true`
- [ ] Pest cobertura ≥80% per tool + agent
- [ ] Pest cross-tenant biz=1 vs biz=99 (agent de biz=4 NUNCA enxerga faturas biz=1)
- [ ] Janela de envio: manhã útil 09-11h OR tarde 14-16h, nunca fim-semana, respeitar timezone biz
- [ ] Dry-run mode (`COBRADORA_DRY_RUN_BIZ_4=true`) registra decisão mas NÃO dispatcha — Wagner revisa em dashboard

---

### US-COPI-081 · EnviarCobrancaJob

- **module:** Copiloto
- **priority:** P0
- **estimate:** S (~6-10h IA-pair)
- **owner sugerido:** F
- **depends_on:** [US-COPI-082]

**Description:**

Job assíncrono `Modules/Jana/Jobs/EnviarCobrancaJob.php` que recebe `business_id`, `contact_id`, `canal`, `mensagem`, `decisao_id` e dispara WhatsappJob OR Mail dispatch conforme `canal`.

**Acceptance criteria:**

- [ ] Constructor recebe `$businessId` explícito (skill `multi-tenant-patterns` — nunca `session()` em job)
- [ ] Respeita `Contact::canReceiveWhatsappNotification()` / `canReceiveEmailNotification()` ([ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) §LGPD consent) — opt-out → log warning + Event `ClienteSemCanal`
- [ ] Adiciona rodapé Audit Card no final da mensagem: "Decisão automatizada — revisar humanamente em {url}"
- [ ] Retry exponencial 3x com backoff
- [ ] Log estruturado com `decisao_id` + `tentativa_id` pra correlação
- [ ] Dry-run mode respeitado: se `COBRADORA_DRY_RUN_BIZ_4=true` e biz=4, job só loga "would send" sem dispatch real

---

### US-COPI-082 · Tabela `cobranca_tentativas` (append-only)

- **module:** Copiloto
- **priority:** P1
- **estimate:** S (~4-6h IA-pair)
- **owner sugerido:** F
- **depends_on:** [US-ADS-073]

**Description:**

Migration nova `cobranca_tentativas`:

```
business_id (FK, indexed, Tier 0)
recurring_invoice_id (FK nullable)
transaction_id (FK nullable)  -- um dos dois preenchido, XOR check
contact_id (FK)
canal (enum: whatsapp|email|esperar_humano)
mensagem_snapshot (text)
tom (enum: cordial|firme|formal)
enviada_at (timestamp)
respondida_at (timestamp nullable)
decisao_id (FK -> mcp_dual_brain_decisions)
created_at / updated_at
```

**Acceptance criteria:**

- [ ] Append-only enforced via trigger MySQL (similar a `ponto_marcacoes`)
- [ ] Index composto `(business_id, recurring_invoice_id, enviada_at)` + `(business_id, transaction_id, enviada_at)`
- [ ] Global scope `HasBusinessScope` no model
- [ ] FK constraints com `restrict on delete`
- [ ] Pest cobre append-only (UPDATE/DELETE = exception)

---

## Wave C — Observabilidade + smoke

### US-COPI-083 · Dashboard Cobradora ROTA LIVRE

- **module:** Copiloto
- **priority:** P1
- **estimate:** S (~4-6h IA-pair)
- **owner sugerido:** F
- **depends_on:** [US-COPI-080, US-COPI-082]

**Description:**

View Inertia `/copiloto/admin/cobradora` mostra estado vivo do agente.

**Acceptance criteria:**

- [ ] Lista faturas vencidas com próxima ação prevista + janela de envio
- [ ] Timeline últimas 50 tentativas com canal, tom, resposta, decisão_id link
- [ ] KPIs day-over-day: taxa resposta, taxa pagamento pós-cobrança, custo Brain B BRL, decisões `REQUIRE_HUMAN_REVIEW`
- [ ] Filtro per-biz default user-logado (não enxerga outras biz)
- [ ] Botão "ver decisão" abre `mcp_dual_brain_decisions` payload-snapshot completo
- [ ] **Em modo dry-run, banner amarelo destacado:** "Modo dry-run — nenhuma mensagem real enviada"

---

### US-COPI-084 · OTel GenAI métricas Cobradora

- **module:** Copiloto
- **priority:** P1
- **estimate:** S (~4h IA-pair)
- **owner sugerido:** F+C
- **depends_on:** [US-COPI-080]

**Description:**

Instrumentar métricas OTel exportadas pelo CobradoraAgent + EnviarCobrancaJob.

**Acceptance criteria:**

- [ ] Métricas: `cobradora.decisao.confidence` (histogram), `cobradora.action.dispatched` (counter), `cobradora.tentativa.respondida_at` (gauge delta), `cobradora.brain_b.cost_brl` (counter)
- [ ] Sink em `mcp_otel_genai_metrics` existente
- [ ] Drift detection: alerta se `cobradora.brain_b.cost_brl` sobe >50% week-over-week (Pest + cron)
- [ ] Dashboard `/copiloto/admin/cobradora` consome essas métricas (US-COPI-083)

---

### US-COPI-085 · Smoke biz=4 end-to-end

- **module:** Copiloto
- **priority:** P0
- **estimate:** M (~8-12h IA-pair)
- **owner sugerido:** F+C
- **depends_on:** [US-COPI-080, US-COPI-081, US-COPI-082, US-COPI-083]

**Description:**

Script Pest `tests/Feature/Smoke/CobradoraE2eTest.php` simula fatura vencida (snapshot anonimizado biz=4 — PII redacted, ADR 0101 nunca usar dados cliente em test).

**Acceptance criteria:**

- [ ] Pest E2E: fatura vencida → CobradoraAgent decide → FsmActionBridge → ExecuteStageActionService → EnviarCobrancaJob (mocked em CI, real em smoke local)
- [ ] Verifica: `cobranca_tentativas` append, `mcp_dual_brain_decisions` audit com `client_visible=true`, Audit Card URL acessível, OTel métricas emitidas
- [ ] Smoke biz=99 NEGATIVO obrigatório: agent de biz=4 não enxerga faturas biz=99
- [ ] RUNBOOK smoke real local em `memory/requisitos/Copiloto/RUNBOOK-cobradora-rotalivre.md` (US-DOC-090) documenta comandos + esperado
- [ ] CI verde antes de qualquer canary

---

## Wave D — Canary + gate ramp-up

### US-COPI-086 · Canary biz=4 + GA gate

- **module:** Copiloto
- **priority:** P0
- **estimate:** M (~6-10h IA-pair) + 30d wall-clock
- **owner sugerido:** W+C
- **depends_on:** [US-COPI-085, US-DOC-090]

**Description:**

Rollout escalonado biz=4 com gates per-fase. **Default flag inicial:** `COBRADORA_DRY_RUN_BIZ_4=true` — Wagner vira `false` APENAS após confiar nas decisões em dry-run + comunicar Larissa.

**Acceptance criteria:**

- [ ] Config flag `COBRADORA_ATIVA_BIZ_4=true` + `COBRADORA_DRY_RUN_BIZ_4=true` em `.env` Hostinger
- [ ] **Dia 1-7 (dry-run total):** agent decide MAS não dispatcha. Wagner revisa decisões diárias em dashboard. Larissa NÃO foi comunicada ainda.
- [ ] **Wagner gate manual:** vira `COBRADORA_DRY_RUN_BIZ_4=false` apenas após (a) confiar nas decisões dry-run, (b) comunicar Larissa formalmente, (c) Larissa não-opt-out
- [ ] **Dia 8-14 (dispatch L0 e L1 só):** mensagens reais saem mas só pra valores baixos (<R$ [redacted Tier 0]), Wagner aprova L2+
- [ ] **Dia 15-30 (full HITL ladder):** L0/L1 auto, L2 Wagner aprova 1-click, L3 Wagner + Eliana aprovam
- [ ] **Gate GA pra outros biz (escrever ADR retro):** 90%+ taxa resposta ≥média anterior; 0 incidente LGPD; <2 escalation/dia média; custo Brain B <R$ [redacted Tier 0]/mês; Pest cobertura ≥80% mantida
- [ ] **Aborto automático:** Larissa pede desligar 2× em 30d OR 5+ falsos positivos OR custo Brain B >R$ [redacted Tier 0]/mês OR Tier 0 violado OR Wagner >10 overrides/dia → flag `COBRADORA_ATIVA_BIZ_4=false` + ADR retro

---

### US-DOC-090 · RUNBOOK Cobradora ROTA LIVRE

- **module:** Doc
- **priority:** P2
- **estimate:** S (~2-4h IA-pair)
- **owner sugerido:** C
- **depends_on:** [US-COPI-085]

**Description:**

`memory/requisitos/Copiloto/RUNBOOK-cobradora-rotalivre.md` no padrão Cockpit (skill `cockpit-runbook`) — 11 seções obrigatórias.

**Acceptance criteria:**

- [ ] 11 seções obrigatórias do template Cockpit
- [ ] Comandos rollback documentados: desligar canary, virar dry-run on, abortar agent
- [ ] Troubleshoot: mensagem não enviada, escalation perdida, Audit Card 404, agent custo descontrolado
- [ ] Comandos smoke local executáveis (Pest + tinker + ssh CT 100)
- [ ] PT-BR neutro
- [ ] Skill `cockpit-runbook` aprovado

---

### US-DOC-091 · Charter Audit Card

- **module:** Doc
- **priority:** P2
- **estimate:** S (~2h IA-pair)
- **owner sugerido:** C
- **depends_on:** [US-ADS-071]

**Description:**

`resources/js/Pages/Copiloto/Decisoes/Revisao.charter.md` (Mission/Goals/Non-Goals/UX targets/Anti-hooks).

**Acceptance criteria:**

- [ ] Skill `charter-write` aprovou draft
- [ ] Wagner aprovou Non-Goals + Anti-hooks (parte sensível)
- [ ] Mission focada em LGPD Art. 20 / ANPD NT 12/2025 compliance
- [ ] UX targets: Larissa entende "decisão automatizada — solicitar revisão" em 1 clique, mobile-first 1280px

---

## Resumo executivo

| # | US | Wave | Prio | Estimate | Owner |
|---|---|---|---|---|---|
| 1 | US-ADS-070 — FsmActionBridge | A | P0 | M | F+C |
| 2 | US-ADS-071 — Audit Card UI | A | P0 | S | C |
| 3 | US-ADS-072 — Action `cobrar_fatura` | A | P0 | S | F |
| 4 | US-ADS-073 — Schema v2 audit | A | P0 | S | C |
| 5 | US-ADS-074 — Policy config | A | P1 | S | C |
| 6 | US-COPI-080 — CobradoraAgent + 5 tools | B | P0 | M | F+C |
| 7 | US-COPI-081 — EnviarCobrancaJob | B | P0 | S | F |
| 8 | US-COPI-082 — Tabela tentativas | B | P1 | S | F |
| 9 | US-COPI-083 — Dashboard | C | P1 | S | F |
| 10 | US-COPI-084 — OTel métricas | C | P1 | S | F+C |
| 11 | US-COPI-085 — Smoke E2E | D | P0 | M | F+C |
| 12 | US-COPI-086 — Canary 30d | D | P0 | M + 30d | W+C |
| 13 | US-DOC-090 — RUNBOOK | Doc | P2 | S | C |
| 14 | US-DOC-091 — Charter | Doc | P2 | S | C |

Total: **14 tasks** (12 US + 2 doc), **6 P0 / 4 P1 / 2 P2**. Esforço estimado ~80-120h IA-pair, fator 10x ADR 0106 = ~8-12h calendário se paralelizado 3 agents (waves A→B→C→D sequencial, paralelo dentro da wave). **Canary D adiciona 30d wall-clock obrigatório.**

## Próximo passo Wagner (manual)

1. Abrir tool MCP `tasks-create` (UI MCP ou comando)
2. Criar 14 tasks copiando título + module + priority + estimate + owner + description daqui
3. `depends_on` registrar pra MCP montar grafo
4. Atribuir cycle (CYCLE-07 ou hábil)
5. Quando criadas, chamar `cycles-active` pra confirmar grafo OK

Quando US-ADS-070 estiver `status=ready`, eu pego e implemento (fundação que destrava tudo).

---
module: OficinaAuto
artefato: roadmap
status: em-uso (V0+Fase 1 done · Fase 2 parcial · Fase 3 ATIVA via ADR 0171 canary)
purpose: roadmap fase-a-fase pós V0 scaffold DONE (PR #556) até diferenciais ROI alto
fases: 6 (0+1 done · 2 parcial · 3 ativa · 4-5 propostas)
ultima_atualizacao: 2026-05-26
recalibracao: ADR 0106 (fator 10x IA-pair + margem 2x)
related_adrs: [0093, 0094, 0105, 0106, 0119, 0121, 0137, 0143, 0171, 0192, 0194]
---

# Roadmap — Modules/OficinaAuto

> Espelha formato Roadmap ComVis. Estimates ADR 0106 (codáveis-com-IA × 2 margem; humano-limitado relógio real).
>
> **Última correção (2026-05-26):** vocabulário atualizado pós-[ADR 0194](../../decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md) — sub-vertical real de Martinho é **mecânica pesada / autorizada caminhão basculante CNAE 4520** (NÃO locação caçamba container CNAE 4581). Schema `daily_rate`/`expected_return_date` preservado nullable como sub-vertical 3 hipotético sem cliente real ancorado. Status fases recalibrado contra estado real prod biz=164 LIVE desde 2026-05-13.
>
> **Gating cliente:** cada fase requer satisfação de critério antes de avançar. Sem cliente piloto pagante até Fase 2 = violação [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md). Fases 3+ podem ativar paralelas se múltiplos pilotos.

## Visão geral fases

| Fase | Foco | Estimate IA-pair | Wallclock | Critério ativação | Status |
|---|---|---:|---|---|---|
| **0** | V0 scaffold + Pest baseline | — | — | — | ✅ **DONE** (PR #556 · 2026-05-11) |
| **1** | Importer Martinho + smoke + cleanup tools | 70h | ~3 sem | Fase 0 verde | ✅ **DONE** Martinho · 🔒 Vargas pending V1 · 🟡 cleanup US-029/030/031 pendente |
| **2** | FSM wire-up + UI drawer + diferenciais vertical | 68h | ~3 sem | Fase 1 Martinho done | 🟡 **PARCIAL** (FSM done LIVE 2026-05-20 · WhatsApp PIN charter draft · Vargas multi-placa pending V1) |
| **3** | Bulk migration Martinho + 1º piloto pagante ATIVAÇÃO | 30h dev + wallclock | ~4 sem | Fase 2 verde + ADR ativação | 🟢 **ATIVA** ([ADR 0171](../../decisions/0171-oficinaauto-ativacao-piloto-martinho-faseada.md) · canary 7d em andamento) |
| **4** | App PWA campo mecânico + comissão + lembretes | 62h | ~3 sem | Fase 3 verde + 1+ piloto canary 30d sem incidente | 🔒 |
| **5** | Diferenciais ROI alto (Jana IA + NFSe + OEM se sinal) | 80h+ | ~6 sem | Fase 4 verde + 3+ pilotos ativos + receita ≥ R$ [redacted Tier 0]k/m módulo | 🔒 |

**Fundação Fase 0-2 entregue (~138h IA-pair estimadas) · Fase 3 ATIVA**.

---

## Fase 0 — V0 scaffold (DONE)

✅ **PR #556 (squash `b72981eb`)** — 2026-05-11

Entregue:
- 8 peças nWidart (module.json + composer + Config + ServiceProvider + RouteServiceProvider + InstallController + DataController + Routes)
- Migrations `vehicles` (multi-placa nullable) + `service_orders` (vehicle_id FK + transaction_id nullable)
- Models `Vehicle` + `ServiceOrder` com global scope `business_id` + soft delete + relations
- 9 permissions registradas + sidebar entry
- 8 Pages Inertia (Vehicles + ServiceOrders × Index/Create/Show/Edit)
- 16 Pest tests (CRUD + multi-tenant biz=1 vs biz=99)
- 4 RUNBOOKs MWART hook satisfaction

**Decisão diferida (não bloqueante pós-LIVE prod):** rename `vehicles` → `oa_vehicles` (ADR proposal D2) — descartado de facto, naming `vehicles` mantido. Sem urgência operacional pós biz=164 LIVE 2026-05-13.

---

## Fase 1 — Importer Martinho + cleanup tools

> **Objetivo:** preparar fundação pra migração real cliente legacy + validar schema com dados reais Martinho (sub-vertical 4 mecânica pesada) antes de tocar Vargas (sub-vertical 2 recapagem V1). CRUD + importer (FSM Fase 2).

### Pré-requisitos

- [x] Fase 0 done

### Entregas

| US | Descrição | Estimate | Prioridade | Status |
|---|---|---:|---|---|
| **US-OFICINA-002** | Importer Firebird `EQUIPAMENTO_VEICULO` → `vehicles` (Martinho 91 veículos) | 4h | P0 | ✅ DONE 2026-05-13 13:31 BRT (`scripts/legacy-migration/import-vehicles.py`) |
| **US-OFICINA-009** | Defeitos múltiplos JSON array em `service_orders` | 3h | P0 | 🟡 a confirmar (provável pendente — gap US-OFICINA-027 catálogo peça hidráulica V0 cobre parte) |
| **US-OFICINA-005** | Cleanup tools (epic) — 76.7% inadimplência Martinho legacy | 12h | P0 | 🟡 DIVIDIDO em sub-tasks: |
| └ **US-OFICINA-029** | §A Tela batch UI "Revisão pendências legadas" | 6h | P0 | 🟡 todo (criada 2026-05-26 · owner felipe) |
| └ **US-OFICINA-030** | §B Conciliação VENDA↔FINANCEIRO drift detector (374 vendas R$ [redacted Tier 0]M) | 4h | P0 | 🟡 todo (criada 2026-05-26 · owner felipe) |
| └ **US-OFICINA-031** | §C PESSOAS deduplicador fuzzy match (~920 razões sociais) | 2h | P0 | 🟡 todo (criada 2026-05-26 · owner felipe) |

**Subtotal entregue: importer (4h IA-pair real). Pendente: cleanup tools 12h IA-pair Felipe (Sem 23 2026-06-02→06-08 conforme [levantamento Martinho-ready](../../sessions/2026-05-26-levantamento-martinho-ready.md) §6).**

### Critério de validação Fase 1 → Fase 2

- [x] Pest filter OficinaAuto verde local + CI (3 Pest Feature: ServiceOrderCrudTest, VehicleCrudTest, VehicleMultiTenantTest)
- [x] Importer Martinho smoke prod: 91/91 veículos imported sem erro (2026-05-13 13:31 BRT biz=164)
- [ ] Cleanup tools UI testada Wagner aprovou screenshots (pendente US-029/030/031 Sem 23)
- [x] Smoke biz=4 (ROTA LIVRE) sem regressão — multi-tenant guard validado
- [x] Naming `vehicles` mantido pós-LIVE prod (rename `oa_vehicles` descartado de facto)

---

## Fase 2 — FSM wire-up + diferenciais vertical (Vargas-ready)

> **Objetivo:** governance-align (FSM canon [ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)) + features oficina-específicas calibradas sub-vertical 4 (Martinho — mecânica pesada caminhão basculante) e sub-vertical 2 (Vargas V1 — recapagem multi-placa).

### Pré-requisitos

- [x] Fase 1 done (Martinho)
- [x] Modules/Repair FSM wire-up done (espelhar pattern)

### Entregas

| US | Descrição | Estimate | Prioridade | Status |
|---|---|---:|---|---|
| **US-OFICINA-006 (FSM)** | FSM wire-up: coluna `current_stage_id` + trait + seeder 15 stages + Controller + UI drawer | 6h | P0 | ✅ DONE LIVE 2026-05-20 (tríade gaps #1/#2/#3 — PRs #1195/#1203/#1205 · estado-da-arte 80/100) |
| **US-OFICINA-007** | Importer Vargas multi-placa (1.064 veículos com PLACA+PLACA2+CHASSI2) — sub-vertical 2 | 8h | P1 | 🔒 V1 pending — sub-vertical 2 recapagem (não bloqueia Martinho sub-vertical 4) |
| **US-OFICINA-008** | Schema garantia granular per-item (`oa_pecas_utilizadas` + `oa_servicos_executados` + `oa_garantias`) — parte do US-OFICINA-027 | 5h | P0 | 🟡 sub-parte ABSORVIDA em US-OFICINA-027 (catálogo peça hidráulica V0 + recalc `final_total`) |
| **US-OFICINA-010** | Stages oficina-específicos: `teste_estrada` + `ajuste_final` + loop UI | 4h | P1 | 🟡 a confirmar (provavelmente entregue via FSM tríade — verificar) |
| **US-OFICINA-011** | Re-orçamento (action `escalar_supervisor` + flag `aprovado_apos_aumento`) | 4h | P1 | 🟡 a confirmar |
| **US-OFICINA-014 (WhatsApp PIN)** | Aprovação OS via WhatsApp link público + PIN | 7h | P0 | 🟡 charter `draft` (T1 levantamento — bloqueada por wire-up final ADR 0171 add-on faturável) |
| **US-OFICINA-027** | Catálogo peça hidráulica V0 + recalc `final_total` OS mecânica (`peça×qty + hora×horas`) | 8h | P0 | 🟡 todo (criada 2026-05-26 · owner wagner · destrava cobrança automática real CNAE 4520) |

**Subtotal entregue: FSM wire-up estado-da-arte (~6h IA-pair real). Pendente Fase 2 completa:** US-OFICINA-027 catálogo peça hidráulica (8h) + US-OFICINA-014 WhatsApp PIN (7h) + Vargas V1 (US-OFICINA-007 8h) = **~23h IA-pair Sem 22-23 + V1**.

### Critério de validação Fase 2 → Fase 3

- [x] FSM 15 stages canônicos pestado biz=1 + biz=99 cross-tenant guard (12 Pest specs — history/stages/pipeline)
- [ ] Vargas importer canary biz=1 SEM SMOKE PROD: 1.064/1.064 veículos imported (V1 pending — sub-vertical 2)
- [x] UI drawer FsmActionPanel reuso Modules/Repair funcionando OficinaAuto
- [ ] WhatsApp aprovação link testado: Martinho fake cliente recebe + clica + aprova + FSM action dispara + side-effect rastreado em `sale_stage_history` (charter draft — US-OFICINA-014)
- [ ] Catálogo peça hidráulica V0 entrega `final_total` correto OS mecânica (US-OFICINA-027 — bloqueia cobrança real)

---

## Fase 3 — 1º piloto pagante + bulk migration + canary (ATIVA)

> **Objetivo:** ATIVAÇÃO produção. **Materializada via [ADR 0171](../../decisions/0171-oficinaauto-ativacao-piloto-martinho-faseada.md) (2026-05-20)** — Martinho biz=164 piloto formal, pacote R$ [redacted Tier 0]/mês grandfathered + add-on WhatsApp R$ [redacted Tier 0]/instância beta 30d.

### Pré-requisitos (HARD GATING)

- [x] Fase 2 PARCIAL (FSM done — suficiente pra ativação faseada por feature)
- [x] **Sinal qualificado [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) satisfeito**: Cenário modificado — Martinho aceita beta 30d add-on WhatsApp, base R$ [redacted Tier 0] grandfathered (não Cenário A puro R$ [redacted Tier 0] × 3m upfront do roadmap original — modelo evoluiu)
- [x] ADR canon [`0171-oficinaauto-ativacao-piloto-martinho-faseada.md`](../../decisions/0171-oficinaauto-ativacao-piloto-martinho-faseada.md) **accepted** 2026-05-20 (Wagner [W])
- [ ] Tempário seed 100 serviços manual ready (US-OFICINA-013) — pending (não bloqueia Martinho operar — Wagner edita peça/serviço manual)

### Entregas

| US | Descrição | Estimate | Prioridade | Status |
|---|---|---:|---|---|
| **US-OFICINA-013** | Tabela tempária seed (100 serviços comuns BR + UI CRUD) | 5h | P1 | 🟡 todo (não bloqueia Martinho V0) |
| **US-OFICINA-005-bulk (=US-029/030/031)** | Cleanup tools bulk apply (200 pendências/dia × 23 dias batch) | 12h | P0 | 🟡 todo Sem 23 (felipe owner) |
| **fsm:bulk-start-pipeline-oficina** | Comando bulk migrate OS legadas → FSM stages | 3h | P0 | 🟡 a confirmar (provável já existe pra Sells canon biz=1, adaptar) |
| **US-OFICINA-012** | Consulta CRLV/Renavam (cache 30d, adapter pluggable) | 6h | P1 | 🟡 todo |
| **US-OFICINA-017** | Histórico veículo timeline (Page Show + aba histórico + km diff) | 4h | P1 | 🟡 todo (parcial via Vehicle.show — falta aba "histórico OS") |
| **US-OFICINA-032** | Drawer auto-open via `?os=SO-N` query param (Sells→OficinaAuto) | 2h | P1 | 🟡 todo (criada 2026-05-26 — gap UX friction Wave Z-2) |
| **US-OFICINA-033** | `ServiceOrder.$fillable` += `contact_id` (mass-assignment fix) | 1h | P1 | 🟡 todo (criada 2026-05-26 — quick win) |
| **Canary 7d + onboarding piloto** | Operação assistida com Martinho, daily check, fix incident | wallclock 1 semana | P0 | 🟢 EM ANDAMENTO desde 2026-05-20 ADR 0171 |
| **Auto-faturar OS→Venda** ([ADR 0192](../../decisions/0192-auto-faturar-os-venda-jobsheet-observer.md) extensão) | ServiceOrderObserver hook `concluida` → Transaction `source='oficina'` | 4h | P0 | ✅ DONE LIVE 2026-05-25 prod biz=164 (Wave Z-2 PR #1534) · `final_total=0` mecânica pendente recalc via US-OFICINA-027 |
| **RUNBOOK migração cliente legacy** | RUNBOOK 8 peças onboarding | 4h | P0 | ✅ DONE [RUNBOOK-migracao-cliente-legacy.md](RUNBOOK-migracao-cliente-legacy.md) (a reescrever pós-ADR 0194 — US-OFICINA-028 parte 3/5) |
| **B1 NFSe 500 fix** | Schema race fix Caminho A (pacote R$ [redacted Tier 0] inclui NFSe) | 4h | P0 | ✅ DONE LIVE 2026-05-26 (PR #1597 Caminho A reverter Controller pro schema velho prod) |

**Subtotal:** ATIVAÇÃO done · pendentes ~22h IA-pair Sem 22-23 (cleanup + catálogo peça + UX friction) + onboarding wallclock contínuo.

### Critério de validação Fase 3 → Fase 4

- [ ] Martinho canary 7 dias sem incidente sev1/sev2 (canary em andamento — gate Fase 4)
- [ ] Martinho reporta semanal bugs/features (compromisso ADR 0105)
- [x] **NFC-e auto a partir de boleto pago** funcionando ponta-a-ponta prod biz=164 (ADR 0192 extensão LIVE)
- [ ] **NFSe** funcionando (B1 fix LIVE 2026-05-26 PR #1597 — smoke biz=164 pendente)
- [ ] Cleanup tools bulk: relatório piloto "X pendências canceladas / Y write-off / Z renegociadas" com aprovação dono (Martinho aprova batch — Sem 23 US-029/030/031)
- [ ] Receita reportada R$ [redacted Tier 0]/m grandfathered + add-on WhatsApp R$ [redacted Tier 0]/inst beta 30d → fatura cobrada via Modules/RecurringBilling (US-RB-050/051 P0 wagner-owner CYCLE-06)

### Régua por tela (Onda 0b / ADR 0320) — foto UX + comportamento das telas do canary

> **Aditiva e segura pro canary.** Esta camada é uma **FOTO** (`screen-grade` UX **+** `casos_coverage`
> comportamento), gerada a partir de charter + `.casos.md` + testes existentes. **Não muda nenhuma
> tela** (canary Martinho biz=164 LIVE intocado) e **não toca cálculo** (o dente de cálculo é onda
> própria). Segue o método [Onda 0b](../_Governanca/programa-ondas/onda-0-fundacao/0b-extensao-regua.md)
> +[Onda 1.3](../_Governanca/programa-ondas/onda-1-sells/1.3-regua-por-tela.md) do [Programa de Ondas](../_Governanca/programa-ondas/PLANO-MESTRE.md)
> ([ADR 0320](../../decisions/proposals/0320-programa-ondas-regua-correcao.md)) — a mesma foto que
> em Sells gritou "tela premium, cálculo indefeso". Aqui a régua expõe outra contradição.

Telas do canary fotografadas (scorecard `memory/governance/scorecards/screens/oficinaauto-serviceorders-<tela>.yaml`):

| Tela | UX (screen-grade) | `casos_coverage` (contrato) | `d1_calculo` | Leitura |
|---|---|---|---|---|
| **Board** (kanban — maior exposição diária) | 86 · Leader | **89%** (UC 9 · ✅8 🧪1) | 🟡 agregado | **COERENTE** — Leader no visual **e** defendido no comportamento (o contraexemplo) |
| **Show** (Tier-0 — exibe o TOTAL da OS) | 76 · Advanced | **0%** (0 UC contratado) | 🔴 aplica | **contradição** — Advanced + fonte-da-verdade do valor, **sem contrato de comportamento** |
| **Edit** (monta a OS: itens → Total OS) | 73 · Advanced | **0%** | 🔴 aplica | **contradição** — Pest existe, mas falta `.casos.md` pra virar UC |
| **Create** (abertura da OS) | 66 · Developing | **0%** | n/a (não computa total) | **contradição** — funil sem contrato; UX mais baixa das 3 |

**A contradição honesta:** as 3 telas têm **Pest de sobra** (CRUD, itens-total, estoque, FSM, HTTP integration,
stage-gate, history), mas **nenhum `.casos.md`** por-tela — logo `cobertura_uc=0%` é de **contrato**, não de
código nu. A régua não tem UC pra citar. O `d1_calculo` marca 🔴 em Show/Edit porque o `final_total` de OS
mecânica ainda retorna R$ 0 (buraco conhecido [US-OFICINA-027](SPEC.md)) e não há property/golden/cross-check citável.

**Contraexemplo coerente — o Board (fotografado 2026-07-03):** o **Board** (`ServiceOrders/Board.tsx`, kanban de
produção, a tela de maior exposição diária no canary) é o **inverso** das 3 acima — **Leader 86 no visual E 89%
de comportamento** (`.casos.md` maduro: 9 UCs, ✅8 🧪1, verificados por e2e via manifesto G-7). Foi gradeado pelo
método `screen-grade` (16-dim LLM-as-judge sobre o código real, não nota à mão): zero cor crua (100% tokens DS +
primary roxo — fecha o gap que segurava o antigo `ProducaoOficina/Index` em 80), a11y (aria/atalhos de teclado/2ª-porta
por botão pro FSM), 4 views, no-mock. Gaps residuais no scorecard: `bg-white` cru em superfícies (vs `bg-card`),
densidade cognitiva de 6 KPIs+abas+toggles no 1280px, e sensor de teclado do dnd-kit a confirmar. `d1 🟡` porque o
KPI "Valor em curso" **exibe** um agregado (soma das OS não-terminais) que herda o buraco `final_total=0`
(US-OFICINA-027) — subestima, mas não é onde o cálculo vive. **A foto prova que a régua funciona nos dois sentidos:**
Show/Edit são premium-mas-indefesos (contradição), o Board é premium-e-defendido (coerência).

**Armadilha do casos-gate respeitada (G-2):** nenhum UC foi declarado em `.casos.md` nesta foto — os candidatos
ficam no `backlog` do scorecard (status implícito ⬜) e só ganham id de UC **no mesmo PR** que trouxer o `.casos.md`
+ citação do teste (o Pest que os defende já existe, então o PR de contrato é de baixo risco e não toca a tela).

---

## Fase 4 — App PWA campo + comissão + lembretes

> **Objetivo:** diferenciais UX (PWA mobile mecânico) + automações pós-venda + atrair 2º-5º piloto. Bloqueada até Martinho canary 30d sem incidente.

### Pré-requisitos

- [x] Fase 3 ATIVA (ADR 0171)
- [ ] Martinho canary 30d sem incidente
- [ ] Piloto valida demanda PWA campo (entrevista mecânicos — Frente 4 do plano comercial Wagner 2026-05-26 "discovery V1 cliente-driven")
- [ ] Centrifugo CT100 Push tested

### Entregas

| US | Descrição | Estimate | Prioridade |
|---|---|---:|---|
| **US-OFICINA-015** | PWA mecânico V0: minhas OS + foto antes/depois + clock-in/out + push transição | 16h | P1 |
| **US-OFICINA-019** | Comissão por OS (% mecânico + atendente + relatório mensal) | 8h | P1 |
| **US-OFICINA-016** | Lembrete garantia pré-vencimento (cron daily WhatsApp opt-in LGPD) | 3h | P2 |
| **US-OFICINA-021** | Integração FIPE veículo (valor mercado + cap garantia) | 4h | P2 |
| **US-OFICINA-020** | Importer Kanban estado UI Delphi (`WR_KANBAN` → `oa_kanban_state`) | 4h | P2 |
| **2º-3º piloto onboarding** | Migration Factory 1 piloto/mês até M3 ([ADR 0119](../../decisions/0119-paralelismo-sessoes-whats-active-tier-1.md)) — Vargas / Extreme / Gold / Zoom / Fixar / Produart | wallclock | P0 |
| **US-OFICINA-034** | CAPTERRA-FICHA recalibrada vs Auto Manager / Mecânico Tecnomotor / Plumelp / Sysmecânica | 4h | P2 |

**Subtotal: ~39h codáveis × 2 margem = ~78h IA-pair = ~3 semanas + 2 meses wallclock onboarding pilotos.**

### Critério de validação Fase 4 → Fase 5

- [ ] PWA testada Vargas/Martinho mecânicos (≥50% instalam e usam diariamente)
- [ ] Comissão calculada correto vs apuração paralela manual piloto (divergência ≤ 1%/mês)
- [ ] Lembrete garantia opt-in: ≥30% clientes aceitam, ≥10% retornam pra revisão
- [ ] 3+ pilotos ativos pagantes (receita ≥ R$ [redacted Tier 0]/m módulo — incluindo Martinho R$ [redacted Tier 0] + 2 novos)

---

## Fase 5 — Diferenciais ROI alto (Jana IA + NFSe + OEM se sinal)

> **Objetivo:** wedge competitivo dos 4 diferenciais únicos (IA + NFSe nacional + FIPE + PWA pro). Escalar 5+ pilotos.

### Pré-requisitos

- [x] Fase 4 done + 3+ pilotos ativos
- [ ] Modules/NFSe driver real entregue (1 município OU NFSe nacional NT 2024-001 modelo 56)
- [ ] Jana ContextSnapshotService hook `oficina_auto` validado (memória de marca/modelo populada)
- [ ] Mercado validou pricing R$ [redacted Tier 0]/399/799 com conversão ≥10% (ou pricing iterado pós-Martinho)

### Entregas

| US | Descrição | Estimate | Prioridade |
|---|---|---:|---|
| **US-OFICINA-018** | NFSe modelo 56 split documentos (NFe55 peça + NFSe servico) | 10h dev + wallclock SEFAZ/nacional | P1 |
| **US-AUTO-007** | Diagnóstico Jana IA (sintoma → hipóteses + tempário + LGPD disclaimer) | 16h | P1 |
| **US-OFICINA-022** | Cotação RFQ pra fornecedores múltiplos (`oa_cotacoes`) — vetor Tork PTO prospect | 8h | P2 |
| **App PWA V1** | Offline-first robusto + voz→texto + OBD-II read Bluetooth (se piloto frota) | 24h | P2 |
| **US-OFICINA-008-AUTO** | Catálogo peças OEM (SE sinal piloto Pro tier exigir + parceria fornecedor) | 40h | P3 condicional |
| **5º piloto onboarding** | Migration Factory rolling | wallclock | P0 |

**Subtotal: ~58h codáveis × 2 margem = ~116h IA-pair = ~6 semanas + wallclock SEFAZ/nacional + onboarding pilotos paralelos.**

### Critério de validação Fase 5 → ESCALA

- [ ] NFSe auto funcionando ≥ 1 município OU nacional NT 2024-001 modelo 56 com piloto (wedge #1 vs Ultracar)
- [ ] Jana diagnóstico usado ≥10x/m por piloto sem disclaimer reclamado
- [ ] 5+ pilotos ativos pagantes (ARR ≥ R$ [redacted Tier 0]k/ano módulo)
- [ ] NPS ≥ 50 pilotos
- [ ] Custo suporte ≤ 15% receita módulo

---

## Onda 1.4 — Dente de cálculo (OS) · governança executável · TEST-ONLY

> Camada aditiva do [Programa de Ondas](../_Governanca/programa-ondas/onda-1-sells/1.4-dente-calculo.md) aplicada ao coração da **ordem de serviço** de reparo. **NÃO** é fase nova nem doc paralelo — é o dente da dimensão D1 (cálculo de valor) plugado no cálculo PRÓPRIO da OS, registrado aqui pra não fragmentar (gate T6: 1 tema = 1 doc). **Seguro pro canary Martinho (biz=164 LIVE, [ADR 0171](../../decisions/0171-oficinaauto-ativacao-piloto-martinho-faseada.md)):** só adiciona testes, não toca cálculo.

**Contexto:** a [REGRA MESTRE](../../proibicoes.md) (dupla confirmação MANUAL de qualquer mudança de valor/estoque) é controle humano que não sobrevive ao tempo. Este dente converte esse controle em **automático** no cálculo da OS — o mesmo motivo que existe pro Sells em [1.4-dente-calculo.md](../_Governanca/programa-ondas/onda-1-sells/1.4-dente-calculo.md), agora pro reparo. Ancora o risco vivo já catalogado nesta ROADMAP (`final_total=0` / recalc `peça×qty + hora×horas`, US-OFICINA-027).

**Alvos (o cálculo próprio da OS — não duplica venda #3695 / financeiro #3710 / fiscal):**

| Método | Arquivo | Contrato defendido |
|---|---|---|
| `ServiceOrderItemService::recalcularTotal` | [Services/ServiceOrderItemService.php](../../../Modules/OficinaAuto/Services/ServiceOrderItemService.php) | total OS == `round(Σ valor_total, 2)`, sem centavo perdido |
| `ServiceOrderItemService::breakdownPorTipo` | idem | partição peça+mão+terceiro == total (dinheiro não migra de categoria) |
| `ServiceOrderItemService::addItem` | idem | `valor_total = round(qty × unit, 2)`; override de desconto flui sem inflar |
| `DviInspectionService::totalRecomendado` | [Services/DviInspectionService.php](../../../Modules/OficinaAuto/Services/DviInspectionService.php) | orçamento recomendado = Σ `valor_recomendado` só de {atenção,crítico} |

**Nota de domínio:** a OS **não tem campo de desconto** ([ADR 0194](../../decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md)/[0265](../../decisions/0265-oficina-reparo-erradica-locacao.md)); desconto entra como override de `valor_total` por item, depois somado. O cálculo casta `(float)` direto (**não** passa por `num_uf`) — por isso o golden de "não inflar" é **sentinela de regressão** (pega o dia em que alguém rotear esse valor por um parser pt-BR ou trocar `round` por strip), não a reprodução de um bug atual.

**Teste:** [`tests/Feature/Calculo/CalculoValorOficinaAutoTest.php`](../../../tests/Feature/Calculo/CalculoValorOficinaAutoTest.php) — property (conservação + partição + filtro-severidade fuzzed, seed fixa) + golden (0,10×3==0,30; qty×unit; override não-infla) + discriminação RED (mutantes floor/cast-int que perdem centavo divergem do real) + guard Tier 0 cross-tenant. Contrato ancorado FORA do código (conservação de dinheiro / filtro DVI), não no que a classe faz hoje — anti-tautologia ([proibicoes.md §"Teste que deriva do CÓDIGO"](../../proibicoes.md)).

**Critério de pronto:** green no código atual · red por construção contra floor/cast-int/strip (golden = fronteira) · divergência de somadores documentada · **rodado no CT100** (nunca local/Hostinger).

```bash
tailscale ssh root@ct100-mcp \
  "docker exec -e DB_CONNECTION=mysql oimpresso-staging php artisan test --filter=CalculoValorOficinaAuto"
```

> ⛔ Unificar/alterar qualquer somador acima = valor em prod → **US separada sob REGRA MESTRE** (dupla confirmação + antes→depois + OK [W]). Nunca pega carona no PR do teste.

---

## Métricas convergentes (M0-M12 pós-ativação ADR 0171)

> **M0 = 2026-05-20** (ADR 0171 ativação Martinho). **M1 = 2026-06-20**, etc.

| Métrica | M0 estado atual (2026-05-26) | M1-M3 (Fase 3 done) | M6 (Fase 4 done) | M12 (Fase 5 done) | Crítica |
|---|---|---|---|---|---|
| Clientes pagantes Modules/OficinaAuto | **1** (Martinho biz=164 ✅) | 1-2 | 3 | **5-10** | <3 = reavaliar tese |
| ARR módulo (R$/ano) | **R$ [redacted Tier 0]k** (R$ [redacted Tier 0] × 12 grandfathered + WhatsApp beta 30d) | R$ [redacted Tier 0]-15k | R$ [redacted Tier 0]-50k | **R$ [redacted Tier 0]-120k** | <R$ [redacted Tier 0]k = pivotar |
| US entregues (de ~34 totais incl 027-034) | **~18** (Fase 0+1 done · Fase 2 FSM done · Fase 3 ATIVA + auto-faturar + NFSe fix) | 22 (Sem 22-23 done) | 28 (Fase 4) | **34** (Fase 5) | <22 M3 = stack mal calibrado |
| Cases públicos clicáveis | 0 (Martinho não autorizou ainda — Frente 1 testemunhal pendente pós-canary 30d) | 1 (Martinho testemunhal) | 1-2 | **2-3** | (transparência radical) |
| NFSe funcionando | ✅ tela LIVE 2026-05-26 (PR #1597 Caminho A) — pendente smoke real biz=164 | tela funcional | NFSe auto ≥ 1 município | **NFSe auto nacional modelo 56** | (wedge #1 vs Ultracar) |
| Bug crítico produção | NFSe 500 fix LIVE · BG7 sev2 investigação em curso | <1/mês | <1/mês | <1/trimestre | (Pest gate ADR 0094) |
| Churn módulo (Martinho) | 0% (5d desde ativação) | <5% 90d | <5%/m | <8%/ano | (review trigger ADR 0121) |

**Convergente com [ADR 0022](../../decisions/0022-meta-5mi-ano-financeira.md):** Modules/OficinaAuto contribui R$ [redacted Tier 0]-120k ARR de R$ [redacted Tier 0]M total no M12 pós-ativação (1,2%-2,4%) — recalibrado vs estimate original R$ [redacted Tier 0]-72k (Martinho grandfathered + add-on WhatsApp R$ [redacted Tier 0]/inst escala mais rápido que pricing tier Auto Pro original). Multi-vertical é tese — oficina diversifica, não substitui Vestuario (LIVE ROTA LIVRE) / ComVis (em construção).

## Riscos críticos roadmap (atualizado 2026-05-26)

| Risco | Probabilidade | Impacto | Mitigação |
|---|---|---|---|
| **Martinho churn antes 90d pós-ativação** | Baixa (engajamento alto: feedback 2026-05-26 *"placa Mercosul + design Oficina ficou top"*) | Alto (R$ [redacted Tier 0]/m + sinal mercado) | Frente 4 discovery V1 cliente-driven · suporte assistido equipe Wagner + Felipe + Maiara |
| **BG7 Tier 0 sev2 confirmado** (Admin#164 superadmin) | Média (achado documentado · investigação concluída [memory/sessions/2026-05-26-bg7-tier0-admin-superadmin-investigation.md](../../sessions/2026-05-26-bg7-tier0-admin-superadmin-investigation.md)) | Crítico se sev1 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) IRREVOGÁVEL) | Wagner roda query SQL prod ~2min · se confirmar revoga permission tinker ~30s · scan batch outros businesses · ADR 0193-bis errata se epidemia |
| **`final_total=0` em OS mecânica bloqueia cobrança real** | Alta (LIVE prod hoje — Wagner edita manual cada OS) | Médio (Martinho operacional mas cobrança não-automática) | US-OFICINA-027 P0 8h catálogo peça hidráulica V0 + recalc `peça×qty + hora×horas` (Sem 23) |
| **`/fiscal/nfse` 500** (resolved 2026-05-26) | — | Médio | ✅ Fix Caminho A LIVE PR #1597 — schema race resolvido reverter Controller pro schema velho prod |
| **Vargas não fecha como 2º piloto** | Média (sinal médio recapagem) | Médio (1.064 veículos = caso real validador sub-vertical 2) | Cenário modificado — Martinho 1º (sub-vertical 4 ✅), Vargas 2º (sub-vertical 2 V1); se Vargas não fechar, Extreme/Gold/Zoom/Fixar/Produart 6 candidatos OfficeImpresso saudáveis |
| **NFSe driver municipal nunca verde** | Média (10 US backlog, 0 implementados pro modelo 56 nacional NT 2024-001) | Médio (bloqueia wedge fiscal vs Ultracar) | Fase 5 condicional · piloto Martinho NFC-e-only enquanto isso (auto-faturar OS→Venda LIVE) |
| **ComunicacaoVisual regressão -3pp** (2026-05-26) | Confirmada (baseline v3.4.3 registra) | Baixo (módulo não tocado neste cycle) | Próximo PR que tocar ComVis investiga D-dimensions (D2/D6.a/D4 prováveis) + abre US-COMVIS-NNN P2 |
| **Re-trabalho FSM se ADR 0143 evoluir** | Baixa (ADR canon LIVE prod) | Médio | Wire-up via caminho 2 isola — mudança engine afeta Sells/Repair simultâneo, OficinaAuto herda |
| **App PWA iOS Safari quirks** | Média (Fase 4) | Baixo (Android cobre maioria mecânicos BR) | Validar Vargas Android primeiro; iOS opcional V1 |
| **Cleanup tools migração causa data loss** | Baixa (dry-run obrigatório + canary) | Crítico | Sempre dry-run + Wagner aprova batch antes apply; rollback via Firebird backup |
| **Capacidade time** | Alta (5 pessoas com Vestuario LIVE + ComVis paralelo + Jana sprint + Martinho ativo) | Alto (atrasa Fase 2-3 finalização) | Sem 22-23 prioridade absoluta Martinho-ready (levantamento 2026-05-26) · paralelo Felipe cleanup tools + Wagner US-027 catálogo peça hidráulica |

## Refs

- [SPEC OficinaAuto](SPEC.md) — base canônica US-OFICINA-001..034
- [BRIEFING OficinaAuto](BRIEFING.md) — estado consolidado pós-ADR 0194 (reescrito 2026-05-26 PR #1598)
- [RUNBOOK-migracao-cliente-legacy](RUNBOOK-migracao-cliente-legacy.md) — onboarding (pendente reescrita US-OFICINA-028 parte 3/5)
- [Levantamento Martinho-ready 2026-05-26](../../sessions/2026-05-26-levantamento-martinho-ready.md) — checklist P0-P3 + roadmap 2 semanas
- [ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0105 — Cliente como sinal qualificado](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — gating Fase 3+
- [ADR 0106 — Estimates IA-pair fator 10x](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)
- [ADR 0119 — Migration Factory rolling](../../decisions/0119-paralelismo-sessoes-whats-active-tier-1.md)
- [ADR 0121 — Modular vertical](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md)
- [ADR 0137 — OficinaAuto qualificada](../../decisions/0137-modules-oficinaauto-qualificada.md) (amendado por 0194)
- [ADR 0143 — FSM canon LIVE prod](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
- [ADR 0171 — Ativação piloto Martinho faseada](../../decisions/0171-oficinaauto-ativacao-piloto-martinho-faseada.md) (amendado por 0194)
- [ADR 0192 — Auto-faturar OS→Venda Observer](../../decisions/0192-auto-faturar-os-venda-jobsheet-observer.md)
- [ADR 0194 — Correção domínio mecânica pesada](../../decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md) — **2026-05-26**
- [Investigação BG7 sev2 2026-05-26](../../sessions/2026-05-26-bg7-tier0-admin-superadmin-investigation.md)
- [Matriz ROI](MATRIZ-ROI.md)
- [Research mercado oficinas BR 2026-05](../../research/2026-05-prospeccao-auto/01-mercado-oficinas-auto-br.md)
- [Vargas perfil](../../research/clientes-legacy-officeimpresso/02-vargas-recapagem/01-perfil.md) — sub-vertical 2 V1
- [Martinho perfil](../../research/clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md) — sub-vertical 4 LIVE biz=164
- [Tork PTO prospect](../../research/clientes-prospect/tork-tomadas-forca/01-perfil.md) — cadeia comercial 2026-05-26
- [Dicionário domínios verticais](../../reference/dominios-verticais-oimpresso.md) — vocabulário canon

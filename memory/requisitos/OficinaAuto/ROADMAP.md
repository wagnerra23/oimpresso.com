---
module: OficinaAuto
artefato: roadmap
status: draft (discovery 2026-05-12, aguarda Wagner)
purpose: roadmap fase-a-fase pós V0 scaffold DONE (PR #556) até diferenciais ROI alto
fases: 6 (0 done + 1-5 propostas)
ultima_atualizacao: 2026-05-12
recalibracao: ADR 0106 (fator 10x IA-pair + margem 2x)
---

# Roadmap — Modules/OficinaAuto

> Espelha formato Roadmap ComVis. Estimates ADR 0106 (codáveis-com-IA × 2 margem; humano-limitado relógio real).
>
> **Gating cliente:** cada fase requer satisfação de critério antes de avançar. Sem cliente piloto pagante até Fase 2 = violação ADR 0105 (cliente sinal). Fases 3+ podem ativar paralelas se múltiplos pilotos.

## Visão geral fases

| Fase | Foco | Estimate IA-pair | Wallclock | Critério ativação | Status |
|---|---|---:|---|---|---|
| **0** | V0 scaffold + Pest baseline | — | — | — | ✅ **DONE** (PR #556) |
| **1** | Importer Vargas/Martinho + smoke biz=4 | 70h | ~3 sem | Fase 0 verde | 🟡 ready |
| **2** | FSM wire-up + UI drawer FsmActionPanel + diferenciais vertical | 68h | ~3 sem | Fase 1 verde + Martinho importado canary | 🔒 |
| **3** | Bulk migration Vargas + 1º piloto pagante | 30h dev + wallclock | ~4 sem | Fase 2 verde + ADR ativação `OficinaAuto-ativacao` (1 piloto Cenário A do SPEC §9.1) | 🔒 |
| **4** | App PWA campo mecânico + comissão + lembretes | 62h | ~3 sem | Fase 3 verde + 1+ piloto canary 30d sem incidente | 🔒 |
| **5** | Diferenciais ROI alto (Jana IA, FIPE, OEM se sinal) | 80h+ | ~6 sem | Fase 4 verde + 3+ pilotos ativos + receita ≥ R$ 5k/m módulo | 🔒 |

**Total fundação Fase 1-2 = ~138h IA-pair = ~6 semanas Felipe focal.**

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

**Pendente Wagner:** `php artisan test --filter=OficinaAuto` local + decisão naming `vehicles` vs `oa_vehicles` antes de Fase 1.

---

## Fase 1 — Importer + smoke biz=4 (Martinho primeiro)

> **Objetivo:** preparar fundação pra migração real cliente legacy + validar schema com dados reais Martinho (mais simples) antes de tocar Vargas (mais complexo). Sem FSM ainda — só CRUD + importer.

### Pré-requisitos

- [x] Fase 0 done
- [ ] Wagner aprovou rename `vehicles` → `oa_vehicles` (ADR proposal D2)
- [ ] Wagner sign-off matriz ROI top 5

### Entregas

| US | Descrição | Estimate | Prioridade |
|---|---|---:|---|
| **rename V0** | Migration `rename_vehicles_to_oa_vehicles_and_service_orders_to_oa_service_orders` (1h) + atualiza Models + Controllers + 8 Pages + 16 Pest | 1h | Pre-req |
| **US-OFICINA-002** | Importer Firebird `EQUIPAMENTO_VEICULO` → `oa_vehicles` (Martinho 91 veículos) | 4h | P0 |
| **US-OFICINA-009** | Defeitos múltiplos JSON array em `oa_service_orders` | 3h | P0 |
| **US-OFICINA-005a** | Tela "Revisão pendências legadas" (batch UI Martinho cleanup) | 6h | P0 ROI imediato |
| **US-OFICINA-005b** | Conciliação VENDA↔FINANCEIRO detectar drift | 4h | P0 |
| **US-OFICINA-005c** | PESSOAS deduplicador fuzzy match | 2h | P0 |

**Subtotal: ~20h codáveis × 2 margem = ~40h Felipe = ~2 semanas IA-pair.**

### Critério de validação Fase 1 → Fase 2

- [ ] Pest filter OficinaAuto 100% verde local + CI
- [ ] Importer Martinho rodado smoke biz=1: 91/91 veículos imported sem erro
- [ ] Cleanup tools UI testada Wagner aprovou screenshots
- [ ] Smoke biz=4 (ROTA LIVRE) sem regressão — usar 1 veículo fake
- [ ] Renaming `oa_*` consolidado em prod + zero referências legacy

---

## Fase 2 — FSM wire-up + diferenciais vertical (Vargas-ready)

> **Objetivo:** governance-align (FSM canon ADR 0143) + features oficina-específicas calibradas Vargas (multi-placa real + teste-estrada + re-orçamento). Pré-pré-piloto.

### Pré-requisitos

- [x] Fase 1 done
- [ ] Wagner aprovou ADR proposal `oficina-auto-modulo-canonico-fsm-wireup.md` (status `accepted`)
- [ ] Modules/Repair FSM wire-up done (espelhar pattern)

### Entregas

| US | Descrição | Estimate | Prioridade |
|---|---|---:|---|
| **US-OFICINA-006** | FSM wire-up: coluna `current_stage_id` + trait + seeder 15 stages + Controller + UI drawer | 6h | P0 |
| **US-OFICINA-007** | Importer Vargas multi-placa (1.064 veículos com PLACA+PLACA2+CHASSI2) | 8h | P0 |
| **US-OFICINA-008** | Schema garantia granular per-item (`oa_pecas_utilizadas` + `oa_servicos_executados` + `oa_garantias`) | 5h | P0 |
| **US-OFICINA-010** | Stages oficina-específicos: `teste_estrada` + `ajuste_final` + loop UI | 4h | P0 |
| **US-OFICINA-011** | Re-orçamento (action `escalar_supervisor` + flag `aprovado_apos_aumento`) | 4h | P1 |
| **US-OFICINA-014** | Aprovação OS via WhatsApp link público + PIN | 7h | P0 |

**Subtotal: ~34h codáveis × 2 margem = ~68h Felipe = ~3 semanas IA-pair.**

### Critério de validação Fase 2 → Fase 3

- [ ] FSM 15 stages canônicos pestado biz=1 + biz=99 cross-tenant guard
- [ ] Vargas importer canary biz=1 SEM SMOKE PROD: 1.064/1.064 veículos imported, 216 com placa secundária correta, 88 com chassi secundário
- [ ] UI drawer FsmActionPanel reuso Modules/Repair funcionando OficinaAuto
- [ ] WhatsApp aprovação link testado: ROTA LIVRE fake cliente recebe + clica + aprova + FSM action dispara + side-effect `ReservarEstoque` rastreado em `sale_stage_history`
- [ ] Teste-estrada loop pestado: OS rodada 3 iterações (concluir_execucao → precisa_ajuste → ajuste_concluido) com timeline correta

---

## Fase 3 — 1º piloto pagante + bulk migration + canary

> **Objetivo:** ATIVAÇÃO produção. Requer ADR canon `OficinaAuto-ativacao` (ADR 0105 gatilho).

### Pré-requisitos (HARD GATING)

- [x] Fase 2 done
- [ ] **Sinal qualificado ADR 0105 satisfeito**:
  - Cenário A: 1 oficina assina contrato Auto Pro R$ 399/m × 3m upfront (R$ 1.197 antecipado) — preferido
  - OU Cenário C: 2+ leads inbound oficina (cross-sell ComVis) com 1 fechando
- [ ] ADR canon `NNNN-oficina-auto-ativacao-vertical.md` accepted (Wagner [W] + Felipe [F] revisão)
- [ ] Tempário seed 100 serviços manual ready (US-OFICINA-013)

### Entregas

| US | Descrição | Estimate | Prioridade |
|---|---|---:|---|
| **US-OFICINA-013** | Tabela tempária seed (100 serviços comuns BR + UI CRUD) | 5h | P1 |
| **US-OFICINA-005-bulk** | Cleanup tools bulk apply (200 pendências/dia × 23 dias batch) — escalar US-005 | 4h | P0 |
| **fsm:bulk-start-pipeline-oficina** | Comando bulk migrate OS legadas → FSM stages | 3h | P0 |
| **US-OFICINA-012** | Consulta CRLV/Renavam (cache 30d, adapter pluggable) | 6h | P1 |
| **US-OFICINA-017** | Histórico veículo timeline (Page Show + aba histórico + km diff) | 4h | P1 |
| **Canary 7d + onboarding piloto** | Operação assistida com cliente piloto, daily check, fix incident | wallclock 1 semana | P0 |
| **Documentação onboarding cliente** | RUNBOOK migração oficina X → oimpresso (8 peças: cadastros + OS abertas + boletos + colaboradores + tempário + permissions + Spatie roles + smoke real) | 4h | P0 |

**Subtotal: ~26h codáveis × 2 margem = ~52h Felipe + ~1 semana wallclock canary = ~4 semanas wallclock.**

### Critério de validação Fase 3 → Fase 4

- [ ] 1 piloto canary 7 dias sem incidente sev1/sev2
- [ ] Piloto reporta semanal bugs/features (compromisso ADR 0105)
- [ ] NFC-e auto a partir de boleto pago funcionando ponta-a-ponta piloto (não bloqueado por NFSe ainda)
- [ ] Cleanup tools bulk: relatório piloto "X pendências canceladas / Y write-off / Z renegociadas" com aprovação dono
- [ ] Receita reportada R$ 399/m × 3 meses upfront cobrado (R$ 1.197+ rec na Modules/RecurringBilling)

---

## Fase 4 — App PWA campo + comissão + lembretes

> **Objetivo:** diferenciais UX (PWA mobile mecânico) + automações pós-venda + atrair 2º-5º piloto.

### Pré-requisitos

- [x] Fase 3 done + 1 piloto canary 30d sem incidente
- [ ] Piloto valida demanda PWA campo (entrevista mecânicos)
- [ ] Centrifugo CT100 Push tested

### Entregas

| US | Descrição | Estimate | Prioridade |
|---|---|---:|---|
| **US-OFICINA-015** | PWA mecânico V0: minhas OS + foto antes/depois + clock-in/out + push transição | 16h | P1 |
| **US-OFICINA-019** | Comissão por OS (% mecânico + atendente + relatório mensal) | 8h | P1 |
| **US-OFICINA-016** | Lembrete garantia pré-vencimento (cron daily WhatsApp opt-in LGPD) | 3h | P2 |
| **US-OFICINA-021** | Integração FIPE veículo (valor mercado + cap garantia) | 4h | P2 |
| **US-OFICINA-020** | Importer Kanban estado UI Delphi (`WR_KANBAN` → `oa_kanban_state`) | 4h | P2 |
| **2º-3º piloto onboarding** | Migration Factory 1 piloto/mês até M3 (ADR 0119) | wallclock | P0 |

**Subtotal: ~35h codáveis × 2 margem = ~70h Felipe = ~3 semanas IA-pair + 2 meses wallclock onboarding pilotos.**

### Critério de validação Fase 4 → Fase 5

- [ ] PWA testada Vargas/Martinho mecânicos (≥50% instalam e usam diariamente)
- [ ] Comissão calculada correto vs apuração paralela manual piloto (divergência ≤ 1%/mês)
- [ ] Lembrete garantia opt-in: ≥30% clientes aceitam, ≥10% retornam pra revisão
- [ ] 3+ pilotos ativos pagantes (receita ≥ R$ 1.500/m módulo)

---

## Fase 5 — Diferenciais ROI alto (Jana IA + NFSe + OEM se sinal)

> **Objetivo:** wedge competitivo dos 4 diferenciais únicos (IA + NFSe + FIPE + PWA pro). Escalar 5+ pilotos.

### Pré-requisitos

- [x] Fase 4 done + 3+ pilotos ativos
- [ ] Modules/NFSe driver real entregue (1 município) — destrava US-OFICINA-018
- [ ] Jana ContextSnapshotService hook `oficina_auto` validado (memória de marca/modelo populada)
- [ ] Mercado validou pricing R$ 199/399/799 com conversão ≥10%

### Entregas

| US | Descrição | Estimate | Prioridade |
|---|---|---:|---|
| **US-OFICINA-018** | NFSe modelo 56 split documentos (NFe55 peça + NFSe servico) | 10h dev + wallclock SEFAZ | P1 |
| **US-AUTO-007** | Diagnóstico Jana IA (sintoma → hipóteses + tempário + LGPD disclaimer) | 16h | P1 |
| **US-OFICINA-022** | Cotação RFQ pra fornecedores múltiplos (`oa_cotacoes`) | 8h | P2 |
| **App PWA V1** | Offline-first robusto + voz→texto + OBD-II read Bluetooth (se piloto frota) | 24h | P2 |
| **US-OFICINA-008-AUTO** | Catálogo peças OEM (SE sinal piloto Pro tier exigir + parceria fornecedor) | 40h | P3 condicional |
| **5º piloto onboarding** | Migration Factory rolling | wallclock | P0 |

**Subtotal: ~58h codáveis × 2 margem = ~116h Felipe = ~6 semanas IA-pair + wallclock SEFAZ NFSe + onboarding pilotos paralelos.**

### Critério de validação Fase 5 → ESCALA

- [ ] NFSe auto funcionando ≥ 1 município com piloto (wedge #1 vs Ultracar)
- [ ] Jana diagnóstico usado ≥10x/m por piloto sem disclaimer reclamado
- [ ] 5+ pilotos ativos pagantes (ARR ≥ R$ 24k/ano módulo)
- [ ] NPS ≥ 50 pilotos
- [ ] Custo suporte ≤ 15% receita módulo

---

## Métricas convergentes (M0-M12 pós-ativação)

| Métrica | M0 (Fase 3 ativa) | M6 (Fase 4 done) | M12 (Fase 5 done) | Crítica |
|---|---|---|---|---|
| Clientes pagantes Modules/OficinaAuto | 1 (piloto) | 3 | **5-10** | <3 = reavaliar tese |
| ARR módulo (R$/ano) | R$ 4,8k | R$ 14k | **R$ 24-72k** | <R$ 12k = pivotar |
| US entregues (de ~22 totais) | 8 (Fase 1-2) | 14 (Fase 3-4) | **19** (Fase 5) | <14 = stack mal calibrado |
| Cases públicos clicáveis | 0 | 1 (piloto) | **2-3** | (transparência radical) |
| NFSe auto ≥ 1 município | n/a | n/a | **sim** | (wedge #1 vs Ultracar) |
| Bug crítico produção | n/a | <1/mês | <1/trimestre | (Pest gate ADR 0094) |
| Churn módulo | n/a | <5%/m | <8%/ano | (review trigger ADR 0121) |

**Convergente com [ADR 0022](../../decisions/0022-meta-5mi-ano-financeira.md):** Modules/OficinaAuto contribui R$ 24-72k ARR de R$ 5M total no M12 pós-ativação (0,5%-1,5%). Multi-vertical é tese — oficina diversifica, não substitui ComVis/Vestuário.

## Riscos críticos roadmap

| Risco | Probabilidade | Impacto | Mitigação |
|---|---|---|---|
| **Vargas não fecha como piloto** | Média (sinal médio, sem reclamação explícita) | Alto (1.064 veículos = caso real validador) | Cenário B sinal: Martinho fecha primeiro (mais simples); Vargas vira 2º piloto |
| **NFSe driver municipal nunca verde** | Alta (10 US backlog, 0 implementados) | Médio (bloqueia wedge fiscal vs Ultracar) | Fase 5 condicional; piloto Cenário A NFC-e-only enquanto isso |
| **Re-trabalho FSM se ADR 0143 evoluir** | Baixa (ADR canon LIVE prod) | Médio | Wire-up via caminho 2 isola — mudança engine afeta Sells/Repair simultâneo, OficinaAuto herda |
| **App PWA iOS Safari quirks** | Média | Baixo (Android cobre maioria mecânicos BR) | Validar Vargas Android primeiro; iOS opcional V1 |
| **Cleanup tools migração causa data loss** | Baixa (dry-run obrigatório + canary) | Crítico | Sempre dry-run + Wagner aprova batch antes apply; rollback via Firebird backup |
| **Capacidade time** | Alta (5 pessoas com Vestuario LIVE + ComVis paralelo + Jana sprint) | Alto (atrasa Fase 1-2) | Ativar pós-ComVis 2º piloto (M6 dez/2026 estimado); rolling 1 vertical/trimestre |

## Refs

- [SPEC OficinaAuto §14-§18 + §V0](SPEC.md) — base canônica
- [ADR 0137 OficinaAuto qualificada](../../decisions/0137-modules-oficinaauto-qualificada.md)
- [ADR 0143 FSM canon LIVE](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
- [ADR 0105 cliente sinal qualificado](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — gating Fase 3+
- [ADR 0106 estimates IA-pair](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)
- [ADR 0121 modular vertical](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md)
- [ADR 0119 Migration Factory](../../decisions/0119-paralelismo-sessoes-whats-active-tier-1.md)
- [Proposal FSM wire-up](../../decisions/proposals/drafts/oficina-auto-modulo-canonico-fsm-wireup.md)
- [Matriz ROI](MATRIZ-ROI.md)
- [Research mercado oficinas BR 2026-05](../../research/2026-05-prospeccao-auto/01-mercado-oficinas-auto-br.md)
- [Vargas perfil](../../research/clientes-legacy-officeimpresso/02-vargas-recapagem/01-perfil.md)
- [Martinho perfil](../../research/clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md)

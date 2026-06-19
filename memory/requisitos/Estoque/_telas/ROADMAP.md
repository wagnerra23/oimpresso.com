---
modulo: Inventory (cross-vertical)
status: proposed
created_at: 2026-05-12
spec_ref: SPEC.md
matriz_roi_ref: MATRIZ-ROI.md
adr_ref: ../../decisions/proposals/drafts/inventory-avancado-kits-batch-dimensional.md
---

# ROADMAP Inventory — 5 fases × ~9 semanas full-focus

> Plano de execução faseado. Cada fase entrega valor incremental + tem gate de sinal qualificado ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)) antes de avançar.
>
> Estimates IA-pair recalibrados ([ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)) com margem 2× pra tarefas humano-limitadas (canary, smoke, contador Eliana[E] revisão NFe).

---

## §1 Sequenciamento + dependências

```
F1 Kits/BOM ──┬─→ F2 Batch tracking ──┬─→ F3 Dimensional ──┐
              │                       │                    │
              │                       └─→ F4 Movements ────┴─→ F5 Negative/FEFO/Analytics
              │                          unified (paraleliza com F3)
              │
              └─→ pode iniciar isolado SE só Vargas sinal qualificado (sem ComVis)
```

**Gate F1→F2:** Vargas usando kit bomba em prod ≥ 30 dias sem regressão biz=1.
**Gate F2→F3:** ≥ 2 ComVis candidatos saudáveis assinaram pioneer.
**Gate F3→F4:** F3 fechou + custo-real-OS funcionando ComVis ≥ 30 dias.
**Gate F4→F5:** stock_movements estável + reconcile drift = 0 por 60 dias.

---

## §2 Fase 1 — Kits/BOM fundação (Sem 1-2)

**Goal:** Vargas vende "kit bomba VW Gol" em produção biz=1 com baixa cascateada correta + NFe configurável.

**Pré-requisito ativação ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)):** Vargas assinou contrato pioneer OficinaAuto.

### Entregas
- **US-INV-001** Schema `product_bom` + `product_kits` + flags business (6h)
- **US-INV-002** UI Inertia BOM drag-drop componentes (12h)
- **US-INV-003** ReservarEstoque v2 multi-level recursive (8h)
- **US-INV-004** ConsumirEstoque + LiberarReserva v2 cascade (6h)
- **US-INV-005** NFe55 kit strategy per produto (8h) — **bloqueado por D4 Wagner + Eliana[E] contador**

### Esforço
- Total: 40h IA-pair · margem 2× = 80h ≈ 2 semanas full-focus

### Gates qualidade
- ✅ Pest cross-tenant biz=1 vs biz=99 (BusinessIdGuard)
- ✅ Pest BOM multi-level (8 casos: simples, multi-level, opcional, substituição, batch_id, decimal qty, FK validation, recursion guard ≤5)
- ✅ Pest backward compat: `combo_variations` JSON legacy ainda funciona quando `business.enable_bom=false`
- ✅ Smoke biz=1 (Wagner manual) + canary 7d ROTA LIVRE biz=4 NÃO habilita
- ✅ Backfill reconciliation job dual-write combo↔product_bom (3 meses transition)
- ✅ Contador Eliana[E] aprovou estratégia NFe `linhas_componentes` default (D4)

### Métricas de sucesso
- 1 venda kit bomba em prod biz Vargas ≥ 30 dias sem regressão estoque
- Zero alerta `inventory:reconcile drift` em logs
- Tempo cadastro 1 BOM ≤ 5min (UI ergonômico)

---

## §3 Fase 2 — Batch tracking (Sem 3-4)

**Goal:** ComVis Extreme rastreia rolos Mimaki por lote (cor-Pantone) + AutoPeças Vargas rastreia Mahle pra RMA fornecedor.

**Pré-requisito ativação:** Fase 1 OK + ≥ 1 ComVis pioneer assinou OU AutoPeças hipótese confirmada Vargas.

### Entregas
- **US-INV-006** Schema `product_batches` + flags + FK purchase_lines (6h)
- **US-INV-007** RegistrarEntradaBatch side-effect (FSM purchase) (6h)
- **US-INV-008** UI Inventory/Batches listagem + filtros (10h)
- **US-INV-009** Batch picker em PDV/OS + FEFO opcional (12h) — **diferencial cor-Pantone ComVis**
- **US-INV-010** Lookup garantia fornecedor (batch → clientes) (4h) — **bloqueado por US-INV-016 stock_movements**

### Esforço
- Total: 38h IA-pair · margem 2× = 76h ≈ 2 semanas full-focus

### Gates qualidade
- ✅ Pest batch resolver FEFO vs manual
- ✅ Pest decremento qty_current preserva qty_available (não double-decrement)
- ✅ Smoke ComVis pioneer: cria batch a partir NFe entrada + consume em OS + check rastreio retro
- ✅ XML NFe entrada extrai lot_number → product_batches automatic (verificar com 3 XMLs reais Mimaki)

### Métricas de sucesso
- 100% NFe entrada ComVis cria batch row
- ≥ 1 lookup garantia executado (batch → clientes) em prod ComVis ≥ 60 dias
- UI batch picker ≤ 3 cliques pra escolher lote em OS

---

## §4 Fase 3 — Dimensional (Sem 5-6)

**Goal:** ComVis Extreme fecha OS com custo-real calculado (lona m² + tinta ml + MOD) — diferencial #1 vs Mubisys/Zênite.

**Pré-requisito ativação:** Fase 2 OK + ComVis pioneer rodando ≥ 30 dias.

### Entregas
- **US-INV-011** `products.base_unit_id_inv` separado venda unit (4h)
- **US-INV-012** UI custo per ml/kg/m² + cálculo custo real OS (10h) — **diferencial mercado**
- **US-INV-013** Alerta cartucho/rolo baixo % (4h)
- **US-INV-014** Apontamento máquina decrementa batch ml automatic (6h)
- **US-INV-015** Conversão unit automática (4h)

### Esforço
- Total: 28h IA-pair · margem 2× = 56h ≈ 1.5 semanas

### Gates qualidade
- ✅ Pest custo real OS: lona 510g 1.6m × 9.4m + tinta CMYK 50ml = R$ calculado preciso
- ✅ Pest decremento batch ml em apontamento (hook US-COMVIS-004)
- ✅ UI cartucho gauge visual (% restante) — gate visual mwart-comparative

### Métricas de sucesso
- ≥ 10 OS ComVis fechadas com custo real automatic
- Alerta cartucho prevenc parou plotter ≥ 1× em prod (case real)
- Erro custo real ≤ 5% vs cálculo manual contador

---

## §5 Fase 4 — Stock Movements unified (Sem 5-7, paraleliza F3)

**Goal:** Single source of truth audit trail estoque pra TODOS clientes (compliance + analytics base).

**Pré-requisito ativação:** Fase 1 OK (não depende F2/F3 conceitualmente, mas paraleliza).

### Entregas
- **US-INV-016** Schema `stock_movements` + triggers MySQL append-only (6h)
- **US-INV-017** Backfill incremental UPos legacy (12h) — **maintenance window biz=1, biz=4**
- **US-INV-018** Hook automático side-effects FSM registra movements (4h)
- **US-INV-019** UI relatório movements per produto/batch/período (8h)
- **US-INV-020** Comando `inventory:reconcile` daily + jana:health-check (4h)

### Esforço
- Total: 34h IA-pair · margem 2× = 68h ≈ 2 semanas

### Gates qualidade
- ✅ Triggers MySQL BEFORE UPDATE/BEFORE DELETE raise erro (testar staging com 100k rows)
- ✅ Backfill biz=1 sem perda histórica (validar count = sum(transactions+purchases+adjustments+transfers))
- ✅ Reconcile job zero drift por 7 dias seguidos biz=1
- ✅ Performance query `stock_movements` indexada — sub-200ms p99 com 1M rows

### Métricas de sucesso
- Drift detection 0 em prod ≥ 60 dias
- Audit retro 1 ano disponível em < 5s
- `jana:health-check` integrado retorna OK em todos businesses

---

## §6 Fase 5 — Negative inventory + FEFO + analytics (Sem 8-9)

**Goal:** Optimizações maturas + dashboard BI estoque.

**Pré-requisito ativação:** F1+F2+F3+F4 todos em prod + sinal qualificado V2 (cliente pediu negative OU dashboard).

### Entregas
- **US-INV-021** Negative inventory opt-in + UI sinalização vermelha (6h)
- **US-INV-022** FEFO consumo policy automatic (4h)
- **US-INV-023** Job daily `inventory:expire-batches` (2h)
- **US-INV-024** Dashboard analytics estoque (giro/lead-time/ruptura/custo categoria) (14h)
- **US-INV-025** Multi-location transfer com batch preservation (8h)

### Esforço
- Total: 34h IA-pair · margem 2× = 68h ≈ 2 semanas

### Gates qualidade
- ✅ Pest negative inventory: oversell ML registrado correto + UI sinaliza
- ✅ Pest FEFO: batch com expires_at mais próximo escolhido primeiro
- ✅ Dashboard renderiza ≤ 3s com 1M movements (cache + Meilisearch?)

### Métricas de sucesso
- ML Full ComVis vende oversell legítimo ≥ 50× (sem erro)
- Perdas validade ComVis reduzem ≥ 30% (FEFO impact)
- Dashboard estoque acessado ≥ 3×/semana per business (engagement)

---

## §7 Marcos Wagner-approval

| Marco | Quando | O quê Wagner aprova |
|---|---|---|
| **M0 — SPEC + ADR aceitos** | Antes Fase 1 | D1-D8 decisões + estimates + escopo 25 US |
| **M1 — Fim Fase 1** | Sem 2 | Kit bomba em prod Vargas + canary 7d biz=4 sem impacto |
| **M2 — Fim Fase 2** | Sem 4 | Lookup garantia fornecedor demo + ComVis pioneer aprovou |
| **M3 — Fim Fase 3** | Sem 6 | Custo real OS demo lado-a-lado vs Mubisys (gap mercado validado) |
| **M4 — Fim Fase 4** | Sem 7 | Audit trail completo + reconcile 0 drift |
| **M5 — Fim Fase 5** | Sem 9 | Dashboard demo + métricas FEFO impact |

---

## §8 Riscos roadmap + mitigação

### R-ROAD-1 — Vargas não assina contrato pioneer

**Impacto:** Fase 1 não inicia ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) bloqueia sem sinal qualificado).

**Mitigação:** Wagner ativa hipótese B — AutoPeças piloto alternativo (Vargas autopeças OU outro candidato). Fase 1 kit BOM ainda útil pra outro cliente.

### R-ROAD-2 — Fase 4 backfill demora > maintenance window aceitável

**Impacto:** ROTA LIVRE biz=4 (99% volume) precisa downtime 4-8h pra backfill 5+ anos UPos.

**Mitigação:** Backfill incremental por mês (1 mês de história por vez, idempotente) — distribui em 12 maintenance windows curtos (30min cada).

### R-ROAD-3 — D4 NFe kit contador Eliana[E] não-disponível

**Impacto:** US-INV-005 bloqueado.

**Mitigação:** Default conservador `linhas_componentes` + flag config per business; Eliana[E] revisa em retro pós-deploy; ajusta default em ADR posterior se preciso.

### R-ROAD-4 — Fase 3 dimensional descoberta nova regra ICMS por unit (kg vs un)

**Impacto:** Cálculo custo real exige cruzar tabela fiscal de unidade — escopo cresce.

**Mitigação:** V1 trata custo gerencial (interno), NÃO fiscal. Fiscal fica em ADR separado Modules/NfeBrasil V2.

---

## §9 Próximos passos imediatos (post-aprovação SPEC + ADR)

1. **Wagner** revisa SPEC §3-§4 + decide D1-D8 (3 decisões críticas: D1, D4, D6)
2. **Wagner** promove ADR proposta `proposals/drafts/inventory-avancado-kits-batch-dimensional.md` → `decisions/NNNN-...md` (atribui número canônico)
3. **Wagner** consulta Eliana[E] sobre D4 (NFe kit strategy fiscal) — agendar 30min próxima semana
4. **Wagner** confirma sinal qualificado Vargas (OficinaAuto OU AutoPeças) — gate F1
5. **Cycle planner** cria 5 US (US-INV-001..005) no MCP via `tasks-create module:Inventory cycle:current`
6. **Webhook GitHub → MCP** sincroniza SPEC + ADR (1×commit + push)
7. **Iniciar Fase 1** PR-a-PR (US-INV-001 primeiro — schema-only, baixo risco)

---

> ROADMAP criado 2026-05-12 [W]. Cross-ref: [SPEC.md](SPEC.md) · [MATRIZ-ROI.md](MATRIZ-ROI.md) · [ADR proposta](../../decisions/proposals/drafts/inventory-avancado-kits-batch-dimensional.md)

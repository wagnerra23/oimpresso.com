# MATRIZ-ROI — Comissão de Vendedores (cross-vertical)

> 15 features × concorrentes × esforço × valor estratégico. Top 5 priorizadas pro P0/P1.
> Última atualização: 2026-05-12.

---

## Concorrentes consultados

| ID | Sistema | Mercado | Tipo |
|---|---|---|---|
| **C1** | UPos legacy (este projeto) | BR (base) | ERP horizontal |
| **C2** | Bling | BR PME | ERP horizontal |
| **C3** | Tiny ERP (Olist) | BR PME | ERP horizontal |
| **C4** | Conta Azul | BR PME | ERP/contábil |
| **C5** | Omie | BR PME | ERP |
| **C6** | Pipefy (workflow) | BR + intl | BPMS |
| **C7** | Spiff (Salesforce) | USA enterprise | ICM (Incentive Comp Management) |
| **C8** | CaptivateIQ | USA mid-market | ICM |
| **C9** | Xactly Incent | USA enterprise | ICM |
| **C10** | Mubisys (vertical Com.Visual) | BR vertical | ERP vertical |
| **C11** | Ultracar (vertical Oficina) | BR vertical | ERP vertical |

Legenda células: ✅ entrega bem · 🟡 entrega parcial · ❌ não entrega · — irrelevante pro nicho.

---

## Matriz features × concorrentes

| # | Feature | C1 UPos | C2 Bling | C3 Tiny | C4 ContaAzul | C5 Omie | C6 Pipefy | C7 Spiff | C8 CaptIQ | C9 Xactly | C10 Mubisys | C11 Ultracar | Esforço (h IA-pair) | Valor estratégico | ROI |
|---|---------|---------|----------|---------|--------------|---------|-----------|----------|-----------|-----------|-------------|--------------|---------------------|--------------------|-----|
| F1 | Vendedor único + % fixo per usuário | ✅ | ✅ | ✅ | ✅ | ✅ | 🟡 | ✅ | ✅ | ✅ | ✅ | ✅ | 0 (já existe) | LOW (paridade) | — |
| F2 | Cálculo automático pós-pagamento (FSM marcar_pago) | ❌ | ✅ | ✅ | ✅ | ✅ | 🟡 | ✅ | ✅ | ✅ | ✅ | ✅ | 16h | **HIGH** (mata planilha Eliana) | **⭐⭐⭐⭐⭐** |
| F3 | Claw-back automático ao cancelar venda | ❌ | 🟡 manual | ❌ | ❌ | ✅ chargeback | ❌ | ✅ | ✅ | ✅ | ❌ | 🟡 | 16h | **HIGH** (perda dinheiro real hoje) | **⭐⭐⭐⭐⭐** |
| F4 | Multi-papel (vendedor+designer+instalador per venda) | ❌ | ❌ | ❌ | ❌ | 🟡 | 🟡 | ✅ splits | ✅ splits | ✅ | ✅ ComVis | 🟡 | 24h | **HIGH** (gap absoluto BR-PME ComVis) | **⭐⭐⭐⭐⭐** |
| F5 | Tiers escalonados R$/% (3+ faixas reais) | ❌ | ✅ faixa desconto | ✅ R$ tiers | ❌ | ❌ | ❌ | ✅ | ✅ | ✅ | 🟡 | ❌ | 16h | MED (vendedores avançados pedem) | **⭐⭐⭐⭐** |
| F6 | Accelerator (>meta = bonus extra) | ❌ | 🟡 manual | ✅ | ❌ | ✅ campanhas | ❌ | ✅ | ✅ | ✅ | ❌ | ❌ | 16h | MED (motivacional) | **⭐⭐⭐⭐** |
| F7 | Relatório mensal per-vendedor com drill-down | ✅ basic | ✅ | ✅ | ✅ | ✅ | 🟡 | ✅ | ✅ | ✅ | 🟡 | 🟡 | 24h (MWART UI) | MED (paridade obrigatória) | **⭐⭐⭐** |
| F8 | Fechamento mensal + folha integrada | ❌ | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ | ✅ | 🟡 | 🟡 | 8h artisan | **HIGH** (impacta financeiro) | **⭐⭐⭐⭐⭐** |
| F9 | Multi-mecânico/multi-instalador split (apontamentos) | ❌ | ❌ | ❌ | ❌ | ❌ | 🟡 | ✅ | ✅ | ✅ | 🟡 ComVis | ✅ Oficina | 24h | MED (vertical Oficina/ComVis) | **⭐⭐⭐⭐** |
| F10 | Comissão sobre líquido marketplace (após taxa ML/iFood) | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | 🟡 | 🟡 | 🟡 | ❌ | ❌ | 16h (após Marketplaces) | **HIGH** (diferenciador BR) | **⭐⭐⭐⭐⭐** |
| F11 | Aprovação workflow + audit trail | ❌ | ❌ | ❌ | ❌ | 🟡 | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | 16h | MED (CLT compliance) | **⭐⭐⭐⭐** |
| F12 | Mobile self-service vendedor | ❌ | 🟡 portal | 🟡 | 🟡 | 🟡 | 🟡 | ✅ | ✅ | ✅ | ❌ | 🟡 | 16h (PWA) | LOW-MED (vendedor pede; não vital P0) | **⭐⭐⭐** |
| F13 | Comissão por produto/categoria/linha | ❌ | ✅ | ✅ | ❌ | ✅ | ❌ | ✅ | ✅ | ✅ | 🟡 | ✅ | 8h (já no schema policy `applies_to_line_filter`) | MED (esperado) | **⭐⭐⭐** |
| F14 | Comissão por origem (CRM lead vs marketplace vs balcão) | ❌ | ❌ | ❌ | ❌ | ❌ | 🟡 | ✅ overlays | ✅ | ✅ | ❌ | ❌ | 16h | LOW (P3 — sem cliente pedindo hoje) | **⭐⭐** |
| F15 | Dispute / overlay / SPIFF (campanhas temporárias) | ❌ | 🟡 | 🟡 | ❌ | 🟡 | ❌ | ✅ | ✅ | ✅ | ❌ | ❌ | 24h | LOW (over-engineering pra PME 1-30 vendedores) | **⭐⭐** |

---

## Ranking ROI (alto → baixo)

| Rank | Feature | ROI | Reason |
|------|---------|-----|--------|
| 1 | **F2 — Cálculo automático pós-pagamento** | ⭐⭐⭐⭐⭐ | Mata planilha Eliana[E] manual ROTA LIVRE (~3h/mês × 12 = 36h/ano recuperadas + zero erro de cópia). Pluga em FSM action existente — baixo esforço, alto impacto. |
| 2 | **F4 — Multi-papel** | ⭐⭐⭐⭐⭐ | Gap absoluto no BR-PME. Habilita ComVis (Vargas/Mhundo/Extreme candidatos) e Oficina (US-AUTO-011). Diferenciador comercial real. |
| 3 | **F3 — Claw-back automático** | ⭐⭐⭐⭐⭐ | Perda de dinheiro real hoje: cancelar venda paga não estorna comissão paga. Quase nenhum concorrente BR cobre bem. Slot side-effect FSM pronto. |
| 4 | **F8 — Fechamento mensal artisan** | ⭐⭐⭐⭐⭐ | Habilita F2 a virar processo (sem fechamento, F2 é só "dados acumulados"). Eliana[E] roda manual + audit. |
| 5 | **F10 — Comissão sobre líquido marketplace** | ⭐⭐⭐⭐⭐ | Diferenciador comercial: Bling/Tiny/Omie não fazem. Cliente ML/iFood reclama "vendedor ganha sobre taxa marketplace que eu pago" — gap real. **Dep externa:** Marketplaces module precisa ter `marketplace_net_amount`. |

---

## Top 5 a construir (P0/P1)

| Sprint | Features | Estimate | US correspondente |
|---|---|---|---|
| **P0 fundação** | F2 + F8 (fechamento) | 16h + 8h = 24h | US-COMM-001 + US-COMM-002 + US-COMM-007 |
| **P0 anti-fraude/perda** | F3 (clawback) | 16h | US-COMM-003 |
| **P1 vertical diferencial** | F4 (multi-papel) | 24h | US-COMM-009 + US-COMM-004 (UI policy) |
| **P1 motivacional** | F5 + F6 (tiers + accelerator) | 32h | US-COMM-005 + US-COMM-006 |
| **P1 visibilidade** | F7 (relatório UI) + F11 (approval) | 40h | US-COMM-008 + US-COMM-012 |

**Subtotal Top 5 caminho P0/P1: ~136h (~17 dias IA-pair recalibrados [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md))**

---

## Features Bottom 5 (P2/P3 — não bloqueiam piloto)

| F | Decisão | Motivo |
|---|---------|--------|
| F9 | P2 — quando OficinaAuto entrar em produção (sem cliente Oficina ativo hoje — [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)) | Sem sinal qualificado cliente |
| F10 (marketplace) | P2 — dep externa Modules/Marketplaces ainda em backlog | Bloqueado por outro módulo |
| F12 (mobile) | P2 — vendedor ROTA LIVRE é Larissa dona (não delegação) | Sem urgência piloto |
| F13 (per produto) | P2 — schema já comporta (`applies_to_line_filter`); UI completa fica P2 | Schema entregue P0; UI low-prio |
| F14 (origem) | P3 — backlog ADR feature-wish ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)) | Sem sinal cliente |
| F15 (SPIFF campanhas) | P3 — over-engineering pra PME 1-30 vendedores | Não-target Spiff/CaptivateIQ |

---

## Comparativo posicionamento mercado

| Eixo | oimpresso (após Top 5) | Bling/Tiny | Omie | Mubisys/Ultracar | Spiff/CaptIQ |
|------|------------------------|------------|------|------------------|--------------|
| Multi-papel vertical | ✅ | ❌ | 🟡 | ✅ (mas isolado por vertical) | ✅ |
| Comissão sobre líquido marketplace | ✅ | ❌ | ❌ | ❌ | 🟡 |
| Cálculo automático FSM-driven | ✅ | ✅ | ✅ | 🟡 | ✅ |
| Clawback automático | ✅ | 🟡 | ✅ | ❌ | ✅ |
| Tiers + accelerator | ✅ | ✅ | 🟡 | 🟡 | ✅ |
| Mobile self-service | 🟡 (P2) | 🟡 | 🟡 | ❌ | ✅ |
| Audit trail CLT | ✅ | ❌ | 🟡 | ❌ | ✅ |
| Preço target BR PME | R$ free-tier (incluso) | R$ [redacted Tier 0]-300/mês | R$ [redacted Tier 0]-799/mês | R$ [redacted Tier 0]-499/mês | USD 30+/user/mês |

**Conclusão posicionamento:** ao entregar Top 5, oimpresso fica **acima de Bling/Tiny/Conta Azul/Omie** em features de comissão e **competitivo com Mubisys/Ultracar** nas verticais — sem precisar virar Spiff/CaptIQ (escopo errado pra PME BR).

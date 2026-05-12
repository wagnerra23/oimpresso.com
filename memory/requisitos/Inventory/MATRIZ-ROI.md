---
modulo: Inventory (cross-vertical)
status: proposed
created_at: 2026-05-12
spec_ref: SPEC.md
adr_ref: ../../decisions/proposals/drafts/inventory-avancado-kits-batch-dimensional.md
---

# MATRIZ-ROI — Inventory avançado

> Cruza 25 features (US-INV-001..025) × valor cliente (unblocking real prospects) × esforço × concorrência (Tiny / Bling / SAP B1 / NetSuite / Cin7 / Odoo / TOTVS / Sankhya / Linx Microvix).
>
> Escala ROI: **A** (alto — desbloqueia cliente pioneer assinante OU mata gap mercado) · **M** (médio — melhoria operacional comprovada) · **B** (baixo — nice-to-have V3+)

---

## §1 Tabela 25 features × ROI

| US | Feature | Cliente-alvo | Esforço (h IA-pair) | Valor desbloqueio | ROI | Concorrência |
|----|---|---|---|---|---|---|
| US-INV-001 | Schema BOM (`product_bom` + `product_kits` + flags) | Vargas + ComVis + AutoPeças | 6h | Base p/ Kits — viabiliza US-002..005 | **A** | Tiny/Bling combo simples; SAP B1/Cin7/Odoo BOM multi-level; oimpresso = paridade |
| US-INV-002 | UI Inertia BOM (drag-drop componentes) | Vargas (kit bomba) | 12h | UX moderna vs Tiny/Bling Blade-ish | **A** | Tiny BOM em modal; Cin7 dedicated screen; oimpresso = moderno |
| US-INV-003 | Resolver BOM recursive em ReservarEstoque | Vargas (kit-de-kit raro mas válido) | 8h | Multi-level matchea SAP/NetSuite — diferenciação BR PME | **A** | Tiny/Bling NÃO suportam multi-level; **gap mercado** |
| US-INV-004 | Resolver BOM em ConsumirEstoque + LiberarReserva | Vargas (cancel OS = libera 4 reservas) | 6h | Cascade baixa correta — sem isso = drift estoque | **A** | Idem US-003 |
| US-INV-005 | NFe55 kit (linha pai vs N filhas) | OficinaAuto + AutoPeças (regulamentado) | 8h | Fiscal compliance kit BR | **A** | Bling configurável; Tiny só linha pai default; oimpresso = configurável melhor |
| US-INV-006 | Schema `product_batches` | Vargas (Mahle lote) + ComVis (bobina Mimaki) | 6h | Base p/ batch tracking — viabiliza US-007..010 | **A** | Tiny/Bling lote string; oimpresso = primeira-classe |
| US-INV-007 | RegistrarEntradaBatch side-effect | ComVis + AutoPeças entrada NFe | 6h | Auto-popula lote a partir XML NFe | **A** | Tiny manual; Bling parcial; oimpresso = automático via TransactionBuilder |
| US-INV-008 | UI Inventory/Batches listagem + busca + filtros | ComVis (rolos ativos) + Vargas (peças vencendo) | 10h | Dashboard rastreio realtime | **A** | Tiny tem; Bling tem; oimpresso = paridade + filtro defeito_lote |
| US-INV-009 | Batch picker em PDV/OS (FEFO ou manual) | ComVis (cliente exige cor uniforme) | 12h | Diferencial real — ninguém BR PME tem cor-pantone-per-lote | **A** | NetSuite/Cin7 sim; Tiny/Bling/Microvix NÃO; **gap mercado** |
| US-INV-010 | Lookup garantia fornecedor (batch → clientes) | AutoPeças (RMA Mahle) | 4h | Reduz fricção devolução fornecedor — economia R$ [redacted Tier 0]-5k/mês biz médio | **A** | SAP B1/NetSuite sim; Tiny/Bling NÃO; **gap mercado BR PME** |
| US-INV-011 | `products.base_unit_id_inv` separado de venda unit | ComVis (cartucho UN vende, ml consome) | 4h | Custo real per OS preciso | **M** | Sankhya tem multi-unit; Tiny limitado; oimpresso = paridade Sankhya |
| US-INV-012 | UI custo per ml/kg/m² + cálculo custo real OS | ComVis (custo banner = lona + tinta + MOD) | 10h | **Diferencial #1 ComVis** — Mubisys NÃO tem | **A** | Mubisys/Zênite NÃO; Calcgraf parcial; oimpresso = melhor BR ComVis |
| US-INV-013 | Alerta cartucho/rolo baixo (% restante) | ComVis (troca M-magenta 5%) | 4h | Evita parada plotter — perda R$ [redacted Tier 0]-500/dia | **A** | Roland VersaWorks tem; Tiny/Bling NÃO; oimpresso = paridade software gráfico premium |
| US-INV-014 | Apontamento máquina decrementa batch ml auto | ComVis (US-COMVIS-004 hook) | 6h | Sem digitação manual — ganho 5-10min/OS | **M** | Calcgraf manual; Roland integrado proprietário; oimpresso = oss-friendly |
| US-INV-015 | Conversão unit automática (kg→g, L→ml) | Vargas + ComVis (compra L, consome ml) | 4h | UX correção automática | **M** | UPos legacy parcial; oimpresso = completar |
| US-INV-016 | Schema `stock_movements` append-only + triggers | TODOS (compliance) | 6h | **Single source of truth audit** — base US-017..020 | **A** | NetSuite/SAP B1 sim; Tiny audit limitado; Bling audit parcial; **gap BR PME** |
| US-INV-017 | Backfill incremental stock_movements UPos legacy | ROTA LIVRE biz=4 + Wr2 biz=1 | 12h | Sem perder histórico 5+ anos | **A** | Cobertura compliance pleno |
| US-INV-018 | Hook automático side-effects (Reservar/Consumir/Liberar) registra stock_movements | TODOS | 4h | Audit automatic — devs não esquecem | **A** | Padrão Anti-bug |
| US-INV-019 | UI relatório movements per produto/batch/período | TODOS | 8h | Compliance LGPD/Receita Federal | **M** | NetSuite/SAP rich; Tiny basic; oimpresso = melhor BR PME |
| US-INV-020 | Comando `inventory:reconcile` daily drift detection | TODOS (jana:health-check) | 4h | Detecta drift estoque silencioso | **A** | Sankhya tem; Tiny/Bling NÃO; **gap mercado** |
| US-INV-021 | Negative inventory opt-in + UI sinalização | ComVis ML Full + AutoPeças marketplace | 6h | Permite oversell legítimo | **M** | Tiny/Cin7 sim; Bling parcial; oimpresso = paridade |
| US-INV-022 | FEFO consumo policy automatic | ComVis (PVC 24m validade) | 4h | Reduz perdas validade — economia 3-5% estoque | **M** | Sankhya tem; Tiny FIFO only; **diferencial BR PME** |
| US-INV-023 | Job daily `inventory:expire-batches` | ComVis + Food (futuro) | 2h | Auto-marca vencidos | **M** | Padrão moderno |
| US-INV-024 | Dashboard analytics estoque (giro, lead-time, ruptura) | TODOS (especial AutoPeças) | 14h | BI estoque — diferencial vs Bling/Tiny | **M** | Cin7/NetSuite rich; oimpresso = melhor BR PME |
| US-INV-025 | Multi-location transfer com batch preservation | ComVis multi-filial + ROTA LIVRE V3 | 8h | Franquias/multi-loja com rastreio | **B** | Cin7/Sankhya sim; Tiny limitado |

---

## §2 Top 5 features por ROI desbloqueador

### 1. US-INV-012 — UI custo per ml/kg/m² + cálculo custo real OS (Fase 3)
**Diferencial #1 ComunicacaoVisual.** Mubisys/Zênite NÃO entregam custo-real-por-OS cruzando lona + tinta + MOD. Calcgraf parcial. oimpresso ganha "transparência radical" prometida no charter ComVis ("cliente piloto telefonável + custo real ao fechar OS"). Unblocking 6 candidatos saudáveis OfficeImpresso.

### 2. US-INV-001..005 — Kits/BOM fundação + UI + side-effects + NFe (Fase 1, conjunto)
**Desbloqueia Vargas OficinaAuto.** Kit bomba VW Gol = caso piloto. Multi-level BOM = gap mercado BR PME (Tiny/Bling não cobrem). 40h IA-pair total — ROI maior do projeto pra unblocking sinal qualificado (ADR 0105).

### 3. US-INV-009 — Batch picker em PDV/OS (FEFO ou manual)
**Diferencial real ComVis.** Cliente exige cor uniforme entre rolos = NetSuite/Cin7 cobrem mas BR PME ninguém. Permite Mimaki cor-Pantone-per-lote tracking — argumento comercial forte.

### 4. US-INV-016..020 — Stock Movements unified + backfill + reconcile (Fase 4, conjunto)
**Compliance + transparência.** Single source of truth audit. Detecta drift estoque silencioso. Habilita LGPD compliance + Receita Federal audit. Gap mercado: Tiny/Bling audit limitado. NetSuite/SAP B1 caros demais SMB BR.

### 5. US-INV-010 — Lookup garantia fornecedor (batch → clientes)
**Economia financeira direta AutoPeças.** RMA Mahle/Bosch automático economiza R$ [redacted Tier 0]-5k/mês biz médio (5-12% devolução autopeças BR). Tiny/Bling NÃO cobrem. Gap BR PME.

---

## §3 Comparativo concorrentes resumo

| Concorrente | Multi-level BOM | Batch/lote | Dimensional kg/ml | Movements audit | FEFO/FIFO | Multi-channel ML | Preço/mês BR PME |
|---|---|---|---|---|---|---|---|
| **Tiny (Olist)** | ❌ Simples 1 nível | ✅ Lote+validade | ⚠️ Multi-unit limitado | ⚠️ Logs básicos | ⚠️ FIFO only | ✅ Forte (core feature) | R$ [redacted Tier 0]–199 |
| **Bling** | ❌ Composição simples | ✅ Lote+validade+NFe | ⚠️ Multi-unit | ⚠️ Logs | ⚠️ Manual | ✅ Forte | R$ [redacted Tier 0]–500 |
| **Linx Microvix** | ✅ Kits + grade | ✅ Lote | ⚠️ Limitado | ✅ Forte (varejo) | ⚠️ Manual | ⚠️ Parcial | R$ [redacted Tier 0]–800 |
| **TOTVS Protheus SIGAEST** | ✅ MATA010 + MATA650 | ✅ Lote+sublote+validade rastreio fim-a-fim | ✅ Indústria | ✅ Tabelas SB8/SD5 | ✅ FEFO/FIFO | ⚠️ Add-on caro | R$ [redacted Tier 0]-15k+ |
| **Sankhya** | ✅ Composição produto | ✅ Lote+explosão automática | ✅ Indústria | ✅ Forte | ✅ FEFO via MP | ⚠️ Limitado | R$ [redacted Tier 0]-8k+ |
| **SAP Business One** | ✅ Multi-level + approval | ✅ Serial+batch end-to-end | ✅ Multi-unit | ✅ Real-time | ✅ Configurável | ⚠️ Add-on | USD 1k+/mês |
| **NetSuite** | ✅ Multi-level assembly | ✅ Lot+serial+bin numbered | ✅ Avançado | ✅ Subledger | ✅ Configurável | ⚠️ Add-on | USD 1k+/mês |
| **Cin7 Core** | ✅ BOM + auto-kitting | ✅ Batch+serial+recall | ✅ Multi-unit | ✅ Real-time | ✅ Configurável | ✅✅ 700+ integrations | USD 350+/mês |
| **Odoo Inventory** | ✅ BOM + approval | ✅ Lots+serial+traceability reports | ✅ Multi-unit | ✅ Forte | ✅ FEFO config | ✅ Add-on | EUR 0–24/usr |
| **oimpresso (proposto V1)** | ✅✅ Multi-level + opcional + substituição | ✅ Lote+serial+defeito+RMA + cor-Pantone | ✅ + custo per unit + alerta % | ✅✅ Append-only triggers SQL | ✅ Per business + per produto override | ✅ Hook ready (Modules/Marketplaces) | R$ ?–? (pricing per ADR 0105) |

**Insight chave:** oimpresso Inventory V1 atinge **paridade SAP B1 / Cin7 Core em capacidades core**, supera Tiny/Bling em multi-level + audit + FEFO, e adiciona **2 features diferenciadas**: (a) cor-Pantone-per-lote ComVis, (b) lookup garantia fornecedor automático AutoPeças. Preço-alvo: 1/5 SAP B1 + paridade Bling Plus.

---

## §4 Distribuição esforço × ROI por fase

| Fase | US count | Esforço total (h IA-pair) | ROI agregado | Recomendação |
|---|---|---|---|---|
| F1 — Kits/BOM | 5 | 40h | **A×5** | Iniciar AGORA — unblock Vargas |
| F2 — Batch tracking | 5 | 38h | **A×4 + M×1** | Iniciar logo após F1 — unblock ComVis 6 candidatos |
| F3 — Dimensional | 5 | 28h | **A×2 + M×3** | Iniciar após F2 — completa ComVis pricing |
| F4 — Movements unified | 5 | 34h | **A×4 + M×1** | Paralelizável com F3 — compliance |
| F5 — Negative/FEFO/analytics | 5 | 34h | **M×3 + B×2** | Aguardar sinal V2+ ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)) |

**Soma:** 174h codáveis IA-pair × margem 2× = ~348h ≈ 9 semanas full-focus 1 dev sênior + IA-pair (Wagner).

---

## §5 Cross-funcionalidade verticais (quem ganha o quê)

| Vertical | F1 Kits | F2 Batch | F3 Dimensional | F4 Movements | F5 Negative/FEFO |
|---|---|---|---|---|---|
| **OficinaAuto (Vargas)** | ⭐⭐⭐ kit bomba | ⭐⭐ peças Mahle | ⭐ óleo L | ⭐⭐ compliance | ⭐ FEFO óleo |
| **ComVis (Extreme/Gold/Zoom/Fixar/Mhundo/Produart)** | ⭐⭐ kit promoção banners | ⭐⭐⭐ bobina Mimaki cor-Pantone | ⭐⭐⭐ tinta ml + lona m² | ⭐⭐ recall | ⭐⭐ FEFO PVC validade |
| **AutoPecas (Vargas hipótese)** | ⭐ kits raros | ⭐⭐⭐ Mahle/Bosch RMA | ⭐ óleo | ⭐⭐⭐ devolução audit | ⭐ negative ML |
| **Vestuario (ROTA LIVRE live)** | ⭐ grade já cobre | — | — | ⭐⭐ audit | — |
| **Genérico SMB BR** | ⭐⭐ kit promoção | ⭐ controle vencimento | ⭐ multi-unit | ⭐⭐⭐ compliance | ⭐ analytics |

⭐⭐⭐ = unblocking direto · ⭐⭐ = melhoria operacional grande · ⭐ = nice-to-have

---

> MATRIZ-ROI criada 2026-05-12 [W]. Cross-ref: [SPEC.md](SPEC.md) · [ROADMAP.md](ROADMAP.md) · [ADR proposta](../../decisions/proposals/drafts/inventory-avancado-kits-batch-dimensional.md)

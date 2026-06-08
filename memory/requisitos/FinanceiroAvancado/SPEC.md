---
title: SPEC — Modules/FinanceiroAvancado
status: draft
date: 2026-05-12
owner: wagner
module: FinanceiroAvancado
parent_modules: [Financeiro, Accounting, RecurringBilling, NfeBrasil, Inventory]
convention_id: US-FINA-NNN
related_adrs: [0093, 0094, 0104, 0143]
proposal_adr: financeiro-avancado-dre-fluxo-conciliacao
---

# SPEC — Modules/FinanceiroAvancado

> **NÃO** é módulo novo "do zero". É **camada de aprofundamento analítico** sobre `Modules/Financeiro` (operacional) + `Modules/Accounting` (contábil formal) + `Modules/RecurringBilling` (cobrança Asaas/Inter) + `Modules/NfeBrasil` (fiscal). Foco: **DRE realista por competência/caixa**, **fluxo de caixa projetado com IA**, **conciliação bancária automática** (extrato Inter/Asaas/OFX → títulos abertos), **plano de contas configurável BR**, **margem real por venda** e **categorização IA Jana**.

## §0 — Comparação JÁ EXISTE (auditoria não-duplicação)

Auditoria executada em `Modules/Financeiro/` (12 controllers, 10 Models, 12 migrations), `Modules/Accounting/` (12 controllers upstream UPos), `Modules/RecurringBilling/Services/Boleto/Drivers/` (Asaas/Inter/C6) e SPEC.md US-FIN-001..014.

| # | Feature | Modules/Financeiro atual | Modules/Accounting (UPos) | Modules/RecurringBilling | UPos legacy (core) | Mercado tem | Veredito |
|---|---|---|---|---|---|---|---|
| 1 | Dashboard 4 estados (AR/AP aberto/recebido/pago mês) | ✅ `DashboardController` + Inertia Pages/Dashboard + US-FIN-013 | ❌ | — | `transactions` + `transaction_payments` (parcial) | Conta Azul/Omie | **JÁ TEM** — não duplicar |
| 2 | Título (fin_titulos) AR/AP unificado | ✅ Model `Titulo` + 13 migrations | ❌ (usa `journal_entries`) | ❌ | `transactions.payment_status` | todos | **JÁ TEM** |
| 3 | Baixa parcial/total (TituloBaixa) | ✅ Model `TituloBaixa` + `TituloService::baixar()` US-FIN-003 | ⚠ via journal entry manual | — | `transaction_payments` | todos | **JÁ TEM** |
| 4 | Auto-título de venda (Observer) | ✅ `TransactionObserver` + `TransactionPaymentObserver` + `TituloAutoService` | ⚠ via `acc_trans_mappings` manual | — | — | todos | **JÁ TEM** |
| 5 | Plano de Contas hierárquico 5 níveis | ✅ Model `PlanoConta` + table `fin_planos_conta` (codigo/nome/tipo/nivel/parent_id/natureza/aceita_lancamento/protegido) | ✅ `ChartOfAccountController` (parallel — ADR ARQ-0005) | — | — | Sankhya/TOTVS/Omie | **JÁ TEM** estrutura — falta UI configurável BR + templates |
| 6 | Categoria (taxonomy operacional) | ✅ Model `Categoria` + `CategoriaController` | ❌ | — | — | Conta Azul | **JÁ TEM** |
| 7 | Boleto CNAB direto | ✅ Strategy `CnabDirectStrategy` US-FIN-010 + lib `eduardokum/laravel-boleto` | ❌ | ✅ alt path (gateway) | — | todos | **JÁ TEM** |
| 8 | Boleto via Gateway (Asaas/Inter/C6) | ⚠ stub `GatewayStrategy` US-FIN-010 | ❌ | ✅ **canônico** — 3 drivers (Asaas/Inter/C6) `Services/Boleto/Drivers/` | — | Conta Azul/Omie | **JÁ TEM** em RecurringBilling — bridge ok |
| 9 | Extrato bancário sincronizado | ✅ Model `ExtratoLancamento` + `ExtratoController` + `RecurringBilling/Services/Banking/InterBankingClient` (job diário 07h BRT) | ⚠ tela `ReconcileController` manual | ✅ pull Inter API | — | Omie (4 bancos diretos) | **JÁ TEM** ingestão Inter — falta normalizar Asaas/C6 + adicionais (Sicoob/BTG/Cora/Bradesco/Itaú) |
| 10 | Conciliação OFX manual + match heurístico | ⚠ US-FIN-009 specced + Inertia Pages/Conciliacao/ stub | ✅ `ReconcileController` manual (UPos Accounting) | — | — | Conta Azul/Omie | **PRECISA construir** — match automático + IA + idempotência |
| 11 | Conciliação automática Inter PJ extrato → títulos | ❌ | ❌ | ⚠ ingestão OK; match com `fin_titulos` ainda manual | — | Omie (Itaú/Bradesco/Santander/Caixa nativo) | **PRECISA construir** — núcleo FinanceiroAvancado |
| 12 | DRE gerencial (4 meses compar.) | ✅ `RelatoriosController@montarDre` US-FIN-014 + `Relatorios/Index.tsx` | ✅ DRE formal `ReportController` (Income Statement) | — | — | Conta Azul/Omie/TOTVS | **JÁ TEM** gerencial — falta drill-down + estrutura DRE BR completa US-FIN-011 (`Bruta → Deduções → Líquida → CMV → Bruto → Despesas → EBITDA → D&A → Impostos → Líquido`) |
| 13 | DRE estrutura BR formal (drill-down + export PDF/Excel + token shareable contador) | ⚠ US-FIN-011 specced sem implementação | ✅ DRE formal contábil | — | — | Conta Azul (advisor network) | **PRECISA construir** (gerencial+formal+shareable) |
| 14 | Fluxo de caixa **projetado** 30/60/90d + alerta descoberto | ⚠ US-FIN-007 specced; `RelatoriosController@montarFluxo` traz **projetado vs realizado mensal** simples | ❌ | — | — | Conta Azul (IA-pred) | **PRECISA construir** — projeção diária + alerta + cache invalidado |
| 15 | Categorização IA de transação banco desconhecida | ❌ | ❌ | — | — | Conta Azul beta | **PRECISA construir** — diferencial Jana |
| 16 | Margem real per venda (custo Inventory + Comissão + impostos) | ❌ | ❌ | — | — | raro (TOTVS SIGAFAT premium) | **DIFERENCIAL** — construir |
| 17 | Aging inadimplência buckets | ⚠ US-FIN-012 specced sem implementação | ❌ | — | — | todos | **PRECISA construir** |
| 18 | Dunning régua automática (WhatsApp/email/SMS) | ❌ (CTA `wa.me` manual no aging US-FIN-012) | ❌ | ⚠ recurring billing tem retry boleto | — | Asaas nativo | **PRECISA construir** — usa Modules/Jana brain |
| 19 | OCR boleto upload | ⚠ US-FIN-005 stub (Onda 4) | ❌ | — | — | Conta Azul killer | **PRECISA construir** (Onda 4) |
| 20 | Mapeamento NFe emitida → entry contábil + DRE | ❌ | ⚠ `acc_trans_mappings` manual | — | `transactions` core (parcial) | Omie/TOTVS | **PRECISA construir** — bridge NfeBrasil→FinanceiroAvancado |
| 21 | Conciliação cartão crédito (taxa adquirente + prazo D+1/D+30) | ❌ | ❌ | — | — | Conta Azul | **PRECISA construir** |
| 22 | Cenários de fluxo (what-if antecipação/empréstimo) | ❌ | ❌ | — | — | TOTVS premium / planilha externa | **DIFERENCIAL** — construir |
| 23 | FSM `marcar_pago` dispara entry caixa + projeção | ⚠ FSM canônico ADR 0143 já em prod biz=1 (refund/cancel cascade); falta hook `cash_movement` | ❌ | — | — | — | **integrar** com FSM existente |
| 24 | Plano contas templates BR (Simples Nacional default) | ⚠ Model preparado (`protegido`/`aceita_lancamento`); seed default ausente | ⚠ template UPos genérico | — | — | Sankhya/TOTVS | **PRECISA construir** templates BR |
| 25 | Token shareable read-only DRE pro contador | ⚠ US-FIN-011 specced | ❌ | — | — | Conta Azul (advisor network) | **PRECISA construir** |

**Conclusão §0:** dos ~25 itens auditados, **9 JÁ EXISTEM** parcial/completo, **2 são bridges entre módulos** (não duplicação), **14 são genuíno gap competitivo**. FinanceiroAvancado **NÃO substitui** `Modules/Financeiro` nem `Modules/Accounting` — **estende analiticamente**. Bridge tables apenas; consumers leem `fin_titulos`/`fin_titulo_baixas`/`fin_caixa_movimentos`/`extrato_lancamentos` existentes.

## §1 — Visão

`Modules/FinanceiroAvancado` adiciona ao stack financeiro existente:

1. **Conciliação automática multi-banco** (Inter/Asaas/C6/Sicoob/BTG/Cora/OFX) com match score IA Jana (>95% auto-aceita, 80-95% sugere, <80% rejeita)
2. **DRE BR estrutura formal completa** (10 linhas) por **regime tenant** (caixa/competência) com **drill-down** + **export PDF/Excel** + **token shareable 7d pro contador**
3. **Fluxo de caixa projetado diário 30/60/90d** com alerta descoberto + **cenários what-if** (antecipar AR, postergar AP, empréstimo)
4. **Plano de Contas BR configurável** com **templates Simples Nacional/Lucro Presumido/Real** + custom per-business
5. **Categorização IA Jana** de transações banco desconhecidas (aprende com histórico)
6. **Margem real per venda** (cruza Inventory custo batch + Comissão + Impostos + Frete)
7. **Dunning régua automática** (WhatsApp/email/SMS via FSM canônico ADR 0143)
8. **Bridge NFe→DRE** (emissão NFe gera entry contábil automático em journal_entries upstream + atualiza DRE)

Estende. **Não duplica.**

## §2 — Cenários peculiares

**A — DRE mês (ROTA LIVRE biz=4, Maio 2026):**
Larissa abre `/financeiro-avancado/dre?mes=2026-05&regime=caixa`:
- Receita Bruta: R$ [redacted Tier 0]k (somatório `transactions.final_total` venda finalizada do mês)
- (-) Impostos sobre venda: R$ [redacted Tier 0] (5% Simples Nacional anexo I — `tax_rates`) + R$ [redacted Tier 0] DIFAL (não SP→SC interestadual tributado nesse anexo)
- (=) Receita Líquida: R$ [redacted Tier 0]
- (-) CMV: R$ [redacted Tier 0] (somatório `transaction_sell_lines.product.purchase_price` × qty — bridge `Inventory`)
- (=) Lucro Bruto: R$ [redacted Tier 0] (62%)
- (-) Despesas operacionais: R$ [redacted Tier 0]k (`fin_titulos tipo=pagar status=quitado` + classificado em plano contas `4.x.x Despesas`)
- (-) Comissões: R$ [redacted Tier 0]k (bridge `Modules/Comissao` — futuro; hoje manual em plano contas)
- (=) EBITDA: R$ [redacted Tier 0] (30%)
- (-) D&A: R$ [redacted Tier 0] (Larissa não usa Accounting formal)
- (-) IRPJ/CSLL: R$ [redacted Tier 0] (Simples Nacional — incluso na DAS já deduzida)
- (=) **Lucro Líquido: R$ [redacted Tier 0] (30%)**
- Botão "Compartilhar com contador" gera token JWT 7d → contador acessa `/financeiro-avancado/dre/share/{token}` read-only (sem login).

**B — Fluxo caixa projetado 30d:**
Wagner abre `/financeiro-avancado/fluxo-projetado?horizonte=30`:
- Saldo atual consolidado (todas contas bancárias do business): R$ [redacted Tier 0]
- Dia D+15 (27/05): entradas previstas R$ [redacted Tier 0]k (3 boletos AR Asaas) − saídas previstas R$ [redacted Tier 0]k (folha + aluguel + fornecedor SP) = **saldo final D+15: R$ -8k 🔴 DESCOBERTO**
- Card alerta: "💡 Antecipar boleto #1234 (R$ [redacted Tier 0]k venc 30/05) via Asaas antecipa = +R$ [redacted Tier 0] líquido. Tap pra simular cenário."
- Wagner tap → cenário what-if mostra novo saldo D+15: R$ [redacted Tier 0] (resolvido)
- Wagner clica "Aplicar" → cria `fin_cash_flow_scenarios` row + dispara Asaas antecipa request

**C — Conciliação Inter PJ extrato → títulos:**
Sync diário 07h BRT puxa extrato Inter biz=4. Vê entrada R$ [redacted Tier 0] em 12/05 sem `extrato_lancamento.conciliacao_titulo_id`:
- IA Jana cruza: valor exato R$ [redacted Tier 0] + cliente "MARIA JOSE OLIVEIRA" + tolerância 3d → 1 match `fin_titulos.numero=2030` (venda OS00125 ROTA LIVRE) com score 97%.
- Auto-aceita (score >95%): cria `fin_titulo_baixas` row + atualiza `fin_titulos.status=quitado` + grava `fin_bank_reconciliations` audit row.
- Painel `/financeiro-avancado/conciliacao` mostra "1 nova conciliação automática hoje (97% confiança) — revisar?" Larissa pode reverter em 24h.

**D — Margem real per venda (banner R$ [redacted Tier 0]):**
Cliente comprou banner lona 2m². Wagner abre `/financeiro-avancado/margem/venda/{transaction_id}`:
- Receita: R$ [redacted Tier 0]
- Custos diretos: R$ [redacted Tier 0] (lona — `Inventory.batch.purchase_price` 2m² × R$ [redacted Tier 0]/m²) + R$ [redacted Tier 0] (tinta — sub-product) + R$ [redacted Tier 0] (mão-obra instalação — `Modules/Repair` linkado) = R$ [redacted Tier 0]
- Comissão vendedor: R$ [redacted Tier 0] (5% de R$ [redacted Tier 0])
- Impostos venda: R$ [redacted Tier 0] (5% Simples)
- Frete: R$ [redacted Tier 0] (cliente retirou)
- Custo total: R$ [redacted Tier 0]
- **Margem bruta: R$ [redacted Tier 0] (58%)**
- Comparativo: média do produto banner últimos 90d = 52%. Esta venda 58% = "🟢 acima da média".

**E — Categorização IA Jana de 50 transações banco:**
Sync diário trouxe 50 lançamentos extrato sem `plano_conta_id`. Larissa abre `/financeiro-avancado/categorizar`:
- Jana sugere batch: "37 lançamentos `DEB AUTO RODOFOX LTDA` = `4.2.1.05 Despesa Fornecedor Insumos` (confiança 94% — 23 ocorrências históricas)"
- "8 lançamentos `TARIFA TED` = `4.5.1.01 Tarifas Bancárias` (confiança 99%)"
- "5 lançamentos `PIX RECEBIDO MARIA JOSE` = `1.1.2.01 Receita Operacional - Vestuário` (confiança 88%)"
- Larissa tap "Aceitar todos" → batch atualiza 50 rows em 1 query.
- Score <70% (`PIX FERNANDO X` desconhecido) fica em fila manual.

## §3 — Schema proposto

Tabelas novas (todas `business_id` indexado + FK + global scope):

| Tabela | Propósito | Campos-chave |
|---|---|---|
| `fina_bank_reconciliations` | Audit das conciliações automáticas/manuais | `extrato_lancamento_id`, `fin_titulo_baixa_id`, `match_confidence` (0-100), `match_method` (exact/heuristic/ai), `confirmed_by` (user_id/jana_agent_id/auto), `confirmed_at`, `reverted_at` |
| `fina_cash_flow_projections` | Snapshot diário projeção 30/60/90d | `business_id`, `projection_date` (data referência), `horizonte` (30/60/90), `dias_json` ([{data, saldo_inicial, entradas_previstas, saidas_previstas, saldo_final, alertas[]}]), `saldo_atual`, `gerado_em` |
| `fina_cash_flow_scenarios` | Cenários what-if salvos | `business_id`, `nome`, `descricao`, `acoes_json` ([{tipo: antecipar_ar/postergar_ap/emprestimo, titulo_id, valor, data}]), `criado_por`, `aplicado_em` (nullable) |
| `fina_dre_snapshots` | Snapshot DRE mensal congelado (auditoria fiscal) | `business_id`, `mes` (YYYY-MM), `regime` (caixa/competência), `dre_json` (10 linhas estruturadas), `gerado_em`, `congelado_em` (nullable — quando contador fecha) |
| `fina_dre_share_tokens` | Tokens read-only pro contador | `business_id`, `token_hash`, `dre_snapshot_id`, `criado_por`, `expira_em` (default +7d), `acessado_em[]` (JSON audit) |
| `fina_margin_analysis` | Margem real per venda | `transaction_id` (UPos core), `business_id`, `receita`, `custo_produtos_json` ([{product_id, qty, custo_unit, fonte}]), `custo_mao_obra`, `comissao`, `impostos`, `frete`, `margem_bruta`, `margem_bruta_pct`, `gerado_em` |
| `fina_ia_categorization_log` | Audit IA Jana categorização | `extrato_lancamento_id`, `business_id`, `plano_conta_id_sugerido`, `confidence`, `accepted_by` (user_id/auto/rejected), `accepted_at`, `model_version` |
| `fina_dunning_rules` | Régua cobrança per-tenant | `business_id`, `dias_apos_vencimento`, `canal` (whatsapp/email/sms), `template_id`, `ativo` |
| `fina_dunning_log` | Audit envios cobrança | `fin_titulo_id`, `regra_id`, `enviado_em`, `canal`, `resultado` (delivered/failed/responded), `engaged` (bool — cliente clicou link pagar) |
| `fina_plano_conta_templates` | Templates BR (Simples Nacional, Lucro Presumido, Lucro Real) | `nome` (template), `regime` (sn/lp/lr), `cnae_grupos[]`, `estrutura_json` (árvore plano contas pronto) |

**Bridge tables (sem duplicação):** consumers leem `fin_titulos`, `fin_titulo_baixas`, `fin_caixa_movimentos`, `extrato_lancamentos`, `transactions`, `transaction_sell_lines`, `products.purchase_price`, `tax_rates`, `accounting_account_transactions` existentes.

## §4 — Integração com módulos existentes

| Módulo | Integração | Contrato |
|---|---|---|
| **Modules/Financeiro** | Read `fin_titulos`/`fin_titulo_baixas`/`fin_caixa_movimentos`/`fin_planos_conta` (autoritários). Write apenas `fin_titulo_baixas` via FSM action `marcar_pago` (ADR 0143). | Event `TituloBaixado`/`TituloCriado` consumido pra invalidar cache projeção. |
| **Modules/Accounting** | Read `accounting_account_transactions` quando tenant `accounting_sync_enabled=true`. Write zero (Accounting é autoritário formal). | Bridge listener `SyncDreToAccounting` opt-in (ADR ARQ-0005). |
| **Modules/RecurringBilling** | Read `Services/Banking/InterBankingClient` extrato; `Services/Boleto/Drivers/AsaasDriver` extrato Asaas. Write zero. | Job diário `SyncBankStatementsJob` (existente) atualiza `extrato_lancamentos`. |
| **Modules/NfeBrasil** | Listener `NfeEmitida` → cria entry contábil `accounting_account_transactions` (se sync ON) + atualiza projeção. | Event `NfeEmitida(transaction_id, xml, valor)`. |
| **Modules/Inventory** | Read `products.purchase_price` + `purchase_lines.exp_date`/`lot_number` pra `fina_margin_analysis`. Write zero. | Service contract `InventoryCostResolver::resolveForSell($transactionId)`. |
| **Modules/Repair** | Read `repair_jobs.total_labor_cost` quando venda tem OS linkada. Write zero. | Service contract `RepairLaborResolver::resolveForSell($transactionId)`. |
| **Modules/Jana (Copiloto)** | Tool `categorizar_transacao_extrato(extrato_id)` retorna `{plano_conta_id, confidence}`. Tool `sugerir_acao_fluxo_caixa(business_id, horizonte)` retorna cenários ranked. | MCP tool exposed CT 100. |
| **FSM canônico ADR 0143** | Action `marcar_pago` (transição `aprovado→pago`) dispara `CashMovementCreatedEvent` → invalida projeção + atualiza DRE snapshot. | Event hook em `FsmEngine::transition()`. |

## §5 — User Stories US-FINA-NNN

### Conciliação bancária automática (P0)
- **US-FINA-001** · Conciliar extrato Inter PJ automático com `fin_titulos` abertos (match exato + tolerância 3d)
- **US-FINA-002** · Conciliar extrato Asaas (recebimentos boleto + Pix) com `fin_titulos`
- **US-FINA-003** · Match IA Jana score 80-95% — sugerir e aguardar confirmação Larissa
- **US-FINA-004** · Auto-aceitar match score >95% + permitir reverter em 24h
- **US-FINA-005** · Painel `/financeiro-avancado/conciliacao` agrupado por banco + filtro confidence
- **US-FINA-006** · Ingestão extrato Sicoob/BTG/Cora/Bradesco/Itaú via OFX manual (fallback fora Banking API)

### DRE BR formal completa (P0)
- **US-FINA-007** · DRE 10-linhas BR estrutura formal + regime caixa/competência configurável tenant
- **US-FINA-008** · Drill-down DRE: clicar linha "Receita Bruta" abre lista `transactions` que somaram
- **US-FINA-009** · Export PDF DRE com cabeçalho fiscal business + assinatura digital opcional
- **US-FINA-010** · Token shareable read-only DRE 7d pro contador (`/dre/share/{token}` sem login)
- **US-FINA-011** · DRE snapshot mensal congelado quando contador "fecha mês" (imutável + audit)
- **US-FINA-012** · DRE comparativo 12 meses (gráfico) com variação % mês anterior

### Fluxo de caixa projetado avançado (P0)
- **US-FINA-013** · Projeção diária 30/60/90d consolidado todas contas bancárias + alerta descoberto
- **US-FINA-014** · Cenários what-if: antecipar AR Asaas, postergar AP, simular empréstimo
- **US-FINA-015** · Sugestão IA Jana de ação quando descoberto detectado (`sugerir_acao_fluxo_caixa`)
- **US-FINA-016** · Cache projeção invalidado em `TituloBaixado`/`TituloCriado`/`TituloCancelado` (5min TTL)

### Plano de Contas BR (P1)
- **US-FINA-017** · Templates BR: Simples Nacional / Lucro Presumido / Lucro Real (seed install)
- **US-FINA-018** · UI hierárquica plano contas (TanStack tree) com drag-drop reordenar + criar/inativar
- **US-FINA-019** · Importar plano contas custom CSV (formato CRC padrão)

### Categorização IA (P1)
- **US-FINA-020** · Jana sugere `plano_conta_id` em batch pra extrato sem categoria (confidence-ranked)
- **US-FINA-021** · Auto-aceitar categorização confidence >90% após N=3 confirmações histórico (`fina_ia_categorization_log`)
- **US-FINA-022** · Botão "Treinar Jana" — Larissa corrige categoria errada → feedback retraina prompt few-shot

### Margem real per venda (P1 diferencial)
- **US-FINA-023** · Margem bruta per venda: cruza Inventory custo + Comissão + Impostos + Frete
- **US-FINA-024** · Comparativo margem produto vs média 90d (badge 🟢🟡🔴)
- **US-FINA-025** · Relatório "Top 10 produtos margem < 15%" — alerta repricing

### Dunning automática (P1)
- **US-FINA-026** · Régua cobrança configurável (D+1/D+7/D+15 canal WhatsApp/email)
- **US-FINA-027** · Template mensagem dunning per business (variáveis: `{cliente}`, `{valor}`, `{vencimento}`, `{link_pagar}`)
- **US-FINA-028** · Audit log envios + status engaged (cliente clicou link)

### Bridge NfeBrasil (P2)
- **US-FINA-029** · `NfeEmitida` listener → entry contábil + atualiza projeção realtime
- **US-FINA-030** · Reconciliação NFe emitida × `fin_titulos` (detecta NFe sem título — alerta)

### Conciliação cartão crédito (P2)
- **US-FINA-031** · Conciliação adquirente (Cielo/Rede/Stone) — desconta taxa + lança AR D+30
- **US-FINA-032** · Forecast recebíveis cartão (D+1 débito / D+30 crédito parcelado)

**Total US-FINA propostas:** 32 (vs 25 originalmente cogitadas — 5 a mais pra cobrir mercado completo)
**Estimate calibrado [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md):**
- P0 (12 stories): ~6 dias-IA-pair
- P1 (15 stories): ~9 dias-IA-pair
- P2 (5 stories): ~3 dias-IA-pair
- **Total: ~18 dias-IA-pair** (margem 2x = ~36 dias úteis calendário)

## §6 — Decisões pendentes top 3

1. **Extender Modules/Financeiro vs criar Modules/FinanceiroAvancado separado?** Argumento separar: SoC brutal (ADR 0094 §5); Financeiro = operacional, FinanceiroAvancado = analítico/IA. Argumento juntar: 1 módulo só pra Larissa entender. **Proposta:** separar (ADR D1).
2. **DRE realtime calc vs snapshot mensal congelado?** Realtime mais atual; snapshot mais auditável + LGPD-friendly (sem recompute histórico). **Proposta:** híbrido — realtime exibição + snapshot ao fechar mês (ADR D2).
3. **Auto-aceitar match conciliação threshold?** 95% suficiente? 99%? Trade-off precisão × velocidade. ROTA LIVRE 99% volume — não pode quebrar. **Proposta:** 95% + janela reverter 24h (ADR D6).

## §7 — Riscos top 3

1. **R1 — Drift cálculo DRE Financeiro vs Accounting:** dois módulos parallel (ARQ-0005) podem mostrar números diferentes pro mesmo período. **Mitigação:** test contract `DreParityTest::biz4_caixa_maio2026_financeiro_eq_accounting()` + alerta em `jana:health-check`.
2. **R2 — Categorização IA Jana pode rotular errado e enviezar DRE:** Jana sugere `4.2.1.05 Insumos` pra fornecedor que é `4.5.1.01 Tarifa`. Larissa aceita batch sem revisar. **Mitigação:** confidence floor 70% + flag visual + audit `fina_ia_categorization_log` + Larissa pode reverter batch inteiro.
3. **R3 — Conciliação automática Inter PJ derrubada (banco API down):** Larissa fica horas sem conciliar. **Mitigação:** fallback OFX manual upload (US-FINA-006) + alerta proativo Centrifugo "Inter API down há 2h, suba OFX manual".

## §8 — Referências cruzadas

- `Modules/Financeiro/SCOPE.md` — escopo operacional autoritário
- [SPEC.md](../Financeiro/SPEC.md) — US-FIN-001..014 base operacional
- [COMPARATIVO_CONCORRENCIA.md](../Financeiro/COMPARATIVO_CONCORRENCIA.md) — score Capterra-style 53/80
- `Modules/RecurringBilling/Services/Boleto/Drivers/` — 3 drivers (Asaas/Inter/C6) prontos
- [ADR ARQ-0005](../Financeiro/adr/arq/0005-financeiro-vs-accounting-paralelo.md) — paralelismo Financeiro × Accounting
- [ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — FSM canônico em prod
- [_ANALISE-FINANCEIRA-CROSS-CLIENTE.md](../../research/clientes-legacy-officeimpresso/_ANALISE-FINANCEIRA-CROSS-CLIENTE.md) — 4 candidatos OfficeImpresso pricing (Martinho R$ [redacted Tier 0]M inadimplência → P0 dunning)
- ADR proposal: [`memory/decisions/proposals/drafts/financeiro-avancado-dre-fluxo-conciliacao.md`](../../decisions/proposals/drafts/financeiro-avancado-dre-fluxo-conciliacao.md)
- [MATRIZ-ROI.md](MATRIZ-ROI.md)
- [ROADMAP.md](ROADMAP.md)

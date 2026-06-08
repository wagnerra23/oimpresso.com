---
title: ROADMAP Modules/FinanceiroAvancado
date: 2026-05-12
status: draft
estimate_calibration: ADR 0106 (10x IA-pair + margem 2x)
---

# ROADMAP — Modules/FinanceiroAvancado

> 5 fases. Estimates **calibrados ADR 0106** — fator 10x IA-pair + margem 2x. Cliente piloto **ROTA LIVRE biz=4** + **canary clientes legacy OfficeImpresso** (Martinho/Vargas/Extreme/Gold).
> Sinal qualificado (ADR 0105): só ativar feature se cliente paga + reporta OU métrica detecta drift.

---

## Fase 0 — Foundation (1 sprint = 1 semana)

**Objetivo:** scaffold `Modules/FinanceiroAvancado` + bridge contracts + Pest fixtures.

**Stories:**
- Scaffold módulo nWidart com 8 peças obrigatórias (skill `criar-modulo`)
- Migrations: `fina_bank_reconciliations`, `fina_cash_flow_projections`, `fina_dre_snapshots`, `fina_dre_share_tokens`, `fina_margin_analysis`, `fina_ia_categorization_log`, `fina_dunning_rules`, `fina_dunning_log`, `fina_plano_conta_templates`, `fina_cash_flow_scenarios`
- Models + traits `BusinessScope` + global scope (ADR 0093 Tier 0)
- Service contracts: `InventoryCostResolver`, `RepairLaborResolver`, `JanaCategorizationAgent`
- Pest fixtures multi-tenant: biz=1 (default — ADR 0101) + biz=99 (cross-tenant) + biz=4 (ROTA LIVRE smoke)
- Topnav entry agrupado "Financeiro › Avançado" via DataController
- Permissions Spatie `financeiroavancado.*` (10 permissions)
- Install command `php artisan financeiro-avancado:install`

**Entrega:** módulo ativo, sem feature, 100% test cobertura baseline. PR ~250 linhas.

**Estimate:** 2 dias-IA-pair (~5 dias calendário margem 2x).

**Gate:** Wagner aprova D1-D6 do ADR proposal ANTES de Fase 0 começar.

---

## Fase 1 — Conciliação Bancária Automática (2 sprints)

**Objetivo:** match automático extrato Inter + Asaas → `fin_titulos`. **Alavanca diferencial** ROTA LIVRE + Martinho.

**Stories implementadas:**
- **US-FINA-001** Conciliar Inter PJ automático (match exato + tolerância 3d)
- **US-FINA-002** Conciliar Asaas (boleto + Pix)
- **US-FINA-003** Match IA 80-95% sugere
- **US-FINA-004** Auto-aceitar >95% + janela reverter 24h
- **US-FINA-005** Painel `/financeiro-avancado/conciliacao`
- **US-FINA-006** OFX manual fallback (multi-banco: Sicoob/BTG/Cora/Bradesco/Itaú)

**Dependências:**
- Service `Modules/RecurringBilling/Services/Banking/InterBankingClient` (existente)
- Driver `AsaasDriver` (existente RecurringBilling)
- Tool MCP `Modules/Jana/Ai/Agents/CategorizationAgent` (novo — calibração few-shot ROTA LIVRE histórico 90d)

**Cliente piloto:** ROTA LIVRE biz=4 (sync Inter PJ + Asaas já ativo CYCLE-05 Inter PJ prod).

**Cenário canary:** **Martinho** (inadimplência 76,7%) — feature **mais valiosa** pra ele (dunning + conciliação). Wagner pitch focado.

**Gate:** smoke biz=4 real (ROTA LIVRE) 7 dias canary com Larissa validando 50+ conciliações automáticas. Reversão <2% accept rate. Falsos-positivos zero (sem baixa errada de outro título).

**Estimate:** 6 dias-IA-pair (~12 dias calendário margem 2x).

---

## Fase 2 — DRE BR Formal + Fluxo Projetado IA (2 sprints)

**Objetivo:** DRE 10 linhas BR completo + fluxo caixa diário 30/60/90d + alerta IA. **Alavanca compra** Larissa+contador.

**Stories implementadas:**
- **US-FINA-007** DRE 10 linhas BR (caixa/competência)
- **US-FINA-008** Drill-down DRE
- **US-FINA-009** Export PDF DRE
- **US-FINA-010** Token shareable 7d contador
- **US-FINA-011** Snapshot congelado fechamento mês
- **US-FINA-012** DRE comparativo 12 meses gráfico
- **US-FINA-013** Projeção diária 30/60/90d alerta descoberto
- **US-FINA-014** Cenário what-if antecipar/postergar/empréstimo
- **US-FINA-015** Sugestão IA Jana descoberto
- **US-FINA-016** Cache projeção invalidado eventos

**Dependências:**
- `RelatoriosController@montarDre` existente (US-FIN-014) — refactor pra usar `fina_dre_snapshots`
- `RelatoriosController@montarFluxo` existente — refactor pra usar `fina_cash_flow_projections`
- Plano de Contas seed default Simples Nacional (Fase 4 — neste momento manual)
- Tool MCP `Modules/Jana/Ai/Tools/FluxoCaixaAdvisor` (novo)

**Cliente piloto:** ROTA LIVRE biz=4 (Larissa fecha mês com contador).

**Gate:** contador externo Larissa aprova DRE 1 mês completo via token shareable. PDF tem cabeçalho fiscal correto (CNPJ 73.306.573/0001-11). Fluxo projetado 30d bate ±5% com realizado depois.

**Estimate:** 8 dias-IA-pair (~16 dias calendário margem 2x).

---

## Fase 3 — Categorização IA Jana + Margem Real per Venda (2 sprints)

**Objetivo:** **diferencial estado-da-arte BR** — features score 40+ ROI×D/C.

**Stories implementadas:**
- **US-FINA-020** Jana sugere `plano_conta_id` batch
- **US-FINA-021** Auto-aceitar >90% após N=3 confirmações histórico
- **US-FINA-022** "Treinar Jana" — feedback few-shot
- **US-FINA-023** Margem bruta per venda (Inventory+Comissão+Imp+Frete)
- **US-FINA-024** Comparativo vs média 90d (badges)
- **US-FINA-025** Relatório Top 10 produtos margem <15%

**Dependências:**
- `Modules/Jana/Ai/Agents/CategorizationAgent` calibrado (Fase 1)
- `InventoryCostResolver` service contract (Fase 0)
- `RepairLaborResolver` quando OS linkada (Fase 0)
- `Modules/Comissao` ainda não existe — usar fallback **valor fixo per-venda** (config tenant) até Comissao módulo nascer

**Cliente piloto:** ROTA LIVRE biz=4 (50k transactions histórico 24m) + canary Extreme legacy (PCP grafica industrial — margem analítica produto-a-produto fit ideal).

**Gate:** Jana categorização confidence média >85% em 30 dias com Larissa validando. Margem real bate ±3% com cálculo manual contábil em 10 vendas amostra.

**Estimate:** 7 dias-IA-pair (~14 dias calendário margem 2x).

---

## Fase 4 — Plano de Contas BR + Dunning Automática + Bridge NfeBrasil (2 sprints)

**Objetivo:** completar feature parity Conta Azul/Omie + ataque inadimplência Martinho.

**Stories implementadas:**
- **US-FINA-017** Templates BR (SN/LP/LR) seed install
- **US-FINA-018** UI hierárquica plano contas drag-drop
- **US-FINA-019** Import CSV custom (formato CRC)
- **US-FINA-026** Régua dunning configurável
- **US-FINA-027** Templates mensagem dunning per business
- **US-FINA-028** Audit envios + engaged
- **US-FINA-029** Listener `NfeEmitida` → entry contábil
- **US-FINA-030** Reconciliação NFe × fin_titulos

**Dependências:**
- `Modules/NfeBrasil` (existente — `NfeBrasil_AUTO_EMISSION_NFCE` flag ON)
- WhatsApp gateway (Twilio/Z-API) — Vault credentials per business
- Email SMTP (existente UPos)

**Cliente piloto:** **Martinho** (cliente sinal qualificado **forte** — R$ [redacted Tier 0]M inadimplência). Pitch outbound focado em "dunning automática reduz inadimplência 30% em 90 dias" (benchmark Asaas).

**Gate:** Martinho 1 mês uso dunning → redução inadimplência mínimo 10% (validação ROI).

**Estimate:** 7 dias-IA-pair (~14 dias calendário margem 2x).

---

## Fase 5 — Conciliação Cartão Crédito + OCR Boleto + Snapshots Históricos (1 sprint)

**Objetivo:** fechar lacunas competitivas restantes vs Conta Azul/Omie.

**Stories implementadas:**
- **US-FIN-005** OCR boleto upload (mover daqui de Modules/Financeiro Onda 4)
- **US-FINA-031** Conciliação adquirente (Cielo/Rede/Stone) — taxa + AR D+30
- **US-FINA-032** Forecast recebíveis cartão (débito D+1 / crédito parcelado)
- **Backfill** `fina_margin_analysis` histórico 12m (job batch OOH)
- **Backfill** `fina_dre_snapshots` histórico congelado 12m (Wagner valida pontos chave)

**Dependências:**
- API extrato adquirente (Cielo IO / Rede / Stone) — auth + cert
- OCR service (Tesseract local CT 100 ou Google Vision $0.0015/imagem)

**Cliente piloto:** Vargas (recapagem) + ROTA LIVRE.

**Gate:** OCR boleto extrai linha digitável + valor + vencimento + beneficiário com >90% acurácia em 50 boletos amostra (ROTA LIVRE 30d real).

**Estimate:** 5 dias-IA-pair (~10 dias calendário margem 2x).

---

## Resumo agregado

| Fase | Duração | US-FINA delivered | Cliente piloto | Outcome |
|---|---|---|---|---|
| 0 | 1 sem | scaffold | — | módulo ativo, 0 features |
| 1 | 2 sem | US-FINA-001..006 | ROTA LIVRE + Martinho canary | Conciliação auto Inter+Asaas |
| 2 | 2 sem | US-FINA-007..016 | ROTA LIVRE + contador | DRE BR + Fluxo IA |
| 3 | 2 sem | US-FINA-020..025 | ROTA LIVRE + Extreme | Diferencial Jana+Margem |
| 4 | 2 sem | US-FINA-017..019, 026..030 | Martinho | Plano Contas BR + Dunning + NFe |
| 5 | 1 sem | US-FINA-031..032 + OCR + backfill | Vargas + ROTA LIVRE | Parity Conta Azul/Omie |
| **Total** | **10 sem** | **32 stories** | 4 clientes pilotos | Score 199/220 (90%) |

**Total estimate:** ~35 dias-IA-pair / ~70 dias calendário margem 2x / 10 semanas (~2.5 meses).

## Métricas de saúde

Adicionar a `php artisan jana:health-check`:
1. **conciliacao_match_rate_30d** — % extrato lançamentos com `conciliacao_titulo_id != null` últimos 30d (target >85%)
2. **dre_parity_financeiro_accounting** — soma Receita Líquida `Modules/Financeiro` vs `Modules/Accounting` mês corrente (diferença <1% — ADR ARQ-0005)
3. **jana_categorization_confidence_avg** — média confidence categorizações últimos 30d (target >85%)
4. **margem_analise_coverage** — % `transactions` finalizadas com `fina_margin_analysis` row últimos 30d (target >95%)
5. **dunning_engaged_rate** — % `fina_dunning_log` com `engaged=true` últimos 30d (target >25% — benchmark Asaas é 30%)

## Cuidados / não-fazer

1. **NÃO rodar backfill `fina_margin_analysis` em horário comercial** ROTA LIVRE biz=4 — locks `purchase_lines` durante ~5min derrubam POS
2. **NÃO ativar dunning automática biz=4 antes de Larissa testar mensagem template** — risco mandar mensagem inapropriada a cliente Larissa (Termas do Gravatal SC é cidade pequena, conhecidos)
3. **NÃO compartilhar token DRE expirado** — JWT precisa rotação ativa + audit `acessado_em[]` LGPD
4. **NÃO permitir `withoutGlobalScopes` em `fina_*` tables sem comentário `// SUPERADMIN`** — Tier 0 ADR 0093
5. **NÃO migrar histórico DRE 12m sem snapshot anterior preservado** — append-only fiscal
6. **NÃO substituir Modules/Accounting** — bridge opt-in ADR ARQ-0005 mantido. `accounting_account_transactions` continua autoritário formal.

## Quando reavaliar roadmap

- Se Wagner rejeitar D1 (módulo separado) → reescrever estendendo `Modules/Financeiro`
- Se ROTA LIVRE rejeitar Fase 1 conciliação automática (Larissa não confia em auto-aceite) → ajustar D6 threshold 99% + revisar
- Se Martinho rejeitar pitch dunning → reordenar Fase 4 (Dunning vai pra fim, Bridge NFe pra Fase 2)
- Se Reforma Tributária IBS/CBS 2027 publicar estrutura DRE nova → revisar Fase 2 (D5 templates) + criar nova ADR antes Fase 4

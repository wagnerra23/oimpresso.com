---
module: Financeiro
purpose: "Razão financeira do negócio — espelha vendas, compras e caixa do core em títulos (fin_titulos) e opera o ciclo do dinheiro sobre eles: contas a pagar e receber, baixa, conciliação bancária OFX, fluxo de caixa, DRE BR e portal read-only do contador externo."
migracao_ui: "pendente — tem Blade servido, sem duvida de escopo; fila em module-surface --migracao"
contains:
  - "Api/FinanceiroLancamentoAcoesController — API do app (tela 06, LANCAMENTO_ACOES): detalhe, alterar, baixar, retornar, excluir, reativar e recibo em /api/app/financeiro/lancamentos/{id}; movido de app/Http/Controllers/Api/App (DependencyDirectionTest)"
  - "AdvisorAccessController — Onda 31 US-FIN-037 grant/revoke acesso contador parceiro (biz-side, consent LGPD)"
  - "Advisor/AdvisorAuthController — Onda 31 US-FIN-037 login/logout guard web-advisor isolado"
  - "Advisor/AdvisorPortalController — Onda 31 US-FIN-037 dashboard /advisor (cards Meus clientes)"
  - "Public/PagamentoPublicoController — US-FIN-081 página pública /pagar/{token}: cliente paga o título a receber por Pix ou boleto (flag FINANCEIRO_LINK_PAGAMENTO_ENABLED)"
  - "TituloAutoService — espelha venda/compra/despesa em fin_titulos (Observers de transactions e transaction_payments); aberto de venda/compra = bruto − baixas do título (US-FIN-073)"
  - "ResyncFromCoreCommand — financeiro:resync-from-core re-espelha valor_total inflado (num_uf) com --apply; modo --aberto só leitura lista títulos parciais com aberto divergente das baixas (US-FIN-073)"
  - "AssinaturaController — FIN-004 atualizar cobrança recorrente (multi-tenant Tier 0, gateway delegado a AssinaturaCobrancaService)"
  - "CaixaController — F6 Soft wrapper Inertia /financeiro/caixa read-only sobre cash_registers core UltimatePOS (não migra dados; lifecycle abrir/fechar continua na header POS via CashRegisterController core)"
  - "CategoriaController"
  - "RateioService — US-FIN-075 rateio do título em centros de custo (fin_centros_custo + fin_titulo_rateios): soma fecha valor_total em centavos inteiros; título sem rateio segue válido"
  - "CobrancaController — F3 PaymentGateway UI Tela 1 (substitui /financeiro/boletos com escopo expandido boleto+pix+pix_recv+card; ADR 0144 + 0170)"
  - "ConciliacaoController — Onda 19 OFX MVP + scaffold Anexos/Aprovação"
  - "ContaBancariaController"
  - "ContaPagarController"
  - "ContaReceberController"
  - "CoworkSidebarController — endpoint JSON Mock Cowork (sidebar real via ShellMenuBuilder + 3 camadas habilitação, sem hardcode business_id; ADR 0093 Tier 0)"
  - "CusteioRkwController + CusteioRkwService — custeio RKW e ponto de equilíbrio do mês (US-FIN-070 · UC-FLX-20): GET só leitura, aplicar o custo-hora sugerido é POST explícito; horas do PCP pelo contrato app/Contracts/Producao/HorasDosPostos.php (comando financeiro:custeio-rkw)"
  - "DashboardController"
  - "DataController"
  - "DreController — Wave reaplicação canon 2026-05-20 US-FIN-014a (tela dedicada /financeiro/dre, hierarquia clássica header/item/subtotal com highlight, % RL sinal preservado, Δ% mês ant., export PDF/Excel/CSV; substitui tab DRE legada em RelatoriosController)"
  - "ExtratoController"
  - "FinanceiroController"
  - "FluxoController"
  - "ImpostosController — F2 PR-2 2026-06-10 tela /financeiro/impostos (DAS estimado ≈6% regime caixa + guias lançadas + calendário + NF↔título; Lançar a pagar cria título payable idempotente; estimativa visual — apuração oficial no módulo Fiscal)"
  - "InstallController"
  - "PlanoContaController — Onda 18 tela /plano-contas dedicada + Fluxo fallback sem conta"
  - "RelatoriosController"
  - "UnificadoController"
  - "ReguaCobrancaJob + comando financeiro:regua-cobranca — US-FIN-071 · UC-FLX-29 régua de cobrança do título vencido (e-mail D+1/D+5/D+15 com opt-in LGPD, histórico idempotente em fin_titulo_lembretes, tarefa na última etapa pelo contrato AgendaDeTarefas de app/Contracts/Crm, implementado no Crm); desligada por padrão em business.common_settings"
not_contains:
  - "Conhecimento canônico (ADRs, sessions) → Modules/KB"
  - "Tasks Jira-style → Modules/Forja"
  - "MCP server admin → Modules/Forja"
  - "Emissão e cancelamento de NFS-e (o Financeiro só observa a mudança de status para lançar as retenções como baixa — US-FIN-072) → Modules/NFSe"
depends_on:
  - Sells
  - Compras
trust_required: L3
owner: wagner
permission_prefix: financeiro.*
charter_adr: 0080
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
url_prefixes:
  - /financeiro/*
  - /advisor/* (portal do contador — login/logout proprios)
  - /api/v1/* (scaffold nWidart · auth:sanctum — co-provido por NfeBrasil e RecurringBilling)
drift_alerts: []
---

# Modules/Financeiro

## Missão

Contas a pagar/receber, fluxo de caixa, DRE BR.

## Trust level

**L3** — ver [TRUST-TIERS.md](../../governance/TRUST-TIERS.md).

## Quando NÃO é tocado

Ver `not_contains[]` no frontmatter. Em dúvida, consulte
[ARCHITECTURE.md](../../governance/ARCHITECTURE.md).

---

- **v1.0.0** (2026-05-05) — SCOPE.md inicial. Gerado em batch via Fase 3.4 do ADR 0079.

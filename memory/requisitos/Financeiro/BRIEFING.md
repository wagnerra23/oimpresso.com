# BRIEFING — Modules/Financeiro

> Visão unificada de AR/AP (Contas a Receber / Contas a Pagar) + Fluxo de Caixa + Boletos + Conciliação OFX + Plano de Contas BR + Workflow Aprovação. Cockpit V2 persona Eliana [E] (financeiro escritório, densidade alta, atalhos teclado).

**Última atualização:** 2026-05-20 tarde · **27 PRs Ondas 12-26** ([#1158→#1252](https://github.com/wagnerra23/oimpresso.com/pulls)) · Bundle copy CSS canon 9054 LOC · 7 funções novas · paridade canon 4.8→**9.8/10** · drawer detalhe **10/10** · cobertura funcional 75%→**87%**.

## ⚠️ Estado de boletos/cobrança 2026-06-08 (corrige pegadinha)

> **Boleto NÃO é mais mock.** A emissão real vive em `Modules/PaymentGateway` (não no `CnabDirectStrategy` legado, que está sendo aposentado — `/financeiro/boletos` → 301 `/financeiro/cobranca`).

| Item | Estado |
|---|---|
| **Funcionando em PROD** | Boleto via **Banco Inter** (`InterDriver` — API Cobrança v3, OAuth2 + mTLS), **biz=1** (Wagner/interno) |
| **Driver mais maduro** | `InterDriver` — único com mTLS real (cert materializado de base64 inline em `rb_boleto_credentials` via `scripts/inter-credentials/install-biz.py`), `registerWebhook`, polling de reconciliação (`InterReconcilePixCommand`), import de boletos pagos por fora (legado WR) |
| **Demais drivers** | Implementados + testados, **aguardando credencial ativa** pra ligar: Asaas (boleto/pix/card), C6, BCB Pix, Pagar.me, Sicoob API, + 11 CNAB file-based (Bradesco/Itaú/BB/Santander/Caixa/Sicoob/Ailos/Sicredi/Cresol/Banrisul/BTG) |
| **biz=4 (Larissa/ROTA LIVRE)** | Boleto Inter **ainda não ligado** (sem credencial ativa comprovada) |

**Pré-condição pra emitir de verdade:** `PaymentGatewayCredential` ativa cadastrada em `/settings/payment-gateways` e vinculada à conta bancária (wizard step 3). Sem isso, `PaymentGatewayService::for($account)` lança `CredentialMisconfiguredException`.

**Caminho real:** tela `/financeiro/cobranca` → "Emitir cobrança" `tipo=boleto` → `CobrancaController::store` → `PaymentGatewayContract::emitirBoleto()` → driver real → persiste `Cobranca` → webhook reconcilia pagamento → evento `CobrancaPaga` → cria baixa no Financeiro (`OnCobrancaPagaCreateFinanceiroTitulo`). ADR 0144 + 0170.

## Estado UI 2026-05-20 tarde — Drawer canon 10/10 ✅

`/financeiro/*` (12 telas) com **paridade visual 9.8/10** vs canon `financeiro-app.jsx`. Bundle CSS de **9054 LOC** importado inteiro em `resources/css/cowork-canon-financeiro-bundle.css` (regra Tier 0 [`feedback-cowork-bundle-aplicar-inteiro`](../../reference/feedback-cowork-bundle-aplicar-inteiro.md) validada 4ª vez).

**Ondas 22-26 hoje (~40min) — drawer detalhe canon parity 100%** ([session log](../../sessions/2026-05-20-financeiro-drawer-canon-ondas-22-26.md)):
- **22/22b** PR [#1243](https://github.com/wagnerra23/oimpresso.com/pull/1243) [#1245](https://github.com/wagnerra23/oimpresso.com/pull/1245) — `fin-cowork` no SheetContent (Portal scope) + bg branco + CSS vars no portal (root cause: Sheet shadcn Portal renderiza fora do wrapper `.fin-cowork` → regras prefixadas não aplicavam)
- **17/18/19/21** PR [#1247](https://github.com/wagnerra23/oimpresso.com/pull/1247) — drawer body canon: header DirIcon + UPPERCASE "A receber · #ID" + date 22px + amount 34px + grid 2-col (Contraparte/Categoria/Canal/Documento + Conta col-span-2) + bloco Conciliação extrato + footer Ver NFe/Cobrar/Recebi-Paguei verde
- **23** PR [#1248](https://github.com/wagnerra23/oimpresso.com/pull/1248) — glyph tabs align (✦ ✎ wrap em span com inline-flex)
- **24** PR [#1250](https://github.com/wagnerra23/oimpresso.com/pull/1250) — bordas globais Financeiro/* (border-stone-100 → oklch 0.92 dentro de `.fin-cowork`)
- **25** PR [#1251](https://github.com/wagnerra23/oimpresso.com/pull/1251) — drawer overflow horizontal (nav margin negativo + `px-5` body)
- **26** PR [#1252](https://github.com/wagnerra23/oimpresso.com/pull/1252) — `px-5` page wrapper `.fin-curadoria` (KPI strip + sparkline + filters + tabela respiram do edge)

**Plano B AppShellV2 nu** (2026-05-18 noite) preservado mas inativo — `mock_cowork_mode=false` + `sidebar_wrap_enabled=false` default. Reversibilidade 5 camadas mantida.

## Telas em prod (12 canon)

| Rota | Onda | Nota | Funções |
|---|---|---|---|
| `/financeiro` (Dashboard) | 12.8+15 | 9/10 | KPI defer + saldo bancos + filtros tabela |
| `/financeiro/unificado` (baseline) | 12-17 | **10/10** | KPI hero warm + 4 lifecycle pills + drawer Aprovação/Anexos + ⌘K |
| `/financeiro/unificado/novo` | 13 | 9.5/10 | Picker Receber/Pagar 2 cards canon |
| `/financeiro/contas-receber` | 12.8 | 8.5/10 | CRUD + emitir boleto |
| `/financeiro/contas-pagar` | 12.8 | 8.5/10 | CRUD + 1-clique pagar |
| `/financeiro/contas-bancarias` | 12.8 | 9/10 | Configurar boleto wizard 3 steps |
| `/financeiro/categorias` | 12.8+13 | 9/10 | Categorias livres + vínculo opcional Plano Contas |
| `/financeiro/extrato/{contaId}` | 12.8 | 8.5/10 | Movimento bancário |
| `/financeiro/fluxo` | 12.8+15+18 | 9/10 | Projeção 35d + banner CTA sem conta |
| `/financeiro/relatorios` | 14+15 | 9/10 | DRE comparativo + Fluxo realizado + Resumo |
| `/financeiro/cobranca` | 15 híbrido pg-shell | 8/10 | Emitir cobrança real (boleto/pix/card) via PaymentGateway — **Inter LIVE biz=1**, demais drivers aguardando credencial |
| **`/financeiro/plano-contas`** (Onda 18) | 18 | 9/10 | **Hierárquica BR 49 entries** |
| **`/financeiro/conciliacao`** (Onda 19) | 19 | 9/10 | **OFX upload + fuzzy match** |
| `/financeiro/assinaturas/atualizar` | 14 | 8/10 | Valor/ciclo/forma pgto |

## Funções (53/61 = 87% cobertura)

### Ondas 12-21 adicionou 7 funções novas
1. Tela **Plano de Contas** hierárquica BR
2. Tela **Conciliação OFX MVP**
3. Upload OFX + parser STMTTRN regex
4. Fuzzy match automático (score 85%)
5. Confirmar/ignorar match
6. **Anexos NF** storage local idempotência SHA-256
7. **Workflow aprovação** (solicitar/aprovar/rejeitar)

### Backbone (46 prévias)
Lançamentos CRUD + 1-clique baixa + filtros lifecycle multi-select + densidade + Plano Contas filtro + ⌘K palette + cross-link + Anomaly + Party history + Frescor + Conferido + Comentários + Audit trail + Sparkline + Inertia::defer + Categorias CRUD + Configurar boleto + Emitir boleto + Cobrança gateways + DRE + Resumo + Export CSV + Atalhos + Modo apresentação + Checklist + Folha jurídica + Favoritos.

## Tabelas DB (3 novas Ondas 19-21 + 8 existentes)

**Novas:** `fin_bank_statement_lines` · `fin_titulo_anexos` · `fin_titulos` ALTER `aprovacao_*` campos.

**Existentes:** `fin_titulos` (47K+ rows MARTINHO) · `fin_titulo_baixas` · `fin_titulo_comments` · `fin_contas_bancarias` · `fin_planos_conta` (49 BR seedados) · `fin_categorias` · `fin_extrato_lancamentos` · `fin_boleto_remessas`.

## Métricas pós-Ondas 12-21

| Métrica | Pré-Onda 12 | Pós-Onda 21 |
|---|---|---|
| Funções implementadas | 46/61 (75%) | **53/61 (87%)** |
| Coerência canon visual | 4.8/10 | **9.5/10** |
| Coerência inter-telas | 50% | **95%** |
| Rotas validadas | 26 (14 GET + 12 POST) | **33** (15+18) |
| Telas canon | 10 | **12** |
| 404 ativos | 4 | **0** |
| Bundle CSS | cherry-pick fragmentado | **9054 LOC canon** |

## Inconsistências catalogadas

📄 [`AUDIT-FUNCOES-2026-05-19.md`](AUDIT-FUNCOES-2026-05-19.md) — inventário completo 46 funções + 15 faltantes + 7 inconsistências.

✅ **Resolvidas Ondas 12-21:** A (DRE Maio MARTINHO) · D (botão Plano contas 404) · E (botão Conciliar 404) · F (KpiCard shadcn) · 6 inconsistências menores.

⏳ **Pendentes (Ondas 22-27):** B (Fluxo sem conta MARTINHO — UI cadastro) · C (Categorias vs Plano contas — decisão produto) · G (AssinaturaAtualizar cosmético) · H (UI lista anexos GET) · I (Pill aprovacao_status na tabela) · J (Permissions Spatie aprovar).

## Roadmap próximas Ondas (P0/P1)

- **27**: UI lista anexos GET no drawer + coluna `aprovacao_status` na tabela + permissions Spatie `financeiro.titulo.aprovar` (era Onda 22 original — slot tomado por canon parity drawer)
- **28**: ConciliacaoService dedicated CNAB + Open Banking API real
- **29**: Aging buckets <30/30-60/60-90/90+
- **30**: Notificações vencimento próximo (e-mail/WhatsApp)
- **31**: Border bump global em outras áreas (Vendas/Produto/Purchase — escopo decidido caso a caso)

---

## Estado histórico — AppShellV2 nu (Plano B canon, 2026-05-18 noite)

`/financeiro/*` voltou pro **AppShellV2 + sidebar UltimatePOS canônico** via `DataController` + `package_details` + Spatie permissions (Tier 0 universal, zero hardcode biz). Pages Inertia `Pages/Financeiro/*.tsx` renderizam direto — controllers caem em `Inertia::render` normal porque trait `RendersMockCowork::tryRenderMockCowork()` retorna `null`.

**Por que:** 3 PRs cherry-pick Cowork falhos (#1085 → #1091 → #1092) + tentativas 4-5 mock+wrap erraram layout 2026-05-18 inteiro. Regra Tier 0 nova (`proibicoes.md §Design System / Pacote Cowork novo`): primeira aplicação Cowork = bundle copy `styles.css` INTEIRO 1×, sem cherry-pick. Replanejamento Cowork Financeiro fica em PR separado quando estiver pronto pra bundle copy direito.

**Reversibilidade 5 camadas** (canon em [feedback-cowork-bundle-aplicar-inteiro.md §Apêndice Plano B](../../reference/feedback-cowork-bundle-aplicar-inteiro.md)): env var `FINANCEIRO_MOCK_COWORK=true` / `FINANCEIRO_SIDEBAR_WRAP=true` / `localStorage __OIMPRESSO_SIDEBAR_OFF__='1'` / git revert / branch snapshot.

**Smoke prod validado** (2026-05-18 noite pós-merge):
- `bootstrap/cache/config.php` literal: `'mock_cowork_mode' => false,` + `'sidebar_wrap_enabled' => false,`
- `curl -sv /financeiro/{unificado,fluxo,boletos}` → 302 `/login` **sem header `X-Mock-Cowork`** (antes era `X-Mock-Cowork: 1` injetado pelo trait)
- Regression `/pos/sells` 302 → inalterado
- Visual logado biz=4/biz=1: pendente Wagner (Chrome MCP ou validação manual)

**Artefatos Cowork preservados** (dormentes, reativáveis): `prototipo-ui/cowork/*.html`, bridges JS (`_oimpresso-bridge-*.js`), trait `RendersMockCowork`, componentes `Pages/Financeiro/_components/Fin*.tsx`. NÃO deletados — Plano B é pausa, não desistência.

## Estado consolidado (Wave 18 RETRY — saturação 68→97)

| Dimensão (rubrica module-grade-v3) | Wave 17 | Wave 18 | Wave 18 RETRY | Δ total |
|---|---|---|---|---|
| D1 Multi-tenant Tier 0 | 23/30 | 30/30 | 30/30 | +7 (BaixaRepository + MultiTenantComprehensiveTest 11 datasets cross-tenant biz=99) |
| D4 SoC brutal (Service/Repository ratio) | 6/20 | 17/20 | 20/20 | +14 (BaixaRepository extraído, 4 métodos canônicos type-hinted, singleton no Provider) |
| D6 Inertia::defer pattern | 6/10 | 10/10 | 10/10 | +4 (Wave 17 já saturou — Dashboard/Fluxo confirmados) |
| D7 LGPD + retention + activitylog | 7/10 | 10/10 | 10/10 | +3 (Wave 17 já saturou — module.json lgpd_compliance) |
| D8 FormRequests tipados | 4/8 | 8/8 | 8/8 | +4 (StoreBaixaRequest + UpdateAccountRequest 5°+6° request) |
| D9 OTel spans + Health command | 4/7 | 7/7 | 7/7 | +3 (BaixaRepository spans + reflection Pest) |

## Capacidade canônica (estado real)

- **AR/AP unificado** — Visão Unificada `/financeiro/unificado` (ADR Cockpit V2) — coluna única Títulos (`fin_titulos`) tipo `receber`/`pagar`, baixas append-only (`fin_titulo_baixas`), idempotência via UNIQUE(business_id, origem, origem_id, parcela_numero).
- **Fluxo de Caixa projeção 35d** — `FluxoCaixaService::projetar(biz, dias)` agrega saldo_cached + títulos futuros + baixas históricas, retorna shape Inertia pronto (KPIs: saldo_hoje, saldo_30d, pior_dia, margem_mínima R$ [redacted Tier 0]).
- **Boletos / Cobrança (estado real 2026-06-08)** — emissão **real** via `Modules/PaymentGateway` (`/financeiro/cobranca` → `PaymentGatewayContract::emitirBoleto()`). **Inter LIVE em prod biz=1** (`InterDriver` OAuth2+mTLS). Demais drivers (Asaas/C6/BCB Pix/Pagar.me/Sicoob API + 11 CNAB) prontos e testados, aguardando credencial ativa. O `CnabDirectStrategy` legado (offline `gerado_mock`, sem registrar no banco) está sendo aposentado — ver callout "Estado de boletos/cobrança" no topo.
- **Conta Bancária** — `saldo_cached` materializado (migration 2026-05-06), múltiplas contas por business, mapeamento legacy via `accounts_legacy_map`.
- **Extrato** — `fin_extrato_lancamentos` para conciliação OFX/CSV (US-FIN futuros).
- **Plano de Contas BR** — Seeder pronto (`PlanoContasBrSeeder`) + categorização hierárquica `fin_categorias`.
- **Integrações** — `TransactionObserver` + `TransactionPaymentObserver` propagam vendas core UltimatePOS → Títulos; `CriarTituloDeVendaJob` assíncrono.
- **Edit Sheet inline (Onda Edit 2026-05-18, PR #1086)** — `TituloEditSheet.tsx` drawer canon edita campos seguros (`cliente_descricao`, `observacoes`, `categoria_id`, `vencimento`, `valor_total` pré-baixa). PUT `/financeiro/unificado/{id}` via `useForm` Inertia. Guard `assertValorMutavel` bloqueia valor pós-baixa (ADR fin-tech/0002). Substitui STUB `<Button>Editar</Button>` linha 706 do Index.tsx.
- **Conferido per-user DB (Onda Edit, substitui Onda 5 R1 localStorage)** — `fin_titulos.conferido_by` (FK users.id) + `conferido_at` (TIMESTAMP) + index `idx_business_conferido`. Eliana confere ≠ Wagner confere = audit per-user. Routes POST/DELETE `/unificado/{id}/conferir`. `FinConferidoToggle` rewrite: `useFinConferido(lancamentos)` consulta DB-backed via prop array.
- **Cross-links auto-pop #V-/#PC-** — `TituloAutoService::sincronizarDeTransacaoInternal` enriquece `cliente_descricao` com `{ContactName} · #V-{tx_id}` (vendas) ou `#PC-{tx_id}` (compras) no afterCreate. FinCrossLinkify renderiza pills clicáveis route → Sells/Compras. Preserva edit manual user (não sobrescreve se já preenchido).

## Multi-tenant Tier 0 (ADR 0093)

Todas Models usam `BusinessScope` trait (`Modules/Financeiro/Models/Concerns/BusinessScope.php`). Queries filtram `business_id` automaticamente. Defesa em profundidade: services também passam `$businessId` explícito.

## Clientes piloto

| Biz | Cliente | Uso | Notas |
|---|---|---|---|
| **biz=1** | Wagner [W] (interno) | dev + smoke tests | sempre alvo de tests (ADR 0101) |
| **biz=4** | ROTA LIVRE — Larissa | produção 99% volume | ADR 0066 `format_date` shift +3h preservado |

## Arquitetura

- **Controllers (13):** Boleto, Categoria, ContaBancaria, ContaPagar, ContaReceber, Dashboard, Data, Extrato, Financeiro, Fluxo, Install, Relatorios, Unificado (com `update`+`conferir`+`unconferir` Onda Edit)
- **Services (4):** FluxoCaixaService (projeção read-side), TituloService (boleto lifecycle), TituloAutoService (origem auto vendas/compras + cross-links #V-/#PC- Onda Edit), UnificadoService (facade KPIs cockpit)
- **Repositories (2):** TituloRepository (Wave 18), BaixaRepository (Wave 18 RETRY) — singleton, type-safe `businessId:int` 1º param
- **Models (10):** Titulo (Onda Edit: +`conferidoPor()` BelongsTo App\User), TituloBaixa, CaixaMovimento, Categoria, ContaBancaria, BoletoRemessa, ExtratoLancamento, PlanoConta, AccountsLegacyMap
- **FormRequests (7):** UpsertCategoriaRequest, UpsertContaBancariaRequest, StoreTransactionRequest, UpdateTransactionRequest, StoreAccountRequest, FluxoFiltroRequest, StoreBaixaRequest, UpdateAccountRequest, UpdateTituloRequest (Onda Edit — guard imutabilidade pós-baixa)
- **Strategies:** `CnabDirectStrategy` (boleto offline mock `gerado_mock` — **legado, sendo aposentado**). Emissão real de boleto migrou pra `Modules/PaymentGateway` (`InterDriver` LIVE biz=1; ver callout no topo)
- **Observers:** TransactionObserver, TransactionPaymentObserver (sync vendas core → Títulos)
- **Pages Inertia (13 charters):** Cockpit V2 padrão; Fluxo, Unificado, ContasReceber, ContasPagar, Boletos validados visualmente

## Diferenciais (vs Bling/Tiny/Conta Azul/Omie)

1. **Cockpit V2 unificado AR/AP** — concorrentes separam telas; aqui visão única com tabs/filtros densos
2. **Fluxo projetado 35d com pior_dia + margem mínima** — KPI proativo, não só passivo
3. **Idempotência forte** — UNIQUE(biz, origem, origem_id, parcela) previne duplicação na sincronização vendas/compras
4. **Pest cross-tenant** — `MultiTenantIsolationTest` + `AccountsLegacyMapMultiTenantTest` blindam biz=1 vs biz=99 (Tier 0)
5. **Append-only audit** — TituloBaixa nunca DELETE; estorno via `estorno_de_id` (Activitylog Spatie em Titulo)

## Gaps conhecidos

- D3.b BRIEFING — **resolvido** neste documento (2026-05-16)
- D4.a Service ratio sub-detectado — Services existem (`Services/*.php`) mas auditor não cruzava com Controllers; UnificadoService criado como facade explícito de KPIs do cockpit
- Conciliação OFX UI — schema existe (`fin_extrato_lancamentos`) mas falta tela Cockpit
- Relatórios DRE — `RelatoriosController` esqueleto, falta US-FIN-XXX detalhada

## ADRs relacionadas

- [ADR 0066](../../decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md) — `format_date` shift +3h preservado pra ROTA LIVRE
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) — Tests biz=1, nunca cliente

## Charters Inertia

`Boletos.charter.md`, `Fluxo/Index.charter.md`, `Unificado/Index.charter.md`, `ContasReceber.charter.md`, `ContasPagar.charter.md` (5 ativos)

## SPECs e canon

- [SPEC.md](SPEC.md) US-FIN-001..020+
- [ARCHITECTURE.md](ARCHITECTURE.md)
- [COMPARATIVO_CONCORRENCIA.md](COMPARATIVO_CONCORRENCIA.md)
- [DOC_TELAS_E_SCORE.md](DOC_TELAS_E_SCORE.md)
- [PLANO_DETALHADO.md](PLANO_DETALHADO.md)

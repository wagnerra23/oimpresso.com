# Changelog — Financeiro

Formato: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) + SemVer.

## [Unreleased]

### Wave 18 saturação 68→95 (2026-05-16)

- **D4 SoC brutal** — Criado `Modules\Financeiro\Repositories\TituloRepository` com 5 métodos canônicos (`listarPaginado`, `totaisAbertos`, `vencidosAntigos`, `aging`, `acharPorOrigem`). Singleton via FinanceiroServiceProvider. Consumers futuros: UnificadoController, FluxoController, FinanceiroHealthCommand. Substitui `Titulo::where(...)` inline em Controllers.
- **D8 saturação** — Criado `Modules\Financeiro\Http\Requests\FluxoFiltroRequest` (4º FormRequest tipado da onda) com helpers `dias()` / `margemMinima()` pré-validados, evitando `$request->input()` ad-hoc no FluxoController.
- **D1+D9.a Pest cross-tenant** — `TituloRepositoryWave18Test` (5 cenários): cobertura biz=1 vs biz=99 isolamento Tier 0 (ADR 0093), reflection nos métodos garante 1º param sempre `$businessId: int`, validação spanBiz nos métodos hot (`titulo.repo.listar`, `titulo.repo.aging`), defesa em profundidade `where('business_id', $businessId)` explícito.
- **module.json governance** — Declarado `governance.fsm_n_a=true` (titulo é status linear simples, não pipeline FSM) + `retention_days=2555` (CTN Art. 195: 7 anos) + `lgpd_compliance` bloco com pii_fields_tracked canônicos.

### Entregue (Onda 1 — MVP, parcial — 2026-04-25)

- Schema base: `fin_titulos`, `fin_titulo_baixas`, `fin_caixa_movimentos`, `fin_contas_bancarias`, `fin_categorias`, `fin_planos_conta`
- Auto-criação de título a partir de venda `due` (TransactionObserver) — funciona para `type='sell'`
- 5 telas Inertia/React: `/financeiro` dashboard, `/contas-receber`, `/contas-pagar`, `/boletos`, `/contas-bancarias`
- Tela `/categorias` com CRUD livre por business (PR `feat/financeiro-categorias` — 7 tests Pest PASS)
- CnabDirectStrategy mock com 21 bancos (TECH-0003)
- Permissões Spatie registradas no boot (R-FIN-002)
- Plano de contas BR pré-seedado (R-FIN-009)
- Multi-tenant isolation tests (R-FIN-001) + integration test E2E (`TransactionObserverIntegrationTest`, 6 PASS / 3 SKIP)

### Bugs descobertos pelo integration test (Onda 2 — ramos `feat/financeiro-onda2-*`)

Ver `audits/2026-04-25-bugs-integration-test.md` pra repro/root-cause/fix completos.

- 🔴 **BUG-1/BUG-2** — `transaction_payment` não cria `TituloBaixa` nem `CaixaMovimento`. Falta Observer + método `registrarPagamento`.
- 🟡 **BUG-3** — `purchase` não gera Titulo a pagar (`sincronizarDeVenda` retorna null pra `type !== 'sell'`; Job órfão).
- ℹ️ **BUG-4** — `due → paid` marca Titulo como `cancelado` em vez de `quitado` (cosmético).

### Planejado (Onda 2 — fechar ciclo de baixa primeiro)

- **PRIORIDADE**: Fix BUG-1/2/3 (baixa automática + compras) — desbloqueia o "automático" da proposta de valor
- Contas a Pagar (US-FIN-004, US-FIN-005, US-FIN-006) — telas existem; falta integration backend pós-fix BUG-3
- Caixa projetado (US-FIN-007) com cache invalidado por evento
- Cálculo juros + multa (R-FIN-006)

### Planejado (Onda 3)

- Boleto via Strategy (US-FIN-010, ARQ-0003)
- PIX cobrança imediata + dinâmico
- Webhook gateway com idempotência (R-FIN-012)

### Planejado (Onda 4)

- Conciliação OFX (US-FIN-009, UI-0001)
- DRE (US-FIN-011, R-FIN-010)
- Aging (US-FIN-012)
- DRE share link (R-FIN-013)

### Em consideração (Onda 5+)

- OCR de boleto upload
- CNAB direto (homologação por banco)
- Multi-moeda
- Integração Receita Federal (DAS auto-cálculo)

## [0.0.0] - 2026-04-24

### Added

- Spec promovida de `_Ideias/Financeiro/` (status `researching`) para `requisitos/Financeiro/` (`spec-ready`)
- Estrutura completa: README + SPEC + ARCHITECTURE + GLOSSARY + 5 ADRs (arq/0001-0004 + tech/0001-0002 + ui/0001)
- Frase de posicionamento e revenue model definido (ARQ-0004): Free / Pro R$ 199 / Enterprise R$ 599 + take rate 0,5% capped R$ 9,90
- Origem rastreada: conversa Claude mobile (`_Ideias/Financeiro/evidencias/conversa-claude-2026-04-mobile.md`)

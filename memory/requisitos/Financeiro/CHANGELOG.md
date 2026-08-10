---
id: requisitos-financeiro-changelog
---

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
- Frase de posicionamento e revenue model definido (ARQ-0004): Free / Pro R$ [redacted Tier 0] / Enterprise R$ [redacted Tier 0] + take rate 0,5% capped R$ [redacted Tier 0]
- Origem rastreada: conversa Claude mobile (`_Ideias/Financeiro/evidencias/conversa-claude-2026-04-mobile.md`)

---

## Implementação (histórico movido de `Modules/Financeiro/CHANGELOG.md`)

> Movido em 2026-08-10. Os dois changelogs registravam eventos DIFERENTES — acima as
> decisões/requisitos, aqui o que foi de fato mergeado. Medido antes de fundir:
> sobreposição de datas entre os dois era 0-2 de 2-7, logo nenhum era cópia do outro
> e escolher um lado perderia registro. Conteúdo preservado na íntegra.

# CHANGELOG — Modules/Financeiro

Convenção: [Keep a Changelog](https://keepachangelog.com/) + [SemVer](https://semver.org/).

## [Wave 28] - 2026-05-17

### Test (D2 — Pest +2 sentry Pluggy W27 + UnificadoService W25)
- `Tests/Feature/Wave28PolishTest.php` — +2 testes sentry Wave 28:
  - UnificadoService::kpis preserva wrap `OtelHelper::spanBiz('financeiro.unificado.kpis')`
    (regression guard W25 D9 — se alguém remover, sentry pega).
  - Pluggy W27 (open banking) connector artifacts sentry tolerante + Services core
    (UnificadoService + FluxoCaixaService) regression guard.
- Tier 0: multi-tenant + zero git ops + OtelHelper canônico + biz=4 intocado.

### Governance
- Saturação 80-95 → 96 (polish final excelência).

## [Wave 27 — Polish final ≥95] — 2026-05-17

### Added — D9 spans + US-FIN sentry + US-RB-044 lock-in
- **D9 W27** — `FinanceiroAuditLogger::redactContext` wrap em `OtelHelper::spanBiz('financeiro.audit.redact_context')` — mensura custo de redação PII em hot-paths (TransactionObserver + TituloAutoService logam por baixa). Atributos: business_id (resolvido do contexto se presente) + keys_count.
- **D2 W27** — Sentinel tests preservation: `BaixaRepository` 4 métodos canônicos + `TituloRepository` 5 métodos canônicos (W18) com assinatura `businessId:int` 1º param validada via Reflection (Tier 0 explícito).
- **D9 W27** — Sentinel spans preservation: `UnificadoService::kpis`, `FluxoCaixaService::projetar`, `TituloService::emitirBoleto`, `TituloAutoService::sincronizarDeTransaction` (lock-in W25/W18/W14).
- **US-FIN sentry** — `Tests/Feature/Wave27PolishTest.php`:
  - US-FIN-013/020: `UnificadoService::kpis(int $businessId)` assinatura estável
  - US-FIN-014: `FluxoCaixaService::projetar(int $businessId, int $dias=35)` assinatura estável (Q2 Wagner aprovou 2026-05-14: 35d fixo configurável)
- **US-RB-044 sentinel** — NFe-de-boleto-pago preservation (Tier 0 IRREVOGÁVEL):
  - `BoletoRemessa::STATUS_PAGO = 'pago'` constante intacta (trigger NFe gateway)
  - `BoletoRemessa` usa `LogsActivity` (audit fiscal CTN Art. 195)
  - `pdf_path` fillable preservado (double-write transição lib laravel-boleto)
  - `getPdfArquivoAttribute` accessor Modules/Arquivos preservado (ADR 0123)

### Validated

- 13 specs novos W27 em `Wave27PolishTest.php` (source-level + Reflection — sem DB).

### Refs

- ADR 0093 multi-tenant Tier 0 IRREVOGÁVEL
- ADR 0094 §4 Constituição v2 (custo IA + audit trail)
- ADR 0123 Modules/Arquivos backbone (US-RB-044 pdf_path transição)
- ADR 0143 FSM canon LIVE prod biz=1

## [Unreleased] — Wave 18 RETRY (2026-05-16)

### Added (saturação 68→97 — governance module-grade-v3)

- **D4 SoC saturação granular** — `Repositories/BaixaRepository.php` extraído (4 métodos canônicos: `listarPaginado`, `totaisPorTipoPeriodo`, `historicoRecente`, `acharPorIdempotencyKey`) com type hints `businessId:int` 1º param + singleton no Provider. Consumers futuros: `FluxoCaixaService`, `FinanceiroHealthCommand`, `DashboardController.calcularKpis`, `UnificadoService`.
- **D8 FormRequests 5° + 6°** — `Http/Requests/StoreBaixaRequest.php` (criação manual de baixa via cockpit, `Rule::in` meios pagamento, helpers tipados `meioPagamento()` + `valorEfetivo()`) + `Http/Requests/UpdateAccountRequest.php` (PATCH semantics, `sometimes` em todos campos, suporta wiring `rb_gateway_credential_id`).
- **D1 + D9 Pest cross-tenant 11 datasets** — `Tests/Feature/MultiTenantComprehensiveTest.php` cobre 11 cenários × 2 Repositories com `biz=99` retornando zero (defesa em profundidade Tier 0 IRREVOGÁVEL). +17 testes (41 assertions). In-memory SQLite robusto pra CI.

### Changed

- `FinanceiroServiceProvider::register()` registra `BaixaRepository` como singleton.

### Validated

- `php artisan vendor/bin/pest Modules/Financeiro/Tests/Feature/MultiTenantComprehensiveTest.php` → **17/17 passed (41 assertions)**.

### Refs

- ADR 0093 (multi-tenant Tier 0 IRREVOGÁVEL)
- ADR 0101 (tests biz=1, NUNCA biz=4 ROTA LIVRE)
- ADR 0094 §5 (SoC brutal)
- ADR 0155 (module-grade-v3)

## [Wave 18] — 2026-05-16

### Added (saturação 66→95)

- `Repositories/TituloRepository.php` (5 métodos canônicos)
- `Http/Requests/FluxoFiltroRequest.php` (4° FormRequest)
- `Tests/Feature/TituloRepositoryWave18Test.php`
- BRIEFING canon updated

## [Wave 17] — 2026-05-15

### Added (saturação 51→66)

- `Console/Commands/FinanceiroHealthCommand.php` (5 checks SQL)
- Inertia::defer em `DashboardController`
- `LogsActivity` em Categoria + ExtratoLancamento (D7)
- 3 FormRequests: StoreTransactionRequest, UpdateTransactionRequest, StoreAccountRequest
- `module.json` `lgpd_compliance` + `retention_days: 2555` (CTN Art. 195)

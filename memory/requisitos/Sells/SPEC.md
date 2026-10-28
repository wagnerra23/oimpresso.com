---
id: requisitos-sells-spec
slug: sells
title: "Especificação funcional — Sells (migração MWART de /sells/create)"
type: spec
module: Sells
status: ativo
owner: wagner
version: "1.0.0"
last_updated: "2026-05-31"
---

# Especificação funcional — Sells (migração MWART de /sells/create)

> **Convenção do ID:** `US-SELL-NNN` para user stories.
> **Origem:** sessão 2026-05-08 — Wagner pediu RUNBOOK como tarefa em produção, com subtarefas selecionáveis e revisão detalhada antes de iniciar (processo crítico, ROTA LIVRE biz=4 faz 99% do volume).
> **Plano:** [RUNBOOK-create.md](RUNBOOK-create.md) — 11 seções com tokens, estados, atalhos, contract, DoD, pegadinhas, ADRs.
> **Estimates recalibradas:** [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) — fator 10x em codáveis + margem 2x; humanos mantém relógio (canary 7d, monitor 30d). Total epic recalibrado: 60h → ~28h reais (16h codáveis + 12h relógio humano).

## 1. Glossário

- **MWART** — Module Web App React Transition (Blade → Inertia/React)
- **Cockpit** — layout-mãe ERP em React (ADR 0039) — 3 colunas; esta tela usa modo "form" sem coluna direita
- **ROTA LIVRE** — `business_id=4`, Larissa, 99% do volume de vendas (auto-mem `cliente_rotalivre`)
- **biz=1** — WR2 SC, Wagner — única empresa segura pra smoke (auto-mem `feedback_test_business_id_1_nunca_4`)
- **Canary** — fase em que só Wagner (biz=1) usa a tela nova antes de habilitar pra ROTA LIVRE
- **Feature flag `useV2SellsCreate`** — chave em `pos_settings` JSON; ON/OFF instantâneo sem deploy

## 2. User stories

### US-SELL-001 · Epic — Migrar /sells/create pra MWART

**Implementado em:** _parcial_ · `resources/js/Pages/Sells/Create.tsx` · `app/Http/Controllers/SellPosController.php` · verificado@176f9bc (2026-07-01) — subtasks US-002..007+053 landadas + V2 live atrás da flag `useV2SellsCreate` (guard biz=4 removido 2026-05-27); relógio humano aberto (canary 7d / monitor 30d / remover Blade = US-009 _pendente_)

> owner: wagner · priority: p1 · estimate: 28h · type: epic · origin: sessao-2026-05-08-runbook-mwart-sells
> blocked_by: —

**Contexto.** Tela `/sells/create` hoje é Blade legacy (`sale_pos.create` 996 LOC + 60+ partials + jQuery 3.178 LOC). Larissa (ROTA LIVRE) tem fricção real: scroll vertical 3 telas, 18 campos visíveis (10 raramente usados), lag de Select2/DataTables. Goal: migrar pra Inertia/React (MWART) com **defaults inteligentes pra ROTA LIVRE**, **8 campos visíveis + 10 colapsáveis**, **draft auto-save**, **atalhos `/` e `⌘+Enter`**, e **smoke fiscal seguro em biz=1 antes de cutover**.

**Acceptance criteria do epic:**
- [ ] Todas as 8 subtasks abaixo (US-SELL-002..009) com status `done`
- [ ] Score audit cockpit-runbook modo B ≥ 70 antes de mergear cada PR
- [ ] Pest tests do `store()` cobrindo 5+ fixtures (à vista, a prazo, desconto, frete, split)
- [ ] Feature flag `useV2SellsCreate` permite rollback em <30s
- [ ] Smoke biz=1 sem incidente fiscal
- [ ] Canary Wagner 7 dias sem regressão antes de habilitar ROTA LIVRE
- [ ] 30 dias sem incidente em ROTA LIVRE → remover Blade legacy

**Refs:** [RUNBOOK-create.md](RUNBOOK-create.md), [ADR 0039](../../decisions/0039-ui-chat-cockpit-padrao.md), [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)

### US-SELL-002 · Backend dual Inertia/Blade + feature flag + Pest

**Implementado em:** `app/Http/Controllers/SellPosController.php` · `app/Services/FeatureFlagService.php` · `tests/Feature/Sells/SellPosControllerCreateTest.php` · verificado@176f9bc (2026-07-01) — dual response `create()` (Inertia se `isOn('useV2SellsCreate')`, senão view Blade `sale_pos.create`); flag migrou pra GrowthBook self-hosted, comando `sells:enable-v2` substituído por `Flag{Set,Get}Command`

> owner: wagner · priority: p1 · estimate: 1.5h · status: done · type: story · origin: sessao-2026-05-08-runbook-mwart-sells
> blocked_by: —

**Contexto.** O `SellPosController@create` hoje retorna `view('sale_pos.create')` com 27 props. Adicionar resposta dual: se header `X-Inertia` E feature flag `useV2SellsCreate=true` no `pos_settings` da empresa, retorna `Inertia::render('Sells/Create', ...)`. Senão, comportamento atual (zero risco).

**Escopo:**
- [ ] Branch `useV2SellsCreate` em `SellPosController@create`
- [ ] Mapping de 27 props legacy → 19 props camelCase pra contract Inertia (vide RUNBOOK §3.1 e §8)
- [ ] Migration ou comando artisan pra adicionar `useV2SellsCreate: false` no `pos_settings` JSON de todas empresas (default off)
- [ ] Comando artisan `sells:enable-v2 {business_id}` pra ligar/desligar flag por empresa
- [ ] Pest test `SellPosControllerTest::create_returns_inertia_when_flag_on` + `create_returns_blade_when_flag_off`
- [ ] Pest tests do `store()` em 5+ fixtures: à vista, a prazo, com desconto %, com desconto fixo, com frete, com split de pagamento — todos passando ANTES de qualquer mudança no `store()` (baseline de regressão)

**Acceptance criteria:**
- [ ] `curl -H "X-Inertia: true" /sells/create` com flag ON → JSON `"component":"Sells/Create"`
- [ ] `curl /sells/create` (sem header) sempre Blade — manter compat
- [ ] `php artisan test --filter=SellPosControllerTest` passa
- [ ] Rollback: `php artisan sells:enable-v2 {biz} --off` desativa em <30s

### US-SELL-003 · Frontend skeleton + AppShellV2 + props contract

**Implementado em:** `resources/js/Pages/Sells/Create.tsx` · `tests/Feature/Sells/SellsCreatePageTest.php` · verificado@176f9bc (2026-07-01) — Page com Persistent Layout AppShellV2 (`SellsCreate.layout`) + interface TS dos props + `useForm` com defaults

> owner: wagner · priority: p1 · estimate: 1h · status: done · type: story · origin: sessao-2026-05-08-runbook-mwart-sells
> blocked_by: US-SELL-002

**Contexto.** Criar `resources/js/Pages/Sells/Create.tsx` com estrutura mínima rodando — só PageHeader, container vazio, Persistent Layout (AppShellV2). Foco em fechar o pipeline build → bundle → render antes de adicionar lógica.

**Escopo:**
- [ ] `Pages/Sells/Create.tsx` com interface TypeScript dos 19 props (vide RUNBOOK §8)
- [ ] `Create.layout = (page) => <AppShellV2>{page}</AppShellV2>` (Persistent Layout, NÃO envolver em `<AppShell>` — auto-mem)
- [ ] `useForm` inicial com defaults (status='final', transaction_date=defaultDatetime, contact_id=walkInCustomer.id)
- [ ] `npm run build:inertia` + verificação `manifest.json` tem `Pages/Sells/Create`
- [ ] Smoke: ativar flag em biz=1, abrir `/sells/create`, ver render mínimo SEM produtos/pagamento

**Acceptance criteria:**
- [ ] Audit cockpit-runbook modo B ≥ 70 (skeleton sem CRITICAL)
- [ ] PR #N abre flag em biz=1, com flag em biz=4 OFF (Larissa segue Blade)

### US-SELL-004 · Triagem visibilidade campos (18 → 8 visíveis + 10 colapsáveis)

**Implementado em:** `resources/js/Pages/Sells/Create.tsx` · verificado@176f9bc (2026-07-01) — 8 campos sempre visíveis + `<details>` "Mais opções" com 10 colapsáveis; estado `advancedOpen` persistido em `localStorage['oimpresso.sells.create.advanced.open']`

> owner: wagner · priority: p1 · estimate: 0.75h · status: done · type: story · origin: sessao-2026-05-08-runbook-mwart-sells
> blocked_by: US-SELL-003

**Contexto.** Mapa do RUNBOOK §3.3 — 18 campos legacy, ROTA LIVRE só usa 8 com frequência. Esconder 10 em `<details>` colapsáveis. Manter dados serializados no form (não reduzir contract — é só visibilidade).

**Escopo:**
- [ ] 8 campos sempre visíveis: location, contact, transaction_date, status, products[], discount inline, payments[], notes
- [ ] 10 campos colapsáveis em `<details><summary>Mais opções</summary>`: price_group (se >1), commission_agent (se mode!=null), pay_term, invoice_scheme, invoice_no, document, tax_rate (imposto pedido), shipping (5 campos como bloco)
- [ ] `<details>` com `open` lembrado em `localStorage.oimpresso.sells.create.advanced.open`
- [ ] Visualmente mostrado em monitor 1280px sem overflow

**Acceptance criteria:**
- [ ] Smoke 1280px: tudo visível em ≤ 1.5 telas (vs 3 hoje)
- [ ] Audit modo B ≥ 70

### US-SELL-005 · Produtos — busca + tabela + cálculos

**Implementado em:** `resources/js/Pages/Sells/Create.tsx` · `resources/js/Pages/Sells/_components/ProductSearchAutocomplete.tsx` · verificado@176f9bc (2026-07-01) — autocomplete + tabela editável com subtotal/desconto/total reativo, `EmptyState`, atalho `/` foca busca, `editPrice`/`editDiscount` respeitam permissões

> owner: wagner · priority: p1 · estimate: 2.5h · status: done · type: story · origin: sessao-2026-05-08-runbook-mwart-sells · closed: 2026-05-13
> blocked_by: US-SELL-004

**Contexto.** Coração da tela. Hoje é Select2 + AJAX + DataTables jQuery. Migrar pra `<ProductSearchAutocomplete/>` (debounce 250ms) + tabela editável com cálculo reativo de subtotal/desconto/total.

**Escopo:**
- [ ] `<ProductSearchAutocomplete/>` componente local (extrair pra shared só quando 2ª tela usar)
- [ ] Endpoint `/api/products/search?q=...&location_id=...` (preferir reuso do existente; novo só se ausente)
- [ ] Tabela: Produto · Quantidade · Preço unitário · Desconto · Subtotal · X (remover)
- [ ] Permissões respeitadas: `editPrice` e `editDiscount` (props.permissions) → readonly se false
- [ ] Cálculo: subtotal = (qty × unit_price) − desconto; total geral = Σ subtotal − desconto pedido + impostos + frete
- [ ] Empty state: `<EmptyState icon={<Package/>} title="Nenhum produto" primaryAction={focar busca}/>`
- [ ] Atalho `/` foca busca
- [ ] Auto-mem `feedback_form_shim_bool_attrs`: usar bool true/false direto no useForm (não tem o problema do shim Form aqui)

**Acceptance criteria:**
- [ ] Smoke: criar venda 1 produto + criar venda 5 produtos com descontos diferentes
- [ ] Pest test do `store()` com 5 produtos passa
- [ ] Audit modo B ≥ 70

### US-SELL-006 · Pagamento + frete + descontos colapsáveis

**Implementado em:** `resources/js/Pages/Sells/Create.tsx` · `resources/js/Pages/Sells/_components/PaymentRow.tsx` · verificado@176f9bc (2026-07-01) — `<PaymentRow>` + split de pagamento, bloco frete (`shipping_*`) e desconto/imposto colapsados em "Mais opções", total reativo

> owner: wagner · priority: p1 · estimate: 1.5h · status: done · type: story · origin: sessao-2026-05-08-runbook-mwart-sells · closed: 2026-05-13
> blocked_by: US-SELL-005

**Contexto.** Bloco pagamento sempre visível (default 1 linha `payments[0]`). Frete em `<details>`. Desconto pedido + imposto pedido em `<details>` separado.

**Escopo:**
- [ ] `<PaymentRow/>` componente local — valor, método (paymentTypes), conta (accounts), data, nota
- [ ] Botão "+ Adicionar pagamento" (split de pagamento)
- [ ] Cálculo total pago vs total venda — barra ou indicador visual
- [ ] Bloco frete colapsado por padrão: shipping.details, shipping.address (auto-fill do contact se disponível), shipping.cost, shipping_status, deliver_to
- [ ] Bloco desconto pedido + imposto pedido colapsado por padrão
- [ ] Tudo serializa no payload do POST `/sells` (mesma rota legacy)

**Acceptance criteria:**
- [ ] Smoke: venda à vista + venda com 2 pagamentos split + venda com frete
- [ ] Pest tests do `store()` para split de pagamento + frete passam
- [ ] Audit modo B ≥ 70

### US-SELL-007 · Atalhos + auto-save draft + estados visuais

**Implementado em:** `resources/js/Pages/Sells/Create.tsx` · verificado@176f9bc (2026-07-01) — atalho barra (foca busca), Esc, Ctrl+Enter e Cmd+Enter (submit); auto-save draft debounced em `localStorage` com key por biz+user (Tier 0), limpeza pós-save, listeners com `removeEventListener` no cleanup

> owner: wagner · priority: p1 · estimate: 1h · status: done · type: story · origin: sessao-2026-05-08-runbook-mwart-sells · closed: 2026-05-13
> blocked_by: US-SELL-006

**Contexto.** Larissa atende telefone no meio. Não pode perder rascunho. Auto-save em `localStorage.oimpresso.sells.create.draft.{biz}.{user}` debounced 500ms. Recuperação ao reabrir.

**Escopo:**
- [ ] Atalho `/` foca busca de produto (vide US-SELL-005)
- [ ] Atalho `Esc` fecha sheet/drawer ou blur de input
- [ ] Atalho `⌘+Enter` (Mac) / `Ctrl+Enter` (Win/Linux) submete form
- [ ] Auto-save debounced 500ms em `localStorage.oimpresso.sells.create.draft.{biz}.{user}`
- [ ] Toast "Recuperar rascunho de HH:MM?" ao montar se houver draft <24h
- [ ] Estados cobertos: default · hover · focus (`ring-accent`) · disabled · loading (`<Spinner/>`) · empty · error (errors do useForm por campo, não toast)
- [ ] Listener tem `removeEventListener` no cleanup (auto-mem GOTCHAS)
- [ ] Listener bloqueia atalho se `e.target instanceof HTMLInputElement` (não interferir digitação)

**Acceptance criteria:**
- [ ] Smoke: começar venda, F5, recuperar rascunho
- [ ] Smoke: digitar "j" no campo busca → não navega (atalho ignorado em input)
- [ ] Audit modo B ≥ 80 (estados completos)

### US-SELL-008 · QA: audit + smoke biz=1 + canary Wagner 7d + rollback plan

**Implementado em:** _parcial_ · `tests/Feature/Sells/SellPosControllerStoreInvariantsTest.php` · verificado@176f9bc (2026-07-01) — Pest baseline `store()` (11 invariantes estruturais, item `[x]`) presente + V2 live atrás da flag; itens de relógio humano (smoke biz=1 real, canary Wagner 7d, backup DB, rollback testado) fora do escopo codável

> owner: wagner · priority: p0 · estimate: 8h (1h codável + 7d canary humano) · type: story · origin: sessao-2026-05-08-runbook-mwart-sells
> blocked_by: US-SELL-007

**Contexto.** Travas finais antes de tocar ROTA LIVRE. Crítico — Wagner 99% do volume é Larissa.

**Escopo:**
- [ ] **Audit cockpit-runbook modo B** — score completo, corrigir todos CRITICAL e WARN antes de seguir
- [x] **Pest baseline store() — caminho A híbrido (2026-05-15)** — 11 invariantes ESTRUTURAIS em `tests/Feature/Sells/SellPosControllerStoreInvariantsTest.php` (permission guard, multi-tenant Tier 0 ADR 0093, DB transaction atomicidade, branch is_credit_sale, split payment array, cash register pré-venda, credit limit fail-fast, branch quotation/proforma, event dispatch pós-commit, pipeline canônico). **Limitação documentada:** não valida que venda PERSISTE corretamente — apenas que CÓDIGO não regrediu. Integration HTTP full fica pra US-SELL-040 quando alguém refatorar `SellPosController@store` (UltimatePOS herdado, ~30 deps, fixture full custaria 6-10h). Canary humano biz=1 7d cobre comportamento.
- [ ] **Smoke biz=1** (NUNCA biz=4 — auto-mem `feedback_test_business_id_1_nunca_4`):
  - [ ] Criar venda à vista R$ [redacted Tier 0] — conferir: `transactions` + `transaction_payments` + caixa atualizado + cliente OK
  - [ ] Criar venda a prazo 3x — conferir: 3 `account_transactions` `due` futuras
  - [ ] Criar venda com NFC-e (US-NFE-002 deve estar ativo) — conferir DANFE emitida + e-mail enviado
  - [ ] Criar venda com frete + split pgto — conferir totais
  - [ ] Cancelar venda recém-criada — conferir reversal
- [ ] **Canary Wagner 7 dias** — flag ON em biz=1, OFF em biz=4. Wagner usa exclusivamente. Bug encontrado → fix antes de seguir.
- [ ] **Rollback plan documentado** em comentário desta task: comando exato pra desativar flag em <30s. SSH disponível, sem deploy.
- [ ] **Backup DB ANTES** de habilitar em ROTA LIVRE: `mysqldump u906587222_oimpresso transactions transaction_payments transaction_sell_lines transaction_payments` em ZIP datado

**Acceptance criteria:**
- [ ] 7 dias canary Wagner sem regressão
- [ ] Backup DB armazenado em local seguro (GD pessoal? nuvem?)
- [ ] Rollback testado: flag OFF → tela volta pra Blade em <30s

### US-SELL-040 · Pest integration HTTP full do `SellPosController@store` (caminho B)

**Implementado em:** _pendente_ — integration HTTP full do store() **desgated** 2026-07-03 (Onda 1.1 CAPTERRA G-01): escrever JÁ contra o `store()` legado como está, NÃO esperar refactor; hoje só invariantes estruturais tautológicas (US-008)

> owner: wagner · priority: p0 · estimate: 6-10h · status: todo · type: story · origin: sessao-2026-05-15-canary-prep-paridade · repriorizado: 2026-07-03 (p2→p0, Onda 1.1 CAPTERRA-FICHA G-01)
> blocked_by: — (independente — **NÃO** esperar refactor do `store()`; é a rede de segurança que deveria existir ANTES dele)

**Contexto.** Caminho B do plano híbrido decidido em 2026-05-15 — alternativa "honesta integration" do baseline `store()`. Hoje `tests/Feature/Sells/SellPosControllerStoreInvariantsTest.php` (US-SELL-008 parte 1) cobre estrutura via regex contra source, mas não persiste venda. Esta US executa fixtures HTTP reais POST `/pos` em biz=1 com RefreshDatabase + seed mínimo (Business + User com perms + Location + Tax + CashRegister aberto + Contact walk-in + Product), valida `transactions` + `transaction_payments` + `transaction_sell_lines` + estoque decrescido.

**Quando disparar (repriorizado 2026-07-03 — gate REMOVIDO).** Fazer **agora**, contra o `SellPosController@store` legado como está (~30 deps UltimatePOS) — **sem esperar refactor**. Racional (Onda 1.1 CAPTERRA-FICHA §8.2 + G-01): as "invariantes estruturais" (`SellPosControllerStoreInvariantsTest`, strpos no source) são **tautológicas** — passam mesmo com a conta errada (anti-padrão proibicoes §5). O canary humano biz=1 **não pega inflação** (o incidente R$×100k de 2026-06-05 em biz=4 foi pego em produção, não por teste nem por canary). Uma venda POST `/pos` real que verifica `final_total`/subtotais **persistidos** é a rede de segurança mais barata contra um 2º incidente de valor — e ela precisa existir **independente** do refactor, não atrás dele. Se/quando o `store()` for refatorado, o mesmo teste vira o guard de não-regressão do refactor (bônus).

**Escopo (5+ fixtures):**
- [ ] Seed builder helper em `tests/Helpers/` ou `tests/Support/SellsTestSeed.php` (Business id=1 + User com `sell.create` + Location + Tax + CashRegister aberto + Contact walk-in + Product enable_stock)
- [ ] Fixture 1 · venda à vista R$ [redacted Tier 0]: POST `/pos` + assert `payment_status='paid'` + `final_total=100` + 1 row `transaction_payments`
- [ ] Fixture 2 · venda a prazo 3x: POST `/pos` com `is_credit_sale=1` + assert `payment_status='due'` + 0 rows `transaction_payments`
- [ ] Fixture 3 · venda com desconto 10%: POST `/pos` com `discount_type='percentage', discount_amount=10` + assert `final_total` desconta corretamente
- [ ] Fixture 4 · venda com frete R$ [redacted Tier 0]: POST `/pos` com `shipping_charges=15` + assert `shipping_charges` persistido
- [ ] Fixture 5 · venda split pgto (dinheiro 50 + cartão 50): POST `/pos` com `payment[]` array 2 linhas + assert 2 rows `transaction_payments`
- [ ] Fixture 6 · cancelamento (BÔNUS): chamar `/sells/{id}/cancel` + assert estoque revertido + `payment_status` reflete reversal

**Acceptance criteria:**
- [ ] 5+ testes Pest passando em `tests/Feature/Sells/SellPosControllerStoreIntegrationTest.php`
- [ ] `RefreshDatabase` ou `DatabaseTransactions` trait usado (não suja state entre testes)
- [ ] biz=1 SEMPRE (NUNCA biz=4 — auto-mem `feedback_test_business_id_1_nunca_4`)
- [ ] CI rodando em <60s (sem network, sem services externos)

### US-SELL-053 · FieldError por campo + auto-open details em erro

**Implementado em:** `resources/js/Pages/Sells/Create.tsx` · verificado@176f9bc (2026-07-01) — componente local `FieldError` (`role="alert"`, `text-xs text-destructive mt-1`) em `contact_id`/`transaction_date`/`location_id`/`invoice_no` + `useEffect` que abre `<details>` "Mais opções" quando erro cai em `COLLAPSED_FIELD_KEYS`

> owner: wagner · priority: p1 · estimate: 1h · status: done · type: story · origin: design-arte-agent-2026-05-13 · closed: 2026-05-13
> blocked_by: US-SELL-007

**Contexto.** Maior gap UX restante após US-SELL-007 (detectado pelo agente `design-arte` 2026-05-13, nota 68/100). Quando erro de validação cai em campo dentro do `<details>` "Mais opções" colapsado, Larissa scrola pro erro mas a seção fica fechada — **não acha o campo**.

**Escopo:**
- [x] Componente `<FieldError>` inline em `Sells/Create.tsx` (canon: reusável só ao 2º uso) — `role="alert"` pra screen reader
- [x] `useEffect` que detecta erro em `COLLAPSED_FIELD_KEYS` → `setAdvancedOpen(true)` + persiste localStorage
- [x] `<FieldError>` aplicado em campos principais: `contact_id`, `transaction_date`, `location_id` (sec-dados sempre visíveis) + `invoice_no` (colapsado SEFAZ)
- [x] Charter: Goal nova "validação inline por campo + auto-open seção colapsada em erro"

**Acceptance criteria:**
- [x] Submit com erro em `invoice_no` → `<details>` "Mais opções" abre automaticamente
- [x] Submit com erro em `contact_id` → mensagem aparece imediatamente abaixo do autocomplete
- [x] Tipografia consistente: `text-xs text-destructive mt-1`
- [ ] Pest test (TODO US-SELL-008 incluirá)

### US-SELL-009 · Cutover ROTA LIVRE + remover Blade após 30d

**Implementado em:** _pendente_ — cutover ROTA LIVRE + remocao Blade legacy: relogio humano 30d monitor; Blade sale_pos/create ainda presente

> owner: wagner · priority: p0 · estimate: 4h (0.5h codável + 30d monitor humano) · status: todo · type: story · origin: sessao-2026-05-08-runbook-mwart-sells
> blocked_by: US-SELL-008

**Contexto.** Habilitar V2 pra Larissa. 30 dias monitorando. Se zero incidente → remover Blade legacy + 60 partials + parte do `pos.js`.

**Escopo:**
- [ ] Aviso prévio pra Larissa (WhatsApp ou ligação): "Tela de venda nova — qualquer estranheza, me avisa imediato"
- [ ] `php artisan sells:enable-v2 4 --on` (biz=4 ROTA LIVRE)
- [ ] Monitorar `storage/logs/laravel.log` filtrando por `Sells/Create` 24h primeiro
- [ ] Daily check 7d: contar vendas criadas vs vendas com erro/exception
- [ ] Após 30d sem incidente:
  - [ ] Deletar `resources/views/sale_pos/create.blade.php`
  - [ ] Deletar `resources/views/sell/create.blade.php` (já é fallback indireto)
  - [ ] Deletar partials não-referenciados restantes em `resources/views/sale_pos/partials/`
  - [ ] Audit `public/js/pos.js` — remover funções não usadas pelo edit/POS
  - [ ] Remover branch `useV2SellsCreate` do `SellPosController@create` (single response)
  - [ ] Remover comando artisan `sells:enable-v2`

**Acceptance criteria:**
- [ ] 30 dias zero incidente (Wagner valida via planilha)
- [ ] Larissa não reporta nenhum problema novo
- [ ] Linhas removidas: ~1.500 LOC Blade + ~500 LOC pos.js
- [ ] PR de remoção tem audit modo B do `Pages/Sells/Create.tsx` ainda ≥ 80

## 3. Mecanismos de confiança (vão direto pro DoD do epic)

Como ter certeza que vai dar certo:

1. **Feature flag `useV2SellsCreate`** — JSON em `pos_settings` da empresa. ON/OFF por empresa, sem deploy. Rollback em <30s via `php artisan sells:enable-v2 {biz} --off`.
2. **Dual response no controller** — Blade ainda funciona pra qualquer empresa com flag OFF. Zero impacto pra ROTA LIVRE até habilitar.
3. **Pest tests do `store()`** — 5+ fixtures cobrindo casos reais (à vista, prazo, desconto %, fixo, frete, split). Baseline de regressão ANTES de qualquer mudança.
4. **Smoke biz=1, NUNCA biz=4** — auto-mem `feedback_test_business_id_1_nunca_4`. Wagner WR2 SC é cobaia segura; Larissa nunca.
5. **Canary 7 dias Wagner** — flag ON só em biz=1 antes de tocar biz=4. Bug encontrado → fix antes do cutover.
6. **Audit cockpit-runbook modo B obrigatório** — score ≥ 70 em CADA US-SELL-NNN antes de mergear PR. CRITICAL bloqueia merge.
7. **Backup DB antes do cutover** — `mysqldump` das 4 tabelas críticas. Restore em <5min se necessário.
8. **Aviso prévio pra Larissa** — humano-no-loop, ela sabe que mudança rolou e tem canal direto pra reportar.
9. **30 dias monitorando antes de remover Blade** — janela longa pra qualquer regressão de borda aparecer.
10. **PR ≤ 300 linhas, 1 PR = 1 intent** — skill `commit-discipline` Tier A.

## 4. Anotações pré-início

> Wagner usa `tasks-comment <ID> "anotação"` pra registrar pensamentos antes de começar cada US.
> Comentários ficam DB-only (não vão pro SPEC) e aparecem em `tasks-detail`.

Histórico de comentários por US fica navegável via `/copiloto/admin/qualidade` ou tool MCP `tasks-detail task_id:US-SELL-NNN`.

## 5. User Stories — State Machine canônica (sessão 2026-05-10)

> Cadeia criada após pivot conceitual com Wagner: **venda sem nota é caminho feliz, não falha**. US-RB-044 fechada com DoD prod-evidence removida. Padrão FSM (Finite State Machine + RBAC por transição) será reutilizado por Sells, Repair, Project e qualquer feature multi-etapa futura.

### US-SELL-010 · Investigar State Machines existentes (Repair, Project, mcp_tasks) + propor ADR padrão FSM canônico

**Implementado em:** `memory/decisions/0129-state-machine-canonica-fsm-rbac.md` · `memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md` · verificado@176f9bc (2026-07-01) — DoD "ADR padrão FSM canônico proposto + aprovado" cumprido: ADR 0129 `accepted` (4 tabelas custom escolhidas vs Spatie/Symfony), ADR 0143 marca pipeline LIVE prod biz=1

> owner: wagner · priority: p1 · estimate: 6h · status: done · type: story
> blocked_by: —

**Contexto:** Wagner identificou que oimpresso precisa de padrão canônico de Workflow/State Machine pra modelar processos multi-etapa com RBAC por transição. Hoje há state machines simples espalhadas (Repair Kanban, mcp_tasks todo→done, talvez Project) sem padrão unificado. Sem isso, qualquer feature multi-etapa (gate emissão NFe por venda, fluxo aprovação OS, kanban PMG) reinventa roda diferente.

**Decisão de design pendente:** adotar `spatie/laravel-model-states`, `symfony/workflow`, ou modelo customizado de 4 tabelas (`processes` + `process_stages` + `stage_actions` + `stage_action_roles`).

**Acceptance criteria:**
- [ ] Mapear o que existe: Modules/Repair status flow, Modules/Project tasks states, mcp_tasks state machine — quem implementa, onde, com qual padrão
- [ ] Verificar pacotes disponíveis no `composer.json` (spatie/laravel-model-states, symfony/workflow)
- [ ] Identificar se já existe RBAC por transição em algum módulo
- [ ] ADR `proposed` em `memory/decisions/NNNN-state-machine-canonica-fsm-rbac.md` com: opções avaliadas (Spatie vs Symfony vs custom 4 tabelas), trade-offs (lock-in vs flexibilidade vs simplicidade), recomendação, plano de migração de Repair/Project pro padrão escolhido (se houver)
- [ ] Wagner aprova ADR antes de qualquer código

**Refs:** sessão 2026-05-10 (CYCLE-04 higiene + pivot conceitual venda sem nota). US-RB-044 fechada motivou esse trabalho.

### US-SELL-011 · Modelar 4 tabelas FSM canônicas (processes + stages + actions + RBAC)

**Implementado em:** `app/Domain/Fsm/Services/ExecuteStageActionService.php` · `app/Domain/Fsm/Models/SaleProcess.php` · `database/migrations/2026_05_11_120001_create_sale_processes_table.php` · `database/migrations/2026_05_11_120005_create_sale_stage_history_table.php` · verificado@cd84a38 (2026-07-01)

**Testado em:** `tests/Feature/Domain/Fsm/ExecuteStageActionServiceTest.php` (covers US-SELL-011)

> owner: wagner · priority: p1 · estimate: 12h · status: done · type: story
> blocked_by: US-SELL-010
> done: 2026-05-10 · PR: #501 · Pest: 13/13 ✅

**Contexto:** após ADR aceitar State Machine canônica (US-SELL-010), implementar a infraestrutura base que será usada por Sells (gate emissão NFe), Repair (kanban OS), Project (tasks), e qualquer feature futura multi-etapa.

**Schema proposto (sujeito a ADR US-SELL-010):**

```sql
sale_processes              -- catálogo: "Venda Padrão", "Venda Sem Nota", "Venda B2B"
  id, business_id, key (unique per business), name, description, default_for_contact_type, active

sale_process_stages         -- estados: rascunho → orcamento → faturada → paga → emitida → enviada
  id, process_id, key, name, sort_order, is_initial, is_terminal, color

sale_stage_actions          -- transições por etapa: "emitir NFe55", "marcar pago", "cancelar"
  id, stage_id, key, label, target_stage_id (nullable se não muda stage), event_class (event a disparar), requires_confirmation

sale_stage_action_roles     -- RBAC join: action × spatie_role/permission
  id, action_id, role_name (FK spatie_roles)
```

**Acceptance criteria:**
- [ ] Migrations das 4 tabelas com `business_id` global scope obrigatório (ADR 0093 multi-tenant Tier 0)
- [ ] Models + relacionamentos
- [ ] Service `ExecuteStageActionService::execute(Sale $sale, string $actionKey, User $user)` que: (1) resolve action válida pra stage atual; (2) checa RBAC; (3) dispara event; (4) atualiza `current_stage_id`; (5) loga em `sale_stage_history`
- [ ] Tabela `sale_stage_history` (audit log: sale_id, from_stage, to_stage, action, user_id, timestamp)
- [ ] Pest 8+ testes: transição válida, action inválida pra stage, RBAC OK, RBAC falha, multi-tenant isolation, terminal state bloqueia ação, history registrada, event disparado

**Refs:** US-SELL-010 (ADR mãe). Modules/Repair + Modules/Project devem migrar pro padrão (US separadas a criar pós-ADR).

### US-SELL-012 · Gate de emissão NFe por venda (aplicar FSM canônica em Sale)

**Implementado em:** `database/migrations/2026_05_11_160001_add_fsm_columns_to_transactions.php` · `app/Domain/Fsm/Services/InitialStageResolver.php` · `app/Http/Controllers/SaleFsmActionController.php` · verificado@cd84a38 (2026-07-01)

**Testado em:** `tests/Feature/Domain/Fsm/GateEmissaoPorVendaTest.php` (covers US-SELL-012)

> owner: wagner · priority: p1 · estimate: 8h · status: done · type: story
> blocked_by: US-SELL-011
> done: 2026-05-10 · PR: #507 · Pest: 6/6 ✅ (19/19 full)

**Contexto:** primeira aplicação real da State Machine canônica (US-SELL-011). Resolve premissa errada do US-RB-044 original — "venda sem nota é caminho feliz, não falha". Auto-emissão NFe55 deixa de ser flag global por business e passa a ser **opt-in por venda** via processo escolhido.

**Mudanças no schema Sales:**
- Adicionar `process_id` + `current_stage_id` em `transactions` (table de vendas UltimatePOS, com FK pras tabelas FSM)
- Default na criação: usar `process_default_for_contact_type` (Contact PJ → "Venda Com Nota"; Contact CF → "Venda Sem Nota")
- UI checkout permite override do processo

**Processos seed (instalados via migration):**
- `Venda Sem Nota`: stages [rascunho → faturada → paga] (sem stage `emitida`/`enviada`)
- `Venda Com Nota Manual`: stages [rascunho → faturada → paga → emitida → enviada], action `emitir_nfe` em `paga` é manual (botão UI)
- `Venda Com Nota Automática`: idem mas stage `paga` tem action `emitir_nfe` com `auto_trigger=true` (event `InvoicePaid` dispara)

**Listener auto-emissão refatorado:**
- `EmitirNFeAoReceberPagamento` (atual) consulta `sale.currentStage->actions` e só emite se existe action `emitir_nfe` com `auto_trigger=true`
- Flag global `nfebrasil.auto_emission_on_invoice_paid` deprecada (vira no-op com warning log; remoção em US futura)

**Acceptance criteria:**
- [ ] Migration adiciona process_id + current_stage_id em transactions (com FK + index multi-tenant)
- [ ] 3 processos seed instalados via SeederFSM (idempotente; cria só se não existe pra business)
- [ ] Default process resolve por Contact type (CF/PF/PJ) — fallback "Venda Sem Nota" se Contact null
- [ ] UI POS checkout mostra processo escolhido + permite trocar (dropdown)
- [ ] Listener `EmitirNFeAoReceberPagamento` consulta FSM antes de emitir; sem action `emitir_nfe` no stage atual = no-op silencioso (não loga warning, é caminho feliz)
- [ ] Pest: 6 testes — venda sem nota não dispara NFe, venda com nota auto dispara, venda com nota manual NÃO dispara automaticamente, multi-tenant isolation, default por Contact type, override UI persiste
- [ ] Doc no SPEC Sells: matriz "Contact type → process default"

**Refs:** US-SELL-011 (FSM base). US-RB-044 fechada com pivot conceitual. ROTA LIVRE biz=4 deve ficar com default "Venda Sem Nota" pra não quebrar fluxo atual.

**Caso prático referência:** [CASO-PRATICO-OS-COMUNICACAO-VISUAL.md](./CASO-PRATICO-OS-COMUNICACAO-VISUAL.md) — OS Comunicação Visual exemplifica gate por venda com 2 docs (NFe55 + NFSe56). Ver dependências adicionais US-SELL-013 (reservas estoque) + US-SELL-014 (multi-documento).

### US-SELL-013 · Reservas de estoque (stock_reservations) — side-effects FSM aplicados

**Implementado em:** `database/migrations/2026_05_11_130001_create_stock_reservations_table.php` · `app/Domain/Fsm/Models/StockReservation.php` · `app/Domain/Fsm/SideEffects/ReservarEstoque.php` · `app/Domain/Fsm/SideEffects/ConsumirEstoque.php` · `app/Domain/Fsm/SideEffects/LiberarReserva.php` · `app/Domain/Fsm/Jobs/ExpireStaleReservationsJob.php` · verificado@cd84a38 (2026-07-01)

**Testado em:** `tests/Feature/Domain/Fsm/StockReservationsTest.php` (covers US-SELL-013)

> owner: wagner · priority: p1 · estimate: 8h · status: done · type: story
> blocked_by: US-SELL-011
> done: 2026-05-10 · PR: #510 · Pest: 8/8 ✅

**Contexto:** caso prático OS Comunicação Visual revelou gap — UltimatePOS core baixa estoque no checkout, mas OS de produção precisa **reservar sem baixar** entre "orçamento aprovado" e "produção concluída". Reserva impede que o mesmo metro de lona seja vendido em 2 OS simultâneas, mas mantém estoque disponível enquanto OS pode ser cancelada.

**Schema:**
```sql
stock_reservations
  id, business_id, transaction_id, product_id, variation_id,
  qty_reserved (decimal), status (active|consumed|released|expired),
  expires_at (TTL configurável por business, default 30d)
```

**Side-effects FSM (consumidos por `sale_stage_actions.side_effect_class`):**
- `App\Domain\Fsm\SideEffects\ReservarEstoque` — cria `stock_reservation` ativa, NÃO mexe `variation_location_details.qty_available`
- `App\Domain\Fsm\SideEffects\ConsumirEstoque` — marca reserva como `consumed`, decrementa `qty_available`
- `App\Domain\Fsm\SideEffects\LiberarReserva` — marca reserva como `released` (cancelamento OS)

**Acceptance criteria:**
- [ ] Migration `stock_reservations` com `business_id` global scope (ADR 0093 Tier 0)
- [ ] Model `StockReservation` + 3 SideEffect classes invocáveis via stage_action
- [ ] Job daily `ExpireStaleReservationsJob` (libera reservas vencidas)
- [ ] Quantidade efetivamente disponível pra venda = `qty_available - SUM(active reservations)` (helper `Product::getAvailableForSaleAttribute()`)
- [ ] UI POS mostra "X em estoque · Y reservados · Z disponíveis" no produto
- [ ] Pest: 8 testes (criar reservation, consumir, liberar, expirar, cálculo disponível, isolation multi-tenant, race condition concorrente, side-effect dispatched via FSM action)

**Caso prático referência:** [CASO-PRATICO-OS-COMUNICACAO-VISUAL.md](./CASO-PRATICO-OS-COMUNICACAO-VISUAL.md) — banner 3×2m reserva 6m² lona em "orçamento aprovado" e consome em "produção concluída".

**Refs:** US-SELL-011 (FSM tabelas + side_effect_class). Boa prática varejo BR mas ausente no UltimatePOS core.

### US-SELL-014 · Multi-documento por venda (transaction_documents poly) — N notas atreladas a 1 OS

**Implementado em:** `database/migrations/2026_05_11_140001_create_transaction_documents_table.php` · `app/Domain/Fsm/Models/TransactionDocument.php` · verificado@cd84a38 (2026-07-01)

**Testado em:** `tests/Feature/Domain/Fsm/TransactionDocumentTest.php` (covers US-SELL-014)

> owner: wagner · priority: p1 · estimate: 6h · status: done · type: story
> blocked_by: US-SELL-011
> done: 2026-05-10 · PR: #508 · Pest: 6/6 ✅

**Contexto:** caso prático OS Comunicação Visual revelou gap — 1 OS = N documentos fiscais. Banner (mercadoria) emite NFe55, instalação (serviço) emite NFSe56. Hoje `Modules/NfeBrasil` assume 1 transaction = 1 NFe via `transaction_id` direto na `nfe_emissoes`. Pra cobrir caso real BR (gráfica, oficina, eletricista, dentista) precisa relação **poly N:1**.

**Schema:**
```sql
transaction_documents
  id, business_id, transaction_id,
  doc_type (nfe55|nfce65|nfse56|nfcom62|mdfe58|cte57),
  doc_class (Modules\NfeBrasil\Models\NfeEmissao|Modules\NfeBrasil\Models\NfseEmissao|...),
  doc_id (FK polimórfica),
  value_total (decimal — soma dos itens cobertos por esse doc),
  emitted_at (nullable — antes de emitir),
  status (pending|authorized|rejected|cancelled)
  UNIQUE(transaction_id, doc_type, doc_id)
```

**Mudanças correlatas:**
- `Modules/NfeBrasil/Models/NfeEmissao` — coluna `transaction_id` deprecada (backref via `transaction_documents`)
- Migration de dados — popula `transaction_documents` retroativamente pras NFe existentes
- Listener `EmitirNFeAoReceberPagamento` — consulta `transaction_documents` antes de emitir (idempotência cross-doc)
- UI tela `/sells/{id}` ganha card "Documentos Fiscais" listando N notas + status individual

**Acceptance criteria:**
- [ ] Migration `transaction_documents` (poly por `doc_class` + `doc_id`) com index `(business_id, transaction_id)` e `(business_id, doc_type, status)`
- [ ] Model `TransactionDocument` + relacionamento poly em `Transaction`
- [ ] Backfill migration popula NFe existentes (preserva idempotência)
- [ ] Listener `EmitirNFeAoReceberPagamento` refatorado pra consultar poly
- [ ] UI mostra N notas no card transaction (status colorido + link DANFE/PDF + botão re-emitir se rejeitada)
- [ ] Pest: 6 testes (1 venda 1 nota, 1 venda 2 notas NFe+NFSe, idempotência cross-doc, multi-tenant isolation, status individual independente, backfill preserva data)

**Caso prático referência:** [CASO-PRATICO-OS-COMUNICACAO-VISUAL.md](./CASO-PRATICO-OS-COMUNICACAO-VISUAL.md) — OS R$ [redacted Tier 0] = NFe55 R$ [redacted Tier 0] (banner) + NFSe56 R$ [redacted Tier 0] (instalação).

**Refs:** US-SELL-011 (FSM base). Pré-requisito pra US-NFE-060 (EmitirNFSeJob).

### US-SELL-015 · Modo "Grade Avançada" — toggle + layout densa base · **P0**

**Implementado em:** _pendente_ — toggle Lista/Grade Avancada REMOVIDO na Unificacao 2026-05-21 (SellsToggleViewMode/SellsGradeAvancada deletados); substituido por tabs de visao. Ver US-SELL-046

> owner: — · priority: p0 · estimate: 6h · status: todo · type: story · origin: sessao-2026-05-11-migration-officeimpresso
> blocked_by: —

**Contexto.** Power-user OfficeImpresso (gráficas — Vargas, Extreme, Gold, Zoom, Fixar, Produart) usa há 10-26 anos o grid Delphi DevExpress denso (30+ colunas, agrupamento, multiseleção, total rodapé). A Lista enxuta atual (5 colunas + 3 KPIs) é correta pra ROTA LIVRE/novos mas choca esse cliente. [ADR 0136](../../decisions/0136-sells-grade-avancada-modo-toggle.md) decide pelo **split via toggle no header** — `viewMode: 'lista' | 'grade-avancada'`, persistido em `localStorage` (`oimpresso.sells.viewMode`).

**Escopo:**
- [ ] Header `Sells/Index.tsx` ganha toggle "Lista | Grade Avançada" (segmented control à esquerda do "Nova venda")
- [ ] Coluna `business.legacy_origin` (`nullable VARCHAR(32)`) — migration + preenchimento dos 6 candidatos OfficeImpresso saudáveis via seeder idempotente
- [ ] `HandleInertiaRequests::share('sells.viewMode.default')` retorna `'grade-avancada'` se `business.legacy_origin === 'officeimpresso'` E user nunca tocou no toggle (`localStorage` vazio)
- [ ] Componente `<GradeAvancadaLayout />` no mesmo arquivo `Sells/Index.tsx` — recebe `rows` + `meta` + handlers, monta tabela densa shadcn `<Table>` com colunas: Data emissão, Nº fatura, Cliente, Razão social, Total, Pago, Saldo, Status financeiro (badge), Status fiscal (badge), Funcionário, Data Faturamento, Placa (vazia pra não-frota)
- [ ] Linha clicável → mesmo drawer `<SaleSheet>` (não duplica state)
- [ ] Pest browser smoke: biz=1, modo "Grade Avançada", 100 vendas seed, screenshot OK
- [ ] Charter `Sells/Index.charter.md` (S4 antecipado quando S4 vier — opcional agora) — Anti-hooks: "não duplicar fetch/state — só layout"

**Acceptance criteria:**
- [ ] Toggle aparece e alterna sem recarregar (re-render só do layout interno)
- [ ] `localStorage['oimpresso.sells.viewMode']` persiste entre sessões
- [ ] Cliente OfficeImpresso novo (sem `localStorage`) cai automático em Grade Avançada
- [ ] Cliente novo qualquer (legacy_origin null) cai em Lista
- [ ] Pest tests do `SellPosController@index` e `/sells-list-json` continuam verdes (zero mudança backend além da migration)
- [ ] Visual comparison `memory/requisitos/Sells/sells-grade-avancada-visual-comparison.md` aprovado por Wagner antes de mergear (gate F3 — [ADR 0107](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md))

**Refs:** [ADR 0136](../../decisions/0136-sells-grade-avancada-modo-toggle.md), [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md), [ADR 0107](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md).

### US-SELL-016 · Multiseleção + ações em lote (imprimir/exportar/agrupar) · **P0**

**Implementado em:** _parcial_ · `resources/js/Pages/Sells/Index.tsx` · `app/Http/Controllers/SellController.php` · verificado@176f9bc (2026-07-01) — multiseleção (`selectedIds`) + barra de ações + emissão NF-e em lote vivas; métodos backend `bulkPrint` e `bulkExport` existem (rotas sells.bulk-print e sells.bulk-export, gate perm + scope Tier 0 + CSV BOM), MAS na UI unificada "Marcar pagas" e "Exportar XML/PDF" são toast "Em breve V2" e faltam os botões "Imprimir seleção" e "Exportar CSV" wired + shift+click range

> owner: — · priority: p0 · estimate: 4h · type: story · origin: sessao-2026-05-11-migration-officeimpresso
> blocked_by: US-SELL-015

**Contexto.** Grid Delphi tem checkbox por linha + barra de ações no topo quando ≥1 selecionada (Imprimir / Exportar Excel / Agrupar). Higiene UX 2026 pra qualquer grid empresarial (Mubisys, Zênite, Calcgraf, Conta Azul têm). Não depende de snapshot Firebird — sinal trivial.

**Escopo:**
- [ ] Coluna `<Checkbox />` à esquerda no `<GradeAvancadaLayout />` (header tem "selecionar todas as N filtradas")
- [ ] Estado `selectedIds: Set<number>` em `SellsIndex`
- [ ] Barra de ações flutuante (slide-down sobre o filter-pills) quando `selectedIds.size > 0`: botões "Imprimir seleção (PDF)", "Exportar CSV", "Agrupar por…" (dropdown — abre US-SELL-019 quando ela existir; agora dropdown vazio com tooltip "P1")
- [ ] Endpoint POST `/sells/bulk-print` recebe `ids[]` retorna stream PDF (combina os DANFEs/PDFs já existentes; reusa lógica `SellController@printInvoice` chamada em loop)
- [ ] Endpoint POST `/sells/bulk-export` retorna CSV das colunas visíveis no momento
- [ ] Pest: 3 tests — multiseleção persiste em paginação, bulk-print retorna PDF válido, bulk-export retorna CSV com header das colunas

**Acceptance criteria:**
- [ ] Selecionar 5 vendas, clicar "Imprimir seleção" → 1 PDF com 5 DANFEs concatenadas
- [ ] "Selecionar todas" respeita filtros aplicados (não seleciona vendas fora do filter)
- [ ] Shift+click selecionar range entre 2 linhas (UX padrão grid moderno)
- [ ] biz=1 isolation: user de biz=2 não consegue forjar IDs de biz=1 no payload

**Refs:** [ADR 0136](../../decisions/0136-sells-grade-avancada-modo-toggle.md), [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) (bulk endpoints validam business_id de cada ID).

### US-SELL-017 · Totalizador rodapé (Qtd vendas + Σ R$ filtrado) · **P0**

**Implementado em:** `app/Http/Controllers/SellController.php` · `resources/js/Pages/Sells/_components/SellsTotalBar.tsx` · `resources/js/Pages/Sells/Index.tsx` · verificado@9a67342 (2026-10-20) — `inertiaList` calcula `totals` sobre o filtro inteiro (`clone($q)` antes de paginar); a barra abaixo da lista mostra quantidade, Pago, A receber e Total do filtro com esses números, sem somar no navegador, e não mostra número quando a visão salva, a pílula Faturada/Cancelada ou a origem filtram só a página carregada (UC-SIDX-06, `tests/js/sells-index-totais.test.tsx`). Os modos Lista opt-in e Grade Avançada do escopo original saíram da tela em 2026-05-21 e não se aplicam

> owner: — · priority: p0 · estimate: 2h · type: story · origin: sessao-2026-05-11-migration-officeimpresso
> blocked_by: US-SELL-015

**Contexto.** Delphi mostra "Total: R$ [redacted Tier 0]" ao pé do grid (soma dos filtros aplicados). Power-user gráfica chama esse número em **toda** demo. Falta no Inertia atual — KPI "Total" no topo é count (113), não soma R$. Cliente migrado vai sentir falta na hora.

**Escopo:**
- [ ] `/sells-list-json` retorna `totals: { count, sum_final_total, sum_total_paid, sum_due }` calculados com os mesmos `where` do query (não da página corrente — totais respeitam filtros mas não paginação)
- [ ] `<GradeAvancadaLayout />` renderiza barra `<tfoot>` sticky-bottom: "Qtd: N vendas · Total: R$ X · Pago: R$ Y · A receber: R$ Z"
- [ ] Modo "Lista" também ganha tfoot mínimo (Qtd + Total), atrás de um botão "Mostrar totais" (não polui Lista limpa por default)
- [ ] Pest: 2 tests — totals respeitam filtro `payment_status=overdue`, totals respeitam search livre

**Acceptance criteria:**
- [ ] Filtrar "Atrasadas" → tfoot mostra `Qtd: 1 · Total: R$ [redacted Tier 0]` (caso atual da screenshot)
- [ ] Limpar filtro → tfoot mostra `Qtd: 113 · Total: R$ X` (soma de todas)
- [ ] Paginar pra página 3 não muda tfoot (totais são do filtro inteiro)

**Refs:** [ADR 0136](../../decisions/0136-sells-grade-avancada-modo-toggle.md). Performance: SUM no MySQL em índice `(business_id, payment_status)` já existente — sub-50ms pra 100k vendas.

---

### Heatmap Firebird 2026-05-11 — sinal qualificado para US-018..027

> **Sinal qualificado obtido** via [HEATMAP-CONSOLIDADO.md](../../research/2026-05-sells-grade-heatmap/HEATMAP-CONSOLIDADO.md) — 4 bancos Firebird amostrados (WR Sistemas + Vargas + Extreme + Gold). As prioridades abaixo refletem evidência, não chute. Cumpre [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md).

### US-SELL-018 · Filtros multi-data com presets Dia/Semana/Mês/Ano + custom · **P1 confirmado**

**Implementado em:** `resources/js/Pages/Sells/_components/SellsDateFilter.tsx` · `resources/js/Pages/Sells/Index.tsx` · verificado@176f9bc (2026-07-01) — segmented control Dia/Semana/Mês/Ano/Personalizado + popover custom range (`date_from`/`date_to`) + dropdown "Tipo de data" (7 campos); estado `dateField` persistido em localStorage

> owner: — · priority: p1 · estimate: 4h · status: done · type: story · origin: heatmap-2026-05-11
> blocked_by: US-SELL-015
> evidence: 3-4 campos data com uso real >30% em pelo menos 1 cliente (DT_FATURAMENTO 92% Extreme/Gold · DT_COMPETENCIA 100% Vargas · DT_PROMETIDO 85% Gold). Preset Ano essencial (10+ anos histórico em todos)

**Contexto.** Delphi tem botões verdes Dia/Semana/Mês/Ano + dropdown "Personalizado · Data:" com 6 opções (Última Alteração, Emissão NF, Emissão, Dt. Faturamento, Dt. Env. Faturamento, Dt. Competência, Dt. Prometido). Sinal pra ativar: snapshot Firebird mostrar ≥30% das sessões usando filter por data customizado.

**Escopo (a especificar quando sinal confirmar):** botões `<Tabs>` Dia/Semana/Mês/Ano default `emissão`; dropdown "Tipo de data" pra trocar campo filtrado; date-range custom (popover `<DateRangePicker />`); URL deep-link `?date_from=...&date_to=...&date_field=transaction_date`.

### US-SELL-019 · Agrupamento drag-to-group por campo do grid · **P1 confirmado**

**Implementado em:** _pendente_ — agrupamento drag-to-group REMOVIDO na Unificacao 2026-05-21 (groupBy deletado do Index; SellsGroupByTest orfao)

> owner: — · priority: p1 · estimate: 8h · status: todo · type: story · origin: heatmap-2026-05-11
> blocked_by: US-SELL-015
> evidence: CODFINANCEIRO_GRUPO em uso 43-65% das linhas em todos clientes (WR2 34.5% · Vargas 65.1% · Extreme 43.3% · Gold 53.1%)

**Contexto.** Delphi tem barra "Arraste uma coluna para fazer o agrupamento" no topo do grid. Cliente arrasta "Cliente" → vendas agrupadas por cliente com subtotal. Sinal: snapshot Firebird mostrar ≥20% das sessões usando agrupamento.

**Escopo (a especificar):** TanStack Table `getGroupedRowModel`; drag-to-group via dnd-kit; subtotal por grupo (count + sum); expand/collapse por grupo; multi-level grouping (Cliente → Status → Mês).

### US-SELL-020 · Especificação campo "Status" (financeiro vs produção vs fiscal — badges separados) · **P2 (rebaixado)**

**Implementado em:** _pendente_ — 3 status separados (financeiro/producao/fiscal) nao implementados; P2 rebaixado, aguarda sinal

> owner: — · priority: p2 · estimate: 2h · status: todo · type: story · origin: heatmap-2026-05-11
> blocked_by: US-SELL-015
> evidence: SITUACAO estruturado só em Gold (7 distinct, 29k vendas EM PRODUÇÃO); WR2 5 distinct mas pobre; Vargas/Extreme 1 distinct vazio = não usa. Status separados em badges é **feature de cliente específico (PCP)**, não padrão

**Contexto.** Hoje "Status" é só financeiro (Pago/A receber/Parcial/Atrasada). Delphi mostra 3 status separados: Financeiro, Produção ("EM APROVAÇÃO", "ENTREGUE", "ORC APROVA…"), Fiscal (Rejeitada/Emitir). Sinal: reclamação cliente migrado.

**Escopo (a especificar):** 3 colunas badge distintas — `Status Financeiro` (atual), `Status Produção` (depende US-SELL-023), `Status Fiscal` (já existe parcial via US-NFE-MANUAL).

### US-SELL-021 · Especificação campo "Data" (qual data: emissão / NF / faturamento / competência / prometido) · **P0 (subido!)**

**Implementado em:** `resources/js/Pages/Sells/_components/SellsDateFilter.tsx` · `app/Http/Controllers/SellController.php` · `tests/Feature/Sells/SellsIndexDateFieldTest.php` · verificado@176f9bc (2026-07-01) — dropdown "Tipo de data" troca qual data exibir/filtrar; `inertiaList` usa whitelist `dateFieldMap` (7 chaves canon, anti-SQL-injection) + JOIN `nfe_emissoes` condicional + `display_date` deep-link `?date_field=`

> owner: — · priority: p0 · estimate: 3h · status: done · type: story · origin: heatmap-2026-05-11
> blocked_by: US-SELL-015
> evidence: DT_PROMETIDO existe e é 85% preenchido em Gold mas **ausente como coluna** em WR2/Vargas/Extreme. Schema OfficeImpresso varia entre instalações — Grade Avançada **não pode hardcodar colunas**, header da coluna Data precisa dropdown dinâmico ler o que existe

**Contexto.** Hoje coluna "Data" mostra `transaction_date`. Delphi mostra 6 datas: Emissão, Última Alteração, Emissão NF, Dt. Faturamento, Dt. Env. Faturamento, Dt. Competência, Dt. Prometido. Sinal: reclamação cliente migrado ("qual data é essa?").

**Escopo (a especificar):** header da coluna Data tem dropdown pra trocar qual data exibir; URL `?date_field=...` deep-link; tooltip mostra todas as 6 datas em hover.

### US-SELL-022 · Sub-linha de produtos por venda (expandir linha) · **P2 confirmado**

**Implementado em:** _pendente_ — sub-linha de produtos por venda (expandir inline) nao implementada em SellsTabelaUnificada

> owner: — · priority: p2 · estimate: 6h · status: todo · type: story · origin: heatmap-2026-05-11
> blocked_by: US-SELL-015
> evidence: Vargas média 3.08 itens/venda (47% das vendas 2-5 itens; 15% 6+); outros marginais (1.30-1.58). Vale pra cliente gráfica produtiva, não pra majoritária

**Contexto.** Delphi mostra produto + MEDIDAS · Quant · R$ Valor · R$ Total · Situação ao expandir uma venda inline no grid (sem abrir drawer). Útil pra gráfica que vende lona 5,60×3,10m. Sinal: snapshot Firebird mostrar ≥15% das sessões usando expandir.

**Escopo (a especificar):** ícone chevron à esquerda da linha; fetch lazy dos itens da venda; render sub-tabela compacta.

### US-SELL-023 · Status produção visível na lista (badge separado) · **P1 (subido!)**

**Implementado em:** _pendente_ — status producao badge na lista depende de FSM producao + mapping; coluna nao ativa

> owner: — · priority: p1 · estimate: 3h · status: todo · type: story · origin: heatmap-2026-05-11
> blocked_by: US-SELL-020, FSM ([ADR 0129](../../decisions/0129-state-machine-canonica-fsm-rbac.md))
> evidence: Gold tem **29.559 vendas em "EM PRODUÇÃO" + 7.082 "FINALIZADA"** — uso massivo de PCP. Tabela `AGENDA_TITULO_WORKFLOW` aparece em todos 3 clientes (Vargas/Extreme/Gold) como possível fonte de workflow

**Contexto.** Delphi mostra ENTREGUE/REIMPRESSÃO/EM APROVAÇÃO/ORC APROVA. Requer FSM produção (US-SELL-011 base + processo "Venda com Produção" novo) e mapping → badge. Investigar `AGENDA_TITULO_WORKFLOW` no PR.

### US-SELL-024 · Campo "venda agrupada" explícito · **P1 (subido!)**

**Implementado em:** _parcial_ · `database/migrations/2026_05_12_140001_add_is_grouped_invoice_to_transactions.php` · `app/Http/Controllers/SellController.php` · verificado@176f9bc (2026-07-01) — coluna boolean `is_grouped_invoice` (migration idempotente + índice composto Tier 0) + payload `inertiaList` (`COALESCE`, cast bool) vivos; MAS badge "Agrupada" na lista não renderiza (o `GroupedInvoiceBadge` vivia no `SellsGradeAvancada` deletado na Unificação 2026-05-21)

> owner: — · priority: p1 · estimate: 2h · type: story · origin: heatmap-2026-05-11
> blocked_by: US-SELL-015, US-SELL-019
> evidence: Mesmo sinal de US-SELL-019 (43-65% das linhas com CODFINANCEIRO_GRUPO em todos clientes). Sem coluna explícita `is_grouped_invoice`, o agrupamento fica ambíguo como no Delphi ("ATIVO CRIADO" string)

**Contexto.** Delphi infere "está agrupada" do texto "ATIVO CRIADO" no campo Status (confuso pro cliente). Fazer certo: coluna boolean `is_grouped_invoice` + badge "Agrupada" quando true.

### US-SELL-025 · Botões agrupamento rápido (1-click) · **P3 confirmado**

**Implementado em:** _pendente_ — botoes agrupamento 1-click dependem de telemetria pos-US-019 (removida); P3

> owner: — · priority: p3 · estimate: 2h · status: todo · type: story · origin: heatmap-2026-05-11
> blocked_by: US-SELL-019
> evidence: depende de telemetria pós-US-SELL-019 — só depois saberemos quais 3 agrupamentos são os mais usados

**Contexto.** Telemetria pós-US-SELL-019 vai mostrar quais 3 agrupamentos são mais usados; vira botões 1-click ("Por Cliente", "Por Mês", "Por Status").

### US-SELL-026 · Impressão batch de vendas selecionadas (PDF consolidado) · **P2 (subido)**

**Implementado em:** _pendente_ — impressao batch com layout consolidado (capa+N+totalizador) nao feita; US-016 entrega so concatenacao

> owner: — · priority: p2 · estimate: 3h · status: todo · type: story · origin: heatmap-2026-05-11
> blocked_by: US-SELL-016
> evidence: power-user OfficeImpresso vai pedir — expectativa óbvia ao migrar (Delphi tinha "Relatório de Vendas Selecionadas"). Não é P0 só porque US-SELL-016 já entrega "imprimir seleção" combinando DANFEs; P2 é layout consolidado (capa + N notas + totalizador)

**Contexto.** US-SELL-016 entrega "Imprimir seleção" combinando DANFEs. P2 estende pra layout consolidado (1 capa + N notas + 1 totalizador) — útil pra entregar lote físico ao cliente OfficeImpresso que recebia "Relatório de Vendas Selecionadas" do Delphi.

### US-SELL-027 · Schema discovery dinâmico Grade Avançada · **P0 (subida v2!)**

**Implementado em:** _pendente_ — schema discovery dinamico (job discover-schema + parser DFM) nao implementado; so scripts probe standalone

> owner: — · priority: p0 · estimate: **10h** (aumentou v4 — parser DFM) · status: todo · type: story · origin: heatmap-v2-2026-05-11
> blocked_by: US-SELL-015
> evidence: heatmap v2 (correções Wagner) + probe `CONFIGURACOES_GRID` v4 (Agent B PR #545) revelou **5ª dimensão** crítica que v2/v3 não previam — config de coluna do user vive em **BLOB DFM DevExpress serializado** dentro da tabela `CONFIGURACOES_GRID` Firebird, não em colunas estruturadas

**Contexto v4 (atualização pós-PR #545):**

Discovery atravessa **5 dimensões** (não 4 como v3 dizia):

1. **Colunas data** em `VENDA` (`PROJETO_DT_FIM` que é "Dt. Prometido", `DT_COMPETENCIA`, `DT_ENVIO_FATURAMENTO` — variam por cliente — corrigido pelo mapping source-first PR #540)
2. **Fontes status** (`VENDA.SITUACAO` inline · `VENDA_SITUACAO` lookup · `VENDA_ESTAGIO` FSM · `VENDA_PRODUTO_CENTRO_TRABALHO` PCP — clientes usam UMA das 4, raramente combinam)
3. **Veículos** em `EQUIPAMENTO_VEICULO` (Vargas 80% PLACA + 20% PLACA2 + 19% CHASSI — recapagem cavalo+reboque; Martinho 96% PLACA pura; Extreme/Gold zero)
4. **Agrupamento** (`CODFINANCEIRO_GRUPO` — universal 34-65% das linhas; sempre detectar)
5. **⚠️ NOVO v4:** Config de coluna do user vive em **BLOB DFM DevExpress** dentro de `CONFIGURACOES_GRID.GRID` (~12-16KB binário por config). Parser ASCII detecta `TcxGridDBColumn`/`Visible: True/False`/`GroupIndex`/`SortOrder`. Achados PR #545:
   - **42 colunas declaradas, 13-18 visíveis avg** (clientes filtram 60-70% por default — defeito do default!)
   - **Quantidade de grids salvos = proxy company size** (Vargas 548 / Martinho 690 / WR2 253) — útil pra qualificar lead pré-demo
   - **Agrupamento usado em 2/5 clientes** (Cliente_F8E47B 12.5% e Cliente_3A1E70 33.3%) — confirma US-SELL-019 P1 **condicional** (não universal)
   - **Sort persistido = 0%** — NÃO priorizar persistência de sort no V1 Grade Avançada (low impact)

**Escopo atualizado:**

- [ ] Job artisan `officeimpresso:discover-schema {business_id}` rodado uma vez no setup quando `business.legacy_origin = 'officeimpresso'`:
  - Conecta ao Firebird do cliente (configuração `business.legacy_firebird_dsn`)
  - Dumpa colunas de `VENDA`, conta `% preenchimento` e `count(distinct)` de campos-chave (dimensões 1-4)
  - **NOVO:** lê `CONFIGURACOES_GRID` filtrando `WHERE FORM LIKE '%Venda%' AND ATIVO='S'`, parse BLOB DFM via biblioteca Python ou script PHP que extrai colunas visíveis/agrupamento via regex ASCII
  - Salva em `business.legacy_origin_features` (JSON column nova)
- [ ] `business.legacy_origin_features` schema **expandido** (v4):
  ```json
  {
    "venda_columns": [...],
    "date_fields": {"DT_EMISSAO": 100, "PROJETO_DT_FIM": 85, ...},
    "situacao_distinct": 7,
    "tem_workflow": true,
    "user_grid_configs": {
      "Lista de Vendas": {
        "configured_users": 12,
        "common_visible_columns": ["DT_EMISSAO", "RAZAOSOCIAL", "TOTAL", ...],
        "rarely_visible": ["DT_COMPETENCIA", "PROJETO_DT_FIM"],
        "agrupamento_usado": false,
        "grids_total": 548
      }
    }
  }
  ```
- [ ] `HandleInertiaRequests::share('sells.legacy_features')` propaga JSON pra Inertia
- [ ] `<GradeAvancadaLayout/>` lê features e configura colunas dinamicamente: coluna existe? `% > LIMIAR_VISIVEL (10%)`? renderiza; senão, esconde. **NOVO:** colunas em `user_grid_configs.common_visible_columns` ficam visíveis por default (preserva fluxo do user OfficeImpresso); restante colapsável.
- [ ] UI admin `/admin/businesses/{id}/legacy-features` permite ajustar colunas visíveis manualmente (override do discovery)
- [ ] **NOVO:** Script standalone `scripts/probe_configuracoes_grid_blob.py` (parser DFM) virou base — incorporar via wrapper PHP no artisan
- [ ] Pest: 4 tests — discovery cria JSON, layout esconde coluna ausente, override admin persiste, parser DFM extrai colunas corretamente de fixture BLOB

**Acceptance criteria atualizado:**

- [ ] Cliente Gold cai com `PROJETO_DT_FIM` + `DT_EMISSAO` + `DT_FATURAMENTO` + `SITUACAO` visíveis (heatmap confirma uso)
- [ ] Cliente Vargas cai com `DT_EMISSAO` + `DT_COMPETENCIA` + `DT_FATURAMENTO` + `DT_ENVIO_FATURAMENTO` + `PLACA`/`PLACA2`/`CHASSI`/`CHASSI2` visíveis (recapagem); `PROJETO_DT_FIM` escondido automaticamente
- [ ] Cliente Extreme cai com `PROJETO_DT_FIM` + `DT_EMISSAO` + `DT_FATURAMENTO` + `DT_ENVIO_FATURAMENTO` visíveis (gráfica industrial PCP); zero veículo
- [ ] Cliente Martinho cai com `PLACA` (sem 2ª) + `DT_EMISSAO` + status `VENDA_ESTAGIO`/`VENDA_SITUACAO` visíveis
- [ ] Zero linha de código de Grade Avançada referencia coluna específica — tudo via lookup `legacy_origin_features.columns`
- [ ] Wagner pode verificar "company size" do cliente novo via 1 query no artisan (pré-demo lead qualification)

**Refs:**
- US-SELL-015 (toggle base), US-SELL-021 (header dropdown qual data lê de features — mergeado PR #548)
- [HEATMAP-CONSOLIDADO.md](../../research/2026-05-sells-grade-heatmap/HEATMAP-CONSOLIDADO.md) §1 origem da US
- **[CONFIGURACOES-GRID.md](../../research/clientes-legacy-officeimpresso/_MAPPING/CONFIGURACOES-GRID.md)** ⭐ mapping canônico tabela + schema BLOB DFM + sinais (PR #545)
- Skill [officeimpresso-source-analysis](../../../.claude/skills/officeimpresso-source-analysis/SKILL.md)
- Scripts: `scripts/probe_configuracoes_grid.py` + `scripts/probe_configuracoes_grid_blob.py` (PR #545)

### US-SELL-028 · Modules/OficinaAuto — schema com multi-placa (cavalo+reboque) · **P1 (emergente v3 — recalibrada)**

**Implementado em:** _pendente_ — schema veiculo OficinaAuto existe mas NAO no formato multi-placa desta US; dominio mudou pos-ADR 0265 (erradicacao locacao)

> owner: — · priority: p1 · estimate: 4h · status: todo · type: story · origin: heatmap-v3-2026-05-11-vargas-recapagem
> blocked_by: ADR `Modules/OficinaAuto` qualificada (futuro amend de ADR 0121)
> evidence: 2 de 4 candidatos OfficeImpresso saudáveis são oficina (Vargas grande recapagem caminhão + Martinho mecânica pesada caminhão basculante · sub-vertical 4 ADR 0194 — pré-correção dizia "caçambas avulsas"). Vargas exige multi-placa (PLACA2 20%, CHASSI2 8%) — cavalo+reboque. Martinho usa só PLACA simples (96% — caminhões de CLIENTES). Schema deve cobrir ambos casos: PLACA obrigatória + PLACA_SECUNDARIA opcional + CHASSI opcional + CHASSI_SECUNDARIO opcional. Ver [perfil Vargas](../../research/clientes-legacy-officeimpresso/02-vargas-recapagem/01-perfil.md) e [perfil Martinho](../../research/clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md)

**Contexto.** v3 corrigiu inferência inicial (v2 dizia Vargas "gráfica + frota"; Wagner clarificou que é **oficina de recapagem de caçamba de caminhão**). Logo, premissa multi-vertical do v2 cai. O caso real: oficina-auto tem schema **com PLACA simples (caso majoritário Martinho)** + **PLACA secundária opcional pro cavalo+reboque (caso Vargas)**.

**Escopo:**
- [ ] `Modules/OficinaAuto/Models/Veiculo.php` com:
  - `placa` (obrigatório)
  - `placa_secundaria` (opcional, pra cavalo+reboque)
  - `chassi` (opcional)
  - `chassi_secundario` (opcional)
  - `ano_fabricacao`, `ano_modelo`, `renavam` (opcionais)
  - `tipo` (caminhão, caminhonete, cavalo, semi-reboque, caçamba-estacionária)
- [ ] Migration com `business_id` global scope ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))
- [ ] UI cadastro veículo com seção "Cavalo+Reboque" colapsável (só preenche quando `tipo IN (cavalo, semi-reboque)`)
- [ ] Importador legacy mapeia `EQUIPAMENTO_VEICULO.PLACA2/CHASSI2` → `placa_secundaria/chassi_secundario`
- [ ] Pest: 3 tests — veículo simples Martinho, veículo cavalo+reboque Vargas, isolation multi-tenant

**Acceptance criteria:**
- [ ] Martinho importa 91 veículos com PLACA única (PLACA_SECUNDARIA null)
- [ ] Vargas importa 1.064 veículos, 216 com PLACA_SECUNDARIA preenchida (cavalo+reboque)
- [ ] OS aberta pra veículo Vargas exibe ambas placas no resumo

**Refs:** US-SELL-027 (schema discovery alimenta features OficinaAuto), [HEATMAP-CONSOLIDADO §3.3](../../research/2026-05-sells-grade-heatmap/HEATMAP-CONSOLIDADO.md), perfis 02-vargas + 05-martinho.

---

## 6. Pipeline Vendas — 7 GAPs canônicos (sessão 2026-05-12)

> Cadeia criada após discovery profundo de [ADR 0129](../../decisions/0129-state-machine-canonica-fsm-rbac.md) + código `app/Domain/Fsm/` existente + auditoria NfeService.
> **Pain points reais Wagner 2026-05-12:**
> 1. *"cancelam nota perdem número pula sequencial"* → G1+G2 (US-029/030)
> 2. *"orçamento foi para estágio voltou sem ninguém ter autorizado"* → G3+G4 (US-031/032)
> 3. *"produção iniciada sem pessoas ter autorizado"* → G5 (US-033)
>
> Doc canônico: [CASOS-USO-PIPELINE-VENDAS.md](./CASOS-USO-PIPELINE-VENDAS.md) — 7 casos Given/When/Then + 5 arquivos Pest failing-first.
> **Aprovação pendente Wagner** antes de implementar qualquer linha.

### US-SELL-029 · NFe cancelada via SEFAZ não sofre forceDelete (preserva sequencial) · **P0 fiscal**

**Implementado em:** `Modules/NfeBrasil/Services/NfeService.php` · verificado@cd84a38 (2026-07-01)

**Testado em:** `tests/Feature/Domain/Fsm/SequencialNfeAposCancelamentoTest.php` (covers US-SELL-029)

> owner: — · priority: p0 · estimate: 3h codável + 5h tests · status: done · type: story · origin: sessao-2026-05-12-discovery-pipeline
> blocked_by: — (precede US-030)
> evidence: bug confirmado em [NfeService.php:380-398](../../../Modules/NfeBrasil/Services/NfeService.php#L380) — `cancelada` tratada igual `rejeitada/denegada` recebe `forceDelete()`, próxima emissão pula sequencial

**Contexto.** SEFAZ distingue: `cancelada via evento` (número usado oficialmente, imutável) ≠ `rejeitada/denegada` (número não declarado, reaproveitável via inutilização). Mistura atual gera buraco no sequencial fiscal sujeito a multa ([CONFAZ Ajuste SINIEF 07/2005 Art. 14](https://www.confaz.fazenda.gov.br/legislacao/ajustes/2005/ajuste-007-05)).

**Escopo:**
- [ ] Refator `NfeService::emitir()` linha 380: distinguir `cancelada` (bloqueia retry com erro instrutivo) de `rejeitada/denegada` (permite retry após inutilização)
- [ ] NÃO usar mais `forceDelete()` — preservar registro com status `inutilizado` em vez de hard delete
- [ ] Action FSM nova `emitir_nova_apos_cancelamento` (cria nova `transaction_id` que aponta pra transaction original — bridge)
- [ ] Pest 7+ testes em [`tests/Feature/Domain/Fsm/SequencialNfeAposCancelamentoTest.php`](../../../tests/Feature/Domain/Fsm/SequencialNfeAposCancelamentoTest.php) (criado failing-first)

**Acceptance criteria:**
- [ ] `SELECT numero, status FROM nfe_emissoes WHERE business_id=1 AND modelo='55' ORDER BY numero` retorna sequência contínua mesmo após cancelamento
- [ ] Tentativa de re-emitir mesma transaction com NFe cancelada lança `RuntimeException` com mensagem instrutiva
- [ ] Pest `SequencialNfeAposCancelamentoTest` todos verdes
- [ ] Smoke biz=1: cancelar NFe → criar nova venda → conferir `proximoNumeroLocked` avança sem pular

### US-SELL-030 · NfeInutilizacaoService — chama SEFAZ + persiste em `nfe_inutilizacoes` · **P0 fiscal**

**Implementado em:** `Modules/NfeBrasil/Services/NfeInutilizacaoService.php` · `Modules/NfeBrasil/Database/Migrations/2026_05_06_002003_create_nfe_inutilizacoes_table.php` · `app/Domain/Fsm/SideEffects/InutilizarFaixaNfe.php` · verificado@cd84a38 (2026-07-01)

**Testado em:** `tests/Feature/Domain/Fsm/SequencialNfeAposCancelamentoTest.php` (covers US-SELL-030) · `tests/Feature/Domain/Fsm/SideEffects/InutilizarFaixaNfeTest.php` (covers US-SELL-030)

> owner: — · priority: p0 · estimate: 6h codável + 4h tests · status: done · type: story · origin: sessao-2026-05-12-discovery-pipeline
> blocked_by: US-029 (refator NfeService precede)
> evidence: tabela `nfe_inutilizacoes` existe ([migration 002003](../../../Modules/NfeBrasil/Database/Migrations/2026_05_06_002003_create_nfe_inutilizacoes_table.php)) **sem service que a use**

**Contexto.** Tabela criada na fundação mas sem código que dispare inutilização via SEFAZ. Caso real: lote de NFes rejeitadas precisa inutilizar faixa pra preservar sequencial (ex: erro técnico + retry impossível).

**Escopo:**
- [ ] `Modules\NfeBrasil\Services\NfeInutilizacaoService::inutilizar($businessId, $modelo, $serie, $numeroDe, $numeroAte, $justificativa)`
- [ ] Validações: justificativa 15-255 chars (regra SEFAZ), cross-tenant guard, faixa válida (numeroDe ≤ numeroAte)
- [ ] Integração `NFePHP\NFe\Tools::sefazInutiliza()`
- [ ] Persiste em `nfe_inutilizacoes` + atualiza status `inutilizado` em `nfe_emissoes` da faixa
- [ ] Action FSM `inutilizar_faixa` chamável via UI admin fiscal
- [ ] Pest cobertura: faixa simples, faixa múltipla, justificativa curta, cross-tenant, cstat=102 success / cstat≠102 failure

**Acceptance criteria:**
- [ ] Service callable via UI admin: form `numero_de`, `numero_ate`, `justificativa`
- [ ] Após inutilizar: faixa marcada `inutilizado` em `nfe_emissoes`, registro em `nfe_inutilizacoes` com cstat=102 ou error trace
- [ ] Smoke biz=1: inutilizar nº 200-205 → próxima emissão pega 206

### US-SELL-031 · Action FSM crítica (is_critical) exige role explícita (fail-secure) · **P1 governança**

**Implementado em:** `database/migrations/2026_05_12_010001_add_is_critical_to_sale_stage_actions.php` · `app/Domain/Fsm/Services/ExecuteStageActionService.php` · verificado@cd84a38 (2026-07-01)

**Testado em:** `tests/Feature/Domain/Fsm/TransicaoCriticaExigeAutorizacaoTest.php` (covers US-SELL-031)

> owner: — · priority: p1 · estimate: 2h codável + 1h tests · status: done · type: story · origin: sessao-2026-05-12-discovery-pipeline
> blocked_by: —
> evidence: [ExecuteStageActionService.php:62](../../../app/Domain/Fsm/Services/ExecuteStageActionService.php#L62) — `empty($roleNames)` libera pra qualquer user; seed incompleto vira bypass silencioso

**Contexto.** Hoje action sem role cadastrada permite execução. Pra actions de risco (cancelar venda, voltar estágio, iniciar produção), comportamento fail-secure: sem role = bloqueio.

**Escopo:**
- [ ] Migration `add_is_critical_to_sale_stage_actions` (boolean default false)
- [ ] Refator `ExecuteStageActionService::execute()`: se `is_critical && empty($roleNames)` → `UnauthorizedActionException` com mensagem instrutiva
- [ ] Seeder atualiza actions de risco com `is_critical=true` + role mínima default
- [ ] Pest 5 testes em [`tests/Feature/Domain/Fsm/TransicaoCriticaExigeAutorizacaoTest.php`](../../../tests/Feature/Domain/Fsm/TransicaoCriticaExigeAutorizacaoTest.php) (criado failing-first)

**Acceptance criteria:**
- [ ] Action `is_critical=true` sem role bloqueia execução
- [ ] Action `is_critical=false` sem role mantém comportamento aberto (back-compat)
- [ ] Mensagem da exception instrui qual role configurar

### US-SELL-032 · Observer bloqueia UPDATE direto em current_stage_id (gateway obrigatório) · **P1 governança**

**Implementado em:** `app/Domain/Fsm/Observers/TransactionFsmObserver.php` · `app/Domain/Fsm/Concerns/GuardsFsmTransitions.php` · `app/Domain/Fsm/Support/FsmAuthorizationFlag.php` · `app/Console/Commands/FsmScanDriftCommand.php` · verificado@cd84a38 (2026-07-01)

**Testado em:** `tests/Feature/Domain/Fsm/CurrentStageIdBypassObserverTest.php` (covers US-SELL-032)

> owner: — · priority: p1 · estimate: 4h codável + 3h tests · status: done · type: story · origin: sessao-2026-05-12-discovery-pipeline
> blocked_by: —
> evidence: ExecuteStageActionService é gateway recomendado mas não obrigatório — bypass via Eloquent direto, query builder mass-update, tinker, ou DB::table

**Contexto.** Pra transformar service em gateway obrigatório, Observer Eloquent intercepta `saving` de `current_stage_id`. Flag interna `_fsmAuthorizedTransition` setada pelo service contorna. Acesso superadmin via flag explícita + log estruturado.

**Escopo:**
- [ ] `App\Domain\Fsm\Observers\TransactionFsmObserver` com hook `updating`
- [ ] Modificar `ExecuteStageActionService::execute()` pra setar `$subject->_fsmAuthorizedTransition = true` antes do `save()`
- [ ] Registrar observer em `Transaction::booted()` + em qualquer model FSM-managed (Repair JobSheet futuro)
- [ ] Comando artisan `fsm:scan-drift` detecta drift via raw DB::table updates (Observer não pega)
- [ ] Pest 5 testes em [`tests/Feature/Domain/Fsm/CurrentStageIdBypassObserverTest.php`](../../../tests/Feature/Domain/Fsm/CurrentStageIdBypassObserverTest.php) (criado failing-first)
- [ ] Doc no SPEC: padrão "todo write em current_stage_id passa pelo Service"

**Acceptance criteria:**
- [ ] UPDATE direto (Eloquent ou Eloquent::update) lança `UnauthorizedActionException`
- [ ] ExecuteStageActionService passa normal
- [ ] `php artisan fsm:scan-drift` detecta registros que mudaram via raw SQL e loga WARNING

### US-SELL-033 · Processo seed "Venda Com Produção" canônico (9 stages + 12 actions + roles) · **P0 negócio**

**Implementado em:** `database/seeders/FsmProcessoVendaComProducaoSeeder.php` · `app/Domain/Fsm/Services/InitialStageResolver.php` · verificado@cd84a38 (2026-07-01)

**Testado em:** `tests/Feature/Domain/Fsm/ProcessoVendaComProducaoTest.php` (covers US-SELL-033)

> owner: — · priority: p0 · estimate: 6h codável + 4h tests · status: done · type: story · origin: sessao-2026-05-12-discovery-pipeline
> blocked_by: US-031 (is_critical) + US-032 (Observer)
> evidence: 3 processos seed atuais (Sem Nota / Com Nota Manual / Com Nota Auto) **não têm stages de produção** — gambiarra/informal pra clientes OficinaAuto/ComunicacaoVisual/Vestuario

**Contexto.** Pipeline canônico cobre ciclo completo Orçamento → Produção → Venda → Faturamento com sub-FSM internas por setor (RBAC granular por transição).

**Stages canônicos:**
```
quote_draft → quote_sent → quote_approved → in_production →
ready_for_invoice → invoiced → paid → delivered → completed (terminal)
Transições laterais: cancelar_venda → cancelled (terminal),  pausar → on_hold
```

**Actions com roles obrigatórias (is_critical=true marcadas com 🔒):**
- `enviar_orcamento` — role `vendas.enviar`
- `cliente_aprovou` — role `vendas.confirmar_aprovacao` 🔒 + side_effect `ReservarEstoque`
- `cliente_rejeitou` — role `vendas.confirmar_aprovacao`
- `iniciar_producao` — role `producao.iniciar` 🔒
- `pausar_producao` — role `producao.pausar`
- `concluir_producao` — role `producao.concluir` 🔒 + side_effect `ConsumirEstoque`
- `faturar` — role `financeiro.faturar` 🔒
- `emitir_nfe` — role `fiscal.emitir` 🔒 + side_effect `EmitirNFeJob`
- `marcar_pago` — role `financeiro.baixar` 🔒 + side_effect `BaixarFinanceiro`
- `entregar` — role `logistica.entregar`
- `concluir` — role `vendas.gerente`
- `cancelar_venda` — role `vendas.gerente` 🔒 + side_effect `CancelarVendaCascade` (US-034)
- `reabrir_para_revisao` (volta `quote_approved → quote_sent`) — role `vendas.gerente` 🔒

**Escopo:**
- [ ] Seeder `Database\Seeders\FsmProcessoVendaComProducaoSeeder` (idempotente, por business)
- [ ] Roles novas via Spatie Permission seed: `producao.iniciar`, `producao.pausar`, `producao.concluir`, `vendas.enviar`, `vendas.confirmar_aprovacao`, `vendas.gerente`, `fiscal.emitir`, `financeiro.faturar`, `financeiro.baixar`, `logistica.entregar`
- [ ] Comando artisan `fsm:install-process {business_id} venda_com_producao`
- [ ] Pest 7 testes em [`tests/Feature/Domain/Fsm/ProcessoVendaComProducaoTest.php`](../../../tests/Feature/Domain/Fsm/ProcessoVendaComProducaoTest.php) (criado failing-first)
- [ ] Charter `memory/requisitos/Sells/CHARTER-pipeline-vendas.charter.md` (S4 antecipado)

**Acceptance criteria:**
- [ ] Seeder cria processo + 11 stages (9 lineares + cancelled + on_hold) + 13 actions + 10 roles
- [ ] Fluxo feliz end-to-end testado: rascunho → completed (8 transições)
- [ ] Multi-tenant: seeder biz=1 não vaza pra biz=99
- [ ] Idempotência: rodar 2x não cria duplicatas

### US-SELL-034 · Side-effect `CancelarVendaCascade` orquestra NFe + boleto + reserva + notificação · **P1 negócio**

**Implementado em:** `app/Domain/Fsm/SideEffects/CancelarVendaCascade.php` · verificado@cd84a38 (2026-07-01)

**Testado em:** `tests/Feature/Domain/Fsm/CancelarVendaCascadeSideEffectTest.php` (covers US-SELL-034)

> owner: — · priority: p1 · estimate: 4h codável + 3h tests · status: done · type: story · origin: sessao-2026-05-12-discovery-pipeline
> blocked_by: US-029 (cancelamento NFe correto) + US-033 (action cancelar_venda)
> evidence: hoje cancelar venda é processo manual com risco de inconsistência (cancela NFe mas esquece de estornar boleto, libera reserva mas não notifica cliente, etc)

**Contexto.** Side-effect transacional canônico que orquestra todos os efeitos colaterais do cancelamento — best-effort com idempotência por job individual.

**Escopo:**
- [ ] `App\Domain\Fsm\SideEffects\CancelarVendaCascade implements SideEffectInterface`
- [ ] Jobs filhos (dispatch dentro do side-effect):
  - `Modules\NfeBrasil\Jobs\CancelarNfeJob` (cancela cada NFe `authorized` via SEFAZ — não pula sequencial, US-029)
  - `App\Jobs\EstornarBoletoJob` (Asaas/Inter API cancel — idempotente)
  - `Modules\Whatsapp\Jobs\NotificarClienteCancelamentoJob` (WhatsApp/email "venda cancelada — motivo: X")
- [ ] Side-effect síncrono `LiberarReserva` (já existe, US-013)
- [ ] Pest 5 testes em [`tests/Feature/Domain/Fsm/CancelarVendaCascadeSideEffectTest.php`](../../../tests/Feature/Domain/Fsm/CancelarVendaCascadeSideEffectTest.php) (criado failing-first)

**Acceptance criteria:**
- [ ] Cancelar venda com NFe+boleto+reserva dispara 4 efeitos em ordem
- [ ] NFe já cancelada antes não duplica job (idempotência)
- [ ] Sem boleto: não dispara EstornarBoletoJob (caso vazio, não erro)
- [ ] Motivo registrado em `sale_stage_history.payload_snapshot`
- [ ] Smoke biz=1: cancelar venda real, conferir 4 efeitos rastreáveis no log

### US-SELL-035 · UI timeline de transições FSM (drawer + page) · **P2 UX/auditoria**

**Implementado em:** `app/Http/Controllers/SaleHistoryController.php` · `resources/js/Pages/Sells/_components/SaleTimeline.tsx` · `resources/js/Pages/Sells/_components/SaleSheet.tsx` · verificado@cd84a38 (2026-07-01)

**Testado em:** `tests/Feature/Domain/Fsm/SaleHistoryControllerTest.php` (covers US-SELL-035)

> owner: — · priority: p2 · estimate: 8h frontend (sem canary) · status: done · type: story · origin: sessao-2026-05-12-discovery-pipeline
> blocked_by: US-033 (processo canon) + visibilidade real após implementação
> evidence: `sale_stage_history` registra tudo desde US-011 mas **não há UI** mostrando — Wagner não consegue responder "quem aprovou? quando? com qual motivo?"

**Contexto.** Audit trail LGPD + governança operacional. Sem UI, o dado existe mas não é útil pra operador. Crítico pra Wagner responder reclamações de cliente ("você aprovou via WhatsApp em 12/05 14h32").

**Escopo:**
- [ ] Endpoint `/api/sells/{id}/history` retorna `sale_stage_history` com joins (user.name, action.label, stage_from.name, stage_to.name)
- [ ] Componente `<SaleTimeline />` em `resources/js/Pages/Sells/_components/SaleTimeline.tsx`
- [ ] Tab "Histórico" no drawer existente `SaleSheet.tsx` (já implementado em US-008)
- [ ] Filtros: tipo de transição (críticas / side-effects fiscais / todas), faixa de data
- [ ] Render badges de side-effects disparados (visual quick-scan)
- [ ] Pest controller test `SaleHistoryControllerTest` (autorização + multi-tenant isolation)

**Acceptance criteria:**
- [ ] Drawer mostra timeline vertical com 5+ transições de venda exemplo biz=1
- [ ] Cada item: user, action label, stage from→to, timestamp, motivo (se payload), badges side-effects
- [ ] LGPD: timeline só visível pra users com permission `sale.history.view` (default ON pra roles vendas.*, financeiro.*, gerencial)

---

**Refs:**
- Doc canônico [CASOS-USO-PIPELINE-VENDAS.md](./CASOS-USO-PIPELINE-VENDAS.md) (origem destas US)
- [ADR 0129](../../decisions/0129-state-machine-canonica-fsm-rbac.md) (fundação FSM)
- [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) (§5 SoC, §6 Tier 0)
- [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) (estimates recalibradas)

### US-SELL-036 · FSM rollout — migrar 14 vendas legadas biz=1 via bulk-start-pipeline + canary 7d

**Implementado em:** `app/Console/Commands/FsmBulkStartPipelineCommand.php` · `app/Console/Commands/FsmScanDriftCommand.php` · `resources/js/Pages/Sells/_components/FsmActionPanel.tsx` · verificado@cd84a38 (2026-07-01)

**Testado em:** `tests/Feature/Console/FsmBulkStartPipelineCommandTest.php` (covers US-SELL-036)

> owner: wagner · priority: p0 · estimate: 4h · status: done · type: story
> blocked_by: —

Migrar 14 vendas biz=1 do estado legacy pro FSM canon ADR 0143 (goal #3 CYCLE-06 — alvo: 14 de 162 vendas migradas).

**DoD:**
- [ ] Dry-run primeiro: `php artisan fsm:bulk-start-pipeline 1 --dry-run` valida 14 candidatos
- [ ] Aprovar lista 14 (cherry-pick por stage atual / payment_status / volume)
- [ ] Executar `php artisan fsm:bulk-start-pipeline 1 --limit=14` (sem --dry-run)
- [ ] Smoke: 14 transactions têm `current_stage_id` populado + 14 entries em `sale_stage_history` ("Pipeline iniciado")
- [ ] Canary 7d: monitorar `fsm:scan-drift transactions` daily 03:00 BRT pra detectar mass-update bypass
- [ ] Verificar drawer SaleSheet (`resources/js/Pages/Sells/_components/FsmActionPanel.tsx`) renderiza actions corretas pros stages das 14 vendas
- [ ] Comunicar Wagner (owner biz=1) sobre mudança de UX no drawer
- **Estimate:** 4h código + 7d canary monitoring (relógio mundo real)
- **blocked_by:** nenhum (FSM canon LIVE prod biz=1 desde 2026-05-12, ADR 0143)

### US-SELL-041 · NFC-e "emitir agora" no fim do Create (paridade Bling)

**Implementado em:** _pendente_ — botao Salvar-e-emitir-NFC-e no Create nao wired; VdNfeEmitModal existe mas so e usado no Index

> owner: wagner · priority: p1 · estimate: 4h · status: todo · type: story
> blocked_by: —

**Origem:** benchmark `tela-venda-arte` 2026-05-31 (gap **G5**, P1) — `memory/sessions/2026-05-31-tela-venda-arte.md`.

**Problema:** o Bling emite NFC-e DENTRO do fluxo da venda; no oimpresso a NFe/NFC-e só sai depois (drawer do Index ou flag listener auto). A Larissa (biz=4) vende E fatura — hoje precisa sair do Create e ir ao Index pra emitir.

**Aceite:**
- [ ] Botão "Salvar e emitir NFC-e" no footer/pós-save do Create, reusando `VdNfeEmitModal` (já existe) + `FiscalSection`.
- [ ] Gate por flag fiscal do business (só aparece se biz tem emissão habilitada — ex biz=4).
- [ ] Não quebra o fluxo "só salvar" (botão primário continua "Salvar venda").
- [ ] Pest cobrindo: salva venda → emite NFC-e cstat 100; venda sem flag → botão ausente.

**Impacto:** alto (paridade concorrente BR + sinal forte Larissa fatura). **Esforço IA-pair:** ~3-5h.

### US-SELL-042 · Batch no handlePriceGroupChange — elimina N+1 em /products/list

**Implementado em:** _pendente_ — handlePriceGroupChange ainda faz 1 request por linha (Promise loop), nao batched em /products/list

> owner: wagner · priority: p1 · estimate: 2h · status: todo · type: story
> blocked_by: —

**Origem:** benchmark `tela-venda-arte` 2026-05-31 (gap **G4**, P1) — `memory/sessions/2026-05-31-tela-venda-arte.md`.

**Problema:** ao trocar o grupo de preço do cliente, `handlePriceGroupChange` (`Sells/Create.tsx` ~L353-419) refaz **1 request por item** do carrinho pra re-buscar preço → N+1. Com carrinho grande, trava perceptível.

**Aceite:**
- [ ] Batchar num único `/products/list` (ou endpoint que aceite lista de variation_ids) em vez de 1 request por linha.
- [ ] Preço/grupo reaplicado a todas as linhas após 1 round-trip.
- [ ] Sem regressão no auto-aplica grupo de preço ao trocar cliente (US-SELL R8).

**Impacto:** médio (perf percebida com carrinho grande). **Esforço IA-pair:** ~1-2h. **Pré-req:** endpoint aceitar batch.

### US-SELL-043 · Migrar CSS Cowork (.sells-cowork / vd-*) → tokens DS no Sells/Index

**Implementado em:** _pendente_ — migracao CSS Cowork (sells-cowork/vd-*) para tokens DS no Sells/Index nao feita

> owner: wagner · priority: p1 · estimate: 6h · status: todo · type: story
> blocked_by: —

**Origem:** benchmark `tela-venda-arte` 2026-05-31 (gap **G6**, P1) — `memory/sessions/2026-05-31-tela-venda-arte.md`.

**Problema:** `Sells/Index.tsx` (~1806 linhas) é o cockpit de vendas (board 90, Leader) mas desvia do DS por **CSS Cowork scoped** (`.sells-cowork`, `vd-*`, oklch/hex/blue cru) fora do DS v4. É o que separa o Index de Champion (95+).

**Aceite:**
- [ ] Mapa classe↔token (auditar `vd-*` / `.sells-cowork` no bundle + no .tsx).
- [ ] Migrar pra tokens DS v4 / roxo 295; eliminar hex/blue cru (respeitando cores de status semânticas — ver convenção do projeto).
- [ ] Sem regressão visual (gate PRE-MERGE-UI + screenshot Wagner).

**Impacto:** médio (não move agulha da Larissa, mas destrava Champion). **Esforço IA-pair:** ~4-8h. **Relacionado:** cycle DS-v3 (provável ADR pra tokens semânticos de status).

---

### US-SELL-045 · Bug: payload `totals` morto na rede — backend calcula/envia, frontend nunca lê

**Implementado em:** _parcial_ · `resources/js/Pages/Sells/_components/SellsTotalBar.tsx` · `resources/js/Pages/Sells/Index.tsx` · verificado@9a67342 (2026-10-20) — opção (b), reexibir o totalizador do filtro inteiro, pedida por [M] em 2026-10-20 (ratificação [W] no merge); o payload `totals` volta a ser lido. Falta a parte do DoD sobre o `SellsTotalsTest`: os it() de front em quarentena testam arquivos apagados e o arquivo não roda em nenhuma lane de PR

**Testado em:** `tests/js/sells-index-totais.test.tsx` (covers US-SELL-045 · UC-SIDX-06; lane Vitest do `Frontend / Vite build`)

> owner: — · priority: p2 · estimate: 2h · type: story
> blocked_by: —

**Origem:** revisão adversarial da triage Q2 Fase 2b SDD (2026-06-13). Contrato órfão de backend confirmado por leitura de `origin/main`.

**Sintoma:** `SellController.php:1218-1229` (`inertiaList`) ainda computa e envia o payload `totals` (`sum_final_total`, `sum_total_paid`, `sum_due` via `clone($q)` pós-pill-filter + `COALESCE(SUM(...transaction_payments))` + `max(0,...)`), mas `resources/js/Pages/Sells/Index.tsx` faz `setTotals(json.totals)` (l.717) com `const [totals] = useState` (l.491) e **nunca lê** o getter `totals` nas 1807 linhas. Os KPIs `kpiToday`/`kpiAReceber` (l.800-852) têm semântica "hoje/scoped" — **não substituem** a soma sobre o filtro inteiro que o `SellsTotalsRow.tsx` (deletado) renderizava.

**É remoção pela metade.** O teste `SellsTotalsTest` vermelho está **correto** ao sinalizar.

**DECISÃO DE PRODUTO (Wagner):** (a) **Remover** o cálculo do controller + state morto (economiza query/request); OU (b) **Reexibir** o totalizador (rodapé soma do filtro inteiro — feature perdida).

**DoD:** decisão tomada; backend+frontend consistentes; `SellsTotalsTest` reescrito (não quarentenado).

Ref: triage `memory/sessions/2026-06-13-sdd-f2b-triage-q2.md` · US-GOV-017 fase 2b.

---

### US-SELL-046 · Bug: viewMode `grade-avancada` órfão — middleware roteia 6 clientes legacy pra UI deletada

**Implementado em:** _pendente_ — bug confirmado: `HandleInertiaRequests::sellsViewModeDefault` ainda retorna `'grade-avancada'` p/ `legacy_origin='officeimpresso'` mas `SellsToggleViewMode`/`SellsGradeAvancada` foram deletados (Index só trata como localStorage legacy→`financeira`); afeta clientes reais — aguarda DECISÃO DE PRODUTO Wagner (remover roteamento OU reimplementar grade)

> owner: — · priority: p2 · estimate: 3h · status: todo · type: story
> blocked_by: —

**Origem:** revisão adversarial da triage Q2 Fase 2b SDD (2026-06-13). Contrato multi-tenant órfão confirmado em `origin/main`.

**Sintoma:** `app/Http/Middleware/HandleInertiaRequests.php:523-536` (`sellsViewModeDefault`) ainda retorna `viewMode='grade-avancada'` quando `business.legacy_origin === 'officeimpresso'`. Migration `2026_05_12_180000_add_legacy_origin_to_business` + `BusinessLegacyOriginSeeder` (6 clientes reais: Vargas/Extreme/Gold/Zoom/Fixar/Produart) vivos. **MAS** os componentes que consumiam (`SellsToggleViewMode.tsx` + `SellsGradeAvancada.tsx`) foram **deletados** no refactor Sells→SellsTabelaUnificada. `Index.tsx:654-655` trata `grade-avancada` só como localStorage legacy migrado pra `financeira`.

**Resultado:** backend serve, pra 6 clientes pagantes legacy, um viewMode que o frontend não renderiza mais.

**DECISÃO DE PRODUTO (Wagner):** (a) **Remover** o roteamento + migration/seeder (cai no default); OU (b) **Reimplementar** a grade-avançada na UI nova. **Afeta clientes reais — confirmar antes.**

**DoD:** decisão tomada; middleware+frontend consistentes; teste reescrito.

Ref: triage `memory/sessions/2026-06-13-sdd-f2b-triage-q2.md` · US-GOV-017 fase 2b.

---

### US-SELL-047 · Teste de isolamento multi-tenant REAL da tela Sells (ADR 0093) — gap mascarado por grep

**Implementado em:** _pendente_ — teste de isolamento multi-tenant REAL (HTTP/DB, nao grep) da tela Sells nao existe; os testes atuais sao grep de source

**Testado em:** `tests/Feature/Sells/SellsIndexTenantContratoTest.php` — **pagamento PARCIAL desta US** (2026-07-27, chip Onda 2 do passo 5): primeiro teste de Sells que faz **HTTP+DB real** (GET sells-list-json com actingAs biz=1 e uma transacao plantada em biz=2), nao grep de source. Cobre 2 dos 4 itens do Escopo — o indicador de devolucao (UC-SIDX-01, com controle positivo pra nao passar por nao-execucao) e o agregado de dinheiro do rodape (UC-SIDX-02, antes->depois). **Continua _pendente_ de proposito:** faltam bulk-print/bulk-export com IDs alheios e o DoD "provado mutando".

> Lane: `PHP / Pest (Sells - MySQL)` — **advisory**, nao bloqueia merge. Contratos em Index.casos.md e no SDD §6.3 (CU-SELL-32/33). _(Os ponteiros sairam da linha `Testado em:` de proposito: o `anchor entry/covers gate` extrai TODO path daquela linha e cobra `@covers-us` nele — casos.md e workflow nao sao arquivo de teste.)_

> owner: — · priority: p1 · estimate: 4h · status: todo · type: story
> blocked_by: —

**Origem:** revisão adversarial da triage Q2 Fase 2b SDD (2026-06-13). Achado CRÍTICO: a tela Sells **não tem nenhum teste que exerça isolamento de tenant** — os ~254 it() em `tests/Feature/Sells/*` são 100% `file_get_contents`+regex (medido: 0/254 fazem HTTP/render/DB).

**Falso conforto:** os it() rotulados "multi-tenant Tier 0 (ADR 0093)" só **grepam o texto-fonte** (`->where('transactions.business_id'`, ordem `where...whereIn`, `not->toContain('withoutGlobalScopes')`). **Quebra-se a tenancy mantendo a string → passa verde.**

**Escopo (comportamento real):** `GET /sells-list-json` `actingAs(biz=1)` vs `biz=2` → não vaza venda cross-tenant; `POST /sells/bulk-print|bulk-export` com IDs de outro business → nega; `inertiaList` com filtros → `clone($q)` preserva escopo; cenário biz=99.

**DoD:** Pest com ≥1 caso que FALHA se removerem o `business_id` scope do `SellController` (provado mutando) — diferente dos greps que passam com tenancy quebrada. MySQL real (ADR 0101).

Ref: triage `memory/sessions/2026-06-13-sdd-f2b-triage-q2.md` · US-GOV-017 fase 2b.

---

### US-SELL-048 · Higiene dos snapshots-grep Sells: DELETE/REWRITE por it() (não quarentena) — gated no nº do nightly C1

**Implementado em:** _pendente_ — higiene DELETE/REWRITE dos snapshots-grep Sells nao executada; bloqueada por nightly C1 (US-GOV-017)

> owner: — · priority: p2 · estimate: 8h · status: todo · type: story
> blocked_by: US-GOV-017

**Origem:** revisão adversarial da triage Q2 (2026-06-13) REVERTEU a recomendação de quarentena Q-A. Os ~254 it() de `tests/Feature/Sells/*` são snapshots `file_get_contents`+regex determinísticos-stale — **quarentena é a ferramenta errada** (`@group legacy-quarantine` tem 0 commits no repo vs 1.423 `markTestSkipped` nunca queimados).

**Triagem por it() (rodar Pest por arquivo — NÃO confiar em classificação-prosa):** **DELETE ~28** (componente+feature mortos: groupBy TanStack, tab-bar/`SellsInsightsView`→Jana, toggle Lista|Grade) · **REWRITE/REPOINT ~45** (feature viva relocada: Comissão, `is_grouped_invoice`, bulk → `SellsTabelaUnificada`/`SellsTabsVisao`; `NumericInputPtBR`→`Components/ui/`; `SellsTabsViewModeTest`→~~`Jana/JanaCockpitV2.tsx`~~ **alvo inexistente desde 2026-08-10** — o V2 foi removido por ter 0 imports; o REPOINT não tem pra onde ir, e os `it()` vivos daquele teste guardam só o CSS) · **KEEP+FIX ~20** (bug do teste, ex `bulkExport BOM UTF-8` byte-vs-literal) · **QUARANTINE ≈0**.

**Pré-requisito DURO:** rodar o nightly com fix C1 (PR #2632) ISOLADO e re-medir — o nº de falhas de **comportamento** é o único baseline honesto. **Contradição a resolver antes:** plano dizia "4 arquivos intocados" (Totals/IndexDateField/StatusProducao/SaleSheet) mas estão TODOS vermelhos (~27 it()).

Ref: triage `memory/sessions/2026-06-13-sdd-f2b-triage-q2.md` · revisão adversarial 2026-06-13 · US-GOV-017 fase 2b.

---

**Última atualização:** 2026-06-13 — US-SELL-045/046/047/048 adicionadas (revisão adversarial da triage Q2 Fase 2b SDD: 2 bugs de contrato órfão de backend [`totals` morto, `grade-avancada`], 1 US de teste de isolamento real, 1 US de higiene dos snapshots-grep — quarentena Q-A revertida). 2026-05-31 — US-SELL-041/042/043 adicionadas (benchmark `tela-venda-arte` 2026-05-31, gaps P1 — G5 NFC-e inline no Create / G4 batch price-group / G6 CSS Cowork→tokens no Index). 2026-05-15 — US-SELL-036 adicionada (goal #3 CYCLE-06 FSM rollout). 2026-05-12 — **discovery + spec executable Pipeline Vendas (7 GAPs)**. Wagner valida casos de uso + testes failing-first **antes** de implementar (estratégia: pagar custo agora com poucos clientes ativos vs. retrabalho exponencial com mais clientes). Antes era heatmap v3 → agora pipeline canon completo Orçamento→Produção→Venda→Faturamento. Total SPEC: **5 P0 + 5 P1 + 3 P2 + 1 P3 (US-015..028) + 4 P0 + 2 P1 + 1 P2 (US-029..035) + 1 P0 (US-036) = 22 US ativas**. Cumpre [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) (sinal qualificado pelo próprio Wagner — pain points reportados em sessão).

### US-SELL-051 · Migrar dados históricos transaction_date (timezone/format) — afeta ROTA LIVRE

**Implementado em:** _pendente_ — migration backfill timezone/format de transaction_date (ADR 0066) nao rodou

> owner: — · priority: p0 · estimate: 4h · status: todo · type: story
> blocked_by: —
> parent_plan: timezone-format-date-migracao

**Iniciativa-plano perdida** recuperada pro backlog (triagem 2026-06-20 · run wf_1bfbefba).
labels: `plano-perdido`, `backlog-2026-06-20`

**Sinal (ADR 0105):** bug histórico preservado em ADR 0066 — migration de timezone/format de `transaction_date` nunca rodou; afeta cliente real ROTA LIVRE (biz=4).

**DoD:**
- Migration idempotente de backfill timezone/format.
- Validar exibição pós-migração.
- ⚠️ Confirmar módulo correto (Sells vs Financeiro) antes de codar — `transaction_date` é coluna core UltimatePOS.

**Fonte:** memory/requisitos/_processo/BATCH-BACKLOG-34-2026-06-20.md (§Aprovação [W] 2026-06-20)

### US-SELL-052 · Fechar paridade Sells V2 vs Blade (configure-search · quick-add · preço-diferenciado)

**Implementado em:** _pendente_ — paridade Sells V2 vs Blade (configure-search/quick-add/preco-diferenciado) nao fechada; overlap com epic US-001..006

> owner: — · priority: p1 · estimate: 8h · status: todo · type: story
> blocked_by: —
> parent_plan: sells-v2-paridade-blade-biz4

**Iniciativa-plano perdida** recuperada pro backlog (triagem 2026-06-20 · run wf_1bfbefba).
labels: `plano-perdido`, `backlog-2026-06-20`

**Sinal (ADR 0105):** Larissa biz=4; guard biz=4 já removido. Restam 3 features do Blade ausentes em V2: configure-search, quick-add, preço-diferenciado.
**⚠️ Dedup parcial:** possível overlap com o epic MWART US-SELL-001..006 (migração /sells/create) — checar antes de abrir trabalho redundante.

**DoD:**
- configure-search em paridade.
- quick-add em paridade.
- preço-diferenciado em paridade.
- Testes Pest.

**Fonte:** memory/requisitos/_processo/BATCH-BACKLOG-34-2026-06-20.md (§Aprovação [W] 2026-06-20)

### US-SELL-054 · Offline-first no PDV — fila IndexedDB + reemissão ao reconectar

> owner: — · priority: p1 · estimate: 40h · status: doing · type: story
> blocked_by: —
> parent_plan: programa-ondas-onda-1-sells

**Gap G-02 da CAPTERRA-FICHA Sells (Onda 1.1 · programa-ondas) — fecha C05 (nota 2/10).**

Persistir a venda em fila local (IndexedDB) quando a rede cai e sincronizar/emitir ao reconectar. Modo de falha real da persona Larissa @ ROTA LIVRE (biz=4): loja em Termas do Gravatal/SC com internet instável — hoje o PDV é 100% online (igual Bling), ela trava no meio da venda. O autosave localStorage protege contra crash de aba, NÃO contra queda de rede na finalização.

- Referência SOTA: Omie/Hiper/Nex/Square (offline default, sync depois; Square ligou offline-card por padrão abr/26).
- **ADR 0105 (cliente como sinal):** MEDIR a frequência de queda em biz=4 ANTES de executar as 40h — hipótese sem sinal vira feature-wish.
- Tier 0: fila com `business_id` scopado; ao reemitir, respeitar regra-mestre de valor (arredondar 2 casas, num_uf 3-dígitos-milhar) + smoke biz=1 (ADR 0101).
- Ref: memory/requisitos/Sells/CAPTERRA-FICHA.md §6 G-02 + §8.3

**Implementado em:** _pendente_ — em andamento (servidor feito em `VendaOffline` e `SellPosController::store`): o servidor reconhece a chave `offline_uuid` e grava a venda uma vez só, atrás de `SELLS_OFFLINE_ENABLED` (desligada); UC-VOFF-01..04 em `resources/js/Pages/Sells/Create.casos.md`. Falta a fila no navegador e o aviso de "sem internet" no caixa.

**Testado em:** `tests/Feature/Sells/VendaOfflineContratoTest.php`

**Aceite:**
- [x] O mesmo reenvio grava uma venda e baixa o estoque uma vez (UC-VOFF-01).
- [x] Chave de uma empresa não acha venda de outra (UC-VOFF-02); flag desligada não muda nada (UC-VOFF-03).
- [ ] O caixa guarda a venda no navegador quando a rede cai e reenvia ao voltar.

### US-SELL-055 · Pix QR no PDV + webhook auto-reconcile ligado à venda/caixa

> owner: — · priority: p1 · estimate: 20h · status: todo · type: story
> blocked_by: —
> parent_plan: programa-ondas-onda-1-sells

**Gap G-04 da CAPTERRA-FICHA Sells (Onda 1.1 · programa-ondas) — fecha C04 (nota 5/10).**

Gerar QR Pix no fechamento da venda e reconciliar automaticamente via webhook (~15s) ligando o pagamento à venda + Caixa do dia. Elimina o "chegou o Pix?" manual da balconista.

- Referência SOTA: Omie.Cash / Gálago (QR + webhook auto-reconcile ~10-15s).
- Split payment já existe (US-SELL-006); falta o trilho Pix-webhook auto-reconcile e TEF auto-fill NFC-e.
- Tier 0: webhook com filtro multi-tenant `business_id`; idempotência de reconciliação; PII redacted em log; valor via regra-mestre. Provável reuso de PaymentGateway/RecurringBilling (drivers Inter/Asaas/PixAuto) — checar antes de criar (não duplicar).
- Ref: memory/requisitos/Sells/CAPTERRA-FICHA.md §6 G-04

**Implementado em:** _pendente_ — em andamento: a cobrança da venda já leva o saldo em aberto e não duplica (`SellController::emitirCobranca`, UC-VCOB-01..04 em `Sells/Show.casos.md`) e o retorno do banco lança o pagamento atrás da flag `PAYMENTGATEWAY_LANCAR_PAGAMENTO_NA_VENDA` (desligada). Falta o Pix direto do caixa e ligar a flag.

### US-SELL-056 · Keyboard-first coeso no Create — hotkeys configuráveis + Enter-avança + F-key pagar

> owner: — · priority: p2 · estimate: 8h · status: todo · type: story
> blocked_by: —
> parent_plan: programa-ondas-onda-1-sells

**Gap G-05 da CAPTERRA-FICHA Sells (Onda 1.1 · programa-ondas) — fecha C07 (nota 5/10).**

Fluxo mouse-free coeso pra 50 vendas/dia (muscle memory): hotkeys configuráveis, Enter-avança-campo/estágio, F-key dedicada pra pagar. Hoje há só `/` (foca busca), Esc e Ctrl/Cmd+Enter (submit).

- Referência SOTA: Bling (Alt+key config), Conta Azul (checkout mouse-free), Linx (F2/L function-keys).
- **ADR 0105:** Larissa NÃO é power-user — MEDIR uso/pedido antes de investir muito. Esforço S (~8h).
- Cuidar de não colidir com atalhos globais do AppShell; manter guard de input (não disparar dentro de campo de texto).
- Ref: memory/requisitos/Sells/CAPTERRA-FICHA.md §6 G-05

### US-SELL-057 · Skeleton de carregamento no Create + INP < 200ms

> owner: — · priority: p2 · estimate: 3h · status: todo · type: story
> blocked_by: —
> parent_plan: programa-ondas-onda-1-sells

**Gap G-06 da CAPTERRA-FICHA Sells (Onda 1.1 · programa-ondas) — fecha C13 (nota 5/10).**

Perceived performance no `Sells/Create`: skeleton inicial enquanto carrega + alvo INP < 200ms a cada venda. `Inertia::defer` já existe na Index; o Create não tem skeleton inicial.

- Referência SOTA: Shopify (Spring'26 −1min em carts grandes, silencioso).
- **ADR 0105:** MEDIR Web Vitals reais em biz=4 antes (é percepção, não bug). Esforço S (~3h).
- NÃO confundir com US-SELL-003 (skeleton do AppShellV2/props contract) — este é perceived-perf de carregamento de dados.
- Ref: memory/requisitos/Sells/CAPTERRA-FICHA.md §6 G-06

### US-SELL-058 · Redesenho do cadastro de venda em tela PARALELA (`/sells/create-v3`) — sem tocar na tela que a ROTA LIVRE opera

**Implementado em:** _parcial_ · `resources/js/Pages/Sells/CreateV3.tsx` · `app/Http/Controllers/SellsV3Controller.php` · `routes/web.php` · verificado@70ebd92 (2026-08-07) — o **scaffold** do preview shipou: rota GET /sells/create-v3, tela e dados de cena (sem store(), sem POST, sem cálculo de valor). A US **não** está fechada — falta o smoke real e a decisão [W] sobre ligar dados reais, que é onde entra o território `[V0]`

**Testado em:** `tests/Feature/Sells/SellsCreateV3ContratoTest.php` (covers US-SELL-058) — UC-V301 rota registrada · UC-V302 nenhuma rota de escrita · UC-V303 fronteira (o V3 não encosta em Create.tsx/SellPosController). Na allowlist da lane `Pest (Sells · MySQL)` e **verde** no run 31203571574 sobre `c0214c6` (`40 passed · 133 assertions`; este arquivo `passed: 3 · failed: 0`), com veredito dos 3 UCs no manifesto do G-7

> owner: luiz · priority: p2 · estimate: 6h · type: story
> blocked_by: —

<!-- Sem `status:` de propósito, como a US-SELL-001 (mesmo caso: epic parcialmente
     landado). ADR 0302: `status:` é legado derivado/aposentado — a fonte única de
     done-ness é `**Implementado em:**`, que aqui diz `_parcial_` e explica o que falta.
     Escrever `status: done` com smoke pendente seria mentira; `status: doing` com
     âncora viva é o conflito que o doneness-lint morde, com razão. -->

**Origem:** restrição de negócio declarada por **[L] Luiz** em 2026-08-06, textual: *"Tela do Guilherme e da Larissa não pode ser alterada de forma alguma, se não eles quebram contrato e perdemos dinheiro."*

**Problema:** o cadastro de venda precisa de redesenho, mas a tela viva (`Sells/Create.tsx` em `/pos/create`) é operada pela **ROTA LIVRE** (`biz=4` — Larissa dona, Guilherme retaguarda), 99% do volume. E a feature flag **não protege**: `FeatureFlagService::$fallbackDefaults['useV2SellsCreate'] = true` desde 2026-05-27 — a V2 React está ligada por padrão pra todos, então editar `Create.tsx` e deployar atinge o cliente direto. Daí **arquivo novo + rota nova**, não branch de flag.

**Aceite:**
- [x] Rota `GET /sells/create-v3` + `SellsV3Controller` novos, dentro do mesmo grupo de middleware da tela viva.
- [x] Mesma alçada de permissão da venda real (`sell.create` ou `superadmin`) — preview não afrouxa gate.
- [x] Fronteira verificável no diff: `Sells/Create.tsx`, `SellPosController.php` e componentes compartilhados **não aparecem**.
- [x] Sem `store()`, sem POST, sem migration; botão "Finalizar venda" renderizado `disabled`.
- [x] Faixa permanente no topo avisando que não é produção.
- [ ] Smoke real pós-deploy: `/sells/create-v3` responde + `/pos/create` e `/sells` inalteradas (regression adjacent) + screenshot 1440/1280.
- [ ] Decisão [W] sobre ligar dados reais (hoje são dados de cena) — é aí que entra território `[V0]` da REGRA MESTRE de valor/estoque.

**Contrato de não-regressão (o que esta US promete NÃO fazer):** enquanto `status != done`, nenhum PR desta US pode tocar `resources/js/Pages/Sells/Create.tsx`, `app/Http/Controllers/SellPosController.php` nem componentes que a tela viva consome. Quebrar isso é quebrar o motivo de a tela existir.

- Charter: [`CreateV3.charter.md`](../../../resources/js/Pages/Sells/CreateV3.charter.md) · casos: [`CreateV3.casos.md`](../../../resources/js/Pages/Sells/CreateV3.casos.md) · RUNBOOK: [RUNBOOK-create-v3.md](RUNBOOK-create-v3.md)
- Âncora de design: `prototipo-ui/cowork/Wagner/venda-v3.jsx` (seção `sells-create.jsx`) _(repontado 2026-10-07 por decisão [W] para a cópia do [W]: ela recebeu o conteúdo da do Felipe — busca de cliente sem acento e por dígito, CST, parcelas — e mantém a placa do total escura no tema escuro; a referência anterior era `prototipo-ui/cowork/Felipe/venda-v3.jsx`)_ _(repontado 2026-09-21 — o espelho do Felipe passou a ser o pacote "Produto Unificado V2", onde a Venda V3 veio num arquivo só: `venda-v3.jsx`, seção `sells-create.jsx`, exporta `window.VendaV3Create`. A âncora anterior `venda-v3/sells-create.jsx` segue no histórico em [bdf6d07e3](https://github.com/wagnerra23/oimpresso.com/blob/bdf6d07e3/prototipo-ui/cowork/Felipe/venda-v3/sells-create.jsx); dono: `resources/js/Pages/Sells/CreateV3.charter.md`)_ _(histórico: repontado 2026-09-15 — a âncora anterior `prototipo-ui/design-oimpresso/04-modulos/vendas/sells-create.jsx` nunca versionado no repo; dono: `resources/js/Pages/Sells/CreateV3.charter.md`)_
- Landado em [#5356](https://github.com/wagnerra23/oimpresso.com/pull/5356) — [L] não mergeia sozinho ([F] ou [W] aprova).

---

### US-SELL-059 · Editar venda existente (`/sells/{id}/edit`) — guards de prazo, devolução e tenant

**Implementado em:** `resources/js/Pages/Sells/Edit.tsx` · `app/Http/Controllers/SellController.php` (`edit()`) · `resources/js/Pages/Sells/Edit.charter.md` · verificado@17f1c0c052 (2026-08-31) — a **capacidade** já estava viva em produção (`route-hits: 14hit@2026-08-22`); o que faltava era o **contrato declarado**. Esta US é retroativa: registra o que existe e passa a ancorá-lo, em vez de propor trabalho novo

**Testado em:** `tests/Feature/Sells/SellsEditContratoTest.php` (covers US-SELL-059) — UC-SEDIT-01 tenant · 02 permissão · 03 devolução · 04 prazo · 05 `defer` · 06 GET é leitura pura · 07 pré-fill `[V0]`. Na allowlist da lane `Pest (Sells · MySQL)` e **verde** no job 99463057234 (`47 passed · 228 assertions`; era `43 · 208` antes deste arquivo), com os 7 UCs nominalmente ✓

> owner: wagner · priority: p1 · estimate: 0h · type: story
> blocked_by: —

<!-- Sem `status:` de propósito (ADR 0302: a fonte única de done-ness é
     `**Implementado em:**`). A capacidade está completa e provada por teste
     verde; o resíduo declarado abaixo é de COBERTURA (e2e/axe), não de
     funcionalidade — por isso a âncora não leva `_parcial_`. -->

**Origem:** lacuna encontrada em 2026-08-31 pelo `charter-us-lint`, ao levar a tela à cobertura sustentável. A busca nas 52 US então existentes do SPEC e nas âncoras `**Implementado em:**` não achou **nenhuma** que cobrisse EDIÇÃO de venda — as `US-SELL-001..009` são todas sobre `/sells/create`. A tela existia, era usada em produção e não tinha US.

**Problema:** `Sells/Edit` move **VALOR e ESTOQUE** (REGRA MESTRE de `memory/proibicoes.md`) e é a segunda tela mais sensível do módulo depois do cadastro. Ainda assim parecia coberta e não estava: os 5 arquivos de teste com o nome dela — `Wave1EditBaselineTest`, `Wave1EditInertiaTest`, `SellsEditCoworkTest`, `SellsEditParkingLotP1P2P3Test`, `SellsEditPrefillContractTest` — tinham, medido em 2026-08-31, **zero invocadores** (`git grep <basename> -- .github/ phpunit.xml package.json .github/ci-sqlite-pest.list`, com controle positivo). Existiam no repo e não rodavam em lane nenhuma.

**Aceite:**
- [x] Venda de outro business não abre para edição — `firstOrFail` escopado devolve 404 (Tier 0, ADR 0093).
- [x] Sem `direct_sell.update` nem `so.update`, o editor é negado.
- [x] Venda com devolução associada não é editável (422).
- [x] Passado o `transaction_edit_days`, a venda trava (422); dentro do prazo, abre.
- [x] O formulário pesado não viaja na primeira resposta — `Inertia::defer()` + `<Deferred data="form">`.
- [x] Abrir o editor não altera a venda — GET é leitura pura (FSM safety, ADR 0143).
- [x] O pré-fill traz os valores do banco sem fallback nem arredondamento `[V0]`.
- [ ] Cobertura e2e/axe da tela renderizada — hoje `Sells/Edit` não tem teste Browser; os UCs acima provam contrato de resposta, não visibilidade.
- [ ] Decidir o destino dos 5 arquivos de teste órfãos acima: pôr na allowlist ou aposentar. O precedente do `Wave1ShowInertiaTest` (vermelho por 2 meses sem ninguém ver) diz que podem nascer vermelhos, então é intent próprio.

**Achados abertos (reportados, não consertados neste escopo):**
- Os guards `canBeEdited`/`isReturnExist` rodam **antes** do `findOrFail` escopado (`SellController@edit:2643-2668` vs `:2673`): venda de outro business **fora do prazo** devolve 422 "prazo expirou" em vez de 404, confirmando ao chamador que o id existe e é antigo. Não é vazamento de dado, é vazamento de **existência** cross-tenant.
- `TransactionUtil::isReturnExist:4259` não escopa por business (`App\Transaction` não tem global scope) — defesa em profundidade, sem vazamento provado.
- O charter promete "Header + stage FSM", mas o `headline` entrega `current_stage_key => null` fixo (`:2955`). Pela regra de precedência, corrigir o perdedor é ato de quem fechar o item.

- Charter: [`Edit.charter.md`](../../../resources/js/Pages/Sells/Edit.charter.md) · casos: [`Edit.casos.md`](../../../resources/js/Pages/Sells/Edit.casos.md) · RUNBOOK: [RUNBOOK-edit.md](RUNBOOK-edit.md)
- Landado em [#6455](https://github.com/wagnerra23/oimpresso.com/pull/6455).

---

### US-SELL-060 · Listar rascunhos de venda (`/sells/drafts`)

**Implementado em:** `resources/js/Pages/Sells/Drafts.tsx` · `app/Http/Controllers/SellController.php` (`getDrafts()`) · `resources/js/Pages/Sells/Drafts.charter.md` · `memory/requisitos/Sells/RUNBOOK-drafts.md` · verificado@9101f86af5 (2026-09-08) — a capacidade já está viva em produção (rota viva em routes/web.php linha 716). Esta US é **retroativa**: registra o que existe e passa a ancorá-lo, no mesmo espírito da US-SELL-059.

**Testado em:** `tests/Feature/Sells/SellsDraftsCoworkTest.php` — o arquivo existe, mas medido em 2026-09-08 tem **zero invocadores** (`git grep SellsDraftsCoworkTest -- .github/ phpunit.xml package.json` — a sonda foi validada com controle positivo num teste que SIM tem invocador). **Não roda em lane nenhuma** — esta linha registra existência, não cobertura.

> owner: wagner · priority: p2 · estimate: 0h · type: story
> blocked_by: —

<!-- Sem `status:` de propósito (ADR 0302: a fonte única de done-ness é
     `**Implementado em:**`). A capacidade está viva; o resíduo declarado no
     Aceite é de COBERTURA (teste que não roda), não de funcionalidade. -->

**Origem:** lacuna acusada pelo `charter-us-lint` em 2026-09-08, ao vincular os inventários de paridade da Onda 7 ([#6972](https://github.com/wagnerra23/oimpresso.com/pull/6972)). Tocar o charter acordou a dívida dele: `Drafts.charter.md` não declarava `related_us`, e a busca nas 53 US então existentes — por path e por termo de domínio — não achou nenhuma que cobrisse rascunhos. As `US-SELL-001..009` são todas sobre `/sells/create`. A tela existia, tinha charter, RUNBOOK e inventário de paridade, e não tinha US.

**Problema:** rascunho é a venda que a Larissa deixa pela metade quando o telefone toca — foi a US-SELL-007 que criou o auto-save que os produz. A tela que os recupera não tinha contrato declarado, então nenhum gate sabia o que ela deve garantir.

**Aceite:**
- [x] Sem `draft.view_all` nem `draft.view_own`, a tela é negada com 403 (`SellController@getDrafts`).
- [x] A listagem é escopada por `business_id` da sessão (Tier 0, ADR 0093).
- [x] Só entram vendas com `status = draft`; cotação é tela própria (US-SELL-061).
- [x] "Continuar" leva ao editor (`/sells/{id}/edit`), que aplica os próprios guards (US-SELL-059).
- [ ] Pôr `SellsDraftsCoworkTest` numa lane ou aposentá-lo. O precedente da US-SELL-059 mostra que teste órfão fica vermelho por meses sem ninguém ver — decidir é intent próprio.
- [ ] `Drafts.casos.md` não existe: a tela não tem UC citado por teste (G-2 do `casos-gate`).

- Charter: [`Drafts.charter.md`](../../../resources/js/Pages/Sells/Drafts.charter.md) · RUNBOOK: [RUNBOOK-drafts.md](RUNBOOK-drafts.md) · paridade: [drafts-visual-comparison.md](drafts-visual-comparison.md)

---

### US-SELL-061 · Listar cotações (`/sells/quotations`)

**Implementado em:** `resources/js/Pages/Sells/Quotations.tsx` · `app/Http/Controllers/SellController.php` (`getQuotations()`) · `resources/js/Pages/Sells/Quotations.charter.md` · `memory/requisitos/Sells/RUNBOOK-quotations.md` · verificado@9101f86af5 (2026-09-08) — capacidade viva (rota viva em routes/web.php linha 719). US **retroativa**, mesmo motivo da US-SELL-060.

**Testado em:** `tests/Feature/Sells/SellsQuotationsCoworkTest.php` — existe, **zero invocadores** medidos em 2026-09-08 (mesma sonda e mesmo controle positivo da US-SELL-060). Não roda em lane nenhuma.

> owner: wagner · priority: p2 · estimate: 0h · type: story
> blocked_by: —

<!-- Sem `status:` de propósito (ADR 0302). Resíduo é de cobertura, não de funcionalidade. -->

**Origem:** a mesma lacuna do `charter-us-lint` (2026-09-08, Onda 7). `Quotations.charter.md` não declarava `related_us` e nenhuma US do SPEC cobria cotação.

**Problema:** cotação é proposta formal enviada ao cliente — o passo comercial que antecede a venda. Ela compartilha a tabela `transactions` com o rascunho e se distingue **só** por `sub_status = quotation`; sem contrato declarado, essa distinção vivia apenas no código e no charter.

**Aceite:**
- [x] Sem `quotation.view_all` nem `quotation.view_own`, a tela é negada com 403 (`SellController@getQuotations`).
- [x] A listagem é escopada por `business_id` da sessão (Tier 0, ADR 0093).
- [x] O recorte é `status = draft` **mais** `sub_status = quotation` — é o que separa esta tela da US-SELL-060, e a separação é do backend, não da UI.
- [ ] Pôr `SellsQuotationsCoworkTest` numa lane ou aposentá-lo (intent próprio).
- [ ] `Quotations.casos.md` não existe: sem UC citado por teste (G-2).

- Charter: [`Quotations.charter.md`](../../../resources/js/Pages/Sells/Quotations.charter.md) · RUNBOOK: [RUNBOOK-quotations.md](RUNBOOK-quotations.md) · paridade: [quotations-visual-comparison.md](quotations-visual-comparison.md)

---

### US-SELL-062 · Listar vendas recorrentes e pausar/retomar (`/sells/subscriptions`)

**Implementado em:** `resources/js/Pages/Sells/Subscriptions.tsx` · `app/Http/Controllers/SellPosController.php` (`listSubscriptions()`) · `resources/js/Pages/Sells/Subscriptions.charter.md` · `memory/requisitos/Sells/RUNBOOK-subscriptions.md` · verificado@9101f86af5 (2026-09-08) — capacidade viva (rota viva em routes/web.php linha 714). US **retroativa**.

**Testado em:** `tests/Feature/Sells/SellsSubscriptionsCoworkTest.php` — existe, **zero invocadores** medidos em 2026-09-08. Não roda em lane nenhuma.

> owner: wagner · priority: p2 · estimate: 0h · type: story
> blocked_by: —

<!-- Sem `status:` de propósito (ADR 0302). Resíduo é de cobertura, não de funcionalidade. -->

**Origem:** a mesma lacuna do `charter-us-lint` (2026-09-08, Onda 7).

**Problema:** esta é a única das quatro que **escreve**: o toggle por linha pausa e retoma uma cobrança recorrente (`POST /sells/recurring-toggle/{id}`). Pausar uma assinatura é ato de faturamento — deixar de cobrar um cliente — e mesmo assim a tela não tinha US. O charter já declarava a permissão separada para escrita; agora o SPEC também.

**Aceite:**
- [x] Sem `sell.view` nem `direct_sell.access`, a tela é negada com 403 (`SellPosController@listSubscriptions`).
- [x] A listagem é escopada por `transactions.business_id` (Tier 0, ADR 0093).
- [x] O recorte é `status = final` **mais** `is_recurring = 1`.
- [x] O toggle de pausar/retomar exige `sell.update` — permissão de **escrita**, distinta da de leitura da tela.
- [ ] Pôr `SellsSubscriptionsCoworkTest` numa lane ou aposentá-lo (intent próprio).
- [ ] `Subscriptions.casos.md` não existe: sem UC citado por teste (G-2). Vale mais aqui do que nas irmãs, porque esta tela escreve.

- Charter: [`Subscriptions.charter.md`](../../../resources/js/Pages/Sells/Subscriptions.charter.md) · RUNBOOK: [RUNBOOK-subscriptions.md](RUNBOOK-subscriptions.md) · paridade: [subscriptions-visual-comparison.md](subscriptions-visual-comparison.md)

---

### US-SELL-063 · Caixa do dia por forma de pagamento e por origem (`/vendas/caixa`)

**Implementado em:** `resources/js/Pages/Sells/Caixa/Index.tsx` · `app/Http/Controllers/SellController.php` (`inertiaCaixa()`) · `resources/js/Pages/Sells/Caixa/Index.charter.md` · verificado@9101f86af5 (2026-09-08) — capacidade viva (rota viva em routes/web.php linha 727, nome de rota `vendas.caixa`). US **retroativa**.

**Testado em:** `tests/Feature/Sells/SellsCaixaPageTest.php` — existe, **zero invocadores** medidos em 2026-09-08. Não roda em lane nenhuma. Há ainda um teste de escopo Tier 0 do cash register (fora do escopo desta US, e por isso não citado como teste-que-cobre).

> owner: wagner · priority: p2 · estimate: 0h · type: story
> blocked_by: —

<!-- Sem `status:` de propósito (ADR 0302). Resíduo é de cobertura, não de funcionalidade. -->

**Origem:** a mesma lacuna do `charter-us-lint` (2026-09-08, Onda 7). Diferente das irmãs, esta tela **não tem RUNBOOK** — o inventário de paridade `Caixa-r1-visual-comparison.md` é o artefato mais próximo de um.

**Problema:** o caixa do dia é onde a diferença entre o esperado e o conferido aparece — é conferência de dinheiro. A tela coexiste com o fluxo legacy `/cash-register/*`, que continua dono do fechamento real, e essa fronteira (quem lê × quem fecha) não estava declarada em US nenhuma.

**Aceite:**
- [x] Sem `direct_sell.view`, `view_own_sell_only` nem `view_commission_agent_sell`, a tela é negada com 403 (`SellController@inertiaCaixa`).
- [x] Todos os agregados são escopados por `business_id` da sessão (Tier 0, ADR 0093).
- [x] A tela **lê** e resume; o fechamento real permanece no legacy `/cash-register/close-register/{id}` — a tela navega para lá, não substitui o ato.
- [ ] Pôr `SellsCaixaPageTest` numa lane ou aposentá-lo (intent próprio).
- [ ] `Caixa/Index.casos.md` não existe: sem UC citado por teste (G-2).
- [ ] A tela não tem RUNBOOK, ao contrário das irmãs `Drafts`/`Quotations`/`Subscriptions`.

- Charter: [`Caixa/Index.charter.md`](../../../resources/js/Pages/Sells/Caixa/Index.charter.md) · paridade: [Caixa-r1-visual-comparison.md](Caixa-r1-visual-comparison.md)

---

### US-SELL-064 · Listar vendas de POS com rodapé de totais (`/pos`)

**Implementado em:** `resources/js/Pages/Sells/Pos/Index.tsx` · `app/Http/Controllers/SellPosController.php` · `app/Http/Controllers/SellController.php` · `resources/js/Pages/Sells/Pos/Index.charter.md` · `memory/requisitos/Sells/RUNBOOK-pos.md` · verificado@f40a995 (2026-10-01) — `SellPosController@index` responde a Page para o cliente Inertia; o GET comum segue no Blade até o cutover F5. Dados de `inertiaList` com `is_direct_sale=0`.

**Testado em:** `tests/Feature/Sells/SellsPosIndexContratoTest.php` — lane `sells-pest.yml` (MySQL, tenant 98 × 99). Nasce sem run.

> owner: wagner · priority: p2 · estimate: 0h · type: story
> blocked_by: —

**Origem:** thread 01 do playbook `prototipo-ui/cowork/Wagner/cowork-inbox/venda-menu/` (2026-10-01). A Lista de POS era a única tela do menu Vendas sem US.

**Problema:** a lista de vendas de balcão (`is_direct_sale = 0`) é o que se confere no fim do turno, e no Blade o rodapé era somado pelo DataTable no cliente, só sobre a página visível.

**Aceite:**
- [x] Sem `sell.view` nem `sell.create`, a tela e a lista são negadas com 403.
- [x] Só vendas de POS do `business_id` da sessão entram (Tier 0, ADR 0093); venda direta fica na lista geral.
- [x] O rodapé (total, pago, em aberto) vem do servidor e cobre o filtro inteiro.
- [x] Filtro "Vencido" e período por `transaction_date`.
- [ ] Contagem por status de pagamento e por forma no rodapé.
- [ ] Filtros de local, cliente, vendedor e tipo de serviço.

- Charter: [`Pos/Index.charter.md`](../../../resources/js/Pages/Sells/Pos/Index.charter.md) · RUNBOOK: [RUNBOOK-pos.md](RUNBOOK-pos.md) · casos: [`Pos/Index.casos.md`](../../../resources/js/Pages/Sells/Pos/Index.casos.md)

---

### US-SELL-065 · Cadastro de descontos com ver × editar (`/discount`)

**Implementado em:** `resources/js/Pages/Discount/Index.tsx` · `app/Http/Controllers/DiscountController.php` (`index()`, `store()`, `update()`) · `app/Http/Requests/SalvarDescontoRequest.php` · `database/migrations/2026_10_02_120000_add_discount_view_manage_permissions.php`

**Testado em:** `tests/Feature/Sells/DescontosContratoTest.php` (UC-DSC-01..08, lane `sells-pest.yml`).

> owner: wagner · priority: p2 · estimate: 0h · type: story
> blocked_by: —

**Origem:** thread 04 do playbook `venda-menu` + decisão D1 de [W] (2026-10-02): ver a lista e criar/editar/excluir viram permissões separadas.

**Problema:** a tela tinha uma permissão só, `discount.access` — quem conferia preço também apagava desconto. Desconto é valor que o PDV aplica sozinho.

**Aceite:**
- [x] `discount.view` vê a lista; `discount.manage` cria, edita, desativa, reativa e exclui; sem `manage` os botões aparecem desabilitados com o motivo.
- [x] Quem tinha `discount.access` recebe as duas no deploy (papel e usuário direto).
- [x] A gravação grava a mesma linha de antes (FormRequest sem regras novas; números crus, datas pelo formato do negócio).
- [x] Toda leitura e escrita escopada por `business_id` (Tier 0, ADR 0093).
- [ ] Validação no servidor (achado A2) — decisão [W].

- Charter: [`Discount/Index.charter.md`](../../../resources/js/Pages/Discount/Index.charter.md) · runbook: [RUNBOOK-discount.md](RUNBOOK-discount.md)

### US-SELL-066 · Devolução de venda em React — lista e registro (`/sell-return`)

**Implementado em:** `resources/js/Pages/SellReturn/Index.tsx` · `resources/js/Pages/SellReturn/Add.tsx` · `app/Http/Controllers/SellReturnController.php` (`index()`, `add()`)

**Testado em:** `tests/Feature/Sells/SellReturnIndexContratoTest.php` · `tests/Feature/Sells/SellReturnAddContratoTest.php` (UC-SRADD-01..06, lane `sells-pest.yml`).

> owner: wagner · priority: p2 · estimate: 0h · type: story
> blocked_by: —

**Origem:** thread 03 do playbook `venda-menu` (PR 1 #8487 lista · PR 2 registro).

**Problema:** a devolução era só Blade; a lista e o registro não tinham tela no padrão do app.

**Aceite:**
- [x] Lista de devoluções em React, com o Blade como fallback no GET comum.
- [x] Registro de devolução em React gravando pelo mesmo `POST /sell-return` do Blade, sem mudar o cálculo (mesmo total, mesmo estoque — dupla prova no UC-SRADD-05).
- [x] Quantidade devolvida nunca maior que a vendida (teto na tela).
- [x] Venda de outra empresa = 404; sem permissão = 403 (Tier 0, ADR 0093).
- [ ] Motivo da devolução (R3) e aviso de item sob medida (R4) — exigem mudar o `store()` ou dado que o produto não guarda.

- Charters: [`SellReturn/Index.charter.md`](../../../resources/js/Pages/SellReturn/Index.charter.md) · [`SellReturn/Add.charter.md`](../../../resources/js/Pages/SellReturn/Add.charter.md) · runbook: [RUNBOOK-sell-return-add.md](RUNBOOK-sell-return-add.md)

### US-SELL-067 · Orçamento guarda o custo do dia em cada linha (UC-FLX-33)

**Implementado em:** `Modules/Compras/Observers/TransactionSellLineCustoObserver.php` · `database/migrations/2026_10_19_200000_add_custo_previsto_to_transaction_sell_lines.php`

**Testado em:** `tests/Feature/Sells/OrcamentoCustoPrevistoContratoTest.php` (lane `sells-pest.yml`).

> owner: wagner · priority: p1 · estimate: 3h · type: story
> blocked_by: US-COM-024

**Origem:** casos de custo da tela de fluxos (UC-FLX-33). O que se negocia na venda não é o que se emprega na produção; o orçamento precisa guardar o custo do dia em que foi feito.

**Problema:** a linha de venda não guarda custo. O custo de hoje está em `variations.dpp_inc_tax`, sobrescrito a cada compra, então um orçamento antigo não sabe com que custo foi calculado.

**Aceite:**
- [x] Linha de venda em rascunho (orçamento) grava `custo_previsto`, a data e o método, pelo custo do insumo na data do orçamento (US-COM-024).
- [x] Compra posterior e edição da linha não mudam o custo guardado; trocar o produto da linha recalcula.
- [x] Converter o orçamento em venda mantém o custo do dia.
- [x] Venda finalizada não grava custo previsto (o custo real vem do lote, `mapPurchaseSell`).
- [x] Falha no cálculo não impede gravar a linha.
- [ ] Mostrar o custo guardado e a margem na tela do orçamento (UC-FLX-34).

### US-SELL-068 · Orçamento aberto avisa que o custo mudou (UC-FLX-34)

**Implementado em:** `app/Http/Controllers/SellController.php` (`custoDoOrcamento()`) · `resources/js/Pages/Sells/Show.tsx` · `app/Contracts/Compras/CustoDoInsumo.php`

**Testado em:** `tests/Feature/Sells/SellsShowCustoOrcamentoContratoTest.php` (UC-VSHOW-08, lane `sells-pest.yml`).

> owner: wagner · priority: p1 · estimate: 3h · type: story
> blocked_by: US-SELL-067

**Problema:** o orçamento guarda o custo do dia (US-SELL-067), mas ninguém vê quando o custo de hoje já se afastou dele.

**Aceite:**
- [x] Detalhe do orçamento aberto mostra custo guardado × custo de hoje, por linha e no total, e a margem com cada um.
- [x] Aviso quando algum item se afastou mais de 5% do custo guardado.
- [x] Só para quem tem `view_purchase_price`; sem ela o custo não vai no JSON.
- [x] O núcleo lê o custo pela interface `App\Contracts\Compras\CustoDoInsumo`, ligada pelo módulo Compras.
- [ ] Recalcular o orçamento com o custo de hoje, a pedido (fora desta entrega).

### US-SELL-069 · Orçamento abaixo da margem mínima só vira venda com aprovação (UC-FLX-35)

**Implementado em:** `app/Observers/TransactionMargemMinimaObserver.php` · `app/Exceptions/MargemAbaixoDoMinimo.php` · `app/Domain/Fsm/Modelos/RegrasDaEmpresa.php` (`margemMinima()`) · `resources/js/Pages/Fluxos/_components/AbaEmpresa.tsx`

**Testado em:** `tests/Feature/Sells/SellsMargemMinimaContratoTest.php` (UC-DRAFT-05, lane `sells-pest.yml`) · `tests/Feature/Domain/Fsm/FluxosTelaContratoTest.php` (UC-FLXT-19, lane `domain-pest.yml`).

> owner: wagner · priority: p1 · estimate: 4h · type: story
> blocked_by: US-SELL-067

**Origem:** decisão [W] 2026-10-19 — *"margem abaixo do mínimo bloqueia a conversão do orçamento em venda; só quem tem a permissão aprova. Configure a margem por empresa na tela de Fluxos."*

**Aceite:**
- [x] A empresa grava a margem mínima do orçamento na aba Fluxos da empresa (`fluxos.configurar`); vazio desliga.
- [x] Converter orçamento (rascunho) em venda com margem pelo custo guardado abaixo do mínimo é recusado para quem não tem `sell.aprovar_margem_baixa`, com a margem e o mínimo na mensagem.
- [x] Quem tem a permissão converte; sem mínimo configurado, com margem acima ou sem custo guardado, converte normalmente.
- [x] O desconto do pedido entra na margem.
- [x] A permissão aparece na tela de papéis.
- [ ] Mostrar a margem e o mínimo na tela do orçamento antes de tentar converter.

### US-SELL-070 · Termômetro de acesso da proposta (UC-FLX-04) — **P2**

**Implementado em:** `app/Services/Sells/QuoteAccessRecorder.php` · `app/Jobs/QuoteFollowUpJob.php` · `app/Console/Commands/Sells/QuoteFollowUpCommand.php` · `database/migrations/2026_10_20_120000_create_transaction_quote_accesses_table.php` · `app/Http/Controllers/SellController.php` · `resources/js/Pages/Sells/Show.tsx` · `resources/js/Pages/Sells/Quotations.tsx`

**Testado em:** `tests/Feature/Sells/QuoteAccessTermometroContratoTest.php` (lane `sells-pest.yml`) — casos UC-QUO-05..08 no Quotations.casos.md da tela de cotações · `tests/Feature/Sells/QuoteAccessTelaContratoTest.php` — UC-VSHOW-20/21 no Show.casos.md e UC-QUO-10.

> owner: wagner · priority: p2 · estimate: 3h · type: story

**Origem:** caso UC-FLX-04 da tela de Fluxos (`app/Domain/Fsm/Modelos/CasosDoPrototipo.php:57`, fonte de mercado Mubisys) — na etapa Enviado, registrar quando o cliente abre a proposta; sem abertura em 48 h, criar tarefa de retorno ao vendedor.

**O que existe (medido 2026-10-20):** a etapa existe (`quote_sent`, ação `enviar_orcamento` em `database/seeders/FsmProcessoVendaComProducaoSeeder.php:178`) e o link público do orçamento também (`/quote/{token}` em `routes/web.php:509` → `SellPosController::showInvoice`, `app/Http/Controllers/SellPosController.php:2397`). O `showInvoice` só renderiza: não grava acesso nenhum. Não há coluna nem tabela de abertura (`opened_at`/`viewed_at`) em `app/` nem em `Modules/Crm`. O CRM registra o mesmo gap para a proposta dele (`memory/requisitos/Crm/SPEC.md:95`, *"falta tracking abertura/aceite"*). O agendamento de retorno do CRM (`Modules/Crm/Entities/Schedule.php`) é o destino natural da tarefa.

**Aceite:**
- [x] Abrir o link público `/quote/{token}` de um orçamento em `quote_sent` grava a primeira abertura e o número de aberturas; acesso de usuário logado da própria empresa não conta.
- [x] O detalhe do orçamento mostra "aberto em <data>" ou "não aberto", só para quem tem `sell.view` (ou `direct_sell.view`) na empresa.
- [x] Orçamento em `quote_sent` há mais de 48 h sem abertura gera uma tarefa de retorno para o vendedor, uma vez só (rodar de novo não duplica).
- [x] A rotina de 48 h roda por empresa e recebe o `business_id` (job assíncrono não usa sessão); token de outro business não grava nada.
- [x] Usuário de outro business que pede o detalhe ou o histórico de abertura recebe 404.

**Entregue em 2026-10-20 (backend):** a abertura vai para a tabela-ponte `transaction_quote_accesses` (não altera `transactions`; sem IP nem user agent). HEAD e prefetch do navegador não contam; falha ao gravar não derruba a página. A tarefa de retorno é um agendamento do CRM (`crm_schedules`, tipo ligação, atribuído ao `created_by` do orçamento e ligado à venda em `crm_followup_invoices`); empresa sem as tabelas do CRM não ganha tarefa. Rotina diária `sells:quote-follow-up --sync` às 08:15 (`app/Console/Kernel.php`). Fora desta entrega: a tela ("aberto em <data>" no detalhe, com o 404 de outro business) — o dado já está na tabela.

**Entregue em 2026-10-21 (tela):** o detalhe do orçamento (`/sells/{id}`) ganha a faixa "Aberto pelo cliente em dd/mm hh:mm" (com quantas vezes e a última, se mais de uma) ou "Ainda não aberto pelo cliente", no payload deferido `detail.acesso`; a lista de cotações ganha a coluna "Aberto". A leitura é pelo business da venda. Quem vê é quem abre a venda (gate do `/sells/{id}`: `sell.view`, `direct_sell.access` ou `view_own_sell_only` restrito às próprias). Outro business recebe 404.

### US-SELL-071 · Pesquisa de satisfação depois da entrega (UC-FLX-25) — **P2**

**Implementado em:** _pendente_

**Testado em:** `tests/Feature/Sells/PesquisaSatisfacaoEntregaContratoTest.php` (lane `sells-pest.yml`) — casos UC-VSHOW-09..13 no Show.casos.md do detalhe da venda.

> owner: wagner · priority: p2 · estimate: 4h · status: doing · type: story

**Origem:** caso UC-FLX-25 da tela de Fluxos (`app/Domain/Fsm/Modelos/CasosDoPrototipo.php:78`, fonte de mercado Mubisys) — dois dias depois da entrega, enviar a pesquisa; nota baixa abre atendimento.

**O que existe (medido 2026-10-20):** a etapa de entrega existe (ação `entregar` → `delivered` em `database/seeders/FsmProcessoVendaComProducaoSeeder.php:194` e `:196`). Há pesquisa de satisfação pronta, mas para **conversa** do WhatsApp, não para venda: `Modules/Whatsapp/Services/Csat/CsatDispatcher.php:57` (`dispatchOnResolve`, disparado ao resolver a conversa em `Modules/Whatsapp/Http/Controllers/Admin/InboxController.php:753`), nota 1-5 lida por `Modules/Whatsapp/Services/Csat/CsatResponseParser.php:116`, gravada em `Modules/Whatsapp/Entities/CsatResponse.php`. Nada liga a entrega da venda a uma pesquisa, nem nota baixa a um atendimento.

**Aceite:**
- [x] Venda que chega a `delivered` agenda a pesquisa para 2 dias depois; cancelada ou devolvida antes disso, não envia.
- [x] A pesquisa sai pelo canal do cliente (reaproveitando o CSAT do WhatsApp quando houver canal) só se o cliente aceita receber (`Contact::canReceiveWhatsappNotification()` / `canReceiveEmailNotification()`), e uma vez por venda.
- [x] Nota abaixo do limite configurado pela empresa abre um atendimento ligado à venda e ao cliente.
- [x] Job e agendamento recebem o `business_id`; a resposta só se grava na venda da mesma empresa.
- [ ] As respostas aparecem para quem tem `sell.view` na empresa; usuário de outro business recebe 404.

**Entregue em 2026-10-20 (backend, status segue aberto pelo aceite 5):** tabela-ponte `transaction_satisfaction_surveys` (uma linha por venda, UNIQUE business + venda; não altera `transactions`). Rotina diária `sells:pesquisa-entrega --sync` às 10:00 (`app/Console/Kernel.php`) roda `App\Jobs\PesquisaSatisfacaoEntregaJob` por empresa que ligou em `business.common_settings['sells_pesquisa_entrega']` (`App\Services\Sells\PesquisaEntregaConfig`; desligada por padrão, nota baixa padrão 2 na escala 1-5). Pega a venda pela última entrada em `delivered` no `sale_stage_history`, há 2 a 9 dias, ainda em `delivered`/`completed`. Envia pelo `CsatDispatcher` do WhatsApp na conversa do cliente (a nota volta pelo `CsatResponseParser` e é copiada para a pesquisa), senão por e-mail; cliente sem opt-in fica `nao_enviado` com o motivo. O atendimento é uma tarefa no agendamento do CRM (`crm_schedules`, ligada à venda em `crm_followup_invoices`) para o vendedor — não há entidade de atendimento/ticket ligada à venda no núcleo. Código: `app/Jobs/PesquisaSatisfacaoEntregaJob.php` · `app/Services/Sells/PesquisaEntregaConfig.php` · `app/Console/Commands/Sells/PesquisaSatisfacaoEntregaCommand.php` · `app/Mail/PesquisaSatisfacaoEntregaMail.php` · `database/migrations/2026_10_20_140000_create_transaction_satisfaction_surveys_table.php`. Fora desta entrega: a tela das respostas com `sell.view` e 404 de outro business (aceite 5), a resposta por e-mail (não há leitor) e a tela de ligar/desligar.

### US-SELL-072 · Venda com nota ou pagamento trava itens e valores — **P1**

**Implementado em:** `app/Services/Sells/TravaDaVenda.php` · `app/Contracts/Fiscal/NotaDaVenda.php` · `app/Http/Controllers/SellPosController.php` · `Modules/NfeBrasil/Services/NotaDaVendaNfeBrasil.php` · `Modules/NFSe/Services/NotaDaVendaNfse.php`

**Testado em:** `tests/Feature/Sells/SellsTravaAposNotaContratoTest.php` (lane `sells-pest.yml`) — casos UC-SEDIT-13..18 no Edit.casos.md da tela de edição de venda.

> owner: wagner · priority: p1 · estimate: 4h · type: story

**Origem:** decisão [W] 2026-10-20, textual: *"Trava itens e valores após nota"*. Com nota fiscal autorizada na venda, itens, valores e cliente não se editam — cancela-se a nota antes. Com pagamento, só os valores travam; campos de texto seguem editáveis.

**O que existia (medido 2026-10-20):** a edição de venda só era barrada pelo prazo `transaction_edit_days` e por devolução existente (`SellPosController@edit`, `SellController@edit`, `TransactionUtil::canBeEdited`). Nada olhava nota nem pagamento: o update (`SellPosController@update`, caminho do Blade e da tela React) regravava linhas, total e estoque de venda com NF-e autorizada.

**Aceite:**
- [x] Venda com NF-e/NFC-e autorizada (ou em contingência) ou NFS-e emitida recusa mudar quantidade, preço, desconto, imposto, frete, despesas, cliente e situação, com a mensagem "Venda com nota autorizada: cancele a nota para alterar itens e valores.", e nada muda (total, linhas, estoque).
- [x] Com nota, mudar só observação, nota interna ou dados de entrega grava esses campos sem passar pelo recálculo.
- [x] Nota cancelada não trava.
- [x] Venda com pagamento (sem nota) recusa mudar valores com "Venda com pagamento: valores não podem ser alterados; estorne o pagamento antes."; observação e cliente seguem editáveis.
- [x] Venda sem nota nem pagamento edita como antes.
- [x] Nota de outro business nunca trava a venda.
- [ ] A tela de edição desabilita os campos travados e mostra o motivo (a prop `trava` já chega em `Sells/Edit`).

**Entregue em 2026-10-20 (servidor):** `TravaDaVenda` responde o que pode ser editado e o `SellPosController@update` recusa antes de qualquer cálculo. A nota é lida pelos módulos fiscais pela tag `App\Contracts\Fiscal\NotaDaVenda::TAG` (o núcleo não importa de módulo). Pagamento = `transaction_payments` sem troco com valor, ou títulos do Financeiro pagos ou parciais (`SituacaoPagamentoVenda`). A decisão lê os números com o mesmo `num_uf` e multiplicador do `createOrUpdateSellLines`; ela não calcula nem grava valor. Fora desta entrega: desabilitar os campos na tela React e no Blade, e os outros caminhos que mexem em valor de venda (edição de pagamento, devolução).

### US-SELL-073 · Volumes no protocolo de entrega (UC-FLX-23) — **P2**

**Implementado em:** `app/Http/Controllers/Api/App/PedidoEntregaController.php` · `database/migrations/2026_10_20_160000_add_volumes_to_app_entregas_table.php` · `app/Http/Controllers/SellController.php` · `resources/js/Pages/Sells/Show.tsx`

**Testado em:** `tests/Feature/Sells/AppPedidoEntregaContratoTest.php` e `tests/Feature/Sells/SellsShowEntregaContratoTest.php` (lane `sells-pest.yml`) — casos UC-ENTR-01..04 e UC-VSHOW-22/23 no Show.casos.md do detalhe da venda.

> owner: wagner · priority: p2 · estimate: 1h · type: story

**Origem:** caso UC-FLX-23 da tela de Fluxos (`app/Domain/Fsm/Modelos/CasosDoPrototipo.php:76`, fontes de mercado Mubisys, Corebridge e Cyrious) — na etapa Entregue, gerar o protocolo com itens, volumes e quem recebeu.

**O que existe (medido 2026-10-20):** o protocolo já existia para a **venda**, pela entrega do app: `POST /api/app/pedidos/{id}/entrega` (`routes/api/app/pedidos.php:18` → `PedidoEntregaController@registrar`, contrato `memory/requisitos/AppMobile/api/tela-22-entrega.md`) grava `ENT-NNNN` por business em `app_entregas` (`database/migrations/2026_10_15_180000_create_app_entregas_table.php`) com os itens conferidos, quem recebeu, documento, assinatura e GPS, e executa a ação `entregar` da FSM na mesma transação. Faltavam os **volumes**: sem coluna e sem campo. Ninguém lê `app_entregas` além do próprio controller (sem tela, PDF ou GET do protocolo). O caso no catálogo aponta `US-COMVIS-007`, mas essa US é da OS da Comunicação Visual, que este protocolo não cobre.

**Aceite:**
- [x] O app pode mandar `volumes` (total do protocolo, inteiro de 1 a 9999); ele é gravado em `app_entregas.volumes` e devolvido na resposta 201.
- [x] `volumes` fora da faixa, fracionado ou texto → `422 campos.volumes`, sem gravar nada.
- [x] Sem `volumes` (app antigo) a entrega funciona como antes, com `volumes` nulo; a mesma `Idempotency-Key` com o mesmo corpo antigo segue devolvendo a resposta gravada.
- [x] Pedido de outro business → 404 e nada gravado; o protocolo grava no business do usuário.
- [x] O detalhe da venda (`/sells/{id}`) mostra o protocolo, os itens, os volumes e quem recebeu, só para quem vê a venda na empresa (2026-10-21 · `SellController::entregaDaVenda` + seção Entrega do `Show.tsx` · UC-VSHOW-22/23).
- [ ] Protocolo da OS da Comunicação Visual (UC-FLX-23 no processo de OS), que hoje não tem protocolo.

**Entregue em 2026-10-20 (backend):** total de volumes, não por item — um volume (caixa, rolo, pacote) pode levar várias linhas da venda, então volume por linha não é bem definido. Opcional porque o app que já está nos celulares não manda o campo; sem ele não se inventa número (fica nulo, "não informado"). O `volumes` só entra no hash de idempotência quando vem, para que o corpo antigo mantenha o hash de antes.

### US-SELL-074 · Cliente aprova o orçamento pelo link público, e a aprovação vira pedido

> owner: wagner · priority: p1 · estimate: 10h · status: todo · type: story · origin: lacunas-concorrencia-2026-10-25
> blocked_by: —

**Implementado em:** _pendente_ — fatia 1 (gerar o link + o que o cliente vê, atrás de `SELLS_ORCAMENTO_ACEITE_LINK`); falta a página pública, a decisão e o movimento da FSM.

O link público do orçamento (`SellPosController@showInvoice`) só mostra o documento. O cliente passa a aprovar (informando o nome) ou pedir ajuste; a aprovação fica registrada na venda, com data e IP, e o vendedor converte em pedido pelo caminho de conversão que já existe. Não muda valor nem estoque. Tier 0: o token do link só abre o orçamento da própria empresa.

### US-SELL-075 · Maquininha integrada ao caixa (TEF): o valor vai para a máquina e a resposta preenche o pagamento

> owner: wagner · priority: p1 · estimate: 40h · status: doing · type: story · origin: lacunas-concorrencia-2026-10-25
> blocked_by: — (só o driver real depende da escolha do integrador [W])

**Implementado em:** _pendente_ — em andamento (servidor feito em `TefService` e `TefController`): camada independente da adquirente com driver `simulador`, atrás de `SELLS_TEF_ENABLED` (desligada); UC-TEF-01..07 em `resources/js/Pages/Sells/Create.casos.md`. Falta o botão no caixa e o driver da adquirente escolhida.

**Testado em:** `tests/Feature/Sells/TefCaixaContratoTest.php`

Os drivers do `Modules/PaymentGateway` são de cobrança online; não há TEF. Antes de código: escolher o integrador (TEF dedicado, SmartPOS ou maquininha com API do adquirente). Aceite: o caixa manda o valor, a máquina devolve aprovação com bandeira, NSU e autorização, e o pagamento da venda nasce preenchido. Mexe em valor: regra mestre.

**Aceite:**
- [x] O caixa manda o valor e recebe `pendente`; a consulta devolve `aprovada` com bandeira, NSU e autorização (UC-TEF-02).
- [x] Reenvio com a mesma chave não cobra duas vezes (UC-TEF-03); cobrança de outra empresa não aparece (UC-TEF-05).
- [x] Valor com mais de 2 casas é recusado; centavos conferidos por dois caminhos (UC-TEF-06).
- [ ] A linha de pagamento do caixa ganha "Cobrar na maquininha" e nasce preenchida com a resposta.
- [ ] Driver da adquirente escolhida pelo [W].

### US-SELL-076 · Cupom de desconto e promoção leve X pague Y no caixa novo

> owner: wagner · priority: p2 · estimate: 16h · status: doing · type: story · origin: lacunas-concorrencia-2026-10-25
> blocked_by: —

**Implementado em:** _pendente_ — em andamento: cupom pelo servidor feito (`App\Services\Sells\CupomDaVenda`, `POST /sells/cupom`, tabela `sell_coupons`, UC-VCUP-01..05 em `Sells/Create.casos.md`), atrás da flag `SELLS_CUPOM` desligada. Falta o campo do cupom no caixa React, gastar o uso ao gravar a venda, o cadastro dos cupons e a promoção leve X pague Y.

Hoje há desconto por período (`DiscountController`). Faltam cupom com código, validade e limite de uso, e a promoção leve X pague Y aplicada sozinha no caixa. Mexe em valor: regra mestre (antes→depois e dupla prova).

Regra de acúmulo (2026-10-27): a promoção (`discounts`) vale por linha; o cupom vale sobre o carrinho. Cupom que **acumula** incide sobre o subtotal já com a promoção. Cupom que **não acumula** (padrão) dá ao cliente o maior dos dois: só entra se for maior que a promoção e, aí, a substitui. Desconto nunca passa da base.
### US-SELL-078 · O ICMS-ST entra no total da venda, pela mesma conta da NF-e

> owner: wagner · priority: p1 · estimate: 16h · status: doing · type: story · origin: coordenacao-lacunas-2026-10-26
> blocked_by: R-NFE-043 (NfeBrasil, ST no XML)

**Implementado em:** _pendente_ — em andamento: servidor feito (`App\Services\Sells\IcmsStDaVenda`, `POST /sells/icms-st`, UC-VST-01..05 em `Sells/Create.casos.md`). Falta a tela de venda mostrar o ST no total.

A NF-e passou a somar o ICMS-ST no vNF (R-NFE-043, regra do leiaute). Sem esta US, a nota valeria mais do que a venda e o título que a geraram.

Decisão [W] 2026-10-26: "O ST entra no fechamento da venda: com cliente contribuinte de outra UF, o caixa calcula o ST pelo mesmo motor da NF-e, mostra no total e o cliente paga junto. O título já nasce com o ST."

Aceite:

- O ST da venda é o da NF-e (55): mesma função (`NfeService::montarItensNfe`), mesmas entradas (emitente do local, destinatário do cadastro do cliente, alíquota interna do destino, NCM padrão). Só há ST com cliente identificado de outra UF e interna do destino cadastrada.
- A tela pede o ST do carrinho (`POST /sells/icms-st`), mostra no total e manda `icms_st_valor` junto. Ao gravar, o servidor recalcula; divergiu mais de 1 centavo → a venda é recusada com o valor certo.
- A venda guarda o ST (`transactions.icms_st_valor`) e o `final_total` já o inclui. A nota parte de `final_total − icms_st_valor` e soma o vST: **total da venda = vNF = valor do título**.
- Venda sem ST: nada muda.
- Tier 0: produto, cliente e local resolvidos no business da sessão.


### US-SELL-079 · Resgate de pontos de fidelidade no caixa React

> owner: wagner · priority: p1 · estimate: 6h · status: doing · type: story · origin: coordenacao-lacunas-2026-10-27

**Implementado em:** _pendente_ — em andamento: `App\Services\Sells\ResgatePontosFidelidade` chamado pelo `SellPosController::store` e o bloco `Sells/_components/ResgatePontos.tsx` na `Sells/Create` (UC-SRP-01..06 em `Sells/Create.casos.md`). Falta: ligar `SELLS_RESGATE_PONTOS_ENABLED` depois do teste no caixa.

Os pontos de fidelidade nativos (`business.enable_rp`) já tinham regra de ganho, validade, configuração em React (`Configuracoes/Empresa`, aba Pontos de fidelidade) e extrato na ficha do cliente. Faltava resgatar no caixa React: o `store` aceitava `rp_redeemed` e `rp_redeemed_amount` como vinham, e quem conferia era o JavaScript da Blade. Decisão [W] 2026-10-27: "constrói o resgate de pontos de fidelidade no caixa React, usando o rp_redeemed que o backend já aceita" (no lugar do cashback em dinheiro, que duplicava os pontos).

Aceite (UC-SRP-01..06 em `resources/js/Pages/Sells/Create.casos.md`):

- Com cliente cadastrado, a venda mostra os pontos disponíveis (`POST /sells/pos/get-reward-details`) e aceita um resgate; o valor (pontos × valor do ponto, 2 casas) sai do total da venda.
- O servidor confere antes de gravar: pontos ligados, cliente da empresa e não padrão, pontos entre o mínimo e o disponível, venda acima do mínimo, valor batendo com os pontos (±0,01). Senão recusa e a tela mantém o carrinho.
- A tela só mostra o resgate com `SELLS_RESGATE_PONTOS_ENABLED` (nasce desligada). A conferência no servidor vale sempre, para a Blade também.
- Fora: resgate na edição da venda e na Venda V3 (que ainda não grava).
### US-SELL-077 · Cancelar a venda devolve o estoque, tira a venda dos relatórios e cancela o título

> owner: wagner · priority: p0 · estimate: 12h · status: doing · type: story · origin: coordenacao-lacunas-2026-10-26
> blocked_by: —

**Implementado em:** _pendente_ — em andamento: `App\Services\Sells\CancelamentoDaVenda` chamado pelo `CancelarVendaCascade` (UC-VCAN-01..08 em `Sells/Show.casos.md`). Falta: as consultas que contam `status != 'draft'` como compra do cliente e o dry-run das vendas antigas já canceladas.

Antes, a ação FSM `cancelar_venda` cancelava notas e boleto pendente e soltava a reserva ativa, mas a venda continuava `final`: o estoque baixado não voltava, o título seguia aberto e relatórios e conferência de estoque ainda contavam a venda.

Decisões [W] 2026-10-26:

- **Venda cancelada vira `status = 'cancelled'` + `cancelled_at`.** Sai dos relatórios de faturamento, e a conferência de estoque passa a fechar. Vendas antigas já canceladas pela FSM e ainda `final` só mudam depois de um dry-run com tabela antes→depois aprovado pelo [W]; sem ele, não se mexe nelas.
- **Venda já paga não cancela** até o dinheiro ser estornado ou devolvido. A recusa diz quanto foi recebido e o que fazer para liberar.

Aceite:

- O estoque volta pelo caminho por onde saiu: venda `final` devolve, por linha, `quantidade − já devolvida` e libera o lote de compra (mesmo caminho da devolução); reserva consumida pela FSM devolve a `qty_reserved` e passa a `released`.
- Título sem pagamento: cancelado no Financeiro (`cancelarSeExistir`), nunca apagado.
- Só pela ação FSM (`ExecuteStageActionService`), dentro da transação: recusa não deixa nada pela metade. Idempotente.
- Só para venda; a OS do Repair que usa o mesmo efeito não muda.
- Tier 0: outra empresa não cancela.


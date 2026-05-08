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

> owner: wagner · priority: p1 · estimate: 28h · status: todo · type: epic · origin: sessao-2026-05-08-runbook-mwart-sells
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

> owner: wagner · priority: p1 · estimate: 1.5h · status: todo · type: story · origin: sessao-2026-05-08-runbook-mwart-sells
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

> owner: wagner · priority: p1 · estimate: 1h · status: todo · type: story · origin: sessao-2026-05-08-runbook-mwart-sells
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

> owner: wagner · priority: p1 · estimate: 0.75h · status: todo · type: story · origin: sessao-2026-05-08-runbook-mwart-sells
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

> owner: wagner · priority: p1 · estimate: 2.5h · status: todo · type: story · origin: sessao-2026-05-08-runbook-mwart-sells
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

> owner: wagner · priority: p1 · estimate: 1.5h · status: todo · type: story · origin: sessao-2026-05-08-runbook-mwart-sells
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

> owner: wagner · priority: p1 · estimate: 1h · status: todo · type: story · origin: sessao-2026-05-08-runbook-mwart-sells
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

> owner: wagner · priority: p0 · estimate: 8h (1h codável + 7d canary humano) · status: todo · type: story · origin: sessao-2026-05-08-runbook-mwart-sells
> blocked_by: US-SELL-007

**Contexto.** Travas finais antes de tocar ROTA LIVRE. Crítico — Wagner 99% do volume é Larissa.

**Escopo:**
- [ ] **Audit cockpit-runbook modo B** — score completo, corrigir todos CRITICAL e WARN antes de seguir
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

### US-SELL-009 · Cutover ROTA LIVRE + remover Blade após 30d

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
6. **Audit cockpit-runbook modo B obrigatório** — score ≥ 70 em CADA US-SELL-00X antes de mergear PR. CRITICAL bloqueia merge.
7. **Backup DB antes do cutover** — `mysqldump` das 4 tabelas críticas. Restore em <5min se necessário.
8. **Aviso prévio pra Larissa** — humano-no-loop, ela sabe que mudança rolou e tem canal direto pra reportar.
9. **30 dias monitorando antes de remover Blade** — janela longa pra qualquer regressão de borda aparecer.
10. **PR ≤ 300 linhas, 1 PR = 1 intent** — skill `commit-discipline` Tier A.

## 4. Anotações pré-início

> Wagner usa `tasks-comment <ID> "anotação"` pra registrar pensamentos antes de começar cada US.
> Comentários ficam DB-only (não vão pro SPEC) e aparecem em `tasks-detail`.

Histórico de comentários por US fica navegável via `/copiloto/admin/qualidade` ou tool MCP `tasks-detail task_id:US-SELL-NNN`.

---

**Última atualização:** 2026-05-08

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

## 5. User Stories — State Machine canônica (sessão 2026-05-10)

> Cadeia criada após pivot conceitual com Wagner: **venda sem nota é caminho feliz, não falha**. US-RB-044 fechada com DoD prod-evidence removida. Padrão FSM (Finite State Machine + RBAC por transição) será reutilizado por Sells, Repair, Project e qualquer feature multi-etapa futura.

### US-SELL-010 · Investigar State Machines existentes (Repair, Project, mcp_tasks) + propor ADR padrão FSM canônico

> owner: wagner · priority: p1 · estimate: 6h · status: todo · type: story
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

> owner: — · priority: p0 · estimate: 4h · status: todo · type: story · origin: sessao-2026-05-11-migration-officeimpresso
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

> owner: — · priority: p0 · estimate: 2h · status: todo · type: story · origin: sessao-2026-05-11-migration-officeimpresso
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

> owner: — · priority: p1 · estimate: 4h · status: todo · type: story · origin: heatmap-2026-05-11
> blocked_by: US-SELL-015
> evidence: 3-4 campos data com uso real >30% em pelo menos 1 cliente (DT_FATURAMENTO 92% Extreme/Gold · DT_COMPETENCIA 100% Vargas · DT_PROMETIDO 85% Gold). Preset Ano essencial (10+ anos histórico em todos)

**Contexto.** Delphi tem botões verdes Dia/Semana/Mês/Ano + dropdown "Personalizado · Data:" com 6 opções (Última Alteração, Emissão NF, Emissão, Dt. Faturamento, Dt. Env. Faturamento, Dt. Competência, Dt. Prometido). Sinal pra ativar: snapshot Firebird mostrar ≥30% das sessões usando filter por data customizado.

**Escopo (a especificar quando sinal confirmar):** botões `<Tabs>` Dia/Semana/Mês/Ano default `emissão`; dropdown "Tipo de data" pra trocar campo filtrado; date-range custom (popover `<DateRangePicker />`); URL deep-link `?date_from=...&date_to=...&date_field=transaction_date`.

### US-SELL-019 · Agrupamento drag-to-group por campo do grid · **P1 confirmado**

> owner: — · priority: p1 · estimate: 8h · status: todo · type: story · origin: heatmap-2026-05-11
> blocked_by: US-SELL-015
> evidence: CODFINANCEIRO_GRUPO em uso 43-65% das linhas em todos clientes (WR2 34.5% · Vargas 65.1% · Extreme 43.3% · Gold 53.1%)

**Contexto.** Delphi tem barra "Arraste uma coluna para fazer o agrupamento" no topo do grid. Cliente arrasta "Cliente" → vendas agrupadas por cliente com subtotal. Sinal: snapshot Firebird mostrar ≥20% das sessões usando agrupamento.

**Escopo (a especificar):** TanStack Table `getGroupedRowModel`; drag-to-group via dnd-kit; subtotal por grupo (count + sum); expand/collapse por grupo; multi-level grouping (Cliente → Status → Mês).

### US-SELL-020 · Especificação campo "Status" (financeiro vs produção vs fiscal — badges separados) · **P2 (rebaixado)**

> owner: — · priority: p2 · estimate: 2h · status: todo · type: story · origin: heatmap-2026-05-11
> blocked_by: US-SELL-015
> evidence: SITUACAO estruturado só em Gold (7 distinct, 29k vendas EM PRODUÇÃO); WR2 5 distinct mas pobre; Vargas/Extreme 1 distinct vazio = não usa. Status separados em badges é **feature de cliente específico (PCP)**, não padrão

**Contexto.** Hoje "Status" é só financeiro (Pago/A receber/Parcial/Atrasada). Delphi mostra 3 status separados: Financeiro, Produção ("EM APROVAÇÃO", "ENTREGUE", "ORC APROVA…"), Fiscal (Rejeitada/Emitir). Sinal: reclamação cliente migrado.

**Escopo (a especificar):** 3 colunas badge distintas — `Status Financeiro` (atual), `Status Produção` (depende US-SELL-023), `Status Fiscal` (já existe parcial via US-NFE-MANUAL).

### US-SELL-021 · Especificação campo "Data" (qual data: emissão / NF / faturamento / competência / prometido) · **P0 (subido!)**

> owner: — · priority: p0 · estimate: 3h · status: todo · type: story · origin: heatmap-2026-05-11
> blocked_by: US-SELL-015
> evidence: DT_PROMETIDO existe e é 85% preenchido em Gold mas **ausente como coluna** em WR2/Vargas/Extreme. Schema OfficeImpresso varia entre instalações — Grade Avançada **não pode hardcodar colunas**, header da coluna Data precisa dropdown dinâmico ler o que existe

**Contexto.** Hoje coluna "Data" mostra `transaction_date`. Delphi mostra 6 datas: Emissão, Última Alteração, Emissão NF, Dt. Faturamento, Dt. Env. Faturamento, Dt. Competência, Dt. Prometido. Sinal: reclamação cliente migrado ("qual data é essa?").

**Escopo (a especificar):** header da coluna Data tem dropdown pra trocar qual data exibir; URL `?date_field=...` deep-link; tooltip mostra todas as 6 datas em hover.

### US-SELL-022 · Sub-linha de produtos por venda (expandir linha) · **P2 confirmado**

> owner: — · priority: p2 · estimate: 6h · status: todo · type: story · origin: heatmap-2026-05-11
> blocked_by: US-SELL-015
> evidence: Vargas média 3.08 itens/venda (47% das vendas 2-5 itens; 15% 6+); outros marginais (1.30-1.58). Vale pra cliente gráfica produtiva, não pra majoritária

**Contexto.** Delphi mostra produto + MEDIDAS · Quant · R$ Valor · R$ Total · Situação ao expandir uma venda inline no grid (sem abrir drawer). Útil pra gráfica que vende lona 5,60×3,10m. Sinal: snapshot Firebird mostrar ≥15% das sessões usando expandir.

**Escopo (a especificar):** ícone chevron à esquerda da linha; fetch lazy dos itens da venda; render sub-tabela compacta.

### US-SELL-023 · Status produção visível na lista (badge separado) · **P1 (subido!)**

> owner: — · priority: p1 · estimate: 3h · status: todo · type: story · origin: heatmap-2026-05-11
> blocked_by: US-SELL-020, FSM ([ADR 0129](../../decisions/0129-state-machine-canonica-fsm-rbac.md))
> evidence: Gold tem **29.559 vendas em "EM PRODUÇÃO" + 7.082 "FINALIZADA"** — uso massivo de PCP. Tabela `AGENDA_TITULO_WORKFLOW` aparece em todos 3 clientes (Vargas/Extreme/Gold) como possível fonte de workflow

**Contexto.** Delphi mostra ENTREGUE/REIMPRESSÃO/EM APROVAÇÃO/ORC APROVA. Requer FSM produção (US-SELL-011 base + processo "Venda com Produção" novo) e mapping → badge. Investigar `AGENDA_TITULO_WORKFLOW` no PR.

### US-SELL-024 · Campo "venda agrupada" explícito · **P1 (subido!)**

> owner: — · priority: p1 · estimate: 2h · status: todo · type: story · origin: heatmap-2026-05-11
> blocked_by: US-SELL-015, US-SELL-019
> evidence: Mesmo sinal de US-SELL-019 (43-65% das linhas com CODFINANCEIRO_GRUPO em todos clientes). Sem coluna explícita `is_grouped_invoice`, o agrupamento fica ambíguo como no Delphi ("ATIVO CRIADO" string)

**Contexto.** Delphi infere "está agrupada" do texto "ATIVO CRIADO" no campo Status (confuso pro cliente). Fazer certo: coluna boolean `is_grouped_invoice` + badge "Agrupada" quando true.

### US-SELL-025 · Botões agrupamento rápido (1-click) · **P3 confirmado**

> owner: — · priority: p3 · estimate: 2h · status: todo · type: story · origin: heatmap-2026-05-11
> blocked_by: US-SELL-019
> evidence: depende de telemetria pós-US-SELL-019 — só depois saberemos quais 3 agrupamentos são os mais usados

**Contexto.** Telemetria pós-US-SELL-019 vai mostrar quais 3 agrupamentos são mais usados; vira botões 1-click ("Por Cliente", "Por Mês", "Por Status").

### US-SELL-026 · Impressão batch de vendas selecionadas (PDF consolidado) · **P2 (subido)**

> owner: — · priority: p2 · estimate: 3h · status: todo · type: story · origin: heatmap-2026-05-11
> blocked_by: US-SELL-016
> evidence: power-user OfficeImpresso vai pedir — expectativa óbvia ao migrar (Delphi tinha "Relatório de Vendas Selecionadas"). Não é P0 só porque US-SELL-016 já entrega "imprimir seleção" combinando DANFEs; P2 é layout consolidado (capa + N notas + totalizador)

**Contexto.** US-SELL-016 entrega "Imprimir seleção" combinando DANFEs. P2 estende pra layout consolidado (1 capa + N notas + 1 totalizador) — útil pra entregar lote físico ao cliente OfficeImpresso que recebia "Relatório de Vendas Selecionadas" do Delphi.

### US-SELL-027 · Schema discovery dinâmico Grade Avançada · **P0 (subida v2!)**

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

> owner: — · priority: p1 · estimate: 4h · status: todo · type: story · origin: heatmap-v3-2026-05-11-vargas-recapagem
> blocked_by: ADR `Modules/OficinaAuto` qualificada (futuro amend de ADR 0121)
> evidence: 2 de 4 candidatos OfficeImpresso saudáveis são oficina (Vargas grande recapagem caminhão + Martinho caçambas avulsas). Vargas exige multi-placa (PLACA2 20%, CHASSI2 8%) — cavalo+reboque. Martinho usa só PLACA simples (96%). Schema deve cobrir ambos casos: PLACA obrigatória + PLACA_SECUNDARIA opcional + CHASSI opcional + CHASSI_SECUNDARIO opcional. Ver [perfil Vargas](../../research/clientes-legacy-officeimpresso/02-vargas-recapagem/01-perfil.md) e [perfil Martinho](../../research/clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md)

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

> owner: — · priority: p0 · estimate: 3h codável + 5h tests · status: todo · type: story · origin: sessao-2026-05-12-discovery-pipeline
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

> owner: — · priority: p0 · estimate: 6h codável + 4h tests · status: todo · type: story · origin: sessao-2026-05-12-discovery-pipeline
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

> owner: — · priority: p1 · estimate: 2h codável + 1h tests · status: todo · type: story · origin: sessao-2026-05-12-discovery-pipeline
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

> owner: — · priority: p1 · estimate: 4h codável + 3h tests · status: todo · type: story · origin: sessao-2026-05-12-discovery-pipeline
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

> owner: — · priority: p0 · estimate: 6h codável + 4h tests · status: todo · type: story · origin: sessao-2026-05-12-discovery-pipeline
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

> owner: — · priority: p1 · estimate: 4h codável + 3h tests · status: todo · type: story · origin: sessao-2026-05-12-discovery-pipeline
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

> owner: — · priority: p2 · estimate: 8h frontend (sem canary) · status: todo · type: story · origin: sessao-2026-05-12-discovery-pipeline
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

---

**Última atualização:** 2026-05-12 — **discovery + spec executable Pipeline Vendas (7 GAPs)**. Wagner valida casos de uso + testes failing-first **antes** de implementar (estratégia: pagar custo agora com poucos clientes ativos vs. retrabalho exponencial com mais clientes). Antes era heatmap v3 → agora pipeline canon completo Orçamento→Produção→Venda→Faturamento. Total SPEC: **5 P0 + 5 P1 + 3 P2 + 1 P3 (US-015..028) + 4 P0 + 2 P1 + 1 P2 (US-029..035) = 21 US ativas**. Cumpre [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) (sinal qualificado pelo próprio Wagner — pain points reportados em sessão).

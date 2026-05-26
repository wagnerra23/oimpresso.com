---
title: "Levantamento Martinho-ready — o que falta terminar pra cliente Martinho biz=164 operar plenamente"
date: 2026-05-26
type: session-log
status: ativo
scope_modulos: [OficinaAuto, Sells, Compras, Financeiro, Whatsapp, RecurringBilling, NfeBrasil]
cliente: Martinho Caçambas LTDA (biz=164 · Capivari de Baixo SC · CNAE 4520 mecânica pesada)
related_adrs: [0093, 0105, 0137, 0143, 0171, 0192, 0194]
owner: [W]
sem_aprovacao_humana: tasks_propostas_nao_criadas_no_mcp
---

# Levantamento Martinho-ready — o que falta terminar (2026-05-26)

## Resumo executivo (3 bullets)

- **Núcleo PARCIAL em prod biz=164** desde 2026-05-13: ✅ 91 vehicles + ✅ 43.995 vendas (header) + ✅ 83.044 títulos + ✅ 18.856 contatos + FSM canon + Fiscal cockpit habilitado 2026-05-25 + Auto-faturar OS→Venda LIVE 2026-05-25. **MAS** (descoberto 2026-05-26 manhã — ver §0): ❌ 0 sell_lines em prod · ❌ 1.838 products legacy jan/2025 (não os 4.378 do Firebird) · ❌ 15 VLD com saldo zero · ❌ 1 purchase transaction (de 15.617 esperadas).
- **4 frentes objetivas estão "devendo"**: (0) **NOVO 2026-05-26 — gap maratona 13-17/05** — produtos/sell_lines/compras/estoque vão pro Herd local em vez de Hostinger por bug `--mysql-host` default (ver §0); (a) domínio classificado errado até ADR 0194 mergear 2026-05-26 — vocabulário "locação caçamba/daily_rate/m³" precisa reescrever em telas/charters/seeds antes de Martinho usar sem confusão; (b) `final_total=0` em OS de manutenção — **causa raiz revisada §0**: sell_lines=0 em prod, sem linhas de produto não tem como calcular nada; (c) bug `/fiscal/nfse 500` em prod (schema race migration duplicada) — NFSe inutilizada apesar de pacote R$ [redacted Tier 0] incluir.
- **Sem isso destravado, Fase 2 ROADMAP OficinaAuto não ativa** (gate ADR 0171 = Martinho assina add-on OU aceita beta 30d explícito) — base R$ [redacted Tier 0]/mês continua faturando mas add-on WhatsApp R$ [redacted Tier 0]/instância (revenue stream incremental) está bloqueado.

---

## §0 — ACHADO CRÍTICO 2026-05-26 manhã — gap silencioso maratona 13-17/05 → prod biz=164

> Descoberto durante investigação "migrar estoque Martinho" (pedido Wagner 2026-05-26 ~08:30). Detalhes completos da investigação no diff deste session log + [plano-migracao-estoque-martinho 2026-05-26](./2026-05-26-plano-migracao-estoque-martinho.md) §status final.

### O que aconteceu

Maratona 2026-05-13→17 importou cliente Martinho biz=164. Cinco scripts da branch `claude/wip-martinho-canary-2026-05-14` (commits `32b0ea31e` + `db3342ae0`) rodaram com `--target prod --confirm` em 2026-05-14 15:28–15:29 conforme logs `output/martinho-{produtos,estoque}-prod-*.log`. Logs limpos `Errors: 0`. **Mas só metade chegou em prod Hostinger** — outra metade foi pro **Herd local Wagner** sem detecção.

### Causa raiz

`import-produtos.py` linha 321 + `import-estoque.py` linha 152 + (provável) `import-compras.py` + `import-venda-produto.py`:

```python
parser.add_argument("--mysql-host", default=os.environ.get("MYSQL_HOST", "127.0.0.1"))
parser.add_argument("--mysql-database", default=os.environ.get("MYSQL_DATABASE", "oimpresso"))
```

Flag `--target prod` SÓ aciona gate `--confirm`, **não muda host**. Sem `--mysql-host=127.0.0.1 --mysql-port=33069` explícito + SSH tunnel ativo, conecta no Herd local `127.0.0.1:3306` database `oimpresso`. Audit JSON tem `"target":"prod"` mas **não captura mysql_host real** (silent bug · sem assertion).

Compare: `import-vehicles.py`, `import-empresas.py`, `import-financeiro.py`, `import-vendas.py` (header) — esses usam path diferente, conectam direto Hostinger via cred próprio · chegaram em prod sem problema.

### Snapshot real prod biz=164 (queries diretas 2026-05-26 ~09:00)

| Tabela | Esperado (Firebird Martinho) | Prod biz=164 atual | Status |
|---|---|---|---|
| `vehicles` | 91 | **91** | ✅ chegou |
| `contacts` | DISTINCT clientes VENDA | **18.856** | ✅ chegou (importer próprio) |
| `transactions` `type='sell'` | ~44.709 | **43.995** | ✅ chegou (header só) |
| `fin_titulos` | ~103.000 | **83.044** | ✅ chegou (filtro/skip ~20k a auditar) |
| `transaction_sell_lines` (biz=164 join) | ~milhares | **0** | ❌ **NÃO chegou** |
| `products` (biz=164) | 4.378 esperado | **1.838** (de jan/2025) | ❌ migração 14/05 NÃO chegou em prod; só legacy antigo |
| `variation_location_details` (biz=164) | 4.378 com saldo | **15** com `qty_available=0,00` | ❌ NÃO chegou |
| `transactions` `type='purchase'` | ~15.617 | **1** | ❌ NÃO chegou |

`max(products.updated_at) WHERE business_id=164 = 2025-01-14 12:01:03` — 16 meses sem update. Confirma: re-import 14/05 escreveu em Herd local, não Hostinger.

### Implicação dramática

1. **B2 (`final_total=0` em OS manutenção) tem causa raiz REVISADA** — não é só falta de catálogo peça hidráulica V0 ([ADR 0194](../decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md) §"Critério validação"). É que `transaction_sell_lines` está **vazia em prod** — Auto-faturar Observer LIVE 2026-05-25 está criando `transactions` header com `final_total=0` porque **não tem linhas pra somar**.
2. **44k vendas em prod estão "ocas"** — header existe (data, cliente, ref_no=VENDA.CODIGO Delphi), mas sem `transaction_sell_lines.product_id` referenciando produtos reais. Operacionalmente impossível Martinho consultar "o que vendeu no item X em Mar/2025".
3. **Estoque OperacIonal inviável** — 15 VLD com `qty_available=0` pra biz=164 (apenas 4 produtos legacy jan/2025 com matching). Tela `/products/{id}/stock` mostra zero.
4. **Compras zeradas** — 1 purchase transaction de ~15.617 esperadas. Sem isso, Modules/Compras não tem histórico Martinho pra mostrar pra Wagner negociar com fornecedores.
5. **Maratona "validada prod" linha 17 do RUNBOOK-migracao-cliente-legacy.md ESTÁ ERRADA** — quando diz "91 vehicles + ~103k títulos + ~44k vendas (sessão maratona 2026-05-13 → 2026-05-17)", a parte "44k vendas" é só header sem detalhe. Update do RUNBOOK necessário no §11 deste doc.

### Fix necessário

**Patch nos scripts da branch `claude/wip-martinho-canary-2026-05-14`** (não na main):
- Mudar default `--mysql-host` de `127.0.0.1` pra **`None`** + assertion no `args.target == "prod"`: se host não foi passado explícito, abortar com erro claro `"--target prod EXIGE --mysql-host + --mysql-port (use migrar-tudo.py wrapper)"`.
- Adicionar `mysql_host` + `mysql_database` no audit JSON pra prevenção (next time captura silent bug).
- Aplicar em `import-produtos.py` + `import-estoque.py` + `import-compras.py` + `import-venda-produto.py` + qualquer outro novo importer da branch.

**Re-rodada em ordem canônica via `migrar-tudo.py` orquestrado** (após patch):
1. `import-produtos.py --target prod --confirm` (4.378 produtos · UPSERT por officeimpresso_codigo · vai atualizar os 1.838 já existentes + criar 2.540 novos)
2. `import-estoque.py --target prod --confirm` (4.580 saldos · UPDATE only)
3. `import-venda-produto.py --target prod --confirm` (sell_lines pra header já existente · key transaction.ref_no = VENDA.CODIGO)
4. `import-contacts-from-nfe.py --target prod --confirm` (fornecedores · pré-req pra compras)
5. `import-compras.py --target prod --confirm` (15.617 NFE + linhas)

### Documento canônico do bug

[plano-migracao-estoque-martinho 2026-05-26](./2026-05-26-plano-migracao-estoque-martinho.md) — escrito antes do achado · plano ORIGINAL (18.5h estoque) ficou OBSOLETO · realidade é trabalho maior (recovery + patch).

---

## §1 — Bloqueadores P0 (sem isso Martinho não opera o que foi prometido)

| # | Item | Módulo | Onde (path) | Esforço h-humano | Status atual |
|---|---|---|---|---|---|
| **B0** | **🚨 NOVO 2026-05-26 — Recovery prod biz=164 maratona 13-17/05 (gap silencioso `--mysql-host` default)**. Trazer 4.378 produtos + 4.580 saldos + sell_lines (~milhares) + 15.617 compras + fornecedores NFE pra Hostinger prod. **Bloqueia B2 raiz** (Auto-faturar Observer gera `final_total=0` por falta de sell_lines) + Fase 2 ROADMAP OficinaAuto (catálogo é pré-req cobrança real). | OficinaAuto / legacy-migration | `scripts/legacy-migration/import-{produtos,estoque,venda-produto,compras,contacts-from-nfe}.py` (na branch `claude/wip-martinho-canary-2026-05-14`) + worktree `D:/oimpresso.com/.claude/worktrees/estoque-rerun/` já preparada | **8-12h** (patch `--mysql-host` 1h · dry-run all 5 importers 2h · audit Wagner inspecionar 1h · `migrar-tudo.py` orquestrado prod 2h · smoke conciliação 2h · session log debrief 1h · skill update memory-pending) | 🔴 P0 absoluto — descoberto manhã 2026-05-26 · worktree pronta · script `import-estoque.py` dry-run rodado OK · falta patch + outros 4 importers + prod run |
| B1 | **`/fiscal/nfse` 500 em prod** (schema race migration duplicada `create_nfse_emissoes_table` batch 69 vs 106). Pacote Martinho R$ [redacted Tier 0] inclui NFSe mas tela quebra. Workaround atual aponta legacy `/nfse` 200. | NfeBrasil/Fiscal | `Modules/Fiscal/Http/Controllers/NfseCockpitController.php` + migrations `2026_05_01_000003_*` + `2026_05_11_150001_*` | 4-8h (caminho A reverter Controller pro schema velho — mais rápido; ou B migration RENAME 13 colunas) | task #12 documentada · root cause achado · Wagner decide caminho A/B/C |
| B2 | **`final_total=0` em OS de manutenção** — **CAUSA RAIZ REVISADA 2026-05-26 §0**: além de Observer/ADR 0194 que esperava catálogo hidráulico V0, descoberto que `transaction_sell_lines` tem 0 rows em prod biz=164 — vendas existem em header mas sem linhas, então Observer não consegue somar item × preço. **Pré-req: fechar B0 primeiro** (trazer sell_lines + produtos). Pós-B0 Observer ainda precisa cobrir cálculo `peça×qty + hora-trabalho×horas`. | OficinaAuto | `Modules/OficinaAuto/Observers/ServiceOrderObserver.php:144-154` (método `computeFinalTotal`) **+ B0 recovery** | 8h V0 (catálogo peça hidráulica básico + 2 campos `oa_pecas_utilizadas`/`oa_servicos_executados` minimalistas) — **+8-12h B0 antes** = 16-20h total real | known gap — Wagner edita manual hoje · raiz era maior que o leitura pré-§0 indicava |
| B3 | **Schema órfão pós-ADR-0194**: `service_orders.daily_rate` + `expected_return_date` + `delivery_address` + accessor `valor_receber`/`is_overdue` viraram inutilizáveis pro caso real Martinho. Charter Vehicles.tsx/ServiceOrders.tsx + RUNBOOK + BRIEFING ainda referenciam "locação caçamba avulsa" / "m³ volume". | OficinaAuto | `Modules/OficinaAuto/Database/Migrations/2026_05_12_220002_add_rental_fields_to_service_orders.php` + `Entities/ServiceOrder.php:62-200` + `memory/requisitos/OficinaAuto/{BRIEFING,ROADMAP,RUNBOOK-migracao-cliente-legacy,demo-martinho-2026-05-13/*}.md` | 3-5h reescrita docs (NÃO drop schema — ADR 0194 §3.5 manda preservar nullable) | review_trigger ADR 0194 vence 2026-06-15 (drift) |
| B4 | **US-OFICINA-005 cleanup tools NÃO entregue** — Martinho importou 91 veículos + 44k vendas + 103k títulos com 76.7% inadimplência. Sem tela "Revisão pendências legadas" (write-off/cancelar/renegociar batch), relatórios do oimpresso mostram R$ [redacted Tier 0]M no relatório mas só R$ [redacted Tier 0]k cobrável (lixo fóssil 2015-19). Wagner não pode usar pra cobrança real. | OficinaAuto / Financeiro | (a criar) `Pages/OficinaAuto/CleanupLegacy/Index.tsx` + Service backend filtrando `fin_titulos.metadata.is_write_off_candidate=true` | 12h (3 sub-features: (a) tela batch UI 200/dia × 23d, (b) conciliação VENDA↔FINANCEIRO drift detector, (c) PESSOAS dedup fuzzy) | US-OFICINA-005 P0 todo no SPEC — bloqueada Martinho operar cobrança honesta |
| B5 | **Compras Tier 0 leak parcial** — PR #1576 hotfix aplicado em 3 métodos `TransactionUtil::getList*` mas US-COM-006/007/008/009 todos P0 todo (cross-tenant Pest faltando · `session()` em vez de `auth()` em business_id source). Martinho cadastrar fornecedores hidráulicos sem isso = risco vazamento cross-business. | Compras | `Modules/Compras/Http/Controllers/ComprasController.php` + `TransactionUtil.php` | 11h (4h pest + 2h auth + 2h FormRequest + 3h JOIN scope) | 4 tasks P0 ativas no MCP |
| B6 | **WhatsApp Anti-cross-contact P0 incident 2026-05-14** (US-WA-094) — incident já mitigado em PR #854 (linker suffix-8) mas tarefa permanece `todo` p0. Sem cobrança Whatsapp R$ [redacted Tier 0]/instância pode confundir conversas de clientes do Martinho. | Whatsapp | `Modules/Whatsapp/Services/Linker/*` | 3h (validar suffix-8 hardening + Pest cross-business) | US-WA-094 todo p0 |
| B7 | **RecurringBilling Inter PJ PIX cobrança imediata (US-RB-050) + webhook receiver (US-RB-051)** — Martinho R$ [redacted Tier 0]/mês CYCLE-06 G1 declarou "wiring Martinho". Sem isso fatura mensal Martinho NÃO cobra automaticamente — Wagner emite boleto manual. | RecurringBilling | `Modules/RecurringBilling/Services/Gateways/InterAdapter.php` (a criar/completar) | 9h (US-RB-050 4h + US-RB-051 5h) | US-RB-050/051 P0 todo wagner-owner cycle-06 |

**Total esforço P0: ~50-58h-humano** (~6-7 dias úteis Wagner+Felipe IA-pair real, NÃO 10x recalibrado — Wagner pediu horas reais).

---

## §2 — Telas devendo / inacabadas P1 (em uso parcial, faltam coisas)

| # | Item | Módulo | Onde (path) | Esforço h-humano | Status atual |
|---|---|---|---|---|---|
| T1 | **OficinaAuto AprovacaoPublica.tsx** charter `status: draft` — pacote WhatsApp R$ [redacted Tier 0] prometeu aprovação OS via PIN mas charter não chegou em `live`. | OficinaAuto | `resources/js/Pages/OficinaAuto/AprovacaoPublica.tsx` + charter | 7h (US-OFICINA-014 já estimada) | charter draft · bloqueada por US-OFICINA-006 FSM wire-up |
| T2 | **Sells/Index FSM rollout 14 vendas legadas biz=1** — US-SELL-036 P0 wagner-owner. Sem isso FSM canon biz=1 não está canary 7d limpo, e Martinho biz=164 não pode replicar pattern. | Sells | `php artisan fsm:bulk-start-pipeline` adapted | 4h | US-SELL-036 P0 todo wagner |
| T3 | **Sells cutover ROTA LIVRE + remover Blade após 30d (US-SELL-009)** — telas Blade `sell/create.blade.php`, `sell/edit.blade.php`, `sale_pos/*.blade.php` AINDA ativas em `app/Http/Controllers/SellController.php` e `SellPosController.php` como fallback. Martinho usa balcão → cai em Blade legacy quando devia ir pra Inertia. | Sells | `app/Http/Controllers/SellController.php` (linhas com `return view('sell.create')`, `sale_pos.show` etc) + 30+ Blade partials | 4h cutover + smoke 30d wallclock | US-SELL-009 P0 todo wagner — bloqueada US-SELL-008 |
| T4 | **Financeiro Onda 22 — anexos/aprovação UI 3 tasks P0 abertas** (US-FIN-026/027/028) · Martinho usa títulos com aprovação contábil → sem essas o workflow trava em revisor → aprovador. | Financeiro | `Pages/Financeiro/Unificado/_components/FinAnexosPanel.tsx` (parcial) + `FinPillFrescor.tsx` + Spatie permission `financeiro.titulo.aprovar` | 6.5h | 3 tasks P0 todo Onda 22 |
| T5 | **Financeiro BUG-3 listener cria `titulo_pagar` pra purchase com payment_status=due** (US-FIN-015) — duplica títulos pendentes na conciliação cruzada Compras↔Financeiro. Confunde Martinho. | Financeiro | listener `Modules/Financeiro/Listeners/SyncFromPurchase.php` | 3h | US-FIN-015 P0 todo wagner |
| T6 | **NfeBrasil Foundation domínio (US-NFE-040) Epic** — migrations + models + composer foundation ainda não fechada. Martinho emite NFe via cockpit Fiscal mas Foundation tem débito técnico Epic 16h. Não bloqueia operação mas trava evoluções fiscais. | NfeBrasil | (Epic) | 16h | US-NFE-040 P0 todo |
| T7 | **WhatsApp daemon SIGTERM revoga session (US-WA-079)** — restart CT 100 perde pareamento Baileys. Operacionalmente Martinho perde conexão quando daemon reinicia. | Whatsapp | `daemon/index.ts` sock.logout → sock.end | 3h | US-WA-079 P1 todo |
| T8 | **Charter OficinaAuto ProducaoOficina/Index.charter.md** — não validado se reflete `?os=SO-N` query param auto-open drawer (gap conhecido §2 Wave Z-2: "drawer auto-open via `?os=` query param ainda não implementado nos kanbans receptores"). Martinho clica notif Sells→OficinaAuto, cai no kanban correto mas drawer não abre sozinho. | OficinaAuto | `resources/js/Pages/OficinaAuto/ProducaoOficina/Index.tsx` | 2h | gap-conhecido Wave Z-2 backlog item #3 |
| T9 | **`ServiceOrder.$fillable` falta `contact_id`** — mass-assignment filtrado silenciosamente. UI form provavelmente seta direto via setAttribute(). Risco operador criar OS sem cliente vinculado. | OficinaAuto | `Modules/OficinaAuto/Entities/ServiceOrder.php` $fillable | 30min | gap-conhecido Wave Z-2 backlog item #1 |
| T10 | **Compras telas Blade ainda ativas** — 5 partials em `resources/views/business|contact|home/partials/*purchase*.blade.php`. Cadastro fornecedor peça hidráulica Martinho via Inertia incompleto (Pages/Compras só tem Index.tsx + Drawer.tsx — não tem Create.tsx Edit.tsx separados). | Compras | `Modules/Compras` + `resources/views/*purchase*` | 8h (migrar Create/Edit + cutover Blade) | US-COM-002 P2 ainda | 

**Total esforço P1: ~54h-humano** (~6-7 dias úteis adicionais).

---

## §3 — Bugs ativos / regressions abertas

| # | Sintoma | Módulo | Issue/PR/commit | Severidade |
|---|---|---|---|---|
| BG1 | `/fiscal/nfse` 500 prod (schema race) | NfeBrasil/Fiscal | task #12 doc · root cause: migrations duplicadas batch 69 vs 106 | **sev1** — feature paga bloqueada |
| BG2 | `final_total=0` Transaction derivada de OS manutenção | OficinaAuto | known gap Wave Z-2 item #2 | **sev2** — Wagner edita manual cada OS |
| BG3 | `Wave26/27/28 forceDelete` 3 Pest tests vermelhos pre-existentes (afirmam NfeService NUNCA contém `->forceDelete()` mas linha 956 tem) | NfeBrasil | ADR 0193 proposed (PR #1518 aguarda Wagner) | **sev3** — bloqueia merge cascateando PRs que tocam Repair |
| BG4 | Drawer auto-open via `?os=` query param ainda não implementado kanbans receptores | OficinaAuto/Repair | known gap Wave Z-2 item #3 | sev3 — UX friction, não bloqueia |
| BG5 | `valor_receber` accessor zera quando `status='concluida'` (RENTAL_ACTIVE_STATUSES check restrito) | OficinaAuto | known gap Wave Z-2 item #2 | sev3 — só relevante pra sub-vertical 3 hipotético locação (não Martinho pós-ADR 0194) |
| BG6 | `composer install --optimize-autoloader` falha pre-existing `custom_views` directory missing | infra | known gap Wave Z-2 item #5 | sev4 — workaround `optimize:clear` funciona |
| BG7 | **Achado lateral Tier 0 (CRÍTICO)**: Role `Admin#164` tem permission `superadmin` cross-tenant. Pattern provavelmente em outros businesses. | Permissions/Multi-tenant | sessão 2026-05-25-fiscal achado lateral — **não investigado** | **sev1 potencial** — violação Tier 0 IRREVOGÁVEL ADR 0093 se confirmado |
| BG8 | Compras `TransactionUtil::getList*` R1 leak Tier 0 (3 métodos) | Compras | PR #1576 hotfix parcial · US-COM-006/009 P0 todo cobrir restante | sev1 — risco vazamento cross-business |
| BG9 | NfeBrasil `retransmitir` usava forceDelete (CONFAZ Art. 14) | NfeBrasil | fixado PR #1575 (a2422362a) | resolvido — mantido aqui pra audit |
| BG10 | Financeiro migration `caixa_bridge` usava `account_type` enum em vez de FK `account_type_id` | Financeiro | fixado PR #1521 (a81057ba0) | resolvido — bloqueou 6 migrations em cascade até fix |
| BG11 | `ProducaoOficinaController:368` chamava `toDateString()` em string (não Carbon) | Repair | fixado PR #1529 (6644a86e3) | resolvido |
| BG12 | Anti-cross-contact WhatsApp (incident 2026-05-14) | Whatsapp | mitigado PR #854 · US-WA-094 P0 ainda aberta pra validar hardening | sev2 — operacional aceito mas não fechado canon |

---

## §4 — Schema órfão / débito técnico

- **Migration `2026_05_12_220002_add_rental_fields_to_service_orders.php`** — 4 colunas (`order_type`/`delivery_address`/`expected_return_date`/`daily_rate`) + accessor `valor_receber`/`is_overdue` viraram inutilizáveis pra Martinho pós-ADR 0194 (sub-vertical 3 locação caçamba container = hipótese sem cliente real). Decisão ADR 0194 §3.5: **preservar nullable sem drop** — review_trigger M6+ caso cliente real surgir. Risco: dev novo achar que é feature ativa.
- **ADR 0192 `final_total` recalc OS mecânica** — Observer `ServiceOrderObserver::computeFinalTotal` hardcoda 0.0 pra `order_type='manutencao'` (linha 153). ADR 0194 §"Critério validação" exige recalcular pra `peça×qty + hora×horas` quando V1 catálogo peça hidráulica chegar.
- **BRIEFING/ROADMAP/RUNBOOK/charters OficinaAuto** ainda referenciam "locação caçamba avulsa" / "m³ volume" / `daily_rate` — review_trigger ADR 0194 vence 2026-06-15. PR separado pós-aceite.
- **CAPTERRA-FICHA OficinaAuto** — score 63 calculado contra concorrentes locação caçamba (errado). Recalibrar contra Auto Manager / Mecânico (Tecnomotor) / Plumelp / Sysmecânica — review_trigger 2026-06-30.
- **Compras** sem `Pages/Compras/Create.tsx` + `Edit.tsx` separados — só Index.tsx + Drawer.tsx (CRUD Inertia incompleto).
- **Modules/Financeiro/Http/Controllers/FinanceiroController.php** retorna 4× `view('financeiro::index')` legacy stub (não deve ser usado mas existe).
- **Modules/RecurringBilling/Http/Controllers/RecurringBillingController.php** retorna 3× `view('recurringbilling::create')` legacy stubs.
- **Modules/NfeBrasil/Http/Controllers/NfeBrasilController.php** retorna 4× `view('nfebrasil::index')` legacy stubs (causa do bug `/nfebrasil` 500 que motivou redesign sidebar 3 entries flat).
- **`Modules/OficinaAuto/Config/retention.php:30`** — único TODO real no módulo (LGPD override per-business config).

---

## §5 — Tests vermelhos

| Suite | Vermelhos | Causa provável |
|---|---|---|
| `Wave26/27/28 forceDelete` 3 Pest NfeBrasil | 3 | Pre-existente main · afirma `NfeService NUNCA contém ->forceDelete()` mas linha 956 do Service tem. ADR 0193 proposed propõe Caminho A soft-delete (~50 LOC trivial). Bloqueia merges cascateando. |
| `ComprasIndexTest SQLite guard` 5 fails | 5 | Pre-existente (PR #1584 documentou) — fix SQLite guard para skip gracioso. Não bloqueia prod, atrapalha CI vermelho. |
| **OficinaAuto suite** | **0 confirmado** | 24 tests Feature presentes (CRUD/multi-tenant/FSM/observability/cleanup/E2E/security/LGPD). Sem PHP local pra rodar agora — não confirmei verde, **mas Wave Z-2 smoke E2E declarou ✅ biz=1+biz=4+biz=164**. |
| `module-grades-gate` bloqueia merges sem cobertura D8 FormRequests | indeterm. | Detectado maratona Financeiro 2026-05-25 — PR J/K precisaram label `module-grades-allowed-regression` |

**Recomendação**: rodar `php artisan test --filter=OficinaAuto` + `--filter=Compras` + `--filter=NfeBrasil` ANTES de smoke real prod Martinho — confirmar real vs declarado.

---

## §6 — Roadmap proposto 2 semanas pra reconquistar Martinho

### Semana 1 (Sem 22 · 2026-05-26 → 2026-06-01) — DESTRAVAR BLOQUEADORES P0

**Dia 1** (2026-05-26 — HOJE pós-achado §0):
- **B0 recovery prod biz=164** (gap maratona 13-17/05) — 8-12h. Patch `--mysql-host` nos 4-5 importers WIP branch · dry-run all · audit Wagner · `migrar-tudo.py` prod orquestrado · smoke conciliação. **DESTRAVAR ESTE PRIMEIRO** (B2 não fecha sem B0, B4 cleanup tools precisa product_id real)
- (paralelo) B1 (`/fiscal/nfse` 500 fix) — 4h Caminho A

**Dia 2** (2026-05-27):
- B5 (Compras Tier 0 — US-COM-006/007/008/009 P0) — 11h Felipe IA-pair em paralelo

**Dia 3-4** (2026-05-28/29):
- B6 (Whatsapp anti-cross-contact hardening US-WA-094) — 3h
- B7 (RecurringBilling Inter PJ PIX US-RB-050/051) — 9h Wagner

**Dia 5** (2026-05-30):
- B3 (reescrita BRIEFING/ROADMAP/RUNBOOK/charters OficinaAuto pós-ADR 0194) — 5h Wagner+Claude

### Semana 2 (Sem 23 · 2026-06-02 → 2026-06-08) — CLEANUP + COBRANÇA HONESTA

**Dia 6-8** (2026-06-02/03/04):
- B4 (US-OFICINA-005 cleanup tools 3 sub-features) — 12h Felipe IA-pair
- T5 (BUG-3 Financeiro US-FIN-015) — 3h paralelo

**Dia 9-10** (2026-06-05/06):
- B2 (catálogo peça hidráulica V0 + recalc final_total OS mecânica) — 8h
- T2 (Sells FSM rollout biz=1 canary US-SELL-036) — 4h paralelo

**Dia 11-12** (2026-06-07/08):
- T4 (Financeiro Onda 22 — anexos/aprovação 3 tasks) — 6.5h
- T9 (`ServiceOrder.$fillable` contact_id) — 30min
- **Smoke real biz=164 Martinho FULL** (Chrome MCP + tinker + checklist 60-item) — 2h Wagner

**Não cabe nas 2 semanas, fica Sem 24+**: T3 cutover ROTA LIVRE Blade (30d wallclock), T6 NFE Foundation Epic 16h, T1 AprovacaoPublica (bloqueada FSM wire-up), T10 Compras Create/Edit Inertia, BG7 investigação Admin#164 superadmin (sev1 potencial — separar pra sessão própria).

**Total Sem 22 + Sem 23**: ~67h Wagner+Felipe IA-pair (~8-9 dias úteis). Margem 30% imprevistos = entrega ~2026-06-09 com Martinho operando plenamente.

---

## §7 — Tasks acionáveis (proposed — Wagner aprova batch via `tasks-create` MCP)

> **NÃO criadas no MCP** — publicação só após Wagner aprovar batch (skill `publication-policy`). Lista pronta pra colar/editar.

### P0 (12 tasks)

- [P0] **US-FISCAL-NFSE-FIX** · NfeBrasil — `/fiscal/nfse` 500 fix (schema race) — Caminho A reverter Controller pro schema velho (cpf_cnpj_tomador / value_servico / emitted_at → tomador_cnpj / valor_servicos / created_at) · 4h · ref task #12 sessão 2026-05-25-fiscal
- [P0] **US-OFICINA-005-A** · OficinaAuto — Tela "Revisão pendências legadas" batch UI (200/dia × 23d Martinho) com ações Baixar/Cancelar/Renegociar/Write-off · 6h
- [P0] **US-OFICINA-005-B** · OficinaAuto/Financeiro — Conciliação VENDA↔FINANCEIRO drift detector (374 vendas 12m sem lançamento R$ [redacted Tier 0]M Martinho) · 4h
- [P0] **US-OFICINA-005-C** · OficinaAuto/Crm — PESSOAS deduplicador fuzzy match (~920 razões sociais órfãs Martinho) · 2h
- [P0] **US-OFICINA-023** · OficinaAuto — Catálogo peça hidráulica V0 (`oa_pecas_utilizadas` + `oa_servicos_executados` mínimos) + recalc `ServiceOrderObserver::computeFinalTotal` pra `peça×qty + hora×horas` quando `order_type='manutencao'` · 8h · ref ADR 0194 §"Critério validação"
- [P0] **US-OFICINA-024** · OficinaAuto/docs — Reescrita BRIEFING + ROADMAP + RUNBOOK-migracao-cliente-legacy + 5 charters pós-ADR 0194 (vocabulário sub-vertical 4 peça hidráulica) · 5h · ref review_trigger ADR 0194 vence 2026-06-15
- [P0] **US-COM-006** (já existe) — Pest cross-tenant biz=1 vs biz=99 4 testes · 4h
- [P0] **US-COM-007** (já existe) — Fix business_id source `auth()` em vez de `session()` + `abort_if` · 2h
- [P0] **US-COM-008** (já existe) — Throttle 60/1 em /compras + `ListarComprasRequest` · 2h
- [P0] **US-COM-009** (já existe) — Validar JOIN scope `contacts.business_id` em `TransactionUtil::getListPurchases` (R1 leak) · 3h
- [P0] **US-RB-050** (já existe) — Inter PJ PIX cobrança imediata wiring Martinho · 4h · wagner-owner cycle-06
- [P0] **US-RB-051** (já existe) — Inter PJ webhook PIX receiver · 5h · wagner-owner cycle-06

### P1 (8 tasks)

- [P1] **US-OFICINA-025** · OficinaAuto/Repair — Drawer auto-open via `?os=` query param nos kanbans receptores (`/oficina-auto/producao-oficina?os=SO-N` + `/repair/producao-oficina?os=OS-N`) · 2h · ref gap Wave Z-2 backlog item #3
- [P1] **US-OFICINA-FILLABLE-FIX** · OficinaAuto — `ServiceOrder.$fillable` += `contact_id` (gap Wave Z-2 #1) · 0.5h
- [P1] **US-FIN-015** (já existe) — Fix BUG-3 listener cria titulo_pagar pra purchase com payment_status=due · 3h
- [P1] **US-FIN-026** (já existe) — UI lista anexos GET no drawer Unificado + thumbnail PDF + delete · 3h
- [P1] **US-FIN-027** (já existe) — Pill aprovacao_status na tabela Unificado + filtro workflow · 2h
- [P1] **US-FIN-028** (já existe) — Spatie permission `financeiro.titulo.aprovar` + gate UI · 1.5h
- [P1] **US-WA-094** (já existe) — Anti-cross-contact P0 hardening + Pest cross-business · 3h
- [P1] **US-WA-079** (já existe) — Fix daemon SIGTERM revoga session sock.logout → sock.end · 3h

### P2 (5 tasks)

- [P2] **US-CAPTERRA-RECALIB-OFICINA** · OficinaAuto/docs — Recalibrar CAPTERRA-FICHA contra Auto Manager / Mecânico Tecnomotor / Plumelp / Sysmecânica pós-ADR 0194 · 4h · ref review_trigger 2026-06-30
- [P2] **US-NFE-040** (já existe) — Epic Foundation domínio NFe migrations + models + composer · 16h
- [P2] **US-SELL-009** (já existe) — Cutover ROTA LIVRE + remover Blade após 30d · 4h + 30d wallclock
- [P2] **US-SELL-036** (já existe) — FSM rollout migrar 14 vendas legadas biz=1 via bulk-start-pipeline + canary 7d · 4h
- [P2] **US-ADMIN164-SUPERADMIN-AUDIT** · Permissions/Security — Investigar role `Admin#164` permission `superadmin` cross-tenant + scan batch outros businesses (achado lateral sessão 2026-05-25) · 6h · **sev1 potencial Tier 0 ADR 0093**

### P3 (1 task)

- [P3] **US-NFE-FORCEDELETE-FIX** · NfeBrasil — ADR 0193 Caminho A implementação (~50 LOC soft-delete em vez de forceDelete) · 2h · destrava 3 Pest vermelhos cascateando PRs

**Resumo batch sugerido pro `tasks-create` MCP**:
- 12 P0 (~50h)
- 8 P1 (~18h)
- 5 P2 (~34h + 30d wallclock)
- 1 P3 (~2h)
- **Total: 26 tasks · ~104h h-humano** (~13 dias úteis IA-pair real, 2.5-3 semanas wallclock incluindo paralelismo Wagner+Felipe)

Wagner aprova/edita/colab batch antes de Claude rodar `tasks-create`.

---

## Refs

- [ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL](../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0105 — Cliente como sinal qualificado](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)
- [ADR 0137 — OficinaAuto qualificada](../decisions/0137-modules-oficinaauto-qualificada.md)
- [ADR 0143 — FSM Pipeline LIVE prod](../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
- [ADR 0171 — OficinaAuto ativação piloto Martinho faseada](../decisions/0171-oficinaauto-ativacao-piloto-martinho-faseada.md)
- [ADR 0192 — Auto-faturar OS→Venda Observer](../decisions/0192-auto-faturar-os-venda-jobsheet-observer.md)
- [ADR 0194 — Correção domínio OficinaAuto Martinho mecânica pesada](../decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md)
- [SPEC OficinaAuto](../requisitos/OficinaAuto/SPEC.md)
- [BRIEFING OficinaAuto](../requisitos/OficinaAuto/BRIEFING.md)
- [ROADMAP OficinaAuto](../requisitos/OficinaAuto/ROADMAP.md)
- [RUNBOOK-migracao-cliente-legacy](../requisitos/OficinaAuto/RUNBOOK-migracao-cliente-legacy.md)
- [Plano migração estoque Martinho 2026-05-26 manhã](./2026-05-26-plano-migracao-estoque-martinho.md) — escrito antes do achado §0 · plano de 18.5h ficou OBSOLETO · realidade exige recovery maior
- [Sessão Wave Z-2 completa prod 2026-05-25](./2026-05-25-wave-z2-completa-prod-live-3-fixes.md)
- [Sessão Fiscal sidebar tests CI 2026-05-25](./2026-05-25-sessao-fiscal-sidebar-tests-ci.md)
- [Sessão Financeiro 11 PRs marathon 2026-05-25](./2026-05-25-financeiro-11-prs-marathon.md)
- Perfil Martinho: `memory/research/clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md`

# Como-integrar — migração `purchase/*` legacy → `Modules/Compras/` greenfield

> **Data:** 2026-05-21
> **Agent:** `como-integrar` (introspectivo, zero web)
> **Pedido Wagner:** mapear plug-points pra futura migração, NÃO implementar nada.
> **Veredito macro:** **AUSENTE pra UX nova** + **PARCIAL pra backend** — `Modules/Compras/` não existe; mas tabela `transactions` (type='purchase'), `TransactionUtil`, Observer Financeiro e import XML SEFAZ (`nfe_dfe_recebidos`) já existem e devem ser **reusados, não duplicados**. Caminho recomendado: **B híbrido** — `Modules/Compras/` greenfield SÓ pra Controllers/Pages/Sidebar, mas reusa `transactions` polimórfica + `TransactionUtil` + `TransactionObserver` existentes. Greenfield total quebra Financeiro/Manufacturing/Fiscal.

---

## Fase 1 — Inventário (o que já existe?)

### 1.1 Tabela canônica

| O que procurei | Onde achei | Status |
|---|---|---|
| Controller compras legacy | `app/Http/Controllers/PurchaseController.php` (1769+ linhas, 15 métodos públicos) | **completo** — Blade + Inertia híbrido (linhas 73-79 fazem early-return Inertia/v=2) |
| Rotas `/purchases/*` | `routes/web.php:301-307, 583, 641-642` | **9 rotas registradas** — `Route::resource` + 8 ad-hoc (import-purchase-products, update-status, get_products, get_suppliers, get_purchase_entry_row, check_ref_number, get-purchase-order-lines/{id}, print/{id}, show/{id}) |
| Rota `/compras` (PT-BR) | grep em `routes/web.php` | **0 hits — livre** |
| `Modules/Compras/` | Glob `Modules/Compras/**` | **0 hits — não existe** |
| Views Blade legacy | `resources/views/purchase/*.blade.php` | **14 arquivos** (create, edit, index, show + 9 partials + 2 keyboard_shortcuts). DataTable Yajra + 3-4 modais (import_purchase_products, update_purchase_status, purchase_order_lines) |
| Tipos `purchase` em Transaction | `app/Transaction.php:15` comment | **purchase, purchase_return, purchase_order, purchase_transfer** (4 types). `production_purchase` é Manufacturing (diferente, ProductionService:62) |
| Métodos TransactionUtil pra compras | `app/Utils/TransactionUtil.php:3044 (purchaseCurrencyDetails), :4893 (getListPurchases), :6147 (updatePurchaseOrderStatus)` | **3 métodos diretos** + `createPurchase`/`updatePurchase` lógica inline em PurchaseController::store/update |
| Bridge Financeiro `transactions → fin_titulos` pra purchase | `Modules/Financeiro/Observers/TransactionObserver.php:46-53` | **JÁ COBRE type='purchase'** — Observer Onda 2 (2026-04-25) chama `TituloAutoService::sincronizarDeTransacao($tx)` unificado pra sell+purchase. Comentário linha 20: "cobre type='sell' E type='purchase'" |
| Import XML NF-e (fornecedor → meu CNPJ) | `Modules/NfeBrasil/Services/Manifestacao/DistribuicaoDfeService.php` + `BuscarDfesRecebidosJob` + tabela `nfe_dfe_recebidos` | **PARCIAL** — XML é puxado via SEFAZ NSU + gravado em `nfe_dfe_recebidos` (US-NFE-049/051, ADR 0116). Tela existe (`Modules/Fiscal/Http/Controllers/DfeController.php` → `Fiscal/Dfe` Inertia). **MAS:** zero bridge `NfeDfeRecebido → Transaction(type='purchase')`. Manifestação fiscal apenas (confirmar/desconhecer/ciência) — não vira compra na operação |
| Fiscal Cockpit | `Modules/Fiscal/Http/Controllers/{NfeCockpitController, DfeController, CockpitController}` | **Tela leitura DF-e existe** (pendentes/confirmadas/desconhecidas/naoRealizadas + valor pendente agregado). É o plug-point lógico do botão "Importar XML" do protótipo |
| Manufacturing usa compras? | `Modules/Manufacturing/Services/ProductionService.php:62,106,135,162` | **NÃO** — usa `production_purchase` (consumo BOM), tipo DIFERENTE de `purchase` operacional. Sem acoplamento direto |
| Acoplamento cross-módulo `Purchase` | grep `Modules/**/*.php` por "compras/Compras" | só TeamMcp (tests não-relacionados, scope permissions) |
| Protótipo canon visual | `D:/oimpresso.com/public/cowork-preview/erp-shell-v2/compras-page.{jsx,css}` + `Compras.html` | **existe** — FSM 6 estágios (rascunho/pedido/transito/recebido/conferido/pago), 4 KPIs cockpit (aberto/transito/mês/fornec), drawer detalhe com 4 abas, botão "↓ Importar XML" + "+ Nova compra" no header. Mock 7 compras COMP-28XX, supplier dict, fornecedores SP. CSS escopado em `.compras-root` |
| Charter/SPEC Compras | Glob `memory/requisitos/Compras/**` + `memory/decisions/proposals/*compras*` + `memory/sessions/*compras*` | **0 hits — ausente** |
| Pegadinha catalogada Compras | grep proibicoes.md/decisions/requisitos/Infra/PEGADINHA-* | **0 hits específicos**, mas pegadinhas gerais (multi-tenant, MWART, FSM, Cowork bundle) se aplicam |

### 1.2 Métodos do `PurchaseController` legacy (15 públicos)

| Método | Linha | Função |
|---|---|---|
| `index()` | 61 | Lista (dual Blade Yajra DataTable + Inertia v=2 early-return desde hot-fix #601) |
| `create()` | 348 | Form criação (Blade) |
| `store()` | 475 | Persiste transaction type=purchase + lines + payment + dispara `PurchaseCreatedOrModified` event |
| `show()` | 628 | Detalhe (Blade) |
| `edit()` | 828 | Form edição (Blade) |
| `update()` | 1041 | Atualiza purchase (Blade) |
| `destroy()` | 1178 | Deleta + reverte estoque |
| `getSuppliers()` | 1276 | Autocomplete fornecedor |
| `getProducts()` | 1323 | Autocomplete produto |
| `getPurchaseEntryRow()` | 1427 | Linha dinâmica modal Blade |
| `importPurchaseProducts()` | 1509 | **Excel CSV** (NÃO XML NF-e!) — upload planilha SKU/qty/cost |
| `getPurchaseOrderLines()` | 1631 | Lookup linhas de PO existente |
| `checkRefNumber()` | 1675 | Validação ref-no único |
| `printInvoice()` | 1708 | Imprime |
| `updateStatus()` | 1769 | Muda status compra (received/ordered/pending) |

**Achado-chave:** `importPurchaseProducts` legacy importa **planilha Excel** (CSV/XLS), NÃO XML NF-e. Botão "Importar XML" do protótipo é **funcionalidade NOVA** que precisa puxar de `nfe_dfe_recebidos` (NfeBrasil) ou parser XML standalone.

### 1.3 Conclusão Fase 1

- **Caminho A** ("renomear views Blade→React no UltimatePOS core") = funciona pra cosmético mas mantém `PurchaseController` com 1769 linhas — **rejeitar**.
- **Caminho B puro** ("`Modules/Compras/` greenfield com tabela própria `purchases`") = quebra Observer Financeiro (espera Transaction.type=purchase), quebra Fiscal/SPED (lê transactions), duplica TransactionUtil — **rejeitar custo**.
- **Caminho B HÍBRIDO recomendado:**
  - Cria `Modules/Compras/` com Controllers Inertia novos + Pages React + sidebar own.
  - **REUSA** tabela `transactions` (type='purchase'/'purchase_order'/'purchase_return') sem migration nova.
  - **REUSA** `TransactionUtil::getListPurchases` + `createPurchase` lógica (eventualmente extrai pra `Modules/Compras/Services/ComprasService` SEM duplicar). Wrapper inicial chamando `app(TransactionUtil::class)` é OK MVP.
  - **REUSA** `TransactionObserver` Financeiro (já cobre purchase desde Onda 2).
  - **NOVO:** bridge `NfeDfeRecebido → Transaction(type='purchase')` pro botão "Importar XML" do protótipo. Esse é o **maior gap funcional**.
  - **Deprecação Blade `purchase/*`:** 3xx redirect `/purchases → /compras` em fase 2 (igual padrão Financeiro #1283), legacy fica desativado feature-flag mas código permanece pra rollback.

---

## Fase 2 — Pegadinhas APLICÁVEIS

### 2.1 Tabela enxuta (filtrada — 8 pegadinhas que tocam Compras)

| # | Pegadinha | Aplicação a Compras | Fonte canon |
|---|---|---|---|
| 1 | **Multi-tenant Tier 0** — `business_id` global scope, Job passa `$businessId` no constructor, `withoutGlobalScopes` exige comentário SUPERADMIN | Todo ComprasController query precisa scope; ImportXmlJob (bridge DFe→purchase) recebe `$businessId` no constructor; auto-detect supplier via CNPJ scopado por business | [ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md) + skill `multi-tenant-patterns` (Tier A) |
| 2 | **MWART canônico 5 fases** + RUNBOOK obrigatório antes de Edit `.tsx` | Compras é Page Inertia NOVA → F1 pin protótipo → F2 backend baseline (Pest do `store()` legacy ANTES) → F3 visual-comparison V4 → F4 QA biz=1 → F5 cutover canary 7d ROTA LIVRE | [ADR 0104](../decisions/0104-processo-mwart-canonico-unico-caminho.md) + hook `block-mwart-violation.ps1` runtime + workflow `mwart-gate.yml` |
| 3 | **F3 anti-patterns (Cowork → Inertia)** — 6 meta + 15 técnicos catalogados batch Financeiro rejeitado | Compras tem 6 estágios FSM + drawer 4 abas + 4 KPIs — alto risco de virar "tabela genérica TanStack" se Claude pular comparação literal. Wagner reabriu Financeiro 3x antes de obedecer | [`prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`](../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md) |
| 4 | **Cowork bundle aplicar INTEIRO 1ª vez (não cherry-pick)** | `compras-page.css` deve ser copiado INTEIRO pra `resources/css/cowork-compras-bundle.css` ANTES de qualquer customização Inertia. Cherry-pick = 3-5 reabertas (lição PR #1085→#1091→#1092 Financeiro 18/mai) | [`memory/reference/feedback-cowork-bundle-aplicar-inteiro.md`](../reference/feedback-cowork-bundle-aplicar-inteiro.md) — Tier 0 |
| 5 | **`transactions` polimórfica NÃO pode virar tabela própria** | Decidido caminho B híbrido (Fase 1 §1.3): reusar `transactions` evita: (a) quebrar TransactionObserver Financeiro (já cobre purchase), (b) duplicar TransactionUtil (3K+ linhas), (c) quebrar `purchase_lines` FK em Fiscal/SPED, (d) quebrar Manufacturing `production_purchase` (parente). Migration nova só pra coluna `compras_xml_dfe_id` em `transactions` se precisar link DFe | inferência inventário Fase 1 + ADR 0094 §5 SoC brutal |
| 6 | **Bridge `NfeDfeRecebido → Transaction(purchase)` é GAP NOVO + risco de concorrência import XML** | Hoje `nfe_dfe_recebidos` só serve manifestação fiscal. Botão "Importar XML" do protótipo precisa: (a) ler DFe selecionada, (b) auto-match supplier por CNPJ, (c) auto-criar Transaction type=purchase + lines, (d) marcar DFe como "importada→compra" (campo novo `transaction_id` em `nfe_dfe_recebidos`). Concorrência: 2 users importando mesmo DFe simultâneo → UNIQUE em (business_id, dfe_id) ou advisory lock. Idempotência: re-import detecta `transaction_id` existente | gap detectado Fase 1 §1.1; ADR 0093 multi-tenant pra `$businessId` no Job |
| 7 | **Larissa biz=4 ROTA LIVRE — atalhos teclado + monitor 1280px + persona não-técnica** | Protótipo já tem `kbd /` pra busca; replicar. Resolution 1280px = densidade alta (drawer não pode passar de 480px width). Larissa não usa termos técnicos: "Recebido", "Conferido" PT-BR (já no protótipo). Smoke real biz=1 obrigatório ([ADR 0101](../decisions/0101-tests-business-id-1-nunca-cliente.md)) — biz=4 canary 7d depois | brief Larissa + protótipo + ADR 0101 |
| 8 | **Inertia::defer DEFAULT em props caras** | `ComprasController::index` retorna 4 KPIs agregados (`aberto`/`transito`/`mes`/`fornec`) + lista paginada → toda agregação via `Inertia::defer(fn () => $this->buildKpisPayload(...))`. Frontend wrap em `<Deferred>`. Pattern obrigatório [RUNBOOK-inertia-defer-pattern.md](../requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md). DfeController:36 já usa pattern — copiar | proibicoes.md "Sempre fazer" §`Inertia::defer DEFAULT` |

### 2.2 Pegadinhas NÃO aplicáveis (filtradas fora — pra Wagner confirmar)

- FSM Pipeline ADR 0143 — só aplica a `transactions.current_stage_id` em pipeline Sells. Compras tem FSM próprio de 6 estágios no protótipo (rascunho→pago) — provavelmente coluna `status` de `transactions` legacy (`ordered`/`pending`/`received`), não pipeline live. **Verificar com Wagner** se decisão futura é integrar FSM canon ou manter `status` simples.
- Junction NTFS Windows — só toca dev local Wagner, não migração.
- Format_date +3h shift — relevante só pra render de datas legadas; Compras nova usa `format_now_local`.
- NFe sequencial preservado — Compras é entrada (DFe recebida), não emissão. Não toca.

### 2.3 Observação separada (NÃO catalogada formalmente — atenção)

- **Hot-fix PurchaseController #601 (linhas 73-79):** padrão "Inertia ANTES de AJAX via X-Inertia header". Ao deletar PurchaseController, garantir `/purchases?v=2` ainda responde 301 → `/compras` (não 500 por método ausente).
- **`PurchaseCreatedOrModified` event** (linha 25) — listener atual provavelmente em Manufacturing ou notification. Antes de extrair, grep `Listeners/*PurchaseCreatedOrModified*` pra mapear quem escuta. Migration NÃO pode quebrar listeners existentes.

---

## Fase 3 — Plug-points concretos

### 3.1 Estrutura nova `Modules/Compras/` (espelhando Financeiro/NfeBrasil)

| Peça | Arquivo a criar | Ação | Reusa |
|---|---|---|---|
| `module.json` + service provider | `Modules/Compras/module.json`, `Providers/ComprasServiceProvider.php`, `Providers/RouteServiceProvider.php` | skill `criar-modulo` scaffold | — |
| Controller Index (lista + cockpit) | `Modules/Compras/Http/Controllers/ComprasController.php` | `index()` retorna `Inertia::render('Compras/Index', [filters, kpis (defer), rows (defer)])`. Method `buildRowsPayload()` chama `app(TransactionUtil::class)->getListPurchases($business_id)` + filtra. `buildKpisPayload()` agrega 4 KPIs (aberto/transito/mes/fornec) | `TransactionUtil::getListPurchases:4893` |
| Controller Show (drawer detalhe) | `ComprasController::show($id)` | retorna prop `compra` com lines+payments+timeline. Reusa `Transaction::with('purchase_lines','payment_lines')->find($id)` | model `Transaction` |
| Controller Store/Update | `ComprasController::store()`, `update()` | MVP: wrapper que chama lógica `PurchaseController::store` extraída pra `ComprasService::criar(array $payload)`. Service novo encapsula lógica legacy inline | extrair de `PurchaseController:475-627` |
| Service novo | `Modules/Compras/Services/ComprasService.php` | encapsula `criar/atualizar/deletar/atualizarStatus`. Internamente delega `TransactionUtil` enquanto não refatora | `TransactionUtil` |
| **Bridge XML NF-e (GAP NOVO)** | `Modules/Compras/Services/ImportarDfeComoCompraService.php` + `Modules/Compras/Jobs/ImportarDfeComoCompraJob.php` (com `$businessId` constructor) | (1) recebe `NfeDfeRecebido::id`, (2) auto-match supplier via `Contact::where('tax_number', $dfe->cnpj_emitente)`, (3) cria Transaction type=purchase via `ComprasService::criarFromDfe()`, (4) atualiza `nfe_dfe_recebidos.transaction_id` (coluna NOVA — migration). UNIQUE compound (business_id, transaction_id) pra idempotência | model `NfeDfeRecebido`, `NfeDfeItem`, `Contact`, `Transaction` |
| Migration nova | `Modules/Compras/Database/Migrations/2026_05_22_000000_add_transaction_id_to_nfe_dfe_recebidos.php` | adiciona coluna `transaction_id` nullable FK → `transactions(id)` em `nfe_dfe_recebidos`. Down() drop column | ADR 0093 multi-tenant — `business_id` já existe na tabela |
| Page React principal | `resources/js/Pages/Compras/Index.tsx` | F1: pin literal de `public/cowork-preview/erp-shell-v2/compras-page.jsx`. F2: adapta state pra props Inertia. FSM 6 estágios + 4 KPIs + drawer 4 abas + botão "Importar XML" abre modal listando DFe pendentes | protótipo canon |
| Charter Page | `resources/js/Pages/Compras/Index.charter.md` | OBRIGATÓRIO antes do `.tsx` (skill `charter-write`). Targets UX: drawer ≤480px, FSM stepper visível >1280px, atalho `/` busca, smoke biz=1 | skill `charter-write` |
| CSS bundle | `resources/css/cowork-compras-bundle.css` | COPIAR INTEIRO `compras-page.css` (proibicoes.md Design System §Tier 0 Cowork bundle inteiro). Escope `.compras-root` já existe | bundle canon |
| Rotas | `Modules/Compras/Routes/web.php` | `Route::resource('compras', ComprasController::class)` + `POST /compras/importar-dfe/{dfeId}` (chama ImportarDfeComoCompraJob). Middleware `['web','SetSessionData','auth','language','timezone','AdminSidebarMenu','CheckUserLogin']` (proibicoes.md "Sempre fazer") | — |
| Sidebar entry | `Modules/Compras/Http/Controllers/DataController.php::modifyAdminMenu()` | publica dropdown "Compras" próprio. Grupo visual "Operação" via `SIDEBAR_GROUPS` em `resources/js/Components/cockpit/Sidebar.tsx` (NÃO `Menu::dropdown` cross-módulo) | skill `sidebar-menu-arch` |
| Permissions Spatie | `Modules/Compras/Database/Seeders/PermissionsSeeder.php` | suffix `#{biz}`: `compras.view`, `compras.create`, `compras.edit`, `compras.delete`, `compras.import_xml`. `Role::firstOrCreate(['name' => "{$role}#{$bizId}", ...])` (pegadinha hotfix #624) | ADR 0093 + proibicoes.md FSM §Roles Spatie |
| Pest baseline F2 | `Modules/Compras/Tests/Feature/ComprasIndexTest.php` + `ComprasStoreTest.php` + `ImportarDfeComoCompraTest.php` + `MultiTenantIsolationTest.php` | biz=1 NUNCA biz=4 ([ADR 0101](../decisions/0101-tests-business-id-1-nunca-cliente.md)). `phpunit.xml` precisa registrar `<testsuite name="Modules"><directory>./Modules/Compras/Tests</directory></testsuite>` (proibicoes.md §"Não criar Tests sem registrar") | — |
| SPEC | `memory/requisitos/Compras/SPEC.md` | mínimo US-COM-001 (lista cockpit), US-COM-002 (criar compra manual), US-COM-003 (importar DFe), US-COM-004 (deprecar /purchases legacy). Estimates recalibrados ADR 0106 (10x IA-pair codável + relógio mundo real pra canary 7d) | [ADR 0106](../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) |
| BRIEFING | `memory/requisitos/Compras/BRIEFING.md` | template `BRIEFING-TEMPLATE.md`. Skill `brief-update` Tier B auto-atualiza por PR | [BRIEFING-TEMPLATE](../requisitos/_DesignSystem/BRIEFING-TEMPLATE.md) |
| ADR migração | `memory/decisions/proposals/compras-modulo-greenfield-hibrido.md` | Nygard. Status `proposed`. Decisão: caminho B HÍBRIDO. Supersedes nenhuma; complementa ADR 0104. Justifica reuso `transactions` polimórfica vs greenfield total | apêndice ADR 0094 §5 SoC brutal |
| INFRA contract (se rotas mexerem) | seção `## Infra Contract` no PR body | curl literal `https://oimpresso.com/compras` HTTP 200, regression adjacent `/purchases` HTTP 301 + `/sells` HTTP 200 | proibicoes.md §"Claim sem evidência" — Hook bloqueador |

### 3.2 Plug-points marcados ⚠️ (não existem — criar como sub-tarefa)

- ⚠️ **`ComprasService`** — não existe. Criar como wrapper de `TransactionUtil` no MVP, refatorar pra service nativo depois.
- ⚠️ **`ImportarDfeComoCompraService` + Job** — não existe. Esse é o **único componente verdadeiramente novo** (resto é mover/embrulhar). Maior risco técnico da migração.
- ⚠️ **Coluna `nfe_dfe_recebidos.transaction_id`** — não existe. Migration nova obrigatória. UNIQUE `(business_id, transaction_id)` pra idempotência.
- ⚠️ **Page `Compras/Index.charter.md`** — não existe. Bloqueador `mwart-gate.yml` se Edit `.tsx` antes.
- ⚠️ **Bundle CSS `cowork-compras-bundle.css`** em `resources/css/` — não copiado. Tier 0 obrigatório aplicar inteiro 1ª vez.

---

## Fase 4 — Checklist pré-código

```markdown
## Pré-código checklist — Módulo Compras greenfield híbrido

### Antes de Edit/Write
- [ ] **DECISÃO ARQUITETURAL WAGNER (bloqueador):** confirma caminho B HÍBRIDO?
      (greenfield `Modules/Compras/` + reusa `transactions` polimórfica + reusa Observer Financeiro + cria bridge nova DFe→purchase)
      Alternativas rejeitadas neste doc: A (renomear views só), B-puro (tabela própria).
- [ ] **CLIENTE COMO SINAL (ADR 0105):** Larissa biz=4 ROTA LIVRE usa compras semanalmente HOJE? Reporta dor real?
      Se NÃO → hipótese vira ADR feature wish, não US ativa. Compras pode esperar.
- [ ] Ler `memory/requisitos/Compras/SPEC.md` — criar com US-COM-001..004
- [ ] Ler `memory/requisitos/Compras/RUNBOOK-compras-index.md` — criar (bloqueador MWART F1)
- [ ] ADR proposta `memory/decisions/proposals/compras-modulo-greenfield-hibrido.md` — Nygard, Wagner aprova promoção a accepted
- [ ] Feature flag `compras_module_enabled` (per-business via UltimatePOS package_details, NÃO hardcode `if (biz=4)` — proibicoes.md Multi-tenant §3 camadas)
- [ ] Schema migration: `nfe_dfe_recebidos.transaction_id` nullable FK + UNIQUE compound (business_id, transaction_id)

### Pegadinhas a respeitar (filtradas, ver Fase 2)
- [ ] Multi-tenant Tier 0 — `business_id` global scope + Job `$businessId` constructor (ADR 0093)
- [ ] MWART 5 fases — RUNBOOK antes do .tsx, F1 pin literal, F2 Pest baseline, F3 visual-comparison V4, F4 biz=1, F5 canary 7d (ADR 0104)
- [ ] F3 anti-patterns — não virar "tabela genérica TanStack" (LICOES_F3_FINANCEIRO_REJEITADO)
- [ ] Cowork bundle COPIAR INTEIRO `compras-page.css` antes de customizar (proibicoes Tier 0)
- [ ] Reusar `transactions` polimórfica — NÃO criar tabela `purchases` própria
- [ ] Bridge DFe→Purchase: idempotência via UNIQUE + advisory lock anti-concorrência
- [ ] Larissa biz=4 — atalho `/` busca, drawer ≤480px, smoke biz=1
- [ ] `Inertia::defer` DEFAULT em props KPI agregadas (RUNBOOK-inertia-defer-pattern)

### Pontos de plugue (ordem sugerida pra execução IA-pair)
- [ ] **Wave 1 backend scaffold:** `Modules/Compras/` scaffold via skill `criar-modulo` (module.json + providers + Routes + DataController sidebar + Tests dir registrada em phpunit.xml) — 2-4h IA-pair
- [ ] **Wave 2 SPEC + charter:** `SPEC.md` + `Index.charter.md` + ADR proposta — 2h IA-pair
- [ ] **Wave 3 backend wrapper:** `ComprasController::index/show/store/update` chamando `TransactionUtil` + Pest baseline F2 do `store()` legacy (espelha comportamento) — 4-6h IA-pair
- [ ] **Wave 4 bundle CSS + F1 pin:** copy `cowork-compras-bundle.css` inteiro + `resources/js/Pages/Compras/Index.tsx` pin literal protótipo — 2h IA-pair
- [ ] **Wave 5 F3 visual-comparison V4:** 15 dimensões + screenshot Wagner aprova ANTES de F4 — 4-6h (Cowork ↔ Claude Code loop)
- [ ] **Wave 6 bridge XML NF-e (NOVO):** `ImportarDfeComoCompraService` + Job + migration `transaction_id` + Pest + UI modal "Importar XML" lista DFe pendentes — 8-12h IA-pair (maior risco)
- [ ] **Wave 7 Pest multi-tenant + idempotência:** `MultiTenantIsolationTest` biz=1, `ImportarDfeComoCompraTest` cobre re-import + race condition — 3-4h IA-pair
- [ ] **Wave 8 deprecação legacy:** 301 `/purchases → /compras` (padrão #1283 Financeiro), feature flag esconde menu legacy quando `compras_module_enabled`, ADR deprecação `PurchaseController` — 3-4h IA-pair
- [ ] **Wave 9 cutover canary 7d:** biz=1 → biz=4 ROTA LIVRE, monitor, avisar Larissa prévio (ADR 0104 F5) — RELÓGIO MUNDO REAL (7 dias canary + 30 dias monitor)

### Smoke pós-deploy (R1 PROTOCOLO WAGNER — evidência curl literal não narração)
- [ ] biz=1 — `curl -sv https://oimpresso.com/compras 2>&1 | grep '^< HTTP'` mostra `200`
- [ ] biz=1 — criar compra manual end-to-end (3 lines, fornecedor, payment due) + verificar `fin_titulos` foi criado (Observer Financeiro)
- [ ] biz=1 — importar DFe mock (NSU sintético) → Transaction type=purchase persistido + `nfe_dfe_recebidos.transaction_id` populado
- [ ] biz=1 — regression adjacent: `/purchases` retorna 301 → `/compras`, `/sells` segue 200, `/financeiro` segue 200
- [ ] biz=1 — Pest `php artisan test --filter=Compras` 100% verde + multi-tenant isolation passa
- [ ] biz=1 — Chrome MCP screenshot `/compras` confirma protótipo replicado (FSM 6 estágios visíveis, KPIs render, drawer abre)
- [ ] biz=4 ROTA LIVRE (canary 7d) — Larissa avisada via WhatsApp, monitor `php artisan jana:health-check`, rollback feature flag se incident
- [ ] CI `gh pr checks <PR>` 100% verde ANTES de propor merge (proibicoes.md §"Sempre fazer" última entry)
- [ ] Hook `post-merge-ui-smoke-required.ps1` cumprido — Chrome MCP screenshot pós-deploy obrigatório (proibicoes.md §"Claim sem evidência")

### Estimativa total (recalibrada ADR 0106 — fator 10x IA-pair + margem 2x)
- **Codável IA-pair (Waves 1-8):** ~30-45h IA-pair (≈ 3-5 dias agressivos)
- **Humano-limitado (Wave 9 canary 7d + monitor 30d):** relógio mundo real, NÃO acelera
- **Total calendar:** 4-5 dias dev + 7d canary biz=1 + canary 7d biz=4 + 30d monitor = ~6-7 semanas calendar pra "live prod estabilizado em ROTA LIVRE"
```

---

## Notas finais

- **NÃO foi criado:** task MCP, commit, código, migration, ADR promovida.
- **Foi criado APENAS:** este documento sessão.
- **Decisão pendente Wagner (bloqueador único):** confirmar caminho B HÍBRIDO + autorizar Wave 1 scaffold ou parar.
- **Maior risco técnico detectado:** Wave 6 (bridge XML NF-e DFe→Purchase) — é o **único componente novo real**. Concorrência import + auto-match supplier + idempotência re-import + UI modal "Importar XML" listando DFe pendentes. Resto é embrulhar TransactionUtil existente. Se Wagner quiser MVP rápido, Wave 6 pode ser fase 2 (ship Compras CRUD primeiro, importar XML depois).
- **Decisão arquitetural secundária:** FSM 6 estágios do protótipo (rascunho→pedido→transito→recebido→conferido→pago) integra com FSM Pipeline canônico ADR 0143? Ou mantém `transactions.status` simples? Recomendação: simples no MVP, integração FSM canônico em fase 3.

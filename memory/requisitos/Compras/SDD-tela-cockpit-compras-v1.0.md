---
id: requisitos-compras-sdd-tela-cockpit-compras-v1-0
slug: compras-sdd
title: "SDD — Cockpit de Compras (domínio Compras)"
type: sdd
module: Compras
status: ativo
owner: W
version: 1.1.0
last_updated: "2026-11-05"
related_docs:
  - SPEC.md
  - BRIEFING.md
  - CAPTERRA-FICHA.md
  - CAPTERRA-INVENTARIO.md
  - AUDIT-SENIOR-2026-05-25.md
  - DISCOVERY-LARISSA-COMPRAS.md
  - RUNBOOK-compras-index.md
  - SUPERFICIE.md
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0101-tests-business-id-1-nunca-cliente
  - 0104-processo-mwart-canonico-unico-caminho
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0141-skill-migracao-blade-react
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0351-sdd-from-source
related_us:
  - US-COM-001
  - US-COM-002
  - US-COM-003
  - US-COM-004
  - US-COM-005
  - US-COM-006
  - US-COM-007
  - US-COM-008
  - US-COM-009
  - US-COM-011
  - US-COM-013
  - US-COM-016
  - US-COM-023
  - US-COM-026
  - US-COM-027
  - US-COM-030
  - US-COM-031
  - US-COM-032
  - US-COM-034
---

# SDD — Software Design Document · Cockpit de Compras (domínio Compras)

> **Primeiro SDD criado do zero pelo agent [`sdd-from-source`](../../../.claude/agents/sdd-from-source.md)**
> ([ADR 0351](../../decisions/0351-sdd-from-source.md)), chip **S1** da Onda 1 do
> [passo 5](../_Governanca/programa-ondas/passo-5-sdd-por-modulo.md). O SDD do Produto
> (o único que existia) nasceu à mão; o ramo *"módulo sem SDD"* nunca tinha rodado.
>
> **Escopo:** o domínio **Compras** — a tela `Compras/Index` (`/compras`) **e** o trilho
> `/purchases/*` do qual ela depende por convergência C1. O SDD é do **MÓDULO/família**,
> nunca da tela ([ADR 0351](../../decisions/0351-sdd-from-source.md) Fase 2.1).
>
> **Triangulação — 3 fontes de 4** (a 4ª declarada ausente, §0.1).
>
> **v1.1 (2026-11-05) — o fluxo de compra ponta a ponta entra aqui, não em arquivo novo.** O pedido
> da auditoria de 2026-11-05 era um `SDD-fluxo-compras-v1.0.md` separado. Ele ficaria paralelo a
> este: o [SDD-TEMPLATE](../_DesignSystem/SDD-TEMPLATE.md) manda **1 SDD por domínio** e proíbe
> `FLUXO-*.md` ao lado dele (regra 3), e este documento já declara escopo de domínio. O fluxo
> requisição → cotação → pedido (alçada) → recebimento → título a pagar → devolução está em
> **§5.5** (arquitetura, com `arquivo:linha`), **§5.6** (o que é só serviço) e **§5.7** (decisões
> em aberto); os casos de uso em **§6.2** (`CU-CFX`). O nome `SDD-tela-cockpit-*` é legado e não
> muda (o template proíbe renomear sem plano de realocação).

---

## 0. Base empírica

<!-- curado: foto que envelhece -->

### 0.1 As fontes que sustentam este documento — e a que falta

| # | Fonte | Resolvida em | Estado |
|---|---|---|---|
| 1 | **Documentação canon** | [`SPEC.md`](SPEC.md) (21 US) · [`Index.charter.md`](../../../resources/js/Pages/Compras/Index.charter.md) · [`AUDIT-SENIOR-2026-05-25.md`](AUDIT-SENIOR-2026-05-25.md) · [`memory/dominio/compras.md`](../../dominio/compras.md) | ✅ |
| 2 | **React/Laravel atual** | `Compras/Index.tsx` + `ComprasController` + `ComprasService` + `ListarComprasRequest` | ✅ |
| 3 | **Blade AdminLTE legada** | `resources/views/purchase/index.blade.php` + `purchase_table.blade.php` + o `addColumn('action')` do `PurchaseController@index` | ✅ — **e o operador REALMENTE a alcança** (§0.2) |
| 4 | **Delphi / Office Comercial** | — | ❌ **AUSENTE** |

> ⚠️ **Gap declarado (fonte 4).** `find memory -iname "*ANTI-REGRESSAO*"` devolve **2 arquivos, ambos do Produto** — Compras não tem `ANTI-REGRESSAO-*.md` destilado do manual WR Comercial. A triangulação aqui é de **3 fontes**: mais barata, e com **contrato de paridade mais fraco**. **Nada de contrato legado Delphi foi inventado neste documento** — onde a paridade é afirmada, a referência é a Blade AdminLTE viva (fonte 3), não o Delphi. Se [W] quiser o eixo Delphi, ele precisa nascer como `ANTI-REGRESSAO-compras-legacy.md` antes.

### 0.2 A Blade de referência — resolvida, não assumida

A armadilha da Blade homônima ([ADR 0351](../../decisions/0351-sdd-from-source.md) Fase 1.1) **existe aqui e está ativa**: `/purchases` é **dual-path**. `PurchaseController@index` decide em 3 ramos (`app/Http/Controllers/PurchaseController.php:74 / :80 / :237`):

1. `X-Inertia` **ou** `?v=2` → `indexInertia()` → `Purchase/Index.tsx` (React)
2. `request()->ajax()` → JSON DataTables (é o que a Blade consome)
3. senão → `view('purchase.index')` — **a Blade legada**

O cockpit `/compras` **manda o operador pro ramo 3 de propósito**: os 4 botões de export do `Toolbar` chamam `openExportBlade()`, que faz `window.open('/purchases', ...)` (`Compras/Index.tsx` — busca: `grep -n "openExportBlade" resources/js/Pages/Compras/Index.tsx`). `window.open` é navegação nova: **sem header `X-Inertia`, sem `?v=2`** → cai na Blade. Logo a Blade **não é fóssil**: é o destino de CSV/Excel/PDF/Imprimir do cockpit hoje.

> **Atualização 2026-10-13:** o cutover pelo menu (#9095, D1 [W] 2026-10-07) mudou o ramo 3 — GET comum em `/purchases` passou a abrir a Page React, e a Blade só sai com `?classico=1`. O parágrafo acima vale para o código até essa data. Desde 2026-10-13 o `openExportBlade` abre `/purchases?classico=1` (contrato UC-CMP-11).

**A Blade de referência deste SDD é `resources/views/purchase/index.blade.php` + `purchase_table.blade.php`**, com o menu de Ações vindo do `addColumn('action')` do ramo 2. As demais (`create`/`edit`/`show`) pertencem ao trilho `/purchases` e só entram aqui quando o cockpit delega.

### 0.3 O que o benchmark expôs — ponteiro, não cópia

Números vivem no dono, não aqui ([proibicoes §5](../../proibicoes.md) 2026-07-17). **Recibo datado:** em **2026-07-03** o [`BRIEFING.md`](BRIEFING.md) §Score registrava três réguas divergentes — `module-grade v3` (higiene), `CAPTERRA design` (UX do protótipo) e `CAPTERRA capacidade` (features vs líderes) — com a **capacidade sendo a mais baixa das três**. A leitura que a própria BRIEFING faz: *"higiene e design escondem que o motor de compra não fecha o ciclo — importa/manifesta (via NfeBrasil) mas não vira compra, não casa PO, não recebe parcial, não concilia."* Para o valor atual de cada régua, **abrir a [`CAPTERRA-FICHA.md`](CAPTERRA-FICHA.md) / rodar `php artisan module:grade Compras`** — não reproduzir o número aqui.

---

## 1. Visão geral

<!-- derivado: re-rodável do fonte -->

### 1.1 O que é

**Compras** é o lado-entrada da espinha `transactions` do UltimatePOS: `type='purchase'` (+ `purchase_order`, `purchase_return`). O módulo `Modules/Compras` **não** é greenfield puro — é **Caminho B híbrido** ([SPEC §3](SPEC.md)): greenfield só em Controller/Service/Page, **reusando** `transactions` + `TransactionUtil` + o `TransactionObserver` do Financeiro.

### 1.2 Família de telas — 1 tela própria, 1 trilho vizinho

| Rota | Componente | Dono | Papel |
|---|---|---|---|
| `/compras` | `Compras/Index.tsx` | `Modules/Compras` | **cockpit** — lista + 4 KPIs + drawer 5 abas |
| `/compras/{id}/detalhe` | (JSON) | `Modules/Compras` | endpoint do drawer (partial reload) |
| `/purchases` | `Purchase/Index.tsx` **ou** Blade | núcleo (`app/`) | lista legada + exports + Ações |
| `/purchases/create\|{id}/edit` | `Purchase/Create\|Edit.tsx` | núcleo | **CRUD** — o cockpit delega (C1) |

> **Convergência C1** ([ADR proposta `compras-purchase-convergencia-c1`](../../decisions/proposals/compras-purchase-convergencia-c1.md), vigente): `/compras` **não** ganha `Create.tsx`/`Edit.tsx`. Ele **delega** por `router.visit('/purchases/...')`. Isso torna o trilho `/purchases` **parte do contrato do cockpit** — e é por isso que ele entra neste SDD.

### 1.3 Verticais

- **horizontal** — todo business que compra de fornecedor.
- **vestuário (Larissa @ ROTA LIVRE biz=4)** — grade tam×cor por modelo pai (US-COM-005), que vive em `Purchase/Create.tsx`, não no cockpit.
- **Estado de adoção:** o cockpit está **live no código** e, no fechamento da [`BRIEFING.md`](BRIEFING.md) (2026-07-03), **não estava em produção nem canary pra nenhum business**. Confirmar antes de citar: [`config/governance/module_clients.yaml`](../../../config/governance/module_clients.yaml) (US-COM-010 registra que a entrada `Compras:` ainda não existe lá).

---

## 2. Público-alvo e personas

<!-- curado: foto que envelhece -->

| # | Persona | Onde ela dói |
|---|---|---|
| **P1** | **Larissa — ROTA LIVRE (biz=4, vestuário)**, 1280px, não-técnica | entrada de lote: 50+ modelos × 4 tamanhos × 3-5 cores linha-a-linha no Blade ([`DISCOVERY-LARISSA-COMPRAS.md`](DISCOVERY-LARISSA-COMPRAS.md)). Meta declarada no [SPEC §2](SPEC.md): ≤2min/modelo contra ≥10min hoje |
| **P2** | **Wagner — WR2 SC (biz=1)** | operador-dono e **cobaia segura** — todo Pest roda em biz=1, **nunca** biz=4 ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)) |
| **P3** | **Conferente / operador de estoque restrito a uma loja** | é a persona do `permitted_locations` — hoje o cockpit **não a enxerga** (§5.4.1 · CU-COM-05). ⬜ **não-verificada com [W]**: nenhum documento canon do módulo a nomeia; ela foi **inferida do código do legado**, não de cliente reportando |

> ⚠️ **P3 precisa de validação [W].** Ela é derivada de `permitted_locations()` existir e ser aplicada nas duas implementações de `/purchases`. Se nenhum business real usa localização restrita, CU-COM-05 vira Non-Goal — decisão de [W], não do agente.

---

## 3. Governança aplicável — o Tier 0 que morde AQUI

<!-- derivado: re-rodável do fonte -->

| Regra | Onde morde no Compras |
|---|---|
| **Multi-tenant Tier 0** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) | `ComprasController@index` deriva `business_id` de `auth()->user()`, **não** de `session()` (US-COM-007) + `abort_if(<=0)` + cross-check session×auth. `ComprasService` recebe `$businessId` explícito em **todos** os métodos |
| **JOIN scoped** (R1 do [`AUDIT-SENIOR`](AUDIT-SENIOR-2026-05-25.md)) | `TransactionUtil::getListPurchases` escopa `contacts.business_id` **e** `BS.business_id` dentro da closure do JOIN — sem isso o filtro `?q=` casava linha de outro tenant (US-COM-009) |
| **REGRA MESTRE valor/estoque** | a entrada da compra **move estoque** (`purchase_lines` → `variation_location_details.qty_available`) e grava `final_total`. Toda mudança no caminho exige dupla-confirmação + antes→depois ([proibicoes](../../proibicoes.md)) — CU-COM-08 `[V0]` |
| **Testes só no CT 100 / CI** ([ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md)) | a lane é `.github/workflows/compras-pest.yml` (MySQL real, biz=1 + biz=2) |
| **`biz=1`, nunca `biz=4`** ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)) | o adversário canônico do módulo é **biz=99** (`MultiTenantTest`) |
| **Dicionário de domínio** ([ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md) G-4) | [`memory/dominio/compras.md`](../../dominio/compras.md) é fonte única: `ordered`=pedido · `pending`=aguardando · `received`=**recebida (só aqui entra estoque)**. Proibido "entrada de nota" pra recebimento; proibido "ordem de compra" |

---

## 4. Design system aplicável

<!-- derivado: re-rodável do fonte -->

- **Shell:** `AppShellV2` com breadcrumb único `Compras` (`Compras/Index.tsx`, `ComprasIndex.layout`).
- **Bundle:** `resources/css/cowork-compras-bundle.css`, aplicado **INTEIRO** na 1ª vez (Tier 0 — [proibicoes §Design System](../../proibicoes.md)). Escopo por wrapper `.compras-root`, não por `@scope`.
- **Protótipo âncora:** `prototipo-ui/cowork/Wagner/compras-page.jsx` (declarado em `related_prototype` do charter). O `proto-baseline` da tela está **ausente** — `npm run screen:files -- Compras/Index` acusa `proto-baseline ✗`.
- **Padrão de tela:** cockpit lista+detalhe (drawer lateral sobre grid), família do [PT-01 Lista](../_DesignSystem/padroes-tela/PT-01-Lista.md).
- **`ux_targets` (do charter, §UX targets):** first-paint ≤800ms · 0 erro JS no console · KPI legível em ≤5s · 25 linhas sem scroll horizontal em 1280px · drawer abre ≤200ms.

---

## 5. Arquitetura

### 5.1 Visão em camadas

<!-- derivado: re-rodável do fonte -->

```
GET /compras
  → [web, auth, SetSessionData, language, timezone, AdminSidebarMenu, CheckUserLogin, throttle:60,1]
  → ComprasController@index(ListarComprasRequest)
      ├─ authorize()        → permission `compras.view`  (senão 403)
      ├─ rules()            → whitelist q/stage/sort/dir/per_page/compra_id
      ├─ business_id        ← auth()->user()->business_id   (NÃO session — US-COM-007)
      ├─ abort_if(<=0, 403) + cross-check session×auth
      └─ Inertia::render('Compras/Index', [...])
             filters, selected_id, permissions            ← eager
             kpis          ← defer → ComprasService::calcularKpis
             rows          ← defer → buildRowsPayload → ComprasService::listarCompras → paginate
             summary       ← defer → ComprasService::calcularSummary
             compra_detalhe← defer → ComprasService::buscarDetalhe (só se ?compra_id=)

GET /compras/{id}/detalhe
  → ComprasController@show → can('compras.view') senão 403
                           → ComprasService::buscarDetalhe($id, $businessId)
                           → null ⇒ abort(404)   (404, não 403 — não revelar existência)
                           → response()->json()
```

**Camada de dados:** `ComprasService::listarCompras` **não** monta query própria — envolve `TransactionUtil::getListPurchases($businessId)` num span OTel (`compras.listarCompras`) e aplica por cima: `?q=` (ref_no · contacts.name · contacts.supplier_business_name), `stage` (`transactions.status`) e `orderBy` resolvido pelo `SORT_MAP` (anti-SQLi: só coluna virtual mapeada entra).

### 5.2 Modelo de dados (núcleo)

<!-- derivado: re-rodável do fonte -->

| Tabela | Papel | Onde mora o `business_id` |
|---|---|---|
| `transactions` | a compra (`type='purchase'`) — `status`, `payment_status`, `final_total`, `total_before_tax`, `tax_amount`, `discount_amount`, `shipping_charges`, `pay_term_*`, `location_id`, `contact_id`, `created_by` | coluna própria + `WHERE transactions.business_id` |
| `purchase_lines` | linhas (qty × `purchase_price*`, `lot_number`, `variation_id`) | herda da transaction |
| `variation_location_details` | **saldo por variação/local** — o que a entrada move | por local (que é do business) |
| `contacts` | fornecedor (`primary_role=supplier`) | **escopado DENTRO da closure do JOIN** (R1 · US-COM-009) |
| `business_locations` (`BS`) | local da compra | **escopado DENTRO da closure do JOIN** |
| `transaction_payments` (`TP`) | pagamentos e devoluções (`is_return`) | via transaction |
| `activity_log` | timeline do drawer (Spatie, `Activity::forSubject`) | via subject |
| `fin_titulos` | contas a pagar geradas pelo `TransactionObserver` | do Financeiro |

> **Nota de fronteira:** `Modules/Compras/Database/Migrations` **não existe** — o módulo não tem tabela própria ([`SUPERFICIE.md`](SUPERFICIE.md): 23 arquivos, 0 migrations). Tudo mora na espinha do núcleo. É o Caminho B por desenho, não descuido.
>
> **Atualização 2026-11-05:** a nota acima valia em 2026-07-27. Desde 2026-10-20 o módulo tem
> migrations próprias, para as tabelas dos serviços do fluxo (§5.5): `compras_propostas_preco`,
> `compras_prazo_fornecedor`, `compras_cotacoes`, `compras_aprovacoes_pedido`, `compras_custo_medio`,
> `compras_inspecoes` e `compras_insumo_metodo_custo`, todas em
> `Modules/Compras/Database/Migrations/`. A espinha da compra continua sendo `transactions` + `purchase_lines`.

### 5.3 Fluxos críticos

**F1 · Listar compras no cockpit (`GET /compras`)** <!-- derivado: re-rodável do fonte -->

1. `ListarComprasRequest::authorize()` exige `compras.view` → senão **403**.
2. `rules()` valida a whitelist; **valor fora da whitelist derruba o request** (ValidationException → redirect), não é ignorado.
3. `filtros()` aplica defaults (`stage=all`, `sort=transaction_date`, `dir=desc`, `per_page=25`).
4. `business_id` vem de `auth()`, com `abort_if(<=0)` e cross-check contra a session.
5. `rows` é **deferido**: `ComprasService::listarCompras` → `getListPurchases` (JOIN scoped) → filtros → `orderBy(SORT_MAP[...])` → `paginate(per_page)` → `{data, meta}`.
6. O front (`<Deferred data="rows">`) aplica **por cima** um filtro **client-side** (`filteredRows`) das abas *Todas / A pagar / Rascunhos / Em trânsito* — **sobre a página corrente apenas** (§5.4.2).

**F2 · Abrir o drawer (`?compra_id=` · partial reload)** <!-- derivado: re-rodável do fonte -->

1. Clique na linha → `router.get('/compras', {compra_id}, {only:['compra_detalhe','selected_id']})`.
2. `ComprasService::buscarDetalhe` faz `where('business_id')` **antes** do `where('id')`, `whereIn type ∈ {purchase, purchase_order, purchase_return}`, com `->with([contact, location, purchase_lines(+product,+unit,+variations), payment_lines])`.
3. Monta `lines` (com `line_total = quantity × purchase_price_inc_tax`), `payments`, `timeline` (Spatie, `limit(50)`), `amount_paid` e `amount_due = max(0, final_total − Σ pagamentos)`.
4. `null` ⇒ o Controller responde **404**, nunca 403.
5. O `Drawer.tsx` renderiza **5 abas** (`Resumo · Itens · Documentos · Pagamentos · Histórico`) — ver a divergência aberta em §5.4.3.

**F3 · Agir sobre a compra (menu Ações)** <!-- derivado: re-rodável do fonte -->

`AcoesDropdown.tsx` oferece 9 itens, em 3 regimes de transporte:

| Ação | Transporte | Destino |
|---|---|---|
| Ver · Ver pagamentos | estado local | abre o drawer na aba certa |
| Editar · Atualizar status · Notif. pendente | `router.visit` (Inertia) | `Purchase/Edit.tsx` React (`#status` / `#notify-pending` como âncora) |
| Excluir | `router.delete` | `DELETE /purchases/{id}` + `reload only:[rows,kpis]` |
| Impressão · Rótulos | `window.open` | **Blade legada** (`/purchases/print/{id}`, `/labels/show?purchase_id=`) |
| Reembolso de compra | `window.location.href` | **Blade legada** (`/purchase-return/add/{id}`) |

**F4 · Criar compra (delegação C1)** <!-- derivado: re-rodável do fonte -->

`+ Nova compra` só aparece se `permissions.create` (resolvido no back por `purchase.create`, **não** `compras.create`) → `router.visit('/purchases/create')` → header `X-Inertia` → `Purchase/Create.tsx`. **Não existe** `Pages/Compras/Create.tsx` e o charter proíbe que exista (anti-hook C1).

**F5 · Entrada move estoque e valor** `[V0]` <!-- derivado: re-rodável do fonte -->

`POST /purchases` → `PurchaseController@store` → `ProductUtil::createOrUpdatePurchaseLines` + `updateProductQuantity` → `variation_location_details.qty_available`; o `TransactionObserver` do Financeiro cria `fin_titulos` `type=pagar`. **Este fluxo não passa por `Modules/Compras`** — o cockpit só o observa. O contrato executável dele é `PurchaseCalculoValorEstoqueE2ETest` (US-COM-011).

**F6 · Exportar (bridge Blade)** <!-- derivado: re-rodável do fonte -->

CSV / Excel / PDF / Imprimir chamam o **mesmo** `openExportBlade`, que ignora o formato e abre `/purchases?classico=1` em nova aba (até 2026-10-13, `/purchases` sem o parâmetro — ver a atualização em §0.2) — os 4 botões levam ao mesmo lugar, onde o DataTables da Blade tem os exports nativos. **O filtro corrente do cockpit não viaja** (§5.4.4).

### 5.4 Onde os dois mundos ainda não se conversam

#### 5.4.1 O cockpit não conhece `permitted_locations` nem `view_own_purchase` ⚙️ derivado · medido 2026-07-27

**Varredura contada** (`git grep`, sem `head_limit`): `getListPurchases` aparece em **29 linhas**; descontando testes, docblocks e seeders, os **chamadores reais são 4, em 3 arquivos**:

| Chamador | Escopo aplicado ALÉM de `business_id` |
|---|---|
| `app/Http/Controllers/PurchaseController.php:81` (Blade/AJAX) | `permitted_locations` + `view_own_purchase` + location/supplier/status/data |
| `app/Http/Controllers/PurchaseController.php:247` (`indexInertia`) | idem |
| `Modules/Crm/Http/Controllers/PurchaseController.php:56` (portal do fornecedor) | `contacts.id = crm_contact_id` |
| **`Modules/Compras/Services/ComprasService.php:68`** | **nenhum** |

`git grep -n "permitted_locations" -- Modules/Compras` → **0 ocorrências**. `git grep -n "view_own_purchase" -- Modules/Compras` → **0** (o repo inteiro tem **89**).

**Consequência:** usuário sem `access_all_locations` vê, em `/compras`, compras de locais aos quais não tem acesso — enquanto em `/purchases` (Blade **e** React) vê só os seus. **Não é vazamento cross-tenant** (o `business_id` segue escopado, e `MultiTenantTest` prova) — é perda de escopo **intra-tenant**, na migração. Vira **CU-COM-05** `[must]` `[reg]`.

#### 5.4.2 O filtro de aba é client-side sobre a página corrente ⚙️ derivado

`filteredRows` filtra `rows.data` — as 25 linhas da página. "A pagar" mostra as abertas **daquela página**, e o contador `{filteredRows.length} de {totalRows}` compara maçã (página filtrada) com laranja (total do servidor). O rodapé `ft` soma `filteredRows`; o `SummaryFooter` mostra `summary` (servidor, todas as páginas) — **dois totais diferentes na mesma tela**. O charter **já declara** isso como dívida consciente (*"state client-side; quando server filter for adicionado, vira query string"*), então **não é regressão** — é `[BACKLOG]`.

#### 5.4.3 O drawer 5 abas existe, e o charter diz que não deveria ⚠️ divergência aberta

`Drawer.tsx` declara as 5 abas (`Resumo · Itens · Documentos · Pagamentos · Histórico`) e o backend `show()` **existe** (`Routes/web.php` rota `compras.show` + `ComprasController@show` + prop `compra_detalhe`). O charter tem, em §Non-Goals: *"❌ NÃO renderiza DrawerView 5 tabs — backend `show()` ainda não existe. Wave 6+ habilitam"*.

**Non-Goal é INTENÇÃO — o agente é proibido de corrigir** ([ADR 0351](../../decisions/0351-sdd-from-source.md) Fase 2.6). Fica registrado nos dois lados como **divergência aberta pra [W]**: ou o Non-Goal caducou (o mais provável, já que o `show()` nasceu e a [`BRIEFING.md`](BRIEFING.md) lista o drawer como ✅), ou o drawer excedeu a lei da tela.

#### 5.4.4 A UI emite valores que o próprio contrato do módulo rejeita ⚙️ derivado · medido 2026-07-27

Dois conjuntos incompatíveis **dentro do mesmo módulo**:

| Eixo | O que a UI emite | O que o `ListarComprasRequest` aceita | O que o `ComprasService` sabe fazer |
|---|---|---|---|
| `stage` | `all` · `abertas` · `rascunhos` · `transito` (ids das abas, ao buscar com uma aba ativa) | `all` · `received` · `ordered` · `pending` · `draft` | qualquer string → `where('transactions.status', …)` |
| `sort` | `ref_no` · `contact_name` · `transaction_date` · **`status`** · `final_total` · **`payment_status`** (os 6 `SortHeader`) | `transaction_date` · `ref_no` · `final_total` · `contact_name` | **7** colunas no `SORT_MAP` (inclui `status`, `payment_status`, `location_name`) |

Efeito previsto: buscar com a aba *A pagar / Rascunhos / Em trânsito* ativa, ou clicar em ordenar por **Estágio** / **A pagar**, envia valor fora da whitelist → `ValidationException` → **a listagem não responde 200**. Viram **CU-COM-04** + os UCs `UC-CMP-06`/`UC-CMP-07`.

> **Predição, não veredito.** Isto é leitura de código, não execução — o status vem da lane ([proibicoes §5](../../proibicoes.md) 2026-07-15). Os testes escritos neste PR são **failing-first**; o vermelho, se vier, é o achado.

#### 5.4.5 Dois estágios da UI são inalcançáveis ⚙️ derivado

`STAGES` declara 6 estágios (`rascunho · pedido · transito · recebido · conferido · pago`), mas `CORE_TO_STAGE` só mapeia os 5 status core (`draft · ordered · pending · received · cancelled`). **`conferido` e `pago` não têm origem** — nenhum valor de `transactions.status` produz um deles. É o sintoma da FSM não-persistida que **US-COM-014 já nomeia**; `[BACKLOG]`, não CU novo.

#### 5.4.6 Os exports perdem o filtro ⚙️ derivado

`openExportBlade` ignora o parâmetro `_format` e não propaga `q`/`stage`/`sort` — o operador filtra no cockpit e exporta **a lista inteira** da Blade. `[BACKLOG]`.

### 5.5 Fluxo de compra ponta a ponta

<!-- derivado: re-rodável do fonte · verificado@7d99759 (2026-11-05) -->

> **Fontes, na ordem fixa:** (1) [`SPEC.md`](SPEC.md) US-COM-013/016/026..034 e as propostas
> [`compras-purchase-convergencia-c1`](../../decisions/proposals/compras-purchase-convergencia-c1.md) e
> [`2026-10-06-entrada-xml-compra`](../../decisions/proposals/2026-10-06-entrada-xml-compra.md);
> (2) código, só para confirmar o comportamento; (3) Blade legada `resources/views/purchase_requisition/`,
> `purchase_order/`, `purchase/`, `purchase_return/`. A fonte 4 (Delphi) segue ausente (§0.1).
> Todas as linhas abaixo são do `main` em `7d99759`; quem for citar de novo, re-localize pelo símbolo.

```
estoque mínimo (job diário) ─┐
MRP (necessidade de compra) ─┼─► REQUISIÇÃO  transactions.type=purchase_requisition
digitada à mão ──────────────┘        │
                                      ├─► COTAÇÃO  compras_cotacoes  (só serviço, §5.6)
                                      │        │ fechar()
                                      ▼        ▼
                                   PEDIDO  transactions.type=purchase_order   ── alçada (compras_aprovacoes_pedido)
                                      │
                                      ▼  pedido pendente/recusado na alçada = 422
                                   COMPRA  transactions.type=purchase
                                      │ status=received
                                      ├─► estoque  variation_location_details.qty_available
                                      ├─► custo   (regra da empresa · custo médio móvel por flag)
                                      ├─► inspeção (só serviço, §5.6)
                                      └─► TÍTULO A PAGAR  fin_titulos (observer do Financeiro, pela compra)
                                                 │
                                   DEVOLUÇÃO  transactions.type=purchase_return  (estoque desce)
```

| # | Etapa | Onde nasce | Âncora no código | Tela |
|---|---|---|---|---|
| E1 | **Requisição** à mão | `PurchaseRequisitionController@store` grava `type=purchase_requisition` | `app/Http/Controllers/PurchaseRequisitionController.php:365` · `:377`; itens sugeridos pelo alerta em `getRequisitionProducts` `:532` | `PurchaseRequisition/Index.tsx` (lista) + Blade `purchase_requisition/` (criar) |
| E1a | Requisição **automática** por estoque mínimo (US-COM-027) | job diário, desligado por padrão em `common_settings` | `Modules/Compras/Services/RequisicaoEstoqueMinimoService.php` (docblock :13-26) · agenda `Modules/Compras/Providers/ComprasServiceProvider.php:65` | interruptor na aba Fluxos |
| E1b | **MRP** — necessidade somando OS e mínimo (US-COM-030) | só lê | `Modules/Compras/Services/NecessidadeDeCompra.php:38` · chamado em `PurchaseRequisitionController.php:277-281` | `PurchaseRequisition/_components/NecessidadeDeCompra.tsx` |
| E2 | **Cotação** entre fornecedores (US-COM-031) | `criar` / `daRequisicao`, respostas, `comparar`, `fechar` gera o pedido | `Modules/Compras/Services/CotacaoDeCompra.php:33` · `:83` · `:127` · `:165`; o pedido nasce em `:209-212` (`type=purchase_order`, `status=ordered`) | **nenhuma** (§5.6) |
| E3 | **Pedido** de compra | `PurchaseOrderController@store`; requisições de origem passam a `partial`/`completed` | `app/Http/Controllers/PurchaseOrderController.php:429` · linhas `:545` · status das requisições `:548` | `PurchaseOrder/Index.tsx` (lista) + Blade `purchase_order/` (criar/editar) |
| E3a | **Alçada** de valor (US-COM-032) | registrada no `store` do pedido; desligada por padrão (`common_settings['compras_alcada']`) | `PurchaseOrderController.php:554-556` → `Modules/Compras/Services/AlcadaDoPedido.php:68`; decisão em `:92` | **nenhuma** para aprovar nem para configurar (§5.6) |
| E4 | **Recebimento** = compra a partir do pedido (US-COM-013, decisão 2026-10-26: cada entrega é uma compra ligada ao pedido) | `PurchaseController@store`; travas antes de gravar | `app/Http/Controllers/PurchaseController.php:511`; posse das variações `:520`, pedido da própria empresa `:521` (US-COM-029), pedido aprovado na alçada `:522` → `:1558-1566`; transação `:628-665`; linhas `:642`; pedido vira `partial`/`completed` `:654-655` | `Purchase/Create.tsx` |
| E4a | **Estoque** | sobe só se a compra está `received` | `app/Utils/ProductUtil.php:1234-1236` (`createOrUpdatePurchaseLines` `:1201`); mudança de status depois em `PurchaseController@updateStatus` `:2038` | — |
| E4b | **Custo** pela regra da empresa (US-COM-026) | ao entrar em `received` | `PurchaseController.php:643` · `:1195` · `:2072` → `Modules/Compras/Services/AplicarCustoDoRecebimento.php:60` | proposta de preço no detalhe da compra (US-COM-028) |
| E4c | **Custo médio móvel** | observer do `Transaction`, depois do commit; flag `COMPRAS_CUSTO_MEDIO_MOVEL` | `Modules/Compras/Providers/ComprasServiceProvider.php:39` → `Modules/Compras/Observers/CompraRecebidaCustoMedioObserver.php:37-55` → `CustoMedioMovel.php:40`; flag em `Modules/Compras/Config/config.php:11` | — |
| E4d | **Inspeção** de qualidade (US-COM-034) | reprovado sai pela devolução do núcleo; desligada por padrão | `Modules/Compras/Services/InspecaoDoRecebimento.php:52` | **nenhuma** (§5.6) |
| E5 | **Título a pagar** | observer do Financeiro no `created`/`updated` da compra | `Modules/Financeiro/Providers/FinanceiroServiceProvider.php:72` → `Modules/Financeiro/Observers/TransactionObserver.php:33-59` → `Modules/Financeiro/Services/TituloAutoService.php:74-79` (só `purchase` vira `pagar`) e `:107` (só `payment_status` `due`/`partial`) | Financeiro (contas a pagar) |
| E5a | Compra **excluída** | título cancelado, não apagado | `TransactionObserver.php:61-76` | — |
| E6 | **Devolução** de compra | por compra (`PurchaseReturnController@store`) ou combinada (`CombinedPurchaseReturnController@save`); estoque desce, devolução vira crédito por `updatePaymentStatus` | `app/Http/Controllers/PurchaseReturnController.php:325` · estoque `:361` · `type` `:399` · `:412`; `app/Http/Controllers/CombinedPurchaseReturnController.php:73` · `:93` · `:131` · `:143` | `PurchaseReturn/Index.tsx` (lista) + Blade `purchase_return/` |
| E7 | **Entrada por XML** (fase 2 da proposta de 2026-10-06) | upload grava em `nfe_dfe_recebidos`; **não cria compra** | `Modules/NfeBrasil/Services/Entrada/ImportarXmlEntradaService.php:13-21`; flag `Modules/NfeBrasil/Config/config.php:36`; a ponte para compra é a US-COM-003, sem código | **nenhuma** (§5.6) |

**O que o código mostra e não estava escrito em lugar nenhum** <!-- derivado · medido 2026-11-05 -->

1. **O título a pagar nasce com a compra, qualquer que seja o `status`.** `TituloAutoService` olha o
   `type` (`:74-79`) e o `payment_status` (`:107`), nunca `transactions.status`. Compra gravada
   como `ordered` ou `pending` já gera o título. O **pedido** (`purchase_order`) não gera: cai no
   `default => null` do `match`. É a pergunta P1 de §5.7. (A auditoria de 2026-11-05 escreveu
   "nasce no pedido"; medido, o objeto é a compra ainda não recebida, não o pedido de compra.)
2. **O observer do Financeiro roda dentro da transação da compra.** `TransactionObserver::created`
   (`:33-42`) chama o serviço sem `try/catch`, e o `created` do `Transaction` dispara dentro do
   `DB::beginTransaction()` de `PurchaseController.php:628` (a compra nasce em `:637`). Erro no Financeiro desfaz a compra.
   Pergunta P2.
3. **O pedido que a cotação gera não passa pela alçada.** `CotacaoDeCompra::fechar` insere o
   pedido direto em `transactions` (`:209-212`) e não chama `AlcadaDoPedido::registrar`; sem linha
   em `compras_aprovacoes_pedido`, `bloqueiaRecebimento` (`AlcadaDoPedido.php:111-121`) não o segura.
4. **A alçada só é avaliada no `store` do pedido.** `PurchaseOrderController@update` (`:752`) não
   chama `registrar`: um pedido criado abaixo do limite e editado para cima não espera aprovação
   (achado A7 da auditoria de 2026-11-05).
5. **Devolução de compra não mexe no título a pagar.** `TituloAutoService` não trata
   `purchase_return` e o `DevolucaoVendaTituloService` só reconhece `sell_return`
   (`Modules/Financeiro/Services/DevolucaoVendaTituloService.php:40-43`). A devolução vira crédito
   no `payment_status` do núcleo; o título da compra original fica com o valor cheio. Não está entre
   as decisões já abertas: é pergunta nova (P7).
6. **O custo médio não volta atrás.** O observer só age na entrada em `received`
   (`CompraRecebidaCustoMedioObserver.php:39`); excluir, cancelar ou devolver não refaz o médio, e
   a falha vai só para o log (`:48-53`). O próprio docblock declara o limite da edição (`:21`).

### 5.6 O que existe só como serviço, sem tela

<!-- derivado: re-rodável do fonte · medido 2026-11-05 -->

Busca pelo nome da classe em `*.php`/`*.ts`/`*.tsx` fora de teste e fora do próprio arquivo
(`git grep -l -w <Classe>`), no `main` `7d99759`:

| Serviço | US | Quem chama fora de teste | Rota | Tela |
|---|---|---|---|---|
| `CotacaoDeCompra` | US-COM-031 | ninguém | não | não |
| `InspecaoDoRecebimento` | US-COM-034 | ninguém | não | não (o SPEC diz "depois do protótipo, ADR 0438") |
| `CurvaAbcReposicao` | US-ESTOQUE-003 | ninguém | não | não |
| `ImportarXmlEntradaService` (NfeBrasil) | fase 2 da entrada por XML | ninguém | não | não |
| `AlcadaDoPedido` — `definir`/`decidir` | US-COM-032 | ninguém | não | não |
| `AlcadaDoPedido` — `registrar`/`bloqueiaRecebimento` | US-COM-032 | `PurchaseOrderController.php:554-556` · `PurchaseController.php:1564-1565` | — | — |

Consequência para a alçada: ligada, ela **trava** o recebimento de pedido pendente (E4, `:522`), mas
ninguém consegue aprovar nem configurar pela aplicação. A permissão `compras.import_xml` é declarada
em `Modules/Compras/Http/Controllers/DataController.php:59` e nenhuma rota a confere.

O `NecessidadeDeCompra` (MRP) **não** está nesta lista: tem tela em `PurchaseRequisition/Index.tsx`.

### 5.7 Decisões em aberto — **[W]**

<!-- curado: [W] decide -->

As seis primeiras já estavam abertas na auditoria de 2026-11-05; a sétima saiu da leitura deste
fluxo. Nada aqui foi decidido pelo agente.

| # | Pergunta | O que o código faz hoje (evidência) |
|---|---|---|
| P1 | O título a pagar nasce na compra lançada (como hoje) ou só no recebimento? | nasce pela compra com `payment_status` `due`/`partial`, sem olhar o `status` — `TituloAutoService.php:74-79`, `:107` (§5.5 item 1) |
| P2 | Um erro no Financeiro deve cancelar a compra? | hoje cancela: observer síncrono, sem `try/catch`, dentro da transação — `TransactionObserver.php:33-42` + `PurchaseController.php:628` |
| P3 | Cotação, inspeção e curva ABC ganham tela ou ficam declaradas "só serviço"? E a alçada ganha tela para aprovar e configurar? | §5.6 |
| P4 | Custo médio móvel: uma chave para a instalação inteira (hoje) ou por empresa? | `config('compras.custo_medio_movel')` lido de `env` — `CustoMedioMovel.php:34-37`, `Config/config.php:11`; a tabela `compras_custo_medio` já é por `business_id` (migration `2026_10_27_400000`, `:26`, `:30`) |
| P5 | Fase 2 da entrada por XML: a nota recebida vira compra? Quem confere `compras.import_xml`? | o upload grava a nota e para (`ImportarXmlEntradaService.php:13-21`); a ponte é US-COM-003, `_pendente_` no SPEC; a permissão não é conferida por rota nenhuma |
| P6 | Este fluxo documentado vem antes dos 7 dias na ROTA LIVRE (US-COM-023), hoje planejados só para o cockpit? | US-COM-023 é `_pendente_` no SPEC e fala só de `/compras` |
| P7 | Devolução de compra deve abater o título a pagar da compra, como a devolução de venda abate o título a receber? | não abate (§5.5 item 5) |

---

## 6. Casos de uso

> **Derivados das 3 fontes** (canon → código → Blade), nunca só do `.tsx` ([ADR 0351](../../decisions/0351-sdd-from-source.md) D-A). Estado vem do **veredito da lane**, nunca da leitura: `✅` provado por teste verde que o cita · `🟡` parcial · `🔴` falso/quebrado · `⬜` não-verificado.
>
> ⚠️ **A lane `PHP / Pest (Compras · MySQL)` é ADVISORY** — conferido em [`governance/required-checks-baseline.json`](../../../governance/required-checks-baseline.json) (as lanes Pest **required** são Financeiro, NfeBrasil e Unit; Compras **não** está lá). Reprova visível, **não bloqueia merge**.

### 6.1 Core do cockpit (`CU-COM`)

#### CU-COM-01 — Consultar o cockpit de compras `[must]` 🟡
<!-- derivado: re-rodável do fonte (F1 · ComprasController@index · Index.tsx · purchase/index.blade.php) -->
*Dado* um usuário com `compras.view` no business B; *quando* abre `/compras`; *então* vê 4 KPIs (a pagar · em trânsito · volume do mês · fornecedores ativos), a lista paginada e o rodapé de sumário — todos do business B.

1. `[must]` sem `compras.view` → **403** (`ListarComprasRequest::authorize`).
2. `[must]` o componente Inertia renderizado é `Compras/Index` — nunca Blade.
3. `[perf]` `kpis`, `rows`, `summary` e `compra_detalhe` são **deferidos**; o primeiro response não os carrega.
4. `[reg]` a Blade oferece **5 filtros de servidor** (local · fornecedor · status da compra · status de pagamento · intervalo de datas). O cockpit oferece `q` + `stage`; **fornecedor, local e período não existem** — some sem Non-Goal ⇒ regressão. `[BACKLOG]` até [W] decidir.

#### CU-COM-02 — Isolar as compras por business `[must]` `[T0]` 🧪
<!-- derivado: re-rodável do fonte (ComprasController@index/@show · getListPurchases · MultiTenantTest) -->
*Dado* compras nos businesses 1 e 99; *quando* o usuário do business 1 usa o cockpit; *então* nada do 99 aparece, em nenhuma das superfícies.

1. `[T0]` a listagem do usuário biz=1 **não** contém `ref_no` criado em biz=99.
2. `[T0]` `GET /compras/{id-de-99}/detalhe` → **404**, não 403 (não revelar existência).
3. `[T0]` os KPIs deferidos do biz=1 **não** contam transações do biz=99.
4. `[T0]` o filtro `?q=` — que casa em `contacts.supplier_business_name` via JOIN — **não** casa contato do biz=99 (risco R1, fechado por US-COM-009).
5. `[must]` `business_id` vem de `auth()->user()`, não de `session()`; `<=0` ⇒ 403 (US-COM-007).

#### CU-COM-03 — Abrir o detalhe da compra `[must]` 🟡
<!-- derivado: re-rodável do fonte (F2 · ComprasService::buscarDetalhe · Drawer.tsx · purchase/partials/show_details.blade.php) -->
*Dado* uma compra do meu business; *quando* clico na linha (ou em "Ver" no menu Ações); *então* o drawer abre com Resumo, Itens, Documentos, Pagamentos e Histórico, sem recarregar a lista.

1. `[must]` partial reload `only:['compra_detalhe','selected_id']` — a tabela **não** é re-buscada.
2. `[must]` `amount_due = max(0, final_total − Σ pagamentos)` e `line_total = quantity × purchase_price_inc_tax`.
3. `[must]` compra de outro business ⇒ 404 (ver CU-COM-02).
4. `[ux]` "Ver pagamentos" abre o drawer **já na aba Pagamentos**.
5. ⚠️ **conflito com o §Non-Goals do charter** — §5.4.3. Decisão de [W].

#### CU-COM-04 — Filtrar e ordenar a lista `[must]` 🔴 **previsto quebrado** (§5.4.4)
<!-- derivado: re-rodável do fonte (ListarComprasRequest · ComprasService::SORT_MAP · Index.tsx SortHeader/tabs) -->
*Dado* que estou numa aba de estágio ou ordenando por uma coluna; *quando* busco ou clico no cabeçalho; *então* a listagem responde — nunca é derrubada pela validação do próprio módulo.

1. `[must]` **nenhum** valor de `stage` que a própria tela emite pode ser rejeitado pelo `ListarComprasRequest`.
2. `[must]` **todo** valor de `sort` que o `ComprasService::SORT_MAP` sabe ordenar tem que ser aceito pelo `ListarComprasRequest` (ou sair do `SORT_MAP`) — os dois contratos do módulo não podem divergir.
3. `[must]` a whitelist continua **fechada** (anti-SQLi/anti-DOS): `sort`/`stage`/`per_page` arbitrários seguem rejeitados. **Alargar não é abrir.**
4. `[should]` `dir` inválido normaliza pra `desc` (já coberto por `GapsHardeningTest`).

> **Duas correções são válidas** (alargar a whitelist **ou** fazer a UI emitir o valor core). O contrato é *"a UI não emite valor que o contrato rejeita"* — o teste é a instância dele hoje, e deve ser reescrito se [W] escolher corrigir pelo front.

#### CU-COM-05 — Respeitar as localizações permitidas do usuário `[must]` `[reg]` 🔴 **previsto ausente** (§5.4.1)
<!-- derivado: re-rodável do fonte (PurchaseController:83-86 Blade + :249-252 Inertia · ComprasService::listarCompras) -->
*Dado* um usuário sem `access_all_locations`, com permissão só para a Loja A; *quando* abre `/compras`; *então* vê apenas compras da Loja A — **igual ao que `/purchases` faz nos dois caminhos**.

1. `[must]` `[reg]` compra de local não permitido **não** aparece na listagem do cockpit.
2. `[should]` `[reg]` os KPIs também respeitam o recorte (hoje `calcularKpis` agrega o business inteiro).
3. `[should]` `[reg]` `view_own_purchase` sem `purchase.view` restringe a `created_by = eu` — `[BACKLOG]`, ver §9.

#### CU-COM-06 — Agir sobre a compra pelo menu Ações `[should]` `[reg]` ⬜
<!-- derivado: re-rodável do fonte (F3 · AcoesDropdown.tsx · PurchaseController@index addColumn('action')) -->
*Dado* uma linha da lista; *quando* abro "Ações"; *então* tenho as mesmas operações que a Blade oferece — sem perda silenciosa na migração.

1. `[reg]` **paridade contada contra a Blade** (`addColumn('action')`): Ver · Imprimir · Editar · Excluir · Rótulos · **Baixar documento** · **Ver documento (imagem)** · **Adicionar pagamento** · Ver pagamentos · Devolução ao fornecedor · Atualizar status · Notificação por estágio (`new_order`/`items_received`/`items_pending`). O cockpit entrega **9**; **"Baixar documento", "Ver documento" e "Adicionar pagamento" não existem** — e "Notificação" virou âncora `#notify-pending` no Edit, não o modal de template.
2. `[must]` "Excluir" confirma antes e recarrega `only:['rows','kpis']`.
3. `[must]` cada item respeita a permission correspondente. **Hoje o `AcoesDropdown` recebe `visibility` com default `{canEdit:true, canDelete:true, canRefund:true}` e o `Index.tsx` não o passa** — as permissões do back (`permissions.update`/`delete`) **não chegam** ao menu. `[BACKLOG]` (§9).

#### CU-COM-07 — Delegar criação e edição ao trilho `/purchases` (C1) `[must]` 🟡
<!-- derivado: re-rodável do fonte (F4 · Index.tsx :280-289 · AcoesDropdown · ADR compras-purchase-convergencia-c1) -->
*Dado* que quero criar ou editar uma compra a partir do cockpit; *quando* clico "+ Nova compra" ou "Editar"; *então* chego no React do trilho `/purchases` — nunca no Blade — e sem recarregar a aplicação.

1. `[must]` a navegação usa `router.visit` (injeta `X-Inertia`) — `<a href>`/`window.location.href` para `/purchases/*` cai no Blade (anti-hook do charter).
2. `[must]` "+ Nova compra" só aparece com `purchase.create` (não `compras.create`) — coberto por `ComprasIndexTest`.
3. `[must]` não existe `Pages/Compras/Create.tsx` nem `Edit.tsx` (anti-hook C1).
4. ⚠️ **Impressão, Rótulos e Reembolso usam `window.open`/`window.location.href` para URLs `/purchases/*` e `/purchase-return/*` de propósito** (são Blade-only). O anti-hook do charter é escrito em termos absolutos e não abre essa exceção — **divergência pra [W]**, não corrijo (é intenção).

#### CU-COM-08 — A entrada da compra move estoque e valor `[must]` `[V0]` 🧪
<!-- derivado: re-rodável do fonte (F5 · PurchaseController@store · ProductUtil · PurchaseCalculoValorEstoqueE2ETest) -->
*Dado* uma compra com grade, frete, desconto e imposto; *quando* é salva; *então* `final_total`, as `purchase_lines` e o saldo por variação/local ficam corretos — e o Financeiro ganha o título a pagar.

1. `[V0]` `final_total` confere por **dois caminhos** (recomputo à mão + soma das linhas).
2. `[V0]` `qty_available` por variação/local muda exatamente pelo delta comprado.
3. `[V0]` blindagem anti-`num_uf` ×100 (o incidente de 2026-06-05).
4. `[should]` `fin_titulos` `type=pagar` nasce pelo `TransactionObserver` — **`[BACKLOG]`**, é a US-COM-016 (sem teste hoje).

> **Este CU vive fora de `Modules/Compras`** (o `store` é do núcleo). Está aqui porque é o contrato que o cockpit **observa** e reporta nos KPIs.

#### CU-COM-09 — Importar DF-e recebida como compra `[should]` ⬜ **ausente**
<!-- derivado: re-rodável do fonte (SPEC US-COM-003 R-COM-201..206 · Index.tsx botão disabled · Modules/NfeBrasil) -->
*Dado* DF-e puxadas do SEFAZ pelo NfeBrasil; *quando* abro "Importar XML"; *então* escolho uma e a compra nasce com linhas e fornecedor pré-preenchidos.

1. `[must]` lista `NfeDfeRecebido` do meu business com `transaction_id IS NULL`.
2. `[must]` auto-match do fornecedor por CNPJ (`contacts.tax_number`).
3. `[T0]` re-import é idempotente (UNIQUE `(business_id, transaction_id)`) + advisory lock anti-corrida.
4. **Estado hoje:** o botão está `disabled` e o `ImportarDfeComoCompraService` **não existe**. É o maior gap do módulo ([`BRIEFING.md`](BRIEFING.md) G-01). **Nenhum UC nasce agora** — caso sem código vira UC órfão e o `casos-gate` G-2 pune ([proibicoes §5](../../proibicoes.md) 2026-07-16).

### 6.2 Fluxo de compra ponta a ponta (`CU-CFX`)

> Derivados de §5.5 (SPEC → código → Blade). O estado segue a regra 5 do template: vem do veredito
> da lane, não da leitura. Este PR **não** conferiu vereditos de lane; por isso todo CU abaixo está
> `⬜` e aponta o teste que existe hoje, para quem for promover. Nenhum UC nasce deste PR.

#### CU-CFX-01 — Pedir material pela requisição `[must]` ⬜
<!-- derivado: re-rodável do fonte (E1/E1a/E1b · PurchaseRequisitionController@store · RequisicaoEstoqueMinimoService · NecessidadeDeCompra) -->
*Dado* um item abaixo do mínimo, ou faltando para OS abertas; *quando* alguém (ou o job diário) abre a requisição; *então* ela nasce como `purchase_requisition` da empresa, por local, sem mover estoque nem valor.

1. `[must]` requisição à mão grava `type=purchase_requisition` (`PurchaseRequisitionController.php:377`).
2. `[must]` a automática (desligada por padrão) não pede de novo item que já está numa requisição aberta do local — US-COM-027, `RequisicaoEstoqueMinimoContratoTest`.
3. `[must]` o MRP só lê; a necessidade é comprometido + mínimo − saldo − a caminho — US-COM-030, `NecessidadeDeCompraTest`.
4. `[T0]` local, produto e variação são da empresa. ⚠️ A auditoria de 2026-11-05 registra o contrário no `store` à mão (A6, `PurchaseRequisitionController.php:376,393-394`); quem fecha é o lote 0.

#### CU-CFX-02 — Cotar entre fornecedores `[should]` ⬜ **sem tela**
<!-- derivado: re-rodável do fonte (E2 · CotacaoDeCompra) -->
*Dado* uma requisição; *quando* o comprador cota com vários fornecedores e fecha; *então* cada fornecedor vencedor recebe um pedido de compra.

1. `[must]` vence o menor preço unitário; empate, o menor prazo; ainda empatado, quem respondeu primeiro (`CotacaoDeCompra.php` docblock :19-20).
2. `[must]` o pedido gerado é `purchase_order` `ordered` (`:209-212`).
3. `[should]` ⚠️ o pedido gerado **não** passa pela alçada (§5.5 item 3). Se deve passar é parte de P3.
4. Teste hoje: `CotacaoDeCompraTest`. Tela: nenhuma (P3).

#### CU-CFX-03 — Fazer o pedido, com alçada `[must]` ⬜
<!-- derivado: re-rodável do fonte (E3/E3a · PurchaseOrderController@store · AlcadaDoPedido) -->
*Dado* a alçada ligada na empresa; *quando* o pedido passa do limite de quem o fez; *então* ele fica `pendente` e não pode ser recebido até gerente ou diretoria aprovar.

1. `[must]` sem alçada ligada, nada muda (desligada por padrão, `AlcadaDoPedido.php` docblock :15-16).
2. `[must]` quem já tem a permissão do nível aprova na hora (`:20-21`, `:79-88`).
3. `[must]` pedido pendente ou recusado bloqueia a compra que o recebe (`PurchaseController.php:522` → `:1558-1566`).
4. `[should]` ⚠️ editar o pedido para cima não reavalia a alçada (§5.5 item 4).
5. Teste hoje: `AlcadaDoPedidoTest`. Tela para aprovar e configurar: nenhuma (P3).

#### CU-CFX-04 — Receber o pedido, em partes `[must]` `[V0]` `[T0]` ⬜
<!-- derivado: re-rodável do fonte (E4/E4a · PurchaseController@store · ProductUtil::createOrUpdatePurchaseLines · TransactionUtil::updatePurchaseOrderStatus) -->
*Dado* um pedido aprovado da empresa; *quando* chega parte da mercadoria e a compra é lançada como recebida a partir dele; *então* o estoque sobe só pelo recebido e o pedido fica `partial` até completar.

1. `[V0]` estoque sobe só com `status=received` (`ProductUtil.php:1234-1236`).
2. `[must]` cada entrega é uma compra ligada ao pedido; o que falta fica no pedido (decisão 2026-10-26 em US-COM-013) — `RecebimentoParcialPedidoTest`.
3. `[T0]` pedido e linha de pedido de outra empresa = 422 (US-COM-029, `PurchaseController.php:521`) — `CompraDePedidoOutraEmpresaTest`.
4. `[V0]` valor e estoque conferem por dois caminhos — US-COM-011, `PurchaseCalculoValorEstoqueE2ETest` (o mesmo contrato do CU-COM-08).

#### CU-CFX-05 — Atualizar o custo no recebimento `[must]` `[V0]` ⬜
<!-- derivado: re-rodável do fonte (E4b/E4c · AplicarCustoDoRecebimento · CustoMedioMovel) -->
*Dado* a regra de custo da empresa gravada; *quando* a compra entra em `received`; *então* o custo do insumo segue o método da regra e o preço segue a opção escolhida (manter, aplicar ou propor).

1. `[must]` empresa sem a regra: comportamento do núcleo, sem mudança — US-COM-026, `AplicarCustoDoRecebimentoTest`.
2. `[must]` proposta de preço é aprovada ou recusada no detalhe da compra — US-COM-028, `PropostasDePrecoContratoTest`.
3. `[V0]` custo médio móvel (flag da instalação, P4) recalcula depois do commit e nunca derruba a compra — `CustoMedioMovelTest`.
4. `[V0]` ⚠️ excluir, cancelar ou devolver não refaz o médio (§5.5 item 6).

#### CU-CFX-06 — Gerar o título a pagar `[must]` ⬜
<!-- derivado: re-rodável do fonte (E5/E5a · TransactionObserver · TituloAutoService) -->
*Dado* uma compra a prazo; *quando* é gravada; *então* o Financeiro tem um título a pagar com o valor da compra; *quando* é excluída, o título é cancelado, não apagado.

1. `[must]` só `type=purchase` com `payment_status` `due`/`partial` gera título (`TituloAutoService.php:74-79`, `:107`).
2. `[must]` compra paga no ato não gera título pendente (`:107`).
3. `[must]` em qual momento nasce (lançamento ou recebimento) é P1; se erro no Financeiro desfaz a compra é P2.
4. Teste hoje: nenhum no caminho da compra — é a US-COM-016 (`[BACKLOG]`).

#### CU-CFX-07 — Devolver ao fornecedor `[must]` `[V0]` ⬜
<!-- derivado: re-rodável do fonte (E6 · PurchaseReturnController@store · CombinedPurchaseReturnController@save) -->
*Dado* uma compra recebida; *quando* parte volta ao fornecedor (por compra ou combinada, ou pelo reprovado da inspeção); *então* o estoque desce o devolvido e a devolução vira crédito com o fornecedor.

1. `[V0]` estoque desce pelo `quantity_returned` (`PurchaseReturnController.php:361`; `CombinedPurchaseReturnController.php:131`).
2. `[must]` crédito pelo `updatePaymentStatus` (`PurchaseReturnController.php:412`; `CombinedPurchaseReturnController.php:143`).
3. `[must]` o reprovado da inspeção sai pela mesma conta (US-COM-034, `InspecaoDoRecebimentoTest`; sem tela, P3).
4. `[should]` ⚠️ o título a pagar da compra não é abatido (§5.5 item 5, P7).
5. Teste hoje: `tests/Feature/Purchase/DevolucaoCompraStoreBaselineTest.php`.

#### CU-CFX-08 — Lançar a compra pela nota recebida `[should]` ⬜ **ausente**
<!-- derivado: re-rodável do fonte (E7 · ImportarXmlEntradaService · US-COM-003) -->
É o CU-COM-09 visto do fluxo: o upload do XML já grava a nota (`ImportarXmlEntradaService.php:13-21`, flag desligada), mas nada a transforma em compra. Depende de P5. Nenhum UC nasce enquanto não houver código (mesma razão do CU-COM-09).

### 6.4 Non-Goals — **só [W] preenche**

> O agente é **proibido de inferir** Non-Goal ([ADR 0351](../../decisions/0351-sdd-from-source.md) Fase 2.1). Os Non-Goals vigentes da tela vivem no [§Non-Goals do `Index.charter.md`](../../../resources/js/Pages/Compras/Index.charter.md) e **não foram tocados neste PR**. As duas divergências levantadas contra eles (§5.4.3 drawer · CU-COM-07 item 4) são **perguntas pra [W]**, não edições.

⬜ _Seção aguardando [W]._

---

## 7. Requisitos não-funcionais

<!-- derivado: re-rodável do fonte -->

| NFR | Alvo | Onde é medido hoje |
|---|---|---|
| First-paint | ≤ 800ms (skeleton ≤100ms) | `ux_targets` do charter — **sem medição automatizada** |
| Anti-N+1 | contagem de queries **constante** com o nº de linhas | `ComprasListagemNPlusUmTest` (na lane) |
| Rate limit | `throttle:60,1` no grupo `/compras` | rota declarada; **o 429 comportamental nunca foi testado** (US-COM-008 admite) |
| Observabilidade | spans `compras.listarCompras` · `compras.calcularKpis` · `compras.buscarDetalhe` | `GapsP1HardeningTest` (structural) |
| A11y | `role=dialog` + focus-trap + `Esc` no drawer | **ausente** — US-COM-020 |
| Regressão visual | estados `default` + `loading` | charter `states:`; `dark`/`empty` **podados** por baseline flaky (US-COM-021) |

> ⚠️ **Nota honesta sobre os "hardening tests".** `GapsHardeningTest` e `GapsP1HardeningTest` são, em boa parte, **source-grep** (`file_get_contents` + `str_contains`) — provam que o *texto* existe, não que o *comportamento* acontece. O próprio [SPEC US-COM-011](SPEC.md) diz isso e manda **não** deletá-los em bloco: o **bloco Gap #4** (`ListarComprasRequest`, whitelists) **é comportamental e permanece**. Nenhum CU deste SDD se ancora exclusivamente num source-grep.

---

## 8. Estratégia de qualidade e rollout

<!-- derivado: re-rodável do fonte -->

- **Lane:** [`.github/workflows/compras-pest.yml`](../../../.github/workflows/compras-pest.yml) — MySQL 8 real, schema baseline, seed biz=1 + biz=2, `skip-as-pass` por `paths-filter`.
- **A lane é uma ALLOWLIST EXPLÍCITA**, arquivo a arquivo, no step `Run Pest`. Teste que não está listado **não roda** — é o "verde impossível" que o `anchor-lint` denuncia. Adicionar o arquivo ao YAML é parte do trabalho, não opcional.
- **Força:** **advisory** — não está em [`governance/required-checks-baseline.json`](../../../governance/required-checks-baseline.json).
- **Catraca declarada da lane:** *"roda só os arquivos comprovadamente VERDES"* (ratchet up por lote). ⚠️ Este PR **adiciona um arquivo failing-first** — a lane vai ficar vermelha de propósito, e como ela é advisory isso **não bloqueia merge**. Ver §9.
- **Trio da tela:** `Index.tsx` + `Index.charter.md` + `Index.casos.md` (o terceiro nasce neste PR). Verificável por `npm run screen:files -- Compras/Index`.
- **Rollout:** o módulo ainda não tem entrada em `config/governance/module_clients.yaml` (US-COM-010); canary Larissa biz=4 é 7d **pós-merge**, nunca em teste.

---

## 9. Riscos e dívidas conhecidas

<!-- curado: foto que envelhece -->

| # | Dívida | Vira |
|---|---|---|
| D1 | Cockpit ignora `permitted_locations` | **CU-COM-05** (UC-CMP-08) |
| D2 | `stage`/`sort` da UI fora da whitelist do módulo | **CU-COM-04** (UC-CMP-06/07) |
| D3 | Cockpit ignora `view_own_purchase` | `[BACKLOG]` — a premissa (usuário com `compras.view` **e** `view_own_purchase` **sem** `purchase.view`) não está estabelecida em documento nenhum. Precisa de [W] antes de virar UC |
| D4 | `permissions.update`/`delete` não chegam ao `AcoesDropdown` (defaults `true`) | `[BACKLOG]` |
| D5 | 3 ações da Blade sem equivalente (baixar documento · ver documento · adicionar pagamento) | `[BACKLOG]` — CU-COM-06 item 1 |
| D6 | Filtro de aba client-side sobre a página corrente; dois totais divergentes na mesma tela | `[BACKLOG]` — charter já declara |
| D7 | `conferido`/`pago` inalcançáveis (FSM não-persistida) | `[BACKLOG]` → **US-COM-014** |
| D8 | Exports perdem o filtro corrente e os 4 botões fazem a mesma coisa | `[BACKLOG]` |
| D9 | Sem filtro de fornecedor / local / período (a Blade tem) | `[BACKLOG]` — CU-COM-01 item 4 |
| D10 | Ponte DF-e→compra ausente | **US-COM-003** (CU-COM-09 ⬜) |
| D11 | Throttle sem teste comportamental do 429 | **US-COM-008** |
| D12 | Drawer sem a11y | **US-COM-020** |
| D13 | Non-Goal do charter contradiz o drawer que existe | ⚠️ **decisão [W]** (§5.4.3) |
| D14 | Anti-hook do charter contra `/purchases/*` fora do Inertia vs. os bridges deliberados | ⚠️ **decisão [W]** (CU-COM-07 item 4) |
| D15 | Fonte 4 (Delphi/Office Comercial) inexistente pro módulo | ⚠️ **decisão [W]** — criar `ANTI-REGRESSAO-compras-legacy.md` ou assumir paridade só-Blade |

---

## 10. Roadmap de evolução

<!-- curado: [W] prioriza -->

Fonte viva do backlog: [`SPEC.md`](SPEC.md) §9 + [`CAPTERRA-INVENTARIO.md`](CAPTERRA-INVENTARIO.md). A ordem abaixo é **proposta**, não decisão.

1. **Fechar o contrato quebrado** (CU-COM-04, CU-COM-05) — é dívida da tela que já está no ar, não feature nova.
2. **US-COM-003 + US-COM-012** — ponte DF-e→compra + matching por EAN. O substrato do NfeBrasil já existe; falta a ponte.
3. **US-COM-013** — recebimento parcial (vestuário recebe lote incompleto de verdade).
4. **US-COM-014** — FSM persistida, alinhada ao [ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12) (fecha D7).
5. **US-COM-015 / US-COM-016** — invariante de estoque + `/compras`→contas a pagar.
6. **US-COM-019 / US-COM-020 / US-COM-021** — eager-load, a11y, VRT dark/empty.

---

## 11. Referências

- [`SPEC.md`](SPEC.md) · [`BRIEFING.md`](BRIEFING.md) · [`SUPERFICIE.md`](SUPERFICIE.md) · [`RUNBOOK-compras-index.md`](RUNBOOK-compras-index.md)
- [`AUDIT-SENIOR-2026-05-25.md`](AUDIT-SENIOR-2026-05-25.md) · [`AUDITORIA-COMPRAS-2026-05-21.md`](AUDITORIA-COMPRAS-2026-05-21.md) · [`DISCOVERY-LARISSA-COMPRAS.md`](DISCOVERY-LARISSA-COMPRAS.md)
- [`CAPTERRA-FICHA.md`](CAPTERRA-FICHA.md) · [`CAPTERRA-DESIGN-FICHA.md`](CAPTERRA-DESIGN-FICHA.md) · [`CAPTERRA-INVENTARIO.md`](CAPTERRA-INVENTARIO.md)
- [`memory/dominio/compras.md`](../../dominio/compras.md) — fonte única do vocabulário (gate `dominio:check`)
- [`Index.charter.md`](../../../resources/js/Pages/Compras/Index.charter.md) · [`Index.casos.md`](../../../resources/js/Pages/Compras/Index.casos.md)
- ADR proposta [`compras-purchase-convergencia-c1`](../../decisions/proposals/compras-purchase-convergencia-c1.md) (C1 vigente) · [`compras-modulo-greenfield-hibrido`](../../decisions/proposals/compras-modulo-greenfield-hibrido.md)
- [ADR 0351](../../decisions/0351-sdd-from-source.md) (o agent) · [ADR 0352](../../decisions/0352-errata-0351-venue-distiller-citacao-taxonomia.md) (errata) · [SDD-TEMPLATE](../_DesignSystem/SDD-TEMPLATE.md)

---

## Changelog

> Correção não apaga — vira changelog (regra 7 do template).

| Versão | Data | O que mudou |
|---|---|---|
| 1.0.0 | 2026-07-27 | Nascimento. §0–§11 derivados de **3 fontes** (fonte 4 Delphi declarada ausente). 9 CU (`CU-COM-01..09`). 6 divergências de contrato medidas (§5.4). Chip **S1** da Onda 1 do passo 5 — primeira corrida do ramo *"SDD não existe"*. |
| 1.1.0 | 2026-11-05 | Fluxo de compra ponta a ponta entra no SDD do domínio, sem arquivo novo (SDD-TEMPLATE regra 3): §5.5 (etapas E1–E7 com `arquivo:linha` do `main` `7d99759`), §5.6 (serviços sem tela), §5.7 (7 decisões em aberto [W]: as 6 da auditoria de 2026-11-05 e a P7, devolução × título a pagar) e §6.2 (`CU-CFX-01..08`, todos `⬜`). Nota datada em §5.2: o módulo passou a ter migrations próprias. Nada do texto de 1.0.0 foi apagado. |

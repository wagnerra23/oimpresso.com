---
id: requisitos-cliente-sdd-cadastro-cliente-v1-0
slug: cliente-sdd
title: "SDD — Cadastro de Cliente (domínio Cliente)"
type: sdd
module: Cliente
status: ativo
owner: W
version: 1.0.0
last_updated: "2026-07-27"
related_docs:
  - SPEC.md
  - BRIEFING.md
  - CAPTERRA-FICHA.md
  - CAPTERRA-INVENTARIO.md
  - clientes-gap.md
  - ../Crm/RUNBOOK-cliente-index.md
  - ../Crm/RUNBOOK-Cliente-drawer-760px.md
  - ../Crm/PII-REDACTION.md
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0101-tests-business-id-1-nunca-cliente
  - 0104-processo-mwart-canonico-unico-caminho
  - 0110-cockpit-pattern-v2-canon-list-detail
  - 0127-modules-auditoria-undo-activity-log
  - 0149-mwart-screen-pattern-reuse-cowork
  - 0178-restauracao-campos-fiscais-br-canon
  - 0179-cliente-drawer-760px-substitui-show-fullpage
  - 0188-contacts-multi-type-flag-aditiva
  - 0246-tipo-outros-default-migracoes-legacy
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0301-separar-cliente-deprecar-crm-pipeline
  - 0351-sdd-from-source
related_us:
  - US-CRM-063
  - US-CRM-064
  - US-CRM-065
  - US-CRM-066
  - US-CRM-067
  - US-CRM-068
  - US-CRM-069
  - US-CRM-070
  - US-CRM-071
  - US-CRM-072
  - US-CRM-073
  - US-CRM-074
  - US-CRM-075
  - US-CRM-076
  - US-CRM-078
  - US-CRM-079
  - US-CRM-080
  - US-CRM-081
  - US-CRM-082
  - US-CRM-083
  - US-CRM-084
  - US-CRM-085
---

# SDD — Software Design Document · Cadastro de Cliente (domínio Cliente)

> Chip **S-Cliente** da Onda 2 do [passo 5](../_Governanca/programa-ondas/passo-5-sdd-por-modulo.md),
> agent [`sdd-from-source`](../../../.claude/agents/sdd-from-source.md) ([ADR 0351](../../decisions/0351-sdd-from-source.md)).
>
> **Este módulo entrou diferente dos irmãos:** ele já tinha **contrato parcial** — 7
> `casos.md` com **21 UC, todos com teste que os cita** (`requisitos-status.mjs Cliente`,
> 2026-07-27). O trabalho aqui **não** foi escrever contrato do zero; foi **derivar o SDD
> do que já estava contratado**, medir se aqueles testes *rodavam em algum lugar*, e
> contratar o único eixo Tier 0 que estava descoberto (PII, §6.3).
>
> **Escopo:** o domínio **Cliente** — as 7 Pages `resources/js/Pages/Cliente/*` + o
> **drawer 760px** que é a superfície de detalhe viva ([ADR 0179](../../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md)).
> O SDD é do **MÓDULO/família**, nunca da tela ([ADR 0351](../../decisions/0351-sdd-from-source.md) Fase 2.1).
>
> 🪪 **Cliente ≠ CRM** ([ADR 0301](../../decisions/0301-separar-cliente-deprecar-crm-pipeline.md)):
> este documento cobre o **cadastro**. O *pipeline CRM* (leads/propostas/campanhas), que
> compartilha o diretório `Modules/Crm/`, está em depreciação e **não** entra aqui.

---

## 0. Base empírica

<!-- curado: foto que envelhece -->

### 0.1 As fontes que sustentam este documento — e a que falta

| # | Fonte | Resolvida em | Estado |
|---|---|---|---|
| 1 | **Documentação canon** | [`SPEC.md`](SPEC.md) (22 US) · [`CAPTERRA-FICHA.md`](CAPTERRA-FICHA.md) · [`CAPTERRA-INVENTARIO.md`](CAPTERRA-INVENTARIO.md) · 7 `*.charter.md` · 7 `*.casos.md` (21 UC) · 8 `RUNBOOK-cliente-*.md` em [`../Crm/`](../Crm/) | ✅ |
| 2 | **React/Laravel atual** | `Cliente/{Index,Create,Edit,Show,Import,Ledger,Map}.tsx` + `_drawer/` + `_show/` · `app/Http/Controllers/ContactController.php` (3.558 LOC) · `Modules/Crm/Http/Controllers/Cliente*Controller.php` · `App\Contact` | ✅ |
| 3 | **Blade AdminLTE legada** | `resources/views/contact/**` — **25 arquivos**, todas alcançáveis hoje pelo *dual-render* das 7 flags `MWART_CLIENTE_*` (§0.2) | ✅ |
| 4 | **Delphi / Office Comercial** | — | ❌ **AUSENTE** |

> ⚠️ **Gap declarado (fonte 4).** `find memory -iname "*ANTI-REGRESSAO*"` devolve **2 arquivos, ambos do Produto**. O Cliente não tem `ANTI-REGRESSAO-*.md` destilado do manual WR Comercial. A triangulação aqui é de **3 fontes**, e o contrato de paridade é **mais fraco**: onde este documento fala em paridade, a referência é a **Blade AdminLTE viva** (fonte 3), nunca o Delphi. **Nada de contrato legado Delphi foi inventado.** Se [W] quiser o eixo Delphi, ele precisa nascer como `ANTI-REGRESSAO-cliente-legacy.md` antes.

### 0.2 A Blade de referência — resolvida, não assumida

A armadilha da Blade homônima ([ADR 0351](../../decisions/0351-sdd-from-source.md) Fase 1.1) **não** morde aqui do jeito clássico (nome do método ≠ nome do arquivo), mas morde de outro: o Cliente é **dual-render por flag**, não por header.

`ContactController::shouldRenderInertiaCliente($flag, $business_id)` (`app/Http/Controllers/ContactController.php:36`) resolve, por business:

```
config("mwart.{$flag}.enabled") === false                  → Blade
business_ids vazio                                          → React (todos)
business_ids preenchido e o meu id não está lá              → Blade
```

São **7 flags** (`config/mwart.php:120-153`): `cliente_index · cliente_create · cliente_show · cliente_edit · cliente_import · cliente_ledger · cliente_map`. Logo **cada uma das 7 telas tem uma Blade irmã viva**, e a paridade não é histórica — é o que um business com a flag desligada vê **hoje**:

| Tela React | Blade irmã (fonte 3) |
|---|---|
| `Cliente/Index` | `contact/index.blade.php` (362 linhas) |
| `Cliente/Show` | `contact/show.blade.php` (594 linhas) + 8 partials |
| `Cliente/Create` | `contact/create.blade.php` · `create-page.blade.php` |
| `Cliente/Edit` | `contact/edit.blade.php` |
| `Cliente/Import` | `contact/import.blade.php` |
| `Cliente/Ledger` | `contact/ledger.blade.php` + `ledger_format_2/3.blade.php` |
| `Cliente/Map` | `contact/contact_map.blade.php` |

**A Blade de referência do detalhe é `contact/show.blade.php`** — e ela é quem expõe a divergência de paridade da §5.4.1.

> Há ainda um **segundo desvio de rota**, e ele NÃO é a Blade: com `cliente_index` ligada, `GET /cliente/{id}` **não** chama `show()` — faz **302 pra `/cliente?contact_id={id}&tab=...`**, abrindo o drawer (`routes/web.php`, bloco `cliente.show`). Ou seja: a ficha que a Larissa realmente abre é o **drawer da Index**, não o `Show.tsx`. Documentar `Show.tsx` como "a ficha" seria carimbar a superfície morta.

### 0.3 O que o benchmark expôs — ponteiro, não cópia

Números vivem no dono, não aqui ([proibicoes §5](../../proibicoes.md) 2026-07-17). **Recibo datado:** em **2026-07-03** a [`CAPTERRA-FICHA.md`](CAPTERRA-FICHA.md) fechou o módulo com nota de **capacidade** abaixo da nota de **design** já registrada no [board de screen-grade](../../governance/scorecards/SCREEN-GRADE-BOARD-2026-05-30.md), e nomeou o eixo mais baixo: **C04 — direitos do titular (erasure/portabilidade, LGPD Art. 18)**, com a leitura *"o que a tela bonita esconde não é 'a conta não fecha', é 'o titular não tem como ser esquecido'"*. Para o valor atual de cada régua, **abrir a ficha / rodar `php artisan module:grade Crm`** — não reproduzir o número aqui.

Dois achados da ficha que este SDD **confirmou por varredura própria** (§5.4.2 e §5.4.3): C05 (`App\Contact` sem global scope) e C03 (o "masking" de CPF/CNPJ).

---

## 1. Visão geral

<!-- derivado: re-rodável do fonte -->

### 1.1 O que é

**Cliente** é o cadastro de contatos PF/PJ do UltimatePOS (`contacts`, `type ∈ {customer, supplier, employee, representative, other, both}`) com canon fiscal BR restaurado ([ADR 0178](../../decisions/0178-restauracao-campos-fiscais-br-canon.md)), multi-papel por flags ([ADR 0188](../../decisions/0188-contacts-multi-type-flag-aditiva.md)), extrato financeiro, import em massa e mapa geográfico.

É o registro **mais PII-pesado do ERP**: CPF/CNPJ, IE/RG, nascimento, endereço, telefone, e-mail, dado bancário de pagamento. Todo o §3 e a §6.3 saem daí.

O módulo **não é greenfield**: o backend é o `ContactController` do núcleo UPOS (3.558 LOC), estendido; `Modules/Crm/` acrescenta os controllers do drawer 760 (autosave, auditoria, lookup, IA, veículos, OSs).

### 1.2 Família de telas — 7 Pages, 1 superfície viva de detalhe

| Rota | Componente | Flag | Papel |
|---|---|---|---|
| `/cliente` (canon) · `/contacts` | `Cliente/Index.tsx` | `cliente_index` | **cockpit** — lista + KPIs + **drawer 760** (a ficha viva) |
| `/cliente/{id}` | — | `cliente_index` | **302** → `/cliente?contact_id={id}&tab=` (abre o drawer) |
| `/contacts/{id}` | `Cliente/Show.tsx` | `cliente_show` | ficha full-page — **`deprecated`** ([ADR 0179](../../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md)), fallback de canary |
| `/contacts/create` | `Cliente/Create.tsx` | `cliente_create` | cadastro novo (form single-page) |
| `/contacts/{id}/edit` | `Cliente/Edit.tsx` | `cliente_edit` | edição full-page |
| `/contacts/import` | `Cliente/Import.tsx` | `cliente_import` | import XLSX (27 colunas UPOS) |
| `/contacts/ledger` | `Cliente/Ledger.tsx` | `cliente_ledger` | **extrato financeiro** (valor) |
| `/contacts/map` | `Cliente/Map.tsx` | `cliente_map` | mapa + lista pesquisável |

> **O drawer não é uma 8ª Page** — ele vive dentro de `Index.tsx`, com 10 abas de cadastro em `_drawer/` (`Identificacao · Contato · Endereco · Comercial · Classificacao · Auditoria · IA · Oss · PlacasMain · EnderecosEntregaList`) e a aba **Operações** (`_drawer/OssTab`) que **reusa 7 componentes de `_show/`** (`LedgerTab · SalesTab · PaymentsTab · DocumentsTab · PessoasContatoTab · SubscriptionsTab · RewardPointsTab`). É por isso que o contrato do `Show.tsx` **deprecated** segue vivo: os componentes dele são o miolo da ficha atual.

### 1.3 Verticais

- **horizontal** — todo business cadastra cliente. É o módulo mais transversal do ERP (Sells, Compras, Financeiro, Fiscal e Oficina todos leem `contacts`).
- **vestuário (Larissa @ ROTA LIVRE biz=4)** — [W] confirmou em **2026-06-24** ([`audits/ALINHAMENTO-cliente-2026-06-22.md`](audits/ALINHAMENTO-cliente-2026-06-22.md)) que biz=4 roda **5 das telas em React em produção** (flags ON). Este é o único módulo da leva com adoção confirmada em cliente real.
- **oficina (Martinho biz=164)** — as abas `PlacasMainTab` / `OssTab` / `ClienteVeiculosController` são a especialização veicular enxertada no mesmo cadastro.

---

## 2. Público-alvo e personas

<!-- curado: foto que envelhece -->

| # | Persona | Onde ela dói |
|---|---|---|
| **P1** | **Larissa — ROTA LIVRE (biz=4, vestuário)**, 1280×1024, não-técnica | cadastra no balcão, no meio da venda. Precisa do drawer abrindo sobre a lista (sem perder o contexto) e do autosave campo-a-campo — daí o paradigma da [ADR 0179](../../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md) |
| **P2** | **Eliana [E] — financeiro/fechamento mensal** | é a persona do **Ledger**: "quem me deve quanto", export PDF, fechamento. O `[V0]` do §6.2 é dela |
| **P3** | **Wagner — WR2 SC (biz=1)** | operador-dono e **cobaia segura**: todo Pest roda em biz=1, **nunca** biz=4 ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)) |
| **P4** | **O titular do dado** (o próprio cliente da Larissa) | persona **sem tela**: é quem a LGPD Art. 18 protege. Hoje ele não tem como pedir esquecimento nem portabilidade (§5.4.4 · CU-CLI-12). ⬜ **não-validada com [W]** — nenhum cliente reportou; a demanda é legal, não observada ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)) |

> ⚠️ **P4 precisa de decisão [W].** É a única persona deste SDD derivada de **obrigação legal** e não de sinal de cliente. Se [W] entender que o risco não se materializa em PME, CU-CLI-12 vira Non-Goal — decisão dele, não do agente.

---

## 3. Governança aplicável — o Tier 0 que morde AQUI

<!-- derivado: re-rodável do fonte -->

| Regra | Onde morde no Cliente |
|---|---|
| **Multi-tenant Tier 0** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) | ⚠️ **`App\Contact` NÃO tem global scope** — medido, §5.4.2. O isolamento é `where('business_id')` **manual** em cada query. Todo `[T0]` deste SDD existe por causa disso |
| **PII / LGPD** | `contacts` guarda CPF/CNPJ, IE/RG, nascimento, endereço, telefone. Nenhum documento real em teste/log/commit — fixtures sintéticas ou `[REDACTED]` ([proibicoes](../../proibicoes.md)) |
| **Activity log sem PII** ([ADR 0127](../../decisions/0127-modules-auditoria-undo-activity-log.md)) | `Contact::getActivitylogOptions()` exclui `tax_number_1` do `logOnly`. Guard comportamental: `tests/Feature/Auditoria/ContactPiiLogsActivityTest.php` |
| **REGRA MESTRE valor/estoque** | o **saldo do cliente** sai por **dois caminhos independentes** (`Util::getContactDue` e `TransactionUtil::getLedgerDetails['all_balance_due']`). Mexer em qualquer um exige dupla-confirmação + antes→depois — CU-CLI-08 `[V0]` |
| **`biz=1`, nunca `biz=4`** ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)) | crítico aqui: biz=4 é **ROTA LIVRE em produção com as flags ON**. O adversário canônico é biz=2 (seed da lane) / biz=99 |
| **Testes só no CT 100 / CI** ([ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md)) | a lane nasce neste PR: [`.github/workflows/cliente-pest.yml`](../../../.github/workflows/cliente-pest.yml) (MySQL real, biz=1 + biz=2) |
| **Dicionário de domínio** ([ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md) G-4) | ❌ **não existe** `memory/dominio/cliente.md` (`ls memory/dominio/` = 6 arquivos: compras · estoque · financeiro · fiscal-faturamento · oficina-auto · vendas). Os enums de `contacts.type` **não são cobertos** pelo `dominio-gate`. Gap declarado, não consertado (arquivo fora da área do chip) |

---

## 4. Design system aplicável

<!-- derivado: re-rodável do fonte -->

- **Shell:** `AppShellV2` + `PageHeader`; a barra de abas de tipo usa o componente canônico `PageHeaderTabs` (migrada do inline hand-rolled em 2026-07-14).
- **Padrão de tela:** cockpit **lista + detalhe em drawer** — família [PT-01 Lista](../_DesignSystem/padroes-tela/PT-01-Lista.md) com o pattern list-detail da [ADR 0110](../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md).
- **Protótipo âncora:** `bundle_source: clientes-page.jsx` (declarado no `Index.charter.md`); handoff em `prototipo-ui/prototipos/clientes/HANDOFF_CLIENTES.md`.
- **Estados VRT:** `[default, empty, loading, dark]` — `error` foi **removido de propósito** (toast sonner não dá estado determinístico no VRT). Sincronizado com `tests/Browser/visreg-states.json`.
- **`ux_targets` (dos charters):** first-paint Index p95 <600ms · drawer abre p95 <200ms · autosave round-trip p95 <400ms · lookup CEP/CNPJ p95 <800ms (cache hit) / <2,5s (miss) · card de IA p95 <6s · Ledger first-paint p95 <1000ms para 100 lançamentos · PDF <3s para 100 linhas · **1280×1024 sem scroll horizontal** com drawer 760 + sidebar 240.

---

## 5. Arquitetura

### 5.1 Visão em camadas

<!-- derivado: re-rodável do fonte -->

```
/cliente ────────────────► ContactController@index ──► buildClienteIndexCustomers()  ─┐
  (rota fecha whitelist de              │              buildClienteIndexKpis()        ├─► contacts
   type: customer|supplier|employee|    │                                             │   (+ transactions p/ KPI)
   representative|other|all)            └─ shouldRenderInertiaCliente('cliente_index')
                                             ├─ true  → Inertia::render('Cliente/Index')
                                             └─ false → view('contact.index')   ← Blade viva

/cliente/{id} ──────────► (closure em routes/web.php) ─► 302 /cliente?contact_id=&tab=   (drawer)
                                             └─ flag off → ContactController@show → Cliente/Show | contact.show

drawer (autosave) ──────► PATCH /cliente/{id}/{secao} ─► Modules\Crm\...\ClienteAutosaveController
drawer (Operações) ─────► GET  /cliente/{id}/sales-json | payments-json | rewards-json
                                                        | subscriptions-json | anexos
                          └─► ContactController@{salesJson,paymentsJson,...}

/contacts/ledger ───────► ContactController@getLedger ─► TransactionUtil::getLedgerDetails()
/contacts/import ───────► ContactController@{get,post}ImportContacts
/contacts/map ──────────► ContactController@contactMap
POST /contacts ─────────► StoreContactRequest (mod-11 BR) → ContactController@store
PUT  /contacts/{id} ────► UpdateContactRequest            → ContactController@update
```

**Todas** as 7 telas passam pelo mesmo gate `shouldRenderInertiaCliente` — o dual-render é a arquitetura, não um resíduo.

### 5.2 Modelo de dados (núcleo)

<!-- derivado: re-rodável do fonte -->

| Tabela | Colunas que importam | `business_id` |
|---|---|---|
| `contacts` | `type` · `name` · `supplier_business_name` · **`tax_number`** (legacy UPOS) · **`cpf_cnpj`** · `ie` / `inscricao_estadual` · `rg` · `nascimento` · `cargo` · `indicador_ie` · `regime_tributario` · `consumidor_final` · `contribuinte` · `credit_limit` · `opening_balance` · `position` (lat,lng do mapa) · `vip` · `tags` (JSON) · `is_customer/is_supplier/is_employee/is_representative/is_other` ([ADR 0188](../../decisions/0188-contacts-multi-type-flag-aditiva.md)/[0246](../../decisions/0246-tipo-outros-default-migracoes-legacy.md)) · `whatsapp_consent` / `email_consent` | coluna presente — **sem global scope** (§5.4.2) |
| `contact_addresses` | múltiplos endereços por contato (US-CRM-078) | trait `BelongsToBusinessViaParent` + teste cross-tenant ✅ |
| `transactions` | `contact_id` · `type` · `status` · `final_total` · `is_return` | fonte do saldo e dos KPIs |
| `transaction_payments` | `payment_for` (= contato) · `amount` · `is_return` · **`bank_account_number`** · `cheque_number` · `card_transaction_number` | fonte da aba Pagamentos (§6.3) |
| `activity_log` (Spatie) | `log_name = crm.contact` | **`tax_number_1` fora do `logOnly`** por design |

> ⚠️ **Dois campos para o mesmo documento.** `tax_number` (legacy UPOS) e `cpf_cnpj` (canon BR, [ADR 0178](../../decisions/0178-restauracao-campos-fiscais-br-canon.md)) coexistem; o payload faz `cpf_cnpj ?? tax_number`. Idem `ie` (onde o autosave grava) vs `inscricao_estadual` (fallback legacy). US-CRM-074 (backfill) existe justamente por isso. Qualquer consulta que olhe **só um** dos dois lê errado parte da base.

### 5.3 Fluxos críticos

**F1 · Listar clientes (`GET /cliente?type=`)** <!-- derivado: re-rodável do fonte -->

1. A closure de rota fecha a **whitelist** de `type` (`customer · supplier · employee · representative · other · all`); valor fora dela cai em `customer` — **não** 404. É a camada que o incidente #2297 (aba "Outros" caindo em Clientes) obrigou a existir; hoje são **3 camadas** (whitelist da rota + `$types` + `$inertiaTypes`) e o UC-CIDX-01 defende as três.
2. `buildClienteIndexCustomers` monta as rows com `paginate()` **server-side** (`page` + `per_page`), sort por **whitelist** (anti-injeção) com default `id desc` ("recentes"), colunas agregadas via `leftJoinSub` e NULLs por último.
3. `buildClienteIndexKpis` devolve `vips` · `sem_compra_90d` · `novos_mes` por **query real**, com `business_id` explícito **dentro das subqueries** de `transactions` (não só via join) — UC-CIDX-04.
4. `shouldRenderInertiaCliente('cliente_index')` decide React × Blade.
5. `isLegacyAjax()` (= `ajax() && ! hasHeader('X-Inertia')`) separa o partial reload do Inertia dos XHR DataTables legados — sem ele o Inertia recebe JSON cru e quebra (bug de 2026-05-21, PR #1299).

**F2 · Abrir a ficha (drawer 760)** <!-- derivado: re-rodável do fonte -->

1. Clique na linha → o drawer abre **com o dado que já veio na row** (`Index.tsx`), sem round-trip: por isso `buildClienteIndexCustomers` carrega `cpf_cnpj_masked`, `ie`, `rg`, `nascimento`, `cargo` — o fix de 2026-05-27 ("drawer sem IE/CNPJ") foi exatamente adicionar essas colunas ao select.
2. Deep-link: `GET /cliente/{id}` valida `type ∈ {customer, both}` **dentro do business** → `abort(404)` se não achar → **302** para `/cliente?contact_id={id}&tab={tab}`.
3. As abas de **Operações** fazem **self-fetch JSON** (`sales-json`, `payments-json`, `rewards-json`, `subscriptions-json`, `anexos`) — foi o fix de 2026-06-08 (abas presas em "aguardando wiring" porque recebiam prop `undefined`).

**F3 · Salvar cadastro** <!-- derivado: re-rodável do fonte -->

| Caminho | Rota | Validação |
|---|---|---|
| Cadastro novo | `POST /contacts` | `StoreContactRequest` — **mod-11** de CPF/CNPJ (`App\Rules\BR\CpfCnpj`) + `indicador_ie ∈ {1,2,9}` + regime canônico → **422** |
| Edição full-page | `PUT /contacts/{id}` | `UpdateContactRequest` + `processContactUpdate()` (o branch Inertia nasceu no fix de 2026-05-26; antes o corpo inteiro vivia dentro de `if (isLegacyAjax())` e o PUT Inertia devolvia `void`) |
| **Autosave do drawer** | `PATCH /cliente/{id}/{secao}` | `ClienteAutosaveController` — 5 seções, debounce 800ms, **mesma regra mod-11** → 422 |

**F4 · Extrato / saldo (`GET /contacts/ledger`)** `[V0]` <!-- derivado: re-rodável do fonte -->

O saldo do cliente é calculado por **dois caminhos independentes, com SQL próprio**:

```
Util::getContactDue($contact_id, $business_id)                     ← "devendo" na listagem + no PDV
  = Σ(sell final).final_total + Σ(purchase).final_total
    − net_paid_venda            ← SUM(IF(is_return=1, −amount, amount))
    − purchase_paid + opening_balance − opening_balance_paid

TransactionUtil::getLedgerDetails(...)['all_balance_due']          ← rodapé do extrato
```

Os dois **têm que convergir** — é a dupla-confirmação da REGRA MESTRE feita máquina (UC-CLED-03). `action=pdf` **força a Blade** (`getLedger` só renderiza Inertia quando `input('action') !== 'pdf'`): o PDF do extrato é legado vivo, não fóssil.

**F5 · Import em massa (`/contacts/import`)** <!-- derivado: re-rodável do fonte -->

`getImportContacts` devolve `zip_available` (banner quando falta a extensão PHP Zip) e renderiza `Cliente/Import` ou o Blade. O `postImportContacts` (multipart, 27 colunas fixas UPOS) **não tem preview, nem dedupe, nem relatório de linhas rejeitadas** — é o gap C-import da ficha e a US-CRM-082.

**F6 · Lookup CNPJ/CEP** <!-- derivado: re-rodável do fonte -->

`Modules\Crm\Services\BrLookupService` (BrasilAPI CNPJ + ViaCEP) **server-side**, cache TTL 30d/90d, retry 2×, timeout 4s — o charter da Index proíbe explicitamente a chamada client-side.

### 5.4 Onde os dois mundos ainda não se conversam

#### 5.4.1 A Blade serve `type='both'` com 2 abas que o React não tem ⚙️ derivado · medido 2026-07-27

`contact/show.blade.php:66-82` abre, para `type ∈ {both, supplier}`, as abas **Compras** (`purchases_tab`) e **Relatório de estoque** (`stock_report_tab`). Varredura contada de `resources/js/Pages/Cliente/_show/` = **13 arquivos**; nenhum é `PurchasesTab` ou `StockReportTab`. O `_drawer/OssTab` reusa **7** dos 13 — nenhum deles tampouco.

E a rota `/cliente/{id}` aceita `type ∈ {customer, **both**}`. Logo: **um contato `both` (cliente E fornecedor) alcança a ficha React e perde as duas abas que a Blade lhe dá**. Não é vazamento nem erro de cálculo — é **perda de superfície na migração**, a classe exata que o MWART existe para impedir ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)).

Vira **CU-CLI-15** `[should]` `[reg]`. **Nenhum charter declara isso como Non-Goal** — a decisão (implementar ou virar Non-Goal) é de [W].

#### 5.4.2 `App\Contact` não tem global scope — a doc diz que tem ⚙️ derivado · medido 2026-07-27

**Varredura contada:** `grep -c "addGlobalScope" app/Contact.php` → **0**. Os traits da classe são `Notifiable`, `SoftDeletes`, `LogsActivity` — nada de tenant. O padrão canônico existe no repo (`app/Concerns/HasBusinessScope.php` + `ScopeByBusiness`) e é usado por 10 Entities do ComunicacaoVisual e pelo `Arquivo`; **`Contact` não o usa**. O filho `ContactAddress` usa `BelongsToBusinessViaParent` e **tem** teste cross-tenant; o **pai não**.

Isso contradiz frontalmente o [`SPEC.md`](SPEC.md) §1, que afirma *"`App\Contact` usa global scope `business_id` (UPOS canon). TODA query passa por scope automático."* — **corrigido neste PR** (era um fato verificável, não intenção; Fase 2.6 do [ADR 0351](../../decisions/0351-sdd-from-source.md)). O próprio SPEC já contradizia a si mesmo: **US-CRM-080** se chama literalmente *"Teste cross-tenant no `App\Contact` pai + avaliar global scope (Tier 0)"*.

**Consequência prática:** o isolamento do cadastro repousa **inteiramente** em `where('business_id')` escrito à mão em cada uma das ~126 chamadas `Contact::where(...)` / `Contact::find(...)` de `app/` + `Modules/`. Uma esquecida = vazamento Tier 0 silencioso. **Não afirmo que exista vazamento hoje** — não varri as 126 uma a uma nem rodei teste que prove; afirmo que **a defesa é disciplina, não mecanismo**, e que a doc dizia o contrário. Por isso todo CU deste SDD que toca leitura de contato carrega `[T0]`.

#### 5.4.3 O "mascaramento" de CPF/CNPJ **formata**, não redige ⚙️ derivado · medido 2026-07-27

**Varredura contada:** `grep -rn "maskTaxNumber" --include=*.php` (excluindo `memory/`) → **19 ocorrências**, **2 implementações** (`ContactController:419` e `ClienteAutosaveController:711`). As duas fazem o mesmo:

```php
$digits = preg_replace('/\D/', '', $taxNumber);
if (strlen($digits) === 11)  return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits);
if (strlen($digits) === 14)  return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $digits);
```

`12345678901` sai como `123.456.789-01`. **Nenhum dígito é escondido** — é formatação. O código é honesto sobre isso; o docblock do `ClienteAutosaveController::maskTaxNumber` diz textualmente: *"formata visualmente mas mantem digitos visiveis porque a logica canon UPOS ... faz so formatacao, nao redact ... futura ADR pode endurecer pra realmente censurar."*

**A documentação é que não é.** Quatro artefatos canon leem como redação:

| Artefato | O que afirma |
|---|---|
| [`SPEC.md`](SPEC.md) §2 | *"`cpf_cnpj`, `ie_rg`, `bank_account_number` **mascarados** via `maskTaxNumber($value)` ANTES do Inertia props"* |
| `Index.charter.md` §Anti-hooks | *"CPF/CNPJ mascarado server-side (`tax_number_masked`); telefone idem"* |
| `Show.charter.md` §Anti-hooks | *"CPF/CNPJ mascarado via `maskTaxNumber`"* |
| [`CAPTERRA-FICHA.md`](CAPTERRA-FICHA.md) C03 | *"PII masking server-side ... **à frente de TODO ERP BR**"* — o diferencial nº 1 declarado do módulo |

E os **4 testes que "provam" o masking** (`ClienteDrawerRowsCanonBrPayloadTest`, `ClienteListagemTurbinadaTest`, `Wave1IndexBaselineTest`, e o guard do payload) fazem `file_get_contents` do Controller e checam que **a chamada está escrita** — presença de chamada, nunca efeito no payload. É L-24 (*presença ≠ correção*) na forma pura: renomear a função mantém o teste verde; censurar de verdade também. Dois deles estão em `@group legacy-quarantine`.

> ⚠️ **Divergência aberta — decisão de [W], não do agente.** *Deve* o CPF sair censurado (`123.***.**9-01`) para o front? É decisão de **produto + jurídico**, com custo real (a Larissa confere documento na tela, e a NFe precisa do número). **Não escrevi teste que trave o comportamento atual** — travar o desvio é o anti-padrão de [proibicoes §5](../../proibicoes.md) 2026-06-05. O que este PR faz é (a) registrar a medição, (b) **não** repetir a palavra "mascarado" como se fosse redação, e (c) contratar o único ponto onde a redação **é real** (§6.3, o `bank_account_number` do `paymentsJson`).
>
> `bank_account_number` **é** redigido de verdade (`'****' . substr($n, -4)`, `ContactController:243`). `cheque_number` e `card_transaction_number` saem **inteiros** no mesmo payload — registrado, sem juízo.

#### 5.4.4 O titular não tem porta de saída ⚙️ derivado

O `DsrService` / `LgpdEsquecerTitularTool` cobrem **chat e memória da Jana**, não `contacts` — varredura da ficha, confirmada por US-CRM-079 e US-CRM-085 existirem como pendentes. Não há anonimização fiscal-aware nem export de portabilidade do registro. É o eixo C04 da §0.3. Vira **CU-CLI-12** ⬜ **ausente**.

#### 5.4.5 O extrato ainda cai na Blade ao filtrar ⚙️ derivado

US-CRM-064 e US-CRM-084 registram: o `LedgerTab` renderiza inline, mas **ao filtrar abre o Blade legacy**. A âncora da US-CRM-064 no SPEC já é `_parcial_` por isso. `[BACKLOG]`, não CU novo — a US existe e nomeia o gap.

#### 5.4.6 Dois Non-Goals de charter que o código já contradiz ⚠️ divergência aberta

Non-Goal é **INTENÇÃO** — o agente é proibido de corrigir ([ADR 0351](../../decisions/0351-sdd-from-source.md) Fase 2.6). Registrados nos dois lados para [W]:

| Charter | Non-Goal | O que o código faz |
|---|---|---|
| `Show.charter.md` | *"❌ Tab Atividades — escopo futuro"* · *"❌ Tab Pessoas de contato — escopo futuro"* · *"❌ Tab Assinaturas — escopo futuro"* | `_show/ActivitiesTab.tsx`, `PessoasContatoTab.tsx`, `SubscriptionsTab.tsx` **existem**, e as US-CRM-068/069 estão `done` no SPEC |
| `Create.charter.md` | *"❌ Lookup CEP automático ViaCEP (futuro; nice-to-have)"* | `BrLookupService` faz ViaCEP server-side; US-CRM-075 `done` |
| `Index.charter.md` | *"❌ Show.tsx full-page (DELETADO no mesmo PR — Q1)"* | `resources/js/Pages/Cliente/Show.tsx` **existe** e é servido quando `cliente_show` liga |

O padrão é o mesmo nos três: **o Non-Goal caducou** (a feature nasceu depois). Mas "caducou" é leitura minha; a caneta é de [W].

---

## 6. Casos de uso

> **Derivados das 3 fontes** (canon → código → Blade), nunca só do `.tsx` ([ADR 0351](../../decisions/0351-sdd-from-source.md) D-A). Estado vem do **veredito da lane**, nunca da leitura: `✅` provado por teste verde que o cita · `🧪` teste existe e aguarda veredito · `🟡` parcial · `🔴` falso/quebrado · `⬜` não-verificado.
>
> ⚖️ **A lane `PHP / Pest (Cliente · MySQL)` é ADVISORY** — ela nasce neste PR e **não** está em [`governance/required-checks-baseline.json`](../../../governance/required-checks-baseline.json). Reprova visível, **não bloqueia merge**.
>
> **Mapa CU → UC:** os 21 UC dos `casos.md` **já existiam** e não foram reescritos. Cada CU abaixo cita quais UC o realizam; onde não há UC, o CU está `⬜` e o motivo está dito.

### 6.1 Cadastro (`CU-CLI-01..04`)

#### CU-CLI-01 — Cadastrar cliente com documento fiscal válido `[must]` 🧪
<!-- derivado: re-rodável do fonte (F3 · StoreContactRequest · App\Rules\BR\CpfCnpj · contact/create.blade.php) -->
*Dado* o formulário de novo cliente; *quando* envio o cadastro; *então* documento que não passa no mod-11 é barrado no campo, e documento válido entra sem atrito.

1. `[must]` CPF **ou** CNPJ que falha no dígito verificador → **422** com erro em `cpf_cnpj`, e o cadastro **não** é criado. — *UC-CCRE-01*
2. `[must]` `indicador_ie` fora de `{1,2,9}` ou regime fora do conjunto canônico → **422**. — *UC-CCRE-02*
3. `[must]` documento válido → **sem** erro em `cpf_cnpj` e o cliente é criado (anti-falso-positivo). — *UC-CCRE-03*
4. `[reg]` a Blade `contact/create.blade.php` aceitava qualquer string (o `$request->only([...])` de antes do slice US-CRM-076). Endurecer é **melhoria deliberada**, não regressão.

#### CU-CLI-02 — Editar o cadastro sem perder os campos fiscais `[must]` 🧪
<!-- derivado: re-rodável do fonte (F3 · ContactController@edit/@update · Cliente/Edit.tsx · contact/edit.blade.php) -->
*Dado* um cliente existente; *quando* abro a edição e salvo; *então* os campos BR vêm preenchidos e a alteração persiste com confirmação.

1. `[must]` `GET /contacts/{id}/edit` traz os campos BR **não-nulos** em `props.contact` (bug de 2026-05-26: `edit()` os omitia). — *UC-CEDI-01*
2. `[must]` `PUT /contacts/{id}` persiste e redireciona com flash — o branch Inertia existe e não devolve `void`. — *UC-CEDI-02*
3. `[T0]` cliente de outro business → **404** (não 403: não revelar existência). — *UC-CEDI-03*

#### CU-CLI-03 — Salvar campo-a-campo no drawer sem perder a validação `[must]` 🧪
<!-- derivado: re-rodável do fonte (F3 · ClienteAutosaveController · _drawer/IdentificacaoTab.tsx) -->
*Dado* o drawer 760 aberto; *quando* saio de um campo (autosave on blur); *então* a mesma regra fiscal do formulário completo se aplica.

1. `[must]` `PATCH /cliente/{id}/identificacao` com documento que falha no mod-11 → **422**; válido → **200** e persiste. — *UC-CEDI-04*
2. `[must]` a validação do autosave **não** pode ser mais frouxa que a do `StoreContactRequest` — dois contratos do mesmo módulo não podem divergir.
3. `[BACKLOG]` as outras 4 seções (contato · endereço · comercial · classificação) não têm UC — o `ClienteDrawerCadastroAutosaveTest` já as exercita, falta declarar.

#### CU-CLI-04 — Autopreencher por CNPJ e CEP `[should]` ⬜
<!-- derivado: re-rodável do fonte (F6 · BrLookupService · ClienteLookupController) -->
*Dado* que digitei um CNPJ (ou CEP); *quando* peço a busca; *então* razão social / endereço vêm preenchidos, **sempre por proxy server-side**.

1. `[should]` lookup roda **server-side** com cache; chamada client-side à BrasilAPI/ViaCEP é proibida pelo charter.
2. `[should]` falha do provedor externo **degrada**, não bloqueia o cadastro.
3. ⬜ **sem UC** — o `ClienteLookupCnpjCepTest` existe (`Testado em:` de US-CRM-075) mas nenhum `casos.md` o cita. É o candidato mais barato de próximo UC.
4. ⚠️ o `Create.charter.md` lista ViaCEP como **Non-Goal** e o código o faz — §5.4.6.

### 6.2 Listagem, ficha e extrato (`CU-CLI-05..09`)

#### CU-CLI-05 — Consultar a lista pelo tipo de contato `[must]` 🧪
<!-- derivado: re-rodável do fonte (F1 · routes/web.php cliente.index · ContactController@index · contact/index.blade.php) -->
*Dado* a listagem; *quando* escolho uma aba de tipo; *então* vejo a lista daquele tipo — inclusive **"Outros"**.

1. `[must]` `GET /cliente?type=other` renderiza Inertia com `activeType=other`, **não** `customer`; as 3 camadas (whitelist da rota + `$types` + `$inertiaTypes`) aceitam `other` ([ADR 0246](../../decisions/0246-tipo-outros-default-migracoes-legacy.md)). — *UC-CIDX-01*
2. `[must]` `type` fora da whitelist **normaliza** para `customer` (a rota fecha) — não 500, não SQL livre.
3. `[must]` o componente renderizado é `Cliente/Index` quando a flag liga; Blade quando não.

#### CU-CLI-06 — Paginar e ordenar no servidor `[must]` 🧪
<!-- derivado: re-rodável do fonte (F1 · buildClienteIndexCustomers · Index.tsx) -->
*Dado* uma base com milhares de clientes; *quando* mudo de página ou ordeno; *então* o servidor re-busca — a tela não filtra as 50 linhas que já tem.

1. `[must]` mudar de página manda `page` + `per_page` ao servidor; mudar `per_page` **reseta** para a página 1. — *UC-CIDX-02*
2. `[must]` o sort é server-side com **whitelist** (anti-injeção), default = recentes (`id desc`), NULLs por último. — *UC-CIDX-03*
3. `[reg]` a Blade oferece os filtros do DataTables legado; o cockpit oferece 6 dropdowns (Tipo/Status/UF/Tags/Sem compra/Saldo) — os 6 estão em `[BACKLOG]` no `Index.casos.md`, sem UC.

#### CU-CLI-07 — Ver KPIs que vieram de query, isolados por tenant `[must]` `[T0]` 🧪
<!-- derivado: re-rodável do fonte (F1 · buildClienteIndexKpis) -->
*Dado* a lista carregada; *quando* os cards do topo são computados; *então* são números do negócio inteiro, não estimativa da página.

1. `[must]` `vips` (`vip=1`) · `novos_mes` (`created_at >= startOfMonth`) · `sem_compra_90d` (já comprou, mas nada em 90d) vêm de query. — *UC-CIDX-04*
2. `[T0]` as subqueries de `transactions` filtram `business_id` **explicitamente**, não só via join em `contacts`. — *UC-CIDX-04*
3. `[BACKLOG]` clicar num KPI aplicar o filtro (toggle 2×) — precisa de e2e.

#### CU-CLI-08 — Saber quanto o cliente deve `[must]` `[V0]` 🧪
<!-- derivado: re-rodável do fonte (F4 · Util::getContactDue · TransactionUtil::getLedgerDetails · contact/ledger.blade.php) -->
*Dado* um cliente com vendas, pagamentos e devoluções; *quando* olho o saldo; *então* o número é o mesmo na listagem e no rodapé do extrato.

1. `[V0]` a devolução (`is_return=1`) entra com o **sinal certo** e **sem dupla contagem**; uma versão que a ignore dá número diferente (discriminação). — *UC-CLED-01*
2. `[V0]` devolução de valor X sobe o saldo em **exatamente** +X (property). — *UC-CLED-02*
3. `[V0]` **dupla-confirmação**: `Util::getContactDue` (resumo) converge com `getLedgerDetails['all_balance_due']` (extrato) — os dois caminhos independentes da REGRA MESTRE. — *UC-CLED-03*
4. `[V0]` parcelar um pagamento não muda o saldo (aditividade). — *UC-CLED-05*
5. ⛔ **nenhum destes testes altera método de cálculo.** Mexer em `getContactDue`/`getLedgerDetails` é mudança de valor em prod → US separada, sob dupla-confirmação + antes→depois + OK [W].

#### CU-CLI-09 — Ver o extrato sem somar tenant vizinho `[must]` `[T0]` 🧪
<!-- derivado: re-rodável do fonte (F4 · __transactionQuery/__paymentQuery) -->
*Dado* um cliente homônimo em outro `business_id`; *quando* computo o saldo do meu tenant; *então* o lançamento estrangeiro **nunca** entra na conta.

1. `[T0]` lançamento de outro business não soma. — *UC-CLED-04*
2. `[reg]` `action=pdf` continua indo pela Blade (`contact/ledger.blade.php` + formatos 2 e 3) — é legado **vivo**, não fóssil; não some sem Non-Goal.
3. `[BACKLOG]` os 3 KPI cards do extrato (débitos/créditos/saldo) somarem a tabela.

### 6.3 PII e isolamento — o eixo Tier 0 do módulo (`CU-CLI-10..12`)

#### CU-CLI-10 — Não vazar dado bancário na aba Pagamentos `[must]` `[T0]` 🧪 **contrato novo neste PR**
<!-- derivado: re-rodável do fonte (F2 · ContactController@paymentsJson:194-250 · US-CRM-063 · Index/Show charter §Anti-hooks) -->
*Dado* um pagamento por transferência com número de conta; *quando* abro a aba Pagamentos do cliente; *então* vejo o pagamento e **não** recebo a conta inteira.

1. `[must]` o endpoint entrega os pagamentos do contato — **pré-condição anti-vácuo**: sem provar que o pagamento viajou, "não vazou" passaria por lista vazia ([proibicoes §5](../../proibicoes.md) 2026-07-24).
2. `[T0]` o número da conta **não aparece em ponto nenhum do corpo da resposta** — a asserção varre o JSON cru, não uma chave: renomear a chave não faz o vazamento passar.
3. `[T0]` o que sobra é reconhecível como redigido + os 4 últimos dígitos (o operador confere sem receber a conta).
4. `[T0]` pagamento de **outro contato do mesmo tenant** não entra na aba (`payment_for` escopado).
5. `[T0]` contato de **outro business** → **404**, nunca 403 nem 200 — e o teste prova que o contato **existe**, senão o 404 mediria ausência, não isolamento.
6. — *UC-CSHW-03* (novo), `tests/Feature/Cliente/ClientePagamentosPiiTest.php`. Promove o `[BACKLOG]` mais antigo do `Show.casos.md` ("Aba Pagamentos (self-fetch AJAX)").

> Este é o **único** ponto do módulo com redação real de PII (§5.4.3). Todo o resto do "mascaramento" é formatação, e os guards existentes são source-grep.

#### CU-CLI-11 — Isolar o cadastro por business em toda superfície `[must]` `[T0]` 🧪
<!-- derivado: re-rodável do fonte (§5.4.2 · ADR 0093) -->
*Dado* clientes nos businesses 1 e 99; *quando* opero no business 1; *então* nada do 99 aparece — em **nenhuma** das 7 telas.

1. `[T0]` edição de contato estrangeiro → **404**. — *UC-CEDI-03*
2. `[T0]` o mapa não lista contato estrangeiro em `all_contacts`. — *UC-CMAP-02*
3. `[T0]` o saldo não soma lançamento estrangeiro. — *UC-CLED-04*
4. `[T0]` a aba Pagamentos → 404. — *UC-CSHW-03*
5. `[T0]` **a LISTAGEM (`GET /cliente`) não tem UC de cross-tenant** — ⬜. O `ClienteKpisServerSideTest` cobre os KPIs por *source-grep*, não a lista. É a lacuna Tier 0 mais relevante que sobra, e é exatamente o que **US-CRM-080** pede.
6. `[must]` ⚠️ nenhuma destas defesas vem de global scope — vêm de `where('business_id')` manual (§5.4.2). O CU é sobre o **efeito**; o **mecanismo** é decisão aberta de [W] (US-CRM-080).

#### CU-CLI-12 — Atender o titular do dado (esquecimento e portabilidade) `[should]` ⬜ **ausente**
<!-- derivado: re-rodável do fonte (§5.4.4 · CAPTERRA C04 · US-CRM-079/083/085 · LGPD Art. 18) -->
*Dado* um titular que exerce direito Art. 18; *quando* peço anonimização ou portabilidade; *então* o registro é anonimizado **preservando o dado fiscal obrigatório**, ou exportado por inteiro.

1. `[should]` anonimização **fiscal-aware**: apaga PII, preserva o que NF/retenção exigem, com trilha append-only. — US-CRM-079
2. `[should]` export de portabilidade do registro completo (CSV/JSON), não só o PDF do extrato. — US-CRM-085
3. `[should]` base legal por finalidade + opt-in/opt-out na UI (as colunas `whatsapp_consent`/`email_consent` já existem). — US-CRM-083
4. ⬜ **nada disso existe**; as 3 US são `backlog`. Não escrevi UC — seria **UC órfão**, que o `casos-gate` G-2 pune e que **bloqueia o merge de quem for implementar** ([proibicoes §5](../../proibicoes.md) 2026-07-16).

### 6.4 Import, mapa e paridade (`CU-CLI-13..15`)

#### CU-CLI-13 — Importar clientes em massa `[should]` 🧪
<!-- derivado: re-rodável do fonte (F5 · getImportContacts · contact/import.blade.php) -->
*Dado* uma planilha de clientes; *quando* abro a importação; *então* vejo o assistente React, com aviso quando o PHP Zip falta.

1. `[should]` `GET /contacts/import` renderiza `Cliente/Import` quando a flag liga (nunca o Blade silenciosamente). — *UC-CIMP-01*
2. `[should]` o payload traz `zip_available` (banner).
3. `[BACKLOG]` template XLSX 27 colunas · upload valida extensão e devolve contagem sucesso/erro · **dedupe por CPF/CNPJ** (US-CRM-082, o gap de mercado da ficha).

#### CU-CLI-14 — Ver os clientes no mapa `[should]` `[T0]` 🧪
<!-- derivado: re-rodável do fonte (ContactController@contactMap · contact/contact_map.blade.php) -->
*Dado* clientes com `position`; *quando* abro `/contacts/map`; *então* vejo mapa + lista pesquisável, só do meu business.

1. `[should]` renderiza `Cliente/Map` com `contacts` (com posição) e `all_contacts` (todos). — *UC-CMAP-01*
2. `[T0]` contato de outro business não aparece em `all_contacts`. — *UC-CMAP-02*
3. `[BACKLOG]` busca no aside · seleção renderiza o iframe · badge "Sem posição".

#### CU-CLI-15 — Não perder as abas de fornecedor do contato `both` `[should]` `[reg]` ⬜ **previsto ausente** (§5.4.1)
<!-- derivado: re-rodável do fonte (contact/show.blade.php:66-82 · routes/web.php cliente.show · _show/ 13 arquivos) -->
*Dado* um contato `type='both'` (cliente **e** fornecedor); *quando* abro a ficha dele; *então* vejo também **Compras** e **Relatório de estoque** — como a Blade lhe dá hoje.

1. `[should]` `[reg]` a ficha de `both` oferece a aba **Compras**.
2. `[should]` `[reg]` a ficha de `both` oferece o **Relatório de estoque**.
3. ⬜ **sem UC e sem teste** — deliberado: escrever UC agora criaria órfão (não há implementação a defender). É `[BACKLOG]` no `Show.casos.md` até [W] decidir entre **implementar** (vira US + UC) e **Non-Goal** (vira linha no charter, que só [W] escreve).

### 6.5 Non-Goals — **só [W] preenche**

> O agente é **proibido** de inferir Non-Goal ([ADR 0351](../../decisions/0351-sdd-from-source.md) Fase 2.6). Os Non-Goals vigentes vivem nos 7 `*.charter.md` e **não foram tocados** por este PR — inclusive os três que o código já contradiz (§5.4.6), que ficam registrados como divergência aberta.
>
> **Decisões que este SDD devolve a [W]** (nenhuma tomada pelo agente):
>
> 1. CPF/CNPJ deve ser **censurado** no payload, ou "mascarado = formatado" é o comportamento desejado? (§5.4.3)
> 2. `App\Contact` ganha **global scope**, ou o `where()` manual continua sendo a defesa? (§5.4.2 · US-CRM-080)
> 3. Contato `both` na ficha React: **implementar** as 2 abas ou declarar **Non-Goal**? (§5.4.1 · CU-CLI-15)
> 4. Os 3 Non-Goals caducados dos charters: **revogar** ou o código é que excedeu a lei? (§5.4.6)
> 5. CU-CLI-12 (direitos do titular): prioridade real, ou Non-Goal para PME? (§5.4.4)

---

## 7. Requisitos não-funcionais

<!-- derivado: re-rodável do fonte -->

| Eixo | Alvo | Onde está escrito |
|---|---|---|
| First-paint da lista | p95 <600ms (props caras deferidas) | `Index.charter.md` §UX Targets |
| Abertura do drawer | p95 <200ms (partial reload `only:[tab]`) | idem |
| Autosave round-trip | p95 <400ms (PATCH + UI otimista, debounce 800ms) | idem |
| Lookup CNPJ/CEP | p95 <800ms (cache hit) · <2,5s (miss + fallback), timeout 4s, retry 2× | idem · `BrLookupService` |
| Extrato | first-paint p95 <1000ms para 100 lançamentos · PDF <3s | `Ledger.charter.md` |
| Viewport | 1280×1024 sem scroll horizontal com drawer 760 + sidebar 240 | `Index.charter.md` (teste de charter) |
| Privacidade | **não** emitir log de "viewed" (só mutação entra no Spatie) | Anti-hooks de `Index`/`Show` |
| Observabilidade | [`../Crm/OBSERVABILITY.md`](../Crm/OBSERVABILITY.md) | — |

---

## 8. Estratégia de qualidade e rollout

<!-- derivado: re-rodável do fonte -->

**As três portas de "roda / é cobrado"** — medidas em `origin/main`, 2026-07-27, **antes** deste PR:

| Pergunta | Porta | Cliente antes | Cliente depois |
|---|---|---|---|
| roda em algum lugar? | `phpunit.xml` (`./tests/Feature` recursivo) | ✅ nightly CT 100 | ✅ |
| **roda no PR?** | `.github/ci-sqlite-pest.list` (allowlist) | ❌ **0 entradas** | ✅ [`cliente-pest.yml`](../../../.github/workflows/cliente-pest.yml) |
| bloqueia merge? | [`governance/required-checks-baseline.json`](../../../governance/required-checks-baseline.json) | ❌ não | ❌ **segue não** (advisory) |

O recibo que motivou a lane, do `anchor-lint` sobre o `SPEC.md`: **`15 US com teste-que-cobre fora das lanes de JUnit (verde impossível até entrar numa lane)`**. Os 21 UC estavam ancorados; os testes rodavam só de madrugada.

**Rollout:** as 7 telas são **flag-gated por business** (`MWART_CLIENTE_*`), com fallback Blade automático. É o canary mais granular do repo — e a razão pela qual a Blade é fonte 3 **viva**, não arqueologia. Rollback = desligar a flag daquele business, sem deploy.

**Gap reportado, não consertado** (arquivo fora da área do chip): `Modules/Crm/Tests/Feature` tem **14 arquivos** e **não está em `phpunit.xml`** — não roda nem no nightly. Falsa cobertura da classe que a proibição *"`Modules/X/Tests` sem CI"* nomeia.

---

## 9. Riscos e dívidas conhecidas

<!-- curado: foto que envelhece -->

| # | Risco | Gravidade | Onde |
|---|---|---|---|
| R1 | Isolamento do cadastro depende de `where()` manual em ~126 chamadas, sem global scope e sem teste no pai | **Tier 0** | §5.4.2 · US-CRM-080 |
| R2 | A doc vende "PII masking" onde há formatação; os guards são source-grep | **alto** (é o diferencial nº 1 declarado) | §5.4.3 |
| R3 | Contato `both` perde 2 abas na ficha React | médio (paridade) | §5.4.1 · CU-CLI-15 |
| R4 | Titular sem esquecimento nem portabilidade | **legal** (LGPD Art. 18) | §5.4.4 · CU-CLI-12 |
| R5 | `tax_number` × `cpf_cnpj` (e `ie` × `inscricao_estadual`) coexistem; consulta que olhe um só lê errado parte da base | médio | §5.2 · US-CRM-074 |
| R6 | Extrato abre Blade ao filtrar (US-CRM-064 `_parcial_`) | baixo | §5.4.5 · US-CRM-084 |
| R7 | Import sem preview/dedupe/relatório de rejeição | médio | §5.4 F5 · US-CRM-082 |
| R8 | Sem `memory/dominio/cliente.md` → enums de `contacts.type` fora do `dominio-gate` | baixo | §3 |
| R9 | `Modules/Crm/Tests/Feature` fora do `phpunit.xml` (14 arquivos, 0 execuções) | médio | §8 |

---

## 10. Roadmap de evolução

<!-- curado: foto que envelhece — [W] prioriza -->

| Trilha | Próximo passo | US |
|---|---|---|
| **Tier 0** | teste cross-tenant no `Contact` pai + decidir global scope | US-CRM-080 |
| **LGPD** | anonimização fiscal-aware · export de portabilidade · UI de consentimento | US-CRM-079 · 085 · 083 |
| **Cadastro** | múltiplos endereços + seletor na venda | US-CRM-078 |
| **Crédito** | limite com bloqueio/aviso na venda | US-CRM-081 |
| **Import** | preview + dedupe/merge por CPF/CNPJ | US-CRM-082 |
| **Extrato** | render inline 100% (parar de abrir Blade ao filtrar) | US-CRM-084 |
| **Paridade** | decidir CU-CLI-15 (abas de fornecedor no `both`) | — (decisão [W]) |

---

## 11. Referências

- [`SPEC.md`](SPEC.md) · [`BRIEFING.md`](BRIEFING.md) · [`CAPTERRA-FICHA.md`](CAPTERRA-FICHA.md) · [`CAPTERRA-INVENTARIO.md`](CAPTERRA-INVENTARIO.md) · [`clientes-gap.md`](clientes-gap.md)
- RUNBOOKs e visual-comparisons em [`../Crm/`](../Crm/) (8 + 8) · [`../Crm/PII-REDACTION.md`](../Crm/PII-REDACTION.md) · [`../Crm/SUPERFICIE.md`](../Crm/SUPERFICIE.md)
- Charters e casos: `resources/js/Pages/Cliente/{Index,Show,Create,Edit,Import,Ledger,Map}.{charter,casos}.md`
- Lane: [`.github/workflows/cliente-pest.yml`](../../../.github/workflows/cliente-pest.yml)
- ADRs: [0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) · [0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [0179](../../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md) · [0246](../../decisions/0246-tipo-outros-default-migracoes-legacy.md) · [0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md) · [0301](../../decisions/0301-separar-cliente-deprecar-crm-pipeline.md) · [0351](../../decisions/0351-sdd-from-source.md)
- Porta viva: `node scripts/governance/requisitos-status.mjs Cliente`

---

## Changelog

| Data | Versão | O quê |
|---|---|---|
| 2026-07-27 | 1.0.0 | Nascimento. Chip S-Cliente da Onda 2 do passo 5, agent `sdd-from-source`. §5/§6 derivados de 3 fontes (Delphi ausente, declarado). 15 CU propostos a partir dos **21 UC pré-existentes** (nenhum reescrito) + 1 UC novo (UC-CSHW-03, PII bancária). Três achados medidos: `Contact` sem global scope (§5.4.2, contradiz o SPEC §1 — corrigido no mesmo PR), `maskTaxNumber` formata em vez de redigir (§5.4.3, divergência aberta pra [W]), contato `both` perde 2 abas (§5.4.1). |

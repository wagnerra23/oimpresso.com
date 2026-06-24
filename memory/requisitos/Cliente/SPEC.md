---
module: Cliente
version: "2.3"
status: ativo
owners: [W]
last_updated: "2026-06-24"
anchor_format: "v1"
us_count: 15
us_list: [US-CRM-063, US-CRM-064, US-CRM-065, US-CRM-066, US-CRM-067, US-CRM-068, US-CRM-069, US-CRM-070, US-CRM-071, US-CRM-072, US-CRM-073, US-CRM-074, US-CRM-075, US-CRM-076, US-CRM-078]
related_adrs: [0093-multi-tenant-isolation-tier-0, 0104-processo-mwart-canonico-unico-caminho, 0107-emendation-0104-visual-comparison-gate-f3, 0110-cockpit-pattern-v2-canon-list-detail, 0114-prototipo-ui-cowork-loop-formalizado, 0149-mwart-screen-pattern-reuse-cowork, 0179-cliente-drawer-760px-substitui-show-fullpage, 0273-anchor-spec-codigo-formato-canonico-fluxo-novo, 0301-separar-cliente-deprecar-crm-pipeline, 0303-anchor-lint-wired-testado-sa-a2-bis]
---

# SPEC — Cliente

> 🪪 **Cliente ≠ CRM (Wagner 2026-06-22):** este é o SPEC **canônico do cadastro de Cliente / contatos** — separado do *pipeline CRM* (leads/propostas/campanhas), que está em **depreciação**. O código ainda vive fisicamente em `Modules/Crm/` (rename de módulo não feito ainda), mas o canon do cadastro mora aqui em `memory/requisitos/Cliente/`. Renomeado de `Crm/SPEC-us-063-078.md` em 2026-06-22. Ver [doc de desambiguação](../../reference/crm-e-o-modulo-de-cliente.md) + plano de depreciação do pipeline.

> **Naming técnico:** as Pages Inertia vivem em `resources/js/Pages/Cliente/` (rota PT-BR `/cliente`); o código backend ainda em `Modules/Crm/` (UPOS legacy contacts, ADR 0149). Este SPEC consolida as US tocadas pelas Pages `Cliente/{Index,Create,Edit,Show}.tsx` durante 2026-04 → 2026-05.

> 🔗 **Âncoras spec↔código (ADR 0273, `anchor_format: v1`):** cada US abaixo carrega `**Implementado em:**` apontando o arquivo real, verificado por `existsSync` em `origin/main@3b425d8` (2026-06-24). ✅ Como este arquivo agora é `Cliente/SPEC.md`, **o `anchor-lint.mjs` passa a lê-lo** (a máquina está ligada para o cadastro). Relatório: [audits/ALINHAMENTO-cliente-2026-06-22.md](audits/ALINHAMENTO-cliente-2026-06-22.md).

## Missão

Gerenciar cadastro de clientes (PF e PJ) com canon BR completo (fiscais + endereço + contato) e tela de detalhe rica (header + 4 stats + 4 tabs + dropdown ações + sidebar com bloco fiscal BR + drawer histórico OS no Index).

## Personas

- **Larissa** [L] @ ROTA LIVRE (biz=4 vestuário) — dona PME, monitor 1280×1024, balcão de venda
- **Eliana** [E] (futuro) — financeiro escritório, fechamento mensal

## Escopo do módulo

| Page Inertia | Rota | Charter | RUNBOOK | visual-comparison |
|---|---|---|---|---|
| Cliente/Index | `/cliente` | [`Index.charter.md`](../../../resources/js/Pages/Cliente/Index.charter.md) (live) | [RUNBOOK-cliente-index.md](../Crm/RUNBOOK-cliente-index.md) | [cliente-index-visual-comparison.md](../Crm/cliente-index-visual-comparison.md) |
| Cliente/Create | `/contacts/create` | [`Create.charter.md`](../../../resources/js/Pages/Cliente/Create.charter.md) (live) | [RUNBOOK-cliente-create.md](../Crm/RUNBOOK-cliente-create.md) | [cliente-create-visual-comparison.md](../Crm/cliente-create-visual-comparison.md) |
| Cliente/Edit | `/contacts/{id}/edit` | [`Edit.charter.md`](../../../resources/js/Pages/Cliente/Edit.charter.md) (live) | [RUNBOOK-cliente-edit.md](../Crm/RUNBOOK-cliente-edit.md) | [cliente-edit-visual-comparison.md](../Crm/cliente-edit-visual-comparison.md) |
| Cliente/Show | `/cliente/{id}` (canon) · `/contacts/{id}` (dual-render) | [`Show.charter.md`](../../../resources/js/Pages/Cliente/Show.charter.md) (**superseded** → drawer 760px, [ADR 0179](../../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md)) | [RUNBOOK-cliente-show.md](../Crm/RUNBOOK-cliente-show.md) | [cliente-show-visual-comparison.md](../Crm/cliente-show-visual-comparison.md) |
| Cliente/Import | `/contacts/import` | [`Import.charter.md`](../../../resources/js/Pages/Cliente/Import.charter.md) (live) | [RUNBOOK-cliente-import.md](../Crm/RUNBOOK-cliente-import.md) | [cliente-import-visual-comparison.md](../Crm/cliente-import-visual-comparison.md) |
| Cliente/Ledger | `/contacts/ledger` | [`Ledger.charter.md`](../../../resources/js/Pages/Cliente/Ledger.charter.md) (live) | [RUNBOOK-cliente-ledger.md](../Crm/RUNBOOK-cliente-ledger.md) | [cliente-ledger-visual-comparison.md](../Crm/cliente-ledger-visual-comparison.md) |
| Cliente/Map | `/contacts/map` | [`Map.charter.md`](../../../resources/js/Pages/Cliente/Map.charter.md) (live) | [RUNBOOK-cliente-map.md](../Crm/RUNBOOK-cliente-map.md) | [cliente-map-visual-comparison.md](../Crm/cliente-map-visual-comparison.md) |

> ℹ️ RUNBOOKs e visual-comparisons do cadastro ainda residem em `memory/requisitos/Crm/` (links `../Crm/`) — serão movidos pra `Cliente/` na execução do plano de separação.

> **Nota de fidelidade (alinhamento 2026-06-22 · revisão adversarial + confirmação de prod 2026-06-24):** a superfície de detalhe **viva** é o **drawer 760px** aberto do `Index` (ADR 0179) — `Show.tsx` segue só como dual-render legado (charter `deprecated`/superseded). As 7 Pages estão **todas listadas** na tabela acima. Os charters `Create/Edit/Ledger/Map/Import` foram **reconciliados `draft`→`live` em 2026-06-24**: cada tela Inertia é flag-gated (`config/mwart.php` `MWART_CLIENTE_*`, fallback Blade no `ContactController::shouldRenderInertiaCliente`) e **Wagner confirmou que biz=4 (ROTA LIVRE, tenant vivo) roda as 5 telas em React em produção** (flags ON) — essa é a base honesta da promoção (não os Wave1 source-tests, que só fazem grep do `.tsx`). Relatório completo: [audits/ALINHAMENTO-cliente-2026-06-22.md](audits/ALINHAMENTO-cliente-2026-06-22.md).

## §1 — Multi-tenant (Tier 0 IRREVOGÁVEL)

`App\Contact` usa global scope `business_id` (UPOS canon). TODA query passa por scope automático. Cross-tenant retorna 404 (anti-enumeração, não 403). ADR 0093 obrigatório.

## §2 — LGPD / PII handling

- `cpf_cnpj`, `ie_rg`, `bank_account_number` **mascarados** via `maskTaxNumber($value)` ANTES do Inertia props
- Activity log do model `Contact` exclui `tax_number_1`/`cpf_cnpj` via `logOnly`
- Export PDF ledger gera com PII completa (Larissa autorizada via permission)
- Display "viewed" NÃO logado (privacidade — Charter Anti-hook em todas as Pages)

## §3 — US declaradas

> Formato canon: `### US-XXX-NNN — Título` (compatível com MWART gate regex).

> **Cobertura por US (honesto, pós-revisão adversarial 2026-06-24):** cada US tem `**Testado em:**` com `// @covers-us` no teste citado (gate de covers verde). Mas a *qualidade* varia e está declarada em cada DoD via `_Cob.:_`: **comportamental** (exercita runtime — unit/DB/HTTP/render) = US-072 (schema dos 10 campos + mod-11 do `Rule\BR\CpfCnpj` no unit)·074·075·076·078; **073** = comportamental no primitivo de máscara (`br-inputs.test.tsx`), com lacuna na seção; **065·066·071** = guard estrutural + 1 asserção HTTP que pula sem MySQL no lane sqlite; **063·064·067·068·069·070** = **guard estrutural** (source-grep) — travam o contrato do componente mas não exercitam runtime. ⚠️ Nenhum dos testes citados está hoje numa lane de JUnit do CI; quando o gate "verde" (ADR 0303) armar, vão reportar `ausente` até serem incluídos numa lane.

### US-CRM-063 — Tab Pagamentos no Show

**Implementado em:** `resources/js/Pages/Cliente/_show/PaymentsTab.tsx` · verificado@3b425d8 (2026-06-24)
**Testado em:** `tests/Feature/Cliente/Show/PaymentsTabTest.php`
**DoD:** lista payments paginados do contato (Data/Ref/Valor/Método/Pago por/Ação) via self-fetch `/cliente/{id}/payments-json` scoped `business_id` + permissão `customer.view`; PII bancária mascarada; empty state.
**Status:** done (PR #1298 Wave 5 W-A · 2026-05-21)
**Prioridade:** P0
**Persona:** Larissa
Self-fetch `/contacts/payments/{id}` exibindo lista paginada de payments (Data/Ref/Valor/Método/Pago por/Ação). Substitui Blade `payments_tab.blade.php`.

### US-CRM-064 — Tab Ledger inline no Show

**Implementado em:** _parcial_ · `resources/js/Pages/Cliente/_show/LedgerTab.tsx` · `Modules/Crm/Http/Controllers/LedgerController.php` · verificado@3b425d8 (2026-06-24) — render inline 100% pendente (abre Blade legacy ao filtrar)
**Testado em:** `tests/Feature/Cliente/Show/LedgerTabTest.php`
**DoD:** filtros range/formato 1·2·3/local, resumos período+total, export PDF/email via `/contacts/send-ledger`, empty state. _Gap conhecido (parcial):_ render inline 100% — ao filtrar ainda abre Blade legacy.
**Status:** done (PR #1298 Wave 5 W-B · 2026-05-21)
**Prioridade:** P0
Range datas + Formato 1/2/3 + filtro localização + export PDF/email via `/contacts/send-ledger`. Gap remanescente: render inline 100% (atualmente abre Blade legacy ao filtrar).

### US-CRM-065 — Tab Vendas DataTable no Show

**Implementado em:** `resources/js/Pages/Cliente/_show/SalesTab.tsx` · `tests/Feature/Cliente/ClienteSalesJsonEndpointTest.php` · verificado@3b425d8 (2026-06-24)
**Testado em:** `tests/Feature/Cliente/ClienteSalesJsonEndpointTest.php`
**DoD:** DataTable de vendas com paginação server-side via partial reload `only:['sales']`, filtros range/status/q; `salesJson($id)` faz `business_id` findOrFail + `customer.view` + delega `buildClienteSalesPaginator`.
**Status:** done (PR #1298 Wave 5 W-C · 2026-05-21)
**Prioridade:** P0
Paginação server-side via Inertia partial reload (`only:['sales']`) + filtros range/status/q. Helper `buildClienteSalesPaginator($id, $filters)`.

### US-CRM-066 — Tab Documents & Note no Show

**Implementado em:** `resources/js/Pages/Cliente/_show/DocumentsTab.tsx` · `tests/Feature/Cliente/ClienteAnexosEndpointTest.php` · verificado@3b425d8 (2026-06-24)
**Testado em:** `tests/Feature/Cliente/ClienteAnexosEndpointTest.php`
**DoD:** upload/list/delete de anexos via `/cliente/{id}/anexos` (GET/POST/DELETE) todos scoped `business_id`; textarea de notas autosave 1500ms via `/note-documents`; contagem viva.
**Status:** done (PR #1298 Wave 5 W-D · 2026-05-21)
**Prioridade:** P0
Upload via `/post-document-upload` + lista + delete + textarea notas autosave 1500ms via `/note-documents`.

### US-CRM-067 — ActionsMenu + AddDiscountModal no Show

**Implementado em:** `resources/js/Pages/Cliente/_show/ActionsMenu.tsx` · `resources/js/Pages/Cliente/_show/AddDiscountModal.tsx` · verificado@3b425d8 (2026-06-24)
**Testado em:** `tests/Feature/Cliente/Show/ActionsMenuTest.php`
**DoD:** dropdown filtrado por permissões (pagar/editar/excluir/ativar-desativar/desconto/atalhos) com endpoints canon (`/contacts/update-status/`, `/ledger-discount`) + CSRF; modal Add Discount com `sub_type`.
**Status:** done (PR #1298 Wave 5 W-E · 2026-05-21)
**Prioridade:** P0
Dropdown 8 itens (Pagar / Editar / Excluir / Activate-Deactivate / Add Discount / ...) filtrado por permissions. Modal Add Discount canon.

### US-CRM-068 — Tab Pessoas de contato no Show

**Implementado em:** `resources/js/Pages/Cliente/_show/PessoasContatoTab.tsx` · verificado@3b425d8 (2026-06-24)
**Testado em:** `tests/Feature/Cliente/Show/PessoasContatoTabTest.php`
**DoD:** componente `_show/PessoasContatoTab.tsx` (5 colunas + "Adicionar pessoa") + injeção `contact_persons` via `Inertia::defer` scoped `business_id`+`crm_contact_id`. _Cob.: guard estrutural sobre a integração em `Show.tsx`_ (superfície dual-render legada; a viva é o drawer — ADR 0179).
**Status:** done (PR #1305 · 2026-05-19)
**Prioridade:** P1
Sub-contatos do cliente (PF dentro de PJ). Listagem + add inline.

### US-CRM-069 — Tab Assinaturas (subscriptions) no Show

**Implementado em:** `resources/js/Pages/Cliente/_show/SubscriptionsTab.tsx` · verificado@3b425d8 (2026-06-24)
**Testado em:** `tests/Feature/Cliente/Show/SubscriptionsTabTest.php`
**DoD:** componente `_show/SubscriptionsTab.tsx` read-only (status ativa/pausada/cancelada) + defer scoped `business_id`+`is_recurring`; self-fetch `/cliente/{id}/subscriptions-json`. _Cob.: guard estrutural sobre a integração em `Show.tsx`_ (superfície legada; a viva é o drawer).
**Status:** done (PR #1306 · 2026-05-20)
**Prioridade:** P1
Recorrência. Listagem read-only com status (ativa/cancelada/pausada).

### US-CRM-070 — Tab Reward Points no Show

**Implementado em:** `resources/js/Pages/Cliente/_show/RewardPointsTab.tsx` · verificado@3b425d8 (2026-06-24)
**Testado em:** `tests/Feature/Cliente/Show/RewardPointsTabTest.php`
**DoD:** componente `_show/RewardPointsTab.tsx` condicional `business.enable_rp` (cards + ledger de pontos) + defer scoped; self-fetch `/cliente/{id}/rewards-json`. _Cob.: guard estrutural sobre a integração em `Show.tsx`_ (superfície legada; a viva é o drawer).
**Status:** done (PR #1307 · 2026-05-20)
**Prioridade:** P2
Tab condicional `rp_enabled`. Ledger inline de pontos + crédito/débito manual.

### US-CRM-071 — KB-9.75 Slice A no Index

**Implementado em:** `resources/js/Pages/Cliente/Index.tsx` · `tests/Feature/Cliente/ClienteIndexDrawer760CharterTest.php` · verificado@3b425d8 (2026-06-24)
**Testado em:** `tests/Feature/Cliente/ClienteIndexDrawer760CharterTest.php`
**DoD:** ⌘K command palette + cheat-sheet (`?`) + navegação J/K + foco busca (`/`); drawer 760px com 6 tabs; PII mascarada; cross-tenant retorna 404.
**Status:** done (PR #1309 · 2026-05-21)
**Prioridade:** P0
⌘K command palette + Cheat-sheet (`?`) + J/K navigation. Primeiro Page do oimpresso com palette nativo.

### US-CRM-072 — Restaurar campos fiscais BR perdidos no upgrade UPOS 6.7

**Implementado em:** `database/migrations/2026_05_21_140000_restore_br_fields_to_contacts.php` · `app/Rules/BR/CpfCnpj.php` · verificado@3b425d8 (2026-06-24)
**Testado em:** `tests/Feature/Contact/ContactBrFieldsRestoredTest.php` · `tests/Unit/Rules/BR/CpfCnpjTest.php`
**DoD:** as 10 colunas BR presentes em `contacts` (schema guard `Schema::hasColumn` — `ContactBrFieldsRestoredTest`) + `Rule\BR\CpfCnpj` valida mod-11 (aceita null/válido mascarado+cru, rejeita DV errado/all-equal/curto/letras — `CpfCnpjTest`). _Cob.: schema guard + unit comportamental (mod-11)._
**Status:** done (PR #1313 · 2026-05-21)
**Prioridade:** P0
Migration `2026_05_21_restore_br_fields_to_contacts` restaura 10 campos perdidos: `cpf_cnpj`, `ie_rg`, `rua`, `numero`, `bairro`, `cep`, `consumidor_final`, `contribuinte`, `regime`, `is_sincronizado`. Inclui `Rule\BR\CpfCnpj` (validator mod-11).
Investigação: [`memory/sessions/2026-05-21-investigar-campos-br-cliente.md`](../../sessions/2026-05-21-investigar-campos-br-cliente.md)

### US-CRM-073 — UI campos BR em Create/Edit/Show

**Implementado em:** `resources/js/Pages/Cliente/_form/DadosFiscaisBRSection.tsx` · verificado@3b425d8 (2026-06-24)
**Testado em:** `tests/br-inputs.test.tsx`
**DoD:** máscaras dinâmicas CPF (11) / CNPJ (14) + validação mod-11 client-side cobertas no primitivo `DocumentInput` (`tests/br-inputs.test.tsx`, Vitest+RTL: máscara progressiva, dígitos crus pra persistir, mod-11 true/false/null, axe). _Cob.: comportamental (primitivo)._ _Lacuna:_ a composição em `_form/DadosFiscaisBRSection.tsx` não tem teste comportamental dedicado.
**Status:** done (PR #1316 · 2026-05-21)
**Prioridade:** P0
Slices 2+3 — sub-components `_form/DadosFiscaisBRSection.tsx` + `_form/EnderecoBRSection.tsx` em Create/Edit (reuso 100%). Bloco fiscal BR na sidebar do Show (`_show/DadosFiscaisBRBlock.tsx`). Máscaras dinâmicas CPF (11 dig) / CNPJ (14 dig).
> ⚠️ Drift menor: `_form/EnderecoBRSection.tsx` e `_show/DadosFiscaisBRBlock.tsx` citados acima não existem com esse nome em `origin/main@3b425d8` (endereço/fiscal migraram pro drawer ADR 0179). Âncora aponta o que existe: `_form/DadosFiscaisBRSection.tsx`.

### US-CRM-074 — Comando artisan backfill cpf_cnpj

**Implementado em:** `app/Console/Commands/BackfillCpfCnpjCommand.php` · verificado@3b425d8 (2026-06-24)
**Testado em:** `tests/Feature/Cliente/BackfillCpfCnpjCommandTest.php`
**DoD:** comando `cliente:backfill-cpf-cnpj` — dry-run no-op, `--execute` grava só mod-11 válido (dígitos crus), idempotente, `--business-id` isola Tier 0, nunca sobrescreve `cpf_cnpj` existente, log JSON sem PII em claro.
**Status:** done (PR #1319 · 2026-05-21)
**Prioridade:** P0
Slice 4 — `php artisan contacts:backfill-cpf-cnpj` migra `tax_number_1` legacy → `cpf_cnpj` canon. Idempotente (rerunável). Multi-tenant scope (cobre todos os businesses).

### US-CRM-075 — BrasilAPI lookup CNPJ + botão Buscar

**Implementado em:** `Modules/Crm/Http/Controllers/ClienteLookupController.php` · `Modules/Crm/Services/BrLookupService.php` · `tests/Feature/Cliente/ClienteLookupCnpjCepTest.php` · verificado@3b425d8 (2026-06-24)
**Testado em:** `tests/Feature/Cliente/ClienteLookupCnpjCepTest.php`
**DoD:** `/cliente/lookup/cnpj/{cnpj}` (+ `/cep/`) via `ClienteLookupController` + `BrLookupService` retorna razão social/endereço/IBGE/contatos normalizados; cache hit pula HTTP; 404/429 graceful; auth requerido; formato inválido short-circuit.
**Status:** done — ⚠️ reconciliado 2026-06-22 (spec dizia "backlog/futuro"; código já existe: `ClienteLookupController::cnpj` + `BrLookupService` + Pest. PR de origem não rastreado)
**Prioridade:** P1
Botão "Buscar" ao lado do campo CNPJ chama `https://brasilapi.com.br/api/cnpj/v1/{cnpj}` e preenche razão social + nome fantasia + endereço. Sem auth (público). Fallback se API indisponível.

### US-CRM-076 — FormRequest backend wirando Rule\BR\CpfCnpj

**Implementado em:** `app/Http/Requests/Cliente/StoreContactRequest.php` · `app/Http/Requests/Cliente/UpdateContactRequest.php` · verificado@3b425d8 (2026-06-24)
**Testado em:** `tests/Feature/Cliente/StoreContactRequestTest.php`
**DoD:** POST `/contacts` (Store) rejeita CPF/CNPJ/indicador_ie/regime inválidos com erros de sessão e aceita válido — mod-11 server-side via `new CpfCnpj`. _Cob.: comportamental (HTTP)._ _Lacuna:_ `UpdateContactRequest` aplica a mesma regra (wirado) mas não há teste dedicado do caminho Update.
**Status:** done — ⚠️ reconciliado 2026-06-22 (spec dizia "backlog/futuro"; código já existe: ambos FormRequests aplicam `'cpf_cnpj' => ['nullable', new CpfCnpj]`. PR de origem não rastreado)
**Prioridade:** P0
`StoreContactRequest` + `UpdateContactRequest` aplicam `Rule\BR\CpfCnpj` no `rules()`. Validação mod-11 server-side obrigatória (defesa em profundidade — não confiar no client).

### US-CRM-078 — Múltiplos endereços por contato + seletor de endereço na venda

**Implementado em:** _parcial_ · `database/migrations/2026_06_01_120000_create_contact_addresses_table.php` · `app/ContactAddress.php` · `Modules/Crm/Http/Controllers/ContactAddressController.php` · `resources/js/Pages/Cliente/_drawer/EnderecosEntregaList.tsx` · verificado@3b425d8 (2026-06-24) — PR1+PR2 landed; falta PR3 (seletor escolhe endereço salvo na tela de venda Sells/Create; hoje shipping_address é texto livre)
**Testado em:** `tests/Feature/Contact/ContactAddressMultiTenantTest.php`
**DoD:** `contact_addresses` 1:N com `business_id`+FK+scope (cross-tenant biz≠biz isolado, ADR 0093), default+shipping espelhados nos campos inline, `backfillInline` idempotente, store 404 cross-tenant / 403 sem `customer.update`, invariante 1-default. _Gap (parcial):_ PR3 (seletor na venda) pendente.
**Status:** PR1+PR2 done (backend + EnderecoTab lista) · PR3 (seletor na venda) pendente — reconciliado 2026-06-22
**Prioridade:** P1 — pedido Wagner 2026-06-01 (cliente cadastra matriz/filial/casa/obra e escolhe na entrega)

**Problema:** hoje o cadastro tem **1 endereço só**, inline em `contacts`
(`zip_code/address_line_1/numero/address_line_2/neighborhood/city/state/city_code`).
Na venda, o `shipping_address` (UltimatePOS) é digitado livre — não escolhido de uma
lista salva. O cliente quer cadastrar vários endereços rotulados e **escolher na hora
da entrega**.

**Decisão de modelagem:** tabela ADITIVA `contact_addresses` (1 Contact hasMany N).
Os campos inline de `contacts` **permanecem** (compat UPOS / NFe enderDest / Sells
shipping_address) e viram o endereço "principal", espelhados no endereço
`is_default = true`. Distinto da rede matriz/filial em nível de **contato**
(`parent_contact_id`, ADR 0197): aqui é um catálogo de endereços de entrega de UM
contato (CNPJ único), não hierarquia societária com tax entities separadas.

**Slice 1 (PR1) — backend foundation:**
- Migration aditiva idempotente `2026_06_01_120000_create_contact_addresses_table`
  (`business_id`+FK+index · `contact_id`+FK · `label` · endereço completo ·
  `is_default` · `is_shipping` · `softDeletes` · `down()`).
- `App\ContactAddress` (HasBusinessScope + SoftDeletes + `$fillable` explícito —
  business_id/contact_id setados server-side) + `Contact::addresses()` hasMany +
  `defaultAddress()`/`shippingAddress()` hasOne + accessor `one_line` + `toInlineArray()`.
- Backfill idempotente `ContactAddress::backfillInline()` — endereço inline → 1ª linha
  `is_default=true is_shipping=true label "Principal"`, preservando business_id.
- Pest cross-tenant biz=1 vs biz=99 (ADR 0093) + relações + backfill idempotente.

**Slice 2 (PR2) — UI cadastro (gate visual R2/R7):**
- `EnderecoTab.tsx` vira **lista** (adicionar/editar/remover/marcar padrão) reusando o
  lookup ViaCEP. Endpoints CRUD multi-tenant. Espelha o `is_default` de volta nos campos
  inline de `contacts`. Segue MWART + drawer 760 (ADR 0179).

**Slice 3 (PR3) — seletor na venda (gate visual R2/R7):**
- `Sells/Create.tsx` + `_components/SaleSheet.tsx`: dropdown "Endereço de entrega" lista
  os endereços do cliente selecionado + opção "Outro (digitar)" → grava em `shipping_address`.

**DoD multi-tenant Tier 0:** `contact_addresses` SEMPRE com `business_id` + FK + scope;
Pest cross-tenant antes/depois. **PR ≤300 linhas** (faseado PR1/PR2/PR3).

## §4 — Não-objetivos

- Não substitui `Modules/Crm/` (CRM avançado: leads, deals, marketplace, pipeline FSM)
- Não chama Receita Federal direto (BrasilAPI é proxy informativo público)
- Não automatiza emissão NFe (vive em `Modules/NfeBrasil/`)
- Não dispara WhatsApp/email ao cadastrar (Anti-hook charter)
- Não mostra saldo a receber em tempo real (custo agregação — usa cached)

## §5 — Backlog priorizado

| ID | Título | Prioridade | Estimate |
|---|---|---|---|
| ~~US-CRM-075~~ | ~~BrasilAPI lookup CNPJ~~ — **done** (verificado @3b425d8 2026-06-24) | — | — |
| ~~US-CRM-076~~ | ~~FormRequest Rule\BR\CpfCnpj~~ — **done** (verificado @3b425d8 2026-06-24) | — | — |
| US-CRM-078 (PR3) | Seletor de endereço salvo na venda (`Sells/Create`) | P1 | 3h |
| (futura) | ViaCEP lookup automático | P2 | 3h |
| (futura) | Tab Atividades (activity log inline) | P1 | 6h |
| (futura) | Contact picker no header Show (trocar sem voltar) | P2 | 4h |
| (futura) | Ledger inline 100% (sem abrir legacy) — US-CRM-064 gap | P2 | 8h |
| (futura) | Bulk actions Index (mesclar duplicados) | P2 | 6h |
| (futura) | Mobile < 1100px refinement total | P2 | 4h |
| (futura) | Suframa, indicador_ie NFe (1/2/9) | P3 | 4h |
| (futura) | Saved views Index (favoritar filtros) | P3 | 4h |

## §6 — Histórico

| Data | PR | Autor | Mudança |
|---|---|---|---|
| 2026-05-19 | #1305 | W | Tab Pessoas de contato (US-CRM-068) |
| 2026-05-20 | #1306 | W | Tab Assinaturas (US-CRM-069) |
| 2026-05-20 | #1307 | W | Tab Reward Points (US-CRM-070) + ActionsMenu (US-CRM-067) |
| 2026-05-21 | #1298 | W | Wave 5 paralela Show: Pagamentos+Ledger+Vendas+Documentos (US-CRM-063..066) |
| 2026-05-21 | #1309 | W | KB-9.75 Slice A no Index: ⌘K + cheat-sheet + J/K (US-CRM-071) |
| 2026-05-21 | #1313 | W | Slice 1 migration restore 10 campos BR + Rule\BR\CpfCnpj (US-CRM-072) |
| 2026-05-21 | #1316 | W | Slices 2+3 UI BR Create/Edit + bloco fiscal Show (US-CRM-073) |
| 2026-05-21 | #1319 | W | Slice 4 comando backfill cpf_cnpj (US-CRM-074) |
| 2026-06-01 | — | W+Claude | US-CRM-078 PR1 — migration `contact_addresses` + `ContactAddress` model + Pest cross-tenant |
| 2026-06-22 | #3221 | W+Claude | **Alinhamento de fidelidade #1** — âncoras ADR 0273 em todas as US + correção de 6 drifts: Show `superseded`→drawer; US-075/076 já estavam `done` no código (spec dizia backlog); US-078 reclassificada (PR1+PR2 done, PR3 pendente); US-077 inexistente; pages Import/Ledger/Map não listadas; 8 links de doc quebrados. Relatório: [audits/ALINHAMENTO-cliente-2026-06-22.md](audits/ALINHAMENTO-cliente-2026-06-22.md) |
| 2026-06-22 | (separar-cliente) | W+Claude | **Separação Cliente ≠ CRM** — `Crm/SPEC-us-063-078.md` → `Cliente/SPEC.md` (máquina passa a ler via `anchor-lint`); pipeline CRM (leads/propostas/campanhas) entra em depreciação (plano à parte). Doc de desambiguação atualizado. |
| 2026-06-24 | (reconcile/cliente-sdd) | W+Claude | **Reconciliação SDD** — re-carimbo das 15 âncoras `@3cf2b52`→`@3b425d8` (27 caminhos re-verificados via existsSync, 0 morto/zumbi); escopo completo (7 Pages listadas). **Degrau 4** ([ADR 0303](../../decisions/0303-anchor-lint-wired-testado-sa-a2-bis.md)): `**Testado em:**` + `**DoD:**` + `// @covers-us` por US (gates de aceite e covers verdes), **com nota honesta de cobertura** (comportamental 072/073/074/075/076/078; demais guard estrutural — ver §3). Revisão adversarial + confirmação de prod (Wagner): charters `Create/Edit/Ledger/Map/Import` `draft`→`live` (biz=4 roda as 5 em React em prod); rota do `Map.charter` corrigida `/contacts/contact_map`→`/contacts/map`; US-073 re-ancorada no teste real de máscara (`br-inputs.test.tsx`), tirada do quarantinado; US-072 ancorada no schema + mod-11 unit (`CpfCnpjTest`). |

## §7 — Referências

- [ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0104 — Processo MWART canônico (5 fases)](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)
- [ADR 0107 — Visual gate F1.5 visual-comparison.md](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
- [ADR 0110 — Cockpit Pattern V2 (canon list-detail)](../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- [ADR 0114 — Protótipo UI Cowork loop formalizado](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md)
- [ADR 0149 — Pattern reuse Crm](../../decisions/0149-mwart-screen-pattern-reuse-cowork.md)
- [ADR 0179 — Cliente drawer 760px substitui Show full-page](../../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md)
- [ADR 0273 — Anchor spec↔código (formato canônico `Implementado em`)](../../decisions/0273-anchor-spec-codigo-formato-canonico-fluxo-novo.md)
- Investigação base: [`memory/sessions/2026-05-21-investigar-campos-br-cliente.md`](../../sessions/2026-05-21-investigar-campos-br-cliente.md)
- Coord paralela: [`memory/sessions/2026-05-21-coord-cliente-show-paridade-5waves.md`](../../sessions/2026-05-21-coord-cliente-show-paridade-5waves.md)
- HANDOFF Claude Design: `prototipo-ui/prototipos/clientes/HANDOFF_CLIENTES.md`
- SPEC canônico do módulo: [`memory/requisitos/Crm/SPEC.md`](../Crm/SPEC.md)

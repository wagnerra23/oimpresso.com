---
module: Cliente
version: "2.1"
status: ativo
owners: [W]
last_updated: "2026-06-22"
anchor_format: "v1"
us_count: 15
us_list: [US-CRM-063, US-CRM-064, US-CRM-065, US-CRM-066, US-CRM-067, US-CRM-068, US-CRM-069, US-CRM-070, US-CRM-071, US-CRM-072, US-CRM-073, US-CRM-074, US-CRM-075, US-CRM-076, US-CRM-078]
related_adrs: [0093-multi-tenant-isolation-tier-0, 0104-processo-mwart-canonico-unico-caminho, 0107-emendation-0104-visual-comparison-gate-f3, 0110-cockpit-pattern-v2-canon-list-detail, 0114-prototipo-ui-cowork-loop-formalizado, 0149-mwart-screen-pattern-reuse-cowork, 0179-cliente-drawer-760px-substitui-show-fullpage, 0273-anchor-spec-codigo-formato-canonico-fluxo-novo]
---

# SPEC — Cliente

> **Convenção de naming:** módulo canônico é `Modules/Crm/` (UPOS legacy contacts) — ADR 0149 (pattern reuse). As Pages Inertia vivem em `resources/js/Pages/Cliente/` (rota PT-BR `/cliente`). Este SPEC consolida as US tocadas pelas Pages `Cliente/{Index,Create,Edit,Show}.tsx` durante 2026-04 → 2026-05.

> 🔗 **Âncoras spec↔código (ADR 0273, `anchor_format: v1`):** cada US abaixo carrega `**Implementado em:**` apontando o arquivo real, verificado por `existsSync` em `origin/main@3cf2b52` (2026-06-22). ⚠️ A `anchor-lint.mjs` só varre arquivos chamados `SPEC.md` — este arquivo é `SPEC-us-063-078.md`, então **a máquina ainda não lê estas âncoras** (decisão estrutural pendente — ver [relatório de alinhamento](audits/ALINHAMENTO-cliente-2026-06-22.md) §"Ligar a máquina").

## Missão

Gerenciar cadastro de clientes (PF e PJ) com canon BR completo (fiscais + endereço + contato) e tela de detalhe rica (header + 4 stats + 4 tabs + dropdown ações + sidebar com bloco fiscal BR + drawer histórico OS no Index).

## Personas

- **Larissa** [L] @ ROTA LIVRE (biz=4 vestuário) — dona PME, monitor 1280×1024, balcão de venda
- **Eliana** [E] (futuro) — financeiro escritório, fechamento mensal

## Escopo do módulo

| Page Inertia | Rota | Charter | RUNBOOK | visual-comparison |
|---|---|---|---|---|
| Cliente/Index | `/cliente` | [`Index.charter.md`](../../../resources/js/Pages/Cliente/Index.charter.md) (live) | [RUNBOOK-cliente-index.md](RUNBOOK-cliente-index.md) | [cliente-index-visual-comparison.md](cliente-index-visual-comparison.md) |
| Cliente/Create | `/contacts/create` | [`Create.charter.md`](../../../resources/js/Pages/Cliente/Create.charter.md) (draft) | [RUNBOOK-cliente-create.md](RUNBOOK-cliente-create.md) | [cliente-create-visual-comparison.md](cliente-create-visual-comparison.md) |
| Cliente/Edit | `/contacts/{id}/edit` | [`Edit.charter.md`](../../../resources/js/Pages/Cliente/Edit.charter.md) (draft) | [RUNBOOK-cliente-edit.md](RUNBOOK-cliente-edit.md) | [cliente-edit-visual-comparison.md](cliente-edit-visual-comparison.md) |
| Cliente/Show | `/cliente/{id}` (canon) · `/contacts/{id}` (dual-render) | [`Show.charter.md`](../../../resources/js/Pages/Cliente/Show.charter.md) (**superseded** → drawer 760px, [ADR 0179](../../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md)) | [RUNBOOK-cliente-show.md](RUNBOOK-cliente-show.md) | [cliente-show-visual-comparison.md](cliente-show-visual-comparison.md) |

> **Nota de fidelidade (alinhamento 2026-06-22):** a superfície de detalhe **viva** é o **drawer 760px** aberto do `Index` (ADR 0179) — `Show.tsx` segue só como dual-render legado (charter `superseded`). Além das 4 Pages acima, existem em produção `Cliente/{Import,Ledger,Map}.tsx` (charters `draft`) não listadas nesta tabela. Os charters `Create/Edit/Ledger/Map` estão `draft` apesar das telas estarem em uso — **status-truth a reconciliar**. Relatório completo: [audits/ALINHAMENTO-cliente-2026-06-22.md](audits/ALINHAMENTO-cliente-2026-06-22.md).

## §1 — Multi-tenant (Tier 0 IRREVOGÁVEL)

`App\Contact` usa global scope `business_id` (UPOS canon). TODA query passa por scope automático. Cross-tenant retorna 404 (anti-enumeração, não 403). ADR 0093 obrigatório.

## §2 — LGPD / PII handling

- `cpf_cnpj`, `ie_rg`, `bank_account_number` **mascarados** via `maskTaxNumber($value)` ANTES do Inertia props
- Activity log do model `Contact` exclui `tax_number_1`/`cpf_cnpj` via `logOnly`
- Export PDF ledger gera com PII completa (Larissa autorizada via permission)
- Display "viewed" NÃO logado (privacidade — Charter Anti-hook em todas as Pages)

## §3 — US declaradas

> Formato canon: `### US-XXX-NNN — Título` (compatível com MWART gate regex).

### US-CRM-063 — Tab Pagamentos no Show

**Implementado em:** `resources/js/Pages/Cliente/_show/PaymentsTab.tsx` · verificado@3cf2b52 (2026-06-22)
**Status:** done (PR #1298 Wave 5 W-A · 2026-05-21)
**Prioridade:** P0
**Persona:** Larissa
Self-fetch `/contacts/payments/{id}` exibindo lista paginada de payments (Data/Ref/Valor/Método/Pago por/Ação). Substitui Blade `payments_tab.blade.php`.

### US-CRM-064 — Tab Ledger inline no Show

**Implementado em:** _parcial_ · `resources/js/Pages/Cliente/_show/LedgerTab.tsx` · `Modules/Crm/Http/Controllers/LedgerController.php` · verificado@3cf2b52 (2026-06-22) — render inline 100% pendente (abre Blade legacy ao filtrar)
**Status:** done (PR #1298 Wave 5 W-B · 2026-05-21)
**Prioridade:** P0
Range datas + Formato 1/2/3 + filtro localização + export PDF/email via `/contacts/send-ledger`. Gap remanescente: render inline 100% (atualmente abre Blade legacy ao filtrar).

### US-CRM-065 — Tab Vendas DataTable no Show

**Implementado em:** `resources/js/Pages/Cliente/_show/SalesTab.tsx` · `tests/Feature/Cliente/ClienteSalesJsonEndpointTest.php` · verificado@3cf2b52 (2026-06-22)
**Status:** done (PR #1298 Wave 5 W-C · 2026-05-21)
**Prioridade:** P0
Paginação server-side via Inertia partial reload (`only:['sales']`) + filtros range/status/q. Helper `buildClienteSalesPaginator($id, $filters)`.

### US-CRM-066 — Tab Documents & Note no Show

**Implementado em:** `resources/js/Pages/Cliente/_show/DocumentsTab.tsx` · `tests/Feature/Cliente/ClienteAnexosEndpointTest.php` · verificado@3cf2b52 (2026-06-22)
**Status:** done (PR #1298 Wave 5 W-D · 2026-05-21)
**Prioridade:** P0
Upload via `/post-document-upload` + lista + delete + textarea notas autosave 1500ms via `/note-documents`.

### US-CRM-067 — ActionsMenu + AddDiscountModal no Show

**Implementado em:** `resources/js/Pages/Cliente/_show/ActionsMenu.tsx` · `resources/js/Pages/Cliente/_show/AddDiscountModal.tsx` · verificado@3cf2b52 (2026-06-22)
**Status:** done (PR #1298 Wave 5 W-E · 2026-05-21)
**Prioridade:** P0
Dropdown 8 itens (Pagar / Editar / Excluir / Activate-Deactivate / Add Discount / ...) filtrado por permissions. Modal Add Discount canon.

### US-CRM-068 — Tab Pessoas de contato no Show

**Implementado em:** `resources/js/Pages/Cliente/_show/PessoasContatoTab.tsx` · verificado@3cf2b52 (2026-06-22)
**Status:** done (PR #1305 · 2026-05-19)
**Prioridade:** P1
Sub-contatos do cliente (PF dentro de PJ). Listagem + add inline.

### US-CRM-069 — Tab Assinaturas (subscriptions) no Show

**Implementado em:** `resources/js/Pages/Cliente/_show/SubscriptionsTab.tsx` · verificado@3cf2b52 (2026-06-22)
**Status:** done (PR #1306 · 2026-05-20)
**Prioridade:** P1
Recorrência. Listagem read-only com status (ativa/cancelada/pausada).

### US-CRM-070 — Tab Reward Points no Show

**Implementado em:** `resources/js/Pages/Cliente/_show/RewardPointsTab.tsx` · verificado@3cf2b52 (2026-06-22)
**Status:** done (PR #1307 · 2026-05-20)
**Prioridade:** P2
Tab condicional `rp_enabled`. Ledger inline de pontos + crédito/débito manual.

### US-CRM-071 — KB-9.75 Slice A no Index

**Implementado em:** `resources/js/Pages/Cliente/Index.tsx` · `tests/Feature/Cliente/ClienteIndexDrawer760CharterTest.php` · verificado@3cf2b52 (2026-06-22)
**Status:** done (PR #1309 · 2026-05-21)
**Prioridade:** P0
⌘K command palette + Cheat-sheet (`?`) + J/K navigation. Primeiro Page do oimpresso com palette nativo.

### US-CRM-072 — Restaurar campos fiscais BR perdidos no upgrade UPOS 6.7

**Implementado em:** `database/migrations/2026_05_21_140000_restore_br_fields_to_contacts.php` · `app/Rules/BR/CpfCnpj.php` · verificado@3cf2b52 (2026-06-22)
**Status:** done (PR #1313 · 2026-05-21)
**Prioridade:** P0
Migration `2026_05_21_restore_br_fields_to_contacts` restaura 10 campos perdidos: `cpf_cnpj`, `ie_rg`, `rua`, `numero`, `bairro`, `cep`, `consumidor_final`, `contribuinte`, `regime`, `is_sincronizado`. Inclui `Rule\BR\CpfCnpj` (validator mod-11).
Investigação: [`memory/sessions/2026-05-21-investigar-campos-br-cliente.md`](../../sessions/2026-05-21-investigar-campos-br-cliente.md)

### US-CRM-073 — UI campos BR em Create/Edit/Show

**Implementado em:** `resources/js/Pages/Cliente/_form/DadosFiscaisBRSection.tsx` · verificado@3cf2b52 (2026-06-22)
**Status:** done (PR #1316 · 2026-05-21)
**Prioridade:** P0
Slices 2+3 — sub-components `_form/DadosFiscaisBRSection.tsx` + `_form/EnderecoBRSection.tsx` em Create/Edit (reuso 100%). Bloco fiscal BR na sidebar do Show (`_show/DadosFiscaisBRBlock.tsx`). Máscaras dinâmicas CPF (11 dig) / CNPJ (14 dig).
> ⚠️ Drift menor: `_form/EnderecoBRSection.tsx` e `_show/DadosFiscaisBRBlock.tsx` citados acima não existem com esse nome em `origin/main@3cf2b52` (endereço/fiscal migraram pro drawer ADR 0179). Âncora aponta o que existe: `_form/DadosFiscaisBRSection.tsx`.

### US-CRM-074 — Comando artisan backfill cpf_cnpj

**Implementado em:** `app/Console/Commands/BackfillCpfCnpjCommand.php` · verificado@3cf2b52 (2026-06-22)
**Status:** done (PR #1319 · 2026-05-21)
**Prioridade:** P0
Slice 4 — `php artisan contacts:backfill-cpf-cnpj` migra `tax_number_1` legacy → `cpf_cnpj` canon. Idempotente (rerunável). Multi-tenant scope (cobre todos os businesses).

### US-CRM-075 — BrasilAPI lookup CNPJ + botão Buscar

**Implementado em:** `Modules/Crm/Http/Controllers/ClienteLookupController.php` · `Modules/Crm/Services/BrLookupService.php` · `tests/Feature/Cliente/ClienteLookupCnpjCepTest.php` · verificado@3cf2b52 (2026-06-22)
**Status:** done — ⚠️ reconciliado 2026-06-22 (spec dizia "backlog/futuro"; código já existe: `ClienteLookupController::cnpj` + `BrLookupService` + Pest. PR de origem não rastreado)
**Prioridade:** P1
Botão "Buscar" ao lado do campo CNPJ chama `https://brasilapi.com.br/api/cnpj/v1/{cnpj}` e preenche razão social + nome fantasia + endereço. Sem auth (público). Fallback se API indisponível.

### US-CRM-076 — FormRequest backend wirando Rule\BR\CpfCnpj

**Implementado em:** `app/Http/Requests/Cliente/StoreContactRequest.php` · `app/Http/Requests/Cliente/UpdateContactRequest.php` · verificado@3cf2b52 (2026-06-22)
**Status:** done — ⚠️ reconciliado 2026-06-22 (spec dizia "backlog/futuro"; código já existe: ambos FormRequests aplicam `'cpf_cnpj' => ['nullable', new CpfCnpj]`. PR de origem não rastreado)
**Prioridade:** P0
`StoreContactRequest` + `UpdateContactRequest` aplicam `Rule\BR\CpfCnpj` no `rules()`. Validação mod-11 server-side obrigatória (defesa em profundidade — não confiar no client).

### US-CRM-078 — Múltiplos endereços por contato + seletor de endereço na venda

**Implementado em:** _parcial_ · `database/migrations/2026_06_01_120000_create_contact_addresses_table.php` · `app/ContactAddress.php` · `Modules/Crm/Http/Controllers/ContactAddressController.php` · `resources/js/Pages/Cliente/_drawer/EnderecosEntregaList.tsx` · verificado@3cf2b52 (2026-06-22) — PR1+PR2 landed; falta PR3 (seletor escolhe endereço salvo na tela de venda Sells/Create; hoje shipping_address é texto livre)
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
| ~~US-CRM-075~~ | ~~BrasilAPI lookup CNPJ~~ — **done** (verificado @3cf2b52 2026-06-22) | — | — |
| ~~US-CRM-076~~ | ~~FormRequest Rule\BR\CpfCnpj~~ — **done** (verificado @3cf2b52 2026-06-22) | — | — |
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
| 2026-06-22 | (alinhar-tela) | W+Claude | **Alinhamento de fidelidade #1** — âncoras ADR 0273 em todas as US + correção de 5 drifts: Show `superseded`→drawer; US-075/076 já estavam `done` no código (spec dizia backlog); US-078 reclassificada (PR1+PR2 done, PR3 pendente); US-077 inexistente; pages Import/Ledger/Map não listadas. Relatório: [audits/ALINHAMENTO-cliente-2026-06-22.md](audits/ALINHAMENTO-cliente-2026-06-22.md) |

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

---
module: Cliente
version: "2.0"
status: ativo
owners: [W]
last_updated: 2026-05-21
us_count: 14
us_list: [US-CRM-063, US-CRM-064, US-CRM-065, US-CRM-066, US-CRM-067, US-CRM-068, US-CRM-069, US-CRM-070, US-CRM-071, US-CRM-072, US-CRM-073, US-CRM-074, US-CRM-075, US-CRM-076]
related_adrs: [0093-multi-tenant-isolation-tier-0, 0104-processo-mwart-canonico-unico-caminho, 0107-emendation-0104-visual-comparison-gate-f3, 0110-cockpit-pattern-v2-canon-list-detail, 0114-prototipo-ui-cowork-loop-formalizado, 0149-mwart-screen-pattern-reuse-cowork]
---

# SPEC — Cliente

> **Convenção de naming:** módulo canônico é `Modules/Crm/` (UPOS legacy contacts) — ADR 0149 (pattern reuse). As Pages Inertia vivem em `resources/js/Pages/Cliente/` (rota PT-BR `/cliente`). Este SPEC consolida as US tocadas pelas Pages `Cliente/{Index,Create,Edit,Show}.tsx` durante 2026-04 → 2026-05.

## Missão

Gerenciar cadastro de clientes (PF e PJ) com canon BR completo (fiscais + endereço + contato) e tela de detalhe rica (header + 4 stats + 4 tabs + dropdown ações + sidebar com bloco fiscal BR + drawer histórico OS no Index).

## Personas

- **Larissa** [L] @ ROTA LIVRE (biz=4 vestuário) — dona PME, monitor 1280×1024, balcão de venda
- **Eliana** [E] (futuro) — financeiro escritório, fechamento mensal

## Escopo do módulo

| Page Inertia | Rota | Charter | RUNBOOK | visual-comparison |
|---|---|---|---|---|
| Cliente/Index | `/cliente` | [`Index.charter.md`](../../../resources/js/Pages/Cliente/Index.charter.md) | [RUNBOOK-index.md](RUNBOOK-index.md) | [index-visual-comparison.md](index-visual-comparison.md) |
| Cliente/Create | `/contacts/create` | [`Create.charter.md`](../../../resources/js/Pages/Cliente/Create.charter.md) | [RUNBOOK-create.md](RUNBOOK-create.md) | [create-visual-comparison.md](create-visual-comparison.md) |
| Cliente/Edit | `/contacts/{id}/edit` | [`Edit.charter.md`](../../../resources/js/Pages/Cliente/Edit.charter.md) | [RUNBOOK-edit.md](RUNBOOK-edit.md) | [edit-visual-comparison.md](edit-visual-comparison.md) |
| Cliente/Show | `/cliente/{id}` (canon) · `/contacts/{id}` (dual-render) | [`Show.charter.md`](../../../resources/js/Pages/Cliente/Show.charter.md) (v2 live) | [RUNBOOK-show.md](RUNBOOK-show.md) | [show-visual-comparison.md](show-visual-comparison.md) |

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

**Status:** done (PR #1298 Wave 5 W-A · 2026-05-21)
**Prioridade:** P0
**Persona:** Larissa
Self-fetch `/contacts/payments/{id}` exibindo lista paginada de payments (Data/Ref/Valor/Método/Pago por/Ação). Substitui Blade `payments_tab.blade.php`.

### US-CRM-064 — Tab Ledger inline no Show

**Status:** done (PR #1298 Wave 5 W-B · 2026-05-21)
**Prioridade:** P0
Range datas + Formato 1/2/3 + filtro localização + export PDF/email via `/contacts/send-ledger`. Gap remanescente: render inline 100% (atualmente abre Blade legacy ao filtrar).

### US-CRM-065 — Tab Vendas DataTable no Show

**Status:** done (PR #1298 Wave 5 W-C · 2026-05-21)
**Prioridade:** P0
Paginação server-side via Inertia partial reload (`only:['sales']`) + filtros range/status/q. Helper `buildClienteSalesPaginator($id, $filters)`.

### US-CRM-066 — Tab Documents & Note no Show

**Status:** done (PR #1298 Wave 5 W-D · 2026-05-21)
**Prioridade:** P0
Upload via `/post-document-upload` + lista + delete + textarea notas autosave 1500ms via `/note-documents`.

### US-CRM-067 — ActionsMenu + AddDiscountModal no Show

**Status:** done (PR #1298 Wave 5 W-E · 2026-05-21)
**Prioridade:** P0
Dropdown 8 itens (Pagar / Editar / Excluir / Activate-Deactivate / Add Discount / ...) filtrado por permissions. Modal Add Discount canon.

### US-CRM-068 — Tab Pessoas de contato no Show

**Status:** done (PR #1305 · 2026-05-19)
**Prioridade:** P1
Sub-contatos do cliente (PF dentro de PJ). Listagem + add inline.

### US-CRM-069 — Tab Assinaturas (subscriptions) no Show

**Status:** done (PR #1306 · 2026-05-20)
**Prioridade:** P1
Recorrência. Listagem read-only com status (ativa/cancelada/pausada).

### US-CRM-070 — Tab Reward Points no Show

**Status:** done (PR #1307 · 2026-05-20)
**Prioridade:** P2
Tab condicional `rp_enabled`. Ledger inline de pontos + crédito/débito manual.

### US-CRM-071 — KB-9.75 Slice A no Index

**Status:** done (PR #1309 · 2026-05-21)
**Prioridade:** P0
⌘K command palette + Cheat-sheet (`?`) + J/K navigation. Primeiro Page do oimpresso com palette nativo.

### US-CRM-072 — Restaurar campos fiscais BR perdidos no upgrade UPOS 6.7

**Status:** done (PR #1313 · 2026-05-21)
**Prioridade:** P0
Migration `2026_05_21_restore_br_fields_to_contacts` restaura 10 campos perdidos: `cpf_cnpj`, `ie_rg`, `rua`, `numero`, `bairro`, `cep`, `consumidor_final`, `contribuinte`, `regime`, `is_sincronizado`. Inclui `Rule\BR\CpfCnpj` (validator mod-11).
Investigação: [`memory/sessions/2026-05-21-investigar-campos-br-cliente.md`](../../sessions/2026-05-21-investigar-campos-br-cliente.md)

### US-CRM-073 — UI campos BR em Create/Edit/Show

**Status:** done (PR #1316 · 2026-05-21)
**Prioridade:** P0
Slices 2+3 — sub-components `_form/DadosFiscaisBRSection.tsx` + `_form/EnderecoBRSection.tsx` em Create/Edit (reuso 100%). Bloco fiscal BR na sidebar do Show (`_show/DadosFiscaisBRBlock.tsx`). Máscaras dinâmicas CPF (11 dig) / CNPJ (14 dig).

### US-CRM-074 — Comando artisan backfill cpf_cnpj

**Status:** done (PR #1319 · 2026-05-21)
**Prioridade:** P0
Slice 4 — `php artisan contacts:backfill-cpf-cnpj` migra `tax_number_1` legacy → `cpf_cnpj` canon. Idempotente (rerunável). Multi-tenant scope (cobre todos os businesses).

### US-CRM-075 — BrasilAPI lookup CNPJ + botão Buscar

**Status:** backlog (Slice 5a · escopo futuro)
**Prioridade:** P1
Botão "Buscar" ao lado do campo CNPJ chama `https://brasilapi.com.br/api/cnpj/v1/{cnpj}` e preenche razão social + nome fantasia + endereço. Sem auth (público). Fallback se API indisponível.

### US-CRM-076 — FormRequest backend wirando Rule\BR\CpfCnpj

**Status:** backlog (Slice 7 · escopo futuro)
**Prioridade:** P0
`StoreContactRequest` + `UpdateContactRequest` aplicam `Rule\BR\CpfCnpj` no `rules()`. Validação mod-11 server-side obrigatória (defesa em profundidade — não confiar no client).

## §4 — Não-objetivos

- Não substitui `Modules/Crm/` (CRM avançado: leads, deals, marketplace, pipeline FSM)
- Não chama Receita Federal direto (BrasilAPI é proxy informativo público)
- Não automatiza emissão NFe (vive em `Modules/NfeBrasil/`)
- Não dispara WhatsApp/email ao cadastrar (Anti-hook charter)
- Não mostra saldo a receber em tempo real (custo agregação — usa cached)

## §5 — Backlog priorizado

| ID | Título | Prioridade | Estimate |
|---|---|---|---|
| US-CRM-075 | BrasilAPI lookup CNPJ | P1 | 2h |
| US-CRM-076 | FormRequest Rule\BR\CpfCnpj | P0 | 2h |
| (futura) | ViaCEP lookup automático | P2 | 3h |
| (futura) | Tab Atividades (activity log inline) | P1 | 6h |
| (futura) | Contact picker no header Show (trocar sem voltar) | P2 | 4h |
| (futura) | Ledger inline 100% (sem abrir legacy) | P2 | 8h |
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
| 2026-05-21 | TBD | W | **Slice 6 — RUNBOOK + visual-comparison + SPEC docs governance** (este PR) |

## §7 — Referências

- [ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0104 — Processo MWART canônico (5 fases)](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)
- [ADR 0107 — Visual gate F1.5 visual-comparison.md](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
- [ADR 0110 — Cockpit Pattern V2 (canon list-detail)](../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- [ADR 0114 — Protótipo UI Cowork loop formalizado](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md)
- [ADR 0149 — Pattern reuse Crm](../../decisions/0149-mwart-screen-pattern-reuse-cowork.md)
- Investigação base: [`memory/sessions/2026-05-21-investigar-campos-br-cliente.md`](../../sessions/2026-05-21-investigar-campos-br-cliente.md)
- Coord paralela: [`memory/sessions/2026-05-21-coord-cliente-show-paridade-5waves.md`](../../sessions/2026-05-21-coord-cliente-show-paridade-5waves.md)
- HANDOFF Claude Design: `prototipo-ui/prototipos/clientes/HANDOFF_CLIENTES.md`
- SPEC canônico do módulo: [`memory/requisitos/Crm/SPEC.md`](../Crm/SPEC.md)

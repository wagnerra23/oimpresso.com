---
title: "RUNBOOK — Cliente/Index (`/cliente`)"
module: Cliente
tela: Cliente/Index
owner: W
status: ativo
last_validated: 2026-05-21
preconditions:
  - "Usuário autenticado com permission `customer.view` ou `customer.view_own` (Spatie UPOS canon)"
  - "business_id válido na sessão (multi-tenant Tier 0 ativo — ADR 0093)"
  - "Flag `mwart.cliente_index.enabled=true` em `.env` (default true em prod desde 2026-05-15)"
preconditions_short: customer.view, business_id ativo, flag MWART cliente_index ON
steps:
  - "GET /cliente carrega lista paginada + 4 KPIs (Inertia::defer)"
  - "⌘K abre command palette (KB-9.75 Slice A)"
  - "Click linha abre <Sheet> drawer 480px com 4 KPIs + histórico OS"
  - "Filtros sincronizam via URL (?q=, ?status=)"
related_adrs: [0093-multi-tenant-isolation-tier-0, 0104-processo-mwart-canonico-unico-caminho, 0107-emendation-0104-visual-comparison-gate-f3, 0110-cockpit-pattern-v2-canon-list-detail, 0149-mwart-screen-pattern-reuse-cowork]
---

# RUNBOOK — Cliente/Index (`/cliente`)

> Rota: `/cliente` (canon) · Componente: `resources/js/Pages/Cliente/Index.tsx`
> Controller: `app/Http/Controllers/ContactController@index`
> Charter: `resources/js/Pages/Cliente/Index.charter.md` (v2)
> Última atualização: 2026-05-21

## 1. Objetivo

Listar clientes do business com KPIs de relacionamento (OS abertas/atrasadas/valor total em aberto) e drawer lateral pra detalhe + histórico de OS, substituindo a tela Blade legacy `customer.index.blade.php` preservando Cockpit Pattern V2 (ADR 0110).

## 2. Persona principal

Larissa @ ROTA LIVRE (biz=4 vestuário), monitor 1280×1024, não-técnica. Operação diária: revisar quem tem OS atrasada, abrir drawer pra confirmar contato, voltar pra lista sem perder filtros.

## 3. Pré-requisitos

- Permission `customer.view` ou `customer.view_own` (Spatie UPOS canon)
- Multi-tenant Tier 0 ativo — `App\Contact` filtrado por `business_id` global scope (ADR 0093)
- Flag `mwart.cliente_index.enabled` (default `true` em prod desde 2026-05-15)
- ⌘K palette KB-9.75 Slice A funcional (PR #1309)

## 4. Fluxo principal (golden path)

1. Larissa navega `/cliente`
2. Sistema renderiza header (h1 "Clientes" + botão "Novo cliente" → `/contacts/create`)
3. 4 KPI cards carregam via `Inertia::defer` (~300-500ms): Total / Com OS aberta / Com atraso / Valor total em aberto
4. Tabela 7 colunas exibe primeiros 50 clientes ordenados por última OS desc
5. Larissa pressiona `⌘K` → command palette abre (busca por nome/CPF/CNPJ/telefone)
6. Ou clica linha → `<Sheet>` lateral 480px abre com 4 KPIs do cliente + section Contato + section Histórico de OS (mais recente primeiro)
7. Larissa pressiona `Esc` → drawer fecha, lista mantém filtros (localStorage `oimpresso.cliente.*`)

## 5. Sub-componentes

- `resources/js/Pages/Cliente/Index.tsx` — page raiz
- (Drawer lateral é inline no Index.tsx — ainda não extraído)
- Shared: `PageHeader`, `Sheet`, `KpiCard` (via `resources/js/Components/`)

## 6. Estados (loading / empty / error / success)

| Estado | UI | Trigger |
|---|---|---|
| Loading KPIs | Skeleton stones em 4 cards | `Inertia::defer` pendente |
| Empty | Pill stone "Nenhum cliente cadastrado ainda" + CTA "Cadastrar primeiro" | `contacts.count === 0` |
| Error de fetch | Toast vermelho rose + retry | `props.error` truthy |
| Success | 4 KPIs + tabela 50 linhas | render padrão |
| Drawer loading | Skeleton 3 linhas em "Histórico de OS" | fetch `/cliente/{id}/sheet-data` em vôo |

## 7. Atalhos de teclado

| Tecla | Ação |
|---|---|
| ⌘K / Ctrl+K | Abrir command palette (KB-9.75 Slice A) |
| J / K | Navegar próxima/anterior linha (palette mode) |
| Enter | Abrir drawer da linha selecionada |
| / | Foco no search box |
| ? | Abrir cheat-sheet de atalhos |
| Esc | Fechar palette OU drawer OU cheat-sheet |

## 8. Dependências de API/backend

- `ContactController::index()` — retorna `customers` (paginated 50) + KPIs (`total_customers`, `total_with_open`, `total_late`, `total_open_amount`)
- `ContactController::sheetData($id)` — drawer detail (Contact + agregação OS via `App\Repair\JobSheet`)
- `App\Contact` model: global scope `business_id` (UPOS canon)

## 9. Multi-tenant + LGPD

- **Tier 0 (ADR 0093):** `App\Contact::where('business_id', $business_id)` em TODA query — global scope automático
- **PII:** CPF/CNPJ exibido com máscara via `maskTaxNumber($value)` backend. Plain text NUNCA chega ao frontend
- **Activity log:** `Contact` model não logga `tax_number_1` (excluído via `logOnly`)
- **Drawer:** acesso cross-tenant retorna 404 (não 403 — evita enumeração)

## 10. Smoke check pós-deploy

```bash
# 1. Verificar flag está ON
ssh prod 'cd /home/oimpresso/public_html && grep MWART_CLIENTE_INDEX .env'

# 2. HTTP smoke (curl real, NÃO declaração otimista — ver skill smoke-prod-evidence)
curl -sv https://oimpresso.com/cliente -H "Cookie: laravel_session=<sess_biz4>" 2>&1 | grep -E "(HTTP/|component)"
# Esperado: HTTP/2 200 + "component":"Cliente/Index"

# 3. Multi-tenant isolation
# biz=4 não enxerga cliente de biz=1 — testar com 2 sessões diferentes
```

## 11. Refs

- [ADR 0093 — Multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0104 — Processo MWART canônico](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)
- [ADR 0107 — Visual gate F1.5](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
- [ADR 0110 — Cockpit Pattern V2](../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- [ADR 0149 — Pattern reuse Crm](../../decisions/0149-mwart-screen-pattern-reuse-cowork.md)
- Charter: [`resources/js/Pages/Cliente/Index.charter.md`](../../../resources/js/Pages/Cliente/Index.charter.md)
- PR #1309 — KB-9.75 Slice A (⌘K palette + cheat-sheet)
- Blueprint visual: `prototipo-ui/prototipos/clientes/`

---
title: "RUNBOOK — Cliente/Show (`/cliente/{id}`)"
module: Cliente
tela: Cliente/Show
owner: W
status: ativo
last_validated: 2026-05-21
preconditions:
  - "Usuário autenticado com permission `customer.view` ou `customer.view_own`"
  - "Cliente {id} pertence ao business_id da sessão (Tier 0 — ADR 0093)"
  - "Flag `mwart.cliente_show.enabled=true` em `.env` (cutover 2026-05-21 — PR #1298)"
preconditions_short: customer.view, ownership business_id, flag MWART cliente_show ON
steps:
  - "GET /cliente/{id} renderiza header + 4 stats + 4 tabs"
  - "Larissa navega entre tabs (Extrato/Vendas/Pagamentos/Documentos) sem reload completo"
  - "Bloco fiscal BR exibido em sidebar Contato (CPF/CNPJ + IE + IM + Regime — Slice 3 PR #1316)"
  - "Menu Ações dropdown 8 itens (Pagar/Editar/Excluir/Deactivate/Add Discount/...)"
related_adrs: [0093-multi-tenant-isolation-tier-0, 0104-processo-mwart-canonico-unico-caminho, 0107-emendation-0104-visual-comparison-gate-f3, 0110-cockpit-pattern-v2-canon-list-detail, 0149-mwart-screen-pattern-reuse-cowork]
---

# RUNBOOK — Cliente/Show (`/cliente/{id}`)

> Rota: `/cliente/{id}` (canon) · `/contacts/{id}` (legacy dual-render via flag)
> Componente: `resources/js/Pages/Cliente/Show.tsx` (charter v2 status live)
> Controller: `app/Http/Controllers/ContactController@show` + helper `buildClienteSalesPaginator()`
> Charter: `resources/js/Pages/Cliente/Show.charter.md` (v2)
> Última atualização: 2026-05-21

## 1. Objetivo

Tela completa de detalhe do cliente com paridade funcional ao Blade legacy (`resources/views/contact/show.blade.php`): header rico + 4 stats + 4 tabs (Extrato/Vendas/Pagamentos/Documentos) + dropdown ações + sidebar contato com bloco fiscal BR completo.

## 2. Persona principal

Larissa @ ROTA LIVRE consultando histórico de cliente antes de aprovar venda a prazo. Precisa ver: saldo devedor, últimas vendas, ledger filtrado, documentos anexos. Decisão "aprovo crediário?" em < 60s.

## 3. Pré-requisitos

- Permission `customer.view` ou `customer.view_own` (Spatie UPOS canon)
- `Contact::find($id)->business_id === session('business.id')` — Tier 0 (ADR 0093)
- Flag `mwart.cliente_show.enabled=true` (cutover prod 2026-05-21 via PR #1298)
- Bloco fiscal BR: cliente teve `cpf_cnpj`/`ie_rg`/`regime` populados (PR #1316 Slice 3 exibe)

## 4. Fluxo principal (golden path)

1. Larissa abre `/cliente/{id}` (via Index click ou ⌘K palette)
2. Header renderiza imediato (avatar 56px + nome + doc mascarado + badge tipo)
3. 4 stats carregam via `Inertia::defer` em ~300-800ms (total_invoice / invoice_due / total_purchase / opening_balance)
4. Tab default "Extrato" abre — range datas + Formato 1/2/3 + filtro localização
5. Larissa clica tab "Vendas" → Inertia partial reload `only:['sales']` paginated 20/página
6. Sidebar direita exibe: Celular / Fixo / E-mail / Endereço completo / **Bloco Fiscal BR** (CPF/CNPJ + IE/RG + IM + Regime + Consumidor Final + Contribuinte ICMS)
7. Larissa clica "Ações" dropdown → 8 opções: Pagar / Editar / Excluir / Activate-Deactivate / Add Discount / ...
8. Click "Add Discount" → modal canon abre, valor → confirma → flash success

## 5. Sub-componentes

- `resources/js/Pages/Cliente/Show.tsx` — page raiz
- `resources/js/Pages/Cliente/_show/PaymentsTab.tsx` — self-fetch `/contacts/payments/{id}` (W-A US-CRM-063)
- `resources/js/Pages/Cliente/_show/LedgerTab.tsx` — range + Formato + export PDF/email (W-B US-CRM-064)
- `resources/js/Pages/Cliente/_show/SalesTab.tsx` — Inertia partial reload (W-C US-CRM-065)
- `resources/js/Pages/Cliente/_show/DocumentsTab.tsx` — upload + notas autosave 1500ms (W-D US-CRM-066)
- `resources/js/Pages/Cliente/_show/ActionsMenu.tsx` + `AddDiscountModal.tsx` (W-E US-CRM-067)
- `resources/js/Pages/Cliente/_show/DadosFiscaisBRBlock.tsx` — sidebar bloco BR (Slice 3 PR #1316)

## 6. Estados (loading / empty / error / success)

| Estado | UI | Trigger |
|---|---|---|
| Header loading | Skeleton avatar + texto | Inertia render |
| Stats loading | 4 skeleton cards | `Inertia::defer` pendente |
| Tab Vendas empty | "Nenhuma venda registrada" pill stone | `sales.data.length === 0` |
| Tab Pagamentos empty | "Sem pagamentos" pill | `/contacts/payments/{id}` array vazio |
| Tab Documentos upload | Progress bar 0-100% | XHR multipart em andamento |
| Tab Documentos autosave | Pill amber "Salvando..." → emerald "Salvo" | debounce 1500ms |
| Erro stats | Toast rose + retry | `Inertia::defer` falhou |
| Cross-tenant | 404 (não 403) | business_id mismatch |

## 7. Atalhos de teclado

| Tecla | Ação |
|---|---|
| Tab / Shift+Tab | Navegação tabs (role="tab") |
| Enter (em tab focada) | Ativar tab |
| Ctrl+E | Editar (atalho dropdown — futuro) |
| Esc | Fechar modal ativo (Add Discount, etc) |

## 8. Dependências de API/backend

- `ContactController::show($id)` — Inertia props: contact, stats (defer), sales (defer paginated)
- `ContactController::buildClienteSalesPaginator($id, $filters)` — helper paginação
- `GET /contacts/payments/{id}` — JSON self-fetch PaymentsTab
- `GET /contacts/ledger` (params) — abre Blade legacy em nova aba (gap: inline futuro)
- `POST /contacts/send-ledger` — dispara email PDF
- `POST /post-document-upload` — multipart documento
- `POST /note-documents` (autosave 1500ms) — texto notas
- `POST /contacts/add-discount` — Modal AddDiscount

## 9. Multi-tenant + LGPD

- **Tier 0 (ADR 0093):** `Contact::where('business_id', session('business.id'))->findOrFail($id)` — 404 cross-tenant
- **PII:** `cpf_cnpj`, `ie_rg` mascarados via `maskTaxNumber` ANTES de mandar pro frontend
- **`bank_account_number`:** se exibido em sidebar, sempre via mask (últimos 4 dígitos)
- **Activity log:** logging "viewed" DESABILITADO (privacidade — Charter Anti-hook)
- **Export ledger:** PDF gerado server-side com PII completa (Larissa autorizada via permission)

## 10. Smoke check pós-deploy

```bash
# 1. Flag ON
ssh prod 'grep MWART_CLIENTE_SHOW /home/oimpresso/public_html/.env'

# 2. HTTP smoke
curl -sv "https://oimpresso.com/cliente/1234" -H "Cookie: laravel_session=<sess_biz4>" 2>&1 | grep -E "(HTTP/|component)"
# Esperado: HTTP/2 200 + "component":"Cliente/Show"

# 3. Tab partial reload
# Browser MCP: click tab Vendas → DevTools confirma X-Inertia-Partial-Data: sales

# 4. Cross-tenant
curl -sv "https://oimpresso.com/cliente/99999" -H "Cookie: laravel_session=<sess_biz4>" 2>&1 | grep "HTTP/"
# Esperado: 404

# 5. Bloco BR sidebar
# Browser MCP: validar CPF/CNPJ mascarado (formato 123.***.***-95)
```

## 11. Refs

- [ADR 0093 — Multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0104 — Processo MWART canônico](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)
- [ADR 0107 — Visual gate F1.5](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
- [ADR 0110 — Cockpit Pattern V2](../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- [ADR 0149 — Pattern reuse Crm](../../decisions/0149-mwart-screen-pattern-reuse-cowork.md)
- Charter: [`resources/js/Pages/Cliente/Show.charter.md`](../../../resources/js/Pages/Cliente/Show.charter.md) (v2 status live)
- PR #1298 — Wave 5 paralela paridade Show (US-CRM-063..067)
- PR #1316 — Slice 3 bloco fiscal BR sidebar
- Coord paralela: [`memory/sessions/2026-05-21-coord-cliente-show-paridade-5waves.md`](../../sessions/2026-05-21-coord-cliente-show-paridade-5waves.md)

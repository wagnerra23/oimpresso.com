---
title: "RUNBOOK — Sells/Index (`/sells`)"
module: Sells
tela: Sells/Index
owner: W
status: ativo
last_validated: "2026-05-26"
preconditions:
  - "Usuário autenticado com permission `sell.view` ou `sell.view_own` (Spatie UPOS canon)"
  - "business_id válido na sessão (multi-tenant Tier 0 ativo — ADR 0093)"
  - "Modules/NfeBrasil ativo se intenção é emitir NF-e/NFC-e/NFS-e (opcional)"
  - "business_settings.fiscal_certificate_active se Emit modais forem usados"
preconditions_short: sell.view, business_id ativo, NfeBrasil opcional
steps:
  - "GET /sells carrega lista paginada + 4 KPIs (Inertia::defer)"
  - "SubNav FOCO/Caixa/Faturamento/Comissão troca 4º KPI"
  - "Visões dropdown filtra cliente-side (Todas/Paga/Pendente/Faturada/Cancelada + Aguardando faturamento + Por origem ▾)"
  - "Click linha abre <Sheet> drawer 480px (SaleSheet) com produtos + pagamentos + WhatsApp 3-tab + FSM"
  - "Bulk: checkboxes múltiplos → BulkActionBar floating → Emitir NF-e em lote abre VdBulkEmitModal (PR #1648 wire-up)"
  - "Show.tsx: clica Emitir NFe/NFC-e no FISCAL section → VdNfeEmitModal/VdNfseEmitModal (PR #1644)"
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0104-processo-mwart-canonico-unico-caminho
  - 0107-emendation-0104-visual-comparison-gate-f3
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0180-pageheader-canon-v3-href-direto-sem-dropdown
  - 0190-primary-button-roxo-universal-295
  - 0192-auto-faturar-os-venda-jobsheet-observer
---

# RUNBOOK — Sells/Index (`/sells`)

> Rota: `/sells` (canon) · Componente: `resources/js/Pages/Sells/Index.tsx` (1698 linhas)
> Controller: `app/Http/Controllers/SellController@index`
> Charter: `resources/js/Pages/Sells/Index.charter.md` (pendente backfill — review_trigger 2026-06-15)
> Última atualização: 2026-05-26 (backfill pós PR #1644 MWART soft warn)

## 1. Objetivo

Lista vendas (pedidos · faturamento · NF-e/NFS-e) do business com 4 KPIs operacionais (Faturado hoje / Ticket médio / A receber / Top vendedor) + pipeline FSM progress dots por linha + saved views + bulk emit fiscal. Tela cockpit central de operação comercial — substitui Blade legacy `sell.index.blade.php` preservando Cockpit Pattern V2 + PageHeader v3 (ADR 0180).

## 2. Persona principal

**Wagner Admin** (biz=1 WR2 Sistemas) ou **Larissa @ ROTA LIVRE** (biz=4 vestuário) ou **balconista/vendedor** — operação diária: ver vendas do dia, filtrar pendentes, emitir NF em lote pra batch noturno, abrir drawer pra confirmar pagamento WhatsApp, criar OS cross-module pra produção/oficina.

## 3. Pré-requisitos

- Permission `sell.view` ou `sell.view_own` (Spatie UPOS canon)
- Multi-tenant Tier 0 ativo — `App\Transaction` (type='sell') filtrado por `business_id` global scope (ADR 0093)
- Modules/NfeBrasil ativo se for usar Emit modais (FISCAL section drawer)
- `business_settings.fiscal_certificate_active=1` + certificado A1 instalado se emissão real (sandbox/produção SEFAZ)
- Modules/Whatsapp ativo + sessão Meta Cloud autenticada se for usar contextos mensagem
- Modules/Copiloto ativo se botão `+IA` no drawer for usado

## 4. Fluxo principal (golden path)

1. Usuário navega `/sells`
2. PageHeader v3 renderiza com h1 "Vendas" + sub "Pedidos · faturamento · NF-e/NFS-e" + SubNav 4 abas (FOCO / Caixa / Faturamento / Comissão) + botão primary "Nova venda" (roxo 295 ADR 0190)
3. 4 KPI cards carregam via `Inertia::defer` (~300-500ms):
   - **Faturado hoje** R$ X (gráfico mini-spark)
   - **Ticket médio** R$ Y (delta vs semana passada)
   - **A receber** R$ Z (faixas 0-30d / 31-60d / +60d com `1 estourado · 43 frescos`)
   - **Top vendedor (mês)** nome ou "sem commission_agent atribuído este mês"
4. Toolbar linha 2: tabs Visão (Todas / Paga / Pendente / Faturada / Cancelada) + segmented control Operacional/Financeira/Produção + busca + Filtros avançados + Imprimir caixa + Visões ▾ dropdown
5. Tabela 10 colunas exibe primeiras 50 vendas: Venda · Data · Cliente · Atendido por · Origem · Pipeline (dots progress FSM) · Fiscal (NF-Ce/NF-e badges) · Pagamento · Total · Status
6. Click linha → `<Sheet>` lateral 480px (SaleSheet) com:
   - KV grid (#ID + status + itens/valor/pago/saldo)
   - Bloco Cliente + organização + timestamp
   - Section PRODUTOS (N) + PAGAMENTOS (M) com checkmark FSM
   - **MENSAGEM WHATSAPP** 3 tabs (Confirmação/Retirada/Cobrança) + variáveis dinâmicas + preview ao vivo (PR #1638)
   - **FISCAL** section com botões "Emitir NFC-e" / "Emitir NFe" (PR #1644 VdNfeEmitModal/VdNfseEmitModal — z-index 100 pós PR #1648)
   - **PIPELINE FSM** com botões coloridos canon Cowork (✓ Aprovar · 📄 Faturar · 📦 Entregar · 💰 Receber · ⊘ Cancelar — PR #1641 VdNextActionPanel)
   - **ORDEM DE SERVIÇO** cross-module "Criar OS" (idempotente, "1 OS pra venda toda" ou "1 OS por produto")
   - **HISTÓRICO** append-only auditoria FSM real
   - Footer: Emitir cobrança · Transcript · Apresentar · Imprimir · Editar (azul primary)
   - Botão "+IA" top right abre Copiloto contextual
7. **Bulk:** selecionar múltiplos checkboxes → `BulkActionBar` floating aparece com "N selecionadas · Emitir NF-e em lote (primary verde) · Marcar como pagas · Exportar XML/PDF · ✕" → clica "Emitir em lote" → `VdBulkEmitModal` abre (PR #1648 wire-up) com progress tricolor pending→running→ok|bad
8. **Saved view "Aguardando faturamento"** (KB-9.75 P1 gap #7 PR #1644) — filtra cliente-side `payment_status !== 'paid' && fiscal_status === null` — fluxo otimizado batch noturno
9. **Saved tree "Por origem ▾"** (Onda 4 ADR 0192) — filtra por `source` (Cowork/Wpp/OS-loja/etc)

## 5. Sub-componentes

- `resources/js/Pages/Sells/Index.tsx` — page raiz (1698 LOC)
- `resources/js/Pages/Sells/_components/SaleSheet.tsx` — drawer 480px
- `resources/js/Pages/Sells/_components/SaleMessagePreview.tsx` — preview WhatsApp 3-tab
- `resources/js/Pages/Sells/_components/SaleTimeline.tsx` — histórico append-only
- `resources/js/Pages/Sells/_components/VdNextActionPanel.tsx` — FSM cockpit com emojis canon Cowork (PR #1641, override aprovado #1641-4545772140)
- `resources/js/Pages/Sells/_components/VdBulkEmitModal.tsx` — bulk emit fullscreen
- `resources/js/Pages/Sells/_components/VdNfeEmitModal.tsx` — emit NF-e single
- `resources/js/Pages/Sells/_components/VdNfseEmitModal.tsx` — emit NFS-e single
- `resources/js/Pages/Sells/_components/FiscalSection.tsx` — FISCAL drawer section
- `resources/js/Pages/Sells/_components/CobrancaDrawer.tsx` — emitir cobrança
- `resources/js/Pages/Sells/_components/CriarOsButton.tsx` — cross-module OS
- `resources/js/Pages/Sells/_components/SaleAiPanel.tsx` — botão +IA Copiloto
- `resources/js/Pages/Sells/_components/CommissionSplitEditor.tsx` — splitter comissão
- `resources/js/Pages/Sells/_components/QuickPaymentDialog.tsx` + `QuickPaymentPopover.tsx`
- `resources/js/Pages/Sells/_components/SaleItemComments.tsx`
- `resources/js/Pages/Sells/_components/SellsCheatSheet.tsx` — atalhos `?`
- Shared: `PageHeader` (v3 ADR 0180), `Sheet` (shadcn), `KpiCard`

## 6. Estados (loading / empty / error / success)

| Estado | UI | Trigger |
|---|---|---|
| Loading KPIs | Skeleton stones em 4 cards | `Inertia::defer` pendente |
| Empty | Pill stone "Nenhuma venda neste período" + CTA "Nova venda" | `sales.count === 0` |
| Error de fetch | Toast vermelho + retry | `props.error` truthy |
| Success | 4 KPIs + tabela 50 linhas + pipeline dots | render padrão |
| Drawer loading | Skeleton sections | fetch `/sells/{id}/sheet-data` em vôo |
| Bulk modal running | Progress tricolor por item (pending→running→ok|bad) | mock SEFAZ 600-1200ms/item |
| Validação fiscal BR fail | Erro inline em FISCAL section drawer | `validacoesFiscaisBr.ts` detecta cliente sem CNPJ/CPF/idEstrangeiro (PR #1641) |

## 7. Atalhos de teclado

| Tecla | Ação |
|---|---|
| ⌘K / Ctrl+K | Abrir command palette (KB-9.75 Slice A) |
| J / K | Navegar próxima/anterior linha |
| Enter | Abrir drawer da linha selecionada |
| / | Foco no search box |
| ? | Abrir SellsCheatSheet (atalhos) |
| Esc | Fechar palette OU drawer OU modais |

## 8. Dependências de API/backend

- `SellController::index()` — retorna `sales` (paginated 50) + `kpis` (faturado_hoje, ticket_medio, a_receber, top_vendedor) + `subview_counts` (todas/paga/pendente/faturada/cancelada)
- `SellController::sheetData($id)` — drawer detail (Transaction + items + payments + activities)
- `SellController::messageContextPayload($id, $context)` — WhatsApp preview text com variáveis substituídas
- `SellController::printInvoice($id)` — receipt HTML pra IFRAME imprimir (modo invoice/packing_slip/delivery_note via `printSaleReceipt.ts`)
- `Modules/NfeBrasil/Http/Controllers/NfeController` — endpoint emit single (NFe/NFCe/NFSe)
- `Modules/Copiloto` — botão +IA dispara contexto venda → Brain A/B
- `App\Transaction` model (type='sell'): global scope `business_id` (UPOS canon)

## 9. Multi-tenant + LGPD

- **Tier 0 (ADR 0093):** `App\Transaction::where('business_id', $business_id)` em TODA query — global scope automático. Controller defensive guard explícito quando aplicável.
- **PII cliente:** CPF/CNPJ exibido com máscara via `maskTaxNumber($value)` backend. Plain text NUNCA chega ao frontend. Drawer fiscal renderiza completo (papel cliente protegido fisicamente).
- **PII pagamento:** valores brutos OK exibir (Reais), referências externas (NSU/aut. cartão) com mask parcial.
- **Activity log:** `Transaction` model loga apenas changes audit-relevant. Não logga payload completo.
- **Drawer:** acesso cross-tenant retorna 404 (não 403 — evita enumeração).
- **Bulk emit:** `BulkEmitItem.id` validado por business_id antes de enqueue Job.
- **VdBulkEmitModal mock:** atual é UI stub com setTimeout — quando wire backend real `/sells/{id}/emit-nfe`, cada call passa business_id implícito via session.

## 10. Smoke check pós-deploy

```bash
# 1. Verificar deploy + flag MWART_SELLS_INDEX
ssh prod 'cd /home/oimpresso/public_html && grep -E "MWART_SELLS|NFE_AMBIENT" .env'

# 2. HTTP smoke (curl real, NÃO declaração otimista — skill smoke-prod-evidence)
curl -sv https://oimpresso.com/sells -H "Cookie: laravel_session=<sess_biz1>" 2>&1 | grep -E "(HTTP/|component)"
# Esperado: HTTP/2 200 + "component":"Sells/Index"

# 3. KPIs Inertia::defer (SPA partial reload)
curl -sv 'https://oimpresso.com/sells' -H 'X-Inertia: true' -H 'X-Inertia-Partial-Data: kpis' \
  -H 'Cookie: laravel_session=<sess>' 2>&1 | jq '.props.kpis'

# 4. Multi-tenant isolation
# biz=1 não enxerga venda de biz=164 — testar com 2 sessões diferentes
# SELECT * FROM transactions WHERE business_id=1 AND id=<id_sale_biz_164>; -- deve retornar 0 rows

# 5. Bulk wire-up (PR #1648)
# Browser DevTools: window.dispatchEvent disparado no completed → verificar listener

# 6. Validações fiscais BR (PR #1641)
# Tentar emitir NFe em venda com cliente sem CNPJ → deve retornar erro inline 'CNPJ ausente'
```

## 11. Receitas alternativas

### Receita batch noturno faturamento (Aguardando faturamento)
1. Click saved view "Aguardando faturamento" no dropdown Visões
2. Filtra clientside: payment_status !== 'paid' && fiscal_status === null
3. Select all (checkbox header) ou múltiplas linhas
4. BulkActionBar floating: "Emitir NF-e em lote"
5. VdBulkEmitModal abre com progress tricolor — aguardar conclusão
6. Toast "Lote concluído · X ok / Y falhas" — refresh lista

### Receita venda → OS cross-module
1. Drawer venda aberta
2. Section ORDEM DE SERVIÇO → "Criar OS ▾" dropdown
3. Escolher modo: "1 OS pra venda toda" (caçambas/gráfica) ou "1 OS por produto"
4. Idempotente — clicar 2x não duplica (verifica `Modules/Repair::JobSheet::existsForSale($saleId)`)

### Receita WhatsApp contextual
1. Drawer venda
2. Section MENSAGEM WHATSAPP → tab Confirmação/Retirada/Cobrança
3. Variáveis tags clickáveis: `cliente · id · total · forma · saller · prazo · vencimento · status · data`
4. Preview ao vivo do texto substituído
5. "Abrir no WhatsApp" (verde primary) → wa.me URL com texto preenchido

## 12. O que NÃO fazer

- ❌ NÃO criar OS sem cliente cadastrado (Contact.id obrigatório — Tier 0 + LGPD)
- ❌ NÃO duplicar emissão SEFAZ pra mesma venda (constraint `nfe_emissoes_biz_fx_unique` — ver smoke real 2026-05-26)
- ❌ NÃO bypass validações fiscais BR (`validacoesFiscaisBr.ts`) — cliente sem CNPJ/CPF não emite
- ❌ NÃO marcar "paga" sem pagamento real registrado (audit-log enforce + FSM lock)
- ❌ NÃO editar venda finalizada (status=paid OU faturado) — usar estorno/cancelamento
- ❌ NÃO força-refresh durante bulk emit em execução (cancela operação)
- ❌ NÃO commit de cores hardcoded no `_components/Vd*.tsx` (override aprovado APENAS pra emojis canon Cowork — ADR per-tela)

## 13. Diagnóstico/Troubleshoot

| Sintoma | Causa provável | Fix |
|---|---|---|
| Modal "Emitir NF-e em lote" não abre | (RESOLVIDO PR #1648) BulkActionBar onClick faltava | Verificar deploy chegou pós-PR #1648 |
| Modal Emit aparece atrás do drawer | (RESOLVIDO PR #1648) z-index 50 conflitava com Sheet | Verificar `.vd-emit-bd { z-index: 100 }` em CSS |
| Validação fiscal BR bloqueia | Cliente sem CNPJ/CPF/idEstrangeiro | Editar contato + adicionar tax_number |
| Cross-tenant 404 ao abrir drawer | Tentativa de acessar venda de outro business_id | Comportamento esperado (Tier 0) — verificar URL correta |
| "Duplicate entry" SQLSTATE 23000 | Venda já tem emissão SEFAZ prévia | Verificar `nfe_emissoes` table — estornar antes de re-emitir |
| Drawer carrega vazio | `Inertia::defer` falhou OU props.sale null | Verificar Network tab + SellController::sheetData |
| KPIs travados em skeleton | Backend lento OU exception em buildKpisPayload | Verificar logs `storage/logs/laravel.log` |

## 14. Integrações cross-module

- **Modules/NfeBrasil** — emissão SEFAZ (NFe/NFCe/NFSe single + bulk)
- **Modules/PaymentGateway** — emitir cobrança boleto/PIX/cartão (Onda 4f.0 PR #1587)
- **Modules/Whatsapp** — preview + envio mensagem contextual
- **Modules/Copiloto** (Jana) — botão +IA Brain A/B contextual venda
- **Modules/Repair** — Criar OS cross-module (1 venda → N OS)
- **Modules/Fiscal** — config certificado A1 + ambient (sandbox/prod SEFAZ)
- **Modules/Auditoria** — append-only HISTÓRICO via ActivityLog
- **Modules/RecurringBilling** — assinaturas/recorrências (se venda for recurring)

## 15. Refs

- [ADR 0093 — Multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0094 — Constituição v2](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)
- [ADR 0104 — Processo MWART canônico](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)
- [ADR 0107 — Visual gate F1.5](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
- [ADR 0143 — FSM canon LIVE prod](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
- [ADR 0180 — PageHeader v3 canon](../../decisions/0180-pageheader-canon-v3-href-direto-sem-dropdown.md)
- [ADR 0190 — Primary roxo 295 universal](../../decisions/0190-primary-button-roxo-universal-295.md)
- [ADR 0192 — Auto-faturar OS→Venda Observer](../../decisions/0192-auto-faturar-os-venda-jobsheet-observer.md)
- PRs recentes: #1638 KB-9.75 bundle · #1641 VdNextActionPanel + validações fiscais BR · #1644 Emit modais + Bulk + saved view · #1647 charters Edit · #1648 fix BulkActionBar wire-up + z-index
- Charter Sells/Index (pendente backfill — review_trigger 2026-06-15)
- Sister RUNBOOK: [`RUNBOOK-create.md`](RUNBOOK-create.md)
- Visual comparison: `Sells-r4-cowork-kb975-2026-05-26-visual-comparison.md`
- Override emoji canon: PR #1641 comment 4545772140 (`/mwart-override` Wagner aprovado)

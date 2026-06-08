# Sessão — 2026-05-26 — Fix Imprimir na tela de Venda (tela branca + 3 modos legacy restaurados)

## Contexto

Wagner reportou 2 bugs na impressão de venda:
1. **Tela em branco** ao clicar "Imprimir" no Show.tsx (`/sells/{id}`) e no drawer SaleSheet do Index.
2. **Data do recibo com +3h à frente** do real.

## Diagnóstico

### Bug 1 — Tela em branco
- [Show.tsx:205](../../resources/js/Pages/Sells/Show.tsx) e [SaleSheet.tsx:741](../../resources/js/Pages/Sells/_components/SaleSheet.tsx) usavam `<a href={urls.print} target="_blank">` — navegação HTML normal.
- [SellPosController::printInvoice:1928](../../app/Http/Controllers/SellPosController.php) só responde a requests AJAX (`if (request()->ajax())`).
- Sem AJAX header → controller retorna nada → tela em branco.
- Pattern legacy ([public/js/app.js:1656](../../public/js/app.js)) era `a.print-invoice` interceptado por jQuery: AJAX → recebe `result.receipt.html_content` → injeta em `#receipt_section` (div oculta do `layouts/app.blade.php:108`) → `window.print()` do documento atual.

### Bug 2 — +3h de drift
- [Util.php:297](../../app/Utils/Util.php) `format_date()` usa `Carbon::createFromTimestamp(strtotime($date))->format()` → double timezone shift = +3h.
- **Bug INTENCIONAL documentado** ([feedback-carbon-timezone-bug.md](../reference/feedback-carbon-timezone-bug.md) + [2026-04-24-revert-format-date-timezone.md](2026-04-24-revert-format-date-timezone.md)):
  - Fix anterior (`10634ad2`) foi revertido (`e5c8c90d`) porque ROTA LIVRE (Larissa, biz=4) reclamou "as vendas voltaram 3 horas pra trás".
  - Cliente memorizou horários errados nos recibos físicos, conferências de caixa, rotina diária.
  - Plano formal de 5 passos pra reaplicar (levantamento por cliente + migration condicional + comunicação prévia + reaplicar `Carbon::parse` + ADR formal) **não foi executado**.

## Decisão e Entrega

### Bug 1 — Consertado em duas iterações

**v1 ([PR #1628](https://github.com/wagnerra23/oimpresso.com/pull/1628), commit `0745e7658`)**

- Criou helper `resources/js/Lib/printSaleReceipt.ts` (convenção `@/Lib` capital).
- Show.tsx + SaleSheet.tsx: trocaram `<a target="_blank">` por `Button onClick`.
- Handler faz `fetch` AJAX → recebe `receipt.html_content` → abre nova janela com o HTML via `window.open()` + script inline que chama `window.print()`.
- Estado `isPrinting` desabilita botão durante request.
- Validado em prod: endpoint devolveu `success:1`, `html_len:3822`, `print_title:"OS00129"`. Popup foi bloqueado pelo Chrome MCP automation (não conta como user-gesture), mas em clique humano real funcionaria.

**Feedback Wagner pós-deploy**: *"a impressão era mais bonita e com mais opções"*. Comparativo mostrou 4 regressões vs Blade legacy:

| Aspecto | Legacy | v1 |
|---|---|---|
| Bootstrap 3 CSS | herdado da página atual | popup sem Bootstrap → layout quebrado |
| Opções extras | dropdown 3 modos (`invoice` / `?package_slip=true` / `?delivery_note=true` — [sale_pos/show.blade.php:413/416](../../resources/views/sale_pos/show.blade.php), [SellController:437-440](../../app/Http/Controllers/SellController.php)) | só 1 botão |
| `__currency_convert_recursively` | chamado antes do print | ignorado |
| Popup-blocker | N/A | atinge popup em alguns contextos |

**v2 ([PR #1634](https://github.com/wagnerra23/oimpresso.com/pull/1634), commit `0ee82495b`)** — refator do helper:

- Substituiu `window.open()` por **iframe oculto** (`position:fixed; w:0; h:0; visibility:hidden`) na própria página Inertia.
- `srcdoc` do iframe carrega 3 stylesheets legacy: `/css/app.css` (Bootstrap 3 + `.print_section` + `.invoice`), `/css/init.css`, `/css/tailwind/app.css` (utilitários `tw-*`).
- Injeta `html_content` dentro de `<section class="invoice print_section" id="receipt_section">` — estrutura idêntica ao [layouts/app.blade.php:108](../../resources/views/layouts/app.blade.php).
- Script no iframe tenta chamar `window.parent.__currency_convert_recursively` (compat legacy) → depois `window.print()` do contexto do iframe.
- Cleanup automático após 30s.
- UI virou **DropdownMenu** (radix) com 3 modos (`Recibo / fatura`, `Romaneio / packing slip`, `Nota de entrega`) em Show.tsx + SaleSheet.tsx. Atalho `P` em Show dispara modo `invoice`.
- Backend não muda — `SellPosController::printInvoice` já tratava `$is_package_slip`/`$is_delivery_note` ([linhas 1950/1951](../../app/Http/Controllers/SellPosController.php)).

**Validado em prod via Chrome MCP**: dropdown abriu com 3 opções, fetch AJAX retornou 200 + html_content válido, iframe foi criado, `window.print()` disparou diálogo nativo de impressão do Windows duas vezes consecutivas (uma vez por modo testado).

### Bug 2 — NÃO mexido

Permanece como bug intencional. Pra atacar de novo: seguir plano formal de 5 passos do [revert session log de 2026-04-24](2026-04-24-revert-format-date-timezone.md). Wagner aprovou explicitamente "Só Bug 1 (tela branca) agora".

## Artefatos

- **Branches:** `fix/sells-print-blank-screen` (v1, merged + deleted) · `fix/sells-print-v2-iframe-3modes` (v2, merged + deleted)
- **PRs:** [#1628](https://github.com/wagnerra23/oimpresso.com/pull/1628) (v1) · [#1634](https://github.com/wagnerra23/oimpresso.com/pull/1634) (v2)
- **Commits em main:** `0745e7658` (v1) · `0ee82495b` (v2)
- **Deploys Hostinger:** auto após v1 merge · manual via `gh workflow run "Deploy to Hostinger"` após v2 merge (workflow é `workflow_dispatch` only)
- **Arquivos canônicos:**
  - [resources/js/Lib/printSaleReceipt.ts](../../resources/js/Lib/printSaleReceipt.ts) — helper compartilhado (criado v1, refatorado v2)
  - [resources/js/Pages/Sells/Show.tsx](../../resources/js/Pages/Sells/Show.tsx) — DropdownMenu 3 modos + atalho P
  - [resources/js/Pages/Sells/_components/SaleSheet.tsx](../../resources/js/Pages/Sells/_components/SaleSheet.tsx) — DropdownMenu 3 modos no drawer

## Lições

1. **Comparar com legacy antes de "consertar"**: o `<a target="_blank">` original do MWART tinha 4 regressões silenciosas vs o JS do Blade — só sentidas pelo Wagner como "impressão mais feia, faltam opções". Antes do v1 eu deveria ter lido `public/js/app.js:1656` e o pattern `#receipt_section` no `layouts/app.blade.php`.
2. **Iframe > popup pra impressão browser-style**: sem popup-blocker, com CSS herdado opcional (via stylesheets explicitos), sem perda de user-gesture. Pattern aplicável a outros lugares que precisem impressão A4 (Crm/print, Repair/job-card, etc).
3. **Bug intencional × correção matematicamente correta**: documentar o "porquê não consertar" é tão valioso quanto consertar. Sem o session log de 2026-04-24 eu poderia ter quebrado ROTA LIVRE de novo.
4. **Wagner pediu "não pergunte faça sempre"** após o v1 — virou feedback-memory pra não repetir fricção de "quer que eu rode o smoke?" depois de fix óbvio.

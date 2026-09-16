# EXPORT Estoque · ONDA 1.2 — aba **Transferências** (`/stock-transfers`)

> 1 arquivo = 1 aba = 1 onda = 1 PR (≤300 linhas). Pedido zero-toque; passa no teste do estranho.
> Emitido 2026-09-04 por [CC]. Alvo = protótipo **medido**. Âncora = arquivo real do `main` **lido no turno da medição**.

## BLOCO 1 — Âncora dupla

**Lido no `main` (2026-09-04, árvore `fb2240427978`):**
- `resources/js/Pages/StockTransfer/Index.tsx` (10.548 B) — a LIST viva
- `resources/js/Pages/StockTransfer/Index.charter.md` — persona **Maiara**; R-XFER-001..004; `bundle_source: estoque-page.jsx`
- `resources/js/Pages/StockAdjustment/Index.tsx` — lido como par de densidade (o charter diz "inspirado em Purchase/Index.tsx, mesma densidade")
- `resources/js/Pages/Estoque/Movimentacao.casos.md` — **só linhas de match** → integral = *não verifiquei*

**Não verificado neste turno:** `StockTransfer/Create.tsx`, `memory/requisitos/Inventory/RUNBOOK-stock-transfer-index.md` (o `@memcofre` do arquivo aponta pra ele), controllers/rotas, `AppShellV2.tsx`, `Components/ui/*`.

**Alvo medido:** host `oimpresso.com.html`, rota `est-transferencias`, tema dark, após `__oiLazyDone` **e duas leituras iguais** do DOM. Sonda validada antes do veredito. ✅

## BLOCO 3 — Alvo medido

**TabBar** — idêntica à onda 1.1: `<nav class="ds-tabbar jm-tabs" role="tablist" aria-label="Áreas de Estoque">`, h **36px**, `padding: 0 24px`, borda inferior **1px `oklch(0.34 0.008 240)`**, tab `0 14px`/**13px**, ativa `600`/`oklch(0.94 0.005 90)`. Contador da aba = **transferências em aberto** (2), não o total — a aba mede trabalho pendente.

**Grade** — linha **54px** (mesma métrica de Ajustes), `thead` 10px uppercase sticky.
Colunas: `☐` · Transferência · **Data ↓** · Origem → destino · Status · **Mexeu no saldo?** · Itens · Frete · Total.

**Toolbar** h **48px**: `Buscar na lista` (`/`) · Compacto · CSV · Excel · Imprimir · **Colunas** · select `Todos os locais / Matriz / …` · `Nova transferência`.

**Cor crua no alvo: 0** ocorrência em `style` inline sob `.est-root`.

## BLOCO 2 — a11y do alvo (A1–A12)

✅ 0 `DIV` clicável anônimo · 0 botão sem nome acessível · 0 campo sem rótulo (busca com `aria-label="Buscar na lista"`) · `role="tablist"` correto · 0 cor crua inline · linha clicável com teclado (`role="button"` + `tabindex="0"`, herdado da mesma grade da 1.1).
⚠️ Anel de foco: **inconclusivo** (limitação da sonda).
❌ `th` sem `scope="col"` · `svg` sem `aria-hidden` dentro de botão nomeado → **bundle do DS**. ❌ 0 `<main>` → **host**.
**Corrigido no build daqui: nada** — nenhum defeito é do módulo.

## BLOCO 4 — Delta alvo × `main`

| # | Assunto | `main` hoje | Alvo | Veredito |
|---|---|---|---|---|
| T1 | Status | **cor crua Tailwind**: `bg-rose-50 text-rose-700`, `bg-amber-50 text-amber-800`, `bg-emerald-50 text-emerald-700` em `STATUS_PILL` | `StatusBadge kind="os"` do DS (tinta 6% + borda 22%) | **pedido** — o delta mais duro; viola zero-cor-crua e AP7 |
| T2 | **"Mexeu no saldo?"** | coluna **ausente** — R-XFER-003/005 e INV-2 invisíveis na UI | coluna explícita derivada do status terminal | **pedido** — regra do charter virando pixel |
| T3 | Grade | `<table>` à mão, sem seleção/ordenação/colunas | `DataGrid` + `ColumnManager` | **pedido** |
| T4 | Densidade | `h-11` (44px) | 54px + tweak compacto (`Segmented`) | **pedido** |
| T5 | Exclusão | `confirm()` nativo | `Modal` do DS (PT-04) | **pedido** |
| T6 | Imprimir | `window.open('/stock-transfers/:id/print')` — aba nova | `PresenterMode` do DS | **pedido opcional** — só se [W] quiser tirar o pop-up |
| T7 | Rótulo de status | ✅ vem do servidor (`statuses[s]`) com fallback local | protótipo tem só o fallback | **`main` à frente — NÃO virar pedido.** Preservar `labelStatus()` |
| T8 | Partial reload D-14 | ✅ `only:['rows','filters']`, comentando que `statuses` é estático por request | sem rede | **`main` à frente — NÃO TOCAR** |
| T9 | Permissões | ✅ `view_purchase_price` mascara frete e total com `—`; `permissions.delete` esconde a ação | espelhado | **paridade — não mexer** |

## BLOCO 5 — ARQUIVOS

**A EDITAR** — `resources/js/Pages/StockTransfer/Index.tsx` (**só este**).

**A REUSAR (não recriar)** — `Components/shared/PageHeader.tsx` · `Components/ui/{button,card,input,select,table,dialog}.tsx` · tokens `cockpit.css` + `foundations.css` · o `ArrowRight` da Lucide já usado na célula origem→destino.

**NÃO TOCAR** — `labelStatus()` e o dicionário `statuses` do servidor (T7) · `aplicar()`/partial reload (T8) · `permissions` · `router.delete` e a rota `/print` · `StockTransfer/Create.tsx` (onda 1.7) · charters e `Movimentacao.casos.md`.

**PARAR SE** — passar de 1 PR/300 linhas · não existir `DataGrid`/`ColumnManager` no `main` (**reportar, não portar na mão**) · a derivação de "Mexeu no saldo?" precisar de campo novo no payload (**parar: é mudança de contrato server-side, não de tela**) · as abas exigirem rota nova `/estoque/*` ([W]) · precisar de `<main>` extra (AP9).

## BLOCO 6 — Onda
**1.2 · aba Transferências · view única.** Fecha em `Index.tsx`. Não abre 1.7 (Nova transferência) no mesmo PR.

## BLOCO 7 — O que a ancoragem NÃO resolve
1. `scope`/`aria-hidden` = **bundle do DS**; `<main>` = **host**. Sem dono hoje.
2. **`RUNBOOK-stock-transfer-index.md` não lido** — o `@memcofre` do arquivo o cita como fonte; pode conter regra que este pedido ignora. Ler antes de executar.
3. Semântica exata de `pending` × `in_transit` × `completed` × `final` (4 status, 2 rótulos iguais pra `completed`/`final`) — **não verifiquei** se `final` é status morto. Se for, some da UI; **não decidir sozinho**.
4. `Movimentacao.casos.md` lido só por match — INV-1/4/6 **não verificados**.
5. Sem **T7 (gate)** → nada aqui é "0 bug" nem "igual ao design".

## BLOCO 8 — Contrato de tela (ADR 0286)
A criar **no repo**: `prototipo-ui/contrato/stock-transfer-index.contract.json` — seções (PageHeader → TabBar → Toolbar → DataGrid), copy literal ("Transferências de estoque", "Nova transferência", "Origem → destino", "Mexeu no saldo?", "Em trânsito", "Concluída", "Nenhuma transferência registrada.", "Nenhuma transferência com os filtros atuais.") e estados (vazio-primeiro-uso × vazio-com-filtro × sem-permissão-de-preço × em-trânsito).

## BLOCO 9 — DoD por máquina
`prototipo-readiness.mjs` · `cowork-ssot-guard.mjs` (R1/R2/R3) · `cowork-mirror-freshness.mjs --absent-local --check-orfaos --check-refs` · gate `design-memory-gate.yml` verde · `design-diff --compare --check` **só no T7**, prod deployada.

## BLOCO 10 — Placar
Âncora lida no turno: **3 integrais + 1 parcial** · alvo medido: **sim** · a11y: **6 ✅ · 3 ❌ (DS/host) · 1 ⚠️** · corrigido daqui: **0** · deltas: **6 pedido (T1–T6) · 3 paridade/à frente (T7–T9)** · cor crua no alvo: **0** · na produção: **3 famílias de cor crua** (rose/amber/emerald) · autoridade de token: **DS `StatusBadge` + `TabBar` → protótipo → produção** ✅ · escrito no git: **não** · pacote `sync/` regenerado: **não** · blocos **10/10**.

```
node scripts/design-sync/gerar-payload-partes.mjs --root prototipo-ui/cowork --out sync/ --previous sync/bundle.manifest.json
```

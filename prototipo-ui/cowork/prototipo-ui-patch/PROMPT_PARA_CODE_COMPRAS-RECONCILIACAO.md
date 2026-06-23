# [CC]→[CL] · Compras — reconciliar `Index.tsx`/`Drawer.tsx` ao protótipo (4 autos do Tribunal)

> **Origem:** Tribunal Adversário da tela `/compras` (Cowork, 2026-06-17) + revisão [W] "autorizado".
> O **protótipo Cowork** (`public/cowork-preview/erp-shell-v2/compras-page.{jsx,css}`) é a referência;
> o git divergiu em 4 pontos. As ações pesadas (dropdown Ações, Toolbar/VisibilidadeColunas,
> SortHeader, SummaryFooter, paginação, `Inertia::defer`) **já existem no git** — NÃO recriar.
> Esta ponte cobre só as **4 divergências** onde o git ficou atrás.
>
> **§10.4:** valide contra `main`. Se algo aqui contradiz o repo, o repo vence.
> **AUTO-CONTIDO** — o componente canon (`@/Components/PageHeader`) e o shell já estão no `main`.

---

## Réus (lidos @main nesta sessão)
- `resources/js/Pages/Compras/Index.tsx` — header `.hd` ad-hoc + drawer split inline (`.bd.with-drawer`).
- `resources/js/Pages/Compras/components/Drawer.tsx` — `<aside className="drawer">` dentro do grid.
- `Modules/Compras/Services/ComprasService.php` — `calcularKpis()` devolve **contagem** em `aberto`.
- `resources/css/cowork-compras-bundle.css` — `.hd`, `.bd`, `.drawer` (split).

UMA ONDA = UM PR. Ordem sugerida: C-1 → C-2 → C-3 → C-4.

---

## C-1 · Auto III — Cabeçalho canon (substituir `.hd`)
**Crime:** `Index.tsx` renderiza `.hd` (crumbs "ERP · Operação · Compras" + `<h1>` 16px) ignorando o
`<PageHeader>`/`<PageHeaderPrimary>` canon (ADR 0189/0190) que o resto do ERP já adotou (2026-06-16).

**Pena:** trocar o bloco `<header className="hd">…</header>` por:
```tsx
import { PageHeader, PageHeaderPrimary } from '@/Components/PageHeader';

<PageHeader
  title="Compras"
  subtitle={<>{filteredCount} de {totalRows} notas</>}
  actions={<>
    <button className="btn" disabled title="Importar XML DF-e (Wave 6)">↓ Importar XML</button>
    {permissions.create && (
      <PageHeaderPrimary label="+ Nova compra"
        onClick={() => router.visit('/purchases/create')} />
    )}
  </>}
/>
```
A busca (`⌕`) hoje embutida no `.hd` vai pra Toolbar (já existe) ou pra zona de ações; crumbs saem
(o breadcrumb global do AppShellV2 já cobre). Remover regras `.hd*` do `cowork-compras-bundle.css`.

---

## C-2 · Auto II — Drawer overlay lateral (não split inline)
**Crime:** o detalhe é uma **coluna fixa** no grid `.bd.with-drawer` (rouba 480–540px da lista).
O protótipo já virou **overlay lateral** igual ao da Venda (ADR Cockpit V2 — `os-drawer-back` +
`os-drawer wide`, slide-in da direita, backdrop, Esc fecha).

**Pena:** portar o `Drawer.tsx` pro padrão sheet do cockpit:
- raiz `<div className="os-drawer-back" onClick={onClose}>` + `<aside className="os-drawer wide cmp-drawer" onClick={stop}>`;
- cabeçalho `os-drawer-head` (id `#{id} · {ref}` + `<h2>` fornecedor + subtítulo `data · N itens · local · prazo`)
  com **total grande à direita** (`cmp-drawer-total`) + pill de estágio + `icon-btn` fechar;
- manter FSM stepper, `drw-tabs`, conteúdo das 5 abas;
- rodapé `os-drawer-actions` (sticky): **Fechar** (ghost) + 1 ação primária = **verbo do estágio**
  (`Enviar pedido → / Marcar recebida → / Conferir itens → / Pagar agora → / Concluir →`),
  nunca um "Executar →" genérico (Auto I — corrigido no protótipo).
- a lista volta a ocupar a largura toda; `.bd.with-drawer` / coluna do drawer saem do CSS.

Markup + CSS exatos em `compras-page.jsx` (`DrawerView`) e `compras-page.css` (`.os-drawer.wide`,
`.cmp-drawer-total`, `.drw-tabs`, `.fsm`, `.sec`).

---

## C-3 · Auto IV — KPI "A pagar" em R$ (não contagem)
**Crime:** `KpisGrid` mostra `k.aberto` como **número de compras**; o protótipo mostra **R$ valor** +
subtítulo "N compras". "A pagar: 3" é ambíguo (3 compras? R$ 3?).

**Pena (backend + frontend):**
- `ComprasService::calcularKpis()` passa a devolver `aberto_valor` = `SUM(amount_due)` das compras com
  `due > 0` (scoped `business_id`, ADR 0093), além de manter a contagem.
- `KpisGrid`: card "A pagar" renderiza `fmtMoney(k.aberto_valor)` como herói + `<div className="ln">{k.aberto} compras em aberto · próx. venc. …</div>`. Dinheiro nunca disputa rótulo com contagem.

---

## C-4 · Auto VI — Ordenação acessível (button + aria-sort)
**Crime:** `SortHeader` é `<th onClick>` — não focável por Tab, sem `aria-sort`, sem ativação por
Enter. Larissa opera no teclado (WCAG 2.1).

**Pena:** `<th aria-sort={active ? (dir==='asc'?'ascending':'descending') : 'none'}>` contendo um
`<button type="button" className="cmp-sort-btn" onClick={toggle}>{label} {arrow}</button>`
(largura 100%, focus-visible com anel roxo). Espelho exato em `compras-page.jsx` (`SortTh`) +
`compras-page.css` (`.cmp-sort-btn`). Vale citar pro F3.5 [CA].

---

## Auto VIII (densidade 1280px) — resolve via C-2
Com o drawer virando overlay (C-2), a lista deixa de perder 540px e cabe a 1280 sem scroll-x.
Opcional: default ocultar `nfe`/`itens` em `≤1280` no `useColumnVisibility` (a visibilidade já existe).

---

## Não-fazer (anti-drift)
- ❌ NÃO recriar AcoesDropdown / Toolbar / VisibilidadeColunas / SortHeader / SummaryFooter — **já existem**.
- ❌ NÃO reverter a convergência C1 ("+ Nova compra" segue `router.visit('/purchases/create')`).
- ❌ NÃO inventar dados — KPI `aberto_valor` vem de query real, não mock.

## Prova (Pest GUARD)
- `calcularKpis` retorna `aberto_valor` = soma direta de `amount_due` (teste de invariante).
- snapshot/axe: `<th>` ordenável tem `aria-sort` e botão focável.
- visual: header = `<PageHeader>` canon; drawer abre como overlay (não coluna).

> NÃO está commitado — o Code resolve com este pedido. [W] cola 1×.

---
tela: Fiscal/Nfe
url: /fiscal/nfe
status: approved
approver: wagner
approved_at: 2026-05-20
prototype_source: "prototipo-ui/Oimpresso ERP Conunicação Visual. Ultimotopo/fiscal-page.jsx §9 FiscalNFePage"
implementation: resources/js/Pages/Fiscal/Nfe.tsx
adr: 0107
---

# Visual Comparison — Fiscal/Nfe (PR #1 #1183)

## Blueprint Cowork

`prototipo-ui/Oimpresso ERP Conunicação Visual. Ultimotopo/fiscal-page.jsx §9 FiscalNFePage` + `fiscal-page.css` + `fiscal-data.jsx` (R#1 KB-9.75 — design Cowork export tarball)

## Approval status (ADR 0107 / ADR 0114)

- ✅ Escopo design aprovado por Wagner 2026-05-20 (escolha "NF-e (página 2) — substitui UI atual" no kickoff)
- ✅ Implementação Inertia/React fiel 1:1 ao protótipo Cowork (port direto de selectors `.fx-*`)
- ⏳ Screenshot pós-merge biz=1 pra confirmar render real — `/mwart-override` registrado pra baseline ausente em Page nova

## Não-portado intencional neste PR (Non-Goals — Charter)

- ⌘K palette completa (PR #3)
- Mini-sparklines nos KPIs (Cockpit sub-página 1 — PR #2)
- Pulse animation em rejeitadas críticas (entra com KPIs cockpit)
- Cross-link Linkify V-*/OS-*/CNPJ completo (só V-* manual no drawer; Linkify global em PR #2)
- Ações mutação (Cancelar/Retransmitir/CC-e/Inutilizar — botões desabilitados, PR #4)

## 8 dimensões mwart-comparative

### 1. Layout grid

| Aspecto | Cowork | Inertia | Status |
|---|---|---|---|
| Container raiz | `.fx-page` padding 18px 24px | ✅ scoped CSS idêntico | OK |
| Hero header | flex row + crumb subline | ✅ `.fx-hero` | OK |
| Sub-nav horizontal | flex wrap chips + footer sticky | ✅ `FxShell` componente | OK |
| Body table | full width card border-radius 10px | ✅ `.fx-table` | OK |

### 2. Hierarquia tipográfica

| Aspecto | Cowork | Inertia | Status |
|---|---|---|---|
| Font família | IBM Plex Sans 13.5px corpo + Mono pra números | ✅ Google Fonts importado via AppShell | OK |
| Hero h1 | 20px font-weight 700 letter-spacing -0.015em | ✅ `.fx-hero-l h1` | OK |
| Número nota | Mono 13.5px bold + small modelo abaixo | ✅ `.fx-mono` em `<td>` | OK |
| SEFAZ pill code | Mono 11px font-weight 700 | ✅ `.fx-sefaz .code` | OK |

### 3. Densidade

| Aspecto | Cowork | Inertia | Status |
|---|---|---|---|
| Altura linha tabela | ~48px (9px padding + 12.5px font + 9px) | ✅ `.fx-table td { padding: 9px 12px }` | OK |
| Espaçamento entre chips filtro | 6px gap | ✅ `.fx-filters { gap: 6px }` | OK |
| Padding cards sub-nav | 4px gap entre chips | ✅ `.fx-subnav { gap: 4px; padding: 4px }` | OK |
| Drawer width desktop | 480px | ✅ `.fx-drawer { width: 480px }` | OK |

### 4. Iconografia

| Aspecto | Cowork | Inertia | Status |
|---|---|---|---|
| Set base | lucide-flavored (Search, FileText, RefreshCw, Shield, etc) | ✅ lucide-react importado | OK |
| Tamanho ícone botão | 12-13px | ✅ `size={12-13}` em buttons | OK |
| Ícone na chip filtro | size 11 inline | ✅ `<RefreshCw size={11}/>` na Janela 24h | OK |
| Ícone no drawer header | X size 16 (close button) | ✅ `<X size={16}/>` | OK |

### 5. Estados visuais

| Aspecto | Cowork | Inertia | Status |
|---|---|---|---|
| Linha cursor (J/K) | `.fx-row-focus` outline 2px solid fis + bg fis-soft | ✅ `.fx-row-focus` | OK |
| Chip filtro ativo | bg fis + color white | ✅ `.fx-chip.active` | OK |
| Botão hover | opacity .85 (não-disabled) | ✅ `.fx-btn:hover:not(:disabled)` | OK |
| Botão disabled | opacity .4 + cursor not-allowed | ✅ `.fx-btn:disabled` | OK |
| Hover tabela | bg fx-bg-2 | ✅ `.fx-table tbody tr:hover td` | OK |
| Empty state | card border-dashed + center text | ✅ `.fx-empty` | OK |

### 6. Atalhos teclado

| Aspecto | Cowork | Inertia | Status |
|---|---|---|---|
| 1-7 sub-páginas | `useEffect` listener com FX_PAGES.short | ✅ `FxShell` useEffect navega | OK |
| J/K navegação | cursor state + ArrowDown/ArrowUp | ✅ `Nfe.tsx` useEffect handler | OK |
| Enter abre drawer | `setOpened(dataRows[cursor])` | ✅ idem | OK |
| ESC fecha drawer | listener keydown ESC | ✅ `NotaDrawer` useEffect | OK |
| ⌘K placeholder | botão visível mas desabilitado neste PR | ✅ `disabled title="Em PR #3"` | OK |

### 7. Persistência / state

| Aspecto | Cowork | Inertia | Status |
|---|---|---|---|
| Filtros URL | mockou via window state | ✅ Inertia `router.visit(data)` querystring | melhor (URL-shareable) |
| Cursor state | local useState | ✅ idem | OK |
| Drawer open | local useState `opened` | ✅ idem | OK |
| Partial reload | mockou via re-render | ✅ Inertia `only:['rows','counts','filters']` + `<Deferred>` | melhor (server-side scope automático) |

### 8. Componentes compartilhados

| Aspecto | Cowork | Inertia | Status |
|---|---|---|---|
| Shell envelope | `FxShell` wrapper 7 sub-páginas | ✅ `_components/FxShell.tsx` reusável | OK |
| Drawer slide-in | `NotaDrawer` reusável (qualquer cstat) | ✅ `_components/NotaDrawer.tsx` | OK |
| Helpers prazo | `prazoCancel`, `prazoCCe`, `brl`, `truncKey`, `formatDoc` | ✅ `_lib/fiscal-helpers.ts` | OK |
| SEFAZ codes map | `SEFAZ_CODES` 100/110/220/539/691/778/999 | ✅ Controller `sefazCodes()` + prop pra frontend | OK |
| Mapa "Jana sugere" | `SEFAZ_ACTIONS` receita por cstat | ✅ `NotaDrawer.SEFAZ_ACTIONS` | OK |

## Histórico

- **2026-05-20** — Wagner aprovou escopo PR #1 = NF-e sub-página 2. Implementação fiel ao prototype Cowork (port direto). Screenshot baseline visual será criada em PR #2 quando Cockpit (sub-página 1) tiver dados reais agregados. Override `/mwart-override` registrado pra visual-regression CI.

## Referências

- [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) — Processo MWART canônico único caminho
- [ADR 0107](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md) — Emenda 0104 visual-comparison gate F3
- [ADR 0114](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md) — prototipo-ui Cowork loop formalizado
- Charter: `resources/js/Pages/Fiscal/Nfe.charter.md`
- RUNBOOK: `memory/requisitos/Fiscal/RUNBOOK-nfe.md`
- SPEC: `memory/requisitos/Fiscal/SPEC.md`

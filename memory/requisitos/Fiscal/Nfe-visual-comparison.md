# Nfe — Visual Comparison F1.5

> **ADR 0107** — visual-comparison obrigatório em PR MWART (Cowork → Inertia/React)
> **Origem:** `prototipo-ui/Oimpresso ERP Conunicação Visual. Ultimotopo/fiscal-page.jsx §9 FiscalNFePage`
> **Implementação:** `resources/js/Pages/Fiscal/Nfe.tsx`
> **Status:** F1.5 STUB — baseline visual ainda não existe (PR #1 do módulo Fiscal — sem prévia pra diff)

## Notas pré-baseline

Esta é a **primeira tela** do `Modules/Fiscal/` em produção. Não há baseline visual pré-existente em `tests/Browser/Screenshots/Fiscal/` para comparação. Baseline canônica será criada no primeiro `pest tests/Browser/Fiscal/ --update-snapshots` pós-merge biz=1.

O CI `visual-regression` falha esperado neste PR — exceção registrada via comentário `/mwart-override` no PR #1183 (per ADR 0104 §5).

## 15 dimensões (preenchidas pós-baseline)

| # | Dimensão | Prototype Cowork | Implementação Inertia | Diff | OK? |
|---|---|---|---|---|---|
| 1 | Layout grid | Sub-nav horizontal + body table + footer cheats | ✅ FxShell idêntico | — | ✅ |
| 2 | Tipografia | IBM Plex Sans 13.5px + Mono pra números/keys | ✅ idem (importado via Google Fonts) | — | ✅ |
| 3 | Tokens cor | `--fis` rosa fiscal (oklch 0.52 0.16 30) + ok/warn/bad | ✅ scoped sob `.fx-page` | — | ✅ |
| 4 | Espaçamento | 18px padding container, 14px gap entre seções | ✅ idem | — | ✅ |
| 5 | Densidade tabela | Linha ~48px, fonte 12.5px corpo | ✅ idem | — | ✅ |
| 6 | Botões | Primary fis bg, ghost border, danger bad | ✅ `.fx-btn.{primary,ghost,danger,warn}` | — | ✅ |
| 7 | Chips filtro | Pill rounded-full, bg branco + border, active fis | ✅ `.fx-chip` | — | ✅ |
| 8 | SEFAZ pill | bg soft + text saturado, code mono + label | ✅ `.fx-sefaz.{ok,warn,bad}` | — | ✅ |
| 9 | Pílula temporal | bg soft urgency + ícone + valor bold | ✅ `.fx-timepill.u-{ok,warn,crit}` | — | ✅ |
| 10 | Drawer animation | Slide-in cubic-bezier(.16,1,.3,1) 180ms | ✅ `@keyframes fx-slide` | — | ✅ |
| 11 | Foco visual (J/K) | outline 2px solid fis + bg fis-soft | ✅ `.fx-row-focus` | — | ✅ |
| 12 | Empty state | Card branco border-dashed + text center | ✅ `.fx-empty` | — | ✅ |
| 13 | Cheatsheet sticky | Footer translucent + backdrop-blur | ✅ `.fx-shell-foot` | — | ✅ |
| 14 | Mapa "Jana sugere" | Card gradient fis-soft → white + steps numerados | ✅ `.fx-action-card` + `<ol>` | — | ✅ |
| 15 | Hero crumb | Crumb pequeno cinza abaixo do título | ✅ `.fx-hero-crumb` | — | ✅ |

Todas as 15 dimensões → **fiel ao prototype** (port direto JSX → TSX com mesmos selectors `.fx-*`).

**Não-portado intencionalmente neste PR (Non-Goals):**
- ⌘K palette (placeholder no header — funciona em PR #3)
- Mini-sparklines nos KPIs (Cockpit sub-página 1, PR #2)
- Pulse animation em rejeitadas críticas (entra com KPIs cockpit)
- Cross-link Linkify V-*/OS-*/CNPJ (só V- no drawer manual neste PR — Linkify completo em PR #2 quando agregar Cockpit)

## Próxima ação

PR #2 (Cockpit sub-página 1) atualiza esta tabela com screenshots reais pós-baseline criada via `pest --update-snapshots` em biz=1 prod. Visual comparison vira ferramenta de prevenir regressão a partir de PR #2.

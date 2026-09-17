# HANDOFF — espelho → repo, zero-touch

> **Para quem:** Claude Code (ou qualquer agente) trabalhando em `wagnerra23/oimpresso.com`, e para quem edita este espelho.
> **Contrato:** nada aqui exige que um humano copie, cole, traduza ou "lembre" de algo. Tudo que o Code precisa está em arquivo, com path real e comando real. Único ato humano no fim do caminho: **[W] clica o merge** (ADR 0283 — auto-merge BLOQUEADO até os 5 controles existirem).
> **Direção de autoridade:** `git` é SSOT (ADR 0239). Este projeto claude.ai/design é **espelho derivado / vitrine** — **NÃO é fonte** (ADR 0315 Eixo A, ADR 0299). Leitura daqui é livre; escrita daqui **pra dentro** do git é opt-in explícito do Wagner, nunca automática.

---

## 0. Ordem de leitura (5 min, zero adivinhação)

| # | Abra | Por quê |
|---|---|---|
| 1 | este arquivo | contrato + mapa componente→arquivo + DoD por máquina |
| 2 | [`README.md`](README.md) | fundações visuais, voz PT-BR, iconografia, regras duras |
| 3 | `colors_and_type.css` + `cockpit_domains.css` | tokens espelhados **verbatim** do git (`resources/css/tokens/_generated-*.css`) |
| 4 | `templates/<slug>/<Slug>.dc.html` | a tela desenhada (a fonte visual do handoff) |
| 5 | no repo: `CLAUDE.md` → `memory/requisitos/_DesignSystem/PRE-MERGE-UI.md` → PT aplicável | canon que manda no merge |

---

## 1. Regras duras (violar = regressão, não estilo)

1. **Entrega repo-nativa.** Diff na língua do repo: **Tailwind 4 + tokens existentes**. Proibido mandar `.om-*`, CSS cru do espelho, `#hex`/`oklch()` literal em arquivo de módulo (ADR 0283 R1 · PRE-MERGE-UI AP1).
2. **Leu o `main` antes de afirmar.** Toda afirmação sobre o código cita **arquivo + linha**. "Existe / não existe / diverge" é auditado, não lembrado.
3. **Canal é o repo, não o clipboard.** Bloco de handoff em `prototipo-ui/COWORK_NOTES.md` (ADR 0283 R2). Sem URL efêmera como canal.
4. **Sidebar é PRETA (dark-fixo) nos dois modos** — DEFINITIVO (UI-0023; supersede UI-0019/0014/0009, que diziam "light" e estavam erradas). Fonte: bloco `Sidebar — DARK FIXO` em `resources/css/cockpit.css`.
5. **Primary é roxo** `oklch(0.55 0.15 295)`. O `blue` do shadcn-slate legado em `inertia.css` é **superseded** (DS v6 · ADR 0190/0300).
6. **PT-BR** em label, copy, erro, commit, PR (AP8). Código em inglês; domínio em PT.
7. **Componente vem do shared/ui** — não reinventa `DataTable`, `PageHeader`, `Drawer`, `EmptyState`, `StatusBadge`, `BulkActionBar` (AP2). Ver mapa §3.
8. **`localStorage` prefixado** `oimpresso.<modulo>.*` / `oimpresso.cockpit.*` (AP3 · ADR 0093).
9. **Ícone só `lucide-react`** (AP4). **Sem emoji em UI de produto** (AP6) — emoji só no `FeatureGrid` de marketing.
10. **Sem gradiente decorativo 135deg** (AP5). **Status badge = dot + texto colorido + tinta ≤10%**, nunca `bg-fill` sólido/pastel (AP7).
11. **Um `<main>` por documento** (AP9) e **chain de overflow respeitada**: nó `flex-1` em coluna precisa de `h-full` **ou** pai `flex flex-col min-h-0` (AP10).
12. **Multi-tenant Tier 0 IRREVOGÁVEL** — query com `business_id` global scope; job recebe `$businessId` no constructor (ADR 0093).
13. **Contrato mora no elemento, não num wrapper.** `data-contract`, `aria-label` e recuo lateral vão na própria `<nav>`/`<table>`/`<section>` — `<div>` só pra pendurar atributo é regressão: o atributo deixa de descrever o elemento que ele nomeia e o nó extra entra na chain de overflow (AP10).
14. **Fonte de design ≠ Figma** (ADR 0299). Fonte = `prototipo-ui/` + DS em git + `*.charter.md`. Este espelho entra como **vitrine derivada**.
15. **Regressão é inaceitável.** Score baixou (Module Grade v4 / KB-9.75) → **pare, reporte, não sobrescreva baseline**.

---

## 2. Telas deste espelho → alvo no `main`

Pares marcados “confirmar” se resolvem por máquina: `node scripts/qa/screen-coverage-map.mjs --screen <Tela>`.

| Template daqui | Alvo provável no repo | Estado |
|---|---|---|
| `templates/clientes-crm/ClientesCrm.dc.html` | `resources/js/Pages/Cliente/Index.tsx` (+ `Index.charter.md`, `_drawer/`, `_show/`) | existe — diff, não criar |
| `templates/financeiro/Financeiro.dc.html` | `resources/js/Pages/Financeiro/Unificado/` (+ `ProvaViva.tsx`) | existe — diff; ler `prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md` **antes** |
| `templates/oficina-auto/OficinaAuto.dc.html` | `resources/js/Pages/OficinaAuto/ServiceOrders/` + `Vehicles/` | existe — diff |
| `templates/pt-07-os-detail/Pt07OsDetail.dc.html` | detalhe de OS em `OficinaAuto/ServiceOrders/` | existe — PT-07 aplicado |
| `templates/pt-01-lista/Pt01Lista.dc.html` | padrão, não tela: aplicado em `Cliente/Index`, `Produto/Index`, `Sells/Index` | canon PT-01 |
| `templates/pt-05-dashboard/Pt05Dashboard.dc.html` | `Pages/Home/Index.tsx`, `Pages/governance/Dashboard.tsx` | canon PT-05 |
| `templates/atendimento/Atendimento.dc.html` | **sem par 1:1 no `main`** (adjacentes: `Pages/Jana/Chat.tsx`, `Pages/Whatsapp/_components/`) | tela nova → exige `*.charter.md` novo antes do `.tsx` |

---

## 3. Mapa componente DS → arquivo do repo

`reusar` = já existe, use como está · `estender` = existe, falta variante/prop · `criar` = não existe no `main` (nasce com charter/ADR se virar canon).

| DS (espelho) | Arquivo no `main` | Ação |
|---|---|---|
| `Button` | `resources/js/Components/ui/button.tsx` | reusar |
| `Input` | `ui/input.tsx` + `ui/label.tsx` + `ui/field-state.tsx` | reusar |
| `Select` | `ui/select.tsx` + `ui/SafeSelectItem.tsx` | reusar |
| `Switch` · `Checkbox` · `RadioGroup` | `ui/switch.tsx` · `ui/checkbox.tsx` · `ui/radio-group.tsx` | reusar |
| `Modal` | `ui/dialog.tsx` (destrutivo: `ui/alert-dialog.tsx`) | reusar |
| `Drawer` (+`DrawerSection`) | `ui/sheet.tsx` | estender (badge no header + footer sticky) |
| `DropdownMenu` · `Tooltip` · `Alert` · `Skeleton` · `Command` | `ui/dropdown-menu.tsx` · `ui/tooltip.tsx` · `ui/alert.tsx` · `ui/skeleton.tsx` · `ui/command.tsx` | reusar |
| `Avatar` | `ui/avatar.tsx` | estender (hash → `--av-c1..c8`, sem foto) |
| `TagChip` | `ui/badge.tsx` | estender (paleta semântica lowercase) |
| `StatusBadge` | `resources/js/Components/shared/StatusBadge.tsx` | estender (`kind` novos: `frescor`, `tipo`, `sla`, `atendimento`) |
| `KpiCard` | `shared/KpiCard.tsx` + `shared/KpiGrid.tsx` | reusar |
| `KpiFilterCard` | `shared/KpiCard.tsx` | estender (`selected` + `onClick`) |
| `PageHeader` | **`Components/PageHeader/PageHeader.tsx`** (canon v3.8) — **NÃO** `shared/PageHeader.tsx`, que está `@deprecated`/CONGELADO e tem ratchet `pageheader-gate` no CI | `leading` **reusar** (já existe, opt-in 2026-08-08) · `context` e `freshness` **criar** (ver `HANDOFF-2026-08-31-tabbar-pageheader.md`) |
| `TabBar` | `shared/PageHeaderTabs.tsx` (ghosts) · `shared/SubNav.tsx` · `shared/PageHeaderModuleNav.tsx` | estender (**o contrato vai no elemento**: `...rest` na raiz · `aria-label` do tablist configurável · `pad`/`size`/`off`/`inset`. `icon` e `count` **já existem** como `ghost.icon`/`ghost.badge`. **Não** criar `ModuleTopNav`) |
| `DataTable` | `shared/DataTable.tsx` | reusar |
| `DataTablePro` | `shared/DataTable.tsx` + `@tanstack/react-table` | estender (sticky/resize/densidade) |
| `BulkBar` | `shared/BulkActionBar.tsx` | reusar |
| `EmptyState` | `shared/EmptyState.tsx` | estender (variants `no-perm`/`offline`/`filtered`/`error`) |
| `PlacaVeiculo` | `shared/MercosulPlate.tsx` | estender (padrão antigo + categoria) |
| `FilterChip` | `ui/badge.tsx` + `shared/PageFilters.tsx` | estender |
| `AppSidebar` | `resources/js/Layouts/AppShellV2.tsx` | reusar (sidebar vive no shell · **preta**) |
| `Toast` | pacote `sonner` (já em `dependencies`) | reusar via `sonner`, sem componente novo |
| `Progress` | — | **criar** `ui/progress.tsx` (bar + ring SVG) |
| `DatePicker` | `ui/popover.tsx` + `date-fns` | **criar** (PT-BR `dd/mm/aaaa`) |
| `PeriodBar` | `ui/segmented.tsx` + `DatePicker` | **criar** |
| `Breadcrumb` · `Pagination` · `FsmStepper` · `Chart` | — | **criar** (Chart = SVG inline; não há lib de chart nas deps) |
| `TaskCard` · `BoardColumn` | `shared/TaskBadges.tsx` + `@dnd-kit/*` | **criar** (ADR 0070) |
| `Logo` | `assets/brand/logo-full.svg`, `logo-mark.svg` | **criar** wrapper; ativos já existem |
| `ProofFrame` · `Dimension` · `ProofStrip` · `RegistrationMark` | — | **criar** (print-craft; só se a tela pedir) |

> **Errata de paths** (o README antigo apontava caminhos que não existem mais no `main`): shell é `Layouts/AppShellV2.tsx` (não `AppShell.tsx`); não existe `Components/shared/ponto/`; `ModuleTopNav.tsx` foi substituído por `PageHeaderTabs`/`SubNav`/`PageHeaderModuleNav`; `shared/` tem 16 arquivos — confira com `github_get_tree` antes de citar.

---

## 4. Tokens — como o espelho e o git ficam idênticos (VALOR:0)

Os tokens **nascem** em `resources/css/tokens/*.tokens.json` (DTCG) → Style Dictionary → `_generated-*.css`. O espelho recebe cópia montada, nunca transcrita à mão.

```bash
npm run tokens:build                      # DTCG → CSS
npm run tokens:version:check              # versão do token set
node scripts/design-sync/ds-push.mjs      # monta colors_and_type.css + cockpit_domains.css e VALIDA (sai !=0 se VALOR>0)
node scripts/design-sync/ds-push.mjs --write   # + refresca o mirror-snapshot do sentinela
node scripts/governance/ds-mirror-drift.mjs    # sentinela de divergência espelho↔git
```

`ds-push` **não** faz o upload (o `finalize_plan`/`write_files` do DesignSync exige login claude.ai interativo) — ele imprime a chamada exata de 2 linhas. Esse é o único passo interativo do loop, e é **git → espelho**. O inverso (espelho → git) exige opt-in explícito do Wagner (ADR 0315).

---

## 5. DoD por máquina (rode, não confie na memória)

```bash
# fundações / DS
npm run ds:canon:check          # cor canon (roxo 295) não regrediu
npm run foundation:check
npm run conformance:check
node prototipo-ui/ds-guard.mjs <arquivos tocados>     # §8 do PROCESSO_MEMORIA_CC
node prototipo-ui/integrity-check.mjs                 # §15, ao formalizar
# tela / padrão
npm run pt:conformance:check
npm run design:coverage:check
npm run contrato:check && npm run contrato:preflight
# qualidade
npm run typecheck && npm run lint:baseline:check && npm run stylelint:baseline:check
npm run a11y:check && npm run dominio:check && npm run no-mock:check
npm run test
npm run visreg:pixel            # baseline visual (CT 100)
```

Checklist humano de 3 min: `memory/requisitos/_DesignSystem/PRE-MERGE-UI.md` (camada que você tocou + seção Universal). **Item que falha → comunica o Wagner, não corrige silenciosamente.**

---

## 6. Bloco de handoff a colar em `prototipo-ui/COWORK_NOTES.md`

```md
## HANDOFF <data> · <tela> · espelho DS v6
- fonte visual: <projeto claude.ai/design>/templates/<slug>/<Slug>.dc.html
- tokens: colors_and_type.css + cockpit_domains.css (VALOR:0 vs resources/css/tokens em <data>)
- alvo: resources/js/Pages/<Mod>/<Tela>.tsx  (charter: <Tela>.charter.md — status: live)
- reusar: <lista do §3>   estender: <lista>   criar: <lista + ADR necessária?>
- auditoria do main: <arquivo:linha> existe · <arquivo:linha> diverge · <X> não existe
- gates rodados: ds-guard ✓ · conformance ✓ · pt:conformance ✓ · a11y ✓ · visreg ✓
- pendências pra [W]: <decisão de produto / token novo / componente novo>
```

**Pronto quando:** todo item do §5 verde, bloco acima commitado, PR ≤300 linhas, 1 intent, conventional commit com `Refs:`. **Merge é ato do [W].**

---

## 7. O que este espelho NÃO decide

Token novo · componente novo no canon · produto · merge. Isso é soberania [W] Tier 0. O espelho **propõe** (com ADR proposta quando for canon), nunca numera nem canoniza sozinho.

# Sidebar — rail mode + cores por grupo (visual-comparison)

**Tela:** Layout global `AppShellV2` (sidebar global do ERP)
**Fonte canônica visual:** `prototipo-ui/_cowork-export-2026-05-15/sidebar.jsx` + `styles.css` (linhas 5039-5350) + `data.jsx` (`GROUP_META`)
**Gate F1.5 MWART:** [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) + [ADR 0114](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md)
**Aprovação screenshot Wagner:** AskUserQuestion 2026-05-16 — "Escopo completo (recomendado)"
**Skip design-critique:** Wagner aprovou (protótipo veio do loop Cowork formal, já sancionado)

## Capacidades novas a portar

| # | Capacidade | Spec protótipo | Decisão portabilidade |
|---|---|---|---|
| 1 | Modo `rail` (~56px ícone-only) | `sidebar.jsx:407-440` prop `mode` + variante `SidebarMenuRail` com tooltip `data-tip` + flyout posicionado | Adicionar prop `mode` em `<SidebarMenu>` + novo componente `<SidebarMenuRail>` |
| 2 | Alça `.sb-collapse-handle` borda direita | `sidebar.jsx:427-437` botão 20×36px, chevron `‹`/`›`, `opacity 0→1` em `.sb:hover`, atalho ⌘\\ | Adicionar dentro do `<aside className="sb">` em AppShellV2 |
| 3 | Alça flutuante `.sb-reopen-handle` (sidebar oculta) | `sidebar.jsx:443-455` botão fixed left:0, expande no hover | Renderizar condicionalmente quando `mode === 'hidden'` (Fase 2 — agora só rail/expanded) |
| 4 | Cor por grupo (hue OKLCH) | `data.jsx:334` `GROUP_META` 10 hues; `sidebar.jsx:170-200` aplica `oklch(0.62 0.13 var(--gh))` no dot + `oklch(0.46 0.10 var(--gh))` no label | Adicionar `SIDEBAR_GROUP_HUE: Record<groupKey, number>` em shared.ts + CSS var `--gh` por grupo |
| 5 | Persist `mode` em LS | `localStorage.oimpresso.menu.expanded` (já existe) + novo `oimpresso.sb.mode` | Adicionar `LS.SB_MODE` em shared.ts |

## Paleta hue por grupo (mapeamento Cowork → SIDEBAR_GROUPS atual)

Cowork `GROUP_META` tem 10 grupos com hue. AppShellV2 hoje tem 11 grupos. Mapeamento canônico:

| AppShellV2 `groupKey` | Label atual | Hue (OKLCH) | Cor visual |
|---|---|---|---|
| `office` | ACESSOS RÁPIDOS | 60 | amarelo-ouro |
| `oficina` | OFICINA AUTO | 350 | rosa/vermelho (vertical) |
| `fin` | FINANCEIRO | 145 | verde |
| `estoque` | ESTOQUE | 30 | laranja (Produção/estoque) |
| `fiscal` | FISCAL | 200 | ciano (Integrações/fiscal SEFAZ) |
| `rh` | RH | 295 | roxo (Pessoas) |
| `conhecimento` | CONHECIMENTO | 80 | verde-oliva (Outros/KB) |
| `rel` | RELATÓRIOS | 240 | azul-escuro (Gestão) |
| `ia` | IA & PRODUTIVIDADE | 220 | azul (Comercial/IA pareada) |
| `governanca` | GOVERNANÇA | 270 | violeta (Config./Admin) |
| `plataforma` | PLATAFORMA | 200 | ciano (Integrações) |
| `mais` | MAIS | — | sem cor (fallback neutro) |

## Trade-offs decididos

- **Modo `hidden` (sidebar 0px) fica fora desta entrega.** Protótipo prevê via `SidebarReopenHandle`, mas não é prioridade — adicionar em PR seguinte se Wagner pedir.
- **Atalho ⌘\\ adicionado** mas conflito com `Cmd+K` da Command Palette inexistente (paleta usa `K`, alça usa `\\`).
- **Tooltip `data-tip` puro CSS** (sem lib) — segue protótipo Cowork.
- **CSS vars `--gh` injetadas inline por grupo** via `style={{ ['--gh']: hue }}` no `<div className="sb-group">` (mesmo padrão protótipo).
- **Persist por grupo (`oimpresso.cockpit.group.${key}.expanded`) preservado** — só o `mode` é novo LS key.

## Anti-padrões evitados

- ❌ Não duplicar `SIDEBAR_GROUPS` em outro arquivo — hue vira `Record<groupKey, number>` separado em shared.ts, paralelo ao array existente em Sidebar.tsx.
- ❌ Não mudar API pública de `<AppShellV2>` — nova prop `defaultSidebarMode?: 'expanded' | 'rail'` é opcional, default `expanded`.
- ❌ Não trocar `lucide-react` por SVG inline — segue padrão Cockpit V2 atual.
- ❌ Não rebatizar grupos pra refletir labels Cowork (`Operação`, `Comercial` etc) — `SIDEBAR_GROUPS` é canônico do shell real (US-WA-082+083); cores só ressaltam visualmente.

## Estimativa

| Arquivo | Linhas adicionadas | Tipo |
|---|---|---|
| `shared.ts` | +30 | LS key + GROUP_HUE map |
| `Sidebar.tsx` | +130 | SidebarMenuRail + cores no SidebarGroup |
| `AppShellV2.tsx` | +60 | mode state + alça + atalho ⌘\\ |
| `cockpit.css` | +220 | rail mode + cores hue |
| `sidebar-rail-mode-visual-comparison.md` | +80 | este doc |
| **TOTAL** | **~520** | Ultrapassa o limite ≤300 linhas (ADR 0094) mas é 1 intent única (sidebar canônico portado), justificado em PR description. |

## Pós-merge

- Smoke biz=1 manual: `/dashboard` → click alça colapsa → click ícone grupo → flyout abre → click sub-item navega.
- LS persistido entre navegações (refresh F5 mantém modo).
- Acessibilidade: alça tem `aria-label`, atalho ⌘\\ no `title`.
- Cores: Larissa (cliente piloto monitor 1280px) — confirmar contraste OKLCH 0.46/0.10/hue passa AA contra `var(--sb-bg)` (light/dark).

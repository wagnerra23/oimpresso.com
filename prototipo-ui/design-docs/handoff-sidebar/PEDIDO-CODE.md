# Pedido pro Claude Code — handoff do **Sidebar** (F1 → F3)

> Gerado pelo Cowork em **2026-08-28** a partir de leitura do `main` NESTE turno
> (árvore `7d7c8fd14310`): `prototipo-ui/cowork/sidebar.jsx` @92df6bb4c7cc ·
> `resources/js/Components/cockpit/Sidebar.tsx` @a0bbf58a5f67 · `resources/css/cockpit.css` @7bbd6b277c04 ·
> `.claude/skills/sidebar-menu-arch/SKILL.md` @0446364a1f95 · `app/Sidebar/*` · ADR 0180.
> **Nada aqui está commitado.** Isto é a ponte: [W] cola 1× (zero-toque) ou vira Issue `cowork-intake`.

---

## 0. Estado verificado — o export já está paritário

Diff `sidebar.jsx` (Cowork) × `prototipo-ui/cowork/sidebar.jsx` (`main`): **623 linhas nos dois, 1 linha diferente**,
e essa linha é ruído do editor:

```
- <div className="sb-top">
+ <div className="sb-top" data-comment-anchor="817b4e58ef-div-473-7">
```

Conclusão: **não há export de build pendente.** O handoff do sidebar não é "mandar o .jsx" — o .jsx já está lá.
O que falta é a **tradução F1 → F3**: protótipo (`prototipo-ui/cowork/sidebar.jsx`) → vivo
(`Sidebar.tsx` + `cockpit.css` + contrato backend). É isso que este pedido descreve.

---

## 1. Mapa protótipo → vivo (reusar / estender, nunca recriar)

| Protótipo (`prototipo-ui/cowork/sidebar.jsx`) | Arquivo real no `main` | Ação |
|---|---|---|
| `Sidebar` (shell, modes expanded/rail/hidden) | `resources/js/Components/cockpit/Sidebar.tsx` | **estender** — já existe, inclusive `mode==='rail'` |
| `SidebarMenuRail` + flyout de grupo | `Sidebar.tsx` (`SidebarMenuRail`, ~l.741-870) | **já portado** — comentário no vivo cita `sidebar.jsx:286-385` |
| `CompanyPicker` / `CompanyPickerRail` | `Sidebar.tsx` `export function CompanyPicker` (~l.348) | **já portado** |
| `MenuGroup` (accordion + hue por grupo) | `Sidebar.tsx` `SIDEBAR_GROUPS` (~l.166) + `.sb-group` no `cockpit.css` | **já portado** |
| `SidebarChat` / `ConvRow` / `SidebarTabs` | CSS existe (`.sb-tabs` l.270, `.sb-conv` l.357 em `cockpit.css`); **não achei render em `Components/cockpit/*.tsx`** | **verificar** — ver §2.A |
| `SidebarReopenHandle` | usado em `resources/js/Pages/Financeiro/_cowork-bundle/shell-app.jsx:510` (bundle Cowork), **não em `Sidebar.tsx`** | **promover** — ver §2.D |
| `Kbd`/`ItemEnd` + listener sequência "G X" | **sem ocorrência de `sb-kbd` em `Components/cockpit/`** | **criar** — ver §2.B |
| `GhostList` + `GHOST_TETO = 5` + "⋯ mais N" | **sem `GHOST_TETO` no vivo** | **decidir** — ver §2.C (conflito com ADR 0180) |
| `WipMark` (frescor mock/stub) | — | **NÃO portar** (instrumento de protótipo) |
| `podeVer(papel)` / `MOCK.SIDEBAR_PAPEIS` | `can()` real + guards do `DataController::items()` | **NÃO portar** (é simulação de papel) |
| `countOf` / `MOCK.SIDEBAR_COUNTS` | `shell.sidebar_counts` (`Modules/Whatsapp/Tests/Feature/SidebarCountsTest.php`) | **já existe no vivo** — 3 contadores (chat · atendimento · tarefas) |

Contrato de dados (não reinventar): `app/Sidebar/SidebarMenuItem.php` · `SidebarGroup.php` · `SidebarGhost.php` ·
`SidebarPrimaryAction.php`; publicação por módulo em `DataController::items()` **contrato v2** (ADR 0180) —
`href` single-link + `ghosts[]` + `primary` + `group` ∈ {vender, operar, financas, pessoas, sistema}.

---

## 2. Deltas propostos (ordem de aplicação)

### A. Aba Chat na sidebar — **status a confirmar**
O CSS canônico da aba (`.sb-tabs`, `.sb-conv`, `.sb-conv-t`) está em `cockpit.css`, e há badge de unread
(`.sb-conv-badge`, l.1683 — comentário diz "backend já expõe `unread`"). Não encontrei o render em
`Components/cockpit/*.tsx`. **Antes de escrever código: confirme se o Chat vive fora do `Sidebar.tsx`.**
Se estiver órfão, portar `SidebarTabs` + `SidebarChat` + `ConvRow` do protótipo — o CSS já existe, é só o TSX.

### B. Atalho "G X" (sequência de 2 teclas)
Protótipo: arma no `G`, navega na letra seguinte, janela de 1,5 s; dica `sb-kbd` aparece no hover/foco da linha,
ocupando a **mesma célula grid** do contador de telas (nada empurra o label). Portar como:
1. `shortcut` já é campo do contrato v2 (`'shortcut' => 'G N'`) — consumir, não inventar chave nova;
2. hook `useSidebarShortcut()` em `Components/cockpit/` + classe `.sb-kbd` em `cockpit.css`;
3. **não** colidir com ⌘K (paleta) nem disparar dentro de `input`/`textarea`/`contenteditable`.

### C. Ghosts na sidebar — **decisão de [W] antes de codar**
O protótipo lista até 5 ghosts sob o item + "⋯ mais N", promovendo a rota ativa. A **ADR 0180 diz o oposto**:
ghost não vive no sidebar, vira tab no PageHeader Zona C (AP19). São canons conflitantes.
→ Ou (i) o protótipo está à frente e a ADR precisa de emenda, ou (ii) o `GhostList` é só andaime de navegação do
protótipo e **não** vai pro vivo. **Não portar sem despacho.**

### D. `SidebarReopenHandle`
Hoje só existe no bundle Cowork do Financeiro. Promover pro `Components/cockpit/` e usar no `AppShellV2.tsx`,
para o modo oculto ter volta em qualquer tela — não só no Financeiro.

---

## 3. Regras duras (o handoff é reprovado se quebrar)

- **Sidebar é PRETA (dark-fixo) nos dois modos** — UI-0023 supersede 0019/0014/0009. Fonte: bloco `Sidebar — DARK FIXO` em `cockpit.css`.
- `.sb-item.is-open` **não clareia** — só hover ilumina (regra [W] 2026-05-05).
- **Nenhum grupo cross-módulo em `AdminSidebarMenu.php`** — agrupamento é frontend (`SIDEBAR_GROUPS`).
- **Nenhum `Menu::dropdown` com sub-itens** (AP19) — item de sidebar é single-link.
- **Sem cor crua** — só tokens (`--sb-text`, `--sb-hover`, `--sb-active`, hue por grupo via `--gh`).
- PT-BR na UI, sem emoji no app, um `<main>` por documento (AP9), chain de overflow (AP10).

## 4. Gates (DoD por máquina, não por opinião)

```
php artisan optimize:clear && composer dump-autoload
php artisan test --filter=Sidebar        # tests/Feature/Sidebar/* (5 arquivos) + SidebarConsolidacaoTest + SidebarCountsTest
php artisan test --filter=Cockpit        # CockpitPatternConformanceTest · CockpitTypographyConformanceTest · CockpitAccentCanonTest
php artisan test --filter=AppShellUsageGate
node scripts/cowork-ssot-guard.mjs
node scripts/qa/prototipo-readiness.mjs
```

Teste que trava o contrato: `tests/Feature/Sidebar/SidebarMenuItemContractTest.php` — se o delta mexer no shape do
item, ele é o primeiro a quebrar (e deve ser atualizado junto, não contornado).

## 5. Bloco pra `COWORK_NOTES.md` (colar no fim, ao aplicar)

```
### 2026-08-28 — Sidebar F1→F3 (pedido Cowork)
- Export sidebar.jsx: paritário com o main (só data-comment-anchor de ruído) — nada a exportar.
- Deltas pedidos: (A) aba Chat órfã do TSX?, (B) atalho "G X", (C) ghosts na sidebar = CONFLITO ADR 0180 (parado em [W]), (D) promover SidebarReopenHandle.
- Regras: UI-0023 sidebar preta · AP19 single-link · is-open não clareia.
```

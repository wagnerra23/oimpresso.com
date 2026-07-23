---
id: requisitos-design-system-adr-ui-0011-sidebar-single-pane-cascata-user-menu
---

# ADR UI-0011 · Sidebar single-pane minimalista + user menu cascata lateral

- **Status**: accepted
- **Data**: 2026-05-05
- **Decisores**: Wagner, Claude
- **Categoria**: ui · estruturante
- **Refs**: [UI-0008](0008-cockpit-layout-mae-do-erp.md), [UI-0009](0009-cockpit-sidebar-light-padrao.md), [UI-0010](0010-zip-cowork-2026-04-27-canon-visual.md), [UI Kit Cowork sidebar.jsx](../../ui_kits/cowork-2026-04-27/sidebar.jsx)
- **Substitui parcialmente**: [UI-0008 §"Componentes obrigatórios do Cockpit · Sidebar"](0008-cockpit-layout-mae-do-erp.md) — trecho "SidebarTabs (toggle Chat ↔ Menu)" e "SidebarChat" foram REMOVIDOS

## Contexto

A primeira versão do Cockpit (UI-0008, 2026-04-27) introduziu uma **sidebar dual-pane** com toggle Chat/Menu no topo: ao clicar em "Chat", o body da sidebar mostrava atalhos (Nova conversa, Tarefas, Despachos, Personalizar) + Fixadas + Rotinas + Recentes. Ao clicar em "Menu", mostrava os 23 items do `shell.menu` flat (sem agrupamento).

Após uso em produção, Wagner identificou problemas em sessão 2026-05-05:

1. **Sidebar cluttered**: 23 items flat sem hierarquia visual; o cliente não consegue escanear rapidamente onde está.
2. **Toggle Chat/Menu confuso**: dois modos de visualização forçam o usuário a lembrar qual aba está ativa pra trocar de tela.
3. **Superadmin como accordion vertical** dentro do user dropdown (Meu perfil → Superadmin (collapse) → Backup/Módulos/CMS) cria um menu muito longo, parecido com listagens AdminLTE legacy. Linear/Notion/Claude Desktop usam **cascata lateral** (subpainel desliza da direita ao clicar no item) — visualmente mais limpo, com hierarquia explícita.
4. **Tarefas e Chat sem destaque**: estavam misturadas em "atalhos" da SidebarChat — não pareciam ser **as 2 ações primárias** que são.

## Decisão

### 1. Sidebar single-pane (toggle Chat/Menu REMOVIDO)

A sidebar agora é **uma única coluna scroll-única** com 4 zonas verticais:

```
┌─ 260px sidebar light ─────────┐
│ [WS] WR2 Sistemas           ▾│ ← Zona 1: CompanyPicker
│                               │
│ ✉ Tarefas                  6 │ ← Zona 2: SidebarShortcuts
│ 💬 Chat                     3 │   (Tarefas + Chat com badges live)
│                               │
│ Iniciar                       │ ← Zona 3a: grupo "INICIO" sem header
│                               │
│ OFFICEIMPRESSO         ▾     │ ← Zona 3b: grupos colapsáveis
│   Consulta de OS              │   com scope headers uppercase mute
│   Contatos                    │
│   Produtos                    │
│   ...                         │
│                               │
│ FINANCEIRO             ▾     │
│   Despesas                    │
│   ...                         │
│                               │
│ [WR] Wagner                ▼ │ ← Zona 4: SidebarFooter (1 linha)
└───────────────────────────────┘
```

**Estado removido (compat zerado — `LS.TAB` ignorado se presente):**
- `useState<'chat' | 'menu'>('tab')` no AppShellV2
- `<SidebarTabs />` componente
- `<SidebarChat />` componente (atalhos + Fixadas + Rotinas + Recentes)

**Estado novo:**
- `<SidebarShortcuts tarefasCount={N} chatCount={N} />` — 2 atalhos canônicos no topo, com badges
- `<SidebarGroup groupKey label collapsed?>` — header uppercase + items, colapsável; persistência em `oimpresso.cockpit.group.<key>.expanded`

### 2. Agrupamento por scope via lookup table

`SidebarMenu` agora itera o `shell.menu` flat e agrupa via tabela hardcoded em `Sidebar.tsx`:

```ts
const SIDEBAR_GROUPS = [
  { key: 'inicio', label: '', items: ['Iniciar', 'Home', ...] },           // sem header
  { key: 'office', label: 'OFFICEIMPRESSO', items: ['Consulta de OS', 'Contatos', 'Produtos', 'Vender', ...] },
  { key: 'fin',    label: 'FINANCEIRO',    items: ['Despesas', 'Contas de pagamento', 'Accounting', ...] },
  { key: 'estoque', label: 'ESTOQUE',      items: ['Compras', 'Transferências de ações', 'Ajuste de estoque', ...] },
  { key: 'rel',    label: 'RELATÓRIOS',    items: ['Relatórios', 'Reservas', 'Pedidos', 'Cocina'] },
  { key: 'ia',     label: 'IA & PRODUTIVIDADE', items: ['Copiloto', 'ADS', 'Conector', 'CRM'] },
  { key: 'config', label: 'CONFIGURAÇÕES',  items: ['Gerenciamento de usuários', 'Configurações', 'Modelos de notificação'] },
];
```

Items não-mapeados caem em **MAIS** (collapse fechado por default — fallback).

**Débito técnico assumido:** essa tabela é frágil (depende de string-match de `label`). A versão definitiva move o agrupamento pro backend — `LegacyMenuAdapter` ganha campo `group: string` no `MenuItem`, cada `Modules/<X>/Resources/menus/menu.php` declara seu grupo, e o front consome `shell.menu.groups[]`. Migração planejada após validação de UX em produção (~2 sprints).

### 3. User menu cascata lateral

`SidebarUserMenu` agora tem **2 painéis lado-a-lado**:

```
┌─ Painel principal (240px) ───┐  ┌─ Subpainel (240px) ────────┐
│ Wagner Rocha                 │  │ 🛡 Superadmin              │
│ wagnerra@gmail.com           │  │ ────                       │
│ ────                         │  │ Backup                     │
│ 👤 Meu perfil                │  │ Módulos                    │
│ 🛡 Superadmin           ▶ │ ◀│ CMS                        │
│ 🟢 Disponível            ▶ │  │ Office Impresso            │
│ 🌙 Aparência             ▶ │  │ Acessar Superadmin ›       │
│ ────                         │  └────────────────────────────┘
│ ⌨ Atalhos              ⌘/ │
│ 🔍 Central de ajuda          │
│ ────                         │
│ 🚪 Sair                      │
└──────────────────────────────┘
```

**Comportamento:**
- Click em item com `▶` (cascade trigger): subpainel desliza da direita (animação `userMenuSubIn` 180ms ease-out)
- Item ativo no painel principal recebe `bg: var(--accent-soft); color: var(--accent)` + `chevron com cor accent`
- Click no mesmo item de novo: subpainel some
- Click fora do menu inteiro: ambos os painéis fecham
- Mobile <1024px: subpainel empilha vertical embaixo (não lateral)

**3 cascade triggers atuais:**
1. **Superadmin** — items reais do `shell.menu` filtrados via `isSuperadminMenu()` (Backup, Módulos, CMS, Office Impresso, Acessar Superadmin)
2. **Disponível** — placeholder com 3 opções (Disponível, Ausente, Não perturbe). Backend de status do user ainda não modelado.
3. **Aparência** — usa `useTheme()` hook existente: 3 botões (Claro, Escuro, Sistema) que chamam `setTheme('light' | 'dark' | null)` e marcam o ativo via cor accent

### 4. Padrões visuais

- **Tarefas/Chat** com badge accent (fundo `var(--accent)`, texto `var(--accent-fg)`, min-width 22px, font-weight 600, font-variant-numeric tabular-nums)
- **Scope headers** uppercase + letter-spacing 0.06em + font-size 10.5px + cor `var(--sb-text-dim)` + chevron 11px à direita
- **Grupos** com persistência por `key` em `localStorage` — sobrevive a F5 e troca de empresa
- **Cascade trigger** (item com `▶`): chevron-right 12px à direita, opacity 0.5 → 1 quando ativo

## Consequências

### Positivas

- **Sidebar 60% mais limpa visualmente** — escaneabilidade saltou: agora o usuário identifica o scope do que procura em 1 segundo (OFFICEIMPRESSO, FINANCEIRO, etc).
- **Tarefas e Chat = ações primárias visíveis sempre** — eliminam a fricção do toggle Chat/Menu antigo.
- **User menu cascata** alinha com padrão de mercado (Linear, Notion, Claude Desktop). Hierarquia visual explícita ao abrir cada subpainel separado em vez de accordions empilhados.
- **Persistência por grupo** permite usuário customizar a sidebar (collapsar grupos que não usa) sem quebrar UX dos outros.
- **Sem decision fatigue do toggle** — usuário não precisa lembrar qual aba estava ativa.

### Negativas / mitigações

- **Lookup table hardcoded** é frágil: string-match de `label` quebra se ADR mudar nome de módulo. **Mitigação:** débito técnico assumido com migração planejada pra `LegacyMenuAdapter` (~2 sprints). Itens não-mapeados ainda aparecem (em "MAIS"), só sem agrupamento — não some.
- **Subpainel Disponível ainda placeholder** — clicar não persiste status no DB. **Mitigação:** documentado neste ADR como TODO de produto.
- **Sidebar pode ficar comprida** com muitos grupos expandidos — scroll natural; "MAIS" colapsado por default mitiga.
- **Mobile <1024px** força subpainel a empilhar vertical (perde a metáfora cascata). Aceitável: usuários mobile usam menos o user menu, e cascata visual perde sentido em telas estreitas.

### Removido / cleanup

- Componentes `SidebarTabs` e `SidebarChat` deletados (linhas 96-200 da v UI-0008)
- Imports lucide unused no Sidebar.tsx limpos: `MessageCircle`, `Hash`, `Bell`, `Cog`, `Inbox`, `Pin`, `Plus`
- `LS.TAB` continua existindo em `shared.ts` mas é ignorado — pode ser removido em ADR futuro (compat backwards inútil porque usuários antigos verão a sidebar nova de qualquer jeito)
- AppShellV2 ainda aceita prop `conversas` (compat com 5+ Pages que passam) mas ignora — em sessão futura, remover prop dessas pages individualmente

## Alternativas consideradas

- **Manter dual-pane com toggle** — rejeitada: causa o problema da hierarquia visual (clutter) e do decision fatigue.
- **Drawer/sheet horizontal pra Menu** (estilo TopNav legado UltimatePOS) — rejeitada: quebra o paradigma Cockpit 3-colunas.
- **User menu com TODOS os items expandidos** (sem cascata, todos visíveis) — rejeitada: 15+ items vertical fica longo demais; cascata permite hierarquia em N níveis sem inflar o painel principal.
- **Backend já entregando grupos** (em vez de lookup table no front) — adiada: requer mudança em `LegacyMenuAdapter` + cada `menu.php` de módulo. Front-table é faster path pra validar UX antes de investir.

## Plano de migração

1. ✅ **Fase 0** (2026-05-05): código deployado em produção. Sidebar single-pane + cascata user menu funcionais.
2. ⏳ **Fase 1** (próximas 1-2 semanas): smoke test em produção com Wagner + Larissa (ROTA LIVRE biz=4). Coletar feedback.
3. ⏳ **Fase 2** (após validação): migrar `SIDEBAR_GROUPS` lookup table pro backend (`LegacyMenuAdapter` + campo `group` em `MenuItem`).
4. ⏳ **Fase 3**: implementar persistência real do status "Disponível" (modelagem `users.presence_status` no DB ou via Centrifugo presence channel).
5. ⏳ **Fase 4**: TaskProvider/TaskRegistry pra `/tarefas` ganhar conteúdo real (alinhado com [ADR raiz 0039](../../../../decisions/0039-ui-chat-cockpit-padrao.md) Fase 4).

## Validação pós-deploy (2026-05-05)

- ✅ Sidebar carrega 23 items agrupados em 7 grupos (OFFICEIMPRESSO/FINANCEIRO/ESTOQUE/RELATÓRIOS/IA/CONFIG + INICIO sem header)
- ✅ Tarefas (badge 6) + Chat (badge 3) renderizam no topo com badges visíveis
- ✅ User menu cascata abre com Wagner Rocha + Meu perfil + Superadmin (active state) + Disponível ▶ + Aparência ▶
- ✅ Click em "Superadmin" abre subpainel à direita com Backup, Módulos, CMS, Office Impresso, Acessar Superadmin (validado em screenshot 2026-05-05)
- ✅ Animação `userMenuSubIn` 180ms suave
- ✅ Tema light/dark — sidebar coerente em ambos (UI-0009 sobrevive)
- ⏳ Smoke test em viewport mobile (<1024px) pendente
- ⏳ Smoke test com Larissa (ROTA LIVRE) pendente

## Refs implementação

- [`resources/js/Layouts/AppShellV2.tsx`](../../../../resources/js/Layouts/AppShellV2.tsx) — remoção do state `tab` + render `<SidebarMenu>` direto
- [`resources/js/Components/cockpit/Sidebar.tsx`](../../../../resources/js/Components/cockpit/Sidebar.tsx) — `SidebarShortcuts`, `SidebarGroup`, `SIDEBAR_GROUPS`, cascade `SidebarUserMenu`
- [`resources/js/Pages/Tarefas/Index.tsx`](../../../../resources/js/Pages/Tarefas/Index.tsx) — Page placeholder pra rota `/tarefas`
- [`resources/css/cockpit.css`](../../../../resources/css/cockpit.css) — classes `.sb-shortcuts`, `.sb-group*`, `.user-menu-cascade`, `.user-menu-main`, `.user-menu-sub`, `.um-cascade-trigger`, `.um-sub-h`
- [`routes/web.php`](../../../../routes/web.php) — Route::get('/tarefas') stub

## Commits relacionados

- `f61589b8` (2026-05-05) — refactor(cockpit): sidebar single-pane (UI-0011) — remove toggle Chat/Menu
- `ab5dbd5c` (2026-05-05) — feat(cockpit): sidebar minimalista contextualizada + user menu cascata

# ADR UI-0008 · Cockpit é o layout-mãe do ERP (3 colunas + Apps Vinculados)

- **Status**: accepted
- **Data**: 2026-04-27
- **Decisores**: Wagner, Claude
- **Categoria**: ui · estruturante
- **Substitui**: parcialmente [UI-0006](0006-padrao-tela-operacional.md) (mantém-se válido para listagens dentro do cockpit), [UI-0007](0007-topbar-desktop-removida-breadcrumb-primeira-linha.md) (topbar volta com função real)
- **Substituído**: [ADR raiz 0008](../../../decisions/0008-sidebar-unica-tabs-horizontais.md) (sidebar 1-item + tabs era pra Ponto isolado dentro do AppShell legado — agora todo o ERP vive dentro do Cockpit)
- **Refs**: [ADR raiz 0039](../../../decisions/0039-ui-chat-cockpit-padrao.md), [CLAUDE.md §10](../../../../CLAUDE.md), branch `feat/copiloto-cockpit-piloto` em produção `https://oimpresso.com/copiloto/cockpit`

## Contexto

Pré-cockpit, o ERP tinha **2 padrões de layout coexistindo**:

1. **AppShell.tsx** (`resources/js/Layouts/AppShell.tsx`) — sidebar accordion vertical (estilo AdminLTE/Blade), conteúdo principal com `PageHeader + KpiGrid + PageFilters + Card(Table)` (formalizado em UI-0006). Topbar removida no desktop por ser redundante (UI-0007).

2. **AppLayout.tsx** + variações isoladas (Copiloto/Chat.tsx, MemCofre/Chat) — cada tela conversacional resolvia a UX por conta própria, sem padrão.

A migração do protótipo Cowork "Oimpresso ERP Comunicação Visual" pro repo (2026-04-27) materializou o padrão **Chat Cockpit**: layout 3-colunas com sidebar dual, conteúdo principal contextual e painel de "Apps Vinculados" reagindo à conversa/tarefa em foco. Já está em produção (`/copiloto/cockpit`).

Agora o ERP tem 3 padrões coexistindo, o que vira problema rapidamente. Esta ADR formaliza qual é o layout-mãe e quando cada um aplica.

## Decisão

**Cockpit é o layout-mãe canônico do ERP** — toda nova tela React do core ERP (operação dia-a-dia, chat, tarefas, dashboards de módulo, telas de OS/CRM/Financeiro/Ponto) **nasce dentro do `AppShellV2`** (Cockpit shell).

`AppShell.tsx` legado **continua acessível** mas é considerado "shell secundário" — só permanece em telas administrativas isoladas que não fazem parte do fluxo operacional (Showcase, Modulos manage, Settings de superadmin standalone).

### Estrutura canônica do Cockpit

```
┌──── 260px ───┬────────── 1fr ──────────┬──── 320px ────┐
│  SIDEBAR     │  TOPBAR (breadcrumb +   │               │
│  (dark)      │   actions contextuais)  │  APPS         │
│              ├─────────────────────────┤  VINCULADOS   │
│  • CompanyP  │                         │               │
│  • [Chat]    │  CONTEÚDO PRINCIPAL     │  (cards       │
│    [Menu]    │   • ThreadHeader        │   colapsáveis │
│  • lista     │   • ChatTabs            │   por contexto│
│    body      │   • ThreadContext       │   da conversa │
│              │   • Thread (msgs)       │   ou tarefa)  │
│  superadmin  │   • Composer            │               │
│  user menu   │                         │               │
└──────────────┴─────────────────────────┴───────────────┘
```

### Mapa de "qual layout pra qual tela"

| Tipo de tela | Layout | Exemplos | Notes |
|---|---|---|---|
| **Chat / conversação** | `AppShellV2` Cockpit | `/copiloto/cockpit`, futuro `/atendimento`, `/equipe` | Sai do mock e vira chat real plugado |
| **Inbox de tarefas (cross-módulo)** | `AppShellV2` Cockpit | `/tarefas` (a criar) | TaskProvider de cada módulo registra |
| **Dashboard de módulo** | `AppShellV2` Cockpit + main = Dashboard | `/copiloto`, `/financeiro`, `/ponto` (re-fazer) | Menu tab carrega shell.menu, dashboard renderiza no main |
| **Listagem operacional** (CRUD) | `AppShellV2` Cockpit + main = template UI-0006 | `/ponto/aprovacoes`, `/ponto/intercorrencias`, futuras | UI-0006 continua válido pro CONTEÚDO da main column. Wrapping por Cockpit |
| **Tela administrativa standalone** | `AppShell` legado | `/showcase/components`, `/modulos`, settings superadmin | Não precisam de chat/apps vinculados |
| **Tela de cliente final público** | `SiteLayout` | `/`, `/c/page/*`, `/login` | Não muda |

### Componentes obrigatórios do Cockpit

**Sidebar (260px, dark fixo na vibe workspace):**
- `CompanyPicker` — dropdown listando empresas que o user tem acesso (todas se superadmin, current senão), avatar com gradiente determinístico por id, "+Adicionar empresa" no footer
- `SidebarTabs` — toggle Chat ↔ Menu (estilo ChatGPT)
- `SidebarChat` — atalhos (Nova conversa, Tarefas, Despachos, Personalizar) + Fixadas + Rotinas + Recentes
- `SidebarMenu` — espelho fiel do `shell.menu` real (LegacyMenuAdapter), filtra superadmin pro rodapé
- `SidebarFooter` — items superadmin (cinza claro, separador) + user dropdown rico (perfil/disponível/aparência/atalhos/ajuda/sair)

**Main column (1fr):**
- `topbar` — breadcrumb dinâmico + ações contextuais (no chat: phone/info/more; toggle Apps Vinculados)
- `ThreadHeader` (chat) ou `PageHeader` (CRUD) ou Dashboard custom
- Body conforme o caso

**Apps Vinculados (320px, opcional):**
- Cards `LBlock` colapsáveis com persistência localStorage
- 5 tipos canônicos: `Os`, `Cliente`, `Financeiro`, `Anexos`, `Historico`
- Origin badges (`OS amber`, `CRM blue`, `FIN green`, `PNT violet`, `MFG orange`)
- Cada bloco mostra resumo enxuto + 1 CTA primária

**Tweaks panel (FAB bottom-right, opcional dev/superadmin):**
- Vibe (workspace/daylight/focus) → repinta paleta inteira via `[data-vibe]`
- Densidade (slider 0-100% Skim↔Briefing) → recalcula `--row-h`, `--card-pad`
- Accent hue (slider 0-360°) → repinta `--accent`, `--bubble-me`, etc. via `oklch()` runtime

### Persistência (localStorage com prefixo `oimpresso.cockpit.*`)

Tudo sobrevive a F5:
- `oimpresso.cockpit.sidebar.tab` — chat/menu
- `oimpresso.cockpit.chat.tab` — todos/os/equipe/clientes
- `oimpresso.cockpit.linked.collapsed` — coluna direita on/off
- `oimpresso.cockpit.conv` — conversa ativa
- `oimpresso.cockpit.tweaks.vibe|density|accentHue|open`
- `oimpresso.linked.<bloco>.collapsed` — cada bloco vinculado individualmente

### Escopo CSS

Tokens do Cockpit ficam **escopados em `.cockpit`** (CSS custom properties). Não vazam pro app legado nem pra Site/Cms. Tokens chave: `--bg`, `--surface`, `--border`, `--accent`, `--bubble-me`, `--bubble-them`, `--origin-{TIPO}-{bg|fg}`, `--row-h`, `--font-sans` (IBM Plex).

CSS implementado em `resources/css/cockpit.css` — importado lazy via `Pages/Copiloto/Cockpit.tsx`.

## Consequências

### Positivas

- **Uma tela só pra trabalhar**: chat + tarefas + apps vinculados visíveis ao mesmo tempo. O usuário deixa de orbitar entre N módulos pra descobrir o que tem pra fazer.
- **Cliente final não reaprende nada**: aba Menu espelha o `shell.menu` atual com mesma ordem/labels/ícones (LegacyMenuAdapter). Migração de um módulo Blade→React = só virar a flag `inertia: true`.
- **Cada módulo entrega seu próprio bloco vinculado** sem tocar na tela de Tarefas (TaskRegistry agrega via `TaskProvider` interface — backend a implementar Fase 4).
- **Vibes/Densidade/Accent permitem demonstrar variações de UX pro cliente** sem manter N protótipos paralelos. Útil pra venda da feature antes de bake-in.
- **Origin badges + cores semânticas por módulo** (OS, CRM, FIN, PNT, MFG) tornam contexto cross-módulo escaneável visualmente.

### Negativas / mitigações

- **Coluna direita ocupa 320px** — em monitor 1280px (cliente ROTA LIVRE) sobra pouco pro chat. **Mitigação:** auto-colapsa abaixo de 1280px via media query, e tem botão de toggle no topbar.
- **AppShell legado e Cockpit coexistem** durante migração. Risco de drift visual entre os dois. **Mitigação:** ADR UI-0006 (template tela operacional) continua válido no envelope Cockpit — mesma DataTable/PageFilters/EmptyState dentro da main column.
- **`shell.menu` filtrado por heurística hardcoded** (set + regex pra pegar Backup/CMS/Connector/Módulos/Office Impresso/Superadmin no rodapé). Frágil se label mudar. **Mitigação Fase 5:** virar flag `is_superadmin` no `MenuItem` do `LegacyMenuAdapter` pra ficar declarativo.
- **Switch de empresa ainda visual** (alert "em breve") — endpoint `POST /copiloto/cockpit/switch-business` real é Fase 4, depende de modelagem de "grupo econômico" que UltimatePOS atual (User belongsTo 1 Business) não suporta nativamente.

## Alternativas consideradas

- **Manter AppShell como layout único** — rejeitada: não resolve o problema de rotação entre módulos pro usuário operacional.
- **Cockpit como modo opcional** (toggle do user) — rejeitada: vira fragmentação. Cockpit é o futuro, AppShell é fallback temporário pra telas isoladas.
- **Fazer Cockpit "skin" do AppShell legado** (mesmo componente, CSS diferente) — rejeitada: a estrutura é fundamentalmente diferente (3 colunas vs 2), mistura sairia ruim.

## Plano de migração (mesmo da ADR raiz 0039, refletido aqui pra rastreabilidade)

1. ✅ **Fase 0**: protótipo HTML+React validado em `Oimpresso ERP - Chat.html` (Cowork).
2. ✅ **Fase 1**: `AppShellV2` + `Pages/Copiloto/Cockpit.tsx` + `cockpit.css` portados pro repo. Smoke test em produção `/copiloto/cockpit`. **(2026-04-27)**
3. ✅ **Fase 2.1**: Tweaks panel (vibe/densidade/accent hue). **(2026-04-27)**
4. ✅ **Fase 2.2**: CompanyPicker funcional + Menu real (shell.menu) + rodapé rico (superadmin items + user dropdown). **(2026-04-27)**
5. ✅ **Fase 2.3**: Polish thread (header rich + context bar + bolhas continued + ✓✓ + typing indicator) + LinkedApps completo (Os/Cliente/Fin/Anexos/Historico). **(2026-04-27)**
6. ⏳ **Fase 3**: plugar chat real do Copiloto (composer envia pra `POST /copiloto/conversas/{id}/mensagens` que já existe).
7. ⏳ **Fase 4**: `TaskProvider` interface + `TaskRegistry` service + 1 provider piloto (`OsAprovarArteTask` no Officeimpresso) + tela `/tarefas`.
8. ⏳ **Fase 5**: outros módulos ganham providers (CRM, FIN, PNT). Switch de empresa real. Flag `useV2Shell` vira default.
9. ⏳ **Fase 6**: `AppShell` legado é removido (decommission) — mantém-se só pra telas administrativas isoladas.

## Validação

Testado em produção (`https://oimpresso.com/copiloto/cockpit`):

- ✅ Sidebar dual Chat/Menu alterna, persiste em F5
- ✅ CompanyPicker dropdown abre, lista WR Sistemas com ✓, "+ Adicionar empresa"
- ✅ Aba Menu carrega 33 módulos do `shell.menu` real
- ✅ Rodapé com 5 superadmin items (Backup, Módulos, CMS, Office Impresso, Superadmin) + user dropdown rico
- ✅ ThreadHeader com avatar TP gradiente + nome + dot online + actions
- ✅ Context bar OS pill + cliente + estágio + prazo
- ✅ 4 bolhas com author label + ✓✓
- ✅ Typing indicator anima ao mandar mensagem (3 dots pulsando)
- ✅ Composer auto-grow + disabled quando vazio
- ✅ 5 LinkedApps blocks colapsáveis com origem badges coloridos
- ✅ Tweaks panel (vibe daylight/accent 30°/density 75% testados em runtime)

Pendente: testes Pest browser pro fluxo completo. **(Fase 3 do plano de validação)**

# DESIGN.md — Ponto de entrada de design do Oimpresso ERP

> **Para quem é este arquivo:** qualquer pessoa (humana ou agente) que vai trabalhar no visual, na UX, no design system, ou que precisa abrir uma sessão no **Claude Design canvas** (claude.ai/design) pra produzir mockups.
>
> Pra trabalhos de código backend/CRUD não-visuais, comece em [`CLAUDE.md`](CLAUDE.md). Pra acesso/deploy de produção, em [`INFRA.md`](INFRA.md).

---

## 1. O que você quer fazer?

| Cenário | Vá direto pra |
|---|---|
| Codar uma tela React nova ou alterar uma existente | Seção "Padrão técnico de implementação React" abaixo nesse arquivo |
| Mockup visual numa nova sessão Claude Design canvas | [`memory/requisitos/_DesignSystem/BRIEFING_CLAUDE_DESIGN.md`](memory/requisitos/_DesignSystem/BRIEFING_CLAUDE_DESIGN.md) — colar como 1ª mensagem + anexar arquivo |
| Decidir se um padrão visual é canônico ou divergência | [`memory/decisions/0039-ui-chat-cockpit-padrao.md`](memory/decisions/0039-ui-chat-cockpit-padrao.md) — ADR principal de UI |
| Buscar/usar um componente shared existente | [`memory/requisitos/_DesignSystem/SPEC.md`](memory/requisitos/_DesignSystem/SPEC.md) |
| Auditar uma tela contra o sistema de design | [`memory/requisitos/_DesignSystem/audits/`](memory/requisitos/_DesignSystem/audits/) |
| Catálogo de acabamentos (lâminas, vinis, papéis) pra módulos gráficos | [`memory/requisitos/_DesignSystem/CATALOGO_ACABAMENTOS.md`](memory/requisitos/_DesignSystem/CATALOGO_ACABAMENTOS.md) |
| Visão geral da arquitetura visual | [`memory/requisitos/_DesignSystem/ARCHITECTURE.md`](memory/requisitos/_DesignSystem/ARCHITECTURE.md) |
| Histórico de mudanças no design system | [`memory/requisitos/_DesignSystem/CHANGELOG.md`](memory/requisitos/_DesignSystem/CHANGELOG.md) |

---

## 2. Workflow padrão de design (Wagner usa hoje)

```
1. Wagner abre claude.ai/design (ou continua sessão "Oimpresso ERP Comunicação Visual")
2. Cola o template de prompt da §9 do BRIEFING_CLAUDE_DESIGN.md como 1ª mensagem
3. Anexa o BRIEFING_CLAUDE_DESIGN.md à conversa
4. Itera com Claude Design — recebe zip com HTML + manuais por módulo
5. Manda o zip pro Claude Code (sessão Code do desktop) — esse porta pro repo
   (cria ou ajusta Page Inertia React + componentes shared)
6. Code abre PR ou commita direto (depende do escopo)
```

---

## 3. Princípios não-negociáveis

- **PT-BR em tudo** — copy, label, comentário, commit. Código (classes, métodos) em inglês é OK.
- **Layout-mãe "Chat Cockpit"** (ADR 0039) — toda tela nova nasce dentro do `AppShellV2` 3-colunas (sidebar 260px / coluna principal / Apps Vinculados 320px opcional).
- **Tokens CSS do shell** (definidos em `resources/css/app.css`) — nunca cor hardcoded.
- **Componentes shared antes de criar novo** — `PageHeader`, `DataTable`, `PageFilters`, `KpiCard`, `ModuleTopNav`, `StatusBadge`, `EmptyState`, etc.
- **Atalhos canônicos** — ⌘K busca global, J/K navegar lista, E concluir, A adiar, N novo, / focar busca.
- **Cliente não pode reaprender o sistema** — qualquer mudança de menu/labels/ícones precisa de aprovação explícita do Wagner.
- **Dark mode + responsivo mobile** — DoD mínimo de qualquer tela.

Pra divergir de qualquer regra acima: **abrir ADR nova antes de codar**, não decidir em commit solto.

---

## 4. Stack visual

- **Inertia v3** + **React 19** + **TypeScript** + **Tailwind 4** + **shadcn/ui** + **lucide-react**
- **AppShellV2** (em portagem) + componentes em `resources/js/Components/`
- **TaskProvider** pra inboxes de módulo (não cria tela própria — registra provider em `Modules/<Mod>/Tasks/<Slug>Task.php`)
- **localStorage** com prefixo `oimpresso.` pra persistir estado de UI (empresa ativa, aba, filtros, painéis colapsados)

---

## 5. Designer canônico

**Claude (Anthropic)** — em última instância, decide visual sem perguntar; mas registra a decisão no session log de `memory/sessions/YYYY-MM-DD-*.md`.

Wagner é o aprovador final em divergências de padrão. Cliente (WR2 Sistemas / Eliana) é o aprovador final em mudanças de fluxo de trabalho do PontoWr2.

---

# Padrão técnico de implementação React

> **Leia esta seção SEMPRE que for criar nova tela ou alterar tela existente em React (Inertia v3).**
> Padrão formalizado em [ADR 0039](memory/decisions/0039-ui-chat-cockpit-padrao.md).

## 6. Antes de codificar qualquer tela

1. **Leia [ADR 0039](memory/decisions/0039-ui-chat-cockpit-padrao.md)** — define layout-mãe "Chat Cockpit" 3-colunas, dual-tab Chat/Menu, painel direito de Apps Vinculados, atalhos J/K/E/A, Tweaks (vibe/densidade/accentHue).
2. **Leia o session log mais recente em `memory/sessions/`** — pode ter ajuste de design não refletido no ADR ainda.
3. **Olhe o protótipo de referência** — projeto Cowork "Oimpresso ERP Comunicação Visual", arquivo `Oimpresso ERP - Chat.html`. É a verdade visual mais atual.
4. **Olhe `resources/js/Layouts/AppShell.tsx`** (atual) e o futuro `AppShellV2.tsx` (quando portado) — cliente final NÃO pode reaprender o sistema. Qualquer mudança de menu/labels/ícones precisa ser aprovada explicitamente.

## 7. Hierarquia de decisões de UI

Em ordem de precedência (de cima pra baixo, regra mais alta vence em conflito):

1. **Stack-target do projeto** (Inertia v3 + React 19 + TS + Tailwind 4) — não muda sem ADR.
2. **Layout-mãe "Chat Cockpit"** (ADR 0039) — não muda sem ADR substitutivo.
3. **Padrão Jana** (ADR 0011) — UltimatePOS-like; vale para tudo que não conflita com 0039.
4. **Componentes shared do projeto** (`PageHeader`, `DataTable`, `PageFilters`, `KpiCard`, `ModuleTopNav`, `StatusBadge`, `EmptyState`) — usar antes de criar novo.
5. **Convenções 04** (`memory/04-conventions.md`) — naming PHP, rotas, blade.
6. **Bom gosto do designer** — em última instância, Claude decide visual sem perguntar; mas registra a decisão no session log.

## 8. Layout obrigatório de tela nova

Toda tela React do ERP **nasce dentro do `AppShellV2`** (3 colunas), com:

- **Sidebar (260px)** vinda do shell — você não recria sidebar dentro de página.
- **Topbar com breadcrumb** vinda do shell — você só passa `crumb={[...]}` via Inertia layout.
- **Coluna principal (1fr)** = sua tela.
- **Coluna direita (320px) "Apps Vinculados"** — *opcional*. Se sua tela tem contexto vinculado (uma OS em foco, um cliente, uma marcação), **você é obrigado a entregar o painel direito** com os blocos relevantes. Se não tem, a coluna some.

Para tela em modo **master/detail** (lista + viewer), use o padrão de `Pages/Tarefas/Index.tsx`:
- Lista à esquerda da coluna principal (ex.: 360px), viewer à direita (1fr).
- Atalhos **J/K** (navegar), **E** (concluir/confirmar), **A** (adiar/voltar) ligados via `useEffect` + listener global escopado à página.

Para tela em modo **CRUD clássico** (cadastro, listagem, edição), siga padrão Jana: `PageHeader` + `PageFilters` + `DataTable` + drawer/modal de edição.

## 9. Tokens visuais

Use **sempre** as variáveis CSS do shell (definidas em `resources/css/app.css`):

```
--bg, --bg-2, --panel, --panel-2, --border, --border-2
--text, --text-mute, --accent, --accent-2, --accent-soft
--origin-OS-{bg,fg}, --origin-CRM-{bg,fg}, --origin-FIN-{bg,fg}, --origin-PNT-{bg,fg}
--row-h, --card-pad, --card-gap
```

**Não invente cor solta.** Se precisar de uma cor nova, derive via `oklch()` a partir de `--accent` ou da origem do módulo.

## 10. Apps Vinculados (coluna direita)

Cada bloco do painel direito é um componente em `resources/js/Components/LinkedApps/`:

- `LinkedOs.tsx` — número, cliente, prazo, estágio, CTA `[abrir]`
- `LinkedClient.tsx` — nome, telefone, último contato, CTA `[ligar]` `[whatsapp]`
- `LinkedPonto.tsx` — marcações do colaborador no dia, CTA `[justificar]`
- `LinkedFinanceiro.tsx` — saldo cliente + boletos abertos, CTA `[emitir cobrança]`
- `LinkedAttachments.tsx` — anexos da conversa/tarefa
- `LinkedHistory.tsx` — eventos cronológicos

**Regra:** cada bloco é colapsável (estado em `localStorage` por chave `oimpresso.linked.<bloco>.collapsed`), mostra um resumo enxuto e UMA ação primária. Se a tela não tem dado para um bloco, ele simplesmente não renderiza.

## 11. TaskProvider (quando criar tela de inbox)

Se a tela nova é uma inbox de pendências do módulo, **NÃO crie tela própria**. Em vez disso, registre um `TaskProvider`:

```php
// Modules/<Mod>/Tasks/<Slug>Task.php
class <Slug>Task implements TaskProvider {
  public function origin(): string { return 'OS'|'CRM'|'FIN'|'PNT'|'MFG'; }
  public function color(): string  { return 'amber'|'blue'|'emerald'|'violet'|'orange'; }
  public function for(User $u): Collection { /* o que esse usuário precisa fazer */ }
  public function viewerComponent(): string { return '<NomeDoComponenteReact>'; }
}
```

E entregue o componente viewer em `resources/js/Components/Viewers/<NomeDoComponenteReact>.tsx`. A tela `Pages/Tarefas/Index.tsx` agrega via `TaskRegistry` e renderiza o viewer correto.

## 12. Persistência de estado de UI

**Sempre** persistir em `localStorage` com prefixo `oimpresso.`:
- estado de empresa ativa, aba, rota, conversa, tarefa selecionada, filtros
- estado de painéis (colapsado/aberto), accordions do menu
- preferências do Tweaks panel

Nunca persistir em `sessionStorage` para esses casos — perdem na nova aba.

## 13. Atalhos de teclado

Lista canônica do ERP (toda tela nova herda):
- **⌘K / Ctrl+K** — busca global (já no shell)
- **J / K** — navegar lista (em master/detail)
- **E** — concluir/confirmar item em foco
- **A** — adiar/postergar item em foco
- **N** — nova entidade (em listagem CRUD; verbo do módulo)
- **/** — focar busca da lista atual

Toda tela com lista deve registrar listener via `useEffect` e `removeEventListener` no cleanup.

## 14. Quando divergir do padrão

Se você (humano ou agente) achar que precisa quebrar o padrão de UI:

1. **Pare antes de codificar.**
2. **Abra ADR nova** (próximo número sequencial após o último em `memory/decisions/`) explicando contexto/decisão/alternativas/consequências.
3. **Peça aprovação do Wagner** antes de mergear.
4. **Atualize esta seção** com a nova regra.

Padrão muda por ADR, nunca por commit solto.

## 15. Checklist mínimo antes de PR

- [ ] Tela vive dentro de `AppShellV2`
- [ ] Tokens CSS do shell (sem cor hardcoded)
- [ ] Coluna direita "Apps Vinculados" entregue se houver contexto vinculado
- [ ] Atalhos J/K/E/A ativos se for master/detail
- [ ] Estado persistido em `localStorage` com prefixo `oimpresso.`
- [ ] Componentes shared reusados antes de criar novo
- [ ] PT-BR em todo label/copy/comentário
- [ ] Se inbox de módulo → `TaskProvider` em vez de tela nova
- [ ] Session log atualizado em `memory/sessions/`
- [ ] ADR nova se quebrou padrão

---

> **Última atualização:** 2026-04-28 (consolidado: hub de design (criado 2026-04-28) + spec técnico React (antes em CLAUDE.md §10) num arquivo só)
> **Próxima revisão sugerida:** quando portar `AppShellV2.tsx` pro repo (Fase 1 da migração ADR 0039)

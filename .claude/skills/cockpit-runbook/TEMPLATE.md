# TEMPLATE — RUNBOOK de tela do Cockpit

> Copiar abaixo como ponto de partida. Substituir todo `<placeholder>` por valor real. Apagar `> Dica:` antes de salvar.

```markdown
---
slug: <mod-lower>-runbook-<tela-kebab>
title: "<Mod> — Runbook da tela <Nome legível>"
type: runbook
module: <Mod>
owner: W                        # obrigatório — enum W/F/M/L/E (runbook.schema.json)
status: ativo                   # enum: rascunho|ativo|arquivado|historical (NUNCA "active")
last_validated: "<YYYY-MM-DD>"  # obrigatório, STRING quoted — data crua vira Date; alerta se >30d
---

# RUNBOOK — <Nome legível da tela>

> **Tipo:** runbook reproduzível
> **Refs:** [ADR 0039](../../decisions/0039-ui-chat-cockpit-padrao.md), [_DS ADR 0008](../_DesignSystem/adr/ui/0008-cockpit-layout-mae-do-erp.md), [<outras ADRs específicas>]
> **Validado:** <PR #N> em <YYYY-MM-DD>

<1 parágrafo, 3-5 linhas> explicando: o que a tela faz, pra quem (persona), dentro de qual layout-mãe (Cockpit 3 colunas: sidebar 260 / main 1fr / Apps Vinculados 320, ou stub legado).

## Estado final esperado

| Verificação | Como conferir |
|---|---|
| Tela renderiza em `/<rota>` | Login com permissão `<alias>.access` → URL → tela aparece |
| AppShellV2 envolvendo | Inspetor: `<div class="app-shell-v2">` ao redor da Page |
| Coluna direita populada (se contexto vinculado) | Bloco `<LinkedXxx/>` visível |
| Atalhos J/K respondem (se master/detail) | Foco na lista + `j` desce, `k` sobe |
| Dark mode funciona | Toggle no topbar → contrastes ≥ 4.5:1 |

## 1. Objetivo

> Dica: a tabela "Estado final esperado" acima é OPCIONAL. Se a tela é simples (CRUD direto sem regra de negócio), pode pular e ir direto pro parágrafo abaixo.

<Quem usa, o que faz, em qual layout, qual problema resolve. Tom: 1 parágrafo enxuto.>

## 2. Pré-condições

- [ ] Módulo `<Mod>` instalado em `/manage-modules`
- [ ] Permissão `<alias>.access` atribuída ao role do usuário
- [ ] Rotas registradas em [`Modules/<Mod>/Routes/web.php`](../../../Modules/<Mod>/Routes/web.php)
- [ ] Page Inertia em [`resources/js/Pages/<Mod>/<Tela>.tsx`](../../../resources/js/Pages/<Mod>/<Tela>.tsx) — módulo em **PascalCase** (`Copiloto`, não `copiloto`)
- [ ] Skill irmã carregada se aplicável: `multi-tenant-patterns` (multi-empresa) ou `copiloto-arch` (Copiloto)
- [ ] Seed/fixture: `<como popular dados de teste>`

## 3. Passo-a-passo

### 1. <Verbo imperativo + objeto>

```php
// <path absoluto a partir da raiz>
<snippet copy-pasteable>
```

**Validação:** depois desse passo, rodar `<comando>` — esperado: `<output>`.

### 2. <Próximo passo>

```tsx
// resources/js/Pages/<Mod>/<Tela>.tsx
import { AppShellV2 } from '@/Layouts/AppShellV2'

interface PageProps {
  // <props recebidos via Inertia.render>
}

export default function <Tela>({ /* props */ }: PageProps) {
  return <div>{/* ... */}</div>
}

// Persistent layout (DESIGN.md §4)
<Tela>.layout = (page) => <AppShellV2>{page}</AppShellV2>
```

### 3. <Continuar até cobrir 5-10 passos>

> Dica: se passar de 10 passos, quebrar em RUNBOOKs por escopo (ex: RUNBOOK-tela-criacao.md + RUNBOOK-tela-edicao.md).

## 4. Tokens CSS

Vars do shell que esta tela DEVE usar (definidos em [`resources/css/app.css`](../../../resources/css/app.css)):

| Token | Onde aplica | Esta tela usa? |
|---|---|---|
| `--bg`, `--bg-2` | Fundo da viewport | ✅ |
| `--panel`, `--panel-2` | Cards, drawers | ✅ |
| `--border`, `--border-2` | Bordas + dividers | ✅ |
| `--text`, `--text-mute` | Texto primário/secundário | ✅ |
| `--accent`, `--accent-2`, `--accent-soft` | CTAs, foco, highlights | ✅ |
| `--origin-OS-{bg,fg}` | Tag de origem OS | <✅/❌> |
| `--origin-CRM-{bg,fg}` | Tag de origem CRM | <✅/❌> |
| `--origin-FIN-{bg,fg}` | Tag de origem Financeiro | <✅/❌> |
| `--origin-PNT-{bg,fg}` | Tag de origem Ponto | <✅/❌> |
| `--row-h`, `--card-pad`, `--card-gap` | Densidade | ✅ |

**Tokens shadcn semânticos** (R-DS-002, _DesignSystem/SPEC.md):

```tsx
// ✅ correto
<div className="bg-primary text-primary-foreground border-border" />

// ❌ errado — cor crua quebra dark mode
<div className="bg-blue-500 text-gray-700" />
```

## 5. Estados visuais

| Estado | Trigger | Tokens / classes | Notas |
|---|---|---|---|
| `default` | — | `bg-panel border-border` | Estado base |
| `hover` | mouse-over | `hover:bg-panel-2` | Aplica em rows clicáveis |
| `focus` | tab/click | `focus-visible:ring-2 focus-visible:ring-accent` | Acessibilidade obrigatória |
| `active` | mousedown / [aria-pressed] | `bg-accent-soft` | Toggle states |
| `disabled` | `aria-disabled="true"` | `opacity-50 pointer-events-none` | Cursor `not-allowed` |
| `loading` | data fetching | `<Skeleton/>` ou `<Spinner/>` + `aria-busy="true"` | Nunca tela em branco |
| `empty` | sem dados | `<EmptyState/>` shared | Microcopy em PT-BR |
| `error` | falha de fetch / 500 | `<ErrorBoundary/>` + retry CTA | Logar em Sentry |

```tsx
// Snippet canônico de empty state
import { EmptyState } from '@/Components/shared/EmptyState'

{items.length === 0 && (
  <EmptyState
    icon={<InboxIcon />}
    title="Nenhuma <entidade> ainda"
    description="<microcopy explicando o vazio>"
    primaryAction={{ label: 'Criar <entidade>', onClick: handleCreate }}
  />
)}
```

## 6. Responsividade

Breakpoints canônicos (Tailwind 4 default):

| Breakpoint | Largura | Comportamento desta tela |
|---|---|---|
| `default` | <640px | <Comportamento mobile — talvez tabs no lugar de colunas> |
| `sm` | ≥640px | <Comportamento> |
| `md` | ≥768px | <Comportamento> |
| `lg` | ≥1024px | Coluna direita colapsa pra 44px (ADR 0039 mitigação) |
| `xl` | ≥1280px | 3 colunas full Cockpit (sidebar 260 / main 1fr / direita 320) |
| `2xl` | ≥1536px | <Comportamento se diferente> |

**Master/detail mobile:** abaixo de `md`, lista vira tela única com botão "voltar" pra abrir o item — não tentar empilhar lista+viewer em <768px.

## 7. Atalhos

| Tecla | Ação | Escopo | Listener |
|---|---|---|---|
| `⌘K` / `Ctrl+K` | Busca global | Shell (não a tela) | Já no AppShellV2 |
| `J` | <Próxima linha / —> | <Lista quando focada> | `useEffect` |
| `K` | <Linha anterior / —> | <Lista quando focada> | `useEffect` |
| `E` | <Concluir item em foco / —> | <Item em foco> | `useEffect` |
| `A` | <Adiar item em foco / —> | <Item em foco> | `useEffect` |
| `N` | <Nova entidade / —> | <Tela inteira> | `useEffect` |
| `/` | <Focar busca local / —> | <Tela inteira> | `useEffect` |

```tsx
// Snippet canônico de listener (DESIGN.md §13)
useEffect(() => {
  const handler = (e: KeyboardEvent) => {
    if (e.target instanceof HTMLInputElement) return // não interferir em inputs
    if (e.key === 'j') { /* próximo */ }
    if (e.key === 'k') { /* anterior */ }
    if (e.key === 'e') { /* concluir */ }
    if (e.key === 'a') { /* adiar */ }
  }
  window.addEventListener('keydown', handler)
  return () => window.removeEventListener('keydown', handler)
}, [/* deps relevantes */])
```

## 8. Component contract

Props que a Page recebe via `Inertia::render('<Mod>/<Tela>', [...])`:

```tsx
interface <Tela>PageProps {
  // <prop>: <tipo>  // <descrição curta>
  items: Array<{
    id: number
    nome: string
    status: 'aberto' | 'concluido' | 'cancelado'
    // ...
  }>
  filters: {
    q: string
    status: string | null
    page: number
  }
  permissions: {
    canCreate: boolean
    canEdit: boolean
  }
}
```

**Componentes shared usados** (lista clicável):

- [`@/Components/shared/PageHeader`](../../../resources/js/Components/shared/PageHeader.tsx)
- [`@/Components/shared/DataTable`](../../../resources/js/Components/shared/DataTable.tsx)
- [`@/Components/shared/PageFilters`](../../../resources/js/Components/shared/PageFilters.tsx)
- [`@/Components/shared/EmptyState`](../../../resources/js/Components/shared/EmptyState.tsx)
- [<adicionar shared usados pela tela>]

## 9. DoD checklist

Antes de abrir PR (vide [CHECKLIST.md](../../../.claude/skills/cockpit-runbook/CHECKLIST.md) pra audit completo):

- [ ] Tela vive dentro de `AppShellV2` (Persistent Layout, sem envolver `<AppShell>` interno)
- [ ] Tokens CSS do shell + shadcn semânticos (sem cor crua — R-DS-002)
- [ ] Coluna direita "Apps Vinculados" entregue se houver contexto vinculado
- [ ] Atalhos J/K/E/A ativos se for master/detail (com `removeEventListener` no cleanup)
- [ ] Estado persistido em `localStorage` com prefixo `oimpresso.` (não `sessionStorage`)
- [ ] Componentes shared reusados antes de criar novo (R-DS-001)
- [ ] PT-BR em todo label/copy/comentário
- [ ] Dark mode validado (contraste ≥ 4.5:1 — R-DS-005)
- [ ] Responsividade: 320px, 640px, 768px, 1024px, 1280px conferidos
- [ ] Estados visuais cobertos: default/hover/focus/disabled/loading/empty/error
- [ ] Bundle Inertia builda: `npm run build:inertia` + `grep -i "Pages/<Mod>" public/build-inertia/manifest.json`
- [ ] Multi-tenant: queries usam global scope `business_id` se aplicável

## 10. Pegadinhas

> Apender pegadinhas específicas desta tela. Genéricas estão em [GOTCHAS.md](../../../.claude/skills/cockpit-runbook/GOTCHAS.md).

- ❌ NÃO usar `route('xxx.yyy')` em Pages React — Ziggy não está disponível neste Inertia. Sintoma: `route is not defined` no console. Fix: template literal `` href={`/<prefix>/admin/${id}`} ``.
- ❌ NÃO envolver Page em `<AppShell>` — Persistent Layouts (auto-mem `preference_persistent_layouts`). Sintoma: shell duplicado, scroll quebrado. Fix: `<Tela>.layout = (page) => <AppShellV2>{page}</AppShellV2>`.
- ❌ NÃO inventar cor solta — derive via `oklch()` a partir de `--accent` ou origem do módulo. Sintoma: tela quebra no dark mode.
- ❌ NÃO rodar `npm run build` (config errado) — sempre `npm run build:inertia`. Sintoma: bundle Page não aparece em `manifest.json` → tela 404.
- ❌ NÃO usar `sessionStorage` pra estado de UI — perde na nova aba. Sempre `localStorage` com prefixo `oimpresso.`.
- ❌ <Pegadinha específica desta tela descoberta em audit/session log>

## 11. ADR de origem

- [ADR 0039 — Chat Cockpit](../../decisions/0039-ui-chat-cockpit-padrao.md) — layout-mãe 3 colunas
- [ADR 0011 — Padrão Jana](../../decisions/0011-alinhamento-padrao-jana.md) — base estrutural UltimatePOS-like
- [ADR 0023 — Inertia v3](../../decisions/0023-inertia-v3.md) — base técnica (substituir slug pela rota correta se diferente)
- [_DS ADR 0008 — Cockpit layout-mãe](../_DesignSystem/adr/ui/0008-cockpit-layout-mae-do-erp.md)
- [_DS ADR UI-0023 — Sidebar preta (dark-fixo)](../_DesignSystem/adr/ui/0023-sidebar-dark-fixo-preto-definitivo-supersede-0019.md)
- [<ADR específica desta tela, se houver>]

> Se a tela quebra padrão de UI: **NÃO publicar runbook** — abrir ADR substitutiva primeiro (DESIGN.md §14).

---

**Última atualização:** <YYYY-MM-DD>
```

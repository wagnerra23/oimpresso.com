---
slug: jana-runbook-chat
title: "Jana — Runbook da tela Chat (/ia)"
type: runbook
module: Jana
owner: W
status: ativo
date: "2026-07-09"
last_validated: "2026-07-09"
---

# RUNBOOK — Chat da Jana (`/ia`)

> **Tipo:** runbook reproduzível
> **Refs:** [ADR 0026](../../decisions/0026-posicionamento-erp-grafico-com-ia.md), [ADR 0031](../../decisions/0031-memoriacontrato-mem0-default.md), [ADR 0035](../../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md), [ADR 0036](../../decisions/0036-replanejamento-meilisearch-first.md), [ADR 0039](../../decisions/0039-ui-chat-cockpit-padrao.md), [_DS UI-0008](../_DesignSystem/adr/ui/0008-cockpit-layout-mae-do-erp.md), [_DS UI-0010](../_DesignSystem/adr/ui/0010-zip-cowork-2026-04-27-canon-visual.md)
> **Validado:** tela em produção `https://oimpresso.com/ia`. Re-validação **estática** contra o código de `origin/main` em 2026-07-09 (refs, rotas, controller, componentes conferidos arquivo a arquivo). ⚠️ **Fluxo vivo (envio de mensagem, SSE, escolher/rejeitar sugestão) NÃO exercitado em 2026-07** — última validação funcional registrada é o audit 2026-05-08.

> **🪶 Naming + URL:** o assistente IA chama-se **Jana** (Wagner 2026-05-08). A URL foi renomeada em 2 fases: `/copiloto` → `/jana` (Wagner 2026-05-09, Fase 2b) → **`/ia`** (Wagner 2026-05-22, ADR 0180 sidebar v3 — label "IA"). Redirects 301 em cadeia cobrem bookmarks (`/copiloto/X` → `/jana/X` → `/ia/X`). Route names permanecem `jana.*`; permissions renomeadas `copiloto.*` → `jana.*` (ex: `jana.chat` no topnav). Classes CSS `copiloto-chat-*` e o nome `COPILOTO_AVATAR` seguem como dívida cosmética. **Em texto novo (UI/docs/ADR/PR/commit) sempre Jana**.

Tela principal da Jana — chat conversacional com a assistente IA que cria/edita metas e responde perguntas de negócio em linguagem natural. Persona: dono operador (Larissa, ROTA LIVRE biz=4) abre `/ia` de manhã, conversa com a Jana sobre faturamento/metas/clientes, recebe propostas de metas em cards inline e escolhe/rejeita. Vive dentro do `AppShellV2`; desde o header sticky `JanaAreaHeader` (tabs Dashboard | Chat) a tela tem layout grid próprio `copiloto-chat-layout` (lista de conversas 280px `ConvSidePanel` + thread 1fr — [cockpit.css:1423](../../../resources/css/cockpit.css)). Renderização do thread+composer delegada à lib `@assistant-ui/react` v0.10 ([JanaAssistantUiChat](../../../resources/js/Pages/Jana/_components/AssistantUiChat.tsx) — movido de `Components/copiloto/` pra colocation `Pages/Jana/_components/`).

## Estado final esperado

| Verificação | Como conferir |
|---|---|
| Tela renderiza em `/ia` | Login → URL → JanaAreaHeader + ConvSidePanel + ThreadHeader + thread + composer visíveis |
| AppShellV2 envolvendo | Inspetor: `<div class="cockpit">` ao redor; sidebar (light em `data-theme="light"`) + breadcrumb com módulo dropdown |
| Breadcrumb dropdown abre lista de telas Jana | Clicar em "IA" no breadcrumb → dropdown com os itens ativos do [`Modules/Jana/Resources/menus/topnav.php`](../../../Modules/Jana/Resources/menus/topnav.php) (hoje 7: Conversar/Dashboard/Metas/Governança MCP/KB→/Qualidade IA/Plataforma — Alertas comentado, Team MCP migrou pra `/team-mcp/*`) |
| ThreadHeader com avatar+dot+actions | "Jana" como nome + sub "Assistente IA · Jana" + ícones Phone/Info/More à direita |
| Composer envia mensagem | Digite + ⏎ → mensagem persistida via [POST /ia/conversas/{id}/mensagens](../../../Modules/Jana/Http/Controllers/ChatController.php:313) (variante SSE preferida pelo frontend: [sendStream :366](../../../Modules/Jana/Http/Controllers/ChatController.php:366)) |
| PropostaCard inline quando há sugestões | `sugestoesPendentes.length > 0` → grid 1/2/3 colunas com Badge dificuldade (Fácil/Realista/Ambicioso) + CTA Escolher/Rejeitar |
| Apps Vinculados (coluna direita) | LinkedAppsPanel renderiza vazio (Jana não vincula OS/cliente externamente — comportamento atual). Toggle no topbar colapsa pra 0px |

## 1. Objetivo

Centralizar a interação conversacional com a Jana IA — assistente que opera sobre o contexto de negócio do business em foco (faturamento bruto/líquido/caixa, metas ativas, clientes, OS recentes via `ContextoNegocio` ADR 0052) e propõe ações via cards inline (`PropostaCard`). Persona principal: dono operador, **não-técnico**, que conversa em linguagem natural ("quanto vendi?", "criar meta de faturamento mensal"). Layout-mãe Cockpit. Persistência da conversa: tabela `jana_conversas` + `jana_mensagens` (renomeadas de `copiloto_*`; multi-tenant por `business_id` — [Conversa.php:25](../../../Modules/Jana/Entities/Conversa.php)). Thread+composer renderizados pela lib externa `@assistant-ui/react` (suporta Markdown, code highlight, edit/regenerate). Custo: tokens reportados em `jana_mensagens.tokens_in/tokens_out` + telemetria OTel GenAI (ADR 0050).

## 2. Pré-condições

- [ ] Módulo `Jana` instalado em `/manage-modules`
- [ ] Permissão `jana.chat` no role do usuário (renomeada de `copiloto.chat`) — gate do item "Conversar" no topnav; as rotas do grupo `/ia` em si são protegidas pelo stack `['web', 'SetSessionData', 'auth', ..., 'throttle:120,1']` sem `can:` por rota
- [ ] Rotas registradas em [`Modules/Jana/Http/routes.php`](../../../Modules/Jana/Http/routes.php) (a pasta `Modules/Jana/Routes/` não existe mais) — `/ia` (index `jana.chat.index`), `/ia/conversas/{id}` (show), `/ia/conversas/{id}/mensagens` (send) + `/mensagens/stream` (SSE), `/ia/sugestoes/{id}/escolher` + `/rejeitar`
- [ ] Page Inertia em [`resources/js/Pages/Jana/Chat.tsx`](../../../resources/js/Pages/Jana/Chat.tsx) — módulo em **PascalCase**
- [ ] Skill irmã carregada: `jana-arch` (stack ADRs 0035-0053) — tela toca conceitos da Jana
- [ ] Skill irmã `multi-tenant-patterns` ativa — Controller filtra `business_id` em `Conversa::where(...)`
- [ ] AI driver ativo: `LaravelAiDriver` (ADR 0035) ou fallback `OpenAiDirectDriver`. Em dev: `COPILOTO_AI_DRY_RUN=true` retorna fixtures
- [ ] Meilisearch rodando (CT 100 ou local) pra retrieval de memória (`MemoriaContrato` ADR 0036)
- [ ] Seed: `php artisan module:seed Jana` popula 5 metas template + meta raiz

## 3. Passo-a-passo

### 1. Controller renderiza Inertia com shell + props específicos do Chat

```php
// Modules/Jana/Http/Controllers/ChatController.php:86 — helper renderChat()
// (index() :38 e show() :76 convergem aqui)
$shellProps = $this->shellPropsForDeferred($businessId, $conversa, $userId);

return Inertia::render('Jana/Chat', array_merge(
    $shellProps,
    [
        'conversa'           => $conversa,
        'mensagens'          => $this->buildMensagensPayload($conversa),
        'sugestoesPendentes' => $this->buildSugestoesPendentesPayload($conversa),
    ]
));
```

`shellPropsForDeferred()` ([ChatController.php:127](../../../Modules/Jana/Http/Controllers/ChatController.php:127)) monta business+user+conversas pro AppShellV2 seguindo o pattern `Inertia::defer()` (RUNBOOK-inertia-defer-pattern — props caras pulam execução em partial reload). A variante eager `shellPropsFor()` segue existindo em [:209](../../../Modules/Jana/Http/Controllers/ChatController.php:209).

**Validação:** `php artisan route:list --path=ia` retorna ≥4 linhas (index, conversas show, send/stream, sugestoes).

### 2. Page Inertia recebe Props tipados

```tsx
// resources/js/Pages/Jana/Chat.tsx:65
interface Props {
  businessNome: string
  businesses: BusinessOpt[]
  usuarioNome: string
  // ... shell props completos
  conversa: ConversaBackend
  mensagens: MensagemBackend[]
  sugestoesPendentes?: Sugestao[]
}
```

### 3. Persistent Layout com AppShellV2 + JanaAreaHeader + master/detail interno

```tsx
// resources/js/Pages/Jana/Chat.tsx:245
return (
  <AppShellV2
    title="Jana · Chat"
    business={{ nome: businessNome, opcoes: businesses }}
    user={{ nome, nomeCurto, email, cargo, iniciais }}
    conversas={conversas}
    conversaFoco={conversaFoco}        // alimenta ThreadHeader + breadcrumb
    activeConvId={String(conversa.id)}
    onSelectConv={selectConv}          // router.get pra trocar de conversa
  >
    <Head title="Jana · Chat" />
    <JanaAreaHeader active="chat" />   {/* header sticky tabs Dashboard | Chat (Wagner 2026-05-18) */}
    <div className="copiloto-chat-layout">   {/* grid 280px + 1fr — cockpit.css:1423 */}
      <ConvSidePanel fixadas={...} recentes={...} activeConvId={...} onSelectConv={selectConv} />
      <div className="copiloto-chat-thread">
        <ThreadHeader conv={conversaFoco} />
        <JanaAssistantUiChat conversaId={conversa.id} mensagensIniciais={mensagens}
                             belowThread={/* grid de PropostaCards quando há sugestões */} />
      </div>
    </div>
  </AppShellV2>
)
```

⚠️ Não definir `Chat.layout = ...` — esta tela passa AppShellV2 inline (atípico) porque precisa controlar `conversaFoco` dinamicamente. Outras telas (Dashboard) usam `Page.layout = (page) => <AppShellV2>{page}</AppShellV2>`.

**Mudança vs versão 2026-05:** a lista de conversas saiu da SidebarChat do shell (UI-0011 sidebar single-pane) e virou o componente local `ConvSidePanel` ([Chat.tsx:330](../../../resources/js/Pages/Jana/Chat.tsx:330)) — master/detail interno da própria Page. Abaixo de 1024px a lista some e a troca de conversa é via breadcrumb dropdown ([cockpit.css:1447](../../../resources/css/cockpit.css)).

### 4. Adaptar mensagens backend → Cockpit Mensagem

Backend usa `role: user|assistant|system` + `content`. Cockpit usa `autor: me|them` + `texto` + `whoAvatar`. Adaptador em [Chat.tsx:101](../../../resources/js/Pages/Jana/Chat.tsx:101):

```tsx
function adaptarMensagem(m: MensagemBackend): CockpitMensagem {
  // role 'user' → autor 'me'
  // role 'assistant' | 'system' → autor 'them' com COPILOTO_AVATAR
  // calcula 'Hoje' vs data dd/mm
}
```

### 5. Renderizar PropostaCard quando há sugestões pendentes

O grid de propostas é injetado na thread via prop `belowThread` do `JanaAssistantUiChat` ([Chat.tsx:283](../../../resources/js/Pages/Jana/Chat.tsx:283)):

```tsx
belowThread={
  sugestoesPendentes.length > 0 ? (
    <div className="px-5 pb-3 space-y-2">
      <p className="text-xs font-medium uppercase tracking-wide" style={{ color: 'var(--text-mute)' }}>
        Propostas de metas
      </p>
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        {sugestoesPendentes.map((s) => <PropostaCard key={s.id} sugestao={s} />)}
      </div>
    </div>
  ) : null
}
```

### 6. PropostaCard com Badge dificuldade + CTAs

```tsx
function PropostaCard({ sugestao }: { sugestao: Sugestao }) {
  const dif = DIFICULDADE_CONFIG[p.dificuldade] // facil/realista/ambicioso
  return (
    <Card className="flex flex-col gap-2 bg-card">
      <CardHeader>
        <CardTitle>{p.nome}</CardTitle>
        <Badge>{p.metrica}</Badge> <Badge>{p.periodo}</Badge>
        <Badge variant="secondary">{formatCurrency(p.valor_alvo)}</Badge>
      </CardHeader>
      <CardContent>{p.racional}</CardContent>
      <CardFooter>
        <Button onClick={escolher}>Escolher esta meta</Button>
        <Button variant="outline" onClick={rejeitar}>Rejeitar</Button>
      </CardFooter>
    </Card>
  )
}
```

`escolher()` → `POST /ia/sugestoes/{id}/escolher` cria Meta no business.
`rejeitar()` → `POST /ia/sugestoes/{id}/rejeitar` marca payload como rejeitado.

### 7. Build local + smoke

```bash
npm run build:inertia
grep -i "Pages/Jana/Chat" public/build-inertia/manifest.json
# Esperado: 1 linha com hash do bundle
```

## 4. Tokens CSS

| Token / classe | Onde aplica nesta tela | Origem |
|---|---|---|
| `--bg`, `--surface` | Fundo viewport / Cards | Shell tokens (UI-0008) |
| `--accent`, `--accent-soft` | Hover dropdown breadcrumb, links, Badge | Shell tokens (Tweaks accent-hue runtime) |
| `--bubble-me` | Bolha do usuário (lib `assistant-ui` deve respeitar) | Shell tokens |
| `--bubble-them`, `--bubble-them-fg` | Bolha da Jana | Shell tokens |
| `--text`, `--text-mute`, `--text-dim` | Texto primário/secundário/dim | Shell tokens |
| `--shadow-pop` | Dropdown breadcrumb sombra | Shell tokens (Sprint A 2026-05-05) |
| `bg-emerald-100`, `bg-amber-100`, `bg-rose-100` (+ dark variants) | Badge dificuldade Fácil/Realista/Ambicioso | **Exceção R-DS-002** — status fixos |
| `bg-card` (shadcn) | Card da PropostaCard | Mapeia pra `var(--card)` semântico — reage a dark/light |
| `text-muted-foreground` | Texto secundário shadcn | Reage a tema |

**Coluna direita (Apps Vinculados):** renderizada pelo `AppShellV2` mas vazia neste contexto (Jana não vincula OS/cliente externamente). Toggle no topbar colapsa pra 0px (`data-linked="off"`).

## 5. Estados visuais

| Estado | Trigger | Implementação atual | Nota |
|---|---|---|---|
| `default` | — | shadcn Card + tokens cockpit | OK |
| `hover` | breadcrumb dropdown / row conversa | `bg-accent-soft` (Sprint A) / `--sb-hover` na sidebar | Refinado 2026-05-05 |
| `focus` | tab/click | shadcn Button focus-visible ring | OK |
| `loading` | mensagem sendo enviada | `assistant-ui` mostra typing indicator | Lib externa |
| `empty` | sem mensagens em conversa nova | `assistant-ui` mostra placeholder próprio | OK |
| `error` | falha no `responderChat` | Backend captura via try/catch e injeta msg "Estou com dificuldades técnicas no momento" como assistant role | [ChatController.php:337](../../../Modules/Jana/Http/Controllers/ChatController.php:337) |
| `sugestao pendente` | `sugestoesPendentes.length > 0` | grid de PropostaCards entre thread e composer | OK |
| `dropdown breadcrumb aberto` | clicar em "Jana" no breadcrumb | dropdown 220px com 9 items + ícones Lucide + animação `bcModDdIn` 0.16s | Refinado Sprint A |

## 6. Responsividade

Grid breakpoints declarados nas telas filhas (PropostaCard) + AppShellV2 collapsa LinkedApps:

| Largura | Comportamento |
|---|---|
| `<640px` | PropostaCards 1 coluna; LinkedApps drawer (futuro) |
| `≥640px sm:` | PropostaCards 2 colunas |
| `≥768px md:` | LinkedApps começa a aparecer (320px) |
| `≥1280px xl:` | PropostaCards 3 colunas; Cockpit 3 colunas full |
| `≥1536px 2xl:` | Mantém 3 colunas |

**Pegadinha:** abaixo de 1280px, AppShellV2 colapsa LinkedApps pra 0px (UI-0008 mitigação). Em monitor 1280px (Larissa, ROTA LIVRE) sobra espaço útil pro thread.

## 7. Atalhos

| Tecla | Ação | Escopo | Listener |
|---|---|---|---|
| `⌘K` / `Ctrl+K` | Busca global | Shell | AppShellV2 |
| `J` | — | — | — |
| `K` | — | — | — |
| `E` | — | — | — |
| `A` | — | — | — |
| `N` | Nova conversa | Botão da lista de conversas (`title="Nova conversa · ⌘N"`) | `ConvSidePanel` local ([Chat.tsx:369](../../../resources/js/Pages/Jana/Chat.tsx:369)) — a SidebarChat do shell foi removida (UI-0011) |
| `/` | — | — | — |
| `↵ (Enter)` | Enviar mensagem | Composer da `assistant-ui` | Lib externa |
| `Shift+↵` | Quebra de linha | Composer | Lib externa |

> **Atalhos J/K/E/A não se aplicam aqui** — esta tela não é master/detail interno (cada conversa muda a URL via `router.get`, sidebar Chat mostra a lista). Atalhos `↵` e `Shift+↵` vêm da lib `assistant-ui`.

## 8. Component contract

### Props da Page (Chat.tsx:65)

```tsx
interface Props {
  // Shell props (vindos do shellPropsFor() do controller)
  businessNome: string
  businesses: BusinessOpt[]
  usuarioNome: string
  usuarioNomeCurto: string
  usuarioEmail: string
  usuarioCargo: string
  usuarioIniciais: string
  conversas: {
    fixadas: ConversaResumo[]
    rotinas: Rotina[]
    recentes: ConversaResumo[]
  }
  // Específicos da Jana Chat
  conversa: ConversaBackend
  mensagens: MensagemBackend[]
  sugestoesPendentes?: Sugestao[]
}

interface MensagemBackend {
  id: number
  role: 'user' | 'assistant' | 'system'
  content: string
  created_at: string
  propostas?: Proposta[]  // futuras (Sprint 2 estrutura, ainda não populado)
}

interface Proposta {
  nome: string
  metrica: string
  valor_alvo: number
  periodo: string
  dificuldade: 'facil' | 'realista' | 'ambicioso'
  racional: string
  dependencias: string[]
}
```

### Componentes locais (definidos no próprio arquivo)

- `adaptarMensagem(m)` ([Chat.tsx:101](../../../resources/js/Pages/Jana/Chat.tsx:101)) — converte MensagemBackend → CockpitMensagem
- `PropostaCard({ sugestao })` ([Chat.tsx:138](../../../resources/js/Pages/Jana/Chat.tsx:138)) — card de proposta com Badge dificuldade + CTAs
- `ConvSidePanel` ([Chat.tsx:330](../../../resources/js/Pages/Jana/Chat.tsx:330)) — lista de conversas (fixadas/recentes) do master/detail interno, migrada da SidebarChat removida
- `formatCurrency(v)` — Intl.NumberFormat pt-BR
- `COPILOTO_AVATAR: AvatarRef` — gradiente 17, iniciais "CP" (nome é dívida cosmética pré-rename)

### Componentes shared usados

- [`@/Layouts/AppShellV2`](../../../resources/js/Layouts/AppShellV2.tsx) — Cockpit shell único
- [`@/Components/cockpit/Thread`](../../../resources/js/Components/cockpit/Thread.tsx) — `ThreadHeader` (já refinado pre-Sprint A)
- [`./_components/AssistantUiChat`](../../../resources/js/Pages/Jana/_components/AssistantUiChat.tsx) — wrapper da lib `@assistant-ui/react` v0.10 via `ExternalStoreRuntime` (Thread + Composer + Stop + Markdown + edit/regenerate) — **movido** de `Components/copiloto/` pra colocation em `Pages/Jana/_components/`
- [`./components/JanaAreaHeader`](../../../resources/js/Pages/Jana/components/JanaAreaHeader.tsx) — header sticky da área Jana com tabs Dashboard | Chat
- [`@/Components/ui/{button,card,badge}`](../../../resources/js/Components/ui/) — shadcn primitives
- `sonner` — toast library (CTA escolher/rejeitar)

### Ícones (lucide-react — R-DS-003)

`Check`, `CheckCheck`, `Hash`, `Info`, `MoreHorizontal`, `Paperclip`, `Phone`, `Search`, `Send`, `Smile` (no `Thread.tsx`); `MessageSquare` (FabJana).

## 9. DoD checklist

- [x] Tela vive dentro de `AppShellV2` (inline, não Persistent Layout — `conversaFoco` dinâmico requer)
- [x] Tokens shadcn semânticos + tokens cockpit (`var(--surface)`, `var(--text-mute)`)
- [x] Breadcrumb com módulo dropdown (7 items ativos via `Modules/Jana/Resources/menus/topnav.php`) refinado Sprint A
- [n/a] Atalhos J/K/E/A — não se aplica (tela não é master/detail interno)
- [x] Conversa ativa persistida no shell via `oimpresso.cockpit.conv` (LS)
- [x] Componentes shared reusados (AppShellV2, Thread, AssistantUiChat, shadcn ui/*)
- [x] PT-BR em todos os labels ("Propostas de metas", "Escolher esta meta", "Rejeitar", "Estou com dificuldades técnicas no momento")
- [x] Dark mode validado — `--bubble-them` em dark é `oklch(0.28 0.008 240)`; status fixos (emerald/amber/rose) iguais nos dois temas
- [x] Responsividade 320/640/768/1280/1536 — PropostaCards adaptam, LinkedApps colapsa
- [x] Estados cobertos: default + hover + loading (assistant-ui) + empty (lib) + error (try/catch backend) + sugestao pendente
- [x] Bundle Inertia: `npm run build:inertia` + `Pages/Jana/Chat` no manifest
- [x] Multi-tenant: Controller filtra por `session('user.business_id')` em `Conversa::where(...)` e `Sugestao::where('conversa_id', ...)`

## 10. Pegadinhas

- ✅ **`resize-none` hardcoded no `<ComposerPrimitive.Input>`** — usuário não conseguia redimensionar textarea. Fix: `resize-y` (vertical apenas). **Aplicado 2026-05-08** audit cockpit-runbook · **confirmado no código atual 2026-07-09** ([AssistantUiChat.tsx:272](../../../resources/js/Pages/Jana/_components/AssistantUiChat.tsx:272) tem `resize-y`).

- ✅ **`max-h-40` no textarea (160px fixo)** — crescia 6 linhas e parava. Fix: `max-h-[40vh]`. **Aplicado 2026-05-08 · confirmado 2026-07-09** (mesma linha :272).

- ✅ **`mx-auto max-w-3xl` no `<ComposerPrimitive.Root>`** — "ChatGPT-style" centralizado desalinhava com bubbles. Fix: remover, composer ocupa largura útil do parent. **Aplicado 2026-05-08 · confirmado 2026-07-09** ([AssistantUiChat.tsx:267](../../../resources/js/Pages/Jana/_components/AssistantUiChat.tsx:267) sem `mx-auto max-w-3xl`).

- ✅ **`.copiloto-chat-layout { height: 100% }` em flex-col parent** — deixava ~400px de espaço "morto" no fim da tela. Fix: `flex: 1; min-height: 0`. **Aplicado 2026-05-08 · confirmado 2026-07-09** — hoje o bloco é `display: grid; grid-template-columns: 280px 1fr; flex: 1; min-height: 0` ([cockpit.css:1423](../../../resources/css/cockpit.css)).

- ❌ **`<a className="sb-action">` no `ConvSidePanel`** (hoje componente local — [Chat.tsx:402](../../../resources/js/Pages/Jana/Chat.tsx:402), `href="/tarefas"`) em vez de `<Link>` Inertia — hard nav perde `preserveScroll/preserveState`. Sintoma: clicar em "Tarefas" recarrega tela inteira. Fix: `import { Link } from '@inertiajs/react'`. **AINDA pendente em 2026-07-09** (o `<a href="/ia/conversas/nova">` em :369 é caso distinto — cria registro no backend, hard nav aceitável).

- ❌ **Lib externa `@assistant-ui/react`** controla rendering de Thread + Composer — overrides de tema/cor exigem props específicos da lib (não Tailwind direto). Pra customizar bolhas: configurar `theme={theme}` na `AssistantRuntimeProvider` ou substituir lib (esforço alto).
- ❌ **Não usar `Chat.layout = ...` Persistent Layout aqui** — `conversaFoco` muda quando user troca de conversa, precisa re-renderizar AppShellV2. Demais telas (Dashboard) podem usar Persistent.
- ❌ **`adaptarMensagem` recalcula 'Hoje' vs data** com `new Date()` — risco de timezone implícito. Ainda OK porque `created_at` Eloquent vem em UTC ISO; mas se backend mudar formato, quebra.
- ❌ **`sugestoesPendentes` não filtrado por `business_id`** no front (Page assume Controller já filtrou — o filtro real é por `conversa_id` da conversa já scopada, em [`buildSugestoesPendentesPayload()` ChatController.php:114](../../../Modules/Jana/Http/Controllers/ChatController.php:114)). NUNCA mostrar sugestões cross-tenant.
- ❌ **`router.post` em `escolher`/`rejeitar`** com `preserveScroll + preserveState` — sem `only:[]` Inertia recarrega TODA a página. Considerar `only:['sugestoesPendentes']` pra economizar.
- ❌ **Dropdown breadcrumb pré-Sprint A** (cockpit.css:149-164) tinha `min-width:180px`, padding pequeno, shadow fraca, sem ícones, sem animação. Wagner 2026-05-05 reclamou "tá feia" → fix aplicado.
- ✅ **Tema light** (UI-0009) — Sprint A não força tema; aplicado via `users.ui_theme = 'light'` no DB ou ThemeToggle do shell. Tela respeita ambos os temas após Sprint A.

Pegadinhas genéricas em [`.claude/skills/cockpit-runbook/GOTCHAS.md`](../../../.claude/skills/cockpit-runbook/GOTCHAS.md).

## 11. ADR de origem

- [ADR 0026 — Posicionamento ERP Gráfico com IA](../../decisions/0026-posicionamento-erp-grafico-com-ia.md) — por que existe Jana IA no ERP
- [ADR 0031 — MemoriaContrato + Mem0 default](../../decisions/0031-memoriacontrato-mem0-default.md) — base da memória que alimenta respostas
- [ADR 0035 — Stack AI canônica](../../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md) — laravel/ai SDK (substitui adapters anteriores)
- [ADR 0036 — Replanejamento Meilisearch-first](../../decisions/0036-replanejamento-meilisearch-first.md) — driver de retrieval (afeta latência das respostas)
- [ADR 0039 — Chat Cockpit (3 colunas)](../../decisions/0039-ui-chat-cockpit-padrao.md) — layout-mãe
- [_DS UI-0008 — Cockpit layout-mãe ERP](../_DesignSystem/adr/ui/0008-cockpit-layout-mae-do-erp.md)
- [_DS UI-0023 — Sidebar preta (dark-fixo)](../_DesignSystem/adr/ui/0023-sidebar-dark-fixo-preto-definitivo-supersede-0019.md)
- [_DS UI-0010 — Zip Cowork canon visual](../_DesignSystem/adr/ui/0010-zip-cowork-2026-04-27-canon-visual.md) — referência canônica `chat.jsx`

**Stories cobertas:** US-COPI-001, US-COPI-002, US-COPI-003, US-COPI-MEM-007 ([SPEC.md](SPEC.md))
**Rules:** R-COPI-001 (Jana sempre responde em PT-BR contextualizado), R-COPI-MEM-005 (memória cross-conversa do business)
**Tests:** `AdapterResolverTest.php` e `BridgeMemoriaChatTest.php` — vivem sob `tests/Feature/Modules/` na subpasta `Copiloto/` (renomeada da antiga `Jana/`; nome legado, não há módulo `Copiloto`). Localize por `git grep -l AdapterResolverTest`.

## Sprint A — fix visual aplicado 2026-05-05

Após Wagner reclamar "tela tá feia" em screenshot da prod:

| Item | Fix | Arquivo |
|---|---|---|
| Dropdown breadcrumb visual ruim | min-width 180→220, padding 4→6, shadow fraca → `var(--shadow-pop)` + 1px outline, animação `bcModDdIn` 0.16s, hover com `--accent-soft` | [resources/css/cockpit.css:149-171](../../../resources/css/cockpit.css) |
| Dropdown sem ícones | Adicionado ícone Lucide (via `it.icon` do `topnav.php`) ao lado de cada label | [AppShellV2.tsx:61-117](../../../resources/js/Layouts/AppShellV2.tsx) |
| Tema dark (UI-0009 manda light) | NÃO mudado em código — passa por `users.ui_theme = 'light'` no DB ou ThemeToggle. Aplicar em sessão futura. | — |

Itens postergados pra Sprint B/C: Apps Vinculados específico Jana, ThreadHeader rico, sidebar accordion, IBM Plex Sans verificação.

## Audit cockpit-runbook 2026-05-08 — score 64/100 → fixes aplicados

Wagner reportou em sessão tarde: "barra lateral cortada no fim · não redimensiono a caixa de escrita ficou meio desalinhada · layout do fim da página contado". Audit Modo B confirmou visualmente + estaticamente:

| Finding | Severidade | Fix | Arquivo |
|---|---|---|---|
| Composer `resize-none` hardcoded | UX-CRITICAL | `resize-y max-h-[40vh]` | `AssistantUiChat.tsx:166` (path da época `Components/copiloto/`; hoje [Pages/Jana/_components/AssistantUiChat.tsx:272](../../../resources/js/Pages/Jana/_components/AssistantUiChat.tsx:272)) |
| Composer `mx-auto max-w-3xl` desalinha com bubbles | UX-CRITICAL | remover, deixar largura útil | `AssistantUiChat.tsx:161` (idem; hoje `:267`) |
| `.copiloto-chat-layout { height: 100% }` em flex-col | CRITICAL | `flex: 1; min-height: 0` | [cockpit.css:1476](../../../resources/css/cockpit.css) |
| ConvSidePanel `<a>` cru em vez de `<Link>` | INFO | postergado pra Sprint próximo | [Chat.tsx:309-323](../../../resources/js/Pages/Jana/Chat.tsx:309) |
| LinkedAppsPanel renderiza vazio | UX-WARN | popular cards via shell.linkedApps OU colapsar default | [AppShellV2.tsx:487](../../../resources/js/Layouts/AppShellV2.tsx:487) |

Score 64/100 → esperado ≥80/100 após smoke do PR de fixes.

---

**Última atualização:** 2026-07-09 — re-validação de frescor (radar doc-freshness-score #4031, score 38 → alvo saudável). Verificação **estática** contra `origin/main`: 4 refs quebradas corrigidas (AssistantUiChat movido pra `Pages/Jana/_components/`; `Modules/Jana/Routes/` → `Http/routes.php`; 2 testes movidos pra a subpasta `Copiloto/` sob `tests/Feature/Modules/`), URL canônica atualizada `/copiloto` → `/ia` (ADR 0180, cadeia 301), tabelas `copiloto_*` → `jana_*`, permissions `copiloto.*` → `jana.*`, line-refs do Controller/Page re-ancoradas, 4 pegadinhas confirmadas resolvidas no código (✅) e 1 confirmada ainda pendente (`<a className="sb-action">`). **Fluxo vivo NÃO exercitado em 2026-07** — receita de smoke funcional permanece a do audit 2026-05-08.

**2026-05-08** — audit cockpit-runbook Modo B + 3 fixes CRITICAL aplicados (composer resize+alignment, layout flex). Sprint A 2026-05-05 (dropdown breadcrumb visual).

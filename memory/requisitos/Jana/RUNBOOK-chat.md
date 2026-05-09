---
slug: jana-runbook-chat
title: "Jana — Runbook da tela Chat (/copiloto)"
type: runbook
module: Jana
status: active
date: 2026-05-08
---

# RUNBOOK — Chat da Jana (`/copiloto`)

> **Tipo:** runbook reproduzível
> **Refs:** [ADR 0026](../../decisions/0026-posicionamento-erp-grafico-com-ia.md), [ADR 0031](../../decisions/0031-memoriacontrato-mem0-default.md), [ADR 0035](../../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md), [ADR 0036](../../decisions/0036-replanejamento-meilisearch-first.md), [ADR 0039](../../decisions/0039-ui-chat-cockpit-padrao.md), [_DS UI-0008](../_DesignSystem/adr/ui/0008-cockpit-layout-mae-do-erp.md), [_DS UI-0010](../_DesignSystem/adr/ui/0010-zip-cowork-2026-04-27-canon-visual.md)
> **Validado:** tela em produção `https://oimpresso.com/copiloto`. Sprint A (visual fix) aplicada 2026-05-05.

> **🪶 Naming:** o assistente IA chama-se **Jana** (Wagner 2026-05-08, auto-mem `feedback_jana_naming_canonico`). Backend PHP já é Jana (`Modules/Jana/`); frontend e URLs `/copiloto/*` mantidos como dívida estrutural até PR de rename completo (commit `8f7a5138` Fase 3.7 PR-2 documenta a decisão pragmática). **Em texto novo (UI/docs/ADR/PR/commit) sempre Jana**.

Tela principal da Jana — chat conversacional com a assistente IA que cria/edita metas e responde perguntas de negócio em linguagem natural. Persona: dono operador (Larissa, ROTA LIVRE biz=4) abre `/copiloto` de manhã, conversa com a Jana sobre faturamento/metas/clientes, recebe propostas de metas em cards inline e escolhe/rejeita. Vive dentro do `AppShellV2` (Cockpit 3 colunas: sidebar 260 / main 1fr / Apps Vinculados 320). Renderização do thread+composer delegada à lib `assistant-ui` ([JanaAssistantUiChat](../../../resources/js/Components/copiloto/AssistantUiChat.tsx) — nome do component é dívida legacy, conteúdo já é Jana).

## Estado final esperado

| Verificação | Como conferir |
|---|---|
| Tela renderiza em `/copiloto` | Login com `copiloto.chat` → URL → ThreadHeader + thread + composer visíveis |
| AppShellV2 envolvendo | Inspetor: `<div class="cockpit">` ao redor; sidebar (light em `data-theme="light"`) + breadcrumb com módulo dropdown |
| Breadcrumb dropdown abre lista de telas Jana | Clicar em "Jana" no breadcrumb → dropdown 9 itens (Conversar/Dashboard/Metas/Alertas/Governança MCP/KB→/Qualidade IA/Team MCP→/Plataforma) |
| ThreadHeader com avatar+dot+actions | "Jana" como nome + sub "Assistente IA · Jana" + ícones Phone/Info/More à direita |
| Composer envia mensagem | Digite + ⏎ → mensagem persistida via [POST /copiloto/conversas/{id}/mensagens](../../../Modules/Jana/Http/Controllers/ChatController.php:185) |
| PropostaCard inline quando há sugestões | `sugestoesPendentes.length > 0` → grid 1/2/3 colunas com Badge dificuldade (Fácil/Realista/Ambicioso) + CTA Escolher/Rejeitar |
| Apps Vinculados (coluna direita) | LinkedAppsPanel renderiza vazio (Jana não vincula OS/cliente externamente — comportamento atual). Toggle no topbar colapsa pra 0px |

## 1. Objetivo

Centralizar a interação conversacional com a Jana IA — assistente que opera sobre o contexto de negócio do business em foco (faturamento bruto/líquido/caixa, metas ativas, clientes, OS recentes via `ContextoNegocio` ADR 0052) e propõe ações via cards inline (`PropostaCard`). Persona principal: dono operador, **não-técnico**, que conversa em linguagem natural ("quanto vendi?", "criar meta de faturamento mensal de R$ [redacted Tier 0]k"). Layout-mãe Cockpit 3 colunas. Persistência da conversa: tabela `copiloto_conversas` + `copiloto_mensagens` (multi-tenant por `business_id`). Thread+composer renderizados pela lib externa `assistant-ui` (suporta Markdown, code highlight, edit/regenerate). Custo: tokens reportados em `copiloto_mensagens.tokens_in/tokens_out` + telemetria OTel GenAI (ADR 0050).

## 2. Pré-condições

- [ ] Módulo `Jana` instalado em `/manage-modules`
- [ ] Permissão `copiloto.chat` atribuída ao role do usuário (≠ `copiloto.access` que cobre só leitura)
- [ ] Rotas registradas em [`Modules/Jana/Routes/`](../../../Modules/Jana/Routes/) — `/copiloto` (index), `/copiloto/conversas/{id}` (show), `/copiloto/conversas/{id}/mensagens` (send), `/copiloto/sugestoes/{id}/escolher` + `/rejeitar`
- [ ] Page Inertia em [`resources/js/Pages/Jana/Chat.tsx`](../../../resources/js/Pages/Jana/Chat.tsx) — módulo em **PascalCase**
- [ ] Skill irmã carregada: `copiloto-arch` (stack ADRs 0035-0053) — tela toca conceitos da Jana
- [ ] Skill irmã `multi-tenant-patterns` ativa — Controller filtra `business_id` em `Conversa::where(...)`
- [ ] AI driver ativo: `LaravelAiDriver` (ADR 0035) ou fallback `OpenAiDirectDriver`. Em dev: `COPILOTO_AI_DRY_RUN=true` retorna fixtures
- [ ] Meilisearch rodando (CT 100 ou local) pra retrieval de memória (`MemoriaContrato` ADR 0036)
- [ ] Seed: `php artisan module:seed Jana` popula 5 metas template + meta raiz

## 3. Passo-a-passo

### 1. Controller renderiza Inertia com shell + props específicos do Chat

```php
// Modules/Jana/Http/Controllers/ChatController.php:100
return Inertia::render('Jana/Chat', array_merge(
    $this->shellPropsFor($businessId, $conversas, $conversa),
    [
        'conversa'           => $conversa,
        'mensagens'          => $mensagens,
        'sugestoesPendentes' => $sugestoesPendentes,
    ]
));
```

`shellPropsFor()` ([ChatController.php:115](../../../Modules/Jana/Http/Controllers/ChatController.php:115)) monta business+user+conversas pro AppShellV2 — mesma estrutura usada por `@index`, `@show`, `@cockpit`.

**Validação:** `php artisan route:list --path=copiloto` retorna ≥4 linhas (index, conversas show, send, sugestoes).

### 2. Page Inertia recebe Props tipados

```tsx
// resources/js/Pages/Jana/Chat.tsx:63
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

### 3. Persistent Layout com AppShellV2 + conversaFoco

```tsx
return (
  <AppShellV2
    title="Jana · Chat"
    business={{ nome: businessNome, opcoes: businesses }}
    user={{ nome, nomeCurto, email, cargo, iniciais }}
    conversas={conversas}
    conversaFoco={conversaFoco}        // alimenta ThreadHeader + breadcrumb
    activeConvId={String(conversa.id)} // highlight na sidebar Chat
    onSelectConv={selectConv}          // router.get pra trocar de conversa
  >
    <Head title="Jana · Chat" />
    <ThreadHeader conv={conversaFoco} />
    <JanaAssistantUiChat /* ... */ />
  </AppShellV2>
)
```

⚠️ Não definir `Chat.layout = ...` — esta tela passa AppShellV2 inline (atípico) porque precisa controlar `conversaFoco` dinamicamente. Outras telas (Dashboard) usam `Page.layout = (page) => <AppShellV2>{page}</AppShellV2>`.

### 4. Adaptar mensagens backend → Cockpit Mensagem

Backend usa `role: user|assistant|system` + `content`. Cockpit usa `autor: me|them` + `texto` + `whoAvatar`. Adaptador em [Chat.tsx:99](../../../resources/js/Pages/Jana/Chat.tsx:99):

```tsx
function adaptarMensagem(m: MensagemBackend): CockpitMensagem {
  // role 'user' → autor 'me'
  // role 'assistant' | 'system' → autor 'them' com COPILOTO_AVATAR
  // calcula 'Hoje' vs data dd/mm
}
```

### 5. Renderizar PropostaCard quando há sugestões pendentes

```tsx
{sugestoesPendentes.length > 0 && (
  <div className="px-5 pb-3 space-y-2">
    <p className="text-xs font-medium uppercase tracking-wide" style={{ color: 'var(--text-mute)' }}>
      Propostas de metas
    </p>
    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
      {sugestoesPendentes.map((s) => <PropostaCard key={s.id} sugestao={s} />)}
    </div>
  </div>
)}
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

`escolher()` → `POST /copiloto/sugestoes/{id}/escolher` cria Meta no business.
`rejeitar()` → `POST /copiloto/sugestoes/{id}/rejeitar` marca payload como rejeitado.

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
| `error` | falha no `responderChat` | Backend captura via try/catch e injeta msg "Estou com dificuldades técnicas no momento" como assistant role | [ChatController.php:200](../../../Modules/Jana/Http/Controllers/ChatController.php:200) |
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
| `N` | Nova conversa | Atalho do Sidebar Chat | `SidebarChat` (já no shell) |
| `/` | — | — | — |
| `↵ (Enter)` | Enviar mensagem | Composer da `assistant-ui` | Lib externa |
| `Shift+↵` | Quebra de linha | Composer | Lib externa |

> **Atalhos J/K/E/A não se aplicam aqui** — esta tela não é master/detail interno (cada conversa muda a URL via `router.get`, sidebar Chat mostra a lista). Atalhos `↵` e `Shift+↵` vêm da lib `assistant-ui`.

## 8. Component contract

### Props da Page (Chat.tsx:63)

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

- `adaptarMensagem(m)` — converte MensagemBackend → CockpitMensagem
- `PropostaCard({ sugestao })` — card de proposta com Badge dificuldade + CTAs
- `formatCurrency(v)` — Intl.NumberFormat pt-BR
- `COPILOTO_AVATAR: AvatarRef` — gradiente 17, iniciais "CP"

### Componentes shared usados

- [`@/Layouts/AppShellV2`](../../../resources/js/Layouts/AppShellV2.tsx) — Cockpit shell único
- [`@/Components/cockpit/Thread`](../../../resources/js/Components/cockpit/Thread.tsx) — `ThreadHeader` (já refinado pre-Sprint A)
- [`@/Components/copiloto/AssistantUiChat`](../../../resources/js/Components/copiloto/AssistantUiChat.tsx) — wrapper da lib `assistant-ui` (Thread + Composer + Stop + Markdown + edit/regenerate)
- [`@/Components/ui/{button,card,badge}`](../../../resources/js/Components/ui/) — shadcn primitives
- `sonner` — toast library (CTA escolher/rejeitar)

### Ícones (lucide-react — R-DS-003)

`Check`, `CheckCheck`, `Hash`, `Info`, `MoreHorizontal`, `Paperclip`, `Phone`, `Search`, `Send`, `Smile` (no `Thread.tsx`); `MessageSquare` (FabJana).

## 9. DoD checklist

- [x] Tela vive dentro de `AppShellV2` (inline, não Persistent Layout — `conversaFoco` dinâmico requer)
- [x] Tokens shadcn semânticos + tokens cockpit (`var(--surface)`, `var(--text-mute)`)
- [x] Breadcrumb com módulo dropdown (9 items via `topnav.php`) refinado Sprint A
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

- ❌ **`resize-none` hardcoded no `<ComposerPrimitive.Input>`** ([AssistantUiChat.tsx:166](../../../resources/js/Components/copiloto/AssistantUiChat.tsx:166)) — usuário não consegue redimensionar textarea. Sintoma: desconforto quando prompt é multi-parágrafo. Fix: `resize-y` (vertical apenas; horizontal quebraria flex layout). **Aplicado 2026-05-08** audit cockpit-runbook.

- ❌ **`max-h-40` no textarea (160px fixo)** — cresce 6 linhas e para. Sintoma: textarea congela com scroll interno minúsculo. Fix: `max-h-[40vh]` (40% viewport, escala com monitor). **Aplicado 2026-05-08**.

- ❌ **`mx-auto max-w-3xl` no `<ComposerPrimitive.Root>`** ([AssistantUiChat.tsx:161](../../../resources/js/Components/copiloto/AssistantUiChat.tsx:161)) — "ChatGPT-style" centralizado fica desalinhado com bubbles `max-w-[80%]`. Em monitor 1280px com chat-convs 280px + sb shell 260px, thread útil ~740px; composer com `max-w-3xl=768px` flutua mal centralizado. Fix: remover `mx-auto max-w-3xl`, deixar composer ocupar largura útil do parent. **Aplicado 2026-05-08**.

- ❌ **`.copiloto-chat-layout { height: 100% }` em flex-col parent** ([cockpit.css:1476](../../../resources/css/cockpit.css)) — dentro de `.main-body { display: flex; flex-direction: column; overflow-y: auto }`, `height: 100%` resolve mal e deixa ~400px de espaço vazio "morto" no fim da tela. Fix: `flex: 1; min-height: 0` no children direto de flex-col garante stretch correto. **Aplicado 2026-05-08**.

- ❌ **`<a className="sb-action">` em ConvSidePanel.tsx:309-323** em vez de `<Link>` Inertia — hard nav perde `preserveScroll/preserveState` (auto-mem `preference_cache_estado_preservado`). Sintoma: clicar em "Tarefas"/"Despachos" recarrega tela inteira. Fix: `import { Link } from '@inertiajs/react'`. **Pendente** — listada pra próximo Sprint.

- ❌ **Lib externa `assistant-ui`** controla rendering de Thread + Composer — overrides de tema/cor exigem props específicos da lib (não Tailwind direto). Pra customizar bolhas: configurar `theme={theme}` na `AssistantRuntimeProvider` ou substituir lib (esforço alto).
- ❌ **Não usar `Chat.layout = ...` Persistent Layout aqui** — `conversaFoco` muda quando user troca de conversa, precisa re-renderizar AppShellV2. Demais telas (Dashboard) podem usar Persistent.
- ❌ **`adaptarMensagem` recalcula 'Hoje' vs data** com `new Date()` — risco de timezone implícito (auto-mem `feedback_format_now_local_e_default_datetime`). Ainda OK porque `created_at` Eloquent vem em UTC ISO; mas se backend mudar formato, quebra.
- ❌ **`sugestoesPendentes` não filtrado por `business_id`** no front (Page assume Controller já filtrou — confirmar em [ChatController.php:92](../../../Modules/Jana/Http/Controllers/ChatController.php:92)). NUNCA mostrar sugestões cross-tenant.
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
- [_DS UI-0009 — Sidebar light padrão](../_DesignSystem/adr/ui/0009-cockpit-sidebar-light-padrao.md)
- [_DS UI-0010 — Zip Cowork canon visual](../_DesignSystem/adr/ui/0010-zip-cowork-2026-04-27-canon-visual.md) — referência canônica `chat.jsx`

**Stories cobertas:** US-COPI-001, US-COPI-002, US-COPI-003, US-COPI-MEM-007 ([SPEC.md](SPEC.md))
**Rules:** R-COPI-001 (Jana sempre responde em PT-BR contextualizado), R-COPI-MEM-005 (memória cross-conversa do business)
**Tests:** [AdapterResolverTest](../../../tests/Feature/Modules/Jana/AdapterResolverTest.php), [BridgeMemoriaChatTest](../../../tests/Feature/Modules/Jana/BridgeMemoriaChatTest.php)

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
| Composer `resize-none` hardcoded | UX-CRITICAL | `resize-y max-h-[40vh]` | [AssistantUiChat.tsx:166](../../../resources/js/Components/copiloto/AssistantUiChat.tsx:166) |
| Composer `mx-auto max-w-3xl` desalinha com bubbles | UX-CRITICAL | remover, deixar largura útil | [AssistantUiChat.tsx:161](../../../resources/js/Components/copiloto/AssistantUiChat.tsx:161) |
| `.copiloto-chat-layout { height: 100% }` em flex-col | CRITICAL | `flex: 1; min-height: 0` | [cockpit.css:1476](../../../resources/css/cockpit.css) |
| ConvSidePanel `<a>` cru em vez de `<Link>` | INFO | postergado pra Sprint próximo | [Chat.tsx:309-323](../../../resources/js/Pages/Jana/Chat.tsx:309) |
| LinkedAppsPanel renderiza vazio | UX-WARN | popular cards via shell.linkedApps OU colapsar default | [AppShellV2.tsx:487](../../../resources/js/Layouts/AppShellV2.tsx:487) |

Score 64/100 → esperado ≥80/100 após smoke do PR de fixes.

---

**Última atualização:** 2026-05-08 — audit cockpit-runbook Modo B + 3 fixes CRITICAL aplicados (composer resize+alignment, layout flex). Sprint A 2026-05-05 (dropdown breadcrumb visual).

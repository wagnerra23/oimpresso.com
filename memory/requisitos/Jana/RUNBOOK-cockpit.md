---
slug: jana-runbook-cockpit
title: "Jana — Runbook da tela Cockpit (MVP em validação · supersede em curso V2)"
type: runbook
module: Jana
status: active-superseded-by-v2
date: 2026-05-09
last_revised: 2026-05-15
---

# RUNBOOK — Cockpit (Jana — MVP em validação · supersede em curso V2)

> **⚠️ AVISO 2026-05-15 — supersede em curso (V2 Analista IA):**
>
> O `Cockpit.tsx` documentado neste RUNBOOK (138 lin · MVP-piloto-em-validacao) é a IMPLEMENTAÇÃO ATUAL e ainda LIVE em `/copiloto/cockpit`. Mas é o **anti-pattern WhatsApp-style** identificado no [`COWORK_NOTES.amendment-jana-chat-block-renderer.md`](../../../prototipo-ui/COWORK_NOTES.amendment-jana-chat-block-renderer.md) (tabs `Todos/OS/Equipe/Clientes` · `setTimeout(reply, 2400)` · resposta humana literal *"Recebido, vou verificar e te respondo já já 👍"*).
>
> **Charter V2 já existe em [`resources/js/Pages/Jana/Cockpit.charter.md`](../../../resources/js/Pages/Jana/Cockpit.charter.md)** — define destino "Cockpit do Analista IA" (brief diário + KPIs + análises + ações HITL · aba IA single-thread com 4 kinds tipados). Status `spec-ahead-of-impl`.
>
> **Fonte canônica visual V2:** [`prototipo-ui/_cowork-export-2026-05-15/chat-jana.jsx`](../../../prototipo-ui/_cowork-export-2026-05-15/chat-jana.jsx) (491 lin) + [chat-jana.css](../../../prototipo-ui/_cowork-export-2026-05-15/chat-jana.css) (645 lin). F1.5 score interim **78/100** ([CRITIQUE](../../../prototipo-ui/_cowork-export-2026-05-15/CRITIQUE-chat-jana-vs-amendment.md)).
>
> **Quando F3 entrar (Cowork V2.1 entrega 8 refinos · Wagner aprova screenshot):**
>
> - Este RUNBOOK passa pra `status: historical`
> - Novo `RUNBOOK-cockpit-v2.md` é criado (ou esse re-escrito) com §3 Passo-a-passo + §8 Component contract + §10 Pegadinhas reescritos pra V2
> - `Cockpit.tsx` é substituído in-place (charter `supersedes_in_place`)
> - `Dashboard.tsx` folda como tab `dashboard` na F5 do roadmap V2 (charter `absorbs_when_live`)
>
> Até lá, este RUNBOOK continua válido como descrição do que está EM PROD HOJE. As §1-§11 abaixo cobrem a implementação atual sem alteração.

---

> **Tipo:** runbook reproduzível
> **Refs:** [ADR 0026](../../decisions/0026-posicionamento-erp-grafico-com-ia.md), [ADR 0035](../../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md), [ADR 0039](../../decisions/0039-ui-chat-cockpit-padrao.md), [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md), **[charter V2 vivo](../../../resources/js/Pages/Jana/Cockpit.charter.md)**, **[CRITIQUE V2](../../../prototipo-ui/_cowork-export-2026-05-15/CRITIQUE-chat-jana-vs-amendment.md)**
> **Status:** **MVP em validação · supersede em curso V2** — rota PARALELA `/copiloto/cockpit` (não substitui `Chat.tsx` em `/copiloto`). Mock data hoje; será substituído in-place pelo Cockpit V2 Analista IA quando F1.5 ≥80 + screenshot Wagner aprovados.
> **Ref protótipo:** Cowork "Oimpresso ERP Comunicação Visual"

Tela do padrão "Chat Cockpit" canônico ([ADR 0039](../../decisions/0039-ui-chat-cockpit-padrao.md)) — layout-mãe do ERP. Sidebar de conversas (fixadas / rotinas / recentes), thread central com header + tabs + contexto + mensagens + composer, e (via `AppShellV2`) coluna direita de Apps Vinculados. **Hoje a thread é simulada client-side** com `setTimeout` produzindo "typing" mock — o ChatController.cockpit() devolve mocks ricos pra Wagner aprovar a UX antes de plugar `responderChatStream` real (já implementado em `/copiloto/conversas/{id}/mensagens/stream`).

## Estado final esperado

| Verificação | Como conferir |
|---|---|
| Tela renderiza em `/copiloto/cockpit` | Login com `copiloto.access` → URL → `<AppShellV2>` envolvendo + sidebar + thread |
| Coexiste com `/copiloto` (Chat.tsx) | Abrir as duas em abas separadas — não há redirect, são páginas diferentes |
| Sidebar mostra 3 grupos mock | "Fixadas" (2), "Rotinas" (4), "Recentes" (4) — `conversas` prop |
| Thread foco: Adesivos Recortados — TechPro | `conversaFoco.id === 'c2'` no `usePage().props` |
| Composer envia → bolha "me" + typing 600ms + reply 2400ms | `handleSend()` simula resposta com `setTimeout` |
| Persistência local da aba ativa + conversa | `localStorage.oimpresso.cockpit.chatTab` e `LS.CONV` (chaves em `Components/cockpit/shared.ts`) |
| Header da aba do navegador | "Jana · Cockpit" (vem da prop `title` do `AppShellV2`) |

## 1. Objetivo

MVP em validação do padrão "Chat Cockpit" (ADR 0039) — rota paralela ao `/copiloto` clássico pra Wagner comparar UX nova vs atual lado a lado, sem risco. Renderiza:

- Sidebar de conversas (3 grupos: fixadas, rotinas, recentes) — vem do mock `$mockConversas` no Controller
- Thread central com `ThreadHeader` + `ChatTabs` (Todos/OS/Equipe/Clientes) + `ThreadContext` (cliente, OS, financeiro, histórico, anexos) + `Thread` (mensagens) + `Composer`
- Resposta otimista local (typing 600ms, reply 2400ms) — placeholder até plugar streaming SSE real (já existe em `ChatController.sendStream`)

Persona: dono operador (persona Larissa, ROTA LIVRE biz=4) usando o ERP como WhatsApp do dia-a-dia. Multi-tenant via `session('user.business_id')` no Controller; superadmin/`user_oimpresso` enxerga até 50 businesses no `CompanyPicker` (`AppShellV2`).

## 2. Pré-condições

- [ ] Módulo `Jana` instalado em `/manage-modules` (ADR 0024 — botão Install funcional)
- [ ] Permissão `copiloto.access` atribuída ao role do usuário
- [ ] Rota `Route::get('/cockpit', 'ChatController@cockpit')->name('jana.cockpit')` em [`Modules/Jana/Http/routes.php:30`](../../../Modules/Jana/Http/routes.php) — dentro do prefix `/copiloto`
- [ ] Page Inertia em [`resources/js/Pages/Jana/Cockpit.tsx`](../../../resources/js/Pages/Jana/Cockpit.tsx) — módulo em **PascalCase** (`Jana`, não `Copiloto`)
- [ ] Componentes shared do Cockpit existem: [`@/Components/cockpit/Thread`](../../../resources/js/Components/cockpit/Thread.tsx) e `@/Components/cockpit/shared`
- [ ] Skill irmã carregada: `jana-arch` (stack ADRs 0035-0053)
- [ ] Skill irmã `multi-tenant-patterns` — Controller filtra `Conversa::where('business_id', $businessId)`
- [ ] **NÃO** rodar artisan `module:seed Jana` esperando dados reais — Cockpit hoje é 100% mock; o seed popula `jana_metas` e não conversas

## 3. Passo-a-passo

### 1. Controller monta dados (mock + companies reais) e renderiza Inertia

```php
// Modules/Jana/Http/Controllers/ChatController.php (linha ~398)
public function cockpit(Request $request)
{
    $businessId = $request->session()->get('user.business_id');
    $userId     = auth()->id();
    $user       = auth()->user();
    $isSuper    = $user && ($user->user_type === 'superadmin' || $user->user_type === 'user_oimpresso');

    // CompanyPicker: superadmin → todas (limit 50); resto → só a sua
    $businessesDisponiveis = $isSuper
        ? \App\Business::orderBy('name')->limit(50)->get(['id', 'name'])
        : \App\Business::where('id', $businessId)->get(['id', 'name']);

    $businesses = $businessesDisponiveis->map(fn ($b) => [
        'id'       => $b->id,
        'nome'     => $b->name,
        'iniciais' => $this->iniciais($b->name),
        'ativa'    => $b->id === (int) $businessId,
    ])->values();

    // Conversa real ativa (se houver) — só pra ter um ID válido pra futuro plug
    $conversaAtiva = Conversa::where('user_id', $userId)
        ->where('business_id', $businessId)
        ->where('status', 'ativa')
        ->latest('iniciada_em')
        ->first(['id', 'titulo']);

    // Mock conversas (sidebar 3 grupos) e conversaFoco rica (thread central)
    $mockConversas = [ /* fixadas, rotinas, recentes — ver código */ ];
    $conversaFoco  = [ /* c2 TechPro com cliente+os+financeiro+historico+anexos+mensagens */ ];

    return Inertia::render('Jana/Cockpit', [
        'businessNome'        => session('business.name', 'Oimpresso Matriz'),
        'businesses'          => $businesses,
        'usuarioNome'         => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
        'usuarioNomeCurto'    => $user->first_name ?? 'Usuário',
        'usuarioEmail'        => $user->email ?? '',
        'usuarioCargo'        => $isSuper ? 'Administrador' : 'Usuário',
        'usuarioIniciais'     => $this->iniciais($userNome),
        'conversas'           => $mockConversas,
        'conversaFoco'        => $conversaFoco,
        'conversaAtivaRealId' => $conversaAtiva?->id, // injetado mas NÃO consumido pela Page hoje
    ]);
}
```

**Validação:** `php artisan route:list --name=jana.cockpit` retorna 1 linha apontando pra `ChatController@cockpit`.

### 2. Page Inertia recebe Props tipadas + define defaults com `localStorage`

```tsx
// resources/js/Pages/Jana/Cockpit.tsx
interface Props {
  businessNome: string;
  businesses: BusinessOpt[];
  usuarioNome: string;
  usuarioNomeCurto: string;
  usuarioEmail: string;
  usuarioCargo: string;
  usuarioIniciais: string;
  conversas: { fixadas: ConversaResumo[]; rotinas: Rotina[]; recentes: ConversaResumo[] };
  conversaFoco: ConversaFoco;
  conversaAtivaRealId: number | null;
}

const [chatTab, setChatTab] = useState<string>(() => {
  if (typeof window === 'undefined') return 'todos'; // SSR-safe
  return localStorage.getItem(LS.CHAT_TAB) || 'todos';
});
const [activeConvId, setActiveConvId] = useState<string>(() => {
  if (typeof window === 'undefined') return conversaFoco.id;
  return localStorage.getItem(LS.CONV) || conversaFoco.id;
});
```

### 3. Thread otimista — `handleSend` simula resposta com 2 `setTimeout`

```tsx
function handleSend(texto: string) {
  const novaMsg: Mensagem = {
    id: Date.now(),
    autor: 'me',
    texto,
    hora: new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }),
    lida: false,
    dia: 'Hoje',
  };
  setMensagensLocal((arr) => [...arr, novaMsg]);
  // Mock typing 600ms depois reply 2400ms (Fase 3: substituir por POST real ao stream SSE)
  setTimeout(() => setTyping(true), 600);
  setTimeout(() => {
    setTyping(false);
    const replyAvatar = conversaFoco.mensagens.find((m) => m.autor === 'them')?.whoAvatar;
    const replyNome   = conversaFoco.mensagens.find((m) => m.autor === 'them')?.whoNome;
    setMensagensLocal((arr) => [...arr, {
      id: Date.now() + 1, autor: 'them', whoAvatar: replyAvatar, whoNome: replyNome,
      texto: 'Recebido, vou verificar e te respondo já já 👍',
      hora: new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }),
      dia: 'Hoje',
    }]);
  }, 2400);
}
```

### 4. Persistência `localStorage` SEM `useEffect` (idempotente)

```tsx
// Persiste chatTab + activeConvId — escreve direto, idempotente
if (typeof window !== 'undefined') {
  localStorage.setItem(LS.CHAT_TAB, chatTab);
  localStorage.setItem(LS.CONV, activeConvId);
}
```

> Observação do código: o comentário diz "useEffect não necessario — escreve direto, idempotente". Tecnicamente **roda em todo render**, não só on-change — barato (string ≤ 16 bytes), mas é uma decisão consciente registrada inline.

### 5. Render envolve tudo no `AppShellV2` com `business`/`user`/`conversas` injetados

```tsx
return (
  <AppShellV2
    title="Jana · Cockpit"
    business={{ nome: businessNome, opcoes: businesses }}
    user={{ nome: usuarioNome, nomeCurto: usuarioNomeCurto, email: usuarioEmail, cargo: usuarioCargo, iniciais: usuarioIniciais }}
    conversas={conversas}
    conversaFoco={conversaFoco}
    activeConvId={activeConvId}
    onSelectConv={setActiveConvId}
  >
    <ThreadHeader conv={conversaFoco} />
    <ChatTabs active={chatTab} onChange={setChatTab} />
    <ThreadContext conv={conversaFoco} />
    <Thread mensagens={mensagensLocal} typing={typing}
            typingAvatar={conversaFoco.mensagens.find((m) => m.autor === 'them')?.whoAvatar} />
    <Composer onSend={handleSend} conv={conversaFoco} />
  </AppShellV2>
);
```

> Notar: **Cockpit NÃO usa o `Cockpit.layout = ...` Persistent Layout pattern** — o `<AppShellV2>` é renderizado direto no `return`. Isso é diferente do Dashboard/Custos/Qualidade.

### 6. Build local + smoke

```bash
npm run build:inertia
grep -i "Pages/Jana/Cockpit" public/build-inertia/manifest.json
# Esperado: 1 linha com hash do bundle
```

## 4. Tokens CSS

| Token / classe | Onde aplica nesta tela | Origem |
|---|---|---|
| `--sb-bg`, `--sb-bg-2` | Sidebar do `AppShellV2` (light por default) | _DS UI-0009 — escopados em `.cockpit` |
| `text-primary` | Botões/links do shell e do `Composer` | shadcn semântico (R-DS-002) |
| `text-muted-foreground` | Subtítulos sidebar, "última mensagem", labels | shadcn semântico |
| `text-foreground` | Texto principal das mensagens | shadcn semântico |
| `bg-card` | Background das mensagens "me" e "them" | shadcn semântico |
| `bg-muted/40` | Hover sutil em items de conversa | Tailwind utility |

**Coluna direita do Cockpit (LinkedApps):** renderizada pelo `AppShellV2` quando `conversaFoco.tipo === 'os'` — apps OS/CRM/Financeiro mostrados via `LinkedApps.tsx`. Tela não controla o painel diretamente.

> ⚠️ **`gradId` (avatares)**: o `conversaFoco.avatar.gradId = 7` referencia 1 dos N gradientes definidos em `Components/cockpit/shared.ts`. Não usar emerald/amber/rose hardcoded — esses gradientes são curados pra evitar conflito com tokens semânticos.

## 5. Estados visuais

| Estado | Trigger | Implementação atual | Pegadinha |
|---|---|---|---|
| `default` | — | `<AppShellV2>` + `<Thread>` shadcn | OK |
| `hover` | mouse-over item da sidebar | `hover:bg-muted/40` (no `Sidebar.tsx`) | OK |
| `focus` | tab/click | shadcn primitives herdam `focus-visible` | OK no `Composer`, **não testado** em `ChatTabs` |
| `typing` | `typing === true` após 600ms do envio | `<Thread typing typingAvatar={...} />` mostra "..." animado | Mock — desliga em 2400ms (Fase 3 = SSE real) |
| `loading inicial` | data fetching SSR | **❌ NÃO IMPLEMENTADO** — Inertia entrega tudo síncrono | Ver §10 |
| `empty` | `conversas.fixadas.length === 0` E `recentes.length === 0` | **❌ NÃO IMPLEMENTADO** — hoje sempre tem mock | Ver §10 |
| `error` | `Inertia.render` falha (500) | **❌ NÃO IMPLEMENTADO** localmente | Trata pelo error boundary global do shell |
| `disconnected` | conversaFoco.online === false | `ThreadHeader` exibe "offline" muted | Implementado na prop `online: false` |

## 6. Responsividade

`AppShellV2` controla a grid principal — Cockpit não declara breakpoints próprios. Comportamento herdado:

| Largura | Sidebar | Thread | LinkedApps |
|---|---|---|---|
| `<768px` (mobile) | Drawer (toggleável) | Full | Hidden |
| `768–1279px` (tablet) | Compact 56px | Flex | Collapsed 44px (UI-0008 mitigação) |
| `≥1280px` (desktop) | Full 280px | Flex | Full 320px |
| `≥1536px` (`2xl:`) | Full | Flex | Full |

**Pegadinha Larissa (ROTA LIVRE, monitor 1280px):** no breakpoint exato 1280px, LinkedApps volta a 320px e a thread fica com ~620px — a `ThreadContext` (cliente+OS+financeiro empilhados) pode estourar wrap em telefone longo (`+55 11 98712-3344`). Validar em monitor real antes de promover MVP a default.

## 7. Atalhos

| Tecla | Ação | Escopo | Listener |
|---|---|---|---|
| `⌘K` / `Ctrl+K` | Busca global | Shell | AppShellV2 |
| `Enter` | Enviar mensagem | Composer | `Components/cockpit/Thread.tsx` |
| `Shift+Enter` | Nova linha no Composer | Composer | `Components/cockpit/Thread.tsx` |
| `J` | — | — | — |
| `K` | — | — | — |
| `E` | — | — | — |
| `A` | — | — | — |
| `N` | — | — | — |
| `/` | — | — | — |

> **Cockpit MVP NÃO implementa J/K** pra navegar entre conversas da sidebar — Wagner pediu pra deixar pra depois (decisão consciente, evita estourar escopo do MVP). Quando promover a default e substituir Chat.tsx, considerar adicionar.

## 8. Component contract

### Props da Page

```tsx
interface Props {
  businessNome: string;                  // ex: "Oimpresso Matriz"
  businesses: BusinessOpt[];             // pra CompanyPicker (até 50 se super)
  usuarioNome: string;                   // "Wagner Rocha"
  usuarioNomeCurto: string;              // "Wagner"
  usuarioEmail: string;                  // "wagnerra@gmail.com"
  usuarioCargo: string;                  // "Administrador" | "Usuário"
  usuarioIniciais: string;               // "WR"
  conversas: {
    fixadas:  ConversaResumo[];          // até 2 hoje
    rotinas:  Rotina[];                  // até 4 hoje
    recentes: ConversaResumo[];          // até 4 hoje
  };
  conversaFoco: ConversaFoco;            // mock rico — TechPro c2
  conversaAtivaRealId: number | null;    // injetado MAS NÃO consumido pela Page hoje (futuro plug)
}

interface BusinessOpt   { id: number; nome: string; iniciais: string; ativa: boolean }
interface ConversaResumo{ id: string; titulo: string; unread?: number; origem?: string }
interface Rotina        { id: string; titulo: string; frequencia: string }
interface ConversaFoco  { id, titulo, tipo, online, avatar, cliente, os, financeiro, historico, anexos, mensagens }
interface Mensagem      { id: number; autor: 'me'|'them'; texto: string; hora: string; dia: string; lida?: boolean; whoAvatar?, whoNome? }
```

### Componentes shared usados

- [`@/Layouts/AppShellV2`](../../../resources/js/Layouts/AppShellV2.tsx) — shell-mãe (controla sidebar + LinkedApps)
- [`@/Components/cockpit/Thread`](../../../resources/js/Components/cockpit/Thread.tsx) — `ThreadHeader`, `ChatTabs`, `ThreadContext`, `Thread`, `Composer`
- [`@/Components/cockpit/shared`](../../../resources/js/Components/cockpit/shared.ts) — types + chaves `LS.*` localStorage

### Constantes localStorage

```ts
LS.CHAT_TAB = 'oimpresso.cockpit.chatTab'
LS.CONV     = 'oimpresso.cockpit.conv'
```

## 9. DoD checklist

- [x] Tela vive dentro de `AppShellV2` (renderizado direto no `return`, NÃO via `Page.layout = ...`)
- [x] Tokens shadcn semânticos (`text-primary`, `text-muted-foreground`, `bg-card`)
- [n/a] Coluna direita "Apps Vinculados" — controlada pelo `AppShellV2` automaticamente
- [n/a] Atalhos J/K/E/A — MVP NÃO implementa (decisão consciente)
- [x] Estado `localStorage oimpresso.cockpit.*` (chatTab + conv)
- [x] Componentes shared reusados (Thread cluster + AppShellV2)
- [x] PT-BR em todos os labels ("Cockpit", "Adesivos Recortados — TechPro", "Recebido, vou verificar...")
- [x] Multi-tenant: Controller filtra por `session('user.business_id')` + `Business::where('id', $businessId)` quando não-super
- [ ] **Backend real plugado** — hoje 100% mock (typing/reply via `setTimeout`); plug `responderChatStream` SSE da rota `jana.conversas.mensagens.stream`
- [ ] **Empty state** — sem conversa ativa hoje sempre cai no mock; precisa cobrir `conversas.fixadas.length === 0`
- [ ] **Loading inicial** — Inertia síncrono mascara latência; quando plugar SSE precisa de skeleton
- [x] Bundle Inertia: `npm run build:inertia` + `Pages/Jana/Cockpit` no manifest

## 10. Pegadinhas

- ❌ **MOCK 100% — sem backend real** — `conversas`, `conversaFoco`, typing 600ms e reply 2400ms são todos hardcoded no Controller + `setTimeout` no `handleSend`. Ao plugar produção: substituir `handleSend` por `router.post('/copiloto/conversas/{id}/mensagens/stream', { texto })` + parser SSE; substituir `$mockConversas` no Controller por `Conversa::where('business_id', $businessId)->...` (já existe como `$conversaAtiva`).
- ❌ **`conversaAtivaRealId` injetado mas NÃO consumido** — Controller envia, Page tem `conversaAtivaRealId: number | null` na interface, mas o componente `Cockpit({...})` faz **destructuring sem incluir** essa prop. Linha 47-57: `{businessNome, businesses, usuarioNome, usuarioNomeCurto, usuarioEmail, usuarioCargo, usuarioIniciais, conversas, conversaFoco}` — falta `conversaAtivaRealId`. TS NÃO erra (Inertia repassa props extra). Quando o backend for plugado, esse ID precisa ser usado no `setActiveConvId` default.
- ❌ **`localStorage.setItem` rodando em todo render** — bloco fora de `useEffect` (linha 75-79). Comentário inline diz "idempotente" — verdade, mas faz 2 writes/render. Em rerender intenso (typing animation) acaba escrevendo string igual N vezes/s. Preferível mover pra `useEffect([chatTab, activeConvId])`.
- ❌ **`new Date().toLocaleTimeString('pt-BR', ...)` no client** — formatação client-side; se Wagner abrir o Cockpit num servidor com clock errado vê hora errada. Backend já entrega `mensagens[].hora` formatada — usar a do servidor sempre que possível.
- ❌ **`conversaFoco.mensagens.find(m => m.autor === 'them')` chamado 3x no render** (linhas 96, 97, 133) — recompute em cada render do typing. Memoizar com `useMemo` ou extrair pra const local antes do JSX.
- ❌ **Reply hardcoded "Recebido, vou verificar e te respondo já já 👍"** com emoji literal — remover quando plugar SSE. Hoje confunde o cliente em demo: parece fake.
- ❌ **Sem error boundary local** — se `Components/cockpit/Thread` der `TypeError` (ex: `mensagens` vier `undefined` no plug real), tela inteira quebra. Adicionar `<ErrorBoundary>` ao redor do cluster Thread quando promover a default.
- ❌ **Status do header no `// @memcofre` é `mvp-piloto-em-validacao`** — antes de remover o `Chat.tsx` clássico, atualizar pra `production` + ADR de cutover (ADR 0094 §5 SoC brutal).
- ⚠️ **`businesses` limit 50 hardcoded** (Controller linha 409) — se um superadmin tem mais de 50 businesses ativas, o picker some os restantes silenciosamente. Pra Wagner hoje (~7 com vendas, 56 cadastradas) ainda está OK; quando o ERP escalar, virar paginação ou search-as-you-type.
- ⚠️ **`session('business.name', 'Oimpresso Matriz')`** com fallback hardcoded — se a `session('business')` (Eloquent Model — auto-mem) for null, usuário vê "Oimpresso Matriz" como header e pode confundir com nome real. Auto-mem `project_session_business_model` documenta esse comportamento.

Pegadinhas genéricas em [`.claude/skills/cockpit-runbook/GOTCHAS.md`](../../../.claude/skills/cockpit-runbook/GOTCHAS.md).

## 11. ADR de origem

- [ADR 0026 — Posicionamento ERP Gráfico com IA](../../decisions/0026-posicionamento-erp-grafico-com-ia.md) — motivação de produto (Jana IA como diferencial)
- [ADR 0035 — Stack AI canônica](../../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md) — laravel/ai SDK (futuro plug do `responderChatStream`)
- [ADR 0039 — UI Chat Cockpit (padrão)](../../decisions/0039-ui-chat-cockpit-padrao.md) — **layout-mãe** (esta tela é o MVP do padrão)
- [ADR 0094 — Constituição v2 (7 camadas + 8 princípios)](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — §5 SoC brutal (uma rota = um fim) + §7 transparência (rota paralela é honesto)

**Stories cobertas:** US-COPI-COCKPIT-001 (MVP em validação) — header do `.tsx`
**Rules:** padrão Chat Cockpit (3 colunas) — formalizado em [ADR 0039](../../decisions/0039-ui-chat-cockpit-padrao.md)
**Tests:** sem suite Pest dedicada hoje — quando plugar backend, criar `tests/Feature/Modules/Jana/CockpitMockTest.php` validando shape do `conversaFoco`

**Refs V2 (supersede em curso):**
- [Cockpit.charter.md V2](../../../resources/js/Pages/Jana/Cockpit.charter.md) — destino arquitetural
- [CRITIQUE chat-jana vs amendment](../../../prototipo-ui/_cowork-export-2026-05-15/CRITIQUE-chat-jana-vs-amendment.md) — score F1.5 interim 78/100
- [`prototipo-ui/_cowork-export-2026-05-15/chat-jana.{jsx,css}`](../../../prototipo-ui/_cowork-export-2026-05-15/) — fonte canônica visual V2
- 7 Pest GUARDs spec: R-JANA-COCKPIT-001..007 (definidos no charter V2 §Métricas vivas)

---

**Última atualização:** 2026-05-15 — adicionado §AVISO supersede em curso V2 + refs charter/CRITIQUE/protótipo. §1-§11 (impl atual) inalteradas. Original 2026-05-09 preservado.

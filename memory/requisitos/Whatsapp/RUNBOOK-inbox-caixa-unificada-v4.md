# RUNBOOK — Caixa Unificada v4 (REDESIGN VISUAL only)

> **Tipo:** RUNBOOK MWART (ADR 0104 §F1 PLAN)
> **Status:** ⏳ aguardando sign-off Wagner
> **Escopo:** APENAS redesign frontend. Zero migrations, zero backend novo.
> **Refs:** ADR 0104 (MWART), ADR 0135 (omnichannel já live), ADR 0093 (Tier 0), ADR 0110 (Cockpit V2), handoff Claude Design 2026-05-15 (`inbox-page.jsx`)
> **Tela:** `resources/js/Pages/Atendimento/Inbox/Index.tsx` + `_components/`
> **Backend:** `Modules/Whatsapp/Http/Controllers/Admin/InboxController.php` — props shape PRESERVED, só adiciona 2 props derivadas

---

## 1. Mission

Reskin visual do Inbox `/atendimento/inbox` (já funcional desde ADR 0135 Fase 0) pra bater identidade da Caixa Unificada v4 do handoff. **Zero migration, zero novo backend, zero novo Controller.** Reaproveitar tudo que existe (channels, conversations, messages, tags, macros, slash command parser, Centrifugo, polling).

## 2. Goals

| ID | Goal | Métrica |
|---|---|---|
| G1 | Visual ≥85% identidade com `inbox-page.jsx` v4 | Wagner aprova screenshot F1.5 |
| G2 | Zero regressão funcional vs Inbox atual | Pest existente passa, smoke biz=1 verde |
| G3 | Centrifugo + polling fallback preservados | Same useEffect contracts |
| G4 | Cliente canary Martinho biz=164 sem report de bug | 7d canary |

## 3. Non-Goals (CORTADO desta passada — feedback Wagner 2026-05-15)

- ❌ **NÃO** criar migrations (`whatsapp_queues`, `queue_members`, etc)
- ❌ **NÃO** criar Service `QueueRouter` ou Job de roteamento automático
- ❌ **NÃO** criar `QueuesController` ou rotas `/atendimento/filas`
- ❌ **NÃO** criar permissions novas (`whatsapp.queues.manage`, `whatsapp.broadcast.send`)
- ❌ **NÃO** criar Drawer "Canais e contas" novo — `/atendimento/canais` já é a tela canônica
- ❌ **NÃO** criar Drawer "Broadcast" — fora do escopo redesign
- ❌ **NÃO** criar Drawer "Filas" config — UI de gestão de filas pra outra passada
- ❌ **NÃO** mexer em backend (Controller só ganha 2 props derivadas read-only)
- ❌ **NÃO** mexer em drivers (Baileys/Meta/Z-API)
- ❌ **NÃO** mexer no daemon CT 100

## 4. Filas — como aparecer SEM backend novo

Wagner aprovou 2 filas: **Comercial** e **Financeiro**.

### 4.1 Source: config static (não DB)

`config/whatsapp.php` ganha array:

```php
'queues' => [
    'comercial' => ['label' => 'Comercial',   'hue' => 220, 'sla' => '1h'],
    'financeiro'=> ['label' => 'Financeiro', 'hue' => 280, 'sla' => '4h'],
],
'default_queue' => 'comercial',
```

### 4.2 Mapping conversation → fila (deriva, não persiste)

`InboxController::index()` adiciona uma derivação read-only:

```php
$queues = config('whatsapp.queues');
$conversationsForUi = $conversationsForUi->map(function ($c) use ($queues) {
    // Heurística simples: tags['financeiro','cobranca'] → financeiro; senão comercial
    $tags = $c['tags'] ?? [];
    $slug = collect($tags)->contains(fn($t) => in_array($t['slug'] ?? null, ['financeiro','cobranca']))
        ? 'financeiro'
        : config('whatsapp.default_queue');
    $c['queue'] = ['slug' => $slug] + $queues[$slug];
    return $c;
});
```

Tags já vêm do backend (US-WA-063 — seed defaults inclui "Financeiro" + "Cobrança"). Não inventa schema novo.

### 4.3 Filtro UI

Coluna esquerda ganha pill toggle "Todas · Comercial · Financeiro" lendo do `queues` prop. Filtro client-side puro (não vai pro Controller — `conversations` já vem com `queue` derivado).

### 4.4 Mover conversa entre filas?

**Não nesta passada.** Visual no Contexto: chip read-only mostrando fila atual. Mover entre filas exigiria persistência (`assigned_queue` field ou similar) — fica pra próxima.

## 5. Diferenças existing vs design v4

### 5.1 Visual replace (reskin, mesma função)

| Existing | v4 design | Componente afetado |
|---|---|---|
| `ChannelSelector` dropdown topbar | Pílulas horizontais com glyph+contador | `Index.tsx` topo |
| Tabs verticais (all/unread/assigned/bot/resolved) | `<select>` inline coluna Conversas | `ConversationList.tsx` header |
| Avatar simples | Avatar + glyph canal sobreposto + barra colorida fila lateral | `ConversationList.tsx` item |
| Header thread denso | Avatar + nome + chip canal · conta + handle + botão Resolver | `ConversationThread.tsx` header |
| Composer básico | Toggle Resp/Nota + botões Templates `⌘T` / Macros `/` + slash autocomplete | `ConversationThread.tsx` composer |
| Sidebar contexto atual | Contexto v4 — Fila (read-only chip) + Assignee select + Canal·Conta + Tags + OS + Saldo + Histórico + Ações | `ConversationSidebar.tsx` |

### 5.2 Visual add (visíveis no v4 mas backend já existe)

| Item | Backend que reusa |
|---|---|
| Pill canal "em homologação" / preview banner | `Channel.status === 'pending'` ou `channel_health` field |
| Sub-filtro contas multi-instance (quando canal selecionado) | `availableChannels` prop já lista N channels do mesmo type |
| Internal note bubble (visual) | `Notes/SlashCommandParser` já interpreta `/note ...` → marca msg como internal |
| Slash autocomplete `/orc`, `/cobrar` | `MacrosController::list` já retorna catálogo |
| Status select (Abertas/Pendentes/Aguardando/Resolvidas) | Mapeia pros tabs existentes do Controller (alias compat) |

### 5.3 Visual cut (cortado nesta passada)

- ❌ Drawer "Canais e contas" — usa link pra `/atendimento/canais`
- ❌ Drawer "Filas" config
- ❌ Drawer "Broadcast"
- ❌ Botão "+ Nova conversa" novo — manter botão atual se existir

## 6. Props shape (Controller só ganha 2 derivadas)

```ts
interface Props {
  // ─── PRESERVED (não mexer) ────────────────────────
  conversations: Paginated<ListConversation>;  // cada item ganha .queue derivada
  thread: ThreadConversation | null;
  messages: Message[] | null;
  centrifugoConfig: CentrifugoConfig | null;
  availableChannels: AvailableChannel[];
  selectedChannelId: number | null;
  availableTags: ConvTag[];
  activeTagIds: number[];
  stats: { unread, assigned, bot, awaiting_human, archived };
  businessId: number;
  within24h, unlinked, mediaInbound24h, inboundAging, orderBy, tab, q;

  // ─── ADD (derivado, read-only, sem DB) ───────────
  queues: Record<string, { slug, label, hue, sla }>;  // de config/whatsapp.php
  defaultQueue: string;                                // 'comercial'
}

interface ListConversation {
  // ... fields existentes ...
  queue: { slug, label, hue, sla };  // derivado no Controller via heurística tag → fila
}
```

## 7. Componentes frontend

```
Atendimento/Inbox/Index.tsx                  (RESHAPE — ~280 → ~330 linhas)
├── _components/Atendimento/Inbox/
│   ├── ChannelPillsFilter.tsx              (NEW — substitui ChannelSelector dropdown)
│   ├── AccountSubFilter.tsx                (NEW — conditional render)
│   ├── QueuePillsFilter.tsx                (NEW — Todas/Comercial/Financeiro)
│   └── StatusSelectInline.tsx              (NEW — substitui tabs verticais)
└── _components/Whatsapp/ (RESHAPE existentes — preservar paths legacy)
    ├── ConversationList.tsx                (RESHAPE markup; preservar API)
    ├── ConversationListItem.tsx            (RESHAPE — glyph canal + barra fila)
    ├── ConversationThread.tsx              (RESHAPE header + composer)
    │   ├── ThreadHeader.tsx                (NEW — extrair pra simplificar)
    │   ├── PreviewBanner.tsx               (NEW — canal em homologação)
    │   ├── Composer.tsx                    (RESHAPE)
    │   │   ├── InternalNoteToggle.tsx      (NEW — toggle Resp/Nota)
    │   │   └── SlashMacroPopover.tsx       (NEW — autocomplete /)
    │   └── InternalNoteBubble.tsx          (NEW — render visual nota interna)
    └── ConversationSidebar.tsx             (RESHAPE — Contexto v4)
        ├── QueueChipReadonly.tsx           (NEW)
        └── ChannelAccountChip.tsx          (NEW — read-only)
```

12 sub-componentes touched/created. Maior parte é markup + Tailwind tokens.

## 8. Tokens visuais

Sem inventar paleta. Usar:

- **Hue canais/filas/operadores**: inline `style={{ background: `oklch(0.62 0.14 ${hue})` }}` (canon design)
- **Fontes**: IBM Plex Sans (já default AppShellV2)
- **Ícones**: `lucide-react` (não SVG inline). Glyph canal = letra "W" / "@" / "M" estilizada em chip 13px circular

## 9. LocalStorage keys novas

Adicionar em `Pages/Whatsapp/_components/helpers.ts`:

- `LS.INBOX_CHANNEL_FILTER` (id do canal selecionado)
- `LS.INBOX_QUEUE_FILTER` (`all`/`comercial`/`financeiro`)
- `LS.INBOX_STATUS_FILTER` (default `abertas`)

Preservar `LS.SIDEBAR_COLLAPSED` / `LS.LEFT_SIDEBAR_COLLAPSED` existentes.

## 10. Multi-tenant Tier 0 (preservar)

Nada novo a auditar — todos os filtros novos são client-side ou config static. `Channel`/`Conversation` Models já têm global scope (ADR 0135 + ADR 0093). `selectedChannelId` validation server-side já existe (`ensureChannelIdAccessOrAbort`).

## 11. Anti-hooks

- ❌ **NÃO** quebrar partial reload `only: [...]` — preservar lista exata do existing
- ❌ **NÃO** trocar Centrifugo channel name (`omnichannel:business:{id}`)
- ❌ **NÃO** remover polling fallback 5s (incident 2026-05-11)
- ❌ **NÃO** quebrar atalhos `J/K/E/A/?/` (já live)
- ❌ **NÃO** criar tabela `queues` nem `queue_members` (fora do escopo)
- ❌ **NÃO** alterar enum `conversations.status` legacy — `statusFilter` UI mapeia pros values existentes (alias compat)
- ❌ **NÃO** mover route names `atendimento.inbox.*` (URLs estáveis)
- ❌ **NÃO** mexer em backend além das 2 props derivadas

## 12. Plan: fases MWART

| Fase | Deliverable | Gate |
|---|---|---|
| **F1 PLAN** | Este RUNBOOK | ⏳ Wagner sign-off |
| **F2 BACKEND BASELINE** | Mínimo: ler `config/whatsapp.php` + adicionar 2 props derivadas no Controller. Pest existente continua verde. | `php artisan test --filter=Inbox` ✅ |
| **F3 FRONTEND INCREMENTAL** | 12 sub-componentes redesenhados. Gate visual F1.5 = screenshot comparativo Wagner aprova | Wagner aprova screenshot ≥85% match |
| **F4 QA** | Smoke biz=1 + a11y `aria-label` em botões só-ícone | Lighthouse a11y ≥90 |
| **F5 CUTOVER** | Deploy Hostinger → canary 7d Martinho biz=164 → monitor 30d | ZERO Martinho reportar bug |

F2 fica em ~30min (só append config + 2 props no Controller). F3 é o coração: 5-7h coding paralelizável.

## 13. Estimate (recalibrado ADR 0106)

- **F2 mínimo:** 30min IA-pair (1 arquivo `config/whatsapp.php` + edit `InboxController::index`)
- **F3:** 5-7h IA-pair (12 sub-componentes — paralelizável em 3 waves)
- **F4:** 1h smoke + a11y polish
- **F5:** 30min deploy + 7d canary Martinho (relógio do mundo real)

**Total IA-pair: ~7-9h** · **Wagner: ~3-5h review** · **Canary: 7d**

## 14. Riscos

| Risco | Mitigation |
|---|---|
| Visual divergir do handoff | Gate F1.5 obrigatório SCREENSHOT (não tabela) — skill `mwart-comparative` |
| Quebrar Centrifugo partial reload | Preservar `only: [...]` exato + Pest E2E partial reload |
| Slash autocomplete latência | Carregar macros 1x no mount + cache local |
| Martinho biz=164 ter regressão | Smoke biz=1 ANTES de canary biz=164 |

## 15. Sign-off checklist Wagner

- [ ] **Escopo redesign-only confirmado** — zero backend novo (só 2 props derivadas) — OK?
- [ ] **2 filas via config static** (`config/whatsapp.php`, sem migration) + heurística tag→fila no Controller — OK?
- [ ] **Sem drawer Canais / Broadcast / Filas-config** — OK (corta dessa passada)?
- [ ] **Move conversa entre filas = NÃO nesta passada** (read-only chip) — OK ou prefere que seja editável já?
- [ ] **F3 via coordenador-paralelo** 3 waves (1=filtros topo, 2=lista+items, 3=thread+composer+sidebar)?
- [ ] **F1.5 screenshot gate** obrigatório antes de F4 — OK?
- [ ] **Canary Martinho biz=164** confirmado (avisar 48h antes do cutover)?

Aguardando OK pra disparar F2 (que aqui é mínimo — 30min).

## 16. Refs

- ADR 0104 MWART · ADR 0135 omnichannel · ADR 0110 Cockpit V2 · ADR 0093 Tier 0
- US-WA-040 (ChannelSelector), US-WA-048 (macros), US-WA-063 (tags), US-WA-066 (polling), US-WA-069 (ACL canal)
- Handoff Claude Design `Oimpresso ERP - Chat.html` 2026-05-15 — `inbox-page.jsx` v4 (802 linhas)

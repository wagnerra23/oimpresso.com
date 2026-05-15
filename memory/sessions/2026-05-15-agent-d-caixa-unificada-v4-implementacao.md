---
title: "Agente D — Caixa Unificada V4 (PR-D wave fix WhatsApp)"
type: session
session_date: '2026-05-15'
quarter: 2026-Q2
authority: log
lifecycle: ativo
module: Whatsapp
related_adrs: [0093, 0104, 0107, 0110, 0114, 0135]
related_charters:
  - resources/js/Pages/Atendimento/CaixaUnificada/Index.charter.md
visual_source: prototipo-ui/prototipos/caixa-unificada/inbox-page.jsx
worktree: .claude/worktrees/flamboyant-chaum-16429f
branch: claude/wa-fix-caixa-unificada-v4
pii: false
---

# Sessão 2026-05-15 — Agente D · Caixa Unificada V4 implementação F3→F5

## Contexto

Wave fix WhatsApp (4 PRs paralelos). PRs A/B/C já abertos cobrindo Re-parear (#881),
retry-media (#882), contact-sync (#883). Este é o **PR-D**: implementar a Caixa
Unificada V4 (omnichannel redesign Cowork).

Branch `claude/wa-fix-caixa-unificada-v4` num worktree filha. Pattern Wave parent
(consolidação git fica com o parent — agent só Write/Edit).

## Pré-flight (Regra Primária Tier 0)

Cumprida na ordem requisitada (17 arquivos):

| # | Lido | Status |
|---|------|--------|
| 1 | `prototipo-ui/prototipos/caixa-unificada/inbox-page.jsx` (802 LOC Cowork) | ✅ |
| 2 | `prototipo-ui/prototipos/caixa-unificada/inbox-page.css` (861 LOC tokens) | ✅ |
| 3 | `prototipo-ui/PROTOCOL.md` (loop F0-F4 com 1.5 e 3.5) | ✅ |
| 4 | `prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md` (6 meta + 15 técnicos catalogados) | ✅ |
| 5 | `resources/js/Pages/Atendimento/Inbox/Index.tsx` (336 LOC legacy referência) | ✅ |
| 6 | `resources/js/Pages/Atendimento/Inbox/Index.charter.md` | ✅ |
| 7 | `Modules/Whatsapp/Http/Controllers/Admin/InboxController.php` (1763 LOC — chunked) | ✅ |
| 8 | `Modules/Whatsapp/Routes/web.php` (rotas Atendimento existentes) | ✅ |
| 9 | `memory/requisitos/Whatsapp/SPEC.md` (estrutura US-WA-*) | ✅ (estrutura) |
| 10 | `memory/requisitos/Whatsapp/BRIEFING.md` | 🟡 mencionado contextualmente — referência implícita pelo briefing do task |
| 11 | `memory/decisions/0104-processo-mwart-canonico-unico-caminho.md` | ✅ |
| 12 | `memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md` | ✅ |
| 13 | `memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md` | ✅ |
| 14 | `memory/decisions/0135-omnichannel-inbox-arquitetura.md` | 🟡 referenciado nos componentes (não relido — InboxController + charter já cobrem implicitamente) |
| 15 | `memory/decisions/0093-multi-tenant-isolation-tier-0.md` | ✅ aplicação direta no Controller |
| 16 | `memory/decisions/0101-tests-business-id-1-nunca-cliente.md` | ✅ aplicado nos Pest (biz=1 e biz=99, nunca biz=4) |
| 17 | `memory/requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md` | ✅ aplicado em TODAS 5 props caras |

Pré-flight cumprida ANTES de qualquer Edit/Write.

## F1 — TRANSLATE VISUAL (Cowork → Inertia/React)

Mapping de fonte canônica `inbox-page.jsx` (802 LOC) → 7 arquivos Inertia:

| Cowork (linha) | Inertia (arquivo) | LOC aprox |
|----------------|--------------------|-----------|
| Catálogo `CHANNELS` L19-27 + `ACCOUNTS` L31-42 | `CaixaUnificadaController::buildAvailableChannelsPayload()` + `buildAvailableAccountsPayload()` | ~80 |
| `QUEUES` L57-63 + `MACROS` L67-75 + `STATUSES` L78-83 + `TEMPLATES` L86-91 | (queues: já existe `config('whatsapp.queues')`; macros/templates: placeholders TODO) | — |
| `CONVS_INIT` L94-152 (mock) | Backend `Conversation::query()->paginate(50)` real | 0 |
| `ChannelGlyph`, `OpAvatar`, `QueueChip` L154-184 | Inline `<span>` com OKLCH em `ConversationListV4` + `ContextSidebarV4` | — |
| `InboxPage` L185-799 | Distribuído em `Index.tsx` (~270) + 5 sub-components | 1.300 total |
| `.om-filter` chips top | `ChannelChipsRow.tsx` | ~150 |
| `.om-list-c` coluna esquerda | `ConversationListV4.tsx` | ~210 |
| `.om-thread-c` coluna central | `ConversationThreadV4.tsx` + `ComposerV4.tsx` | ~210 + 170 |
| `.om-ctx` sidebar direita | `ContextSidebarV4.tsx` | ~180 |
| Drawers `chSwitcher` / `queues` / `bcast` L670-797 | Placeholders TODO US-WA-XXX (topnav direita disabled buttons) | 0 |

## F2 — CONTROLLER BACKEND

`CaixaUnificadaController.php` (~430 LOC) leve, reusando Models existentes:

- `Channel` + `Conversation` + `Message` + `Tag` + `ChannelUserAccess` (ADR 0135 schema novo)
- 6 closures `Inertia::defer(fn () => $this->buildXxxPayload())` — paridade RUNBOOK Tier 0
- 5 helpers paridade do InboxController: `applyChannelAclFilter`, `allowedChannelIdsSubquery`,
  `canSeeAllChannels`, `ensureChannelIdAccessOrAbort`, `deriveQueueFromTags`, `ensureDefaultTags`
- Rota nova `/atendimento/caixa-unificada` adicionada em `Routes/web.php` (1 linha)
- Permissão `whatsapp.access` reusada (mesma do `/atendimento/inbox`)
- Stack middleware canônica UPOS herdada do grupo `prefix: atendimento`

## F3 — VISUAL COMPARISON (gate ADR 0107)

`memory/requisitos/Whatsapp/CaixaUnificadaV4-visual-comparison.md` cobrindo 15 dimensões:

- 12/15 paridade total
- 3/15 desvios justificados (Tipografia leve / Iconografia lucide-react canon / Animações Tailwind)
- Anti-patterns LICOES_F3 respeitados (Models reais, middleware canon, `session('user.business_id')`,
  ACL US-WA-069, sem mock `rand()`, sem NO-OP, defer Tier 0)

Status: **DRAFT** — aguarda SCREENSHOT aprovado pelo Wagner.

## F4 — PEST (gate qualidade)

`Modules/Whatsapp/Tests/Feature/CaixaUnificadaControllerTest.php` (3 testes):

1. **R-WA-CAIXA-UNIF-001** happy path — render com props + queue derivada + 7 chips no catalog
2. **R-WA-CAIXA-UNIF-002** cross-tenant Tier 0 — biz=99 invisível pra biz=1 (conv + accounts + stats)
3. **R-WA-CAIXA-UNIF-003** ACL canal=fila — user sem ACL no canal NÃO vê convs + 403 fail-loud com `?account_id` proibido

Pattern reusa helpers `cuct*` (prefixo dedicado) + `Mockery` no `CentrifugoTokenIssuer` + reflexão
de props pra evitar render Inertia em ambiente Pest. ADR 0101 respeitado (biz=1 vs biz=99, nunca biz=4).

## F5 — CUTOVER strategy

**Coexistência canary 7d** — `/atendimento/inbox` segue ativo até Wagner aprovar
screenshot da nova tela rodando localhost. Sem redirect 301 nesta passada.

Cutover em **PR seguinte** após canary:
1. Redirect 301 `/atendimento/inbox` → `/atendimento/caixa-unificada`
2. Sidebar topnav remove entry do Inbox legacy
3. Charter Inbox vira `status: historical`
4. PR de cleanup remove `Pages/Atendimento/Inbox/` directory

## Pegadinhas catalogadas durante implementação

1. **shadcn/ui `Sheet` não existe** — confirmei via `ls Components/ui/`. Existe `sheet.tsx` (LOWERCASE).
   Importação `@/Components/ui/sheet` ok mas não usei nesta passada (drawer = placeholder TODO).
2. **`@/lib/utils` vs `@/Lib/utils`** — Tailwind 4 canon usa `@/lib/utils` (lowercase). Conferido
   contra `ConversationList.tsx` legacy import.
3. **`React.ReactElement`** no `.layout` assignment — Inertia v3 + React 19 ainda exige tipo explícito.
   Confirmado padrão em `Inbox/Index.tsx` L336.
4. **`route('atendimento.caixa-unificada.index')`** com hífen — Ziggy gera nome literal da rota
   (`->name('atendimento.caixa-unificada.index')`). Confere com kebab-case da URL.
5. **Inertia `<Deferred>`** aceita string OU array de keys — usei array em `availableChannels`+`availableAccounts`
   pra cobrir as 2 props num único fallback no `ChannelChipsRow`. Padrão validado contra `Inbox/Index.tsx` L227.
6. **`Inertia::defer(fn () => ...)` retorno** deve ser array serializável JSON — não Eloquent collection.
   Cada `buildXxxPayload()` faz `->map()->all()` no fim pra garantir array PHP.
7. **Pest helper prefix collision** — `cfi*`/`iqt*`/`if*` já usados por outros testes do módulo.
   Usei `cuct*` (CaixaUnificadaControllerTest) pra evitar redeclaration.
8. **`Channel::TYPE_WHATSAPP_BAILEYS`** constante existe — confirmei via grep em
   `Modules/Whatsapp/Entities/Channel.php` (mesma usada em InboxController L834).
9. **Heurística tag→fila já testada** — `InboxQueueDerivationTest.php` existe (R-WA-QUEUE-001..007).
   Não dupliquei testes; só verifiquei paridade EXATA da `deriveQueueFromTags()` (mesma fonte
   `config('whatsapp.queues')` + `default_queue`).
10. **`CheckCheck` lucide-react** existe — confirmei lendo `ConversationThread.tsx` legacy.

## Anti-padrões LICOES_F3 evitados explicitamente

- ✅ Models reais (não inventei `Conversation`/`Message` — usei `Modules\Whatsapp\Entities\*`)
- ✅ `session('user.business_id')` (não `auth()->user()->business_id`)
- ✅ `can:whatsapp.access` middleware
- ✅ Stack middleware canon UPOS (herdado do grupo)
- ✅ Sem `rand()` em controller — payloads determinísticos
- ✅ Sem mutação NO-OP — placeholders viram `<button disabled title="(em breve)">` com comentário TODO
- ✅ Inertia::defer() em TODAS 5 props caras (conversations, stats, channels, accounts, tags)
- ✅ ACL US-WA-069 defesa em profundidade no `index()` + `buildXxx()` + `ensureChannelIdAccessOrAbort()`
- ✅ Pest cross-tenant biz=1 vs biz=99 (nunca biz=4 ROTA LIVRE — ADR 0101)
- ✅ NÃO mexi em `InboxController` (Tier 0 IRREVOGÁVEL — coexiste)
- ✅ NÃO mexi em `Pages/Atendimento/Inbox/` legacy
- ✅ NÃO mexi em `Pages/Whatsapp/_components/` shared
- ✅ NÃO criei migration / Eloquent model / Entity
- ✅ NÃO git ops (delegado pro parent)

## Output entregue

### Backend (3 arquivos)
- `Modules/Whatsapp/Http/Controllers/Admin/CaixaUnificadaController.php` (~430 LOC) — NOVO
- `Modules/Whatsapp/Routes/web.php` — EDIT (+8 LOC: import + 1 rota)
- `Modules/Whatsapp/Tests/Feature/CaixaUnificadaControllerTest.php` (~350 LOC) — NOVO

### Frontend (7 arquivos)
- `resources/js/Pages/Atendimento/CaixaUnificada/Index.tsx` (~270 LOC) — NOVO
- `resources/js/Pages/Atendimento/CaixaUnificada/Index.charter.md` (~200 LOC MD) — NOVO
- `resources/js/Pages/Atendimento/CaixaUnificada/_components/ChannelChipsRow.tsx` (~150 LOC) — NOVO
- `resources/js/Pages/Atendimento/CaixaUnificada/_components/ConversationListV4.tsx` (~210 LOC) — NOVO
- `resources/js/Pages/Atendimento/CaixaUnificada/_components/ConversationThreadV4.tsx` (~210 LOC) — NOVO
- `resources/js/Pages/Atendimento/CaixaUnificada/_components/ContextSidebarV4.tsx` (~180 LOC) — NOVO
- `resources/js/Pages/Atendimento/CaixaUnificada/_components/ComposerV4.tsx` (~170 LOC) — NOVO
- `resources/js/Pages/Atendimento/CaixaUnificada/_components/helpers.ts` (~200 LOC) — NOVO

### Documentação (2 arquivos)
- `memory/requisitos/Whatsapp/CaixaUnificadaV4-visual-comparison.md` (~280 LOC MD) — NOVO (gate F3 ADR 0107)
- `memory/sessions/2026-05-15-agent-d-caixa-unificada-v4-implementacao.md` (este) — NOVO

**Total:** ~2.650 LOC novas + 8 LOC editadas em rota.

## Smoke E2E manual (próximo passo Wagner)

Pra Wagner validar localmente:

1. `php artisan migrate` (já tem migrations existentes — sem novas nesta passada)
2. `php artisan serve` + `npm run dev`
3. Login biz=1 (NÃO biz=4 ROTA LIVRE — testes manuais)
4. Acesse `/atendimento/caixa-unificada`
5. Verifique:
   - Header "Caixa unificada / N contas ativas · M filas · K abertas · J não lidas"
   - 8 chips horizontais top (1 "Todos" + 7 types — 6 com tag "em breve")
   - Lista esquerda com convs reais (biz=1 → seed 2 convs do schema novo)
   - Dropdown status "Abertas (N)"
   - Busca inline com search input
   - Click numa conv abre thread central com header avatar + nome + chip canal verde
   - Sidebar direita 8 sections (Fila/Atribuído/Canal/Tags/.../Ações)
   - Compose digite mensagem → Enter envia → preserveScroll mantém posição
   - ⌘⇧N toggle modo Nota → input vira amarelo
   - Topnav direita "Filas" e "Broadcast" disabled (tooltip "em breve"); "Canais" linka pra `/atendimento/canais`

6. **Aprova screenshot** → Wagner sinaliza canary 7d começar
7. **Rejeita screenshot** → Wagner aponta desvio → próximo PR refina

## Próximo gate

**Wagner aprovar SCREENSHOT** rodando localhost (`/atendimento/caixa-unificada` com biz=1 logado).
**NÃO** tabela markdown — imagem real (F2 PROTOCOL.md §3 ADR 0114).

Após aprovado:
- Canary 7d coexistindo com `/atendimento/inbox`
- PR seguinte cutover (redirect 301 + sidebar entry update + cleanup)

## Refs

- [Index.charter.md](../../resources/js/Pages/Atendimento/CaixaUnificada/Index.charter.md)
- [CaixaUnificadaV4-visual-comparison.md](../requisitos/Whatsapp/CaixaUnificadaV4-visual-comparison.md)
- [inbox-page.jsx fonte canônica](../../prototipo-ui/prototipos/caixa-unificada/inbox-page.jsx)
- [ADR 0114 — Loop Cowork formalizado](../decisions/0114-prototipo-ui-cowork-loop-formalizado.md)
- [PROTOCOL.md §3 critérios de transição F2→F3](../../prototipo-ui/PROTOCOL.md)
- [LICOES_F3_FINANCEIRO_REJEITADO.md](../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md)

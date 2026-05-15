# INVENTÁRIO + PLANO DE CUTOVER — Caixa Unificada V4

> **Tela legacy:** `/atendimento/inbox` (status `live` desde 2026-05-11 · 99% volume ROTA LIVRE)
> **Tela nova:** `/atendimento/caixa-unificada` (status `in_canary` desde 2026-05-15)
> **Pendente:** Wagner aprovar SCREENSHOT antes do cutover (charter V4 §UX Anti-patterns)
>
> Atualiza/complementa:
> - [RUNBOOK-inbox-caixa-unificada-v4.md](RUNBOOK-inbox-caixa-unificada-v4.md) (escopo original era reskin in-place; foi pivotado pra rota nova)
> - [CaixaUnificadaV4-visual-comparison.md](CaixaUnificadaV4-visual-comparison.md) (gate F3 visual)
> - [Index.charter.md (Inbox legacy)](../../../resources/js/Pages/Atendimento/Inbox/Index.charter.md)
> - [Index.charter.md (Caixa Unificada V4)](../../../resources/js/Pages/Atendimento/CaixaUnificada/Index.charter.md)

---

## 1. Estado atual (snapshot 2026-05-15)

| | Inbox legacy `/atendimento/inbox` | Caixa Unificada V4 `/atendimento/caixa-unificada` |
|---|---|---|
| Status | ✅ live · prod biz=1 | 🟡 in_canary · localhost |
| Controller | `InboxController` (1763 linhas, ~12 endpoints) | `CaixaUnificadaController` (561 linhas, 1 endpoint) |
| Page | `Atendimento/Inbox/Index.tsx` (336 linhas) | `Atendimento/CaixaUnificada/Index.tsx` (330 linhas) |
| Components | `Inbox/_components/ChannelSelector.tsx` + reusa `Pages/Whatsapp/_components/*` | 6 components V4 dedicados |
| Pest tests | 7 testes Feature | 1 teste Feature |
| Sidebar entry | `topnav.php:26` apontando `/atendimento/inbox` | sem entry sidebar (não navegável fora do URL direto) |
| Não-duplicação backend | — | ✅ reusa `route('atendimento.inbox.send')` + `update_status` etc |

---

## 2. Inventário gap — itens da Inbox que faltam na V4

### 2.1 Filtros de lista (P0 — regressão funcional)

| Filtro Inbox | V4 |
|---|---|
| `tab` (all/unread/assigned/bot/resolved/awaiting_human/archived — **7 tabs**) | ❌ tem só 4 status (abertas/pendentes/aguardando/resolvidas) |
| `within24h` tri-estado (Meta 24h window) | ❌ ausente |
| `unlinked` (convs sem Contact CRM) | ❌ ausente |
| `mediaInbound24h` (US-WA-043) | ❌ ausente |
| `inboundAging` (6h/12h/24h/48h/7d) | ❌ ausente |
| `orderBy: 'last_message' \| 'inbound'` | ❌ ausente |
| Filtro por tags (`activeTagIds`) | ❌ ausente (V4 carrega `availableTags` mas não filtra) |

### 2.2 Ações sobre conversa — endpoints carregados mas não plugados na UI

Endpoints `InboxController` que V4 referencia via route name mas **não tem UI**:

| Endpoint | Função | V4 |
|---|---|---|
| `atendimento.inbox.update_tags` | Tagear conv | 🟡 read-only |
| `atendimento.inbox.contacts.search` | Buscar Contact CRM | ❌ |
| `atendimento.inbox.link_contact` | Vincular Contact | ❌ |
| `atendimento.inbox.contact.create_from_phone` | Criar Contact do phone | ❌ |
| `atendimento.inbox.block` | Bloquear contato | ❌ |
| `atendimento.inbox.send_media` | Enviar mídia | ❌ |
| `atendimento.inbox.send_interactive` | List/Button interactive | ❌ |

V4 só pluga `send` (texto) + `update_status` (resolver).

### 2.3 Atalhos teclado (P0 UX)

| Atalho | Inbox | V4 |
|---|---|---|
| J/K (navega lista) | ✅ | ❌ |
| `/` (foca busca) | ✅ | ❌ |
| E (resolver) | ✅ | ❌ |
| A (aguardar humano) | ✅ | ❌ |
| ⌘⇧N (toggle nota) | ❌ | ✅ |

### 2.4 Sidebar contexto (8 sections — 4 placeholders TODO no V4)

Charter V4 declara 9 sections:

| Section | Inbox | V4 |
|---|---|---|
| Fila (heurística tag→fila) | ✅ derived | ✅ derived |
| Atribuído | ✅ via `assigned_user_id` | 🟡 "sem atribuição" placeholder |
| Canal · Conta | ✅ | ✅ |
| Tags chips + edit | ✅ via `update_tags` | 🟡 read-only |
| OS vinculada (Repair) | (não tem) | 🟡 placeholder |
| Saldo cliente (Financeiro) | (não tem) | 🟡 placeholder |
| Histórico (Transactions) | (não tem) | 🟡 placeholder |
| Último contato | ✅ | ✅ |
| Ações (Emitir/Enviar/Ligar) | (não tem) | 🟡 disabled |

### 2.5 Templates HSM + Macros no composer

Inbox legacy: `ConversationThread` tinha pickers funcionais.
V4: `⌘T (Templates)` e `/ (Macros)` são botões placeholder TODO.

### 2.6 Mídia inbound/outbound UI

Charter V4 §Non-Goals: "preserva legacy `MediaPreviewCard` durante coexistência. PR seguinte unifica."

---

## 3. Inventário regressão inversa — itens da V4 que NÃO tem na Inbox

Preservar no cutover (não jogar fora junto com a Inbox):

1. **Chips horizontais de canais** acima da shell (vs dropdown topbar Inbox)
2. **7 tipos canal catalog** com glyph/hue/short (Baileys · Meta · Z-API · IG · Messenger · Email · MercadoLivre)
3. **Sub-row de contas** quando type tem 2+ accounts
4. **Banner "em homologação"** pra `channel.status != 'active'`
5. **Composer toggle Resp/Nota inline** (⌘⇧N Cowork pattern)
6. **Topnav direita** (Filas | Canais | Broadcast | + Nova conversa)
7. **Header sub agregado** ("3 contas ativas · 5 filas · 8 abertas · 1 não lidas")
8. **Stats granulares** (`active_accounts` + `queues_count`)

---

## 4. Plano de cutover — 6 fases sequenciais

### F1 — PARIDADE FUNCIONAL (P0 bloqueante)

Wagner copia (ou usuário Felipe) os items P0 da §2.1 + §2.2 + §2.3 pra V4:

- [ ] Tabs filtro (7 tabs Inbox → expandir os 4 status V4)
- [ ] Plugar `updateTags` na sidebar Tags
- [ ] Plugar `searchContacts` + `linkContact` + `createContactFromPhone` + `blockContact` na sidebar
- [ ] Plugar `sendMedia` + `sendInteractive` no composer
- [ ] Atalhos teclado J/K/E/A/`/`
- [ ] Filtros `within24h` + `unlinked` + `inboundAging` + `orderBy` + filtro tags

**Gate F1:** screenshot lado-a-lado mostrando paridade funcional. Wagner aprova.

### F2 — TESTES PEST (proteção regressão)

- [ ] Migrar/duplicar 7 Pest tests de `Inbox*` pra `CaixaUnificada*`
- [ ] Adicionar test específico V4 (chips canal + banner em homologação + composer toggle)
- [ ] CI verde

**Gate F2:** `php artisan test --filter=CaixaUnificada` ✅ + `--filter=Inbox` ainda ✅ (Inbox legacy preservada durante canary)

### F3 — SCREENSHOT APPROVAL Wagner

- [ ] `npm run build` localhost
- [ ] Wagner visualiza V4 rodando + compara com Inbox legacy
- [ ] Aprova screenshot (charter V4 §UX Anti-patterns enforce)

**Gate F3:** charter V4 muda `status: in_canary` → `status: live_canary`

### F4 — CANARY 7d (ROTA LIVRE biz=4)

- [ ] Deploy Hostinger (rotas coexistem)
- [ ] Sidebar topnav.php:26 ganha ENTRY NOVA "Caixa Unificada" abaixo de "Inbox" (Larissa pode usar AS DUAS pra comparar)
- [ ] Comunicar Larissa via WhatsApp: "estou testando layout novo, fique à vontade pra usar 'Caixa unificada'; 'Inbox' continua funcionando"
- [ ] Monitor 7d sem report de bug

**Gate F4:** 7 dias sem Larissa reportar bug funcional crítico

### F5 — CUTOVER (charter V4 §6 Roadmap)

- [ ] Redirect 301: `/atendimento/inbox` → `/atendimento/caixa-unificada` (preservar query params)
- [ ] Topnav.php:26 troca href + label "Inbox" → "Caixa Unificada"
- [ ] Charter Inbox vira `status: historical` + comentário ponteiro pra V4
- [ ] Aviso visual 14d na V4: "Caixa Unificada substituiu Inbox" (tooltip dismissible)

**Gate F5:** Wagner valida em prod biz=1 (smoke completo) + biz=4 sem report

### F6 — LIMPEZA (1 sprint depois do F5)

- [ ] Remover `Pages/Atendimento/Inbox/` (Index.tsx + Index.charter.md + _components/)
- [ ] Remover redirect 301 (rotas Inbox deletadas)
- [ ] `LegacyConversationsRemovedTest` ganha case `inbox_routes_removed`
- [ ] Controller `InboxController` AVALIAR — manter (V4 reusa actions) ou refactor pra `Modules/Whatsapp/Http/Controllers/Admin/Atendimento/ActionsController` (não-bloqueante)

**Gate F6:** code-review limpo, zero referência morta

---

## 5. Arquivos que tocam no cutover (mapa)

```
ALTERAR:
  Modules/Whatsapp/Resources/menus/topnav.php:26      (F4: add entry V4 · F5: substitui)
  Modules/Whatsapp/Routes/web.php:86-95               (F5: redirect 301 · F6: remover)
  resources/js/Pages/Atendimento/Inbox/Index.charter.md (F5: status historical)

REMOVER em F6 (não antes):
  resources/js/Pages/Atendimento/Inbox/Index.tsx
  resources/js/Pages/Atendimento/Inbox/Index.charter.md
  resources/js/Pages/Atendimento/Inbox/_components/ChannelSelector.tsx
  Modules/Whatsapp/Tests/Feature/InboxFiltersTest.php          ← OU migrar pra CaixaUnif*
  Modules/Whatsapp/Tests/Feature/InboxMultiPhoneFilterTest.php
  Modules/Whatsapp/Tests/Feature/InboxSendInteractiveTest.php
  Modules/Whatsapp/Tests/Feature/InboxCleanupTest.php
  Modules/Whatsapp/Tests/Feature/InboxQueueDerivationTest.php

PRESERVAR (V4 reusa):
  Modules/Whatsapp/Http/Controllers/Admin/InboxController.php  (12 endpoints ainda servem V4)
```

---

## 6. Riscos

| Risco | Mitigation |
|---|---|
| Larissa não percebe entry nova e segue usando Inbox legacy | Aviso WhatsApp pessoal + tooltip "novo layout" em sidebar |
| F1 incompleto e cutover quebra workflow real | Gate F3 screenshot OBRIGATÓRIO antes de canary; F4 7d antes de F5 |
| Pest cobertura cai durante canary | F2 obrigatório antes de F3 — migrar tests Inbox→CaixaUnif |
| Redirect 301 perde query params (`?status=&thread=`) | Mapear no Route::redirect com translate (`status: 'unread' → 'pendentes'`) |
| ROTA LIVRE biz=4 reporta bug crítico em F4 | Rollback = remover entry sidebar V4 + comunicar Larissa volta pra Inbox |

---

## 7. Sign-off Wagner

- [ ] **Paridade funcional F1** — concordo que esses 6 items P0 são bloqueantes?
- [ ] **Mídia + Templates** ficam pra F1.5 ou pós-cutover?
- [ ] **Canary 7d** ROTA LIVRE biz=4 OU primeiro biz=1 (você) por 3d e depois 7d biz=4?
- [ ] **Atalhos teclado** J/K/E/A/`/` — manter exatamente como Inbox legacy ou redesenhar pra Cowork?
- [ ] **Redirect 301 ou link permanente** `/atendimento/inbox` durante 14d antes do remove?

---

**Histórico:**

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | Wagner + Opus 4.7 | Inventário inicial pós-descoberta da rota nova. Gap analysis 6 áreas P0 + 8 itens V4 preservar. Plano 6 fases F1→F6 sequencial com gates. Pendente sign-off pra disparar F1. |

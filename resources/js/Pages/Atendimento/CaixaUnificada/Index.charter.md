---
page: /atendimento/caixa-unificada
component: resources/js/Pages/Atendimento/CaixaUnificada/Index.tsx
owner: wagner
status: in_canary
last_validated: 2026-05-15
parent_module: Whatsapp
parent_adr: memory/decisions/0135-omnichannel-inbox-arquitetura.md
visual_source: prototipo-ui/prototipos/caixa-unificada/inbox-page.jsx
related_adrs: [0093, 0094, 0104, 0107, 0110, 0114, 0135]
related_charters: [resources/js/Pages/Atendimento/Inbox/Index.charter.md]
tier: A
charter_version: 1
permissao: whatsapp.access
---

# Page Charter — `/atendimento/caixa-unificada` (V4)

> Caixa Unificada V4 — redesign Cowork omnichannel da Inbox (`/atendimento/inbox`).
> Coexiste com a tela legacy durante canary 7d. Cutover em PR seguinte após Wagner
> aprovar screenshot da tela rodando localhost.

---

## Mission

Tela única que centraliza todas as conversas omnichannel do business num **3-col
visual Cowork** (chips canal em cima · lista esquerda · thread central · contexto
direita), com **paridade de capacidade** com a Inbox legacy (Centrifugo + polling
fallback 5s · ACL canal=fila · multi-tenant Tier 0) e **expansão visual** pra
suportar canais ainda em homologação (Meta Cloud, Z-API, Instagram DM, Messenger,
Email IMAP, Mercado Livre) com banner "em homologação" claro.

Substituirá `/atendimento/inbox` após canary aprovado. Durante coexistência,
**reusa endpoints backend** (`POST /atendimento/inbox/{id}/send`, `PATCH
/atendimento/inbox/{id}`, etc) — sem duplicar contrato HTTP.

---

## Goals — Features (faz)

### Layout e navegação
- **3-col limpa** (lista 320px · thread 1fr · contexto 300px) — responsivo
  para 1280px ROTA LIVRE.
- **Chips horizontais de canais** acima da shell (vs dropdown topbar do
  legacy). 1 chip "Todos" + 7 chips por TYPE de canal — count em mono,
  tag "em breve" para canais inativos.
- **Sub-row de contas** aparece quando user seleciona TYPE com 2+ contas
  (ex: 3× Baileys com phones diferentes).
- **Dropdown de status** dentro do header da lista — 4 valores canônicos:
  Abertas / Pendentes / Aguardando / Resolvidas.
- **Busca inline** com Enter pra aplicar, Esc pra limpar.
- **Topnav direita** — Filas | Canais | Broadcast | + Nova conversa
  (placeholders TODO US-WA-XXX exceto "Canais" que linka real).

### Banner "em homologação"
- Canais com `Channel.status != 'active'` viram preview-only no backend
  (`preview_only: true`).
- Thread mostra banner amarelo: "Email (IMAP) · em homologação. Conexão deste
  canal ainda não foi ativada. Esta conversa é uma prévia. **Ativar canal**"
  (link pra `/atendimento/canais`).
- Lista mostra chip "em breve" ao lado do nome do contato preview.
- Composer fica disabled em modo cliente — nota interna continua permitida.

### Real-time defense in depth (paridade com Inbox legacy)
- **Centrifugo WSS** subscribe em `omnichannel:business:{id}`.
- **Polling 5s SEMPRE** em paralelo (US-WA-066 — cliente real cancelou
  contrato por msg perdida).
- **preserveScroll + preserveState** em todos `router.reload` (anti-flash
  US-WA-068).
- **Pausa em aba inativa** (`document.visibilityState !== 'visible'`).

### Composer
- **Toggle Resp/Nota** inline (⌘⇧N) — replica `internalMode` do Cowork.
- **Botões placeholder** ⌘T (Templates) e `/` (Macros) — TODOs honestos.
- **Enviar/Anotar** verde-primary ou amarelo-nota.
- **Disabled** quando preview (modo cliente) ou contato bloqueado.

### Atalhos teclado (Wave 1 F1 paridade Inbox legacy — 2026-05-15)
- **J/K** — navega conversa anterior/próxima na lista esquerda
- **`/`** — foca o input de busca da lista (data-caixa-unif-search)
- **E** — resolve a conversa aberta (PATCH update_status `resolved`)
- **A** — marca a conversa como "aguardando humano" (PATCH update_status `awaiting_human`)
- **⌘⇧N** — toggle Resp/Nota no composer (já existente)
- Filtra `input/textarea/contentEditable` + ignora com ctrl/meta/alt (defense in depth)

### Sidebar direita (8 sections)
1. **Fila** — derivada via heurística tag→fila (read-only nesta passada).
2. **Atribuído** — placeholder "sem atribuição" (TODO US-WA-XXX).
3. **Canal · Conta** — short label + handle mono.
4. **Tags** — chips coloridos quando há tags aplicadas.
5. **OS vinculada** — placeholder (TODO US-WA-XXX: linkar Repair).
6. **Saldo cliente** — placeholder (TODO US-WA-XXX: Financeiro).
7. **Histórico** — placeholder (TODO US-WA-XXX: agregar Transactions).
8. **Último contato** — relativeTimeBR do `last_message_at`.
9. **Ações** — 3 botões (Emitir cobrança · Enviar arte · Ligar) — placeholders.

### Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL)
- Todas queries via global scope `business_id` (Eloquent).
- ACL canal=fila (US-WA-069) — user sem `channel_user_access` ATIVO não vê
  convs do canal proibido. `?account_id=X` proibido → 403 fail-loud.
- Centrifugo channel segregado por `business_id`.

---

## Non-Goals — Features (NÃO faz nesta tela)

- ❌ **Configurar Channel** — `/atendimento/canais` continua.
- ❌ **Editar templates HSM** — `/whatsapp/templates`.
- ❌ **Wizard de conexão** — `/atendimento/canais/{id}` show + tabs.
- ❌ **Filas configuração real** — placeholder; config via
  `config('whatsapp.queues')` (estática). UI de gerenciamento fica pra
  PR seguinte (TODO US-WA-XXX).
- ❌ **Broadcast cross-canal** — placeholder; backlog (alta complexidade
  janela 24h Meta + opt-in LGPD).
- ❌ **Mover conversa entre filas** — só leitura (fila vem de heurística).
- ❌ **Assignee picker** — placeholder; backlog US-WA-XXX.
- ❌ **Templates picker no composer** — placeholder; reusar
  `TemplatePicker` do legacy em PR seguinte.
- ❌ **Macros / slash commands** no composer — placeholder; reusar dropdown
  `/macros` do legacy em PR seguinte.
- ❌ **Mídia outbound/inbound** UI — preserva legacy `MediaPreviewCard`
  durante coexistência. PR seguinte unifica.

---

## UX Targets

- **First-paint:** p95 < 1500ms em Hostinger frio (Inertia::defer skip
  garante shell rendiriza enquanto closures async resolvem).
- **Switch entre conversas:** p95 < 300ms (paridade com Inbox legacy
  pós-D-14 — só thread+messages no `only:[]`).
- **Latência WSS:** p95 < 1s.
- **0 erros JS console** com config válida.
- **Cabe em 1280px** sem scroll horizontal (ROTA LIVRE monitor).
- **Atalho ⌘⇧N** toggle modo nota funcional.

---

## UX Anti-patterns

- ❌ **Reload total** quando msg chega — `preserveScroll: true` +
  `preserveState: true` SEMPRE (US-WA-068).
- ❌ **Polling sem `visibilityState` check** — drena bateria.
- ❌ **Re-buscar `conversations` no thread switch** — só thread+messages
  no `only:[]` (lição perf D-14).
- ❌ **Eager `paginate()` no Controller** — `Inertia::defer()` em TODAS
  props caras (Tier 0 IRREVOGÁVEL ADR 2026-05-15).
- ❌ **Substituir `/atendimento/inbox`** antes de Wagner aprovar SCREENSHOT
  da tela rodando localhost — coexistência canary 7d primeiro.
- ❌ **Inventar Models/Services** que não existem (anti-pattern LICOES_F3
  T-AP-1 + T-AP-7) — placeholders TODO honestos.
- ❌ **Tom de "completude" em maturidade real WIP** — Filas/Broadcast/Painéis
  são placeholders; commit message + PR title refletem `scaffold` ou `WIP`
  (anti-pattern M-AP-2).

---

## Automation Hooks

- **Centrifugo subscribe** `omnichannel:business:{id}` no `useEffect`.
- **Polling 5s SEMPRE** com cleanup no unmount + pausa visibilityState.
- **Send via** `route('atendimento.inbox.send', conversationId)` —
  reusa contrato legacy (US-WA-069 + ADR 0142 notas internas).
- **Resolve via** `route('atendimento.inbox.update_status')` —
  PATCH com `status: 'resolved'`.

---

## Automation Anti-hooks

- ❌ Não chama daemon Baileys direto no `Inertia::render` — backend reusa
  `InboxController::send` que orquestra.
- ❌ Não emite token Centrifugo no frontend — backend emite via
  `CentrifugoTokenIssuer::issue` em cada `Inertia::render`.
- ❌ Não persiste filtros em DB — tudo via query string + LS (per-user
  per-browser, sem leakage cross-tenant).

---

## Métricas vivas (Pest GUARD)

| Status | Test | Arquivo |
|---|---|---|
| ✅ | `R-WA-CAIXA-UNIF-001 — happy path render com props básicas + queue derivada` | `Modules/Whatsapp/Tests/Feature/CaixaUnificadaControllerTest.php` |
| ✅ | `R-WA-CAIXA-UNIF-002 — cross-tenant biz=99 invisível pra biz=1 (Tier 0)` | mesmo arquivo |
| ✅ | `R-WA-CAIXA-UNIF-003 — user sem ACL no canal NÃO vê convs daquele canal` | mesmo arquivo |

---

## Roadmap — O que falta (priorizado pelo Wagner)

### §1 Filas — UI de configuração (P1)
Hoje filas vêm de `config('whatsapp.queues')` estático. Wagner quer painel
admin pra criar/editar fila (label, hue, SLA, trigger_tags, distribuição
round-robin/sticky/manual, members[]). Tabela DB nova `whatsapp_queues` +
ADR per-schema antes de criar — não inventar.

**US sugerida:** US-WA-XXX Filas DB + painel (~4-6h IA-pair).

### §2 Broadcast cross-canal (P2)
Disparar mensagem template (Meta HSM ou Baileys freeform) pra N contatos
com 1 click. Respeitar janela 24h + opt-in LGPD. Pre-flight: contagem +
preview + dry-run.

**US sugerida:** US-WA-XXX Broadcast (~6-8h IA-pair).

### §3 Assignee picker (P1)
Dropdown no contexto da sidebar pra atribuir conv a operador específico.
Reusa `assigned_user_id` nullable já existente em `conversations`.

**US sugerida:** US-WA-XXX Assignee picker (~2-3h IA-pair).

### §4 Mover conversa entre filas (P2)
Hoje fila é derivada read-only via tag heurística. Mover = re-tagar.
Wagner pode querer override manual sem mexer em tag.

**US sugerida:** US-WA-XXX Manual queue override (~2h IA-pair).

### §5 Painel "Canais e contas" drawer
Cowork tem drawer lateral com lista agrupada de canais (Baileys/Meta/Z-API
+ contas com status ativo/em-breve). Hoje a tela linka direto pra
`/atendimento/canais` (página completa). Drawer in-place economiza
context switch.

**US sugerida:** US-WA-XXX Channels drawer (~2-3h IA-pair).

### §6 Cutover Inbox legacy → Caixa Unificada V4 (P0 — pós-canary)
Após Wagner aprovar canary 7d:
1. Redirect `/atendimento/inbox` → `/atendimento/caixa-unificada` (301)
2. Sidebar topnav substituir entry
3. Charter Inbox vira `status: historical`
4. Remover `Pages/Atendimento/Inbox/` em PR seguinte (~1h)

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | Wagner + Opus 4.7 (Agente D wave fix) | Charter inicial. Implementação F3-F5 do RUNBOOK `cowork-prototype-replication` ADR 0114. Fonte canônica `prototipo-ui/prototipos/caixa-unificada/inbox-page.jsx` (802 LOC Cowork). Coexiste com `/atendimento/inbox` legacy durante canary 7d. Próximo gate: Wagner aprovar SCREENSHOT manual rodando localhost antes de canary começar. |

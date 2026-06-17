---
page: /atendimento/caixa-unificada
component: resources/js/Pages/Atendimento/CaixaUnificada/Index.tsx
owner: wagner
status: live
last_validated: "2026-06-16"
cutover_at: "2026-05-15"
supersedes: resources/js/Pages/Atendimento/Inbox/Index.charter.md
parent_module: Whatsapp
parent_adr: memory/decisions/0135-omnichannel-inbox-arquitetura.md
visual_source: prototipo-ui/prototipos/caixa-unificada/inbox-page.jsx
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0104-processo-mwart-canonico-unico-caminho
  - 0107-emendation-0104-visual-comparison-gate-f3
  - 0110-cockpit-pattern-v2-canon-list-detail
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0135-omnichannel-inbox-arquitetura
related_charters: [resources/js/Pages/Atendimento/Inbox/Index.charter.md]
tier: A
charter_version: 14
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
- **Filtro de canal/conta no popover "Filtros" da lista** (direção [W] 2026-06-16):
  substituiu a faixa horizontal de chips acima da shell (comprimia a tela em 1280px).
  Grupo **Canal** (1 "Todos" + 7 TYPEs, count mono, "em breve" para inativos) e grupo
  **Conta** (quando o TYPE tem 2+ contas) viram seções do popover Filtros (Onda 2).
  Capacidade omnichannel e contrato `?channel=`/`?account_id=` intactos. Faixa
  `ChannelChipsRow` removida na Onda 1.
- **Dropdown de status** dentro do header da lista — 4 valores canônicos:
  Abertas / Pendentes / Aguardando / Resolvidas.
- **Busca inline** com Enter pra aplicar, Esc pra limpar.
- **Topnav direita** — Filas (QueuesSheet US-WA-301) | Canais (ChannelsDrawer
  US-WA-304) | Broadcast (placeholder — US-WA-306 no PR-7) | **+ Nova conversa**
  (US-WA-307, 2026-06-10): dialog com conta ativa + telefone OU Contact CRM
  (ContactPickerModal US-WA-064) + mensagem inicial opcional. POST
  `atendimento.inbox.start_conversation` faz find-or-create (número que já
  conversou REABRE thread) e redireciona com a thread aberta; mensagem inicial
  reusa o pipeline send() completo (zero duplicação de driver).

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
- **Templates** (US-WA-303, 2026-06-10): botão abre `TemplatePicker` legacy
  reusado, filtrado por provider do canal da thread (baileys/zapi/meta_cloud).
  Envia `kind=template` via `atendimento.inbox.send`. Payload deferred
  `availableTemplates` (só LOCAL/APPROVED, Tier 0).
- **Macros `/`** (US-WA-303): dropdown lazy via `atendimento.macros.list`
  (US-WA-048 backend reusado — NÃO cria tabela nova) + autocomplete inline
  digitando `/` no input (Enter aplica a 1ª; Esc dispensa). Apply via
  `atendimento.inbox.apply_macro` (MacroExecutor envia msg + aplica ações).
- **Variáveis `{{}}`** (US-WA-303): botão `{}` insere `{{nome}}`/`{{telefone}}`/
  `{{operador}}`; preview resolvido acima do input (verde=ok, vermelho=sem
  valor → literal); substituição no send (nota interna fica literal).
  TODO honesto: `{{empresa}}`/`{{os}}`/`{{saldo}}` esperam integrações
  Repair/Financeiro das sections 5-6 da sidebar.
- **Enviar/Anotar** verde-primary ou amarelo-nota.
- **Disabled** quando preview (modo cliente) ou contato bloqueado.

### Atalhos teclado (Wave 1 F1 paridade Inbox legacy — 2026-05-15)
- **J/K** — navega conversa anterior/próxima na lista esquerda
- **`/`** — foca o input de busca da lista (data-caixa-unif-search)
- **E** — resolve a conversa aberta (PATCH update_status `resolved`)
- **A** — marca a conversa como "aguardando humano" (PATCH update_status `awaiting_human`)
- **⌘⇧N** — toggle Resp/Nota no composer (já existente)
- Filtra `input/textarea/contentEditable` + ignora com ctrl/meta/alt (defense in depth)

### Tabs filtro (Wave 2 F1 paridade Inbox legacy — 2026-05-15)
- **7 tabs canônicas** substituem dropdown 4-status anterior:
  - `all` (Todas — exceto archived)
  - `unread` (Não lidas — `unread_count > 0`)
  - `assigned` (Minhas — `assigned_user_id == auth user`)
  - `bot` (Bot — `bot_handling == true`)
  - `awaiting_human` (Aguardando — bot escalou pra humano)
  - `resolved` (Resolvidas)
  - `archived` (Arquivadas)
- Backend `CaixaUnificadaController` aceita `?tab=` (parâmetro principal) e mapeia legacy `?status=` pra `tab` automaticamente.
- Stats expandida com counters per-tab (assigned/bot/awaiting_human/archived) — exibe badge contagem na tab quando > 0.
- URL preservada via `replace: true` no router.get (não polui history).

### Filas persistidas + painel (US-WA-301 · ADR 0267, 2026-06-10)
- Tabela `whatsapp_queues` substitui `config('whatsapp.queues')` — seed lazy
  idempotente do config na 1ª visita por business; fallback config se DB vazio.
- Topnav "Filas" abre **QueuesSheet** (CRUD in-place): label, hue (dot OKLCH),
  SLA em minutos, distribuição (persistida; roteamento é US futura),
  tags-gatilho (chips das tags do business). Fila default protegida de delete.
- Mutações via `atendimento.filas.*` (permission `whatsapp.settings.manage`);
  leitura nos props (`queues` shape compat + `queuesAdmin` deferred).
- Heurística tag→fila e `stats.queues_count` passam a ler o DB.

### Sidebar direita (8 sections)
1. **Fila** — heurística tag→fila + **override manual** (US-WA-305, 2026-06-10):
   Popover "mover" lista filas do business; PATCH `atendimento.inbox.move_queue`
   grava `queue_override` (vence heurística sem re-tagar); badge "manual" +
   opção "Voltar pra automática". Slug órfão de fila deletada cai no fallback.
2. **Atribuído** — assignee picker real (US-WA-302, 2026-06-10): Popover com
   operadores do business (grant ativo OU `whatsapp.access`/`whatsapp.send`),
   avatar iniciais + hue determinístico, "remover atribuição". PATCH
   `atendimento.inbox.assign` (Tier 0 — target user do MESMO business,
   cross-tenant = 422). Tab "Minhas" (`assigned`) ganha utilidade real.
3. **Canal · Conta** — short label + handle mono.
4. **Tags** — chips coloridos quando há tags aplicadas.
5. **OS vinculada** — placeholder (TODO US-WA-XXX: linkar Repair).
6. **Saldo cliente** — a receber em aberto do cliente (Onda 3, US-WA-308):
   soma de `transactions` UPOS `due`/`partial` (status != draft, − pagamentos via
   subquery `transaction_payments`). Tier 0 escopado por `business_id`.
7. **Histórico** — pedidos + LTV (Onda 3, US-WA-308): count + `SUM(final_total)`
   de sells reais (status != draft) por `contact_id`. Tier 0. Eager,
   refresca no thread switch via `only:['customerContext']`.
8. **Último contato** — relativeTimeBR do `last_message_at`.
9. **Ações** — 3 botões (Emitir cobrança · Enviar arte · Ligar) — placeholders.

### IA na thread (PR-9, 2026-06-10 — do protótipo inbox-ai.jsx)
- **Validação pré-PR** (regra do brief): infra Jana EXISTE — laravel/ai SDK
  (ADR 0035) + pattern Agents (`BriefingAgent`/`ChatCopilotoAgent`) → IA REAL,
  não scaffold.
- **3 endpoints finos** (`InboxAiController` + `InboxAssistAgent`):
  `POST .../inbox/{id}/ai/summarize` (resumo 5 bullets) · `.../ai/ask`
  (pergunta sobre o transcript) · `.../ai/suggest-reply` (preenche o composer;
  humano SEMPRE revisa antes de enviar — nada vai pro cliente automático).
- **LGPD**: transcript passa pelo `PiiRedactor` da Jana ANTES do provider;
  notas internas entram marcadas e o Agent é instruído a nunca expô-las.
- **Custo**: `config('copiloto.dry_run')` (mesma chave da Jana) devolve fixture
  sem tocar LLM — dev/test nunca gastam token. Provider fora → 503 legível.
- UI: header "Resumir"/"Perguntar" (`InboxAiDialog`) + composer "✦ Sugerir".

### Polish V2 (PR-8, 2026-06-10 — do protótipo inbox-extras/out/cur)
1. **SLA pill** — `slaState()` client-side (75% = âmbar, estourado = vermelho)
   na lista e no header da thread; só conta quando o cliente falou por último.
2. **⌘K palette** — TODO honesto: palette GLOBAL já existe (PMG-002 no
   AppShellV2); estender com convs/contatos = US própria cross-módulo
   (não duplicar palette — MANUAL #5 anti-duplicação).
3. **Cheat-sheet `?`** — `InboxCheatSheet` com os atalhos REAIS (J/K///E/A/⌘⇧N/⌘K/?).
4. **Lightbox in-app** — imagem da bubble abre `MediaFullscreenModal` reusado
   (navegação entre todas as imagens da thread) em vez de `window.open`.
5. **Mobile tabs <lg** — `InboxMobileTabs` Conversas/Thread/Contexto; abrir
   conversa salta pra Thread; desktop 3-col intacto.
6. **Favoritos** — estrela na conversa (`useInboxFavs`, localStorage per-user,
   SEM DB — anti-hook preservado); favoritas ordenam no topo.
7. **Transcript imprimível** — `InboxTranscriptDialog` print-friendly com
   header Oimpresso; notas internas FORA por default (toggle consciente).
8. **Modo apresentação** — `InboxPresenterMode` overlay limpo (sem IDs
   internos nem notas), Esc fecha.

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
- ❌ **Roteamento automático de filas** (round-robin/sticky/members) —
  `dist`/`members` são persistidos (ADR 0267) mas o roteamento é US futura.
- ❌ **Disparo em massa do broadcast** — fase 2 (ADR 0268): Job rate-limited
  anti-ban + retry por destinatário, com gate [W]. A fase 1 (pre-flight LGPD +
  rascunho auditável) está entregue; o botão "Disparar" fica disabled até lá.
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
| ✅ | `R-WA-CAIXA-UNIF-004 — availableAssignees só lista operadores do business atual (Tier 0)` | mesmo arquivo |
| ✅ | `R-WA-CAIXA-UNIF-005 — assign atribui/remove operador + bloqueia cross-tenant (Tier 0)` | mesmo arquivo |
| ✅ | `R-WA-CAIXA-UNIF-006 — availableTemplates só ready (LOCAL/APPROVED) do business atual (Tier 0)` | mesmo arquivo |
| ✅ | `R-WA-CAIXA-UNIF-007 — filas: seed lazy idempotente do config + payload lê DB + Tier 0` | mesmo arquivo |
| ✅ | `R-WA-CAIXA-UNIF-008 — CRUD filas: store/update/destroy + default protegida + Tier 0` | mesmo arquivo |
| ✅ | `R-WA-CAIXA-UNIF-009 — moveQueue: override vence heurística, null volta, slug inválido 422` | mesmo arquivo |
| ✅ | `R-WA-CAIXA-UNIF-010 — startConversation: cria, reabre (não duplica) + guards canal/phone` | mesmo arquivo |
| ✅ | `R-WA-CAIXA-UNIF-011 — broadcast pre-flight: opt-in LGPD + janela 24h + draft auditável` | mesmo arquivo |
| ✅ | `R-WA-CAIXA-UNIF-012 — inbox AI: dry_run devolve fixture sem LLM + ACL canal fail-loud` | mesmo arquivo |
| ✅ | `R-WA-CAIXA-UNIF-013 — canal whatsmeow ativo vira chip ativo com count real (PARTE 4)` | mesmo arquivo |

---

## Roadmap — O que falta (priorizado pelo Wagner)

### §1 Filas — UI de configuração (P1) — ✅ ENTREGUE 2026-06-10 (US-WA-301)
Tabela `whatsapp_queues` (ADR 0267) + QueuesSheet CRUD + seed lazy do config.
Roteamento automático (dist/members) fica dormente até US futura.

### §2 Broadcast cross-canal (P2) — 🟡 FASE 1 ENTREGUE 2026-06-10 (US-WA-306 · ADR 0268)
Entregue: modelo `whatsapp_broadcasts` + opt-in LGPD (`contacts.whatsapp_opt_in_at`)
+ pre-flight real (total/opt-in/janela 24h/só-HSM) + rascunho auditável
(snapshot congelado server-side) + BroadcastSheet.
**Fase 2 (gate [W])**: extrair dispatch do send() pra Service + Job rate-limited
anti-ban + retry por destinatário + relatório de entrega. Botão "Disparar"
permanece disabled até lá (anti M-AP-2).

### §3 Assignee picker (P1) — ✅ ENTREGUE 2026-06-10 (US-WA-302)
Dropdown no contexto da sidebar pra atribuir conv a operador específico.
Reusa `assigned_user_id` nullable já existente em `conversations`.
Endpoint PATCH `atendimento.inbox.assign` + payload `availableAssignees`.

### §4 Mover conversa entre filas (P2) — ✅ ENTREGUE 2026-06-10 (US-WA-305)
Coluna `queue_override` em conversations + PATCH `atendimento.inbox.move_queue`
+ Popover na section Fila. Override vence heurística; null volta pra automática.

### §5 Painel "Canais e contas" drawer — ✅ ENTREGUE 2026-06-10 (US-WA-304)
`ChannelsDrawer` (Sheet) agrupado por type + contas com status/health + link
"Gerenciar" pra página completa. Zero backend novo (reusa `availableChannels`
+ `availableAccounts`).

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
| 2026-06-16 | Claude Code [CL] (Caixa filtros 2-botões · Onda 1) | **Faixa de canais removida.** Direção [W] 2026-06-16: a faixa horizontal `ChannelChipsRow` acima da shell (comprimia 1280px) sai; Canal/Conta viram grupos do popover **Filtros** da lista (Onda 2). Onda 1: removida a faixa + `ChannelChipsRow.tsx` (dead-code, único consumidor era esta tela); `availableChannels`/`availableAccounts` (props), URL-sync `?channel=`/`?account_id=` e os demais consumidores (Thread/Sidebar/Drawers/Nova-conversa) **intactos**. Charter v14. PR off origin/main; CI verifica build/typecheck (worktree sem node_modules). Onda 2 adiciona os grupos no popover Filtros + Status em DropdownMenu. |
| 2026-06-16 | Claude Code (brief [CC] PARTE 4) | **Fix chips de canal — WhatsApp LIVE sumia.** `buildAvailableChannelsPayload` listava o WhatsApp como `whatsapp_baileys` (provider deletado, ADR 0202); o Channel ativo real é `whatsapp_whatsmeow` (WuzAPI/whatsmeow, ADR 0204), então `$activeTypesCount[id]` nunca casava → TODOS os chips caíam em 'em_breve' e o canal vivo (de onde as conversas chegam) ficava escondido. Row trocada pra `whatsapp_whatsmeow` (label/short "WhatsApp", hue 145 verde, glyph W); `?channel=whatsapp_whatsmeow` filtra via whereHas channel.type. Helper Pest e R-WA-CAIXA-UNIF-001 seedavam o type morto (mascaravam o bug) → migrados pro LIVE; novo R-WA-CAIXA-UNIF-013 (regressão: chip 'ativo' + count real + filtro). Tier 0 multi-tenant preservado. Completa a PARTE 4 que o item dark-mode abaixo deixou pra PR backend. Charter v12. PR próprio off origin/main. |
| 2026-06-16 | Claude Code (brief [CC] dark-mode) | **Fix MODO ESCURO + empty-state Customer 360.** Tokenização dark-aware das folhas que usavam cor clara crua (a tela foi portada antes da auditoria de escuro): bolha inbound `bg-white`→`bg-card`; nota interna, banner "em homologação", SLA-pill e chip de Tag → `warning-soft`/`warning-fg`/`warning` (flipam no `.dark`), corpo da nota `text-foreground` (contraste nos 2 temas); read-tick `text-blue-600`→`oklch` inline (passa R1). `CustomerMemoryBlock` colapsa o card vazio (sem Contact CRM **e** sem enriquecimento) numa linha — compartilhado com o Inbox legacy, sem prop nova (não regride). Repo ativa dark via `.dark` (não `[data-theme]`) → **zero token novo, zero override CSS**. Verificado por probe token-flip nos 2 temas. PARTE 4 (chips "em breve" — catálogo sem `whatsapp_whatsmeow`) fica em PR backend separado. Charter v11. |
| 2026-05-15 | Wagner + Opus 4.7 (Agente D wave fix) | Charter inicial. Implementação F3-F5 do RUNBOOK `cowork-prototype-replication` ADR 0114. Fonte canônica `prototipo-ui/prototipos/caixa-unificada/inbox-page.jsx` (802 LOC Cowork). Coexiste com `/atendimento/inbox` legacy durante canary 7d. Próximo gate: Wagner aprovar SCREENSHOT manual rodando localhost antes de canary começar. |
| 2026-06-10 | Claude (mandato [W] "aplicar todas") | **IA na thread** (PR-9/10): validação pré-PR confirmou infra Jana (laravel/ai + Agents) → IA REAL. `InboxAssistAgent` + `InboxAiController` (summarize/ask/suggest-reply) com PII redigida (PiiRedactor), dry_run gateando custo, 503 gracioso. UI: header Resumir/Perguntar + composer ✦ Sugerir (humano revisa). Charter v10. Pest R-WA-CAIXA-UNIF-012. |
| 2026-06-10 | Claude (mandato [W] "aplicar todas") | **Polish V2** (PR-8/10): SLA pill lista+thread (slaState 75%/estourado) · cheat-sheet `?` · lightbox MediaFullscreenModal reusado · mobile tabs <lg (InboxMobileTabs) · favoritos localStorage (useInboxFavs, sem DB) · transcript imprimível (notas fora por default) · modo apresentação (Esc). ⌘K = TODO honesto (palette global PMG-002 já existe; estender = US cross-módulo). 100% frontend — payloads cobertos por R-WA-CAIXA-UNIF-001/002. Charter v9. |
| 2026-06-10 | Claude (mandato [W] "aplicar todas") | **US-WA-306 Broadcast FASE 1** (PR-7/10, scaffold honesto previsto no brief): ADR 0268 + `whatsapp_broadcasts` + `contacts.whatsapp_opt_in_at` (LGPD) + `BroadcastController` pre-flight real (opt-in/janela 24h/só-HSM) + draft auditável + `BroadcastSheet`. Disparo em massa = fase 2 com gate [W] (botão disabled, anti M-AP-2). Charter v8. Pest R-WA-CAIXA-UNIF-011. |
| 2026-06-10 | Claude (mandato [W] "aplicar todas") | **US-WA-307 + Nova conversa** (PR-6/10): dialog conta ativa + telefone/Contact CRM + mensagem inicial opcional; `InboxController::startConversation` find-or-create Tier 0 (canal ativo do business + ACL US-WA-069; cross/inativo = 403/422) reusando pipeline send(). Charter v7. Pest R-WA-CAIXA-UNIF-010. |
| 2026-06-10 | Claude (mandato [W] "aplicar todas") | **US-WA-304 Drawer Canais e contas** (PR-5/10): topnav "Canais" deixa de navegar pra página e abre `ChannelsDrawer` (Sheet in-place agrupado por type, contas com status ativo/em-breve + health, link Gerenciar pra `/atendimento/canais`). ZERO backend novo — reusa payloads `availableChannels`/`availableAccounts` (cobertos por R-WA-CAIXA-UNIF-001/002). Charter v6. |
| 2026-06-10 | Claude (mandato [W] "aplicar todas") | **US-WA-305 Mover entre filas** (PR-4/10): coluna `queue_override` (migration idempotente, slug não-FK de propósito — fila deletada não quebra conversa) + `InboxController::moveQueue` (slug validado contra filas do business, 422 fail-loud) + Popover "mover" na section Fila com badge "manual" e volta pra automática. Charter v5. Pest R-WA-CAIXA-UNIF-009. |
| 2026-06-10 | Claude (mandato [W] "aplicar todas") | **US-WA-301 Filas DB + painel** (PR-3/10): tabela `whatsapp_queues` (ADR 0267, per-schema antes da migration) + seed lazy idempotente do config + QueuesSheet CRUD (label/hue/SLA/dist/tags-gatilho, default protegida) + heurística tag→fila lê DB com fallback config. Topnav "Filas" deixa de ser disabled. Charter v4. Pest R-WA-CAIXA-UNIF-007/008. |
| 2026-06-10 | Claude (mandato [W] "aplicar todas") | **US-WA-303 Composer completo** (PR-2/10): Templates via `TemplatePicker` legacy filtrado por provider do canal + payload `availableTemplates` (LOCAL/APPROVED) · Macros dropdown + autocomplete `/` inline reusando backend US-WA-048 (`macros.list` + `apply_macro`) · Variáveis `{{nome}}`/`{{telefone}}`/`{{operador}}` com botão `{}`, preview verde/vermelho e substituição no send. Charter v3. Pest R-WA-CAIXA-UNIF-006. |
| 2026-06-10 | Claude (mandato [W] "aplicar todas" — brief [CC] Caixa Unificada completa) | **US-WA-302 Assignee picker** (PR-1/10): section 2 da sidebar vira picker real (Popover operadores + avatar hue + remover atribuição). Backend: PATCH `atendimento.inbox.assign` (InboxController::assign, Tier 0 cross-tenant 422) + prop deferred `availableAssignees` + relação `Conversation::assignedUser`. Charter v2. Pest R-WA-CAIXA-UNIF-004/005. |
| 2026-05-15 | Wagner + Opus 4.7 | Adicionado `<CustomerMemoryBlock>` (US-WA-VOZ-001/002/003 — PR #919) no topo do `ContextSidebarV4`. Lazy fetch `GET /atendimento/customer/{ext}/profile`. Mostra identidade Contact CRM, stats agregados, top 3 reclamações 30d com severity, external_sources Firebird, flags VIP/frágil, LGPD. Mesmo componente usado pelo Inbox legacy (`ConversationSidebar.tsx`) — atendente vê Customer 360 em qualquer tela durante cutover. |

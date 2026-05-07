---
date: 2026-05-07
session_focus: Whatsapp UI cockpit 3-painéis + build:inertia migrado pra Hostinger
agents: [W, Claude]
prs: [173, 174]
adrs_criadas: [0098]
duration_estimate: ~2h
---

# Sessão 2026-05-07 tarde — Whatsapp UI estado-da-arte + build na Hostinger

## Contexto inicial

Wagner abriu pedindo "precisa MELHORAR a UI whatszap". Branch worktree `optimistic-nobel-d99053`, partindo do main que já tinha o WhatsappModule funcional (Lote 2h.A — Centrifugo JS client wired no Show.tsx).

## Entregas

### 1. PR #173 mergeado — UI Whatsapp cockpit 3-painéis

**Trabalho:** Reescrita das telas `Conversations/Index.tsx` e `Show.tsx` no padrão Cockpit canônico (ADR 0039 Chat Cockpit).

**Implementação:**
- Backend `ConversationsController`: `index()` aceita `?thread=ID` + `?q=` (search server-side via `whereHas` em `customer_phone` ou `contact.name`). Helper privado `loadThreadPayload()` reusado por `show()` (permalink) e `index()` (split-view) — zero duplicação. Subqueries pra `last_message_preview` + `last_message_direction` evitam N+1.
- Routes: novo PATCH `/conversations/{id}` (`whatsapp.send`) — atribuir-me, bot ON/OFF, resolver, reabrir, aguardar humano.
- Frontend componentes shared em `resources/js/Pages/Whatsapp/_components/`:
  - `helpers.ts` (types + getInitials/pickColor/relativeTime/groupByDay)
  - `Avatar.tsx` (inicial colorida hash determinístico)
  - `ConversationList.tsx` (search debounced 250ms, tabs pílula, avatar+preview+direção+tempo relativo+badge unread+flag 24h)
  - `ConversationThread.tsx` (header sticky+presence Centrifugo, mensagens agrupadas por dia + tail só na última do mesmo lado, bubbles verde/branco, status icons ✓/✓✓, scroll-bottom button, composer Enter envia/Shift+Enter quebra)
  - `ConversationSidebar.tsx` (avatar grande+badge, ações com cores semânticas, info detalhada janela 24h, metadata)
- `Index.tsx` virou orquestrador 3-painéis (lista 320-384px / thread flex / sidebar 288-320px) com partial reload (clicar conversa atualiza thread inline, sem reload da lista). URL deep-linkable via `?thread=ID`.
- `Show.tsx` refatorada pra 50 linhas reusando os shared (rota permalink).

### 2. PR #174 mergeado — build:inertia migrado pra Hostinger

**Trabalho:** Após Wagner perguntar "build na hostinger, seria bom colocar hotbuild?", investiguei e descobri que Hostinger TEM Node 24.15 + npm 11 via nvm. Testei build manual: 52s, 138GB memória disponível, sem crashes.

**Mudanças:**
- `.gitignore`: adicionado `/public/build-inertia/`
- `git rm -r --cached public/build-inertia/`: -16574 linhas em 230 arquivos binários
- `quick-sync.yml`: novos steps `npm ci` condicional (fingerprint sha256 de `package-lock.json` em `.last-npm-ci-hash`) + `npm run build:inertia`
- `build-inertia-auto.yml`: deletado (obsoleto)

**ADR 0098** criada documentando tudo: contexto, decisão, trade-offs, pegadinhas conhecidas.

## Aprendizados meta

- **Hostinger ≠ "só PHP"** — tem Node 24/npm 11 via nvm desde algum setup anterior. Reflexo de "shared hosting = sem Node" custou tempo investigando opções (GH Actions runner, CT 100 builder) antes de testar Hostinger direto. Vale sempre **olhar antes de assumir**.
- **Race condition silenciosa** — 2 workflows em paralelo + `git reset --hard` antes do build commitar = janela de 30s onde Hostinger serve source+bundles dessincados → 409 manifest mismatch → full reload. Pipeline novo elimina por construção (1 workflow, ordem garantida).
- **Cleanup pós-migração**: `git rm --cached` move arquivos de tracked pra untracked. `git reset --hard` no servidor não apaga untracked. Se não rodar `git clean -fd public/build-inertia/` UMA VEZ, fica lixo até alguém perceber.
- **Build pós-merge falhou em prod** — package.json declarava `centrifuge ^5.5.3`, mas `node_modules` da Hostinger não tinha (npm ci nunca rodou). PR #173 importou centrifuge em `_components/ConversationThread.tsx`, build falhou, sem manifest.json → HTTP 500. **Lição:** sempre rodar `npm ci` quando lockfile mudar — o step condicional do novo workflow previne reprise.
- **Quick-sync SSH secrets vazios** (bug pre-existente) — `SSH_PORT`, `SSH_HOST`, `SSH_USER`, `SSH_PRIVATE_KEY` no GitHub Secrets estão vazios. Workflow falha em `Setup SSH` desde sempre. Hoje deploys precisam ser manuais via SSH. **Pendência:** Wagner setar os secrets pra workflow funcionar de verdade.
- **`route()` global em TS** — 161 erros `Cannot find name 'route'` em todo o projeto. Pre-existente, não regressão. Conflita com strict mode mas funciona em runtime via Ziggy. Algum dia vale resolver com `declare global { function route(...): string }` em `app.d.ts`.

## Estado da prod pós-sessão

- HTTP 200 em `/login` (280ms)
- Bundle Inertia regenerado limpo (sem lixo de build anterior)
- node_modules: 448 packages instalados via npm ci
- `.last-npm-ci-hash` salvo na Hostinger pra próximos quick-syncs pularem npm ci se lockfile não mudar

## Próximos passos sugeridos

- **P0 — Configurar SSH secrets do GitHub Actions** (`SSH_PRIVATE_KEY`, `SSH_HOST`, `SSH_PORT`, `SSH_USER`) — sem isso quick-sync continua inútil e Wagner precisa fazer todo deploy manual
- **P1 — Validar próximo deploy automático** mexendo em `resources/js/` em main e ver se quick-sync (uma vez secrets configurados) executa o pipeline completo
- **P2 — Resolver `route()` global no TS** — adicionar declaração em `app.d.ts` ou import explícito de Ziggy. Limparia 161 erros TS.

## Cycle 02 — progresso

Cycle 02 (CYCLE-02) estava com 6d restantes no início da sessão. Goal MWART-Repair (4 telas) continua 0/4 mas com momentum. Whatsapp Cockpit pattern não estava listado entre goals — bonus delivery.

---

**Última atualização:** 2026-05-07 ~16h BRT — sessão Whatsapp UI cockpit + build Hostinger (PR #173 + #174 mergeados, ADR 0098)

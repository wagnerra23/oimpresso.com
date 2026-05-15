# Handoff 2026-05-15 15:20 BRT — Wave fix WhatsApp 5 PRs + Caixa Unificada V4 deploy prod

> **Append-only ADR 0130** — não editar este arquivo. Próximo handoff cria novo, atualiza índice em `memory/08-handoff.md`.

## Estado MCP no momento do fechamento

- **Cycle:** Martinho prod + FSM rollout + Jana V2 demo (CYCLE-06) · 13d restantes (snapshot brief-fetch hoje)
- **Branch atual session:** `claude/docs-handoff-wave-fix-whatsapp` (worktree flamboyant-chaum-16429f)
- **Último commit deployed Hostinger biz=1:** `8bf355cb7` (hotfix Caixa Unificada V4 ambiguous business_id)
- **PRs abertos pelo Claude na wave** (4 aguardando merge Wagner):
  - [#881 PR-A](https://github.com/wagnerra23/oimpresso.com/pull/881) — Botão Re-parear UI em Channels/Show
  - [#882 PR-B](https://github.com/wagnerra23/oimpresso.com/pull/882) — Cron `whatsapp:retry-recent-media-downloads` horário
  - [#883 PR-C](https://github.com/wagnerra23/oimpresso.com/pull/883) — Sync contatos via `history.sync` chunk_index=0
  - [#885 PR-E](https://github.com/wagnerra23/oimpresso.com/pull/885) — CI quick-sync.yml Tailwind legacy + fetch retry
- **PRs mergeados nesta sessão** (já em prod biz=1):
  - #884 PR-D — Caixa Unificada V4 omnichannel (`741632438`)
  - #886 hotfix — ambiguous business_id post-JOIN (`8bf355cb7`)
- **Outros PRs no repo (não da wave):** #862, #860, #812 (Sells/legacy-migration), #594 DRAFT

## O que aconteceu na sessão (cronologia)

### Fase 1 — Diagnóstico dos 4 problemas reportados (~14:00 BRT)

Wagner reportou 4 problemas no módulo Whatsapp:
1. **Mídias não mostrando** no Inbox `/atendimento/inbox`
2. **Pareamento QR/pairing code** não funciona pós Baileys 7.x
3. **Sincronia inicial dos contatos** não trouxe contatos
4. **Design não foi aplicado** (Caixa Unificada V4)

Diagnóstico em paralelo:
- **#1:** Pipeline OK, mas `RetryFailedMediaDownloadsJob` Camada 4 filtra `status IN (pending, downloading)` — não pega cenário `media_url=NULL + status NULL` que `PersistHistorySyncBatchJob` insere. Gap real.
- **#2:** Botão "Conectar" em `Channels/Index.tsx` só aparece se `status !== 'active' && health !== 'healthy'`. Quando WhatsApp do device desconecta unilateralmente, DB ainda mostra `active+healthy` até cron 5min — Wagner não vê botão. E Show.tsx ConfigTab era read-only.
- **#3:** Daemon Node envia `contacts + chats` no chunk_index=0 do webhook `history.sync` (`Instance.ts:278-281`), mas backend PHP `handleHistorySync` IGNORA completamente esses campos — só lê `messages`.
- **#4:** Caixa Unificada V4 gap conhecido (BRIEFING §5 linha 60). Protótipo Cowork ainda não estava em `prototipo-ui/`.

### Fase 2 — Wave 3 agents paralelos A/B/C (~14:40 BRT)

Spawn 3 agents general-purpose em áreas isoladas:
- **Agent A** → `resources/js/Pages/Atendimento/Channels/Show.tsx` (botão Re-parear + modal QR)
- **Agent B** → novo command `whatsapp:retry-recent-media-downloads` + cron `hourlyAt(15)` + 7 Pest
- **Agent C** → `handleHistorySync` lê `$data['contacts']` + Job novo `PersistContactsFromHistorySyncJob` + 2 Pest

Outputs consolidados em 3 PRs separados ([#881, #882, #883](https://github.com/wagnerra23/oimpresso.com/pulls)) via pattern parent-stash + branch-from-origin/main + selective-stage.

### Fase 3 — Caixa Unificada V4 (Agent D Opus, ~14:55 BRT)

Wagner enviou zip handoff Cowork `Oimpresso ERP Conunicacao Visual.-handoff (1).zip`. Extraídos pra `prototipo-ui/prototipos/caixa-unificada/`:
- `visual-source.html` (83 LOC wrapper)
- `inbox-page.jsx` (802 LOC componente Cowork canônico)
- `inbox-page.css` (861 LOC tokens)

Spawn Agent D Opus sustained pra F3→F5 MWART (`cowork-prototype-replication` skill). Output: 12 arquivos novos (~2650 LOC) — Backend (Controller + Routes + Pest) + Frontend (Index.tsx + 6 sub-components + helpers + charter) + F3 visual-comparison ADR 0107 + RUNBOOK.

Commit final em #884.

### Fase 4 — PR-E CI quick-sync.yml gap (~17:00 BRT)

Wagner pediu "build no servidor Hostinger". Diagnóstico revelou:
- Inertia build **fresh** (`build-inertia/manifest.json` 13:53 hoje via quick-sync.yml #877) — não era esquecimento
- Tailwind Blade legacy **stale 10 dias** (`public/css/tailwind.css` 2026-05-05) — gap real catalogado
- Sessão 2026-05-07 documentou pendência P0 (secrets faltando) — JÁ resolvida (secrets configurados em 2026-04-23 e 2026-05-07)

PR-E #885 adiciona step `npm run build` (Tailwind legacy) + git fetch retry 3-tries no `quick-sync.yml`.

### Fase 5 — Merge PR-D + hotfix SQL (~18:00 BRT)

Wagner mostrou screenshot Cowork "esperado" + autorizou destravar. Workflow:

1. **CI gates falharam** no #884:
   - `check-scope` FAILURE — `Admin/CaixaUnificadaController` não declarado em `Modules/Whatsapp/SCOPE.md`
   - `Frontend / Vite build` FAILURE — 4 imports `@/lib/utils` (case errado, Linux CI sensitive)
2. **Push fixes** no PR-D branch: SCOPE.md + sed `@/lib/utils → @/Lib/utils` em 4 arquivos
3. **Merge #884 via gh api** (gh pr merge falhou por worktree conflict — fallback API direct)
4. **Quick-sync auto-build** OK em 1m21s
5. **Validação visual via Brave (tier read)** — tela explodiu com SQL `SQLSTATE[23000] 1052: Column 'business_id' in WHERE is ambiguous` em `CaixaUnificadaController.php:294`
6. **Hotfix #886** — qualifica `where('conversations.business_id', $businessId)` pós-JOIN com `channels`
7. **Merge #886** + auto-build OK em 42s
8. **Validação visual final** — Caixa Unificada V4 totalmente funcional em prod biz=1 ROTA LIVRE:
   - Chips canal: Todos 130 / WA · Baileys 129 / Meta Cloud em breve / Z-API em breve / Insta / Messenger / Email / Mercado Livre
   - Thread mensagens: bubbles verde (atendente) + branco (cliente) + amarelo (nota interna)
   - Sidebar Contexto: FILA Comercial / SLA 1h / ATRIBUÍDO sem atribuição / CANAL · CONTA WA · Baileys Suporte / OS · SALDO · HISTÓRICO · ÚLTIMO CONTATO 3min
   - Botões ação: Emitir cobrança / Enviar arte / Ligar
   - Header: "Caixa unificada · 2 contas ativas · 2 filas · 130 abertas · 5293 não lidas"

## Lições aprendidas catalogadas

### L1 — Case sensitivity Linux vs Windows
Agent D criou imports `@/lib/utils` que funciona em Windows local (case-insensitive) mas quebra em Linux CI (case-sensitive). Path real é `@/Lib/utils` (Lib maiúsculo). **Pré-flight futuro:** rodar `npm run build:inertia` local ANTES de push em qualquer PR que crie componentes React novos. Pode adicionar pre-commit hook.

### L2 — JOIN com tabela multi-tenant requer prefix explícito
`Conversation::query()->where('business_id', $businessId)->join('channels', ...)` quebra em runtime com SQLSTATE 1052. **Tier 0 ADR 0093 emenda implícita:** sempre qualificar `where('conversations.business_id', $businessId)` em queries com JOIN. Skill futura: `multi-tenant-join-guard` Tier B que detecta ANTES de push.

### L3 — Tailwind Blade legacy esquecido no workflow
Sessão 2026-05-07 criou `quick-sync.yml` cobrindo só `build:inertia`. Tailwind legacy (`vite.config.js` → `public/css/tailwind.css`) ficou 10 dias stale. **PR-E #885 fix:** ambos builds rodam no workflow.

### L4 — Race condition git fetch
PR #870 (2026-05-15 12:56) falhou 14s: `cannot lock ref 'refs/remotes/origin/main'`. Outro PR mergeou entre fetch e reset. **PR-E #885 fix:** retry 3-tries com sleep 3s.

### L5 — gh pr merge falha em worktree filha
`gh pr merge --admin` tenta checkout local da branch principal, mas Windows com main em outro worktree dá `fatal: 'main' is already used`. **Workaround:** `gh api -X PUT repos/.../pulls/N/merge -f merge_method=squash` (sem checkout local).

## Próximos passos sugeridos

### Pra retomar em outro PC (Felipe)

1. **Setup Claude Code + MCP** via skill `oimpresso-team-onboarding` (configura `.claude/settings.local.json` com acesso ao MCP server `mcp.oimpresso.com`)
2. **Setup git** com chave SSH do Felipe registrada no GitHub
3. **Clone** `https://github.com/wagnerra23/oimpresso.com` (private repo)
4. **`composer install` + `npm ci`** (1× setup local)
5. **Acesso ao Hostinger SSH** — Wagner registra chave Felipe no `~/.ssh/authorized_keys` do servidor (procedure documentada em `memory/requisitos/Infra/RUNBOOK-credenciais-hierarquia.md`)

### Wagner deve mergear (manual, ADR 0040 publication-policy)

Em ordem sugerida:
1. **#881 PR-A** (UI puro, baixo risco) → testa botão Re-parear
2. **#885 PR-E** (CI/CD) → garante próximos PRs buildam Tailwind também
3. **#882 PR-B** (cron Job, médio risco) → rodar `whatsapp:retry-recent-media-downloads --dry-run --hours=24` ANTES, validar count
4. **#883 PR-C** (webhook, médio risco) → testar com re-pareamento canal Baileys teste primeiro

Cutover Inbox legacy → Caixa Unificada V4 (PR futuro): redirect 301 `/atendimento/inbox` → `/atendimento/caixa-unificada` + sidebar topnav. **Após canary 7d** com `/atendimento/caixa-unificada` em prod sem regressão.

### Pendências menores não-bloqueantes

- **Roles Spatie sem suffix `#{biz}`** — Eliana/Felipe entrando em breve, validar grants WA quando criarem usuários
- **CSAT spammar cliente** se conv re-resolve 2× em 24h — `DISPATCH_DEDUP_HOURS=24` mitiga
- **Multi-phone UI completa US-WA-040 PR3+PR4** — bloqueia Martinho canary (3h IA-pair)
- **Auto-cadastro contact 38→70** — estado-da-arte 2026-05-14, 3 PRs P0 (10h IA-pair)

## Stats da sessão

- **5 PRs abertos:** #881, #882, #883, #884 (merged), #885, +hotfix #886 (merged)
- **2 PRs mergeados em prod biz=1:** #884 + #886
- **Total LOC adicionadas:** ~7.929 (incluindo 1970 LOC protótipo Cowork canônico)
- **Pest tests novos:** 12 (7 #882 + 2 #883 + 3 #884)
- **Builds Hostinger:** 3 (Inertia manual via SSH + 2 quick-sync.yml automáticos pós-merge)
- **Browsers usados:** Brave tier "read" via computer-use MCP + curl headless validation
- **Duration session:** ~4h
- **Cliente em prod afetado:** ROTA LIVRE biz=1 — sem incidente (hotfix pegou antes de Larissa ver)

## Files canônicos atualizados nesta sessão

- `Modules/Whatsapp/Http/Controllers/Admin/CaixaUnificadaController.php` (novo + hotfix)
- `Modules/Whatsapp/SCOPE.md` (declared CaixaUnificadaController)
- `Modules/Whatsapp/Routes/web.php` (rota nova `/atendimento/caixa-unificada`)
- `resources/js/Pages/Atendimento/CaixaUnificada/` (8 arquivos novos)
- `prototipo-ui/prototipos/caixa-unificada/` (4 arquivos canon Cowork)
- `memory/requisitos/Whatsapp/CaixaUnificadaV4-visual-comparison.md` (F3 ADR 0107)
- `memory/sessions/2026-05-15-agent-a-channels-show-repair-button.md`
- `memory/sessions/2026-05-15-agent-b-retry-recent-media.md`
- `memory/sessions/2026-05-15-agent-c-contact-sync-history-sync.md`
- `memory/sessions/2026-05-15-agent-d-caixa-unificada-v4-implementacao.md`
- `.github/workflows/quick-sync.yml` (PR-E #885 — Tailwind legacy + fetch retry)

## Bridge cloud→local

Não aplicável (sessão inteira em local Claude Code worktree). Felipe pode pegar tudo via `git pull` no clone novo + MCP server expõe os 352+ docs via `decisions-search`/`memoria-search`/`tasks-list` em qualquer PC.

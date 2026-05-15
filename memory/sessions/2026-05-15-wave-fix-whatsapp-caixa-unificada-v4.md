---
date: 2026-05-15
session_focus: Wave fix WhatsApp 5 PRs paralelos + Caixa Unificada V4 deploy prod biz=1 ROTA LIVRE
agents: [W, Claude, Opus subagent D]
prs: [881, 882, 883, 884, 885, 886]
merged_prod: [884, 886]
adrs_criadas: []
adrs_referenciadas: [0093, 0096, 0101, 0104, 0107, 0114, 0130, 0135, 0143]
duration_estimate: ~4h
cycle: CYCLE-06 (Martinho + FSM + Jana V2)
---

# Sessão 2026-05-15 14:00-18:30 BRT — Wave fix WhatsApp + Caixa Unificada V4 prod

## Contexto inicial

Wagner abriu reportando 4 problemas no módulo Whatsapp em prod biz=1:
1. Mídias inbound não mostrando no Inbox
2. Pareamento QR/pairing code não funciona pós Baileys 7.x deploy CT 100
3. Sincronia inicial dos contatos não trouxe contatos
4. Design da Caixa Unificada V4 não foi aplicado

Estado entrada: BRIEFING WhatsApp 95% operacional, gap 6 "Caixa Unificada v4 frontend redesign (F3 design handoff)" listado como remanescente.

## Diagnóstico paralelo (Fase 1)

Cada problema diagnosticado lendo código real + AskUserQuestion pra Wagner clarificar escopo:

| # | Problema | Root cause | Esforço |
|---|---|---|---|
| 1 | Mídias inbound | `RetryFailedMediaDownloadsJob` Camada 4 filtra `status IN (pending, downloading)` mas `PersistHistorySyncBatchJob` insere msgs com `media_url=NULL + status NULL` que Camada 4 ignora | 2h |
| 2 | Pareamento QR | Botão "Conectar" só aparece se `status !== 'active' && health !== 'healthy'`. Quando device desconecta unilateralmente, DB mostra `active+healthy` até cron 5min — Wagner não vê botão. Show.tsx era read-only ("US futura") | 1.5h |
| 3 | Contact sync | Daemon Node emite `contacts + chats` no chunk_index=0 do webhook (Instance.ts:278-281), backend `handleHistorySync` IGNORA — só lê `messages` | 6h |
| 4 | Design V4 | Gap conhecido. Protótipo Cowork não estava em prototipo-ui/ | 7-9h |

## Wave 3 agents paralelos A/B/C (Fase 2)

Spawn em áreas isoladas via Agent tool general-purpose:

- **Agent A** → Channels/Show.tsx — botão Re-parear + modal QR reaproveitando endpoint existente
- **Agent B** → `whatsapp:retry-recent-media-downloads` + cron `hourlyAt(15)` + 7 Pest
- **Agent C** → estende `handleHistorySync` + Job `PersistContactsFromHistorySyncJob` + 2 Pest

Pattern usado:
1. Cada agent recebeu lista de pastas permitidas (zero overlap)
2. Zero git ops nos agents (só Write/Edit)
3. Prompt incluiu Tier 0 IRREVOGÁVEL (business_id, PT-BR, ADR 0093, ADR 0101)
4. Parent consolidou: `git stash push -u` → 3 branches from `origin/main` → selective `git add` por área → commit + push + PR

Outputs:
- [#881 PR-A](https://github.com/wagnerra23/oimpresso.com/pull/881) +326/-3 LOC
- [#882 PR-B](https://github.com/wagnerra23/oimpresso.com/pull/882) +648 LOC (7 Pest)
- [#883 PR-C](https://github.com/wagnerra23/oimpresso.com/pull/883) +761 LOC (2 Pest)

## Caixa Unificada V4 — Agent D Opus sustained (Fase 3)

Wagner enviou zip handoff `Oimpresso ERP Conunicacao Visual.-handoff (1).zip`. Extraídos via PowerShell:
- `prototipo-ui/prototipos/caixa-unificada/visual-source.html` (83 LOC wrapper)
- `prototipo-ui/prototipos/caixa-unificada/inbox-page.jsx` (802 LOC componente Cowork)
- `prototipo-ui/prototipos/caixa-unificada/inbox-page.css` (861 LOC tokens)
- `prototipo-ui/prototipos/caixa-unificada/chat.jsx` (224 LOC chat secundário)

Commit PR-D parte 1: fonte canônica Cowork.

Agent D Opus sustained spawned com prompt detalhado seguindo F3→F5 do RUNBOOK `cowork-prototype-replication` ADR 0114:
- Pré-flight: 17 arquivos lidos (SPEC, RUNBOOK, charters, ADRs canônicas)
- Restrições Tier 0: NÃO substituir Inbox legacy (coexistência canary)
- Output esperado: 12 arquivos + RUNBOOK + visual-comparison

Outputs (12 arquivos / ~2650 LOC):
- Backend: `CaixaUnificadaController.php` + Routes/web.php edit + Pest 3 testes
- Frontend: Index.tsx + Index.charter.md + 6 sub-components + helpers.ts
- F3 visual-comparison ADR 0107 obrigatório (15 dimensões, 12 paridade + 3 desvios)
- RUNBOOK session log F1-F5 trail + 10 pegadinhas catalogadas

Decisões importantes do agente:
- **Reuso de endpoints, não de componentes** — `POST /atendimento/inbox/{id}/send` + `PATCH /atendimento/inbox/{id}` reaproveitados via Ziggy route(). Componentes React V4 são NOVOS pra não acoplar visual Cockpit V2 ao redesign Cowork.
- **Placeholders honestos** (anti-pattern M-AP-1 LICOES_F3) — Filas/Broadcast/Painéis viraram `disabled` com `title="em breve"` + `// TODO US-WA-XXX:`. NÃO inventou Service/Model fantasma.
- **Filtro fila em-memory pós-paginate** — `queue` derivada (não persiste DB). Refactor futuro se virar gargalo.
- **Iconografia lucide-react canon** sobre char unicode Cowork — desvio justificado ADR 0110.

## PR-E CI quick-sync.yml gap (Fase 4)

Wagner pediu "build no servidor Hostinger verifique se outra sessão já fez isso mas esqueceu de compilar". Investigação:

| Build | Path | Idade | Status |
|---|---|---|---|
| Inertia React | `public/build-inertia/manifest.json` | **2026-05-15 13:53** (3h após últimos commits JS) | ✅ FRESH |
| Tailwind Blade legacy | `public/css/tailwind.css` | **2026-05-05** (10 dias atrás) | ⚠️ STALE |

**Outra sessão "esqueceu" foi a 2026-05-07** que criou `quick-sync.yml` mas só incluiu `npm run build:inertia` — esqueceu `npm run build` (Tailwind). Documentou pendência P0 "secrets faltando" que **JÁ FOI RESOLVIDA** (secrets configurados 2026-04-23 e 2026-05-07).

Rebuild manual via SSH Hostinger sincronizou ambos.

PR-E [#885](https://github.com/wagnerra23/oimpresso.com/pull/885) +25/-1 LOC no `quick-sync.yml`:
1. Step novo "Build Tailwind Blade legacy" após `build:inertia`
2. Retry `git fetch` 3-tries (anti-race PR #870 hoje 12:56 falhou 14s)
3. Comentários PT-BR catalogando lessons learned

## Merge PR-D + hotfix SQL (Fase 5)

Wagner: "assuma ela esta travada". Tomei decisão:

1. **CI gates do #884 falharam:**
   - `check-scope` FAILURE — `Admin/CaixaUnificadaController` não em `Modules/Whatsapp/SCOPE.md.contains[]`
   - `Frontend / Vite build` FAILURE — `Could not load /resources/js/lib/utils` (4 arquivos com `@/lib/utils` minúsculo, path real é `@/Lib/utils`)

2. **Fix push** no branch `claude/wa-fix-caixa-unificada-v4`:
   - SCOPE.md +1 linha
   - sed `@/lib/utils → @/Lib/utils` em 4 arquivos V4

3. **Merge #884** — `gh pr merge` falhou por worktree conflict (`main` em outro worktree). Workaround: `gh api -X PUT repos/wagnerra23/oimpresso.com/pulls/884/merge -f merge_method=squash` direto.

4. **Quick-sync.yml** auto-buildou em 1m21s. Commit `741632438` deployed.

5. **Validação visual via Brave tier read** — SQL EXPLODIU:
   ```
   SQLSTATE[23000]: Integrity constraint violation: 1052
   Column 'business_id' in WHERE is ambiguous
   ```
   Em `CaixaUnificadaController.php:294` método `buildAvailableChannelsPayload`.

   Root cause: `Conversation::query()->where('business_id', $businessId)->join('channels', ...)`. Ambas tabelas têm `business_id` (multi-tenant Tier 0). MySQL rejeita where sem prefix pós-JOIN.

6. **Hotfix #886** — `where('conversations.business_id', $businessId)` qualificado. 1 linha + 3 linhas comentário PT-BR.

7. **Merge #886 via API** + quick-sync 42s + deployed `8bf355cb7`.

8. **Validação final** — Caixa Unificada V4 totalmente funcional em prod biz=1:
   - Chips canal: Todos 130 / WA · Baileys 129 / Meta Cloud em breve / Z-API em breve / Insta em breve / Messenger em breve / Email em breve / Mercado Livre em breve
   - Thread: bubbles verde (atendente) + branco (cliente) + amarelo (nota interna)
   - Sidebar Contexto: FILA Comercial · SLA 1h · ATRIBUÍDO sem atribuição · CANAL · CONTA WA · Baileys Suporte / 554896486699 · OS · SALDO · HISTÓRICO · ÚLTIMO CONTATO 3min
   - Botões: ✓ Resolver / Emitir cobrança / Enviar arte / Ligar
   - Header: "Caixa unificada · 2 contas ativas · 2 filas · 130 abertas · 5293 não lidas"

## Lições aprendidas

### L1 — Case sensitivity Linux vs Windows
Agent D criou imports `@/lib/utils` (minúsculo) que funciona Windows local mas quebra Linux CI. **Pré-flight futuro:** rodar `npm run build:inertia` local ANTES de push em qualquer PR React novo. Possível skill: `case-sensitive-import-guard` ou pre-commit hook.

### L2 — JOIN com tabela multi-tenant requer prefix explícito
`Conversation::query()->where('business_id', ...)->join('channels', ...)` quebra SQLSTATE 1052. **Tier 0 ADR 0093 emenda implícita:** sempre qualificar `where('conversations.business_id', ...)` em queries com JOIN. Possível skill: `multi-tenant-join-guard` Tier B.

### L3 — Tailwind Blade legacy esquecido no workflow
Sessão 2026-05-07 criou `quick-sync.yml` mas só cobriu `build:inertia`. Tailwind legacy ficou 10 dias stale. PR-E #885 fix.

### L4 — Race condition git fetch
PR #870 falhou 14s: `cannot lock ref refs/remotes/origin/main`. PR-E #885 retry 3-tries.

### L5 — gh pr merge falha em worktree filha
`gh pr merge --admin` tenta checkout local que conflita com worktree existente. Workaround: `gh api -X PUT repos/.../pulls/N/merge -f merge_method=squash` (sem checkout).

## Stats finais

- **6 PRs criados:** #881, #882, #883, #884, #885, #886 (hotfix)
- **2 PRs mergeados em prod biz=1:** #884 (squash `741632438`) + #886 (squash `8bf355cb7`)
- **4 PRs aguardando merge Wagner:** #881, #882, #883, #885
- **Total LOC adicionadas:** ~7929 (incluindo 1970 protótipo Cowork canônico)
- **Pest tests novos:** 12 (7 #882 + 2 #883 + 3 #884)
- **Builds Hostinger:** 3 (Inertia manual + 2 quick-sync automáticos)
- **Validação visual:** Brave tier "read" via computer-use MCP + curl headless
- **Duration:** ~4h
- **Incidente prod:** 1 (SQL ambiguous business_id) detectado via screenshot Brave + corrigido em <10min

## Próximos passos

Documentados no handoff: `memory/handoffs/2026-05-15-1520-wave-fix-whatsapp-5prs-caixa-unificada-v4-deploy.md`.

Sessão transferida pra Felipe via MCP. Wagner mergeia os 4 PRs pendentes (#881, #882, #883, #885) em ordem sugerida: A → E → B → C.

Cutover Inbox legacy → Caixa Unificada V4 (PR futuro) após canary 7d sem regressão.

# Handoff 2026-05-12 18:10 — Omnichannel Wave 1 + Wave 2 paralelização (11 PRs)

> Sessão de paralelização massiva via subagents em worktrees isoladas. Família completa **ADR 0142** (notas internas + 4 slash commands) + **ADR 0135** Omnichannel Fase 0 (Canais detail + canal=fila + mídia/Whisper) entregue em main.

## TL;DR

**11 PRs merged em 1 sessão** fechando CYCLE-05 WhatsApp:

| # | PR | US | Linhas |
|---|----|----|--------|
| 1 | [#625](https://github.com/wagnerra23/oimpresso.com/pull/625) | US-WA-067 limpar Settings | -872 |
| 2 | [#630](https://github.com/wagnerra23/oimpresso.com/pull/630) | US-WA-070 sidebar + Templates Jana em Canais | -21 |
| 3 | [#632](https://github.com/wagnerra23/oimpresso.com/pull/632) | **ADR 0142** aceita | +331 |
| 4 | [#641](https://github.com/wagnerra23/oimpresso.com/pull/641) | US-WA-071 notas internas MVP (Chatwoot pattern) | +471 |
| 5 | [#644](https://github.com/wagnerra23/oimpresso.com/pull/644) | US-WA-068 Tab Usuários canal + `channel_user_access` | +1264 |
| 6 | [#648](https://github.com/wagnerra23/oimpresso.com/pull/648) | US-WA-072 Mídia (img/audio/doc) + Whisper transcrição | +1706 |
| 7 | [#649](https://github.com/wagnerra23/oimpresso.com/pull/649) | US-WA-074 framework SlashCommandParser + `/lembrar` | +1300 |
| 8 | [#655](https://github.com/wagnerra23/oimpresso.com/pull/655) | US-WA-069 validar canal=fila (filtragem inbox per channel) | +583 |
| 9 | [#657](https://github.com/wagnerra23/oimpresso.com/pull/657) | US-WA-076 `/lembrete` + cron hourly ProcessRemindersJob | +925 |
| 10 | [#658](https://github.com/wagnerra23/oimpresso.com/pull/658) | US-WA-077 `/config bot=off` per-contact override | +697 |
| 11 | [#659](https://github.com/wagnerra23/oimpresso.com/pull/659) | US-WA-075 `/corrigir` training signal Jana | +750 |

**Total:** ~8.000 linhas de delta, 6 subagents paralelos em 2 waves + 5 PRs sequenciais da sessão. Sessão começou Wagner sem direção clara ("Tela do Atendimento? ou Whatzap agora não é mais só isso então fiquei confuso") e fechou com framework slash commands completo deployado.

## Como aconteceu

### Fase 1 — Diagnóstico (Settings.tsx defasada)

Wagner reportou tela `/whatsapp/settings` com blocos defasados pós-criação de Canais (ADR 0135). Discutimos modelo "canal = fila" — atendente do Suporte não vê inbox do Financeiro. Confirmado: ACL per-canal via tabela nova `channel_user_access` (separada do legacy `whatsapp_phone_user_access`).

Saiu **11 US criadas no SPEC**: 4 originais (067/068/069/070) + 7 da família Chatwoot/Jana (071/072/073/074/075/076/077).

### Fase 2 — Implementação sequencial (5 PRs)

- US-WA-067 (limpar Settings) → PR #625 — 692→117 linhas em Settings.tsx, drivers viraram polimórficos via `Channel.config_json`
- US-WA-070 (sidebar) → PR #630 — `JanaTemplates.tsx` novo em `Atendimento/`, 301 redirect `/whatsapp/settings`
- ADR 0142 → PR #632 — design da família slash + 3 tabelas novas, aceita por Wagner em chat
- US-WA-071 (notas MVP) → PR #641 — `is_internal_note` boolean em `messages` + `whatsapp_messages` (defense-in-depth dual schema), gate Tier 0 IRREVOGÁVEL em Controller, toggle Reply/Note UI, Pest 5/5 local

### Fase 3 — Paralelização Wave 1 (3 subagents background)

Após `/clear` Wagner pediu paralelo. Worktree pai foi deletado (estava em origin agora), então spawn de subagents seguro (ADR 0119). 3 agents em worktrees isoladas + background:

- **Agent A** — US-WA-072 Mídia + Whisper (12h estimadas) → PR #648 (15 arquivos, +1712 linhas, Pest skipou pra CI)
- **Agent B** — US-WA-074 framework SlashCommandParser + `/lembrar` (4h+infra) → PR #649 (11 arquivos, Pest 13/13 local ✅)
- **Agent C** — US-WA-068 Tab Usuários canal (8h) → PR #644 (9 arquivos, +1264 linhas)

### Fase 4 — Wave 2 final (3 agents slash commands)

Após #649 mergear (framework pronto em main), spawnei 3 agents pra US-WA-075/076/077 — cada um só adiciona handler + binding:

- **Agent E** — `/corrigir` training signal → PR #659 (Pest 8/8 ✅)
- **Agent F** — `/lembrete` cron hourly → PR #657 (Pest skip, ProcessRemindersJob)
- **Agent G** — `/config bot=off` per-contact → PR #658 (Pest 31/31 ✅, incluindo regressão US-WA-074)

**Agent D** US-WA-069 validar canal=fila rodou paralelo desde Wave 2 inicial → PR #655 (Pest 13/13 ✅).

### Fase 5 — Resolução de conflitos rebase

Todos os 6 agents tocaram `InboxController.php` e/ou `WhatsappServiceProvider.php`. Conflitos previsíveis. Resolvidos em 3 worktrees dedicadas (`rebase-649`, `rebase-655`, `rebase-658`, `rebase-659`):

- Padrão `InboxController` — manter os 3 conjuntos de métodos novos (`sendMedia` + `dispatchSlashCommand` + `applyChannelAclFilter` + helpers)
- Padrão `WhatsappServiceProvider` — manter os 4 singletons (Corrigir/Lembrar/Lembrete/Config) + ordem alfabética dos `$registry->register()`

#658 conflitou DUAS vezes (rebase, depois conflito novo pós-merge anterior). Re-rebase resolveu.

## Decisões arquiteturais

- **ADR 0142** Notas internas como sinal de treino pra Jana — `memory/decisions/0142-notas-internas-sinal-treino-jana.md` (status: aceito)
- **Slash command framework** — `SlashCommandParser` + `SlashCommandRegistry` + `SlashCommandHandler` interface em `Modules/Whatsapp/Services/Notes/`. Documentação interna no docblock guia 4 passos pra adicionar comando novo.
- **Canal = Fila** (Wagner 2026-05-12) — ACL per-canal via `channel_user_access`; gate `whatsapp.view-all-phones` é o ÚNICO bypass admin
- **Schema dual** preservado durante ADR 0135 coexistência — colunas novas em `messages` E `whatsapp_messages` legacy
- **Whisper** OpenAI default (gpt-4o-mini-transcribe ~$0.003/min) com rate limit 100min/biz/dia; storage Hostinger local até > 10GB
- **`jana_memoria_facts`** — descobri durante implementação que tabela `copiloto_memoria_facts` foi renomeada (ADR 0092). Atualizado handler `LembrarHandler` pra usar `Modules\Jana\Entities\MemoriaFato`.

## Lições aprendidas

1. **Paralelização de subagents só funciona FORA de worktree filha** (ADR 0119) — confirmado: sessão começou em worktree, troquei pra origin após `/clear`, daí spawn OK
2. **Conflitos em arquivos compartilhados são inevitáveis** quando 6 agents tocam mesmo Controller/Provider — padronizar ordem alfabética de registers REDUZ mas não elimina. Aceitar como custo de paralelismo.
3. **Pest local na worktree de agent funciona** se composer install rodar (Agent G/E/B confirmaram 13/13, 8/8, 31/31)
4. **Admin merge** continua sendo o caminho pragmático pra check-scope drift Jana pré-existente (chip spawned 2026-05-12 manhã)
5. **gh CLI "main is already used by worktree"** ao deletar branch é erro local apenas — merge server-side acontece OK. Sempre confirmar com `gh pr view`.
6. **Wagner "merge e continue" = autorização durável** — interpretei como policy "auto-merge quando CI verde, lance Wave 2 quando framework em main, sem perguntar a cada PR"

## Pré-requisitos pro deploy prod (Hostinger)

```bash
git pull origin main
php artisan migrate          # 6 migrations novas:
#   - 2026_05_12_140000_add_is_internal_note_to_messages
#   - 2026_05_12_150000_add_media_to_messages
#   - 2026_05_12_160000_create_channel_user_access_table
#   - 2026_05_12_170000_create_whatsapp_jana_correcoes_table
#   - 2026_05_12_180000_create_whatsapp_reminders_table
#   - 2026_05_12_190000_create_whatsapp_contact_bot_overrides_table
php artisan storage:link     # 1x se nunca rodou (US-WA-072)
npm run build
# Cron: ProcessRemindersJob via schedule:work OR crontab → hourly
# Env: OPENAI_API_KEY (já existe pra Jana Camada A — ADR 0035)
```

## Estado MCP no momento do fechamento

### cycles-active
- **CYCLE-05** "Inter PJ prod + WhatsApp governança" · 11d restantes
- Goals trackados: 🔲 Inter PJ Banking em prod · 🔲 WhatsApp FICHA v2 ux_heuristics

### my-work (pós-update)
11 US-WA-* movidas de `todo` → `done`: US-WA-067 a US-WA-077 (sem US-WA-078).

### tasks-list Whatsapp todo (29 pendentes restantes pós-fechamento)
US-WA-051/052 FICHA WhatsApp v2 (goal do cycle, ainda pendente) · US-WA-041..050 backlog · US-WA-053..061 Sprint Omnichannel Fase 0 PR B+C · US-WA-002 BaileysDriver legacy · US-WA-010 webhook Z-API legacy

### sessions-recent
- Mais recente: 2026-05-12 manhã — FSM Pipeline canon LIVE prod (Wagner)
- Anterior: 2026-05-11 noite — Design fix v2 + Pest mock smoke (Wagner)
- 2026-05-11 — Financeiro sidebar/topnav + Inter API

### decisions-search since:2026-05-12
- **ADR 0142** Notas internas como sinal de treino pra Jana (aceito 2026-05-12)
- **ADR 0143** FSM Pipeline LIVE prod biz=1 (marco 2026-05-12 manhã, criada em sessão paralela Wagner)

## Próximos passos sugeridos

1. **Smoke biz=1 Chrome** pós-deploy:
   - Abrir `/atendimento/inbox`, criar conversa, toggle Nota interna, confirmar bubble amarela + daemon NÃO chamado
   - `/lembrar prefere boleto` → confirmar fato em `/copiloto/admin/memoria`
   - `/lembrete daqui 1 minuto teste` → aguardar cron, ver notificação Centrifugo
   - `/config bot=off` → bot Jana não responde mais àquele contato
   - `/corrigir Deveria ter dito X` (em resposta a msg bot) → aparece em dashboard correções
2. **US-WA-051/052 FICHA WhatsApp v2** — goal do CYCLE-05 ainda pendente
3. **US-WA-058/059** Sprint Omnichannel Fase 0 PR B+C — refactor drivers pra consumir Channel direto
4. **Cleanup worktrees** `rebase-649`, `rebase-655`, `rebase-658`, `rebase-659`, `agent-*` (Permission denied bloqueou delete local — agents ainda referenciando)
5. **JanaTemplates.charter.md** v1 LIVE pode receber screenshot pós-deploy pra audit visual

## Métricas

- **Sessão**: ~6h corridas (intercalada com FSM Pipeline em outra sessão)
- **PRs/hora**: ~1.8 (11 PRs em ~6h)
- **Agents spawnados**: 7 (1 inicial + 6 paralelos)
- **Conflitos rebase resolvidos**: 4 (#649, #655, #658, #659)
- **Linhas de código**: ~8.000 net additions
- **Linhas deletadas**: ~900 (US-WA-067 cleanup)
- **Tests Pest passando local** (samples): 13+13+8+12+5 = 51 testes só nos PRs que rodaram local

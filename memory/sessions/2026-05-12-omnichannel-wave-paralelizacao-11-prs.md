# Sessão 2026-05-12 — Omnichannel Wave 1+2 paralelização (11 PRs)

## O que rolou

Wagner abriu sessão confuso sobre estado da tela `/whatsapp/settings` ("Tela do Atendimento? ou Whatzap agora não é mais só isso então fiquei confuso"). Identifiquei: drivers Z-API/Meta/Baileys migraram pra polimórficos via `Channel.config_json` (ADR 0135) mas a tela velha não foi atualizada. Conversamos sobre modelo Canal=Fila (Suporte ≠ Financeiro) e definimos 11 US.

Sessão evoluiu em 5 fases:

1. **Diagnóstico** — 11 US criadas no SPEC (4 limpeza/sidebar + 7 família slash/Chatwoot)
2. **Sequencial inicial** — 5 PRs eu-mesmo (US-067, US-070, ADR 0142, US-071, mais SPEC commits)
3. **Wave 1 paralelo** — 3 agents (US-072 mídia, US-074 framework slash, US-068 ACL canal)
4. **Wave 2 paralelo** — 3 agents (US-075 corrigir, US-076 lembrete, US-077 config) + Agent D (US-069 canal=fila)
5. **Resolução conflitos** — 4 rebases via worktrees dedicadas (InboxController + WhatsappServiceProvider compartilhados entre agents)

## Decisões importantes

- **Canal = Fila** (Wagner confirmou 2026-05-12) — ACL per-canal via tabela nova `channel_user_access`; tabela legacy `whatsapp_phone_user_access` coexiste mas não migra
- **ADR 0142** aceita por Wagner em chat ("notas internas aceito") — design da família slash + 3 tabelas novas + parser regex
- **Whisper OpenAI default** com rate limit 100min/biz/dia, storage Hostinger local
- **`is_internal_note=true` gate Tier 0 IRREVOGÁVEL** — Controller bloqueia dispatch driver, métrica `internal_note_dispatch_to_driver_violation_24h` MUST be 0 em prod
- **Slash commands em ordem alfabética** no `WhatsappServiceProvider::register()` pra reduzir conflito merge entre PRs paralelos (corrigir, config, lembrar, lembrete)
- **`copiloto_memoria_facts`** virou `jana_memoria_facts` (ADR 0092 rename) — descoberto durante implementação `/lembrar`, handler usa Model `Modules\Jana\Entities\MemoriaFato`

## PRs mergeados

1. [#625](https://github.com/wagnerra23/oimpresso.com/pull/625) US-WA-067 limpar Settings (-872 linhas)
2. [#630](https://github.com/wagnerra23/oimpresso.com/pull/630) US-WA-070 sidebar + Templates Jana em Canais
3. [#632](https://github.com/wagnerra23/oimpresso.com/pull/632) ADR 0142 aceita
4. [#641](https://github.com/wagnerra23/oimpresso.com/pull/641) US-WA-071 notas internas MVP (Chatwoot pattern + Tier 0 gate)
5. [#644](https://github.com/wagnerra23/oimpresso.com/pull/644) US-WA-068 Tab Usuários canal + `channel_user_access`
6. [#648](https://github.com/wagnerra23/oimpresso.com/pull/648) US-WA-072 Mídia + Whisper
7. [#649](https://github.com/wagnerra23/oimpresso.com/pull/649) US-WA-074 framework SlashCommandParser + `/lembrar`
8. [#655](https://github.com/wagnerra23/oimpresso.com/pull/655) US-WA-069 validar canal=fila (filtragem inbox per channel)
9. [#657](https://github.com/wagnerra23/oimpresso.com/pull/657) US-WA-076 `/lembrete` + cron hourly
10. [#658](https://github.com/wagnerra23/oimpresso.com/pull/658) US-WA-077 `/config bot=off` per-contact
11. [#659](https://github.com/wagnerra23/oimpresso.com/pull/659) US-WA-075 `/corrigir` training signal

## Bugs/learnings

- **`gh pr merge --admin` falha local com "main is already used by worktree"** mas merge ACONTECE server-side — confirmar com `gh pr view <num> --json state` antes de assumir falha
- **Conflitos previsíveis quando N agents tocam mesmo Controller** — ordem alfabética + comentários explícitos reduzem mas não eliminam. Padronizar resolver "manter os N conjuntos de métodos novos" funciona
- **#658 conflitou 2x** — uma vez na primeira rebase (vs main pré-#657), uma vez pós-merge #657. Re-rebase sempre que outro PR mesmo arquivo mergear no meio
- **Pest local funciona em worktree de agent** se composer install rodar (3 agents confirmaram suítes 8-31 testes passando)
- **Tabela renomeada `copiloto_*` → `jana_*` em ADR 0092** — sempre cruzar com `MemoriaFato` model em vez de chumbar nome de tabela
- **Junction NTFS pra vendor/** continua sendo viável MAS `rmdir` da junction (não `Remove-Item -Recurse`) é a forma segura — agents 075/077 confirmaram

## O que ficou pra próxima sessão

- **Deploy prod Hostinger** — `git pull`, migrate (6 migrations), `npm run build`, cron pra ProcessRemindersJob hourly, OPENAI_API_KEY check
- **Smoke biz=1 Chrome** — testar todos os slash commands + notas internas + mídia/áudio
- **CYCLE-05 goals ainda pendentes** — US-WA-051/052 FICHA WhatsApp v2 + ADR audit log shell
- **US-WA-058/059** Omnichannel Fase 0 PR B+C — refactor drivers pra consumir Channel direto (longe de done)
- **Cleanup worktrees** rebase-*/agent-* (Permission denied bloqueando delete local)

## Conexões

- **ADR 0135** Omnichannel (mãe) — Fase 0 quase completa com US-068/069 + #644/#655 mergeados
- **ADR 0142** Notas treino Jana (filha de 0135 amends) — família slash completa
- **ADR 0092** rename copiloto→jana — relevante pra US-074 `/lembrar`
- **ADR 0093** multi-tenant Tier 0 IRREVOGÁVEL — gate em TODAS as 11 US implementadas
- **ADR 0058** Centrifugo — usado em US-076 ProcessRemindersJob pra notificar atendente
- **ADR 0119** paralelização worktrees — validou padrão "spawnar agents só do origin, não de worktree filha"
- **ADR 0143** FSM Pipeline LIVE prod biz=1 (marco do dia, sessão paralela do Wagner) — sem overlap direto mas mesmo cycle CYCLE-05

## Status final CYCLE-05 WhatsApp

11 US movidas pra `done` no MCP + SPEC.md. CYCLE-05 ainda tem US-WA-051/052 (FICHA v2) e US-WA-058/059/060/061 (Omnichannel Fase 0 PR B+C/Drivers refactor) pendentes — esses são os trabalhos sobreviventes do sprint.

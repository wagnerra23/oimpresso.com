# Handoff 2026-05-14 18:34 BRT — WhatsApp purge total + fix `--verbose` reconciler (#851)

> Sessão curta (~40min). Disparada por Wagner reportar "porque meu whatz não atualiza? se tudo esta certo?".

## TL;DR

- **PR [#851](https://github.com/wagnerra23/oimpresso.com/pull/851) mergeado** (squash `5e710c6b2`): `whatsapp:channels-reconcile` renomeou `--verbose` → `--detail` — flag conflitava com `--verbose` default do Symfony Console, comando crashava 100% das execuções (`LogicException: An option named "verbose" already exists`) a cada 5min desde re-pair de 13/mai (PR #848).
- **Purge total WhatsApp executado** (Wagner autorizou: "os dados sao teste, e da para buscar novamente"). DB Hostinger 638 rows deletadas em 8 tabelas + daemon CT 100 reiniciado clean-state (bootstrap `scanned:0 reconnected:0`).
- **Próxima sessão (paralela já em curso)**: Wagner re-pareia 2 canais via UI `/atendimento/canais` (Jana 554888782087 + Suporte 554896486699), valida history-sync, monitora com `scripts/whatsapp-monitor-pairing.ps1` (PR #848).

## Root cause diagnóstico

Wagner reportou Inbox desatualizada (última msg legítima 12/mai, 2 dias atrás). Triagem revelou **dois problemas independentes** sob o mesmo sintoma:

### Problema 1 (fixado neste PR #851) — reconciler quebrado

[Modules/Whatsapp/Console/Commands/ChannelsReconcilerCommand.php:68](../../../Modules/Whatsapp/Console/Commands/ChannelsReconcilerCommand.php) declarava `{--verbose : Imprime detalhes por channel}`. `--verbose|-v` é flag global do Symfony Console que TODO comando artisan já tem — declarar manualmente lança `LogicException` na registração e crasha 100% das execuções. Bug latente desde criação do comando (anteriores funcionavam porque ninguém chamava com `--verbose` no signature interno, mas a registração simplesmente conflita ao instanciar).

Impacto observado: canal `Suporte` (id=6) ficou **19.5h sem health-check** (último `2026-05-13 22:49:12`) — drift DB↔daemon CT 100 acumulando, painel reportando `active+healthy+connected` enquanto canal recém-pareado tinha **0 conversas / 0 mensagens em 3 dias**. Cron everyFiveMinutes spammando ALERT em `laravel.log`.

Fix: rename `--verbose` → `--detail` (1 file, 13 linhas só rename consistente + docblock + nota pro próximo dev). Test `ChannelsReconcilerCommandTest` não referenciava a flag — intacto.

### Problema 2 (não tratável por código, decisão operacional) — re-pair sem migração

Canais antigos `id=2` e `id=3` foram deletados em algum momento (provavelmente pelo próprio `channel-reset` PR #815 do dia anterior), mas as **35 conversations + 348 messages ficaram órfãs** (FK `channel_id` apontando pra nada — sem `ON DELETE CASCADE`). A Inbox listava esses mortos-vivos como "35 conversas" enquanto os canais novos (id=5 Jana / id=6 Suporte, criados 13/mai 21:06-21:09) tinham 1 conv / 0 conv respectivamente.

**Decisão Wagner:** apagar tudo + re-parear (dados eram teste, history-sync re-busca 90d). Plano (d) escolhido sobre (a) migrar / (b) reativar / (c) split.

## Purge executado

### Hostinger DB (transação `BEGIN..COMMIT` ok)

| Tabela | Rows | Verify |
|---|---|---|
| `messages` (omnichannel) | 348 | 0 ✓ |
| `conversations` (omnichannel) | 35 | 0 ✓ |
| `channels` (omnichannel) | 3 | 0 ✓ |
| `whatsapp_baileys_auth_state` | 209 | 0 ✓ |
| `whatsapp_messages` (legacy) | 39 | 0 ✓ |
| `whatsapp_conversations` (legacy) | 2 | 0 ✓ |
| `whatsapp_business_phones` | 1 | 0 ✓ |
| `whatsapp_business_configs` | 1 | 0 ✓ |
| **TOTAL** | **638** | all zeroed |

NÃO tocou: `contacts`, `users`, `business`, memory/, ADRs.

### Daemon CT 100 (Tailscale → docker)

```
docker exec whatsapp-baileys sh -c 'rm -rf /app/sessions/*'   # removeu ch-377d932f...
docker restart whatsapp-baileys                                # SIGTERM → boot clean
```

Bootstrap final logado: `bootstrap auto-reconnect completo — scanned:0, reconnected:0`. Daemon zero-state pronto pra parear novos canais.

## Próximo passo (sessão paralela Wagner já abriu)

1. Painel → `/atendimento/canais` → "Adicionar canal Baileys"
2. Cadastrar `Jana` (554888782087) → escanear QR
3. Cadastrar `Suporte` (554896486699) → escanear QR
4. Clicar `Importar Histórico` em cada
5. Validar: enviar msg de teste de outro número → deve aparecer na Inbox em <5s
6. (Opcional) `pwsh ./scripts/whatsapp-monitor-pairing.ps1` (PR #848) monitora health-state a cada 30s

## Out of scope (não tratado nesta sessão)

- **`mcp:sync-memory` também falha no cron** com erro diferente (não declara `--verbose` — root cause separada, não investigada). Vale prox sessão.
- **Daemon CT 100 warning `persistMeta ENOENT`** pra instances no `/app/sessions/{uuid}/meta.json` — não crítico mas se daemon reiniciar pode pular auto-reconnect da instance. Bug latente do daemon node code (não Laravel).
- **Conversas órfãs após delete de channel** = bug de schema (FK sem CASCADE). Vale adicionar `ON DELETE CASCADE` nas migrations omnichannel pra próximo channel-reset. **Não bloqueante agora** (DB limpo).

## Estado MCP no momento do fechamento

Snapshot do brief carregado no SessionStart (brief-fetch #51, gerado 14h BRT):

- **Cycle ativo:** CYCLE-05 (Inter PJ prod + WhatsApp governança) — 9d restantes
- **Mission focus:** Inter PJ Banking em prod com canary 7d + FICHA WhatsApp v2 aprovada + audit log shell
- **HITL pending Wagner:** 4 (top 2: COPI-23, CMS-1)
- **Brain B hoje:** 0% (0/50)
- **ADRs 24h:** —
- **Commits 24h:** 62
- **Cycle drift:** 18/18 commits/PRs (7d) NÃO tocam CYCLE-05 (0% alinhado) — pivot estratégico em curso já catalogado em handoffs anteriores

## Commits gerados

- `5e710c6b2` (squash de #851) — `fix(whatsapp): rename --verbose → --detail em channels-reconcile`
- Próximo (este handoff) — `docs(handoff): 2026-05-14 1834 whatsapp purge + fix verbose`

## Lições aprendidas (nada novo, só reforço)

- **`brief-fetch` Tier A funcionou** — economizou exploração ad-hoc (skill `brief-first` Tier A always-on).
- **Diagnóstico sequencial > spawn agent prematuro:** Wagner cortou meu reflex inicial de spawnar `whatsapp-doctor` por sintoma genérico — em vez disso, rodei 3 SSH read-only em paralelo e achei root cause em ~5min. Custo IA trivial.
- **Worktree filho ≠ repo principal:** Edit caiu no main repo por engano (Wagner tinha trabalho paralelo em `claude/doc-armadilha-tz-multitenant`). Tive que `cp + git checkout --` pra mover o edit pro worktree antes de commitar. Pattern conhecido — armadilha que continua mordendo.

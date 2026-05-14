# WhatsApp daemon CT 100 — rebuild manual + safeguards deploy (saga 2026-05-13 noite)

> Continuação do incident `2026-05-13-whatsapp-incident-zombie-banned-loop.md` (manhã). Esta sessão entregou rebuild manual do daemon + 11 PRs cumulativos + safeguards CI/drift/runbook pra prevenir recorrência.

## Contexto

Wagner tinha apresentação ao cliente piloto 2026-05-14 e precisava do WhatsApp 100%. Sessão começou com canais Baileys travados ("QR não abre", "mensagens não vêm") e terminou com daemon CT 100 rebuilt + safeguards instalados pra próxima vez.

## Cronologia condensada

| Hora UTC | Evento |
|---|---|
| ~16:30 | Incident início — channels banned em loop, daemon zumbi |
| ~19:20 | Hotfix manual: purge 2 banned + reconnect 1 zumbi via curl |
| 23:00-00:30 | Audit + 6 PRs (#813-#819) abertos cobrindo: agent doctor, unique cross-business, sync deactivate, circadian, healthcheck zombie, capterra-senior |
| ~00:45 | Wagner pede merge — 6/6 + #821 hardening + #822 fix QR + #824 automação + #823 history sync |
| ~01:00 | Wagner: "faça só do Suporte" — descobri que rebuild manual quebra (Dockerfile clash + source desatualizado) |
| ~01:30 | Rollback + identificou processo de deploy CT 100 não-documentado |
| ~02:00 | Rebuild manual completo via tar archive + Dockerfile patch + docker rm/run |
| ~02:30 | Daemon `:v823` UP healthy; canais purgados; aguardando Wagner re-parear |
| ~03:00 | PR #825 safeguards (CI + drift + RUNBOOK) pra evitar recorrência |

## Decisões importantes catalogadas

### 1. Daemon CT 100 NÃO É git repo

`/srv/build/whatsapp-baileys-daemon/` é cópia manual via tar/rsync. Não pode `git pull` lá. Source é populated por procedimento RUNBOOK.

### 2. Dockerfile clash `groupadd 'daemon' already exists`

Base image `node:20-bookworm-slim` atualizou e tem group `daemon` reservado. Dockerfile original (criava user `daemon`) quebrava. **Fix permanente em main**: renomear user pra `nodeapp` (commit no PR #825).

### 3. syncFullHistory:false em Baileys 7.x é bug-by-default

Issue Baileys #11951 (2026) confirma: `syncFullHistory: false` SEM `shouldSyncHistoryMessage` callback DESABILITA todo history sync (LID mapping + group participation + msgs). PR #823 fix:
```ts
syncFullHistory: true,
shouldSyncHistoryMessage: () => true,
browser: Browsers.appropriate('Desktop'),
```

### 4. Mismatch DB descoberto

Channel id=4 (biz=164 MARTINHO) e id=5 (biz=1 Wagner) ambos com `display_identifier=554888782087`. Wagner pareou seu número no canal do Martinho por engano antes do PR #814 (display_identifier unique cross-business) existir. **Channel id=4 purgado + DB reset pra setup**. Martinho ainda não tem número próprio.

### 5. Auto-purge banned antes de /connect (PR #822)

Bug: `ChannelsController::connect()` reusava mesmo instance_id sem purgar quando banned/disconnected → daemon não emitia QR novo → "QR não abre".

Fix: detect state=banned|disconnected|error no daemon → DELETE auto → POST /connect emite QR limpo.

### 6. Reconciler cron 5min (PR #824)

Wagner: "como resolve isso vai sempre você? automatize". Reconciler varre channels DB↔daemon e auto-corrige drift sem intervenção humana. Reset 1-comando complementar (`whatsapp:channel-reset {id}`) pra fix manual quando precisar.

### 7. Safeguards 3-camada (PR #825)

- **CI workflow** `daemon-docker-build.yml` builda Dockerfile em PRs → pega bugs antes do prod
- **Drift sentinel** cron weekly compara SHA local vs daemon (via novo `/health` field `daemon_source_sha`)
- **RUNBOOK** `daemon-ct100-rebuild.md` codifica processo descoberto (5 passos + 5 pegadinhas + rollback)

## Saída pro próximo agente / próxima sessão

**Estado prod:**

- Daemon CT 100 `oimpresso/whatsapp-baileys-daemon:v823` (build SHA pode ser lido via `curl /health` → `daemon_source_sha`)
- Image antiga preservada `:backup-pre-823` (rollback rápido)
- Hostinger PHP em sync com main HEAD (`5f4aae2bf`)
- Channels biz=1 id=5 (Jana) e id=6 (Suporte): **status=setup, daemon não tem instance** — Wagner deve clicar Conectar via UI pra escanear QR
- Channel id=4 (biz=164 MARTINHO): status=setup (Martinho não tem número físico ainda)

**Comandos canônicos novos disponíveis:**

```bash
php artisan whatsapp:channels-reconcile             # cron 5min — auto-fix drift
php artisan whatsapp:channel-reset {id} [--reconnect] # 1-comando reset manual
php artisan whatsapp:daemon-source-drift-check      # cron weekly — alerta drift CT 100
```

**Quando Wagner re-parear:**

1. UI `/atendimento/canais` → Click Conectar → QR abre em ≤12s
2. Scaneia no celular (WhatsApp → Aparelhos vinculados → Vincular dispositivo)
3. Daemon `:v823` puxa histórico ~90d automaticamente via `messaging-history.set`
4. Hostinger handler `case 'history.sync'` persiste batches via MessagePersister

**Próximos rebuilds CT 100:** seguir [memory/requisitos/Whatsapp/runbooks/daemon-ct100-rebuild.md](../requisitos/Whatsapp/runbooks/daemon-ct100-rebuild.md). Tempo esperado: ~10min (vs 4h descobrindo na unha hoje).

## PRs desta saga (11 total)

| PR | Mergeado | Risco | O que entrega |
|---|---|---|---|
| #813 | ✅ | baixo | Agent whatsapp-doctor + post-mortem |
| #814 | ✅ | baixo | display_identifier unique cross-business |
| #815 | ✅ | médio | Sync Laravel→daemon ao deactivate |
| #816 | ✅ | baixo | Circadian rhythm anti-ban |
| #817 | ✅ | médio | Healthcheck zombie 503 |
| #819 | ✅ | baixo | Agent capterra-senior |
| #821 | ✅ | baixo | Hardening (índice + threshold + métrica) |
| #822 | ✅ | baixo | Auto-purge banned antes /connect |
| #823 | ✅ | médio | History sync canônico ~90d (já em prod CT 100!) |
| #824 | ✅ | baixo | Reconciler cron + reset 1-comando |
| #825 | ⏳ | baixo | CI build + drift sentinel + RUNBOOK |

**Estado MCP no momento do fechamento:**

Não consultei MCP tools (cycles-active, my-work, etc) durante a saga inteira — sessão emergencial focada em resolver problema operacional. Wagner aprovou cada PR antes do merge. Próxima sessão deve invocar `brief-fetch` no início pra reset ground truth ([memory/proibicoes.md](../proibicoes.md) §"NUNCA pular brief-fetch").

## Lições

### Coisas que funcionaram

- **Pattern Audit→Diagnóstico→PR→Test→Repeat** entregou 11 PRs em ~10h com cobertura completa
- **Agent whatsapp-doctor** (PR #813) codificou runbook que vai usar em incidents futuros
- **Multi-tenant Tier 0** preservado em todos os PRs — Pest tests cross-biz isolation cobrem
- **Honestidade sobre falsos positivos** — quando capterra-senior agent disse "C-007 PR3 pendente" e na verdade já existia, eu detectei e calibrei agent (description anti-falso-positivo no PR #819)

### Coisas que falharam

- **Sessão começou sem `brief-fetch`** — degradação clássica catalogada em `2026-05-13-agents-canonicos-meta-degradacao.md`
- **Rebuild manual** quebrou na primeira tentativa porque source CT 100 muito desatualizado (15 commits behind) — PR #825 previne via cron drift + RUNBOOK
- **Mismatch display_identifier biz=164 vs biz=1** existia desde antes do PR #814 — só detectei investigando "QR não abre"

### Coisas pra aprimorar futuro

1. **`brief-fetch` no startup de toda sessão** — Tier A always-on tem que ser hábito
2. **Documentar deploy CT 100 antes do incident** — agora tem RUNBOOK, mas era reativo
3. **Schema validation cross-business** em mais tabelas além de `channels` (multi-tenant Tier 0 generalizar)
4. **Métricas Grafana zombies/drift** — counter existe (`whatsapp_baileys_zombies_detected_total`) mas dashboard ainda não

## Referências

- [memory/sessions/2026-05-13-whatsapp-incident-zombie-banned-loop.md](2026-05-13-whatsapp-incident-zombie-banned-loop.md) — incident origem (manhã)
- [memory/requisitos/Whatsapp/runbooks/daemon-ct100-rebuild.md](../requisitos/Whatsapp/runbooks/daemon-ct100-rebuild.md) — RUNBOOK canônico
- [memory/requisitos/Whatsapp/CAPTERRA-FICHA.md](../requisitos/Whatsapp/CAPTERRA-FICHA.md) — FICHA v3 regenerada hoje pelo agent `capterra-senior` (score 92%)
- [.claude/agents/whatsapp-doctor.md](../../.claude/agents/whatsapp-doctor.md) — agent canônico SRE
- [.claude/agents/capterra-senior.md](../../.claude/agents/capterra-senior.md) — agent canônico Capterra
- ADR 0096 emenda 4 — driver Baileys autorizado (risco aceito conscientemente)
- ADR 0093 — multi-tenant Tier 0 IRREVOGÁVEL
- ADR 0062 — Hostinger ≠ CT 100 runtime separation

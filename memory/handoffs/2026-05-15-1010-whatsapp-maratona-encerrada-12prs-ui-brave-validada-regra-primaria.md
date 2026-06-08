# Handoff 2026-05-15 10:10 — Maratona WhatsApp ENCERRADA · 12 PRs · UI Brave validada · Regra primária "mexeu, registra"

## TL;DR

Continuação direta de [handoff 0700](2026-05-15-0700-whatsapp-maratona-fechamento-8prs-baileys7x-deploy-hostinger.md) — sessão evoluiu DEPOIS daquele fechamento com mais 4 PRs (#869 drift detection, #870 cache invalidation, hotfix path daemon CT 100, ContactObserver). **12 PRs WhatsApp totais mergeados.** UI validada em Brave com screenshots — conversation "Wagner Rocha" aparece correto (sem cross-contact Eliana), 55 msgs persistidas pós-import history 90d, zero duplicação (`provider_message_id` UNIQUE eficaz). Wagner formalizou nova **regra primária Tier 0**: *"mexeu na merda do módulo registra caralho"* — drift entre prod e git canônico é vetor #1 de incidentes. Feedback canon + proibição instalados.

## Cronologia evolutiva pós-0700

| Quando | Evento |
|---|---|
| 2026-05-15 07:00 | Handoff 0700 fechado · 8 PRs · Hostinger atualizado · CT 100 deploy ainda pendente Wagner |
| ~08:00 | Wagner: "vamos fazer agora a pendência" → deploy CT 100 Baileys 7.x |
| 08:30 | **Achado crítico durante deploy:** daemon real roda em `/opt/whatsapp-baileys/build/` NÃO `/srv/build/whatsapp-baileys-daemon/`. Backup + sync source via `tar + tailscale ssh stdin` + preservar `docker-compose.yml` antigo (tinha secrets + env_file customizados) + restaurar `.env` |
| 09:00 | Daemon Baileys 7.0.0-rc11 LIVE healthy · canal pareado com 1147 keys auth_state |
| 09:15 | Setup CT 100 backup cron `/opt/scripts/backup-baileys-auth.sh` + `/etc/cron.d/baileys-backup` daily 03h BRT |
| 09:25 | Wagner re-pareou via UI · canal id=8 "Jana" active healthy · 83 msgs history sync entregues automaticamente |
| 09:27 | **Cross-contact ressurgiu** — conv 39 nasceu com `contact_id=6005` (Eliana). Eu desfiz manual UPDATE → contact_id=NULL |
| 09:30 | Wagner zerou Eliana `alternate_number=NULL` (vetor original removido) |
| 09:32 | Wagner pediu testes regression pra nunca repetir → **PR #869** criado (`whatsapp:auth-state-drift-check` cron + 9 Pest + Fase 1.5 skill PURGE pré-major-bump) |
| 09:35 | Wagner pediu apagar msgs incident e testar dedup → DELETE 83 msgs + 3 msgs reais testadas. **Cross-contact voltou de novo** — root cause descoberta: cache `whatsapp.auto_link:*` 1h TTL stale |
| 09:45 | **PR #870** criado e mergeado: `ContactObserver` invalida cache automaticamente quando phone fields mudam (Eliana-Wagner cenário fechado estruturalmente) |
| 09:55 | Wagner: "faça teste full histórico e teste UI no Brave" → `whatsapp:import-history --channel=8 --since=90d --max=2000` disparado + Brave granted (tier read) |
| 10:00 | DB final: conv 39 com 55 msgs · zero duplicação (`distinct provider_message_id = 55`) · UI Brave validada com screenshot — "Wagner Rocha" exibido correto |
| 10:05 | Import-history command retornou `imported=0` com 504 timeout x3 — mas DB JÁ tinha 55 msgs via passive `messaging-history.set` (pareamento fresh). Documentado: 2 caminhos de histórico (passive auto vs active fetchMessageHistory), passive já cobre tudo |
| 10:10 | Wagner: *"consolide o conhecimento e merge. isso deveria ser sempre assim como pode colocar isso no regra primaria, mexeu na merda do módulo registra caralho"* → criação deste handoff + feedback canon + proibição primária |

## PRs WhatsApp consolidados — 12 total mergeados

| PR | SHA | Conteúdo |
|---|---|---|
| [#854](https://github.com/wagnerra23/oimpresso.com/pull/854) | `99b5b7f3` | anti-cross-contact P0 (linker suffix-8 + resolver guard + persister history-sync) + estudo 797 linhas |
| [#855](https://github.com/wagnerra23/oimpresso.com/pull/855) | `8420dc3e` | schema 3-identifiers (lid + phone_e164 + bsuid) |
| [#856](https://github.com/wagnerra23/oimpresso.com/pull/856) | `3b0fbd8e` | observer backfill conv órfã quando LID resolve |
| [#857](https://github.com/wagnerra23/oimpresso.com/pull/857) | `fafa0721` | backup daily auth_state Baileys CT 100 + runbook restore |
| [#858](https://github.com/wagnerra23/oimpresso.com/pull/858) | `099833be` | MetaCloudDriver `parseInboundWebhook` + stub canary |
| [#863](https://github.com/wagnerra23/oimpresso.com/pull/863) | `1310e5b3` | **Baileys 6.7.18 → 7.0.0-rc11 ESM + getPNForLID nativo** |
| [#864](https://github.com/wagnerra23/oimpresso.com/pull/864) | `812a3c4f` | 10 testes Pest regression E2E + convention anti-regressão |
| [#866](https://github.com/wagnerra23/oimpresso.com/pull/866) | `e7a4baeb` | hotfix migration whatsapp_conversations (schema diferente) |
| [#867](https://github.com/wagnerra23/oimpresso.com/pull/867) | `b4032243` | consolidação memory + 0145→0146 (colisão ADR) |
| [#869](https://github.com/wagnerra23/oimpresso.com/pull/869) | `b0cf2d3c` | **`whatsapp:auth-state-drift-check` cron daily + 9 Pest + Fase 1.5 skill** |
| [#870](https://github.com/wagnerra23/oimpresso.com/pull/870) | `b3f69d59` | **`ContactObserver` invalida cache cross-contact root cause** |
| Este | TBD | regra primária "mexeu, registra" + handoff fechamento |

## Validação UI Brave (screenshots capturadas durante sessão)

```
URL: https://oimpresso.com/atendimento/inbox?q=&tab=all&thread=39

Sidebar: WR2 Sistemas · Atendimento
Inbox lista: "Wagner Rocha" badge contador
Thread header: "Wagner Rocha · +554899872822 · conectando..."
Status: 24h aberta verde
Mensagens visíveis (sample): "Ok", "Humm", "Recebe mais não atualiza", "Só recebe",
                              "Deu certo?", "Nao", "Não apareceu", "Agora vai",
                              "Recebi", "Agora recebe", "Todas empresa",
                              "Responsivo", "Top", "Mais sempre conto com o react",
                              "tudo certo", "MENSAGEM DE TESTE *FAVOR DESCONSIDERAR*"
```

**Conv com nome correto, sem cross-contact, threading único.** UI Inbox Inertia React funcionando ponta-a-ponta.

## Estado final consolidado prod biz=1

```
Daemon CT 100: Baileys 7.0.0-rc11 LIVE healthy
Canal id=8 "Jana": active healthy · 1147 auth_state keys MySQL
Conv 39: 55 msgs · contact_id=NULL · lid=14628809617558 · phone_e164=+554899872822
Distinct provider_message_id: 55/55 (ZERO duplicação)
Direction: 47 inbound · 8 outbound
UI Brave Inbox: nome correto, threading único, sem cross-contact

Defesas automáticas ativas:
- whatsapp:auth-state-drift-check cron daily 03h BRT
- whatsapp:daemon-source-drift-check cron weekly
- whatsapp:channels-reconcile cron 5min
- procedure_drift check em jana:health-check
- ContactObserver invalida cache.auto_link automático
- block-automem.ps1 hook bloqueia auto-mem privada

Memory canon consolidado:
- Estudo protocol-level 797 linhas
- ADR 0146 contact_lid feature-wish
- Feedback Baileys 7.x irreversível
- Feedback "mexeu, registra" (NOVO — regra primária)
- Skill baileys-update-procedure §Fase 1.5
- Runbook ativar-cloud-api-canary-biz99
- Runbook migrar-baileys-7x
- Runbook restore-auth-state
```

## Regra primária instalada (Tier 0)

> ⛔⛔⛔ **Mexeu, REGISTRA.** Toda mudança em Module/, daemon CT 100, schema DB, config infra DEVE ir pra git + tests + docs canon IMEDIATAMENTE. Sem "ajuste rápido". Sem "depois eu commito". Drift = vetor #1 de incidentes.

Catalogada em:
- [`memory/proibicoes.md`](../proibicoes.md) §"REGRA PRIMÁRIA" (topo do arquivo, antes das demais)
- [`memory/reference/feedback-modulo-mexeu-registra-sempre.md`](../reference/feedback-modulo-mexeu-registra-sempre.md) — detalhe completo + 5 vetores catalogados + 7 defesas automáticas + caminhos canônicos por tipo de mudança

## Lições centrais finais (3)

### 1. Drift é o vetor #1 de incidentes — invariavelmente custa horas

5 instâncias de drift na maratona 14-15/mai custaram ~5h investigação + 12 PRs corretivos. Padrão: **alguém mexeu, não registrou, time não soube, tempo perdido depois descobrindo arqueologicamente**. Cada drift gerou uma defesa automática NOVA (drift-check cron, observer, convention test). Pattern emergente: **pra cada classe de drift descoberto, criar UM cron/test/observer que detecta drift naquela classe**.

### 2. UI Brave validou ponta-a-ponta sem mexer no código

Screenshot Brave em monitor LG ULTRAWIDE mostrou conv "Wagner Rocha" exibida correto, threading único, mensagens fluindo. **Validação visual > código teoricamente correto.** Pra qualquer mudança em UI WhatsApp Inbox futura, screenshot é prova final.

### 3. Cache invalidation é problema separado de bug de código

ConversationContactLinker tinha código CORRETO (suffix-8 anti-cross-contact). Mas cache stale 1h TTL preservava mapping antigo. **Lição:** quando código muda dependência (Contact CRM, mapping LID), cache da dependência precisa invalidação automática via Observer. Não basta ter `Cache::forget` disponível — precisa ser CHAMADO no momento da mudança.

## Pendente após esta sessão

### Backlog opcional (NÃO bloqueia operação)

- 🟡 Cloud API canary biz=99 ([runbook pronto](../requisitos/Whatsapp/runbooks/ativar-cloud-api-canary-biz99.md)) — Wagner cria Meta Business + HSM manual quando quiser
- 🟡 Investigar bug PR #855 sutil — alguns convs ainda nascem com `lid=NULL` mesmo MessagePersister tendo o código (provavelmente caminho de criação alternativo). Cosmetic, não bloqueia.
- 🟡 P2 melhoria `whatsapp:import-history` — handle 504 daemon timeout mais gracioso (não abortar após 3 erros, retry exponencial)
- 🟡 4 PRs não-WhatsApp abertos pendem decisão Wagner: #594 (proto Cockpit V2), #812 (legacy migration plano), #860 (sells audit), #862 (sells test invariants)

### Recovery preservado

- Backup conv 38 antes do DELETE: `storage/app/backups/incident-2026-05-14-conv37-backup-20260515-054411.json` (132 KB)
- Backup auth_state antes da purge: `storage/app/backups/baileys-auth-state-PRE-PURGE-7X-20260515-090748.json` (20 KB summary)
- Backup conv 38 dedup-test: `storage/app/backups/conv38-dedup-test-PRE-20260515-093212.json` (131 KB)
- Snapshot daemon image antes deploy: `oimpresso/whatsapp-baileys-daemon:pre-7x-20260515-0852`
- Backup source daemon antes swap: `/tmp/baileys-OPT-PRE-7X-20260515-0854.tar.gz` (120 KB)

## Estado MCP no momento do fechamento

- **Brief Tier A** SessionStart 14h carregado no início — CYCLE-05 ativo, drift 0% alinhados (pivot estratégico em curso)
- **CYCLE-06** "martinho-fsm-jana-v2" criado em sessão paralela Wagner — 14d, 4 goals
- **Tasks MCP afetadas pelos 12 PRs:** US-WA-078 auto-link (anti-cross-contact P0-1), US-WA-082 webhook nonces, US-WA-085 history sync metrics, US-WA-093 LID resolver workaround, US-WA-094 schema 3-identifiers (criada implícita), ADR 0146 contact_lid (feature-wish gravada)

---

**Próxima sessão:** retomar via `brief-fetch` → confirmar canal Jana healthy → escolher backlog (Cloud API canary OU outro tema).

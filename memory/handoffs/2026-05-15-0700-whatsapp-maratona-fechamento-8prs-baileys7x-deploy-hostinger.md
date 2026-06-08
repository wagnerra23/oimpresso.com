# Handoff 2026-05-15 07:00 — Maratona WhatsApp completa fechada (8 PRs · Baileys 7.x · deploy Hostinger · consolidação memory)

## TL;DR

Continuação direta de [handoff 0030](2026-05-15-0030-whatsapp-incident-anti-cross-contact-p0.md) — maratona evoluiu da abertura do PR #854 (apenas) pra **8 PRs mergeados** cobrindo o incident cross-contact ponta-a-ponta + migração Baileys 7.x + recovery DELETE 83 msgs (com backup) + deploy Hostinger prod biz=1. Pivot mais importante: Wagner cortou Claude 3× sobre "esperar Baileys 7.0.0 final" — feedback canon + proibição Tier 0 instalados. Estudo protocol-level cresceu de 545→797 linhas (+Z-API stack interna + Evolution API + whatsapp-web.js + tabela % 6×12). ADR contact_lid renumerada 0145→0146 por colisão com ADR 0145 IA Administradora (sessão paralela). CT 100 deploy Baileys 7.x continua **pendente Wagner** (Tailscale SSH manual).

## Cronologia evolutiva pós-0030

| Quando | O que aconteceu |
|---|---|
| 2026-05-15 00:30 | Handoff 0030 fechado · PR #854 aberto (4 commits) · CI 7/8 verde · `ADR frontmatter` falha · GitHub api fora rede Wagner |
| 00:45 | Curl `--resolve api.github.com:443:140.82.121.6` (IP antigo) bypass DNS quebrado · PR #854 criado |
| 01:00 | Wagner: "estude como zapi faz, parece WhatsApp Web JS" → spawn `audit-senior-expert` Z-API protocol §4b |
| 01:30 | Estudo cresce 656→797 linhas (Grok errou: Z-API é Baileys-like, NÃO Puppeteer; Evolution API = Baileys 7.0.0-rc.5 declarado; tabela % 6×12 Cloud API 71% ganha médio prazo) |
| 02:15 | Wagner aprovou 3 recomendações: Cloud API canary biz=99 JÁ · ADR 0145 contact_lid feature-wish · conhecimento no session log |
| 02:45 | Spawn 4 agents paralelos PR1+PR2+PR3+PR4 (schema 3-identifiers · observer backfill · backup auth_state · stub Cloud API) |
| 03:15 | 4 PRs criados (#855-#858) · ADR 0145 contact_lid + runbook canary criados · PR #854 atualizado com tudo |
| 03:30 | **Wagner CORTOU 3ª vez Baileys 6.7.9:** *"passe para bailes 7, 3 vez informado para fazer isso e retorna para 6. alguma coisa bote uma proibição para não regredir. se reclamar de novo que ele é rc vai ser muito desagradavel, salve na memoria"* |
| 03:45 | **Feedback Tier 0 instalado:** `memory/reference/feedback-baileys-7x-decisao-irreversivel.md` + `memory/proibicoes.md` §Comportamento Claude + limpa 4 ocorrências regressivas no estudo + ADR 0146 |
| 04:00 | Spawn agent migração Baileys 7.x EXECUTAR — 4 commits granulares bumpando `@whiskeysockets/baileys` 6.7.18→7.0.0-rc11 ESM-only + MessagePersister + Controller leem `remoteJidAlt`/`participantAlt` + 5 Pest tests + RUNBOOK 272 linhas canary biz=99 |
| 04:30 | PR #863 Baileys 7.x criado |
| 04:45 | Wagner: "merge confio em voce" → admin merge **8 PRs em sequência:** #854 + #857 + #858 (independentes) → conflito #855 (overlap MessagePersister) → rebase + resolve → #856 + #863 → 6/6 mergeados |
| 05:00 | Wagner aprovou DELETE explícito 83 msgs + conv #37 (invertendo regra "nunca perca mensagem" pra esse caso específico — eram msgs de teste Wagner+contraparte desconhecida) |
| 05:15 | Recovery executado: backup JSON 132KB → DELETE 83 msgs + conv #37 + UPDATE lid_pn_map id=1 NULL + cache flush. Estado biz=1: 0 msgs, 0 convs |
| 05:30 | Wagner: "crie testes" → PR #864 com 10 testes regression E2E + convention anti-regressão (5 E2E reconstruindo cenário + 5 convention source-code) |
| 06:00 | Wagner: "faça" (deploy Hostinger) → composer install + migrate falhou (whatsapp_conversations tem schema diferente — `customer_phone` em vez de `customer_external_id`) |
| 06:15 | PR #866 hotfix migration criado + merged (adiciona colunas em whatsapp_conversations sem `after()` + marca 010000 como ran via insert direto DB) |
| 06:30 | Migration completa em prod biz=1 — 3 colunas (`lid`/`phone_e164`/`bsuid`) em `conversations` + `whatsapp_conversations` ✓ |
| 06:45 | **Conflito detectado:** ADR 0145 dupla — Wagner criou em sessão paralela "IA Administradora pivot ADS↔FSM" (canon/accepted). Minha ADR contact_lid renumerada **0145→0146** via `git mv` + edit frontmatter + atualização 4 arquivos linkando |
| 07:00 | Wagner: "consolide essa memoria" → criação deste handoff fechamento + revisão trechos desatualizados |

## PRs / Branches / Commits

### 8 PRs mergeados (todos squash via curl admin API)

| PR | SHA squash | Conteúdo |
|---|---|---|
| [#854](https://github.com/wagnerra23/oimpresso.com/pull/854) | `99b5b7f3` | anti-cross-contact P0 + estudo 797 linhas + feedback Baileys 7.x + proibição + ADR 0146 + runbook canary |
| [#855](https://github.com/wagnerra23/oimpresso.com/pull/855) | `8420dc3e` | schema 3-identifiers (lid + phone_e164 + bsuid) |
| [#856](https://github.com/wagnerra23/oimpresso.com/pull/856) | `3b0fbd8e` | observer backfill LID resolve → re-link conv órfãs via Job |
| [#857](https://github.com/wagnerra23/oimpresso.com/pull/857) | `fafa0721` | backup daily auth_state Baileys CT 100 + runbook restore |
| [#858](https://github.com/wagnerra23/oimpresso.com/pull/858) | `099833be` | MetaCloudDriver parseInboundWebhook stub canary |
| [#863](https://github.com/wagnerra23/oimpresso.com/pull/863) | `1310e5b3` | **Baileys 6.7.18 → 7.0.0-rc11 ESM-only + getPNForLID nativo** |
| [#864](https://github.com/wagnerra23/oimpresso.com/pull/864) | `812a3c4f` | 10 testes Pest regression E2E + convention anti-regressão |
| [#866](https://github.com/wagnerra23/oimpresso.com/pull/866) | `e7a4baeb` | hotfix migration whatsapp_conversations (schema diferente) |

### Branches já mergeadas (podem ser deletadas)

```
claude/wa-anti-cross-contact-incident-p0
claude/wa-pr1-schema-3-identifiers
claude/wa-pr2-lid-backfill-observer
claude/wa-pr3-baileys-auth-backup
claude/wa-pr4-meta-cloud-driver-stub
claude/wa-baileys-7x-migration
claude/wa-incident-regression-tests
claude/wa-hotfix-migration-whatsapp-conversations
```

## Artefatos canon novos (memory/)

### Decisions

- [memory/decisions/0146-contact-lid-canonico-pk-refactor.md](../decisions/0146-contact-lid-canonico-pk-refactor.md) — feature-wish refactor PK identidade (originalmente 0145, renumerada por colisão)

### Reference (feedback canon)

- [memory/reference/feedback-baileys-7x-decisao-irreversivel.md](../reference/feedback-baileys-7x-decisao-irreversivel.md) — regra dura comportamental Tier 0 (Wagner cortou 3× — 13/14/15-mai catalogados)

### Sessions

- [memory/sessions/2026-05-14-whatsapp-incident-inbox-lid-cross-contact.md](../sessions/2026-05-14-whatsapp-incident-inbox-lid-cross-contact.md) — incident original + correção 15/mai (LID = USER, não chat)
- [memory/sessions/2026-05-14-arte-auto-cadastro-contact-whatsapp.md](../sessions/2026-05-14-arte-auto-cadastro-contact-whatsapp.md) — benchmark concorrentes (Intercom/Twilio/Zendesk/HubSpot), nota 38/100
- [memory/sessions/2026-05-15-estudo-whatsapp-protocol-vs-oimpresso.md](../sessions/2026-05-15-estudo-whatsapp-protocol-vs-oimpresso.md) — estudo protocol-level 797 linhas + Z-API + Evolution + tabela % 6×12 + nota 42/100

### Runbooks

- [memory/requisitos/Whatsapp/runbooks/migrar-baileys-7x.md](../requisitos/Whatsapp/runbooks/migrar-baileys-7x.md) — 7 fases canary biz=99 (272 linhas)
- [memory/requisitos/Whatsapp/runbooks/restore-auth-state.md](../requisitos/Whatsapp/runbooks/restore-auth-state.md) — restore tar.gz auth_state CT 100 (157 linhas)
- [memory/requisitos/Whatsapp/runbooks/ativar-cloud-api-canary-biz99.md](../requisitos/Whatsapp/runbooks/ativar-cloud-api-canary-biz99.md) — Meta Business + HSM + 4 PRs adicionais

### Proibicoes / Governance

- [memory/proibicoes.md](../proibicoes.md) §Comportamento Claude — proibição "NÃO sugerir Baileys 6.7.9" adicionada
- [memory/08-handoff.md](../08-handoff.md) — índice atualizado com handoff 0030 + este 0700

## Estado prod biz=1 ao fechar handoff

```
git HEAD Hostinger: e7a4baeb8 hotfix migration #866
Schema 3-identifiers: ✓ ATIVO (conversations + whatsapp_conversations)

Tabelas biz=1:
  conversations: 0 rows (vazio pós-recovery 83 msgs deletadas)
  messages: 0 rows
  whatsapp_lid_pn_map id=1: phone_e164=NULL ✓ (mapping ofensivo zerado)

Canal id=7 "Jana":
  type=whatsapp_baileys
  status=active
  channel_health=degraded (session_lost — daemon CT 100 ainda 6.7.9)
  display_identifier=554888782087

Backup recovery preservado:
  storage/app/backups/incident-2026-05-14-conv37-backup-20260515-054411.json (132KB)
```

## Lições centrais (4)

### 1. Wagner decidiu Baileys 7.x — proibição comportamental Tier 0 instalada

Claude regrediu 3× citando "rc.X quebrado" como contra-argumento. Wagner cortou em tom severo. Feedback canon + proibição em [proibicoes.md](../proibicoes.md) garantem: futuras sessões NUNCA mais sugerirão "esperar Baileys 7.0.0 final" nem citarão issues abertas como bloqueio a priori. Bugs são gerenciados **DURANTE** execução.

### 2. Estudo protocol-level desmente intuições (Z-API NÃO é mágica)

Grok afirmou "Z-API usa whatsapp-web.js Puppeteer". Pesquisa profunda 14 WebSearch + 4 WebFetch (modo Opus sustained) provou: **Z-API é Baileys-like com SaaS por cima** (mesma fundação técnica, mesmo blackbox LID). O que Z-API "estabilizou" é **infra anti-ban** (queue + IP rotation + warm-up + jitter), não a lib. Replicável em ~3-5 dev-days IA-pair sobre Baileys puro via [baileys-antiban middleware](https://github.com/kobie3717/baileys-antiban).

### 3. ADR colisão de número em paralelismo — protocolo de renumeração rápida

Sessão paralela criou ADR 0145 (IA Administradora) enquanto sessão WhatsApp tinha 0145 (contact_lid). Detectado em `git pull`. Resolução: `git mv` + edit frontmatter `number: 145→146` + grep+edit 4 arquivos linkando + nota "originalmente 0145, renumerada". **Padrão pra catalogar:** se 2 ADRs com mesmo número aparecerem na mesma janela, a `accepted/canon` ganha o número; `proposed/feature-wish` renumera.

### 4. Consolidação memory pós-maratona como protocolo formal

Wagner perguntou "como eu peço pra consolidar a memória?". Resposta canônica:

- **Comando linguagem natural:** *"consolide memória da sessão [tema]"* ou *"revise conflitos memory pós-[evento]"* — Claude executa: (a) `git pull` pra pegar mudanças paralelas, (b) detect colisões numéricas em ADRs/sessions, (c) revise trechos desatualizados via grep cross-doc, (d) crie handoff fechamento, (e) atualize índice.
- **Skill formal candidata (TODO):** poderia virar skill `memory-consolidate` Tier B com description "ATIVAR ao fim de maratona ≥3 PRs OU após Wagner pedir 'consolide memória'". Trigger via auto-match.
- **Anti-padrão:** consolidação NÃO é compactar (não tira informação). É evoluir (atualiza trechos com base no que foi aprendido depois).

## Pendente após esta sessão

### Wagner manual (Tier 0 — não posso fazer)

- ⏳ **Deploy CT 100 Baileys 7.x daemon:**
  ```bash
  tailscale ssh root@ct100-mcp 'cd /opt/whatsapp-baileys/build && docker compose build --no-cache whatsapp-baileys && docker compose up -d'
  tailscale ssh root@ct100-mcp 'docker logs whatsapp-baileys --tail 30'
  ```
  Esperado: daemon ready sem `ERR_REQUIRE_ESM`. Se quebrar (issues rc.X conhecidas), abrir issue no fórum Baileys + fixar `claude/wa-baileys-7x-hotfix-deploy`.

- ⏳ **Setup CT 100 backup auth_state cron** (PR #857):
  ```bash
  tailscale ssh root@ct100-mcp '
    mkdir -p /opt/scripts /backups/baileys-auth
    touch /var/log/baileys-backup.log
  '
  # scp infra/scripts/backup-baileys-auth.sh root@ct100-mcp:/opt/scripts/
  # scp infra/cron/baileys-backup root@ct100-mcp:/etc/cron.d/baileys-backup
  ```

- ⏳ **Re-parear canal id=7 via UI** `/whatsapp/channels` — QR scan novo. Daemon 7.x vai gerar QR via novo auth state.

- ⏳ **Smoke test biz=99 sandbox** primeiro (canary 7d) ANTES de promover biz=4 prod (ROTA LIVRE) — seguir [runbook](../requisitos/Whatsapp/runbooks/migrar-baileys-7x.md).

### Claude pode disparar quando Wagner desbloquear

- 🟡 **Após deploy Baileys 7.x:** acompanhar prod 7d com OTel métricas (uptime, signature failures, history sync success) — pattern já implementado PR #850.
- 🟡 **Após canary 7d OK:** promover biz=4 prod (ROTA LIVRE / Larissa) via re-pareamento + aviso prévio cliente ([ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md)).
- 🟡 **Após biz=4 estável 30d:** ativar Cloud API canary biz=99 ([runbook](../requisitos/Whatsapp/runbooks/ativar-cloud-api-canary-biz99.md)) — Wagner cria Meta Business + HSM templates manual.
- 🟡 **Após Cloud API biz=99 maduro:** revisitar [ADR 0146](../decisions/0146-contact-lid-canonico-pk-refactor.md) feature-wish → implementação real (refactor `contact_lid` como PK canônica).

### Backlog backup (não-bloqueia)

- 🔵 Branches mergeadas listadas acima podem ser deletadas (`git branch -d` local + UI GitHub).
- 🔵 Backup JSON `incident-2026-05-14-conv37-backup-*.json` em storage/app/backups/ pode ser arquivado em S3/Backblaze se Wagner quiser (P2 — opcional).
- 🔵 [ADR 0146](../decisions/0146-contact-lid-canonico-pk-refactor.md) feature-wish aguarda sinal qualificado pra implementação (3 triggers catalogados).

## Estado MCP no momento do fechamento

- **Brief Tier A** SessionStart 14h carregado — CYCLE-05 ativo (Inter PJ + WhatsApp governança), 9d restantes, drift 0% alinhados.
- **Cycle pivot CYCLE-05→06** rodando em paralelo (sessão Wagner criou ADR 0145 IA Administradora — pivot ADS↔FSM + piloto Cobradora ROTA LIVRE em [decision 0145-ia-administradora](../decisions/0145-ia-administradora-pivot-ads-fsm-piloto-cobradora.md)).
- **Tasks MCP afetadas pelos 8 PRs:** US-WA-093 LID Resolver workaround → ganha schema 3-identifiers + observer backfill + Baileys 7.x nativo. US-WA-078 auto-link Contact → ganha defense-in-depth fuzzy suffix-8.
- **Cliente Larissa ROTA LIVRE (biz=4)** continua impactada zero (recovery DELETE foi em msgs de teste Wagner, biz=1 = WR2 Sistemas empresa-mãe / dev — não cliente).

## Como Wagner pede consolidação no futuro (resposta canônica)

Comando linguagem natural reconhecido por Claude:

```
"consolide memória da sessão [tema]"
"consolide essa memória"
"revise memory pós-[evento]"
"limpa conflitos memory [tema]"
```

Claude executa pipeline canônico:

1. `git pull origin main` — pega mudanças paralelas
2. `git diff` + `Grep` cross-doc — detecta colisões numéricas (ADRs, sessions com mesmo timestamp), trechos contraditórios entre sessions
3. Edit em massa — atualiza trechos desatualizados com nota corretiva "ver [link doc novo]"
4. Cria handoff fechamento com cronologia evolutiva + lições centrais
5. Atualiza índice [08-handoff.md](../08-handoff.md)
6. Commit + push + admin merge (single PR consolidação)

Custo: ~10-15min IA-pair. ROI: time não tropeça em info desatualizada futuro.

---

**Próxima sessão:** retomar com `brief-fetch` → confirmar deploy CT 100 Baileys 7.x feito → smoke test biz=99 sandbox → promoção biz=1 7d depois.

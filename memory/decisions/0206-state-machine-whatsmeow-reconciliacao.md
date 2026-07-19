---
slug: 0206-state-machine-whatsmeow-reconciliacao
number: 206
title: "Whatsmeow profissionalização — State Machine + Reconciler + circuit breaker + backup + UI inline"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
proposed_by: audit-senior-expert (opus-4.7)
prompted_by: wagner
created: "2026-05-27"
decided_by: [W]
decided_at: "2026-05-27"
accepted_at: "2026-05-27"
accepted_by: wagner
module: whatsapp
quarter: 2026-Q2
tier: CANON
tags: [whatsapp, whatsmeow, wuzapi, state-machine, circuit-breaker, multi-tenant, profissionalizacao, otel, backup-restic, error-handling]
parent_adr: 0094-constituicao-v2-7-camadas-8-principios
amends: [0204-whatsmeow-driver-substituto-baileys]
supersedes: []
related: [0058-reverb-substituido-por-centrifugo-frankenphp, 0062-separacao-runtime-hostinger-ct100, 0093-multi-tenant-isolation-tier-0, 0094-constituicao-v2-7-camadas-8-principios, 0096-modulo-whatsapp-meta-cloud-api-direto, 0105-cliente-como-sinal-guiar-sem-mandar, 0106-recalibracao-velocidade-fator-10x-ia-pair, 0117-multiplos-numeros-whatsapp-por-business, 0202-whatsapp-profissionalizacao-baileys-out, 0204-whatsmeow-driver-substituto-baileys]
authors: [audit-senior-expert, wagner]
pii: false
companion_dossier: ../../sessions/2026-05-27-dossier-profissionalizacao-whatsmeow.md
review_triggers:
  - Daemon WuzAPI versão major bump (3.x → 4.x) — state machine pode mudar
  - WhatsApp Meta soltar novo TOS endurecendo Whatsapp Web
  - Volume passar 50 channels paireados simultâneo — reavaliar polling 2s vs WebSocket
  - 3+ businesses banidos em 24h (cross-tenant ban alarm dispara)
  - Métrica paired_within_60s cai abaixo 80% por 7 dias
  - Custo CT 100 + sessões superar US$ 30/mês
  - 3+ outros módulos novos precisando HTTP daemon externo — reavaliar Saloon PHP
---

# ADR 0206 — Whatsmeow profissionalização: State Machine + Reconciler + circuit breaker + UI inline

> **Status:** ✅ **ACEITO** 2026-05-27 (Wagner "faça por favor" 18:20 BRT).
> **Renumeração:** proposta nasceu como 0205 mas slot ocupado por ADR 0205 contract-tests-autosave. Reassignado pra 0206 no aceite.
> **Companheiro:** [`memory/sessions/2026-05-27-dossier-profissionalizacao-whatsmeow.md`](../../sessions/2026-05-27-dossier-profissionalizacao-whatsmeow.md)
> **Amend:** [ADR 0204](../0204-whatsmeow-driver-substituto-baileys.md) — adiciona Reconciler + correções 9 débitos catalogados pós-sessão pareamento manual 2026-05-27.

## Contexto

[ADR 0204](../0204-whatsmeow-driver-substituto-baileys.md) (aceita 2026-05-27 manhã) introduziu driver `whatsmeow` via daemon Go WuzAPI substituindo Baileys. PR #1781 mergeado, código produção.

### O que aconteceu pós-merge

Sessão 2026-05-27 19:00 — Wagner tentou parear primeiros 2 channels (`Jana`, `Suporte` biz=1) usando UI canônica. **Experimentou 5 bugs sequenciais**, cada um exigiu workaround manual via Claude Code+SSH:

1. **ENUM `channels.type` sem `whatsapp_whatsmeow`** — agente ADR 0204 adicionou constant na entity mas esqueceu migration. UI não renderizava botão Conectar pq filter por type falhava.

2. **`business.uuid` coluna ausente** — `connectWhatsmeow()` lê `$business->uuid` pra montar webhook URL multi-tenant, mas tabela legacy UltimatePOS não tem coluna `uuid`. 500.

3. **`Http::withToken` injeta `Bearer ` (WuzAPI rejeita)** — Laravel auto-prepend `Bearer ` quebra 100% requests. PR #1787 corrigiu mas SEM Pest guard.

4. **`POST /session/connect` 500 "already connected"** — quando user existe orphan no daemon, connect retorna erro. Daemon já tem QR gerado nesse estado mas backend não consulta. Bug confirmado em [WuzAPI issue #131](https://github.com/asternic/wuzapi/issues/131).

5. **QR PNG salvo em `public/qr-suporte-temp.png`** — gambiarra emergencial pra desbloquear Wagner. URL pública sem auth, LGPD incidente.

### Sinal qualificado Wagner (2026-05-27 19:00)

> "isso tem que ser automatizado, nada de fazer manualmente. crie sistema de controle de erros. profissionalize"

Tradução por [ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md): cliente arquitetural Wagner reportou dor concreta + diretriz explícita. **Backlog deve responder com plano profissional, não mais workaround manual.**

### Mais 4 débitos catalogados (não no fluxo de pareamento mas estratégicos)

6. **`asternic/wuzapi:latest` sem pin SHA digest** — rebuild futuro pode quebrar prod silenciosamente
7. **Sem cron backup** `/srv/docker/whatsapp-whatsmeow/sessions/` — perder volume = re-pair todos channels
8. **Sem retry/circuit breaker + sem OTel spans** — falha transitória vira erro permanente, observability cega
9. **Sem alarme daemon banned/disconnected** — silêncio até cliente reclamar

### Pesquisa estado-da-arte 2026 (fontes citadas)

1. **WuzAPI [issue #131](https://github.com/asternic/wuzapi/issues/131) já documenta** o bug "already connected" — confirmação que precisa state machine no cliente, não no daemon
2. **WuzAPI [API.md](https://github.com/asternic/wuzapi/blob/main/API.md):** `GET /admin/users` permite listar users existentes; `GET /session/status` retorna `{Connected, LoggedIn, Jid}` — base pra reconcile cliente-side
3. **whatsmeow Go pkg ([pkg.go.dev/go.mau.fi/whatsmeow](https://pkg.go.dev/go.mau.fi/whatsmeow)):** `IsConnected` ≠ `IsLoggedIn`; distinção crítica pra state machine
4. **Laravel Retry 2026 packages** ([gregpriday/laravel-retry](https://github.com/gregpriday/laravel-retry), [harris21/laravel-fuse](https://github.com/harris21/laravel-fuse)): pattern Macro + circuit breaker via Cache é canon. Lib externa overkill pra 1 daemon.
5. **Docker [digest pinning 2026](https://docs.docker.com/dhi/core-concepts/digests/)** é convenção pra produção reprodutível.
6. **Restic [mazzolino/restic](https://servercrate.net/restic-docker-backup/)** é padrão self-host backup Docker volumes 2026.
7. **Inertia.js 2.0 [polling helper](https://inertiajs.com/polling)** + `usePoll` resolve QR refresh sem precisar WebSocket.

## Decisão

**Profissionalizar fluxo whatsmeow via 5 fases sequenciadas:**

### Decisão 1 · State Machine WuzAPI user lifecycle + Reconciler service

Implementar `Modules/Whatsapp/Services/WhatsmeowReconciler.php` (~150 LOC) que **sempre** consulta estado real do daemon (`GET /admin/users` + `GET /session/status`) antes de mutar. Retorna `WhatsmeowReconcileResult` DTO com estado canônico + QR base64 inline + mensagem PT-BR pronta pra UI.

**Estados canon (detalhado dossier §2):**
- `NOT_EXISTS`, `EXISTS_NOT_CONNECTED`, `QR_PENDING`, `PAIRED`, `LOGGED_OUT`, `BANNED`, `DAEMON_UNREACHABLE`

**NÃO usa lib externa** (`spatie/laravel-model-states`, `sebdesign/laravel-state-machine`): estado já existe em `channels.status` + `channels.channel_health` ENUMs. Custom thin layer ganha.

### Decisão 2 · Migrations canônicas (ENUM + UUID + trait)

3 migrations + 1 trait:
- `2026_05_28_010001_add_whatsmeow_to_channels_type_enum.php` — fecha gap Débito 1
- `2026_05_28_010002_add_uuid_to_business_table.php` + populate via chunkById — fecha gap Débito 2
- `app/Concerns/HasUuid.php` (NEW trait) — pattern reutilizável pra futuros models

### Decisão 3 · Macro `Http::whatsmeowDaemon()` + circuit breaker via Cache

`AppServiceProvider::boot()` define macro com:
- Retry 3× exponential backoff (500ms, 1s, 2s) + jitter ±200ms
- Timeout connect 3s + total 10s
- Retry só em ConnectionException + 502/503/504 (não em 401/403/500 — esses são lógica)
- OTel span via `OtelHelper::span('whatsmeow.daemon.<method>')`

Circuit breaker via Cache:
- 5 falhas em 60s → estado `open` por 2 min
- `half-open` tenta uma vez a cada 30s

**NÃO usa lib externa:** `harris21/laravel-fuse` é queue-focused, `gregpriday/laravel-retry` overkill, `Saloon PHP` exige refactor 30-50h. Custom 60 LOC entrega.

### Decisão 4 · UI Dialog inline base64 + Inertia polling 2s

Dialog "Conectar" em `Atendimento/Channels/Index.tsx` mostra:
- Loading skeleton enquanto Reconciler trabalha
- QR base64 inline (`<img src="data:image/png;base64,...">`) — **sem arquivo público**
- Polling `/atendimento/canais/{id}/status` 2s detecta pareamento real-time
- Mensagens claras por estado canônico

**Remover gambiarra** `public/qr-suporte-temp.png` ANTES de qualquer outra fase.

**NÃO usa WebSocket Centrifugo dedicado** (overkill pra dialog efêmero ≤ 60s). Polling 2s × 30 reqs = trivial.

### Decisão 5 · Backup Restic + health probe + alarme

- Container `mazzolino/restic` separado backup diário 03:00 BRT volume sessões + retention 7d/4w/3m
- Healthcheck cron diário verifica último backup < 26h, alerta Wagner
- `WhatsmeowHealthProbeCommand` a cada 30min: probe channels active, marca `channel_health=banned/disconnected`, alerta Wagner se 3+ banned em 1 business (cross-tenant ban wave detection)

### Decisão 6 · Pin SHA digest WuzAPI

`docker-compose.yml`: substitui `asternic/wuzapi:latest` por `asternic/wuzapi@sha256:<digest>`. Renovate config auto-PR pra novos digests com `automerge: false` (review obrigatório).

### Decisão 7 · Pest tests + Runbook canônico

7 cenários Pest no `WhatsmeowReconcilerTest` (1 por estado canon). Test guard contra regressão Bearer prefix. Test end-to-end ChannelsController.

Runbook `whatsmeow-troubleshoot.md` com 10 cenários comuns + recovery.

### O que NÃO muda (preservado integralmente)

- ✅ **ADR 0204 driver whatsmeow IN** — esta ADR amenda apenas operacional, não substitui
- ✅ **Baileys forbidden permanente** ([ADR 0202](../0202-whatsapp-profissionalizacao-baileys-out.md))
- ✅ **Multi-tenant Tier 0 IRREVOGÁVEL** ([ADR 0093](../0093-multi-tenant-isolation-tier-0.md)) — Reconciler nunca atravessa business_id, webhook URL com `{business_uuid}` preservada
- ✅ **Meta Cloud default universal** (ADR 0202) — whatsmeow continua opcional
- ✅ **Centrifugo real-time UI** ([ADR 0058](../0058-reverb-substituido-por-centrifugo-frankenphp.md)) — channel `whatsapp:business:{id}` independente de driver
- ✅ **PII redacted em logs** (`App\Support\PiiRedactor`)
- ✅ **WhatsmeowDriver atual** preservado — Reconciler é layer acima, não substitui

## Justificativa

### Por que State Machine via Reconciler service (não lib externa)

1. **Estado já existe em ENUMs `channels.status`+`channel_health`** — adicionar `WhatsmeowState` classe per-estado seria duplicar
2. **Truth of source é o daemon WuzAPI**, não DB local — Reconciler consulta sempre, evita drift
3. **Lib externa adiciona ~2k LOC e dependência runtime** sem ganho — Reconciler custom é 150 LOC controlado
4. **WuzAPI [issue #131](https://github.com/asternic/wuzapi/issues/131)** documenta exato bug "already connected" — solução é consultar `/admin/users` antes (não lib pode salvar disso)

### Por que circuit breaker via Cache (não harris21/laravel-fuse)

1. **Fuse focado em queue jobs**, não HTTP per-request
2. **Cache::increment + Cache::put pattern** é 30 LOC, zero dependência
3. **Apenas 1 daemon externo crítico** (whatsmeow) — se aparecerem 3+ (ML, Insta), reavaliar via review_trigger
4. **Macro `Http::whatsmeowDaemon()`** centraliza config — pattern Laravel idiomático

### Por que polling Inertia 2s (não WebSocket)

1. **Dialog efêmero ≤ 60s** — 30 reqs por sessão de pareamento é trivial
2. **Centrifugo já dedicado a inbox messages** real-time — não misturar canais de propósito diferente
3. **`usePoll` Inertia 2.0** é declarativo e cleanup automático
4. **WebSocket pra pareamento** seria YAGNI sem sinal de scale

### Por que backup Restic (não snapshot LXC)

1. **Restic é estado-da-arte 2026** ([servercrate.net](https://servercrate.net/restic-docker-backup/)) self-host
2. **Single binary, scheduling embutido** via `mazzolino/restic` image
3. **Retention 7/4/3 + prune semanal** é convenção
4. **Snapshot LXC** captura mais que precisa (toda CT 100) e exige Proxmox config — overkill

### Por que pin SHA digest (não tag major)

1. **Convenção 2026 produção** ([Docker Docs](https://docs.docker.com/dhi/core-concepts/digests/))
2. **`:latest` é mutável** — rebuild quebra sem aviso
3. **Renovate automation** garante updates não viram dívida

## Consequências

### Positivas

- **Wagner re-conecta channel novo em ≤ 60s end-to-end** (vs 5 bugs + 2h workaround manual em 2026-05-27)
- **Daemon down não trava UI** — circuit breaker corta cascata, mensagem clara "Indisponível"
- **Bug "already connected" sumiu** — Reconciler trata via state machine
- **LGPD compliance restored** — QR base64 inline, zero arquivo público
- **Multi-tenant Tier 0 preservado** — `business.uuid` formal, webhook URL canônica
- **Observability profissional** — OTel spans + logs estruturados + alertas Wagner
- **Recoverability** — Restic backup garante perda volume = restore 1 comando
- **Test coverage** — Pest guarda regressões (Bearer header, ENUM, estado)
- **Pattern reutilizável** — Reconciler + macro Http servem futuros drivers (ML, Insta)

### Negativas / Trade-offs

- **+1 Service + DTO + macro + command** = ~400 LOC novo
- **+1 container CT 100 (Restic)** — mínimo footprint (50MB RAM)
- **Wagner-h** ~4h decisões/smoke (canary 7d biz=Termas)
- **Risco refactor controller** — mitigado feature flag `WHATSMEOW_USE_RECONCILER`

### Riscos mitigados

| Risco | Mitigação |
|---|---|
| Cross-tenant leak via Reconciler | Sempre resolve via `channels.business_id` global scope; Pest cobre |
| Latência reconcile > 2s | Timeout 10s + circuit breaker corta cascata |
| Refactor regressão produção | Feature flag toggle revert 1 env var |
| Daemon WuzAPI bump quebra API | Pin SHA digest + Renovate PR review |
| Restic password perdido | Vaultwarden + runbook test restore mensal |
| Estado machine fica obsoleto vs daemon | Reconciler consulta runtime — sempre fresh |

## Alternativas consideradas (não escolhidas)

Detalhe em dossier companion §3. Resumo razão rejeição:

### (α) `spatie/laravel-model-states`
**Rejeitada** — força state como classe (5+ classes pra 7 estados); estado já existe em ENUMs DB; adiciona dependência runtime sem ganho técnico.

### (β) `sebdesign/laravel-state-machine` (winzou)
**Rejeitada** — YAML-based config debug obscuro; última release 2024; mesma crítica do α.

### (γ) `harris21/laravel-fuse` circuit breaker
**Rejeitada** — focado em queue jobs, não HTTP request por request; integração com Macro Http exige glue code.

### (δ) Saloon PHP framework completo
**Rejeitada por ora** — refactor 30-50h (Connector pattern em todos drivers Whatsapp); viola "1 PR = 1 intent". Review_trigger se 3+ módulos novos precisarem.

### (ε) WebSocket Centrifugo dedicado pra pareamento
**Rejeitada** — overkill pra dialog efêmero ≤ 60s; Centrifugo já dedicado a inbox messages (canal `whatsapp:business:{id}`); polling 2s × 30 reqs = trivial.

### (ζ) Snapshot LXC Proxmox em vez de Restic
**Rejeitada** — captura toda CT 100 (overkill); exige Proxmox config; Restic é single-binary + scheduling embutido.

### (η) Não fazer State Machine (deixar controller imperativo)
**Rejeitada** — exato pattern que produziu 5 bugs sequenciais 2026-05-27; Wagner sinal qualificado pede profissionalização ([ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md)).

## Plano de implementação faseado

Detalhe completo em dossier companion §4. Resumo 5 fases:

| Fase | Quando | Wagner-h | IA-pair-h | Relógio |
|---|---|---|---|---|
| A · Migrations canônicas | hoje-amanhã | 0.5 | 2-4 | 1 dia |
| B · State Machine + Reconciler | 29-30/mai | 1 | 4-6 | 2 dias |
| C · Retry/OTel/backup/probe | 29-30/mai (paralelo B) | 1 | 3-5 | 2 dias |
| D · UI Dialog inline | 29-30/mai (paralelo B+C) | 1 | 3-5 | 2 dias |
| E · Pest + Runbook | 31/mai-01/jun | 0.5 | 2-4 | 1 dia |
| **TOTAL** | **27/mai - 01/jun** | **~4h** | **14-24h** | **~8 dias** |

Estimates conforme [ADR 0106 recalibração 10x](../0106-recalibracao-velocidade-fator-10x-ia-pair.md) + margem 2× aplicada.

**B/C/D são paralelo-seguro** (áreas isoladas zero conflito) — spawn 3 sub-agents Fase 3 `/audit-and-fix` simultâneos.

## Métricas de sucesso (gates)

Detalhe dossier §8. Resumo:

### Quantitativas SLO

- `whatsmeow.qr.fetch_p95` < 500ms
- `whatsmeow.reconcile.errors_per_day` < 1
- `whatsmeow.session.paired_within_60s` > 90%
- `whatsmeow.daemon.uptime` 99.9%
- `whatsmeow.circuit.open_per_week` < 5
- `business.uuid_coverage` = 100%
- 0 bugs novos sessão pareamento / mês

### Qualitativas

- Wagner conecta channel novo em ≤ 60s end-to-end (NÃO 5 bugs em sequência)
- Daemon down simulado → UI graceful, não trava
- Backup Restic snapshot existe após 24h
- Pest 100% green filter Whatsmeow

### Gate Mês 1 (canary 7d)

- 2+ channels paireados sem incidente
- Zero alerta Wagner por bug pareamento
- Métrica `paired_within_60s` > 90%

## Triggers de reavaliação (review_triggers)

Conforme frontmatter (8 triggers). Destaque:

1. **Daemon WuzAPI major bump** → state machine pode mudar
2. **Volume > 50 channels paireados** → reavaliar polling vs WebSocket
3. **3+ businesses banidos 24h** → cross-tenant ban wave alarm dispara, investigar
4. **3+ outros daemons externos** (ML, Insta) → reavaliar Saloon PHP

## Referências

### ADRs canon oimpresso
- **Mãe:** [ADR 0094](../0094-constituicao-v2-7-camadas-8-principios.md) Constituição v2 (princípio #4 loop fechado por métrica + #8 confiabilidade com fallback)
- **Amend:** [ADR 0204](../0204-whatsmeow-driver-substituto-baileys.md) — esta ADR adiciona operacional não revoga driver
- **Preservadas:** [ADR 0093](../0093-multi-tenant-isolation-tier-0.md) Tier 0, [ADR 0117](../0117-multiplos-numeros-whatsapp-por-business.md), [ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md), [ADR 0106](../0106-recalibracao-velocidade-fator-10x-ia-pair.md)
- **Infra:** [ADR 0058 Centrifugo](../0058-reverb-substituido-por-centrifugo-frankenphp.md), [ADR 0062 Hostinger ≠ CT 100](../0062-separacao-runtime-hostinger-ct100.md)
- **Companheiro:** [`memory/sessions/2026-05-27-dossier-profissionalizacao-whatsmeow.md`](../../sessions/2026-05-27-dossier-profissionalizacao-whatsmeow.md)

### Fontes externas 2026 (pesquisa profunda)
- [WuzAPI GitHub](https://github.com/asternic/wuzapi) + [API.md](https://github.com/asternic/wuzapi/blob/main/API.md) — endpoints canon
- [WuzAPI issue #131](https://github.com/asternic/wuzapi/issues/131) — bug "already connected" confirmado
- [whatsmeow Go pkg](https://pkg.go.dev/go.mau.fi/whatsmeow) — IsConnected vs IsLoggedIn
- [whatsmeow issue #810](https://github.com/tulir/whatsmeow/issues/810) — ban risk Meta 2026 (preservado ADR 0204)
- [Laravel HTTP Client docs 12.x](https://laravel.com/docs/12.x/http-client) — macro pattern
- [gregpriday/laravel-retry](https://github.com/gregpriday/laravel-retry) — pattern reference (rejeitado mas inspiração)
- [harris21/laravel-fuse Laracon India 2026](https://laravel-news.com/laravel-fuse-a-circuit-breaker-package-for-queue-jobs) — circuit breaker reference
- [Docker digest pinning 2026](https://docs.docker.com/dhi/core-concepts/digests/) — convenção produção
- [mazzolino/restic Docker compose](https://servercrate.net/restic-docker-backup/) — backup pattern 2026
- [Inertia.js polling docs](https://inertiajs.com/polling) — `usePoll` 2.0
- [OpenTelemetry Laravel guide](https://uptrace.dev/guides/opentelemetry-laravel) — span attributes

## Aprovação

Wagner aceita ADR alterando frontmatter:

```yaml
status: accepted
decided_at: 2026-05-27
accepted_at: 2026-05-27
accepted_by: wagner
```

E executando pré-flight do dossier §5 antes de spawnar sub-agents Fase 3.

Wagner rejeita ADR alterando `status: rejected` + razão em campo novo `rejected_reason`.

---

**Proposta autoria:** audit-senior-expert (opus-4.7) · 2026-05-27 · PT-BR · Sem hedge
**Decisão final:** Wagner em ≤ 10 min via dossier companion

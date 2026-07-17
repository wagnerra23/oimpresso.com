---
date: "2026-07-17"
time: "17:36 BRT"
slug: staging-freshness-sentinela
tldr: "Checkout de staging (CT100) estava ~4d stale com hand-edit não-commitado; sincronizei com segurança e construí uma sentinela de frescor não-destrutiva (host cron) que escala pra mcp_alertas. 2 PRs mergeados (#4462 core + #4473 sink)."
prs: [4462, 4473]
related_adrs: [0216-deploy-drift-checker, 0235-staging-ct100-clone-anonimizado, 0093-multi-tenant-isolation-tier-0]
decided_by: [W]
next_steps: ["Nada pendente no técnico. Se o staging apodrecer >3d em main, a sentinela horária alerta no brief/inbox via mcp_alertas."]
---

# Handoff — Sentinela de frescor do checkout de staging (não-destrutiva)

## Estado MCP no momento
- `cycles-active`: nenhum cycle ATIVO em COPI.
- `my-work` (@wagner): 30 tasks (10 review, 8 blocked, 12 todo) — nenhuma tocada nesta sessão (trabalho foi infra/governance).
- `decisions-search`: âncoras = [ADR 0216](../decisions/0216-deploy-drift-checker.md) (deploy drift), [ADR 0235](../decisions/0235-staging-ct100-clone-anonimizado.md) (staging CT100).
- Origem: dimensão qualidade-drift-ia-producao (US-COPI-136) topou com o drift ao verificar que não tinha causado drift própria.

## O que aconteceu
1. **Drift resolvido primeiro.** O checkout `/opt/oimpresso-staging/code` estava em `3acabd2fd` (~4d atrás de main) com 5 arquivos modificados + 2 testes não-rastreados + 1 stash. Dois clusters: (A) resíduo do #4401 (byte-idêntico ao main, zero perda) e (B) **trabalho vivo** — um check `langfuse_trace_uptime_24h` (US-COPI-138) editado minutos antes, sem PR. **Preservei** (backup em `/root/staging-drift-backup-20260717`) e escalei em vez de descartar. Entre turnos, uma sessão irmã landou o US-COPI-138 sozinha; sincronizei o checkout com `merge --ff-only` (guardado: sem teste ativo, sem git op) e limpei o backup.
2. **Causa-raiz atacada.** O staging não tem self-update (de propósito: é scratchpad de teste, precisa ficar gravável com trabalho em voo) → apodrece em silêncio → **convida hand-edit** (drift Tier 0). Construí uma sentinela que **só mede e alerta, nunca sincroniza** (o oposto do self-update — pull cego apagaria teste de alguém).
3. **Decisão de engenharia:** resisti ao instinto de criar check novo no `jana:health-check` (seria §5 redundante com `deploy_drift`/ADR 0216) e **estendi a régua consolidada**. Caminho HTTP (`mcp_served_drift`) bloqueado pelo 500 pré-existente do `/api/mcp/version` do staging → sentinela **filesystem no host** (único que vê o disco do staging E o main-SHA fresco do self-update do MCP).

## Artefatos gerados
- **#4462** (core, mergeado): `docker/oimpresso-staging/staging-freshness-sentinel.sh` (host cron hourly, não-destrutiva, `--selftest` controle-negativo) + README. Instalada no crontab do CT100.
- **#4473** (sink, mergeado): `Modules/Governance/Console/Commands/RecordStagingFreshnessAlertCommand.php` (reusa `PersistsDriftAlert` — idempotência diária + escalação >3d) + registro no `GovernanceServiceProvider` + `RecordStagingFreshnessAlertCommandTest` (Pest) + escalação `docker exec` no sentinela.

## Persistência
- **git**: 2 PRs mergeados em `origin/main` (comando + sentinela versionados).
- **CT100**: sentinela deployada em `/opt/oimpresso-staging/staging-freshness-sentinel.sh` + linha no crontab do host; comando `governance:staging-freshness-alert` live no container MCP (self-update puxou — smoke `--verdict=fresco` = no-op verde).
- **Não há BRIEFING de módulo aplicável** (infra, não feature de módulo vertical).

## Próximos passos pra retomar
Nada pendente. Verificar saúde: `tailscale ssh root@ct100-mcp '/opt/oimpresso-staging/staging-freshness-sentinel.sh; cat /opt/oimpresso-staging/freshness-status.json'`.

## Lições catalogadas
- **`[new branch]` em branch que existia = PR foi mergeado** (o #4462 foi mergeado enquanto eu construía a escalação). Cherry-pick só o commit novo sobre `main` fresco + PR novo — não reabrir a branch suja. (Lição já em auto-mem `licao-classificador-instavel-write-chunks`; reincidiu, funcionou.)
- **Editar ServiceProvider (mesmo +1 linha) dispara `infra-contract-required`.** Adicionar seção `## Infra Contract` no PR body (honesta: "sem superfície HTTP, só registro de comando de console").
- **Verificar que o teste RODOU, não só que o shard passou** — `Modules/Governance/Tests/Feature` está no `phpunit.xml` (linha 51), sob suite Feature (por isso grep no shard Unit veio vazio). Confirmação evita falsa-cobertura.
- **Branch-guard da sentinela validado por acaso**: staging estava em `claude/kb-classificador` (sessão irmã) → veredito `nao-aplicavel`, zero falso-alarme. O design de só vigiar `main` é correto.

## Pointers detalhados
- Sentinela + README: `docker/oimpresso-staging/` (`staging-freshness-sentinel.sh`, README §Sentinela de frescor).
- Comando + teste: `Modules/Governance/Console/Commands/RecordStagingFreshnessAlertCommand.php` + `Tests/Feature/RecordStagingFreshnessAlertCommandTest.php`.
- Régua reusada: `Modules/Governance/Services/Concerns/PersistsDriftAlert.php` ([ADR 0216](../decisions/0216-deploy-drift-checker.md)).

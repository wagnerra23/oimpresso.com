# Auditoria · Infra / Ops / DR · 2026-07-05

> **Onda 3** do [PLANO-APROFUNDAMENTO-AVALIACOES](../_Governanca/PLANO-APROFUNDAMENTO-AVALIACOES.md).
> Primeira lente de avaliação sobre infra/ops/DR — antes disto tinha **nota zero** (nenhuma
> lente cobria backup/restore/SPOF). Motivada por incidente silencioso real: a cópia `/opt` do
> CT100 rodou ~17 dias de código velho sem ninguém notar (origem do `self-update.sh`) e o SSH
> Hostinger é flaky. **Ninguém tinha medido backup/restore.**
>
> Método: inventário read-only (Tailscale/CT100 + SSH Hostinger) + **drill de restore executado
> em staging** (nunca prod — [ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md)) +
> análise de drift canon↔servidor. Sem valores BRL, sem PII.

## TL;DR (3 achados que exigem ação)

1. **P0 — Backup de produção é cópia única no mesmo disco.** `spatie/laravel-backup` roda diário e
   funciona (dump completo do banco), mas grava em `public/uploads/UltimatePOS/` na **própria
   Hostinger**. Sem cópia off-site. Se o disco/conta Hostinger cair, o backup cai junto. Viola a
   regra 3-2-1.
2. **P0 — Backup do auth-state do WhatsApp quebrado há 6+ dias, em silêncio.** O daemon migrou de
   Baileys → wuzapi/whatsmeow, mas o script de backup ainda aponta pro path antigo. `/backups/baileys-auth/`
   está **vazio**. É exatamente o SPOF que o backup foi criado pra evitar (incidente 2026-05-14).
3. **P0 — Vaultwarden (todos os segredos) sem backup.** O cofre vive num volume Docker do CT100
   single-node. Sem cron de backup. Perda de disco = perda de todos os secrets (Asaas, Inter, token
   Hostinger, etc).

**Catraca entregue nesta onda:** agendamento do `backup:monitor` (spatie) + Pest que morde se o
schedule sumir ou o limiar de frescor (RPO 24h) for afrouxado. Fecha o modo de falha #2/#3 acima
(morte silenciosa do backup passa a alarmar).

---

## 1. Inventário — o que roda onde ([ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md))

### Hostinger (shared hosting) — app web + dados prod

| Item | Detalhe |
|---|---|
| App web | oimpresso.com (LiteSpeed/Apache), biz=1..N produção, `APP_ENV=live` |
| Banco | MySQL/MariaDB `u906587222_oimpresso` (mesma máquina) |
| Scheduler | `schedule:run` via **cron do hPanel** (não há `crontab` shell — `crontab: command not found`) |
| Backup | `spatie/laravel-backup` daily 01:30 (ver §2) |
| Deploy | `git reset --hard origin/main` via `deploy.yml` (GitHub Actions) |
| Disco | `/home` 21T compartilhado, 73% uso (quota da conta, não dedicado) |

### CT100 Proxmox (VM única) — daemons + staging

22 containers Docker (todos `Up`, healthy no snapshot 2026-07-05):

| Grupo | Containers |
|---|---|
| MCP/canon | `oimpresso-mcp` (mcp.oimpresso.com) |
| Staging | `oimpresso-staging` (web) + `oimpresso-staging-db` (MariaDB 11) |
| Realtime/busca | `centrifugo`, `meilisearch`, `ollama-embedder`, `bge-reranker` |
| WhatsApp | `whatsapp-whatsmeow` (wuzapi) |
| Segredos | `vaultwarden` |
| Observabilidade | `jaeger` + stack `langfuse` (web/worker/postgres/clickhouse/redis/minio) |
| Feature flags | `growthbook` + `growthbook-mongo` |
| Workers | `mysql-workers` (MySQL 8.0) |
| Infra | `traefik`, `portainer`, `tsrecorder` |

**Disco CT100:** `/dev/mapper/pve-vm--100--disk--0` 99G, **80% usado, 19G livres**. Único volume — banco, backups dirty, langfuse e volumes Docker dividem o mesmo disco.

### Crons / timers do CT100

| Mecanismo | Frequência | O que faz |
|---|---|---|
| systemd `oimpresso-git-sync.timer` | 5 min | `git fetch` + grava `deploy-latest-main-sha.txt` + `git pull --ff-only` + `mcp:sync-memory` |
| crontab `self-update.sh` | 15 min | GitOps pull-deploy do container MCP (fetch+reset+recreate) — [ADR 0216](../../decisions/0216-governance-drift-framework-driftchecker-plugavel.md) contexto |
| crontab `ct100-fullsuite.sh` | daily 02:00 | Suite completa CT100 |
| crontab `ragas-publish` | Dom 08:30 | Publica RAGAS |
| cron.d `baileys-backup` | daily 03:00 | Backup auth-state WhatsApp — **QUEBRADO (§3.2)** |
| cron.d `e2scrub`, `tsrecorder-prune`, systemd `logrotate`/`apt-*` | vários | Manutenção SO |

---

## 2. Backup & Restore — drill executado

### Backup de produção (spatie) — ✅ roda, ⚠️ frágil

- **Frequência:** daily 01:30 BRT (`backup:run`), cleanup 01:00 (`backup:clean`).
- **Conteúdo verificado:** zip de ~75MB contendo **dump completo do banco** (`db-dumps/mysql-u906587222_oimpresso.sql`, ~629MB descomprimido) + 121 arquivos/dirs de app.
- **Última execução OK:** 2026-07-05 01:31 ("Backup completed!" no log).
- **Retenção:** `App\Backup\Cleanup\KeepLatestBackups` mantém só os **5 mais recentes** (~5 dias).
- **Destino:** disk `local` = `public_path('uploads')` → `public/uploads/UltimatePOS/`.

### Drill de restore — ✅ SUCESSO (staging, throwaway, nunca prod)

Executado no CT100 sobre `oimpresso_staging` (873MB, clone **anonimizado** de prod), restaurando num
banco descartável `dr_drill` no container `oimpresso-staging-db`, medido e dropado ao fim:

| Fase | Tempo |
|---|---|
| Dump (`mariadb-dump --single-transaction`, 606MB) | 13s |
| Restore (import completo) | 106s |
| **RTO (dump→restore) do banco** | **~2 min** |

**Integridade confirmada:** `transactions` origem vs restaurado = **75.122 == 75.122** (match exato).
Banco `dr_drill` dropado + arquivo temporário removido ao fim.

**RPO produção:** 24h (janela do cron diário).

### Ressalva honesta do drill

O drill restaurou o **clone anonimizado** de staging (mesmo volume/schema, sem PII), **não** o zip
spatie real de prod — de propósito: mover o dump de 629MB com PII real pro CT100 pra um drill não se
justifica sem decisão do Wagner sobre manuseio de PII. A **validade estrutural** do zip de prod foi
confirmada (`unzip -l` mostra o db-dump íntegro dentro), mas o drill **ponta-a-ponta prod-zip → restore**
fica como gap honesto (ver §4, DR-04). O RTO medido (~2min do banco) é representativo; o RTO real de
prod soma o tempo de **obter** o backup + **provisionar** o ambiente — hoje não medido porque não há
runbook de restore.

---

## 3. Tabela de SPOF (Single Point of Failure)

| # | SPOF | Impacto se cair | Backup/mitigação hoje | Sev |
|---|---|---|---|---|
| SPOF-1 | **Disco Hostinger** guarda app + banco + **backup** | Perda total incl. o próprio backup (cópia única, mesmo disco) | Nenhuma cópia off-site | **P0** |
| SPOF-2 | **CT100 VM única** (Proxmox single-node, 22 containers, 80% disco) | Perda de MCP, Vaultwarden, Centrifugo, Meilisearch, staging, langfuse | Sem HA; sem backup de VM documentado | **P0** |
| SPOF-3 | **Vaultwarden** (todos os segredos) num volume Docker do CT100 | Perda de todos os secrets (Asaas/Inter/token Hostinger/etc) | **Sem cron de backup** | **P0** |
| SPOF-4 | **WhatsApp auth-state** (wuzapi `/srv/docker/whatsapp-whatsmeow`) | Re-pareamento manual + perda de histórico/LID (incidente 2026-05-14) | Backup **quebrado** há 6+ dias (§3.2) | **P0** |
| SPOF-5 | **Meilisearch / Ollama embedder** (busca Jana) | Jana perde recall até reindex | Reconstruível de `mcp_memory_documents` (lento) | P2 |
| SPOF-6 | **Langfuse stack** (observabilidade) | Perda de histórico de traces/custo | Não crítico ao runtime; state perdido | P3 |

### 3.1 GAP backup off-site (SPOF-1) — o pior

O backup spatie é **cópia única no mesmo disco da prod**. A regra 3-2-1 (3 cópias, 2 mídias, 1 off-site)
não é atendida em nenhum eixo. Recomendação (Wagner-gated, precisa credencial/decisão):

- **Opção A:** disk `s3` no `config/backup.php` (`monitor_backups` já prevê `['local','s3']` comentado) →
  bucket externo (Backblaze B2/Wasabi/S3). Menor esforço, custo previsível.
- **Opção B:** pull do zip pro CT100 via cron (CT100 já tem `git`/rede) pra `/opt/backups/hostinger/` —
  reaproveita infra existente, mas ainda 1 disco (CT100 também é SPOF). Melhor que nada, pior que A.

Além disso: o backup vive em **dir servido pela web**. Hoje protegido pela `RewriteRule` do `public/.htaccess`
(bloqueia `*.zip` → HTTP 403 confirmado) + `FilesMatch`. **Defesa em profundidade fraca**: uma regressão no
`.htaccess` expõe o dump completo (todo o PII) publicamente. Mover o destino pra fora de `public/` fecha isto.

### 3.2 GAP WhatsApp auth-state (SPOF-4) — quebrado em silêncio

`infra/scripts/backup-baileys-auth.sh:18` fixa `SOURCE_DIR="/srv/docker/whatsapp-baileys/sessions"`, mas o
daemon migrou pra **wuzapi/whatsmeow** (`asternic/wuzapi`, storage em `/srv/docker/whatsapp-whatsmeow` →
`/app/dbdata`). O log `/var/log/baileys-backup.log` mostra `ERROR: source dir ... está vazio` **todo dia
desde ≥2026-06-30**, e `/backups/baileys-auth/` está vazio. O auth-state (o SPOF que motivou o backup no
incidente 2026-05-14) está **sem proteção agora**.

Correção **não é troca de path** — wuzapi guarda um **banco** (`/app/dbdata`), não um dir de arquivos de
sessão como o Baileys. Precisa nova estratégia de backup (dump do store do wuzapi). Registrado como
follow-up Wagner-gated (task spawnada), não corrigido cego nesta PR.

---

## 4. Sync canon ↔ servidor + catraca de drift

### Estado do drift (snapshot 2026-07-05)

| Alvo | Deployado | vs `origin/main` | Mecanismo |
|---|---|---|---|
| CT100 `oimpresso-mcp` | `f90a67550` | == main (self-update a cada 15min) | `self-update.sh` + `git-sync.timer` + [`DeployDriftChecker`](../../decisions/0216-governance-drift-framework-driftchecker-plugavel.md) + sentinela externa `mcp-drift-sentinel.yml` |
| CT100 `oimpresso-staging` | branch própria (−1) | intencional (staging testa branch) | N/A |
| Hostinger app | `f90a675507` | == main (no check) | `deploy.yml` no push + webhook grava SHA |

### A catraca "deployado == HEAD canon" **já existe** (não duplicar — T6)

`Modules/Governance/Services/Checkers/DeployDriftChecker.php` ([ADR 0216](../../decisions/0216-governance-drift-framework-driftchecker-plugavel.md))
já é a catraca `deployado != main` (severity high, enforcement warn, cadence daily), plugada no framework
`governance:audit`. Ela cobre o ambiente onde roda (CT100). Criar uma segunda catraca de deploy-drift
violaria T6. **Follow-up conhecido** (comentado no próprio checker): cobertura multi-env do lado Hostinger
via `/health` — fica registrado, não é escopo desta onda.

### Catraca NOVA entregue nesta onda — frescor do backup

O gap real **sem catraca** não era deploy-drift, era **frescor de backup**: `backup:run` só *cria* o
backup; nada *verificava* que ele existe e está fresco. O spatie tem `monitor_backups` configurado
(`MaximumAgeInDays=1`, `MaximumStorageInMegabytes=5000`) + `UnhealthyBackupWasFoundNotification` via mail —
mas **`backup:monitor` nunca era agendado**, então a morte silenciosa do backup (mesmo modo de falha do §3.2)
passava batido.

**Entregue:**
- `app/Console/Kernel.php` — agenda `backup:monitor` daily 09:00 BRT (`->environments(['live'])`, `withoutOverlapping`).
- `tests/Feature/Console/BackupMonitorScheduleTest.php` — **a catraca morde**: quebra no CI se o schedule
  sumir, mudar de horário, OU se o limiar de frescor for afrouxado (`MaximumAgeInDays > 1` → reabriria a
  janela de 24h de RPO).

Isto fecha o modo de falha "backup morreu e ninguém viu" pro backup spatie. O auth-state WhatsApp (§3.2)
e o Vaultwarden (SPOF-3) precisam da própria estratégia (follow-ups).

---

## 5. Follow-ups priorizados (Wagner-gated)

| ID | Ação | Sev | Esforço | Gate |
|---|---|---|---|---|
| DR-01 | Backup off-site do dump prod (disk `s3` ou pull CT100) — §3.1 | P0 | 1 sessão | credencial bucket |
| DR-02 | Mover destino do backup pra fora de `public/` (defesa em profundidade) | P1 | ½ sessão | Wagner |
| DR-03 | Nova estratégia de backup do auth-state wuzapi (dump do store, não path) — §3.2 | P0 | 1 sessão | verificar storage wuzapi |
| DR-04 | Cron de backup do volume Vaultwarden (SPOF-3) | P0 | ½ sessão | Wagner |
| DR-05 | Runbook de restore ponta-a-ponta + drill com o zip prod real (mede RTO completo) | P1 | 1 sessão | decisão PII |
| DR-06 | Versionar systemd units do CT100 (`oimpresso-git-sync.*`) no repo (hoje ad-hoc no host) | P2 | ½ sessão | — |

---

## Metadados

- **Executor:** sessão Claude Code (Fable 5), worktree a partir de `origin/main` fresco.
- **Fonte dos números:** Tailscale SSH CT100 + SSH Hostinger (warm-up 5×), snapshot 2026-07-05 ~14h BRT.
- **Drill:** restore em `oimpresso-staging-db` (throwaway `dr_drill`, dropado). Zero toque em prod.
- **Catraca:** `backup:monitor` agendado + `BackupMonitorScheduleTest`.
- **Regras honradas:** [ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md) (runtime separado, drill só staging),
  [ADR 0264/0275](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md) (onda entrega catraca, não só relatório), T6 (estende `DeployDriftChecker`, não duplica). Sem valores BRL, sem PII.

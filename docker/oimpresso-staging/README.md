# Staging — `staging.oimpresso.com`

Ambiente de homologação do ERP oimpresso no **CT 100** (Proxmox). Serve a app web
inteira (Inertia/React/Blade) num subdomínio, com **banco separado** e dados
**anonimizados** de produção. Para a equipe ver/testar **sem pesar a máquina local
e sem risco de tocar produção**.

> ADR 0235 (emenda à [0062](../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)) —
> exceção registrada: CT 100 passa a servir 1 subdomínio web (staging), nunca o domínio principal.

## Arquitetura

```
staging.oimpresso.com
  → DNS A (Hostinger API) → 177.74.67.30 (IP CT 100)
    → Traefik (cert Let's Encrypt automático)
      → container oimpresso-staging  (imagem oimpresso/mcp:latest, FrankenPHP CLÁSSICO sem Octane)
        ├─ código: /opt/oimpresso-staging/code   (git, branch própria)
        └─ banco:  oimpresso_staging  @ mysql-workers   (anonimizado)
```

**Por que FrankenPHP clássico (não Octane):** o UltimatePOS é app tradicional, não
Octane-safe (state entre requests). `php-server` boota fresh a cada request, igual o
PHP-FPM do Hostinger — fidelidade > performance em staging.

## As 2 travas de segurança

1. **LGPD** — o banco só recebe dados **depois** de `staging:anonimizar` (CPF/CNPJ/nome/
   e-mail/telefone/endereço → fake; reusa `DsrService`). Validação automática de "0 PII"
   antes de subir. Ver [lgpd-mapa-tratamento.md](../../memory/reference/lgpd-mapa-tratamento.md).
2. **Sem ação no mundo real** — `.env` neutraliza tudo: `MAIL=log`, `QUEUE=sync`,
   WhatsApp/Asaas/NFe **desligados**, credenciais **zeradas** no dump.

## Deploy

```bash
# no HOST CT 100 (tailscale ssh root@ct100-mcp):
sh /opt/oimpresso-staging/code/docker/oimpresso-staging/deploy.sh feat/staging-ct100
```

`deploy.sh` faz: git → composer → build de assets (Node no host) → sobe container.
Migration/import de dados são etapas à parte (gate LGPD).

## Sentinela de frescor (o checkout não pode apodrecer em silêncio)

O checkout de staging **não** tem self-update (de propósito: é scratchpad de teste —
as proibições mandam rodar Pest/PHPStan aqui, então ele precisa ficar gravável e
carrega trabalho em voo; um `pull`/`reset` cego apagaria o teste de alguém). O preço:
ele fica dias atrás de `main` sem ninguém ver, e isso **convida hand-edit direto no
servidor** — drift Tier 0 ([proibicoes §Ambiente](../../memory/proibicoes.md)).
Incidente 2026-07-17: ~4 dias stale + edições na mão, visto por acaso.

`staging-freshness-sentinel.sh` fecha esse buraco **sem** o risco do self-update:
**só MEDE e ALERTA, nunca sincroniza**. Lê o `.git/HEAD` do checkout e compara com o
main-SHA fresco que o self-update do MCP já grava (`/opt/oimpresso-mcp/storage/app/
deploy-latest-main-sha.txt`, /15min) — fallback `git ls-remote` read-only. Roda no
**host** porque só ele enxerga o disco do staging **e** o sha-file do MCP ao mesmo
tempo, e vive **fora** do checkout que vigia (senão apodrece junto).

```bash
# instalar (uma vez, no host CT 100):
cp /opt/oimpresso-staging/code/docker/oimpresso-staging/staging-freshness-sentinel.sh \
   /opt/oimpresso-staging/staging-freshness-sentinel.sh
chmod +x /opt/oimpresso-staging/staging-freshness-sentinel.sh
# crontab -e:
0 * * * * flock -n /tmp/staging-freshness.lock /opt/oimpresso-staging/staging-freshness-sentinel.sh >> /opt/oimpresso-staging/freshness.log 2>&1

# manual / auto-teste:
bash docker/oimpresso-staging/staging-freshness-sentinel.sh            # veredito agora
bash docker/oimpresso-staging/staging-freshness-sentinel.sh --selftest # prova que morde
```

- **Exit 0** fresco / atrás-mas-recente (< `STAGING_FRESHNESS_THRESHOLD_DAYS`, default 3d) / não-aplicável (branch ≠ `main`).
- **Exit 2** STALE (apodreceu > threshold) — a linha `ALERTA` no log **+ alerta em `mcp_alertas`**.
- **Exit 3** indeterminado (não leu HEAD ou main).
- Veredito também em `/opt/oimpresso-staging/freshness-status.json` (machine-readable).
- **Não-destrutiva:** zero `pull`/`fetch`/`reset` — o `ls-remote` do fallback é read-only.

### Sink: mcp_alertas (brief/inbox do time)

No STALE, o sentinela escala pro **brief/inbox** onde o time olha — via
`docker exec oimpresso-mcp php artisan governance:staging-freshness-alert` (único
caminho: o staging tem DB isolada; o exit-2 sozinho só vive no log do host). O comando
reusa `PersistsDriftAlert` ([ADR 0216](../../memory/decisions/0216-deploy-drift-checker.md)):
**idempotência diária** (o cron é hourly → 1 alerta/dia, não spam) + **escalonamento**
(aberto > `governance.drift_escalation_days` = 3d → severidade sobe + `[ESCALADO]`).
`business_id` null (repo-wide, ADR 0093 §Exceção).

- **Best-effort:** se `docker`/container/DB estiverem fora, o `exit 2` + a linha `ALERTA`
  no log seguem sendo o sinal — a escalação nunca derruba o sentinela.
- Desligar (host sem docker): `STAGING_FRESHNESS_ESCALATE=0`.
- **Sem auto-resolve:** quando staging volta a ficar fresco o sentinela para de emitir,
  mas o alerta aberto é resolvido **à mão** na UI Governance (convenção dos demais checkers).

## Arquivos

| Arquivo | O quê |
|---|---|
| `docker-compose.yml` | serviço + labels Traefik + healthcheck |
| `entrypoint-staging.sh` | warm-up + FrankenPHP clássico (sem workers) |
| `.env.staging.example` | template de diffs vs produção + neutralizações |
| `deploy.sh` | deploy idempotente (roda no host) |
| `staging-freshness-sentinel.sh` | heartbeat de frescor do checkout (host cron, não-destrutiva) |

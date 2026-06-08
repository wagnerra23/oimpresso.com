---
name: criar-staging
description: ATIVAR quando user pedir "criar staging", "ambiente de homologação/homolog", "replicar produção pra teste", "subir/recriar/atualizar staging.oimpresso.com", "clone da produção pra equipe testar", "ambiente de teste antes de mexer em produção", "re-seedar o staging", OU qualquer Edit/Write em `docker/oimpresso-staging/**`. Carrega o processo canônico de criar/manter um clone FIEL da produção (ERP web inteiro) no CT 100 — subdomínio próprio + banco MariaDB dedicado ANONIMIZADO (LGPD) + integrações NEUTRALIZADAS — sem precisar perguntar pra ninguém como faz. Aponta pro RUNBOOK detalhado + scripts versionados + as 10 pegadinhas já catalogadas. Origem 2026-05-29 (Wagner: "criar uma regra pro mcp saber fazer isso sem eu precisar informar ninguém").
tier: B
owner: wagner
parent_adr: 0235
---

# Criar / manter o Staging (`staging.oimpresso.com`)

> Clone fiel da produção no CT 100 pra equipe **testar sem tocar prod e sem disparar ação real**.
> **RUNBOOK completo (LER):** [`memory/requisitos/Infra/RUNBOOK-staging-ct100.md`](../../../memory/requisitos/Infra/RUNBOOK-staging-ct100.md)
> **Artefatos versionados:** [`docker/oimpresso-staging/`](../../../docker/oimpresso-staging/) · **ADR 0235** (emenda à 0062).

## Quando ativa
Pedido tipo "criar/recriar/atualizar/re-seedar staging", "ambiente de homologação", "clone de prod pra teste", OU tocar `docker/oimpresso-staging/**`.

## Como fazer (2 comandos — o resto está nos scripts)

```bash
# 1) subir/atualizar app (git + composer + build + container):
tailscale ssh root@ct100-mcp 'bash /opt/oimpresso-staging/code/docker/oimpresso-staging/deploy.sh <branch>'

# 2) popular banco com dump ANONIMIZADO de produção (valida 0-PII, aborta se sobrar):
tailscale ssh root@ct100-mcp 'bash /opt/oimpresso-staging/code/docker/oimpresso-staging/seed-from-prod.sh'
```
Do zero (primeira vez): seguir os 9 passos do RUNBOOK (DNS → MariaDB dedicado → código → composer → build → `.env` derivado de prod → container → seed → cert). Acesso final: `https://staging.oimpresso.com` · qualquer username · senha `staging2026`.

## Restrições Tier 0 (INEGOCIÁVEIS)

- ⛔ **NUNCA** subir dados de prod no staging sem rodar `anonymize.sql` + **validação 0-PII** antes de expor (LGPD — biz=4 ROTA LIVRE é cliente real). Se a validação falhar, NÃO liberar.
- ⛔ **SEMPRE** truncar credenciais/tokens/certificados (`rb_boleto_credentials`, `nfe_certificados`, tokens) — staging não pode cobrar/emitir/conectar de verdade. `.env` com `MAIL=log`, WhatsApp/Asaas/NFe desligados.
- ⛔ **APP_KEY NOVA** no staging (não reusar a de prod). Banco SEPARADO (container `oimpresso-staging-db`, nunca o DB de produção).
- ⛔ CT 100 serve **só staging** como subdomínio web (não o domínio principal — [ADR 0062](../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).

## Pegadinhas que SEMPRE pegam (detalhe no RUNBOOK §Pegadinhas)

1. Imagem FrankenPHP **não tem composer** → usar `composer:2 --ignore-platform-reqs --no-scripts`.
2. `key:generate` falha sem APP_KEY (galinha-ovo) → gerar via `php -r "echo 'base64:'.base64_encode(random_bytes(32));"`.
3. `docker restart` **não relê `env_file`** → `docker compose up -d --force-recreate`.
4. Prod é **MariaDB 11.8** (não MySQL) → staging-db MariaDB + `mariadb-dump` (mysqldump 8.0 quebra).
5. Dump 607 MB num pipe único **trava** (Hostinger dropa) → dump em blocos / tabelas finais à parte.
6. Excluir `activity_log` **quebra o login** (grava nela) → trazer ao menos a estrutura.
7. Cert ACME entra em **backoff** se DNS era NXDOMAIN → `docker restart traefik` após DNS propagar (`@1.1.1.1` E `@8.8.8.8`).
8. Aviso de cert no navegador = **cache** → aba anônima.
9. Healthcheck DB-dependente trava o Traefik → usar healthcheck 2xx-4xx.
10. Credenciais são criptografadas com APP_KEY de prod → inúteis no staging → truncar (já cobre #2 da segurança).

## Refs
- [RUNBOOK-staging-ct100.md](../../../memory/requisitos/Infra/RUNBOOK-staging-ct100.md) — passo a passo + troubleshooting
- [docker/oimpresso-staging/](../../../docker/oimpresso-staging/) — compose, entrypoint, deploy.sh, seed-from-prod.sh, anonymize.sql
- [INFRA-ACESSO-CANON.md](../../../memory/reference/INFRA-ACESSO-CANON.md) — acesso CT 100 + Hostinger + DNS API
- [lgpd-mapa-tratamento.md](../../../memory/reference/lgpd-mapa-tratamento.md) — o que é PII (guia da anonimização)

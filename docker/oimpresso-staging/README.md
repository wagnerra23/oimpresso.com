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

## Arquivos

| Arquivo | O quê |
|---|---|
| `docker-compose.yml` | serviço + labels Traefik + healthcheck |
| `entrypoint-staging.sh` | warm-up + FrankenPHP clássico (sem workers) |
| `.env.staging.example` | template de diffs vs produção + neutralizações |
| `deploy.sh` | deploy idempotente (roda no host) |

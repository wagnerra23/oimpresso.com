# GlitchTip self-host CT 100 — log de falhas

> **[ADR 0429](../../memory/decisions/0429-log-de-falhas-glitchtip-self-host-ct100.md) · US-INFRA-003**
> Recebe erros e falhas do app das lojas (`oimpresso-app`) e, depois, do ERP.
> Fala o protocolo do Sentry: os SDKs `@sentry/capacitor` e `sentry/sentry-laravel` apontam
> para cá só trocando o DSN.

## Onde está

| O quê | Valor |
|---|---|
| Endereço | `https://apm.oimpresso.com` (Traefik + Let's Encrypt, rede `docker-host_default`) |
| Servidor | CT 100 docker-host (`192.168.0.50`), pasta `/opt/glitchtip/` |
| Dados | `/opt/glitchtip/data/{postgres,uploads}` |
| Segredos | `.env` ao lado do compose no servidor (chmod 600) — gerados com `openssl rand -hex 32` |
| Login admin | `/root/.glitchtip-admin` no CT 100 (chmod 600) — trocar a senha no 1º acesso |
| Organização / projeto | `oimpresso` / `oimpresso-app` |
| Retenção | 90 dias (`GLITCHTIP_RETENTION_DAYS`) |

Cadastro aberto e criação de organização estão **desligados**: só entra quem o admin criar.

## Recursos (medido na instalação, 2026-10-07)

| Container | RAM |
|---|---|
| glitchtip (web + worker, `SERVER_ROLE=all_in_one`) | ~175 MB |
| postgres-glitchtip | ~53 MB |
| valkey-glitchtip | ~5 MB |

## Deploy / atualização

```bash
tailscale ssh root@ct100-mcp
test -d /opt/glitchtip/code || git clone https://github.com/wagnerra23/oimpresso.com /opt/glitchtip/code
cd /opt/glitchtip/code && git pull
cd docker/glitchtip
test -f .env || { umask 077; printf "POSTGRES_PASSWORD=%s\nSECRET_KEY=%s\n" "$(openssl rand -hex 32)" "$(openssl rand -hex 32)" > .env; }
docker compose -p glitchtip up -d
```

Nunca editar o compose no servidor: muda aqui, PR, depois `git pull` lá.

## Criar usuário para alguém do time

Sem SMTP configurado não há convite por e-mail. O admin cria pelo terminal:

```bash
docker exec -it glitchtip ./manage.py createsuperuser   # admin
```

ou, para membro comum, cria o usuário pela interface (Configurações da organização → Membros)
depois de configurar `EMAIL_URL`.

## Smoke

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://apm.oimpresso.com/_health/   # 200
```

Evento de teste (troque `<chave>` pela chave pública do DSN):

```bash
curl -s -X POST https://apm.oimpresso.com/api/1/store/ \
  -H "Content-Type: application/json" \
  -H "X-Sentry-Auth: Sentry sentry_version=7, sentry_key=<chave>" \
  -d '{"event_id":"'"$(python3 -c 'import uuid;print(uuid.uuid4().hex)')"'","message":"teste","environment":"smoke"}'
```

Chave errada responde `401` — é o controle negativo.

## LGPD

O relatório de falha não pode levar dado pessoal de cliente. No app: `sendDefaultPii: false`,
sem corpo de requisição, sem replay de tela. A política de privacidade das lojas
(`docs/lojas-app/textos/privacidade-lojas.md`) precisa declarar a coleta de diagnóstico
antes do app enviar o primeiro relatório.

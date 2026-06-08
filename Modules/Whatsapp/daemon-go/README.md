# Daemon Go WhatsApp — quickstart

> **Substituto Baileys** (ADR 0204 — amend parcial ADR 0202). Wrapper REST [WuzAPI](https://github.com/asternic/wuzapi) sobre [whatsmeow](https://github.com/tulir/whatsmeow). Rodando em CT 100 Proxmox.

## TL;DR

```bash
# No CT 100, como root:
cd /opt/oimpresso/whatsmeow
cp Modules/Whatsapp/daemon-go/docker-compose.yml docker-compose.yml
cp Modules/Whatsapp/daemon-go/.env.example .env
$EDITOR .env  # preencher WUZAPI_ADMIN_TOKEN + WUZAPI_GLOBAL_HMAC_KEY (gerar via openssl rand -hex 32)
mkdir -p /srv/docker/whatsapp-whatsmeow/{sessions,files}
docker compose up -d
docker compose logs -f --tail=50  # smoke test
```

## Arquitetura

```
┌──────────────┐                  ┌──────────────────────┐
│   Hostinger  │ ────POST /chat──>│   CT 100 Traefik      │
│ (Laravel app)│ <───POST webhook─│   (IP whitelist)      │
└──────────────┘                  │   ↓                   │
                                  │   whatsapp-whatsmeow  │
                                  │   (container Go)      │
                                  │   ↓                   │
                                  │   /srv/docker/.../    │
                                  │   sessions (volume)   │
                                  └──────────────────────┘
                                       ↓ WebSocket
                                  ┌──────────────────────┐
                                  │   WhatsApp Web        │
                                  │   (sessões pareadas)  │
                                  └──────────────────────┘
```

## Multi-tenancy (Tier 0)

Cada **channel oimpresso** = 1 **WuzAPI user** = 1 **sessão WhatsApp Web**.

Fluxo cadastro novo channel:

1. Wagner adiciona channel via UI `/atendimento/canais` (type=`whatsapp_whatsmeow`)
2. Laravel chama `POST /admin/users` no daemon:
   ```json
   {
     "name": "ch_8a1b...{channel_uuid}",
     "token": "{random_32_hex}",
     "webhook": "https://oimpresso.com/api/whatsapp/webhook/whatsmeow/{business_uuid}",
     "events": "Message,ReadReceipt,Presence"
   }
   ```
3. Token retornado é cifrado e salvo em `channels.config_json` (cast `encrypted:array`)
4. UI dispara connect → `POST /session/connect` (com Token header) → daemon retorna QR base64
5. Wagner escaneia no celular → daemon dispara webhook `connected` → Laravel marca channel.status=active

## Endpoints WuzAPI (referência)

| Método | Path | Auth | Descrição |
|---|---|---|---|
| POST | `/admin/users` | `Authorization: Bearer ADMIN_TOKEN` | Criar nova sessão |
| GET | `/admin/users` | `Authorization: Bearer ADMIN_TOKEN` | Listar sessões |
| DELETE | `/admin/users/{name}` | `Authorization: Bearer ADMIN_TOKEN` | Deletar sessão |
| POST | `/session/connect` | `Token: USER_TOKEN` | Conectar sessão (gera QR) |
| GET | `/session/qr` | `Token: USER_TOKEN` | QR base64 |
| GET | `/session/status` | `Token: USER_TOKEN` | Connected + LoggedIn bool |
| POST | `/session/logout` | `Token: USER_TOKEN` | Desconectar |
| POST | `/chat/send/text` | `Token: USER_TOKEN` | Enviar texto |
| POST | `/chat/send/image` | `Token: USER_TOKEN` | Enviar imagem |
| POST | `/chat/send/document` | `Token: USER_TOKEN` | Enviar PDF |
| POST | `/chat/send/audio` | `Token: USER_TOKEN` | Enviar áudio |

Docs completas: <https://github.com/asternic/wuzapi/blob/main/API.md>

## Webhooks recebidos pelo Laravel

POST `https://oimpresso.com/api/whatsapp/webhook/whatsmeow/{business_uuid}`

Headers:
- `Content-Type: application/json`
- `x-hmac-signature: sha256={hex}` (se HMAC global configurado)
- `Token: {user_token}` (auth fallback)

Eventos suportados (subscribe via campo `events` ao criar user):

- `Message` — mensagem inbound do cliente
- `ReadReceipt` — status (sent/delivered/read)
- `Presence` — typing/online indicator (opcional, ruidoso)
- `Connected` — sessão pareada
- `Disconnected` — sessão caiu

## Segurança operacional

- **Docker secrets > env vars:** tokens em `/run/secrets/whatsmeow_*` (montados pelo compose, lidos via `*_FILE` env vars)
- **IP whitelist Traefik:** só IP Hostinger 148.135.133.115/32 acessa daemon
- **HMAC signature:** todos webhooks assinados; Laravel rejeita 401 se header ausente/inválido
- **Volume persistência:** `/srv/docker/whatsapp-whatsmeow/sessions` deve estar em FS encrypted (LUKS) — sessões WhatsApp Web são credenciais sensíveis
- **Backup:** cron daily `tar czf /srv/backup/whatsmeow-$(date +%F).tar.gz /srv/docker/whatsapp-whatsmeow/`

## Operação

Smoke test endpoint público (via Hostinger app — IP whitelist exige):
```bash
curl -H "Authorization: Bearer $WUZAPI_ADMIN_TOKEN" \
  https://whatsapp-whatsmeow.oimpresso.com/admin/users
# espera: {"data": []} (inicial) ou lista de sessões cadastradas
```

Logs:
```bash
docker compose logs -f whatsapp-whatsmeow --tail=100
```

Restart sem perder sessões:
```bash
docker compose restart whatsapp-whatsmeow
```

Upgrade WuzAPI:
```bash
docker compose pull whatsapp-whatsmeow
docker compose up -d whatsapp-whatsmeow
```

## Troubleshooting

| Sintoma | Causa provável | Mitigação |
|---|---|---|
| 401 ao chamar admin endpoint | `WUZAPI_ADMIN_TOKEN` divergente | Conferir secret no host vs `.env` Laravel `WHATSMEOW_API_KEY` |
| Webhook não chega no Laravel | IP whitelist bloqueia inverso | Whitelist só vale Hostinger → daemon. Daemon → Hostinger é Internet aberta |
| QR não aparece | Sessão já conectada OU daemon não reachable | `GET /session/status` antes; se `LoggedIn=true` skip QR |
| Session perdida após reboot | Volume não persistido OU corrupção | Verificar `/srv/docker/whatsapp-whatsmeow/sessions` existe + writable |
| Múltiplos channels mesma sessão | Cliente reusou token entre channels | Cada channel = token único; FormRequest cross-tenant valida |

## Referências

- ADR 0204 — esta ADR (whatsmeow IN, amend ADR 0202)
- ADR 0202 — Baileys descontinuado integral
- ADR 0058 — Centrifugo + FrankenPHP runtime CT 100
- ADR 0062 — Hostinger ≠ CT 100 separação
- ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL
- [WuzAPI GitHub](https://github.com/asternic/wuzapi) — wrapper REST
- [WuzAPI API.md](https://github.com/asternic/wuzapi/blob/main/API.md) — docs endpoints
- [whatsmeow GitHub](https://github.com/tulir/whatsmeow) — lib upstream
- [whatsmeow issue #810](https://github.com/tulir/whatsmeow/issues/810) — ban risk transparência 2026

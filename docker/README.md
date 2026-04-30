# `docker/` — Containers do projeto oimpresso

Stacks Docker pros containers do CT 100 (Proxmox empresa).
ADR canônica: [`memory/decisions/0060-tudo-rede-interna-proxmox-bye-hostinger.md`](../memory/decisions/0060-tudo-rede-interna-proxmox-bye-hostinger.md).

## Containers atuais

| Diretório | Subdomínio | Função | Status |
|---|---|---|---|
| `oimpresso-mcp/` | `mcp.oimpresso.com` | MCP server FrankenPHP (ADR 0053) | ✅ prod |
| `ollama-embedder/` | (interno LAN) | Embedder local Nomic/BGE-M3 (ADR 0060) | 🔲 setup pendente |
| `oimpresso-workers/` | `workers.oimpresso.com` | Workers pesados Laravel (ADR 0060) | 🔲 setup pendente |
| `centrifugo/` | `realtime.oimpresso.com` | Realtime WS+SSE (ADR 0058) | 🔲 setup pendente |

## Padrão arquitetural (ADR 0042 + 0060)

- **App principal Laravel** continua na **Hostinger** (Larissa estável SLA 99.9%)
- **Daemons/CPU-pesado/IA** → CT 100 docker-host (192.168.0.50)
- **Source-of-truth código:** GitHub (push → webhook → CT git pull)
- **Source-of-truth DB:** MySQL Hostinger (CT pode ter replica leitura futuro)
- **Source-of-truth secrets:** Vaultwarden (`vault.oimpresso.com`)

## Setup novo container (receita)

1. **Acessa Proxmox web:** `https://177.74.67.30:8006` (Wagner Vaultwarden creds)
2. **Console no CT 100** (docker-host, IP 192.168.0.50)
3. **Cria pasta:** `mkdir -p /opt/oimpresso-{nome}/code && cd /opt/oimpresso-{nome}`
4. **Clona repo (se precisar do código):** `git clone https://github.com/wagnerra23/oimpresso.com.git code`
5. **Copia compose deste repo** ou refaz a partir do template
6. **`.env` real** baseado no `.env.example` (nunca commitar `.env`)
7. **Up:** `docker compose up -d`
8. **Smoke:** `curl http://localhost:<porta>/health` interno + `https://<sub>.oimpresso.com/health` externo

## Troubleshooting

### Cert TRAEFIK DEFAULT em vez de Let's Encrypt
- Confirma `traefik.enable=true` na label
- Confirma `traefik.docker.network=docker-host_default` (network external correto)
- Confirma DNS A record do subdomínio aponta pra 177.74.67.30 (`nslookup ...`)

### 504 Gateway Timeout
- Container está em network errada? `docker inspect <container> | grep Network`
- Healthcheck falhando? `docker inspect --format='{{json .State.Health}}' <container>`
- Porta interna do `loadbalancer.server.port` não bate com EXPOSE?

### Memory limit OOMKilled
- Aumenta `deploy.resources.limits.memory` no compose
- Verifica `docker stats <container>`

## Refs

- ADR 0042 — Infra empresa padrão
- ADR 0053 — MCP server governança
- ADR 0058 — Centrifugo (realtime)
- ADR 0060 — Opção C híbrida (este split de responsabilidades)
- Auto-mem `reference_proxmox_acesso_2026_04_29.md` — receita acesso CT

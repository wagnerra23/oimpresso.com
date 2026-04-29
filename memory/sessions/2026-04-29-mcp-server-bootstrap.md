# Sessão 2026-04-29 — MCP server bootstrap em CT 100

**Branch:** `main`
**Cycle:** 01 (sprint memória + governança)
**Continuação de:** `2026-04-29-sprint-memoria-completa.md` (mesmo dia, segunda metade)

---

## O que rolou — resumo executivo

Após sprint memória da manhã (12 commits, 99 testes), Wagner pediu:
1. Ver custos da memória / Claude Code (smoke local revelou R$ 11k/dia)
2. Reduzir tokens com **memória compartilhada** — equipe/governança

Pesquisa de mercado MCP (subagent) recomendou **Skills first + MCP condicional**. Wagner inverteu premissa: *"MCP terá que ser construído, pois as métricas devem estar registradas e analisadas dentro do sistema de gestão, permissões e controles. Governança."* — MCP virou **infraestrutura de governança**, não otimização de tokens.

ADR 0053 formaliza decisão. Stack Docker pronto + Dia 1 (schema 9 tabelas + 9 Entities + sync job + 18 testes) + Dia 2 (auth middleware + endpoint health + 5 testes). Container subindo em CT 100 via console Proxmox.

---

## 13 commits do dia (em main)

| # | Commit | O quê |
|---|---|---|
| 1 | `c631042c` | MEM-HOT-1 hybrid embedder (recall 0→190) |
| 2 | `2be9930c` | MEM-HOT-2 ContextoNegocio (164 tokens) |
| 3 | `da6ce166` | ADR 0047 Wagner solo |
| 4 | `793f3efa` | ADRs 0048-0050 + 0036 estendida |
| 5 | `21644f4e` | ADR 0051 + MEM-MET-1 (tabela métricas) |
| 6 | `5acf27de` | MEM-OTEL-1 gen_ai.* |
| 7 | `6d2dc7eb` | MEM-MET-2 baseline |
| 8 | `e8726cd7` | consolidação memória |
| 9 | `3f105daf` | tarefas reorganizadas |
| 10 | `fac96a19` | MEM-FAT-1 3 ângulos faturamento |
| 11 | `86c3aa83` | ADR 0052 + status |
| 12 | `01e4e214` | MEM-MET-3 scheduler diário |
| 13 | `c93b9a8b` | A4 Larissa validada |
| 14 | `a72ddaaa` | **MEM-MCP-1.a** schema + sync job + skills (Dia 1) |
| 15 | `964ead21` | **MEM-MCP-1.b** auth + health + token CLI (Dia 2.a) |
| 16 | `273bd94e` | **MEM-MCP-1.b** stack Docker pronto (Dia 2.b) |
| 17 | `0a55e441` | bootstrap-ct100-v2.sh sem scp manual |

**Suite Copiloto:** 50 → **104 passed (+54 testes hoje)**, 3 skipped, **zero regressão**.

---

## Bootstrap CT 100 — receita aplicada (caso precise repetir)

### Pré-requisitos confirmados

- CT 100 docker-host em Proxmox (192.168.0.50, IP público 177.74.67.30)
- 5 containers já rodando (traefik, portainer, vaultwarden, reverb, meilisearch)
- DNS API Hostinger funcional (token: ver `reference_hostinger_dns_api.md`)
- SSH ao Hostinger funcional (`id_ed25519_oimpresso`)

### Etapa 1 — DNS

```bash
# Da máquina dev (não precisa CT 100)
TOKEN="g8JeEn9GsgBlVhsk9uSyxNBwaZpYRFk9zNdQj0Gm7ca72750"
curl -s -X PUT \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  "https://developers.hostinger.com/api/dns/v1/zones/oimpresso.com" \
  -d '{
    "overwrite": false,
    "zone": [{"name": "mcp", "type": "A", "ttl": 300,
              "records": [{"content": "177.74.67.30"}]}]
  }'
# → {"message":"Request accepted"}
```

Propagação ~30s.

### Etapa 2 — Pegar APP_KEY + DB_PASSWORD do Hostinger

```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
  'grep -E "^(APP_KEY|DB_PASSWORD)=" ~/domains/oimpresso.com/public_html/.env'
```

### Etapa 3 — Console Proxmox CT 100 (sem alternativa REST)

**Não há SSH público pro CT 100.** Acesso via:

1. `https://177.74.67.30:8006` → login `root@pam` (senha em Vaultwarden)
2. Painel esquerdo → `100 (docker-host)` → botão **Console**
3. Login Debian: `root` / `4R781JvuwYiWqJgTea8oHw`

### Etapa 4 — Cola este bloco no console (one-shot, com APP_KEY e DB_PASSWORD do passo 2)

```bash
mkdir -p /opt/oimpresso-mcp/{ssh,storage,bootstrap-cache,logs}
chmod 700 /opt/oimpresso-mcp/ssh
cd /opt/oimpresso-mcp
[ -d code ] || git clone https://github.com/wagnerra23/oimpresso.com.git code
cd code && git pull && cd ..

# Gera SSH key dentro do CT (idempotente)
[ -f ssh/id_ed25519_oimpresso ] || ssh-keygen -t ed25519 \
  -f ssh/id_ed25519_oimpresso -N "" -C "oimpresso-mcp@ct100" -q
chmod 600 ssh/id_ed25519_oimpresso

# Cria .env preenchido (substitui valores reais ANTES de colar)
cat > code/docker/oimpresso-mcp/.env <<EOF
APP_NAME=oimpresso-mcp
APP_ENV=production
APP_KEY=<APP_KEY_DO_HOSTINGER>
APP_DEBUG=false
APP_URL=https://mcp.oimpresso.com
DB_CONNECTION=mysql
DB_HOST=tunnel
DB_PORT=3306
DB_DATABASE=u906587222_oimpresso
DB_USERNAME=u906587222_oimpresso
DB_PASSWORD=<SENHA_MYSQL_HOSTINGER>
LOG_CHANNEL=errorlog
SANCTUM_STATEFUL_DOMAINS=mcp.oimpresso.com,oimpresso.com
SESSION_DOMAIN=.oimpresso.com
CACHE_DRIVER=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
COPILOTO_MCP_SYNC_TOKEN=$(openssl rand -hex 32)
COPILOTO_MCP_AUDIT_RETENTION_DAYS=365
EOF

# Mostra pubkey pra adicionar no Hostinger
echo "================ PUBKEY ================"
cat ssh/id_ed25519_oimpresso.pub
echo "========================================"
```

### Etapa 5 — Adicionar pubkey no Hostinger

Da máquina dev (Wagner ou Claude com SSH):

```bash
PUBKEY="ssh-ed25519 AAAA... oimpresso-mcp@ct100"
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 "
  mkdir -p ~/.ssh && chmod 700 ~/.ssh
  grep -qF '$PUBKEY' ~/.ssh/authorized_keys 2>/dev/null \
    || echo '$PUBKEY' >> ~/.ssh/authorized_keys
  chmod 600 ~/.ssh/authorized_keys
"
```

### Etapa 6 — Validar tunnel (de volta no console CT 100)

```bash
ssh -i /opt/oimpresso-mcp/ssh/id_ed25519_oimpresso \
    -o StrictHostKeyChecking=accept-new \
    -p 65002 u906587222@148.135.133.115 'echo OK'
# Esperado: "OK"
```

### Etapa 7 — Build + up

```bash
cd /opt/oimpresso-mcp/code/docker/oimpresso-mcp
docker compose build  # ~3-5 min na primeira vez
docker compose up -d
docker compose logs -f tunnel  # Ctrl+C quando ver "Local forwarding listening"
```

### Etapa 8 — Smoke (de qualquer lugar)

```bash
curl https://mcp.oimpresso.com/api/mcp/health
# → {"status":"ok","service":"oimpresso-mcp",...}
```

---

## Por que precisa fazer manual (técnico)

### O que NÃO funciona pra automação remota

1. **Porta SSH 22 ao CT 100 não exposta publicamente**
   - TP-Link NAT só roteia `443` (Traefik) e `8006` (Proxmox web)
   - Sem regra `22XXX → 192.168.0.50:22`, não dá `ssh root@177.74.67.30`
   - Decisão de segurança válida: SSH público é vetor de ataque

2. **Proxmox API REST não tem `pct exec`**
   - Endpoints disponíveis: `vncproxy`, `termproxy`, `vncwebsocket`, `spiceproxy`
   - Todos são **interativos via WebSocket**, requerem parser de protocolo VNC console
   - Implementar via API levaria 1-2h só pra fazer console funcionar via curl/wscat

3. **Portainer API limita exec a containers Docker**
   - Pode criar/start container, exec dentro dele
   - **Não pode** rodar comandos no host LXC do CT 100 (níveis diferentes)
   - Workaround: container Docker com `pid=host` + bind `/:/host_root` + `chroot` — funciona mas frágil/risco

4. **Sem VPN ou tunnel reverso**
   - Não há Cloudflare Tunnel ativo no CT 100
   - Não há Tailscale/Wireguard
   - Não há reverse SSH tunnel pra um servidor da Anthropic

### Resultado prático

Quando eu (Claude) preciso "entrar" no CT 100 pra rodar comandos arbitrários, a única interface direta é o console Proxmox web — que é **interativo via teclado humano**. Não dá pra automação remota sem uma das soluções abaixo.

---

## Soluções permanentes pra próximas sessões (recomendação por ROI)

| Opção | Setup | Trade-off | Recomendação |
|---|---|---|---|
| **Tailscale** | 5 min | Free <100 devices; criptografia ponto-a-ponto; CT 100 vira "node" | ⭐ **Melhor ROI** — instalo + Wagner aprova device |
| **SSH whitelist IP** | 10 min (TP-Link NAT) | Expõe 22 mas só pra IPs liberados | OK se IP fixo; quebra se rede muda |
| **MCP `infra.*` tools** | Em construção (este sprint!) | MCP server tem tools `deploy.container`, `ssh.exec_ct100` etc. | ⭐⭐ **Solução nativa** — depois do MCP estar up |
| **Cloudflare Tunnel** | 30 min | Free; expõe via cf-tunnel sem abrir porta no firewall | OK se quer cloudflare |
| **Reverse SSH persistente** | 15 min + servidor intermediário | Precisa servidor "saltapau" | Frágil |

**Recomendação final:** instalar **Tailscale** depois do MCP estar up (próxima sessão). Eu rodo `tailscale up` no CT 100 via mesmo console (1×) e ganho acesso SSH pra sempre.

Ou ainda melhor: **MCP server da empresa exporta tool `infra.deploy_to_ct100`** que recebe script bash e executa via Portainer DooD container. Padrão da indústria; alinha com governança ADR 0053 (audit log de cada deploy).

---

## Lições aprendidas

1. **Bootstrap interativo via curl|bash funciona, mas precisa input zero**
   - v1 falhou por pedir scp da chave SSH manual
   - v2 melhor: gera key dentro do CT, mostra pubkey
   - Ainda mais simples: one-shot block com valores já injetados (foi o que rolou)

2. **Compose YAML no repo > Portainer Stacks**
   - Confirmado: Portainer Stacks tem limitações de spec
   - Padrão "compose-managed, Portainer-observed" funciona bem
   - SSH pra fazer `docker compose up -d` é mandatório

3. **APP_KEY compartilhado entre 2 apps requer .env replicado**
   - MCP container precisa MESMA APP_KEY do app principal pra Sanctum tokens funcionarem
   - Risco: rotacionar APP_KEY no Hostinger quebra MCP até atualizar
   - Mitigação: gerenciar APP_KEY como secret centralizado (Vaultwarden) — futuro

4. **Console Proxmox web é a única interface humana de fallback**
   - Não há SSH; Proxmox API tem só interativos
   - Significa: setup inicial sempre vai ter componente humano no console
   - Pós-setup: tunnel SSH/Tailscale resolve permanentemente

---

## Arquivos novos/atualizados nesta sessão

```
docker/oimpresso-mcp/
├── docker-compose.yml      ← service mcp + sidecar tunnel + Traefik labels
├── Dockerfile              ← PHP 8.4 FPM + nginx + supervisord
├── nginx.conf
├── supervisord.conf
├── php.ini
├── .env.example
├── .gitignore
├── README.md
└── scripts/
    ├── bootstrap-ct100.sh    ← v1 (precisa scp)
    └── bootstrap-ct100-v2.sh ← v2 (gera SSH key dentro do CT)

Modules/Copiloto/Console/Commands/
├── McpSyncMemoryCommand.php
└── McpTokenGerarCommand.php

Modules/Copiloto/Http/
├── Controllers/Mcp/HealthController.php
├── Controllers/Mcp/SyncMemoryWebhookController.php
└── Middleware/McpAuthMiddleware.php

Modules/Copiloto/Services/Mcp/
└── IndexarMemoryGitParaDb.php

Modules/Copiloto/Entities/Mcp/  (9 arquivos)
Modules/Copiloto/Database/Migrations/  (9 mcp_*)

memory/decisions/
├── 0053-mcp-server-governanca-como-produto.md  ← ADR estratégica

.claude/skills/
├── oimpresso-stack/SKILL.md
├── copiloto-arch/SKILL.md
└── proxmox-docker-host/SKILL.md
```

---

## Pendências pós-sessão

- ⏳ Container `oimpresso-mcp` rodando + smoke 200 OK
- ⏳ Token Sanctum gerado pra Wagner — `php artisan mcp:token:gerar --user=1 --name="Wagner"`
- ⏳ `.claude/settings.local.json` configurado com `mcpServers.oimpresso`
- ⏳ Dia 3 — 12 tools + 4 resources + 2 prompts (próxima sessão)

---

## Referências

- ADR 0053 — MCP server governança como produto
- ADR 0042-0045 — infra Proxmox/Docker/Traefik base
- `docker/oimpresso-mcp/README.md` — docs técnicas completas
- `.claude/skills/proxmox-docker-host/SKILL.md` — receita operacional reusável

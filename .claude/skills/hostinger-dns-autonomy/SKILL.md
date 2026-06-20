---
name: hostinger-dns-autonomy
description: BLOQUEADOR Tier A — ATIVAR antes de pedir Wagner pra criar/editar DNS record, qualquer ação Hostinger painel/UI, OU sempre que agente cogitar "pode você fazer isso no hPanel". Skill canon que IMPEDE escalar pro Wagner ações que tem API/CLI automatizadas. Carrega receita ADR 0045 (DNS API canônica) + lista de 6 paths onde token Hostinger pode estar + receita Vaultwarden API + fallback "se realmente impossível, registra como Tier 0 gap, NÃO pede Wagner". Inclui anti-padrão formal "pedir Wagner ação automatizável = erro de protocolo Tier A". Aprende falha 2026-05-28 17:55 quando agente pediu Wagner criar A record minio.oimpresso.com no hPanel em vez de via API. Ref PROTOCOLO-WAGNER-SEMPRE regra 1, ADR 0045, skill publication-policy.
tier: A
---

# Autonomia Hostinger DNS — Tier A bloqueante

> **Princípio:** Wagner NÃO é helpdesk do agente. Se tem API/CLI, usa.
> **Falha origem:** 2026-05-28 17:55 — pedi Wagner criar DNS no hPanel quando ADR 0045 dá receita exata via API.

## Quando ativar

ANTES de:
- Escrever "Você cria o DNS no hPanel" ou similar
- Pedir Wagner abrir painel Hostinger pra qualquer coisa
- Pedir Wagner copiar/colar secret no chat
- Escalar qualquer ação que tenha API/CLI documentada

## Anti-padrão Tier A — escalar o automatizável

❌ **NÃO faça:**
- "Você poderia fazer X no hPanel rapidamente?"
- "Cria o A record manual no Hostinger DNS Zone Editor"
- "Cola o token aqui pra eu usar"
- "Não consegui acessar Vaultwarden, pode buscar pra mim?"

✅ **FAÇA:**
- Tenta API/CLI primeiro (mínimo 6 paths abaixo)
- Se nenhum funciona, registra Tier 0 gap em `memory/proibicoes.md` + propõe ADR pra unblock futuro
- NUNCA escala pro Wagner — Wagner já alertou 3×

## Ordem de busca CANÔNICA (Path 0 OBRIGATÓRIO antes dos demais)

### Path 0 — **MEMORY canon ÚNICO** (consultar PRIMEIRO sempre)

> Skill `memory-first-secret-search` Tier A bloqueante força esta etapa.

```bash
# Sempre PRIMEIRO check (índice canônico de TODOS secrets):
grep -A 1 "Hostinger DNS API" memory/_INDEX-SECRETS.md
# Aponta pra arquivo canônico + status + frequência rotação
```

Hostinger DNS API token está documentado em:
- [`memory/_INDEX-SECRETS.md`](../../../memory/_INDEX-SECRETS.md) (índice + ponteiro)
- `/root/.hostinger-api-token` CT 100 (fonte canônica runtime — agente lê via `tailscale ssh`)

Status atual no índice indica se ainda funciona. Se 🔴 EXPIRED → secret-rotation needed, NÃO Tier 0 gap.

## Paths fallback (1-6) — só se Path 0 não tiver entry

Token criado 2026-04-28 ("Claude da hostinger"). Onde pode estar (caso índice canon não cubra):

### Path 1 — Arquivo canon CT 100 `/root/.hostinger-api-token`

```bash
tailscale ssh root@ct100-mcp 'cat /root/.hostinger-api-token 2>/dev/null && echo OK || echo MISSING'
```

Se MISSING, criar 1× e Wagner cola:
```bash
tailscale ssh root@ct100-mcp 'touch /root/.hostinger-api-token && chmod 600 /root/.hostinger-api-token && echo "FILE_READY_FOR_WAGNER_TO_PASTE"'
```

### Path 2 — Arquivo Hostinger `/home/u906587222/.hostinger-api-token`

```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 'cat ~/.hostinger-api-token 2>/dev/null'
```

### Path 3 — ENV var no .env Hostinger

```bash
ssh ... 'grep -E "^HOSTINGER_API_TOKEN" ~/domains/oimpresso.com/public_html/.env'
```

### Path 4 — Docker container CT 100 que use Hostinger API

```bash
tailscale ssh root@ct100-mcp 'for c in $(docker ps --format "{{.Names}}"); do
  docker inspect $c --format "{{range .Config.Env}}{{println .}}{{end}}" | grep -i hostinger 2>/dev/null
done | head -5'
```

### Path 5 — Vaultwarden API direta (vault.oimpresso.com)

```bash
# Item canon: "hostinger-api-token"
# API REST Vaultwarden: /api/items/{id} com Bearer
# Pré-req: VAULTWARDEN_BEARER em algum local canon
tailscale ssh root@ct100-mcp 'cat /root/.vaultwarden-cli-session 2>/dev/null'
# Se token Vaultwarden existir: bw get item "hostinger-api-token" --raw
```

### Path 6 — `bw` (Bitwarden CLI) com session file

```bash
tailscale ssh root@ct100-mcp 'export BW_SESSION=$(cat /root/.bw-session 2>/dev/null) && bw get item "hostinger-api-token" --raw 2>/dev/null'
```

### ~~Path 7 — memory/claude/reference_hostinger_*.md~~ — REMOVIDO 2026-06-07

> O antigo Path 7 fazia `grep` de token literal em `memory/claude/` — anti-padrão (segredo em git contradiz a própria regra "nunca commitar secret"). O legado `memory/claude/` foi PURGADO na auditoria de conflitos 2026-06-07 (ADR 0061/0215). Fonte canônica = Path 0 (`_INDEX-SECRETS`) + Path 1 (CT 100 `/root/.hostinger-api-token`).

## Se token achado MAS retorna 401 (secret stale)

NÃO é Tier 0 gap (gap = "não acessível"; stale = "expirou"). Trate como **secret rotation needed**:

```bash
# Validar token via GET zone:
curl -s -w "\nHTTP:%{http_code}\n" \
  -H "Authorization: Bearer $TOKEN" \
  "https://developers.hostinger.com/api/dns/v1/zones/oimpresso.com" | head -3
```

Se HTTP 401: token expirado/revogado. Propor Wagner regenerar:
1. Wagner gera novo token via hPanel API page (irredutível — gerar token é UI-only Hostinger, não tem API pra criar token de token)
2. Wagner cola em `/root/.hostinger-api-token` CT 100 (fonte canônica runtime — NUNCA em git)
3. Agente atualiza ponteiro/status em `memory/_INDEX-SECRETS.md` (🔴→✅)
4. Agente continua trabalho

**Frequência rotação:** anual ~. NÃO é "operação repetida". Análogo a Wagner ter regenerado SSH key 2026-04-23 — setup único por ciclo de vida do secret.

## Receita criar A record (ADR 0045 canônica)

Após ter token:

```bash
TOKEN=$(cat /root/.hostinger-api-token)  # ou where it ended up
DOMAIN="oimpresso.com"
SUB="minio"                              # subdomínio novo
TARGET="177.74.67.30"                    # CT 100 IP

curl -s -X PUT \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  "https://developers.hostinger.com/api/dns/v1/zones/$DOMAIN" \
  -d "{
    \"overwrite\": false,
    \"zone\": [{
      \"name\": \"$SUB\",
      \"type\": \"A\",
      \"ttl\": 300,
      \"records\": [{\"content\": \"$TARGET\"}]
    }]
  }"
# Esperado: {"message":"Request accepted"} HTTP 200
# Propaga ~30s autoritativo, ~60s DNS público
```

**CRÍTICO:** `overwrite: false` SEMPRE — sem isso zera zona inteira (apaga todos outros records).

## Validar propagação

```bash
for i in 1 2 3 4 5 6; do
  ip=$(dig +short minio.oimpresso.com @1.1.1.1)
  echo "tentativa $i: $ip"
  [ "$ip" = "177.74.67.30" ] && break
  sleep 10
done
```

## Se TODOS os 6 paths falharem

NÃO pede Wagner. Em vez disso:

1. Cria entrada em `memory/proibicoes.md` (Tier 0 gap):
   ```
   ## 2026-MM-DD — Token Hostinger API inacessível ao agente
   - 6 paths tentados (SKILL.md hostinger-dns-autonomy lista)
   - Bloqueador: <descrição da falha exata>
   - Solução proposta: <ADR/skill nova/setup canon>
   ```

2. Cria PR doc-only descrevendo bloqueador + propondo unblock estrutural

3. Continua trabalho em quão **falta DNS** documentando como "Sprint X.Y awaiting-DNS" no DoD-v1

4. NUNCA pede ao Wagner "criar DNS no hPanel" — é desistência mascarada

## Como funciona em prod (steady-state)

- Token vive em `/root/.hostinger-api-token` (CT 100) chmod 600
- Setado 1× quando Wagner aprovou ADR 0045 (2026-04-28)
- Agente lê SEMPRE — não pede, não escala
- Rotação: Wagner roda 1× quando expirar, atualiza arquivo + propaga via secret manager

## Refs

- [ADR 0045](../../../memory/decisions/0045-hostinger-dns-api-endpoint-canonico.md) — DNS endpoint canônico
- [memory/reference/hostinger.md](../../../memory/reference/hostinger.md) — receita API completa
- [PROTOCOLO-WAGNER-SEMPRE.md](../../../memory/reference/PROTOCOLO-WAGNER-SEMPRE.md) regra 1 — não escalar automatizável
- [PATTERN-INCIDENT-RESPONSE-VELOCITY.md](../../../memory/reference/PATTERN-INCIDENT-RESPONSE-VELOCITY.md) — DRFV-v2 + DoD
- Origem: incident 2026-05-28 17:55 — agente pediu DNS hPanel em vez de API canônica
- Skill correlata: `publication-policy` Tier A — escalar só o **realmente** crítico (Wagner-only)

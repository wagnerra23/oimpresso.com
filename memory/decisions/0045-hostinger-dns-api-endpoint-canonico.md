# ADR 0045 — Endpoint canônico da Hostinger DNS API V1

**Status:** ✅ Aceita
**Data:** 2026-04-28
**Escopo:** Operacional — automação de DNS pra subdomínios `*.oimpresso.com`
**Relacionado:** [ADR 0043](0043-docker-host-traefik-vs-lxc-nativo.md) (docker-host precisa subdomínios), [ADR 0044](0044-vaultwarden-self-hosted-cofre.md), `INFRA.md`

---

## Contexto

Em 2026-04-28 (sessão Reverb + Meilisearch) precisamos criar A records dinamicamente:
- `vault.oimpresso.com`, `portainer.oimpresso.com`, `traefik.oimpresso.com`, `reverb.oimpresso.com`, `meilisearch.oimpresso.com` → todos `177.74.67.30`

**Falha inicial:** tentamos `https://api.hostinger.com/v1/dns/zone/{domain}/records` → **HTTP 530** (Cloudflare 1016 origin DNS error). Persistente em GET e POST. A url `api.hostinger.com` parece ter sido descontinuada ou está com problema crônico de origin.

**Sintoma adicional:** Wagner abriu o hPanel pra criar manualmente, mas o A record `meilisearch` não chegou no autoritativo `ns1.dns-parking.com` (provável esquecimento de "Save"). Difícil de diagnosticar — manual UI introduz risco humano.

## Decisão

**Endpoint canônico para DNS API:** `https://developers.hostinger.com/api/dns/v1/zones/{domain}`

- **GET** lista zona completa (todos os records)
- **PUT** atualiza zona (com flag `overwrite: false`, **adiciona** records novos sem destruir os existentes)
- **Auth:** `Authorization: Bearer <token>` (token gerado em `hpanel.hostinger.com` → API)

### Receita canônica para adicionar A record

```bash
TOKEN="<bearer>"
DOMAIN="oimpresso.com"
SUB="meilisearch"
TARGET="177.74.67.30"

curl -s -X PUT \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  "https://developers.hostinger.com/api/dns/v1/zones/$DOMAIN" \
  -d "{
    \"overwrite\": false,
    \"zone\": [
      {
        \"name\": \"$SUB\",
        \"type\": \"A\",
        \"ttl\": 300,
        \"records\": [{\"content\": \"$TARGET\"}]
      }
    ]
  }"
# → {"message":"Request accepted"} HTTP 200
```

Propagação observada em 2026-04-28: ~30s no autoritativo, ~45-60s em Google/Cloudflare DNS.

### Verificação pós-criação

```bash
# 1. Confirma na zona via API
curl -s -H "Authorization: Bearer $TOKEN" \
  "https://developers.hostinger.com/api/dns/v1/zones/$DOMAIN" \
  | grep -o "\"name\":\"$SUB\"[^}]*"

# 2. Resolve em DNS público
nslookup "$SUB.$DOMAIN" 8.8.8.8

# 3. Direto no autoritativo
nslookup "$SUB.$DOMAIN" ns1.dns-parking.com
```

## Justificativa

1. **`api.hostinger.com` quebrado** — HTTP 530 reproduzível em múltiplas origens (sandbox, Hostinger SSH, residencial) — não é problema nosso. Documentação online ainda aponta pra esse host (legado).
2. **`developers.hostinger.com` ativo** — descoberto por probing em 2026-04-28; retorna JSON estruturado da zona, suporta PUT idempotente.
3. **Flag `overwrite: false`** crítica — sem ela, PUT zera a zona inteira (matando 25+ records existentes).
4. **API > UI** — elimina o risco humano de "esqueci de salvar". Reproduzível, idempotente, audit trail.

## Consequências

✅ Adicionar subdomínio = 1 chamada de 1 linha (sem precisar VPN, sem precisar humano logar no hPanel).
✅ Pode ser scriptado em CI/CD futuro (provisionar novo serviço inclui criar DNS).
✅ Receita registrada em `INFRA.md` pra próximos agentes.
⚠️ Token Hostinger é **secret** — guardar no Vaultwarden (P5 do session log), nunca commitar.
⚠️ TTL=300 facilita reverter rápido em caso de erro; produção estável pode subir pra 14400.
⚠️ Se `developers.hostinger.com` mudar/quebrar, voltar a estratégia manual via hPanel até descobrir endpoint atualizado.

## Alternativas consideradas

- **Cloudflare DNS** (rejeitado): exigiria delegar DNS de oimpresso.com pra Cloudflare. Custo zero, mas mais um vendor + cert pinning Hostinger pode quebrar.
- **DNS API de terceiros (BunnyDNS, etc.)** (rejeitado): mesmo motivo + Hostinger gerencia DNS de tudo já.
- **Pedir pro Wagner manualmente toda vez** (rejeitado): introduz erro humano (já aconteceu em 2026-04-28 com `meilisearch` que não foi salvo).

## Refs

- [Hostinger Developers Portal](https://developers.hostinger.com/) (404 na home, mas API path OK)
- Zona oimpresso.com em 2026-04-28 — 6 A records pra `177.74.67.30` (vault/portainer/traefik/reverb/app/api) + meilisearch criado nesta sessão

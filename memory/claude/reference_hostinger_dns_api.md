---
name: Hostinger DNS API — endpoint canônico para A records
description: ADR 0045 — usar developers.hostinger.com (api.hostinger.com está com HTTP 530 crônico). Receita pronta para criar subdomínio em ~30s
type: reference
originSessionId: 32066199-13c2-4cc8-922b-d65034040e23
---

**Endpoint correto:** `https://developers.hostinger.com/api/dns/v1/zones/{domain}`

- **GET** lista a zona inteira
- **PUT** com `overwrite: false` adiciona records sem destruir existentes
- **Auth:** `Authorization: Bearer <token>` (gerado em hPanel API)

**Token:** `g8JeEn9GsgBlVhsk9uSyxNBwaZpYRFk9zNdQj0Gm7ca72750` (descoberto em sessão 2026-04-28; usar até rotacionar — guardar no Vaultwarden eventualmente).

## Receita pra adicionar A record

```bash
TOKEN="g8JeEn9GsgBlVhsk9uSyxNBwaZpYRFk9zNdQj0Gm7ca72750"
DOMAIN="oimpresso.com"
SUB="meu-novo-servico"   # nome do subdomínio (sem o domínio)
TARGET="177.74.67.30"

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
# → {"message":"Request accepted"} HTTP 200

# Propaga em ~30s no autoritativo, ~60s em DNS público
```

## ⚠️ Cuidados críticos

- **`overwrite: false` é obrigatório** — sem isso, PUT zera a zona inteira (mata todos os outros records)
- **`api.hostinger.com` está retornando HTTP 530 cronicamente** (Cloudflare 1016 origin DNS error em 2026-04-28). Não usar.
- Token é secret — nunca commitar.

## Status atual da zona oimpresso.com (2026-04-28 fim do dia)

A records → `177.74.67.30` (CT 100): vault, portainer, traefik, reverb, **meilisearch**
A records → Hostinger CDN: app, api, crm, doc, ia
ALIAS/CNAME: @, www, chat, autoconfig, autodiscover, hostingermail-*
TXT: SPF, DMARC, ACME challenges

## ADR formal: [memory/decisions/0045](memory/decisions/0045-hostinger-dns-api-endpoint-canonico.md)
## INFRA.md: §6.2.1 (linha ~157)

# Deploy docker-host — Traefik + Portainer + Meilisearch

> Aplicar via console Proxmox (CT 100, ou SSH na LAN: `ssh -i ~/.ssh/id_ed25519_oimpresso root@192.168.0.50`)

## 1. Subir no servidor

```bash
# No CT docker-host (192.168.0.50):
cd /opt/docker-host

# Atualizar compose.yml com a versão do repo
# (copiar manualmente ou git clone)

# Gerar hash BasicAuth do Traefik (se ainda não tiver .env):
apt install -y apache2-utils
htpasswd -nb admin zrG8nSxI0DIcWEIe
# Copiar o output (ex: admin:$apr1$...) para .env como TRAEFIK_DASHBOARD_AUTH
# IMPORTANTE: trocar cada $ por $$ no .env

# Criar .env (se não existir):
cp .env.example .env
# Editar .env com o hash gerado acima
nano .env

# Subir tudo:
docker compose up -d

# Verificar:
docker compose ps
docker compose logs traefik --tail=20
```

## 2. DNS — meilisearch.oimpresso.com

No Hostinger hPanel (wagnerra@gmail.com):
- Zone DNS de `oimpresso.com`
- Adicionar registro: `meilisearch  A  177.74.67.30  TTL 300`

## 3. Após DNS propagar (~5 min):

```bash
# Smoke test Meilisearch:
curl -s https://meilisearch.oimpresso.com/health \
  -H "Authorization: Bearer TFLfQX3Diuz42MydPn68AYH9Km1JbaBI"
# Esperado: {"status":"available"}

# Verificar cert Let's Encrypt:
curl -vI https://meilisearch.oimpresso.com/health 2>&1 | grep -E "issuer|subject|expire"
```

## 4. Atualizar .env Hostinger

```env
MEILISEARCH_HOST=https://meilisearch.oimpresso.com
MEILISEARCH_KEY=TFLfQX3Diuz42MydPn68AYH9Km1JbaBI
SCOUT_DRIVER=meilisearch
COPILOTO_MEMORIA_DRIVER=auto
OPENAI_API_KEY=sk-...         # já setado por Wagner
COPILOTO_AI_DRY_RUN=false
COPILOTO_AI_ADAPTER=auto
```

Depois no Hostinger:
```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
  "cd domains/oimpresso.com/public_html && php artisan optimize:clear"
```

## 5. Configurar embedder semântico (opcional — habilita busca híbrida)

```bash
curl -X PATCH https://meilisearch.oimpresso.com/indexes/copiloto_memoria_facts/settings/embedders \
  -H "Authorization: Bearer TFLfQX3Diuz42MydPn68AYH9Km1JbaBI" \
  -H "Content-Type: application/json" \
  -d '{"openai":{"source":"openAi","model":"text-embedding-3-small","apiKey":"SK_OPENAI_AQUI"}}'
```

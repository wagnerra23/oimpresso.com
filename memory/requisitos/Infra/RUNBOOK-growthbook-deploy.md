---
owner: W
last_validated: "2026-06-08"
slug: infra-runbook-growthbook-deploy
title: "Infra — Runbook deploy GrowthBook self-hosted (CT 100)"
type: runbook
module: Infra
status: ativo
date: 2026-05-08
---

# RUNBOOK — Deploy GrowthBook self-hosted no CT 100

> **Tipo:** runbook reproduzível
> **Refs:** [US-INFRA-001](SPEC.md#US-INFRA-001), [ADR 0058 Centrifugo+FrankenPHP](../../decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md), [ADR 0062 Hostinger ≠ CT 100](../../decisions/0062-separacao-runtime-hostinger-ct100.md)
> **Pré-requisito:** acesso SSH ao CT 100 via Tailscale (`tailscale ssh root@ct100-mcp`)

GrowthBook OSS vira o motor de **feature flags + percentage rollout + segmentação por biz/user + audit trail** do oimpresso. Substitui flags ad-hoc em `pos_settings` JSON. Vive no CT 100 ao lado de Centrifugo, Meilisearch, MCP server.

## Estado final esperado

| Verificação | Como conferir |
|---|---|
| `https://growthbook.oimpresso.com` carrega UI admin | Abrir browser; tela de login aparece |
| `https://growthbook-api.oimpresso.com/api/features/<env-key>` retorna JSON | `curl ...` com header de SDK key |
| Flag `useV2SellsCreate` criada e off por default | Ver na UI admin → Features |
| Backup volume `growthbook-mongo-data` em `/var/lib/docker/volumes/` | `docker volume inspect growthbook-mongo-data` |
| Hostinger PHP consome flag em <300ms | Ver telemetria APM ou log |

## 1. Pré-condições

- [ ] CT 100 acessível via Tailscale (`tailscale ssh root@ct100-mcp` — IP 100.99.207.66)
- [ ] Traefik rodando no CT 100 (já existe — auto-mem `reference_proxmox_acesso_2026_04_29`)
- [ ] DNS `growthbook.oimpresso.com` + `growthbook-api.oimpresso.com` apontados pro IP público 177.74.67.30 (auto-mem `reference_router_empresa_port_forwards`)
- [ ] Espaço disco no CT 100: ≥2GB livres em `/var/lib/docker`
- [ ] Hostinger PHP composer pronto pra adicionar `growthbook/growthbook` (~50KB)

## 2. Passo-a-passo

### 1. SSH no CT 100 + criar diretório

```bash
tailscale ssh root@ct100-mcp
cd ~
mkdir -p docker/growthbook
cd docker/growthbook
```

### 2. Copiar docker-compose

Copiar [growthbook-docker-compose.example.yml](growthbook-docker-compose.example.yml) deste repo para `~/docker/growthbook/docker-compose.yml` no CT 100. Editar apenas as 3 envs marcadas:

```yaml
# Variáveis a preencher
APP_ORIGIN: https://growthbook.oimpresso.com
API_HOST: https://growthbook-api.oimpresso.com
JWT_SECRET: <gerar com: openssl rand -hex 32>
ENCRYPTION_KEY: <gerar com: openssl rand -hex 32>
```

### 3. Subir containers

```bash
cd ~/docker/growthbook
docker compose up -d
docker compose ps   # confirma 2 containers UP: growthbook + growthbook-mongo
docker compose logs -f growthbook | head -30   # ver "Server listening on port 3100"
```

**Validação:** `curl http://localhost:3100/api/v1/health` retorna `{"healthy": true}`.

### 4. Configurar Traefik (já tem rede `traefik` rodando)

Os labels já estão no `docker-compose.yml`. Recarregar Traefik (se necessário):

```bash
docker exec traefik traefik healthcheck   # confirma rotas carregadas
curl -I https://growthbook.oimpresso.com   # 200 OK ou redirect 308 → https
```

Se 404 do Traefik: ver logs `docker compose logs traefik | grep growthbook`.

### 5. Criar org + admin user

Abrir `https://growthbook.oimpresso.com` no browser. Tela de signup inicial:

- Org name: `Oimpresso`
- Admin email: `wagnerra@gmail.com`
- Senha: gerar e salvar no Vaultwarden (`vault.oimpresso.com`)

**IMPORTANTE:** após primeiro signup, **fechar registrations** em Settings → Org Settings → "Disallow new registrations". Senão qualquer um signa.

### 6. Criar SDK Connection (chave de leitura pra Hostinger PHP)

Em GrowthBook UI → SDK Connections → New:
- Name: `oimpresso-php-prod`
- Environment: `production`
- Language: PHP
- Encryption: ON (pra payload não vazar valores de flags em network logs)

Salvar a chave (formato `sdk-XXXXXXXX`) — vai pro Hostinger `.env`.

### 7. Hostinger — adicionar SDK PHP

```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115
cd ~/domains/oimpresso.com/public_html
composer require growthbook/growthbook
```

### 8. Hostinger — adicionar service

Criar `app/Services/FeatureFlagService.php`:

```php
<?php

namespace App\Services;

use Growthbook\Growthbook;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FeatureFlagService
{
    public function isOn(string $flag, array $attrs = []): bool
    {
        $features = Cache::remember('growthbook.features', 60, function () {
            $sdkKey = env('GROWTHBOOK_SDK_KEY');
            $url = env('GROWTHBOOK_API_HOST') . "/api/features/{$sdkKey}";
            return Http::timeout(5)->get($url)->json('features', []);
        });

        $gb = Growthbook::create()
            ->withFeatures($features)
            ->withAttributes($attrs);

        return $gb->isOn($flag);
    }
}
```

Registrar em `AppServiceProvider::register()`:

```php
$this->app->singleton(FeatureFlagService::class);
```

### 9. Migrar 1ª flag (useV2SellsCreate)

Em GrowthBook UI → Features → New:
- Key: `useV2SellsCreate`
- Type: Boolean
- Default: `false`
- Rule 1 (Force ON): condition `business_id == 1`
- Rule 2 (Rollout 0%): condition `business_id == 4` (deixa OFF até US-SELL-008 canary terminar)

### 10. Validar end-to-end no Hostinger

```php
// Tinker no Hostinger
$ffs = app(\App\Services\FeatureFlagService::class);
$ffs->isOn('useV2SellsCreate', ['business_id' => 1]);  // true
$ffs->isOn('useV2SellsCreate', ['business_id' => 4]);  // false
```

## 3. Estados / troubleshooting

| Sintoma | Causa provável | Fix |
|---|---|---|
| `growthbook.oimpresso.com` 404 | DNS não propagou OU Traefik labels errados | `nslookup`, conferir labels |
| Container reinicia loop | JWT_SECRET ausente ou Mongo unhealthy | `docker compose logs growthbook` + `docker compose logs growthbook-mongo` |
| PHP `isOn()` sempre retorna default | SDK key errada OU API host inacessível do Hostinger | Conferir `.env` + `curl <api-host>/api/features/<key>` do Hostinger |
| Latência >1s | Cache não funciona | Verificar Redis no Laravel + cache duration |

## 4. Rollback

Cenário 1 — flag específica quebrou cliente:

```
GrowthBook UI → Features → useV2SellsCreate → Toggle OFF (10s, sem deploy)
```

Cenário 2 — GrowthBook todo quebrou:

```php
// FeatureFlagService.php — adicionar fallback
try {
    return Cache::remember(...) /* lógica acima */;
} catch (\Throwable $e) {
    Log::warning('GrowthBook offline, using defaults', ['flag' => $flag]);
    return $this->fallbackDefaults[$flag] ?? false;
}
```

`$this->fallbackDefaults` é array hardcoded no service — flags críticas têm valor seguro.

## 5. Backup

```bash
# CT 100 — diário 03:00 BRT (já existe cron geral)
docker run --rm \
  -v growthbook-mongo-data:/data/db:ro \
  -v ~/backups/growthbook:/backup \
  mongo:7 \
  mongodump --uri="mongodb://growthbook-mongo:27017/growthbook" --out=/backup/$(date +%Y-%m-%d)
```

Restore: `mongorestore --uri=... --drop /backup/<data>/`

## 6. Pegadinhas

> **Aprendizados deploy real 2026-05-08** — todos descobertos na 1ª execução do RUNBOOK em prod CT 100. Quem fizer próxima migração já pega de bandeja.

- ⚠ **UI hPanel Hostinger MENTE sobre DNS.** A tela `https://hpanel.hostinger.com/domain/oimpresso.com/dns` mostra mensagem *"Os registros DNS do seu domínio estão sendo gerenciados em outro lugar"* mesmo quando a Hostinger DNS API funciona normalmente. Causa: nameservers do `oimpresso.com` são `app.oimpresso.com` + `ns1.dns-parking.com` + `ns2.dns-parking.com`, mas a zona Hostinger continua autoritativa via API. **Sempre usar a API direta** (`PUT /api/dns/v1/zones/{domain}` com `overwrite:false`) em vez de tentar pelo painel UI.
- ⚠ **Token Hostinger no `.env` é `HOSTINGER_API`** (não `HOSTINGER_API_TOKEN` como muitos guides sugerem). Usar `Bearer $token` no header Authorization.
- ⚠ **Faltou `entrypoints=websecure` = Let's Encrypt não dispara.** Sem `traefik.http.routers.X.entrypoints: "websecure"` nos labels, Traefik não sabe que router responde em HTTPS, daí ACME nunca é chamado e cert fica "TRAEFIK DEFAULT CERT" auto-signed. **Cada router HTTPS precisa explicitamente declarar `entrypoints=websecure`** + router HTTP separado pra redirect (vide modelo `oimpresso-mcp`).
- ⚠ **GrowthBook recusa `ENCRYPTION_KEY=dev` em prod.** Sem env-secrets populado, GrowthBook usa default `dev` e crasha com `Error: Cannot use ENCRYPTION_KEY=dev in production`. Sempre criar `.env-secrets` com `JWT_SECRET=` e `ENCRYPTION_KEY=` populados ANTES do `docker compose up`. `chmod 600`.
- ⚠ **Heredoc shell escape em PowerShell vs bash difere.** Tentei usar `python3 -c` com regex pra editar compose — quebra fácil em string escape. Mais simples: reescrever `docker-compose.yml` inteiro via `cat > file <<EOF` e cuidar do escape de backticks Traefik manual.
- ❌ NÃO usar SDK key no JS browser direto — chave fica exposta. Pra flags client-side, criar SDK Connection separada com client-side key (criptografada).
- ❌ NÃO confiar no cache 60s pra rollback de emergência — em emergência, restart do PHP-FPM derruba cache; flag toggle é instantâneo na UI mas precisa esperar TTL ou clear cache manual.
- ❌ NÃO esquecer de fechar registrations após primeiro signup. Senão qualquer um cria conta.
- ❌ NÃO commitar JWT_SECRET ou ENCRYPTION_KEY no git — gitignored em `.env`. Backup no Vaultwarden.
- ❌ NÃO subir GrowthBook no Hostinger — viola ADR 0062 (separação runtime). Daemon = CT 100.
- ❌ NÃO esquecer de `docker compose down --volumes` ao desativar — volumes ficam órfãos no `/var/lib/docker/volumes/` consumindo disco.

## 7. Refs

- [US-INFRA-001](SPEC.md) — user story que motiva
- [ADR 0058 Centrifugo+FrankenPHP](../../decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md) — vizinho de runtime
- [ADR 0062 Hostinger ≠ CT 100](../../decisions/0062-separacao-runtime-hostinger-ct100.md) — princípio de onde vive o quê
- [ADR 0094 §observabilidade + flag system](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)
- [skill proxmox-docker-host](../../../.claude/skills/proxmox-docker-host/SKILL.md) — receita Traefik labels
- [GrowthBook self-host docs](https://docs.growthbook.io/self-host)

---

**Status:** ✅ deployado em prod 2026-05-08
- `https://growthbook.oimpresso.com` — UI HTTPS 200 com cert Let's Encrypt R12 (válido até 2026-08-06)
- `https://growthbook-api.oimpresso.com/healthz` — HTTP 401 (esperado, requer auth)
- Wagner cria org admin manual no UI (próximo passo) + SDK Connection PHP

**Última atualização:** 2026-05-08 (após deploy real — pegadinhas atualizadas com aprendizados de prod)

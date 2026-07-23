---
title: "RUNBOOK — Registrar tool MCP `brief-fetch` no servidor CT 100"
owner: W
status: ativo
last_validated: "2026-06-08"
---

# RUNBOOK — Registrar tool MCP `brief-fetch` no servidor CT 100

> **Quando rodar:** depois do PR Sprint 1 implementação ser mergeado.
> **Onde rodar:** SSH no CT 100 Proxmox (LAN `192.168.0.50` ou Tailscale `100.99.207.66`).
> **Quem:** Wagner (acesso `dev` no CT 100).
> **Tempo:** ~10 min.
> **Ref:** ADR 0091 (Daily Brief), ADR 0053 (MCP server CT 100).

---

## Contexto

A tool MCP `brief-fetch` foi implementada no app Laravel principal
(Hostinger) — `Modules/Brief/Http/Controllers/BriefFetchController.php`.
A rota está em `Modules/Brief/Routes/api.php`:

```
POST /api/mcp/tools/brief-fetch
```

Pra Claude Code dos devs (Wagner/Felipe/Maiara/Luiz/Eliana) chamarem essa
tool via `mcp__oimpresso__brief-fetch`, ela precisa estar **registrada no
servidor MCP** que vive no CT 100 (`mcp.oimpresso.com`, FrankenPHP, ADR 0053).

O servidor MCP é um Laravel separado que faz proxy autenticado pro app
Hostinger (via SSH tunnel pro MySQL Hostinger e HTTP pra rotas API).

---

## Pré-requisitos

- [ ] PR Sprint 1 implementação mergeado em `wagnerra23/oimpresso.com`
- [ ] Migration `2026_05_06_170045_create_daily_brief_schema.php` aplicada
      no MySQL Hostinger (`php artisan migrate` em produção)
- [ ] `ANTHROPIC_API_KEY` configurada em `.env` do Hostinger
- [ ] Cron `brief:generate` rodando — confirmar com `php artisan schedule:list`
- [ ] Pelo menos 1 brief válido em `mcp_briefs` (`SELECT COUNT(*) WHERE valid=1`)

---

## Passos

### 1. Conectar no CT 100

```bash
# Via Tailscale (preferido):
ssh dev@100.99.207.66

# OU via LAN:
ssh dev@192.168.0.50
```

### 2. Entrar no container `oimpresso-mcp`

```bash
cd /opt/docker/oimpresso-mcp
docker compose exec app bash
```

### 3. Adicionar tool ao registro do MCP server

O servidor MCP usa `laravel/mcp` ^0.7. Tools são registradas em
`config/mcp.php` ou via `App\Providers\McpServerProvider` (ver código real
do CT 100 — pode variar).

Adicionar entrada (formato genérico, ajuste à estrutura local):

```php
// config/mcp.php — array 'tools'
'brief-fetch' => [
    'name'        => 'brief-fetch',
    'description' => 'Devolve o Daily Brief mais recente — markdown ≤3.5k tokens com estado consolidado do projeto. CHAME ANTES DE QUALQUER OUTRA TOOL no início de toda sessão. Cache de 5min.',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'force_refresh' => [
                'type'        => 'boolean',
                'default'     => false,
                'description' => 'Regenera antes de retornar. Restrito ao Wagner; respeita cap diário.',
            ],
        ],
        'required' => [],
    ],
    'handler' => [
        'kind'    => 'http_proxy',
        'method'  => 'POST',
        'url'     => 'https://oimpresso.com/api/mcp/tools/brief-fetch',
        'forward_headers' => ['X-MCP-Agent-Id', 'Authorization'],
        'scope'   => 'mcp.brief.read',  // permission Spatie
    ],
],
```

### 4. Adicionar permission Spatie

No app Hostinger (PR de seguimento), criar permission:

```php
// Modules/TeamMcp/Database/Seeders/McpScopesSeeder.php
Permission::firstOrCreate(['name' => 'mcp.brief.read', 'guard_name' => 'web']);
```

E mapear pros usuários do time (todos podem ler brief — não tem PII de
cliente final).

### 5. Reiniciar container MCP

```bash
exit  # sair do docker exec
docker compose restart app
```

### 6. Validar

```bash
# Listar tools (do CT 100, dentro do container ou via curl externo)
curl -X POST https://mcp.oimpresso.com/api/mcp/tools/list \
  -H "Authorization: Bearer $WAGNER_MCP_TOKEN"

# Deve retornar 'brief-fetch' na lista.
```

```bash
# Chamar a tool de fato
curl -X POST https://mcp.oimpresso.com/api/mcp/tools/brief-fetch \
  -H "Authorization: Bearer $WAGNER_MCP_TOKEN" \
  -H "X-MCP-Agent-Id: wagner-claude-desktop" \
  -H "Content-Type: application/json" \
  -d '{}'

# Deve retornar 200 com {content: '...', meta: {...}}.
```

### 7. Validar do lado do dev

Em qualquer máquina com Claude Code conectado ao MCP:

```
> liste tools disponíveis
```

Resposta deve incluir `mcp__oimpresso__brief-fetch`.

```
> mcp__oimpresso__brief-fetch
```

Deve retornar markdown com 7 seções fixas.

---

## Critério de pronto

- [ ] `mcp__oimpresso__brief-fetch` aparece em todas as sessões Claude Code
- [ ] Cache 5min funciona (10 chamadas seguidas → 1 query SQL no Hostinger)
- [ ] `force_refresh=true` funciona pra Wagner, 403 pra outros agents
- [ ] Audit log em `mcp_audit_log` registra cada call
- [ ] Skill telemetry em `mcp_skill_telemetry` (skill_name='brief-first')
      registra cada call

---

## Rollback

Se algo der errado:

1. Remover entrada `brief-fetch` de `config/mcp.php`
2. `docker compose restart app`
3. Skill `brief-first` cai automaticamente (se tool não existe, agents
   chamam fallback via `cycles-active` etc — caso de exceção 1 da skill)
4. Cron `brief:generate` continua rodando — não precisa parar; briefs
   gerados ficam guardados em `mcp_briefs` pra quando reabrir a tool

---

## Referências

- ADR 0091 — Daily Brief (contrato canônico)
- ADR 0053 — MCP server CT 100 (arquitetura)
- ADR 0070 — Nomenclatura mcp_*
- `memory/sprints/s1-daily-brief/04-tool-brief-fetch.md` (spec completa)
- `Modules/Brief/Http/Controllers/BriefFetchController.php` (handler)

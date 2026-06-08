---
name: laravel/octane + laravel/mcp em prod-deps — ADR estrutural pendente
description: composer.json tem laravel/octane e laravel/mcp em require (não require-dev); são prod-core hoje, mover quebra config:cache no Hostinger; exige ADR estrutural Wagner
type: project
---
`composer.json` na raiz do oimpresso tem `laravel/mcp ^0.7.0` e `laravel/octane ^2.15` no bloco `require` (production deps). Hostinger faz `composer install` no deploy → baixa esses pacotes em shared hosting.

**Por que é problema**: ADR 0062 separa runtime — Hostinger é shared hosting; daemons (Reverb, Centrifugo, Octane, MCP server) só rodam em CT 100 Proxmox. Octane/Mcp em prod-deps contamina vendor/ do Hostinger mesmo quando não usados.

**Por que NÃO foi simples mover pra `require-dev`** (auditoria 2026-05-10, agente compose-fix):

- `config/octane.php` (publicado pelo pacote) faz `use Laravel\Octane\Octane;` + 22 outros `use Laravel\Octane\…`. Esse arquivo é parsed por `php artisan config:cache` no deploy Hostinger. Mover quebra cache de config (autoloader não acha namespace).
- `Modules/Jana/Mcp/*` (15+ arquivos) e `Modules/Brief/Mcp/Tools/*` extend `Laravel\Mcp\Server` direto (top-level `use` statements). Mesmo com `if (config('mcp.tools_exposed'))` guardando rotas (`Modules/Jana/Http/routes.php:218`), o autoload do `use` no topo é parsed.

**3 opções pra Wagner triar via ADR**:

1. **Deletar `config/octane.php`** (config órfã, não usada em Hostinger). Manter Octane só em CT 100 onde precisa.
2. **Encapsular `Modules/Jana/Mcp/*` atrás de `class_exists(Laravel\Mcp\Server::class)` guards** + remover `use` statements top-level (substituir por FQN inline). Mais invasivo mas isola corretamente.
3. **Aceitar contaminação `vendor/` Hostinger como custo** (status quo) e melhorar guard de runtime via env var explícita.

**Status atual** (2026-05-10 pós-auditoria): nenhuma das 3 aplicada. PR de movimentação foi BLOQUEADO durante auditoria, reportado como pendente. Hostinger continua baixando ambos no `composer install`.

**Disparador pra retomar**: Wagner abrir ADR (proposta `0xxx-octane-mcp-runtime-isolation.md` ou similar). Sem ADR, deixar como está — não mover sem decisão estrutural.

**Não confundir com**: `Mcp::web()` exposed condicional em `routes.php` (Tier 0 já correto). O problema é só vendor/ contamination, não rota.

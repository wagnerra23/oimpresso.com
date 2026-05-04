---
name: NUNCA instalar laravel/mcp ou laravel/octane no Hostinger
description: laravel/mcp + laravel/octane são EXCLUSIVOS do CT 100 Proxmox; rodar composer install com eles no Hostinger é proibido
type: feedback
originSessionId: e1324d13-7148-4faa-9bee-1d5fbcc6286e
---
⛔ **NUNCA rodar `composer install` ou `composer update` que envolva `laravel/mcp` ou `laravel/octane` no servidor Hostinger** — nem em worktree separado, nem em /tmp, nem em qualquer pasta do `u906587222`.

**Why:** Wagner em 2026-04-30 reagiu duro: *"na hotiger? mcp ? tais maluco? não é permitido"*. Eu tinha criado worktree em `/home/u906587222/wt-mem-kb-3` e rodado `composer update laravel/mcp laravel/octane` lá pra testar o linter Pest do MEM-KB-3. Hostinger é shared hosting do app web (Inertia/React/L13.6); MCP server e Octane vivem em CT 100 Proxmox isolado. Mesmo isolando em worktree, o ato de instalar esses pacotes no servidor Hostinger é considerado contaminação/violação de contrato — proibido.

**How to apply:**
- Pra testar PHP/Pest/composer envolvendo `laravel/mcp` ou `laravel/octane`: usar **CT 100 Proxmox** (Tailscale ou docker exec) ou ambiente local Wagner. NÃO Hostinger.
- Pra testar features do app web (Inertia, Form shim, Financeiro) sem mexer com mcp/octane: SSH worktree no Hostinger é OK, mas confirma com Wagner antes em zona cinzenta.
- Se for só validar sintaxe/lint sem rodar Pest: GH Actions é o caminho (CI já tem PHP 8.4 setup).
- O remédio quando errar: `git worktree remove --force` + `rm -rf` da pasta + reportar no chat.

**Arquitetura formal:** ADR 0053 + INFRA.md §6.1. App Hostinger NÃO toca MCP/Octane; vendor/ desses pacotes só existe no clone do repo dentro do CT 100 (/opt/oimpresso-mcp/code) ou local Wagner.

**Pegadinha do composer.json:** os 2 pacotes ESTÃO em composer.json do monorepo, mas isso é estado atual problemático — Hostinger nunca pode rodar `composer install` "puro" sem batter neles. Provável fix futuro: separar em composer.json secundário pro CT 100 ou usar `composer install --no-dev` + filtros. NÃO assumir resolvido enquanto Wagner não confirmar.

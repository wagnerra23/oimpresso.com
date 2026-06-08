<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | MCP server tools exposed
    |--------------------------------------------------------------------------
    |
    | Controla se a rota POST /api/mcp (laravel/mcp Mcp::web()) é registrada.
    |
    | Default: false. MCP server expõe tools APENAS no CT 100 Proxmox via
    | FrankenPHP daemon — Hostinger é shared hosting; daemons MCP ficam lentos
    | e instáveis (Wagner regra canônica 2026-05-07 + ADR 0062).
    |
    | Para ativar:
    |   - CT 100 .env:    MCP_TOOLS_EXPOSED=true
    |   - Hostinger .env: omitir (default false) — schema + service backend ficam
    |                     em prod (cron brief:generate continua rodando), mas a
    |                     rota MCP exposed fica 404.
    |
    | Refs: ADR 0053 (MCP server canônico), ADR 0062 (Hostinger ≠ CT 100),
    |       US-COPI-094 sessão 2026-05-07.
    */
    'tools_exposed' => env('MCP_TOOLS_EXPOSED', false),
];

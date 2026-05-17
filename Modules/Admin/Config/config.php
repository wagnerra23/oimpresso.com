<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Admin Center — config (ADR 0122)
    |--------------------------------------------------------------------------
    */

    'name' => 'Admin',

    /**
     * User_id e business_id hardcoded do Wagner. Defense in depth contra
     * DB corruption. Override via env em Sprint 2 quando rotation for tema.
     */
    'wagner_user_id'     => env('ADMIN_WAGNER_USER_ID', 1),
    'wagner_business_id' => env('ADMIN_WAGNER_BUSINESS_ID', 1),

    /**
     * Fallback emergencial — se DB perder user_id=1 (restore, etc), permite
     * login via username + role superadmin. Default null = desabilitado.
     */
    'fallback_username' => env('ADMIN_FALLBACK_USERNAME'),

    /**
     * CIDR Tailscale permitidas. Comma-separated em string OU array.
     * Default 100.99.0.0/16 (range Tailscale do oimpresso, ver auto-mem
     * reference_proxmox_acesso_2026_04_29).
     */
    'tailscale_cidrs' => env('ADMIN_TAILSCALE_CIDR', '100.99.0.0/16'),

    /**
     * Subdomínio canônico do Admin Center. Usado em links de email/notif
     * pro Wagner (Sprint 2+).
     */
    'subdomain' => env('ADMIN_SUBDOMAIN', 'admin.oimpresso.com'),

    /**
     * Bypass DEV — desabilita TailscaleOnly + IsWagner middlewares quando
     * APP_ENV=local. SOMENTE pra dev local (Herd/php artisan serve).
     * Default false. Em prod NUNCA setar true (.env.example tem todos
     * desabilitados).
     */
    'bypass_local' => env('ADMIN_BYPASS_LOCAL', false),

    /**
     * Bypass Tailscale-only restrito (válido em qualquer env, inclusive prod).
     * Defense-in-depth preservado: middleware `is-wagner` (user_id=1) continua
     * validando — apenas Wagner autenticado entra, mesmo com flag ON. Cada
     * request bypass loga WARN em admin.tailscale_bypass pra auditoria.
     *
     * Caso de uso: Wagner acessando admin via Hostinger público (sem VPN
     * Tailscale ativa no laptop). Ativado em prod 2026-05-17 quando setup
     * Tailscale-only do Admin foi diferido pra Sprint posterior.
     *
     * Default false (CIDR Tailscale obrigatório).
     */
    'bypass_tailscale' => env('ADMIN_BYPASS_TAILSCALE', false),
];

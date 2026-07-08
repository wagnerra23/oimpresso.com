<?php

declare(strict_types=1);

// Tests\TestCase já é aplicado globalmente em tests/Pest.php. NÃO redeclarar (Pest 4 lança TestCaseAlreadyInUse).

/**
 * Modo Suporte (ADR 0305/0309) — item de sidebar no grupo SISTEMA.
 *
 * A tela React `/suporte/empresas` era acessível só por URL (sem nav). Este
 * item single-link no core AdminSidebarMenu publica o destino no sidebar,
 * gated por SupportAccessService::isSupportAgent — cliente NUNCA vê.
 *
 * Suporte NÃO é módulo nWidart → sem DataController → o item nasce no core
 * (recomendação do handoff 2026-06-24). Contrato source-level (mesmo estilo
 * dos demais testes de Sidebar, sem boot de DB — render real roda no CT100).
 *
 * Refs:
 *   - memory/decisions/0305-modo-suporte-cross-tenant-exceto-operador.md
 *   - memory/decisions/0309-* (operadora biz=1 = time de suporte)
 *   - app/Http/Middleware/AdminSidebarMenu.php · app/Services/LegacyMenuAdapter.php
 */

const ROOT_MS = __DIR__ . '/../../..';

describe('Modo Suporte — item de sidebar gated (grupo SISTEMA)', function () {
    it('AdminSidebarMenu publica item Suporte gated por isSupportAgent no grupo sistema', function () {
        $src = file_get_contents(ROOT_MS . '/app/Http/Middleware/AdminSidebarMenu.php');

        // Gate service-direct (NÃO Gate::before, que daria true a qualquer Admin#<biz> — ADR 0309)
        expect($src)->toContain('SupportAccessService::class)->isSupportAgent(auth()->user())');
        // Destino canônico via rota nomeada (não path chumbado)
        expect($src)->toContain("route('suporte.empresas')");
        // Grupo SISTEMA declarado via attribute (findGroupKey dá precedência)
        expect($src)->toContain("'group'  => 'sistema'");
    });

    it('item Suporte NÃO usa hardcode de business_id (isolamento via service — Tier 0)', function () {
        $src = file_get_contents(ROOT_MS . '/app/Http/Middleware/AdminSidebarMenu.php');
        // O gate é o service; nunca `=== 1` chumbado pra operadora
        expect($src)->not->toMatch('/business_id\s*[!=]==\s*1\s*\).*suporte/is');
    });

    it('LegacyMenuAdapter marca /suporte como rota Inertia (navegação SPA, sem full reload)', function () {
        $src = file_get_contents(ROOT_MS . '/app/Services/LegacyMenuAdapter.php');
        expect($src)->toContain("'/suporte',");
    });

    it('rota nomeada suporte.empresas existe em routes/web.php', function () {
        $src = file_get_contents(ROOT_MS . '/routes/web.php');
        expect($src)->toContain("->name('suporte.empresas')");
    });
});

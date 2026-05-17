<?php

declare(strict_types=1);

use Modules\ProductCatalogue\Services\CatalogueQrService;
use Modules\ProductCatalogue\Services\CatalogueService;

uses(Tests\TestCase::class);

/**
 * Wave 26 ProductCatalogue POLISH 75→85 — saturação D1/D5/D7 sem boot DB.
 *
 * Estratégia: reflection + source-grep + Container resolve. Sem hit DB pra
 * paralelização worktree (ADR 0093 multi-tenant). Wave 23 saturou Architecture +
 * Service contract; Wave 26 expande D5 README (cliente-facing) + D7 retention
 * confirmar canon + D1 documentação ausência intencional Entities.
 *
 * @see Modules/ProductCatalogue/README.md
 * @see Modules/ProductCatalogue/Config/retention.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
describe('Wave 26 ProductCatalogue POLISH 75→85', function () {

    beforeEach(function () {
        config()->set('otel.enabled', false);
    });

    it('D1: Entities/ vazia por design (catálogo lê core App\\Product, sem Entity própria)', function () {
        // Documentar a ausência intencional vs falha — README explica.
        $entitiesDir = base_path('Modules/ProductCatalogue/Entities');
        expect(is_dir($entitiesDir))->toBeTrue();

        // README deve documentar a razão da Entities/ vazia
        $readme = file_get_contents(base_path('Modules/ProductCatalogue/README.md'));
        expect($readme)->toContain('Entities/');
        expect($readme)->toContain('read-only');
    });

    it('D5: README.md existe + cita Wagner/Larissa + 7+ casos de uso cliente', function () {
        $readmePath = base_path('Modules/ProductCatalogue/README.md');
        expect(file_exists($readmePath))->toBeTrue('README.md cliente-facing obrigatório Wave 26 D5');

        $src = file_get_contents($readmePath);
        expect($src)->toContain('Wagner');
        expect($src)->toContain('Larissa');

        // Tabela "Como cliente usa" >= 7 linhas
        $matches = preg_match_all("/^\\| (Quero\\.\\.\\.|Gerar QR|Imprimir QR|Cliente escaneia|Cliente vê|Cliente tap)/m", $src);
        expect($matches)->toBeGreaterThanOrEqual(7, "Esperava 7+ linhas tabela 'Como cliente usa'; achou {$matches}");
    });

    it('D5: README documenta journey real Larissa biz=4 ROTA LIVRE (caso piloto)', function () {
        $src = file_get_contents(base_path('Modules/ProductCatalogue/README.md'));

        expect($src)->toContain('Larissa');
        expect($src)->toContain('ROTA LIVRE');
        expect($src)->toContain('Termas do Gravatal');

        // 8 passos journey
        $passos = preg_match_all("/^\\| \\d\\. /m", $src);
        expect($passos)->toBeGreaterThanOrEqual(7, "Esperava 7+ passos journey; achou {$passos}");
    });

    it('D5: README documenta multi-tenant Tier 0 defesa em profundidade (rota pública)', function () {
        $src = file_get_contents(base_path('Modules/ProductCatalogue/README.md'));

        expect($src)->toContain('Multi-tenant Tier 0');
        expect($src)->toContain('defesa em profundidade');
        expect($src)->toContain('atacante');
    });

    it('D7: retention.php existe + declara product_catalogue_version=1095d', function () {
        $retentionPath = base_path('Modules/ProductCatalogue/Config/retention.php');
        expect(file_exists($retentionPath))->toBeTrue();

        $cfg = require $retentionPath;
        expect($cfg)->toBeArray();
        expect($cfg)->toHaveKey('entities');
        expect($cfg['entities'])->toHaveKey('product_catalogue_version');
        expect($cfg['entities']['product_catalogue_version'])->toBe(1095);
    });

    it('D7: retention.php documenta razão de ter mesmo sem PII direta', function () {
        $src = file_get_contents(base_path('Modules/ProductCatalogue/Config/retention.php'));

        expect($src)->toContain('LGPD Art. 16');
        expect($src)->toContain('Compliance Audit');
        expect($src)->toContain('governance v3');
    });

    it('D7: retention strategy é hard_delete (recomendado pra versões antigas)', function () {
        $cfg = require base_path('Modules/ProductCatalogue/Config/retention.php');
        expect($cfg['strategy'])->toBe('hard_delete');
        expect($cfg['notice_period_days'])->toBe(30);
    });

    it('D5: README cita ADRs canônicas (0093 multi-tenant, 0155 observability, 0011 padrão)', function () {
        $src = file_get_contents(base_path('Modules/ProductCatalogue/README.md'));

        expect($src)->toContain('0093');
        expect($src)->toContain('0155');
        expect($src)->toContain('0011');
    });

    it('D5: README cita garantias canônicas (Tier 0 + sem PII + read-only + telemetria)', function () {
        $src = file_get_contents(base_path('Modules/ProductCatalogue/README.md'));

        expect($src)->toContain('Multi-tenant Tier 0');
        expect($src)->toContain('Sem PII');
        expect($src)->toContain('Read-only');
        expect($src)->toContain('Schema-aware fail-soft');
        expect($src)->toContain('Telemetria observável');
    });

    it('D5: README documenta spans canon D9 (build_index_payload + build_show_payload)', function () {
        $src = file_get_contents(base_path('Modules/ProductCatalogue/README.md'));

        expect($src)->toContain('product_catalogue.build_index_payload');
        expect($src)->toContain('product_catalogue.build_show_payload');
        expect($src)->toContain('zero-cost');
    });

    it('D2: Container resolve Services canon (Wave 23 mantido)', function () {
        expect(app(CatalogueService::class))->toBeInstanceOf(CatalogueService::class)
            ->and(app(CatalogueQrService::class))->toBeInstanceOf(CatalogueQrService::class);
    });
});

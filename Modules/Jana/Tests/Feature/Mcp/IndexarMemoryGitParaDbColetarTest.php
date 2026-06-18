<?php

declare(strict_types=1);

use Modules\Jana\Services\Mcp\IndexarMemoryGitParaDb;

uses(Tests\TestCase::class);

/**
 * GAP #1 ingest-coverage (2026-05-29) — cobertura da ingestão git→MCP das
 * pastas cegas de ALTO VALOR e SEM PII de cliente.
 *
 * Antes desta correção a whitelist de globs em coletarArquivos() ignorava
 * pastas inteiras de memory/ (handoffs/, reference/, sprints/, governance/,
 * audits/, _DesignSystem/), então handoffs ("onde paramos" entre sessões) e
 * os 51 docs canônicos ex-auto-mem NUNCA chegavam ao MCP.
 *
 * Cobertura:
 *   (a) memory/handoffs/*.md são coletados (type=handoff)
 *   (b) memory/reference/** recursivo coletado (type=reference)
 *   (c) memory/clientes/** NÃO coletado (PII — LGPD)
 *   (d) memory/feedback/** NÃO coletado (PII — LGPD)
 *   (e) não duplica memory/08-handoff.md (slug 'handoff' único)
 *   (f) pula arquivos _* (templates/índices) e README
 *   (g) sprints/, governance/, _DesignSystem/ recursivos + audits/ raiz
 *   (h) slugs únicos (sem colisão entre subpastas homônimas)
 *   (i) caminhos relativos POSIX no campo 'path'
 *
 * Testa coletarArquivos() via reflection num repo-fixture temporário — sem DB,
 * sem Scout, sem rede. Molde: TimeDecayTest.php (teste-por-reflection).
 */

// ── helpers ──────────────────────────────────────────────────────────────

/**
 * Cria árvore de fixtures num diretório temporário e devolve o base path.
 */
function coletarFixtureRepo(): string
{
    $base = sys_get_temp_dir() . '/jana-ingest-' . uniqid('', true);

    $files = [
        // handoffs/ — datados + _TEMPLATE + README (estes dois devem ser pulados)
        'memory/handoffs/2026-05-18-1115-sessao.md'   => "# Sessão handoff\nonde paramos",
        'memory/handoffs/2026-05-20-0900-outra.md'    => "# Outra sessão\ntexto",
        'memory/handoffs/_TEMPLATE.md'                => "# Template\nignore",
        'memory/handoffs/README.md'                   => "# Readme\nignore",

        // 08-handoff raiz — já coletado pela branch legada (slug 'handoff')
        'memory/08-handoff.md'                        => "# Handoff canônico\nlegado",

        // reference/ recursivo — inclui subpasta + _INDEX (pulado)
        'memory/reference/cliente-rotalivre.md'       => "# Rota Livre\nperfil",
        'memory/reference/_INDEX.md'                  => "# Index\nignore",
        'memory/reference/adr/ui/foo-pattern.md'      => "# Foo Pattern\nui doc",

        // sprints/ recursivo
        'memory/sprints/s3-constituicao/03-skills-audit.md' => "# Skills audit\ndoc",

        // governance/ recursivo
        'memory/governance/CONSTITUTION.md'           => "# Constitution\ndoc",
        'memory/governance/_README.md'                => "# Readme gov\nignore",

        // governance/design-requests/ — Design Request Ledger (vereditos · Onda 3)
        'memory/governance/design-requests/LEDGER.md'        => "# Ledger\nREQ-001 | tela | done",
        'memory/governance/design-requests/REQ-001.md'       => "# REQ-001\nvocabulário de estado",
        'memory/governance/design-requests/_TEMPLATE-REQ.md' => "# Template\nignore",

        // audits/ raiz (subpasta NÃO recursada)
        'memory/audits/AUDITORIA-MEMORIA-2026-05-15.md' => "# Auditoria\ndoc",
        'memory/audits/2026-05-pre-sales/sensivel.md'   => "# Pre-sales\nsensivel",

        // _DesignSystem recursivo
        'memory/requisitos/_DesignSystem/padroes-tela/PT-01-Lista.md' => "# PT-01\ndoc",

        // PII — devem ser IGNORADOS (não há branch que os colete)
        'memory/clientes/martinho-cacambas.md'        => "# Martinho\nemail joao@x.com tel (11) 99999-9999",
        'memory/feedback/algum-feedback.md'           => "# Feedback\nemail maria@y.com",
    ];

    foreach ($files as $rel => $conteudo) {
        $full = "$base/$rel";
        @mkdir(dirname($full), 0777, true);
        file_put_contents($full, $conteudo);
    }

    return $base;
}

/**
 * Invoca coletarArquivos() via reflection. Retorna list<array{slug,type,module,path,full}>.
 */
function coletarInvoke(string $base): array
{
    $svc    = new IndexarMemoryGitParaDb($base, 'test', null, 1);
    $ref    = new ReflectionClass($svc);
    $method = $ref->getMethod('coletarArquivos');
    $method->setAccessible(true);

    return $method->invoke($svc);
}

/** Extrai mapa slug => info pra asserts. */
function coletarSlugMap(array $arquivos): array
{
    $map = [];
    foreach ($arquivos as $a) {
        $map[$a['slug']] = $a;
    }
    return $map;
}

function coletarLimpar(string $base): void
{
    if (! is_dir($base)) {
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($it as $f) {
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    @rmdir($base);
}

// ── (a) handoffs/*.md coletados ────────────────────────────────────────────

it('coleta handoffs/*.md com type=handoff', function () {
    $base = coletarFixtureRepo();
    try {
        $map = coletarSlugMap(coletarInvoke($base));

        expect($map)->toHaveKey('handoff-2026-05-18-1115-sessao');
        expect($map['handoff-2026-05-18-1115-sessao']['type'])->toBe('handoff');
        expect($map['handoff-2026-05-18-1115-sessao']['path'])
            ->toBe('memory/handoffs/2026-05-18-1115-sessao.md');

        expect($map)->toHaveKey('handoff-2026-05-20-0900-outra');
    } finally {
        coletarLimpar($base);
    }
});

// ── (b) reference/ recursivo coletado ───────────────────────────────────────

it('coleta reference/ recursivamente (inclui subpasta adr/ui)', function () {
    $base = coletarFixtureRepo();
    try {
        $map = coletarSlugMap(coletarInvoke($base));

        // arquivo na raiz da subárvore
        expect($map)->toHaveKey('reference-cliente-rotalivre');
        expect($map['reference-cliente-rotalivre']['type'])->toBe('reference');

        // arquivo aninhado (glob não pegaria — prova da recursão)
        expect($map)->toHaveKey('reference-adr-ui-foo-pattern');
        expect($map['reference-adr-ui-foo-pattern']['type'])->toBe('reference');
        expect($map['reference-adr-ui-foo-pattern']['path'])
            ->toBe('memory/reference/adr/ui/foo-pattern.md');
    } finally {
        coletarLimpar($base);
    }
});

it('coleta sprints/, governance/ e _DesignSystem/ recursivos + audits/ raiz', function () {
    $base = coletarFixtureRepo();
    try {
        $map = coletarSlugMap(coletarInvoke($base));

        expect($map)->toHaveKey('sprint-s3-constituicao-03-skills-audit');
        expect($map)->toHaveKey('governance-constitution');
        expect($map)->toHaveKey('designsystem-padroes-tela-pt-01-lista');

        // audits/ raiz coletado…
        expect($map)->toHaveKey('audit-root-auditoria-memoria-2026-05-15');
        expect($map['audit-root-auditoria-memoria-2026-05-15']['type'])->toBe('audit');
        // …mas subpasta audits/ NÃO recursada (segurança)
        $temPreSales = collect($map)->contains(
            fn ($a) => str_contains($a['path'], 'audits/2026-05-pre-sales')
        );
        expect($temPreSales)->toBeFalse();
    } finally {
        coletarLimpar($base);
    }
});

// ── (g2) design-requests/ (Design Request Ledger · vereditos · Onda 3) ──────

it('coleta governance/design-requests/ (ledger de vereditos · Onda 3) e pula o _TEMPLATE', function () {
    $base = coletarFixtureRepo();
    try {
        $map = coletarSlugMap(coletarInvoke($base));

        // o ledger e o REQ chegam ao MCP (consultáveis via memoria-search · read-only ADR 0061)
        expect($map)->toHaveKey('governance-design-requests-ledger');
        expect($map)->toHaveKey('governance-design-requests-req-001');
        expect($map['governance-design-requests-req-001']['type'])->toBe('reference');
        expect($map['governance-design-requests-req-001']['path'])
            ->toBe('memory/governance/design-requests/REQ-001.md');

        // o _TEMPLATE-REQ NÃO vaza pro índice
        $temTemplate = collect($map)->contains(
            fn ($a) => str_contains($a['path'], 'design-requests/_TEMPLATE')
        );
        expect($temTemplate)->toBeFalse();
    } finally {
        coletarLimpar($base);
    }
});

// ── (c)+(d) clientes/ e feedback/ NÃO coletados (PII — LGPD) ────────────────

it('NÃO coleta memory/clientes/** nem memory/feedback/** (PII LGPD)', function () {
    $base = coletarFixtureRepo();
    try {
        $arquivos = coletarInvoke($base);

        $temCliente = collect($arquivos)->contains(
            fn ($a) => str_contains($a['path'], 'memory/clientes/')
        );
        $temFeedback = collect($arquivos)->contains(
            fn ($a) => str_contains($a['path'], 'memory/feedback/')
        );

        expect($temCliente)->toBeFalse();
        expect($temFeedback)->toBeFalse();
    } finally {
        coletarLimpar($base);
    }
});

// ── (e) não duplica 08-handoff ──────────────────────────────────────────────

it('não duplica memory/08-handoff.md — slug handoff aparece exatamente 1×', function () {
    $base = coletarFixtureRepo();
    try {
        $arquivos = coletarInvoke($base);

        $slugs = array_column($arquivos, 'slug');

        // slug legado 'handoff' (08-handoff.md) coletado 1× só
        expect(array_count_values($slugs)['handoff'] ?? 0)->toBe(1);

        // o 'handoff' legado aponta pro 08-handoff.md (não pra pasta handoffs/)
        $map = coletarSlugMap($arquivos);
        expect($map['handoff']['path'])->toBe('memory/08-handoff.md');

        // nenhum slug duplicado em todo o coletor
        expect(count($slugs))->toBe(count(array_unique($slugs)));
    } finally {
        coletarLimpar($base);
    }
});

// ── (f) pula _* e README ────────────────────────────────────────────────────

it('pula arquivos _* (templates/índices) e README', function () {
    $base = coletarFixtureRepo();
    try {
        $arquivos = coletarInvoke($base);

        $temTemplate = collect($arquivos)->contains(
            fn ($a) => str_contains($a['path'], '_TEMPLATE')
                || str_contains($a['path'], '_INDEX')
                || str_contains($a['path'], '_README')
        );
        $temReadme = collect($arquivos)->contains(
            fn ($a) => str_contains($a['path'], 'handoffs/README.md')
        );

        expect($temTemplate)->toBeFalse();
        expect($temReadme)->toBeFalse();
    } finally {
        coletarLimpar($base);
    }
});

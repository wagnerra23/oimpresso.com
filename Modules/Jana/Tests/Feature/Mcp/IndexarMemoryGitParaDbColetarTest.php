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

        // SUPERFICIE.md por módulo — "quais arquivos são deste contexto" (gerado)
        'memory/requisitos/Financeiro/SUPERFICIE.md'  => "# Superfície de código — Financeiro\n## Controllers\n- app/Http/Controllers/X.php",

        // PII — devem ser IGNORADOS (não há branch que os colete)
        'memory/clientes/martinho-cacambas.md'        => "# Martinho\nemail joao@x.com tel (11) 99999-9999",
        'memory/feedback/algum-feedback.md'           => "# Feedback\nemail maria@y.com",

        // TRIO DE FEATURE — memory/requisitos/<Mod>/features/<slug>/{requirements,plan,tasks}.md.
        // Profundidade 2 DENTRO da pasta do módulo: é o que prova o glob novo, já que
        // `memory/requisitos/*/*.md` (o glob anterior mais fundo) para um nível antes.
        'memory/requisitos/Connector/features/openapi-connector/requirements.md' => "# Requirements — OpenAPI\nAC-1 ...",
        'memory/requisitos/Connector/features/openapi-connector/plan.md'         => "# Plan — OpenAPI\nplug-points",
        'memory/requisitos/Connector/features/openapi-connector/tasks.md'        => "# Tasks — OpenAPI\nT-01 ...",
        // Segundo módulo com nome de doc IDÊNTICO — prova que o slug não colide.
        'memory/requisitos/Financeiro/features/recebimento-parcial/requirements.md' => "# Requirements — parcial\nAC-1 ...",
        // Templates/índices seguem a convenção do resto do coletor (pulados).
        'memory/requisitos/Connector/features/openapi-connector/_RASCUNHO.md'    => "# Rascunho\nignore",
        'memory/requisitos/Connector/features/_TEMPLATE/requirements.md'         => "# Template feature\nignore",
        // Doc de nome não-whitelistado na profundidade 1 do módulo: NÃO é coletado.
        // Serve de controle — se virasse HIT, o glob novo estaria pegando o nível errado.
        'memory/requisitos/Connector/requirements.md'                            => "# Solto no módulo\nignore",

        // TRIO DE TELA colado (B3) — vive FORA de memory/, em resources/js/Pages/.
        // `Financeiro/Dre/` está em profundidade 2 DE PROPÓSITO: é o que prova a
        // recursão, já que `glob()` do PHP não atravessa `/`.
        'resources/js/Pages/Produto/Edit.charter.md'          => "# Charter Edit\na lei da tela",
        'resources/js/Pages/Produto/Edit.casos.md'            => "# Casos Edit\nUC-PROD-01",
        'resources/js/Pages/Financeiro/Dre/Index.charter.md'  => "# Charter DRE\nprofundidade 2",
        'resources/js/Pages/Produto/Edit.tsx'                 => "export default function Edit() {}",
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

// ── (j) SUPERFICIE.md coletado com type=surface (descoberta por metadado) ────

it('coleta SUPERFICIE.md por módulo com type=surface', function () {
    $base = coletarFixtureRepo();
    try {
        $map = coletarSlugMap(coletarInvoke($base));

        // A "Superfície de código" (gerada por module-surface.mjs) entra no corpus
        // pra a busca da IA achar "quais arquivos são deste contexto" — dor [W] 2026-07-21.
        expect($map)->toHaveKey('superficie-financeiro');
        expect($map['superficie-financeiro']['type'])->toBe('surface');
        expect($map['superficie-financeiro']['module'])->toBe('financeiro');
        expect($map['superficie-financeiro']['path'])
            ->toBe('memory/requisitos/Financeiro/SUPERFICIE.md');
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

// ── trio de FEATURE (memory/requisitos/<Mod>/features/<slug>/) ───────────────
//
// O trio de feature ficava fora do acervo por PROFUNDIDADE, não por pasta: `glob()`
// do PHP não atravessa `/`, e o glob mais fundo que existia parava em
// `memory/requisitos/*/*.md`. Medido em 2026-08-04 rodando `coletarArquivos()` no
// próprio PHP: 9 arquivos no disco, 0 coletados.

it('coleta o trio de feature com type=feature', function () {
    $base = coletarFixtureRepo();
    try {
        $map = coletarSlugMap(coletarInvoke($base));

        foreach (['requirements', 'plan', 'tasks'] as $doc) {
            $slug = "feature-connector-openapi-connector-$doc";
            expect($map)->toHaveKey($slug)
                ->and($map[$slug]['type'])->toBe('feature')
                ->and($map[$slug]['module'])->toBe('connector')
                ->and($map[$slug]['path'])
                    ->toBe("memory/requisitos/Connector/features/openapi-connector/$doc.md");
        }
    } finally {
        coletarLimpar($base);
    }
});

it('o glob novo pega a profundidade 2, e o doc solto na raiz do módulo segue fora', function () {
    $base = coletarFixtureRepo();
    try {
        $arquivos = coletarInvoke($base);
        $paths = array_column($arquivos, 'path');

        // O que o glob novo destrava…
        expect($paths)->toContain('memory/requisitos/Connector/features/openapi-connector/plan.md');

        // …e o que ele NÃO passou a arrastar junto. `memory/requisitos/*/*.md` sempre
        // existiu, mas com whitelist de nomes (RUNBOOK, ARCHITECTURE, …). Se este
        // arquivo virasse HIT, o glob estaria pegando o nível errado e todo `.md` solto
        // de módulo entraria no acervo sem ninguém ter decidido isso.
        expect($paths)->not->toContain('memory/requisitos/Connector/requirements.md');
    } finally {
        coletarLimpar($base);
    }
});

it('o slug do trio de feature abre em /documentacao/{slug} — só [A-Za-z0-9._-]', function () {
    $base = coletarFixtureRepo();
    try {
        $features = array_filter(coletarInvoke($base), fn ($a) => $a['type'] === 'feature');

        // A rota `/documentacao/{slug}` tem `->where('slug', '[A-Za-z0-9._-]+')`. Slug
        // com `:` ou `/` entra no índice e nunca abre — o doc aparece na busca e o
        // clique dá 404. É o que acontece hoje com `charter:<Mod>/<Tela>`.
        expect($features)->not->toBeEmpty();
        foreach ($features as $a) {
            expect($a['slug'])->toMatch('/^[A-Za-z0-9._-]+$/');
        }
    } finally {
        coletarLimpar($base);
    }
});

it('pula _TEMPLATE e _RASCUNHO dentro de features/', function () {
    $base = coletarFixtureRepo();
    try {
        $paths = array_column(coletarInvoke($base), 'path');

        $temTemplate = collect($paths)->contains(fn ($p) => str_contains($p, 'features/_TEMPLATE'));
        $temRascunho = collect($paths)->contains(fn ($p) => str_contains($p, '_RASCUNHO'));

        expect($temTemplate)->toBeFalse();
        expect($temRascunho)->toBeFalse();
    } finally {
        coletarLimpar($base);
    }
});

it('features homônimas de módulos diferentes não colidem no slug', function () {
    $base = coletarFixtureRepo();
    try {
        $map = coletarSlugMap(coletarInvoke($base));

        // Os dois se chamam `requirements.md`; o módulo + a feature desempatam.
        expect($map)->toHaveKey('feature-connector-openapi-connector-requirements');
        expect($map)->toHaveKey('feature-financeiro-recebimento-parcial-requirements');
        expect($map['feature-financeiro-recebimento-parcial-requirements']['module'])->toBe('financeiro');
    } finally {
        coletarLimpar($base);
    }
});

// ── B3 · trio de tela colado (Opção B, 2026-08-01) ───────────────────────────
//
// O trio mora em `resources/js/Pages/`, fora de `memory/` — e por isso estava
// invisível pro RAG. A ADR 0364 queria resolver MOVENDO; a reversão mantém colado
// e indexa in-place. Estes casos defendem o glob aditivo.

it('coleta o trio de tela colado ao .tsx com type e slug canônicos', function () {
    $base = coletarFixtureRepo();

    try {
        $map = coletarSlugMap(coletarInvoke($base));

        expect($map)->toHaveKey('charter:Produto/Edit')
            ->and($map['charter:Produto/Edit']['type'])->toBe('charter')
            ->and($map['charter:Produto/Edit']['module'])->toBe('produto')
            ->and($map['charter:Produto/Edit']['path'])->toBe('resources/js/Pages/Produto/Edit.charter.md');

        expect($map)->toHaveKey('casos:Produto/Edit')
            ->and($map['casos:Produto/Edit']['type'])->toBe('casos');
    } finally {
        coletarLimpar($base);
    }
});

it('recursa além do primeiro nível de Pages (glob do PHP não atravessa /)', function () {
    $base = coletarFixtureRepo();

    try {
        $map = coletarSlugMap(coletarInvoke($base));

        // Profundidade 2. Se a coleta usasse `glob('Pages/*/*.charter.md')`, este
        // arquivo sumia em silêncio — e o módulo Financeiro ficaria fora do RAG.
        expect($map)->toHaveKey('charter:Financeiro/Dre/Index')
            ->and($map['charter:Financeiro/Dre/Index']['module'])->toBe('financeiro');
    } finally {
        coletarLimpar($base);
    }
});

it('não coleta o .tsx — só a doc que vive ao lado dele', function () {
    $base = coletarFixtureRepo();

    try {
        $arquivos = coletarInvoke($base);
        $temTsx = collect($arquivos)->contains(fn ($a) => str_ends_with($a['path'], '.tsx'));

        expect($temTsx)->toBeFalse();
    } finally {
        coletarLimpar($base);
    }
});

it('o slug preserva o caminho — telas homônimas de módulos diferentes não colidem', function () {
    $base = coletarFixtureRepo();

    try {
        // Há 438 `.tsx` em Pages e muitos se chamam `Index`. Slug por basename
        // colidiria e uma tela sobrescreveria a outra no índice, em silêncio.
        $slugs = array_column(coletarInvoke($base), 'slug');

        expect($slugs)->toBe(array_unique($slugs));
    } finally {
        coletarLimpar($base);
    }
});

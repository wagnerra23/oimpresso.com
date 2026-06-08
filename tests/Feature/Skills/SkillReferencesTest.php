<?php

/**
 * Anti-regressão: skills citam paths/classes/ADRs que existem de fato no repo.
 *
 * Skills são markdown — sem checagem de tipo. Quando alguém renomeia um Service,
 * move um arquivo, ou supersede uma ADR, os links em SKILL.md viram letra morta.
 * Este teste quebra antes do drift.
 *
 * Regras (escopo: 2 skills novas — ads-decision-flow + memoria-recall-flow):
 *   1. Todo link relativo `../../...` resolve pra arquivo existente.
 *   2. Todo path Modules/ADS/... ou Modules/Copiloto/... mencionado existe.
 *   3. Todo nome de classe PHP em backticks (PolicyEngine, MeilisearchDriver, etc.)
 *      tem o arquivo correspondente no caminho declarado.
 *   4. Toda referência a ADR (`memory/decisions/NNNN-...md` ou
 *      `memory/requisitos/ADS/adr/arq/ARQ-NNNN-...md`) resolve.
 *
 * Skills antigas ficam de fora desta validação por agora — escopo controlado
 * (a mensagem do Wagner foi "evolução controlada com testes anti-regressão").
 *
 * Quando falha: lista cada link quebrado com a skill de origem.
 */

const SKILLS_VALIDADAS = [
    'ads-decision-flow',
    'memoria-recall-flow',
];

/**
 * Extrai links markdown `[texto](destino)` do conteúdo.
 *
 * @return array<array{texto:string, destino:string}>
 */
function extrairLinksMarkdown(string $conteudo): array
{
    if (! preg_match_all('/\[([^\]]+)\]\(([^)\s]+)\)/', $conteudo, $matches, PREG_SET_ORDER)) {
        return [];
    }
    $links = [];
    foreach ($matches as $m) {
        $links[] = ['texto' => $m[1], 'destino' => $m[2]];
    }

    return $links;
}

/**
 * Resolve link de uma skill (relativo à raiz do repo, padrão CLAUDE.md) pra absoluto.
 *
 * Skills neste projeto seguem a mesma convenção do CLAUDE.md: caminho a partir
 * da raiz, sem `../../`. Ex: `Modules/ADS/Services/PolicyEngine.php`.
 */
function resolverLinkRelativo(string $destino): ?string
{
    if (str_starts_with($destino, 'http://') || str_starts_with($destino, 'https://')) {
        return null; // links externos ignorados
    }
    if (str_starts_with($destino, '#')) {
        return null; // âncoras ignoradas
    }

    // Remove âncora pra checar arquivo
    if (str_contains($destino, '#')) {
        $destino = substr($destino, 0, strpos($destino, '#'));
    }
    if ($destino === '') {
        return null;
    }

    $abs = realpath(base_path(ltrim($destino, '/\\')));

    return $abs !== false ? $abs : base_path(ltrim($destino, '/\\'));
}

/**
 * @return array<array{slug:string, path:string, content:string}>
 */
function skillsValidadas(): array
{
    $base = base_path('.claude/skills');
    $out  = [];
    foreach (SKILLS_VALIDADAS as $slug) {
        $path = "$base/$slug/SKILL.md";
        if (! is_file($path)) {
            continue;
        }
        $out[] = [
            'slug'    => $slug,
            'path'    => $path,
            'content' => file_get_contents($path),
        ];
    }

    return $out;
}

it('todas as skills validadas existem', function () {
    $encontradas = array_column(skillsValidadas(), 'slug');
    expect($encontradas)->toHaveCount(count(SKILLS_VALIDADAS));
});

it('todo link relativo aponta pra arquivo existente', function () {
    $erros = [];

    foreach (skillsValidadas() as $skill) {
        foreach (extrairLinksMarkdown($skill['content']) as $link) {
            $resolvido = resolverLinkRelativo($link['destino']);
            if ($resolvido === null) {
                continue; // externo / âncora
            }
            if (! file_exists($resolvido)) {
                $erros[] = "{$skill['slug']}: [{$link['texto']}]({$link['destino']}) → não existe";
            }
        }
    }

    expect($erros)->toBe([], "Links quebrados nas skills:\n - ".implode("\n - ", $erros));
});

it('toda classe PHP citada em backticks (com .php) tem arquivo correspondente', function () {
    $erros = [];

    foreach (skillsValidadas() as $skill) {
        // Captura crases que contém ".php" e um link próximo:
        // padrão: [`ClasseName`](Modules/Foo/Bar.php) ou [`Foo::metodo()`](.../Foo.php)
        // O teste anterior já valida o link; aqui só conferimos consistência da classe.
        if (preg_match_all(
            '/\[`(\w+)(?:::\w+\(\))?`\]\(([^)]+\.php)\)/',
            $skill['content'],
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $m) {
                $classe = $m[1];
                $path   = resolverLinkRelativo($m[2]);
                if ($path === null || ! file_exists($path)) {
                    continue; // teste de link já cobre
                }
                $conteudoPhp = file_get_contents($path);
                $regexClasse = '/\b(class|interface|trait|enum)\s+'.preg_quote($classe, '/').'\b/';
                if (! preg_match($regexClasse, $conteudoPhp)) {
                    $erros[] = "{$skill['slug']}: backtick `{$classe}` não bate com class/interface no arquivo {$m[2]}";
                }
            }
        }
    }

    expect($erros)->toBe([], "Classes citadas que não existem no arquivo:\n - ".implode("\n - ", $erros));
});

it('skills do ADS referenciam apenas ARQ-NNNN existentes', function () {
    $erros = [];
    $adsBase = base_path('memory/requisitos/ADS/adr/arq');

    foreach (skillsValidadas() as $skill) {
        if ($skill['slug'] !== 'ads-decision-flow') {
            continue;
        }
        if (preg_match_all('/ARQ-\d{4}-[a-z0-9-]+\.md/', $skill['content'], $m)) {
            foreach (array_unique($m[0]) as $nome) {
                if (! file_exists("$adsBase/$nome")) {
                    $erros[] = "ads-decision-flow cita $nome → não existe em memory/requisitos/ADS/adr/arq/";
                }
            }
        }
    }

    expect($erros)->toBe([], implode("\n", $erros));
});

it('skill de memória referencia apenas ADRs existentes em memory/decisions/', function () {
    $erros = [];
    $adrBase = base_path('memory/decisions');

    foreach (skillsValidadas() as $skill) {
        if ($skill['slug'] !== 'memoria-recall-flow') {
            continue;
        }
        if (preg_match_all('/memory\/decisions\/(\d{4}-[a-z0-9-]+\.md)/', $skill['content'], $m)) {
            foreach (array_unique($m[1]) as $nome) {
                if (! file_exists("$adrBase/$nome")) {
                    $erros[] = "memoria-recall-flow cita memory/decisions/$nome → não existe";
                }
            }
        }
    }

    expect($erros)->toBe([], implode("\n", $erros));
});

it('skill ads-decision-flow lista todos os 4 Agents reais do módulo', function () {
    $skill = collect(skillsValidadas())->firstWhere('slug', 'ads-decision-flow');
    expect($skill)->not->toBeNull();

    $agentsDir = base_path('Modules/ADS/Ai/Agents');
    expect(is_dir($agentsDir))->toBeTrue('Modules/ADS/Ai/Agents/ não existe');

    $agentsReais = array_map(
        fn ($f) => basename($f, '.php'),
        glob("$agentsDir/*.php") ?: []
    );

    $faltando = [];
    foreach ($agentsReais as $agent) {
        if (! str_contains($skill['content'], $agent)) {
            $faltando[] = $agent;
        }
    }

    expect($faltando)->toBe([], "Agents existentes em Modules/ADS/Ai/Agents/ não citados na skill: ".implode(', ', $faltando));
});

it('skill memoria-recall-flow lista os services reais de Memoria/', function () {
    $skill = collect(skillsValidadas())->firstWhere('slug', 'memoria-recall-flow');
    expect($skill)->not->toBeNull();

    $svcDir = base_path('Modules/Copiloto/Services/Memoria');
    expect(is_dir($svcDir))->toBeTrue('Modules/Copiloto/Services/Memoria/ não existe');

    // Subset crítico que a skill DEVE citar (drivers + retrieval pipeline).
    $criticos = [
        'MeilisearchDriver',
        'HydeQueryExpander',
        'LlmReranker',
        'NegativeCacheService',
    ];

    $faltando = array_filter(
        $criticos,
        fn ($nome) => ! str_contains($skill['content'], $nome)
    );

    expect(array_values($faltando))->toBe([], 'Services críticos não citados na skill: '.implode(', ', $faltando));
});

<?php

declare(strict_types=1);

/**
 * Gate de FRESCOR de design — todo `visual_source:` de charter aponta pra arquivo
 * que EXISTE no repo.
 *
 * Origem: sessão 2026-06-17/18 (auditoria adversarial do loop design→código). Furo
 * provado: `visual_source` não era validado por gate NENHUM (`git grep visual_source`
 * em PHP/CI = 0) e 5 de 8 charters apontavam pra arquivo morto/`_BACKUP-NAO-USAR`.
 * É o medo do Wagner — "pegar a versão errada do design" — sem defesa. Este teste
 * transforma o campo decorativo em catraca que MORDE.
 *
 * Plano: "Catraca Viva", Fase 0 (gate-semente). Prova o método M4/M5 num caso real:
 *   - MORDE: falha o CI (roda no ui-architecture-gate.yml, required ADR 0263).
 *   - AUTO-TESTA: caso negativo in-band (fixtures/dead-visual-source.charter.md) prova
 *     que o gate acusa um path morto — senão é teatro (lição: self-test da conformance
 *     que "rodava via ci.yml" mas o ci.yml não rodava vitest).
 *   - VIGIA: allowlist explícita e versionada (revisável em PR), não skip silencioso.
 *
 * Filesystem-puro (sem DB, sem browser). Rápido.
 *
 * @see Modules/Jana/Services/CharterHealthChecker.php (charterRefsBroken — advisory; este MORDE)
 * @see tests/Feature/Architecture/AppShellUsageGateTest.php (mesmo padrão estrutural)
 */

// Charters cujo `visual_source` aponta pra protótipo ARQUIVADO (sem fonte viva), com
// motivo explícito. NÃO é "skip" — é débito documentado e congelado: charter NOVO com
// path morto falha mesmo assim. [CC]/[W] decide repoint (promover protótipo pra fora do
// _BACKUP) ou remover o campo. Allowlist só ENCOLHE (mesma filosofia de ratchet).
const CHARTER_VSOURCE_ALLOWLIST = [
    'RecurringBilling/Index.charter.md'
        => 'protótipo recurring arquivado em _BACKUP-NAO-USAR pós-implementação; sem fonte viva — [CC] decidir repoint/remover',
    'RecurringBilling/Planos/Index.charter.md'
        => 'idem recurring (protótipo arquivado)',
    'RecurringBilling/Faturas/Index.charter.md'
        => 'idem recurring (protótipo arquivado)',
    'RecurringBilling/Configuracoes/Index.charter.md'
        => 'idem recurring (protótipo arquivado)',
];

function charterGateRepoRoot(): string
{
    // tests/Feature/Architecture -> repo root (3 níveis acima). Sem base_path()
    // (estrutural de filesystem, não precisa do app bootstrapado).
    return dirname(__DIR__, 3);
}

/**
 * Extrai o PATH do campo `visual_source:` do frontmatter (1º token; o valor costuma
 * ter texto descritivo depois — "· função…", "(tab Faturas)"). Devolve null se o
 * charter não declara `visual_source` ou se o valor não parece um path repo-relativo.
 */
function charterVisualSourcePath(string $charterAbs): ?string
{
    $content = @file_get_contents($charterAbs);
    if ($content === false) {
        return null;
    }
    // Só frontmatter (entre os dois `---`).
    if (! preg_match('/^---\R(.*?)\R---/s', $content, $fm)) {
        return null;
    }
    if (! preg_match('/^visual_source:\s*["\']?(\S+)/m', $fm[1], $m)) {
        return null;
    }
    $path = rtrim($m[1], "\"'");

    // Repo-relativo? (não-URL, contém "/"). Senão não dá pra verificar — não acusa.
    if (preg_match('#^https?://#', $path) || ! str_contains($path, '/')) {
        return null;
    }

    return $path;
}

/**
 * Devolve o motivo da violação (string) ou null se o `visual_source` está OK / ausente.
 * Um charter viola quando o path declarado NÃO existe no repo.
 */
function charterVisualSourceViolation(string $charterAbs, string $repoRoot): ?string
{
    $path = charterVisualSourcePath($charterAbs);
    if ($path === null) {
        return null; // sem visual_source verificável — fora do escopo
    }

    if (! is_file($repoRoot.'/'.ltrim($path, '/'))) {
        return "visual_source morto: {$path}";
    }

    return null;
}

/** @return list<string> caminhos relativos a resources/js/Pages (sep "/") */
function charterFilesUnderPages(string $repoRoot): array
{
    $dir = $repoRoot.'/resources/js/Pages';
    if (! is_dir($dir)) {
        return [];
    }

    $out = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if (! $file->isFile() || ! str_ends_with($file->getPathname(), '.charter.md')) {
            continue;
        }
        $out[] = str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1));
    }
    sort($out);

    return $out;
}

it('todo visual_source de charter aponta pra arquivo que existe (exceto allowlist arquivada)', function () {
    $root = charterGateRepoRoot();
    $charters = charterFilesUnderPages($root);

    // Sanidade: o crawler tem que achar um volume realista de charters.
    expect(count($charters))->toBeGreaterThan(20);

    $violations = [];
    foreach ($charters as $rel) {
        if (array_key_exists($rel, CHARTER_VSOURCE_ALLOWLIST)) {
            continue;
        }
        $why = charterVisualSourceViolation($root.'/resources/js/Pages/'.$rel, $root);
        if ($why !== null) {
            $violations[] = "{$rel} → {$why}";
        }
    }

    sort($violations);

    expect($violations)->toBe([], sprintf(
        "Charter(s) com visual_source apontando pra arquivo inexistente (%d). "
        ."Repoint pro protótipo vigente, OU (se arquivado sem fonte viva) adicione à "
        ."allowlist com motivo em %s:\n  - %s",
        count($violations),
        'tests/Feature/Architecture/CharterVisualSourceGateTest.php',
        implode("\n  - ", $violations)
    ));
});

it('MORDE: acusa um visual_source morto (fixture negativa — anti-teatro)', function () {
    // Self-test in-band: a MESMA lógica do gate real, apontada pra um charter-fixture
    // cujo visual_source é intencionalmente morto. Se isto NÃO acusar, o gate virou
    // teatro (gate-selftest.mjs é node-only e não roda Pest → o self-test vive aqui).
    $fixture = __DIR__.'/fixtures/dead-visual-source.charter.md';
    expect(is_file($fixture))->toBeTrue('fixture negativa sumiu — self-test perdeu o dente');

    $why = charterVisualSourceViolation($fixture, charterGateRepoRoot());

    expect($why)->not->toBeNull('o gate NÃO acusou um visual_source comprovadamente morto — parou de morder');
    expect($why)->toContain('__intencionalmente_inexistente__');
});

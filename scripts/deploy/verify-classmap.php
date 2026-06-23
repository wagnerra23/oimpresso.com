<?php

/**
 * verify-classmap.php — gate de deploy (emenda ADR 0269; incidentes 2026-06-18 + 2026-06-23).
 *
 * Roda no SERVIDOR (Hostinger LSPHP) logo após `composer dump-autoload -o
 * --classmap-authoritative`, AINDA em maintenance ON e ANTES de tirar o site do
 * ar. Verifica que as classes do namespace App\ referenciadas nos pontos que
 * bootam ANTES (ou muito cedo) do logger — exception Handler, HTTP Kernel
 * (middleware web/global), Console Kernel e bootstrap/app.php — estão presentes
 * no CLASSMAP AUTORITATIVO recém-gerado.
 *
 * Por que isso derruba prod: com `--classmap-authoritative` o Composer DESLIGA o
 * fallback de scan PSR-4 do filesystem. Uma classe nova que ficou FORA do classmap
 * (deploy interrompido/cancelado entre `git reset --hard` e o `dump-autoload`, ou
 * source à frente do classmap num flurry de merges) vira "Target class [X] does
 * not exist" → BindingResolutionException → 500. Casos reais:
 *   - 2026-06-18: App\Support\Errors\ErrorReporter (Handler::register) → 500 em
 *     TODA rota, ANTES do logger (nada no laravel.log).
 *   - 2026-06-23: App\Http\Middleware\VisregStateMiddleware (grupo web) → 500 em
 *     /login e toda rota web, em handle() E terminateMiddleware() (deploy a9d0593ff
 *     cancelado no meio → classmap stale).
 *
 * Por que `php artisan about` (boot-smoke console do deploy.yml) NÃO basta: o
 * kernel CLI não monta o pipeline HTTP, então middleware WEB quebrado passa
 * "verde" pelo about. Este verificador é puramente léxico (token_get_all) +
 * pertencimento ao classmap — determinístico, sem bootar o app, sem incluir a
 * classe (zero efeito colateral), e cobre exatamente os arquivos que tombaram.
 *
 * Escopo: só namespace App\ — classe vendor faltando é problema de `composer
 * install` (sinalizado noutro lugar) e ampliar o escopo aumentaria falso-positivo
 * que bloquearia deploy saudável. `isset($classmap[$fqcn])` reproduz fielmente a
 * resolução autoritativa do ClassLoader (findFile só olha o classMap quando
 * authoritative=true), inclusive para interfaces/traits/enums (o classmap os indexa).
 *
 * Saída: "CLASSMAP_OK ..." + exit 0, ou "CLASSMAP_MISSING: <fqcns>" + exit 1.
 *
 * @see .github/workflows/deploy.yml — step "Verifica classmap autoritativo"
 * @see memory/decisions/0269-deploy-automatico-build-no-runner.md
 * @see memory/reference/deploy-recovery-patterns.md (§11)
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);

$classmapFile = $root . '/vendor/composer/autoload_classmap.php';

if (! is_file($classmapFile)) {
    fwrite(STDERR, "CLASSMAP_MISSING_FILE: vendor/composer/autoload_classmap.php ausente — `composer dump-autoload` não rodou.\n");
    exit(1);
}

/** @var array<string,string> $classmap FQCN => caminho do arquivo. */
$classmap = require $classmapFile;

if (! is_array($classmap)) {
    fwrite(STDERR, "CLASSMAP_INVALID: autoload_classmap.php não retornou um array.\n");
    exit(1);
}

// Arquivos de alto sinal que bootam cedo / antes do logger. Literais FQCN aqui
// (middleware no Kernel, classes de erro no Handler) são quase sempre refs reais
// de classe → falso-positivo é praticamente nulo.
$targets = [
    $root . '/app/Http/Kernel.php',
    $root . '/app/Exceptions/Handler.php',
    $root . '/app/Console/Kernel.php',
    $root . '/bootstrap/app.php',
];

$fqcns = [];

foreach ($targets as $file) {
    if (! is_file($file)) {
        continue;
    }

    $tokens = token_get_all((string) file_get_contents($file));

    // O nome qualificado de uma DECLARAÇÃO de namespace (ex.: `namespace App\Http;`)
    // NÃO é uma classe e não está no classmap — pulamos o nome que segue T_NAMESPACE
    // pra não gerar falso-positivo que bloquearia deploy saudável.
    $afterNamespace = false;

    foreach ($tokens as $token) {
        if (! is_array($token)) {
            $afterNamespace = false; // `;`, `{`, etc. encerram o contexto
            continue;
        }

        $id = $token[0];

        // Whitespace e comentários NÃO mudam o estado (e refs em comentário são
        // T_COMMENT/T_DOC_COMMENT → nunca entram como nome qualificado).
        if ($id === T_WHITESPACE || $id === T_COMMENT || $id === T_DOC_COMMENT) {
            continue;
        }

        if ($id === T_NAMESPACE) {
            $afterNamespace = true;
            continue;
        }

        // PHP 8: nomes qualificados chegam como tokens únicos.
        if ($id === T_NAME_QUALIFIED || $id === T_NAME_FULLY_QUALIFIED) {
            if ($afterNamespace) {
                $afterNamespace = false; // é o nome da declaração de namespace → ignora
                continue;
            }

            $name = ltrim($token[1], '\\');

            if (str_starts_with($name, 'App\\')) {
                $fqcns[$name] = true;
            }

            continue;
        }

        $afterNamespace = false; // qualquer outro token significativo encerra o contexto
    }
}

$missing = [];

foreach (array_keys($fqcns) as $fqcn) {
    if (! isset($classmap[$fqcn])) {
        $missing[] = $fqcn;
    }
}

if ($missing !== []) {
    sort($missing);
    fwrite(STDERR, 'CLASSMAP_MISSING: ' . implode(', ', $missing) . "\n");
    fwrite(STDERR, "O classmap autoritativo NÃO resolve as classes acima — em prod isso vira\n");
    fwrite(STDERR, "BindingResolutionException (Target class does not exist) → 500 site-wide.\n");
    fwrite(STDERR, "Causa típica: deploy interrompido/cancelado entre 'git reset --hard' e\n");
    fwrite(STDERR, "'composer dump-autoload', ou source à frente do classmap. Re-rodar o\n");
    fwrite(STDERR, "dump-autoload (-o --classmap-authoritative) contra o source final corrige.\n");
    exit(1);
}

echo 'CLASSMAP_OK (' . count($fqcns) . " refs App\\ resolvidas no classmap autoritativo de " . count($classmap) . " classes)\n";
exit(0);

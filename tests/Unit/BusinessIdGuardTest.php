<?php

declare(strict_types=1);

/**
 * Business ID Guard — varredura estática garantindo que nenhum test/fixture
 * use `business_id = 4` (RotaLivre cliente externo) como default.
 *
 * **Por quê:** Wagner reforçou 2026-05-07 noite-3: "emitir na minha empresa 1
 * sempre, isso é um erro padrão grave prioridade não pode no cliente".
 * biz=4 é a RotaLivre/Larissa (cliente real, 99% volume comercial). Usar como
 * cobaia técnica em fixture/test/smoke é grave — risco de vazamento pra prod
 * com efeito fiscal real (NFC-e contra CNPJ da Larissa).
 *
 * **Regra:**
 * - Default fixture/test = `business_id => 1` (empresa Wagner WR2 Sistemas)
 * - Cross-tenant adversário = `business_id => 99` (improvável existir)
 * - Padrão `decimal(7, 4)` e `char('cfop', 4)` são SCHEMA, não business_id — OK
 *
 * **Auto-mem:** `feedback_test_business_id_1_nunca_4.md` (regra dura).
 * **PRs precedentes:** #208 (NfeBrasil 14 arquivos), #215 (escapados Cert),
 * este PR (Whatsapp 8 + RecurringBilling 4 arquivos + guard test).
 *
 * @see memory/MEMORY.md (entry topo 🚨)
 */

use Symfony\Component\Finder\Finder;

/**
 * Padrões de detecção. Cada regex pega o uso de `4` como business_id de forma
 * inequívoca — sem pegar `decimal(7, 4)`, `char(4)`, `business_id => 14/40`
 * (word-boundary `\b4\b`) ou comparações `=== 4`/`!== 4` em strings de
 * meta-asserção. Cobre: chave de array (incl. prefixo namespaced tipo
 * `gen_ai.business_id`), property/atribuição snake_case e camelCase, named arg,
 * fluent builder e session key.
 */
const BIZ_GUARD_PATTERNS = [
    // Array PHP key (com ou sem prefixo namespaced): 'business_id' => 4,
    // 'user.business_id' => 4, 'gen_ai.business_id' => 4 (qualquer espaçamento).
    // O `[\w.]*` opcional cobre chaves prefixadas tipo OTel/atributo namespaced
    // sem deixar de pegar a forma simples (prefixo vazio).
    '/[\'"][\w.]*business_id[\'"]\s*=>\s*4\b/',
    // Atribuição: $x->business_id = 4 ou $x->business_id=4
    '/->business_id\s*=\s*4\b/',
    // Property/atribuição snake_case: public ?int $business_id = 4; ou $business_id = 4
    // (single `=` só — não pega comparação === / !== em strings de meta-asserção)
    '/\$business_id\s*=\s*4\b/',
    // Named arg PHP: businessId: 4
    '/businessId\s*:\s*4\b/',
    // Atribuição variável camelCase: $businessId = 4
    '/\$businessId\s*=\s*4\b/',
    // Fluent builder: business(4)  (TransactionBuilder etc)
    '/->business\(4\)/',
    // session(['business.id' => 4])
    '/[\'"]business\.id[\'"]\s*=>\s*4\b/',
];

/**
 * Coleta arquivos .php em Modules/<X>/Tests/ + tests/ — onde a regra aplica.
 * Pula arquivos de fixture explicitamente aprovados (este guard test inclusive).
 *
 * @return array<int, array{path: string, relpath: string, content: string}>
 */
function bizGuardCollectFiles(): array
{
    $base = realpath(__DIR__.'/../..');
    if ($base === false) {
        return [];
    }

    $paths = [];

    // tests/ raiz
    if (is_dir($base.'/tests')) {
        $paths[] = $base.'/tests';
    }

    // Modules/*/Tests/ por módulo
    if (is_dir($base.'/Modules')) {
        foreach (glob($base.'/Modules/*/Tests', GLOB_ONLYDIR) ?: [] as $modTests) {
            $paths[] = $modTests;
        }
    }

    if (empty($paths)) {
        return [];
    }

    $finder = (new Finder)
        ->in($paths)
        ->name('*.php')
        ->files();

    $files = [];
    foreach ($finder as $file) {
        $relpath = str_replace('\\', '/', substr($file->getRealPath(), strlen($base) + 1));

        // Não auditar este próprio guard test (auto-referência)
        if (str_ends_with($relpath, 'tests/Unit/BusinessIdGuardTest.php')) {
            continue;
        }

        $files[] = [
            'path'    => $file->getRealPath(),
            'relpath' => $relpath,
            'content' => $file->getContents(),
        ];
    }

    return $files;
}

/**
 * Procura match dos patterns em cada arquivo. Retorna lista de violações
 * `path:linenum: trecho`.
 *
 * @param  array<int, array{path: string, relpath: string, content: string}>  $files
 * @return array<int, string>
 */
function bizGuardScan(array $files): array
{
    $violations = [];

    foreach ($files as $file) {
        $lines = preg_split('/\R/', $file['content']) ?: [];
        foreach ($lines as $idx => $line) {
            // Pula comentários puros (linha começa com // ou * dentro de /** */)
            $trimmed = ltrim($line);
            if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) {
                continue;
            }

            foreach (BIZ_GUARD_PATTERNS as $pattern) {
                if (preg_match($pattern, $line)) {
                    $violations[] = sprintf(
                        '%s:%d: %s',
                        $file['relpath'],
                        $idx + 1,
                        trim($line),
                    );
                    break; // só conta uma violação por linha
                }
            }
        }
    }

    return $violations;
}

it('guard: nenhum test/fixture usa business_id=4 (cliente RotaLivre) como default', function () {
    $files = bizGuardCollectFiles();
    expect($files)->not->toBeEmpty('Nenhum arquivo de test encontrado — guard test inerte');

    $violations = bizGuardScan($files);

    if (! empty($violations)) {
        $msg = "VIOLAÇÃO: business_id=4 (RotaLivre cliente) usado como default em test.\n\n"
            . "Wagner regra: tests SEMPRE biz_id=1 (empresa Wagner), NUNCA 4.\n"
            . "Cross-tenant adversário: usar 99 (improvável existir), não 4.\n\n"
            . "Auto-mem: feedback_test_business_id_1_nunca_4.md\n\n"
            . "Violações encontradas:\n  - "
            . implode("\n  - ", $violations);

        expect($violations)->toBeEmpty($msg);
    }

    expect($violations)->toBeEmpty();
})->group('guard');

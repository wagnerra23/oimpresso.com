<?php

declare(strict_types=1);

// Tests\TestCase já é aplicado globalmente em tests/Pest.php. NÃO redeclarar aqui.

/**
 * ARCHITECTURE TEST — Model de módulo precisa de escopo automático por business.
 *
 * Tier 0 IRREVOGÁVEL (ADR 0093 · Constituição v2 princípio 6). Todo Model
 * Eloquent que vive em `Modules/<X>/{Entities,Models}/**` tem de aplicar escopo
 * de tenant por trait — não basta a coluna existir, a query tem de ser filtrada
 * sem o autor lembrar.
 *
 * ── POR QUE ESTE TESTE EXISTE (receptor do MultiTenantScopeChecker) ──────────
 *
 * A varredura equivalente vivia em `Modules/Governance/Services/Checkers/
 * MultiTenantScopeChecker.php`, orquestrada pelo required `ADR 0216 PR scan`
 * (`governance:audit --diff-only --fail-on=block`). MEDIDO em 2026-07-30, pela
 * SAÍDA do próprio gate (artefato `governance-drift-report.json` de runs reais):
 *
 *   - em `--diff-only` o checker lia `git diff --cached`. Num checkout de CI
 *     nada está staged → `models_scanned: 0` em TODO PR, inclusive no #4917,
 *     que criou o `Modules/VozDoCliente` inteiro com Entities novas;
 *   - dos 12 checkers, só este tinha `enforcement=block`, e `--fail-on=block`
 *     só reprova por checker `block` → o required era estruturalmente incapaz
 *     de reprovar (99 de 99 runs de PR verdes);
 *   - o modo `--all` (diário, exit 0 por desenho) scaneava 211 Models e
 *     devolvia 94 findings nunca triados, com falso-positivo estrutural.
 *
 * Classe LC-13 (verde por não-execução). O receptor mora aqui porque a lane
 * `No hardcode business_id (Tier 0)` (`.github/workflows/multi-tenant-gate.yml`)
 * já é **required** e já é o dono do tema multi-tenant — estender o dono, não
 * abrir gate paralelo (proibicoes.md §5, "duplica régua consolidada").
 *
 * ── OS 3 FALSOS-POSITIVOS QUE ESTA VERSÃO CORRIGE (medidos, não supostos) ────
 *
 *   1. `Modules/Financeiro/Models/Concerns/BusinessScope.php` — é um TRAIT,
 *      flagado porque o docblock dele contém o exemplo `class Titulo extends
 *      Model {`. O detector antigo casava regex em texto corrido, comentário
 *      incluído. Aqui a detecção é por TOKEN (`token_get_all`), então
 *      comentário não é código e o caso morre por construção.
 *   2. `Modules/Financeiro/Models/Titulo.php` — aplica escopo via a trait local
 *      `Modules\Financeiro\Models\Concerns\BusinessScope`.
 *   3. `Modules/KB/Entities/KbNode.php` — idem via
 *      `Modules\KB\Entities\Concerns\BelongsToBusinessTrait`.
 *
 * (2) e (3) morriam numa lista fechada de 2 nomes de trait. Aqui o critério é
 * ESTRUTURAL: a trait qualifica se o código dela cita `business_id` E chama
 * `addGlobalScope`. Medido nas 4 traits reais do repo — as 2 canônicas de
 * `app/Concerns/` e as 2 locais de módulo — todas carregam essa assinatura.
 * Assim uma trait de escopo nova qualifica sozinha, sem editar lista nenhuma.
 *
 * ── FORWARD-ONLY (ADR 0275) ─────────────────────────────────────────────────
 *
 * A dívida existente fica GRANDFATHERED em
 * `governance/multi-tenant-scope-baseline.json`. O teste morde quem PIORA:
 * Model novo (ou Model existente que perde a trait) sem escopo. Backfill em
 * massa de legado morre no CI (proibicoes.md §5 2026-07-12) — a dívida sai
 * quando o arquivo for tocado por trabalho real.
 *
 * Refs:
 *   - memory/decisions/0093-multi-tenant-isolation-tier-0.md
 *   - memory/decisions/0218-multi-tenant-scope-checker-tier-0.md (o checker original)
 *   - memory/proibicoes.md §"Multi-tenant Tier 0 IRREVOGÁVEL"
 *   - .github/workflows/multi-tenant-gate.yml (a lane required que roda isto)
 *
 * @group architecture
 */

const MTS_ROOT = __DIR__ . '/../../..';

const MTS_BASELINE_PATH = MTS_ROOT . '/governance/multi-tenant-scope-baseline.json';

/**
 * Assinatura ESTRUTURAL de uma trait que aplica escopo de tenant.
 * Medida 2026-07-30 nas 4 traits reais: HasBusinessScope,
 * BelongsToBusinessViaParent, KB\...\BelongsToBusinessTrait,
 * Financeiro\...\BusinessScope — todas casam as duas condições.
 */
function mtsTraitAplicaEscopo(string $src): bool
{
    return str_contains($src, 'business_id') && str_contains($src, 'addGlobalScope');
}

/** Resolve FQCN de trait → caminho de arquivo (PSR-4 do projeto). */
function mtsTraitPath(string $fqcn): ?string
{
    $fqcn = ltrim($fqcn, '\\');

    if (str_starts_with($fqcn, 'App\\')) {
        $rel = 'app/' . str_replace('\\', '/', substr($fqcn, 4)) . '.php';
    } elseif (str_starts_with($fqcn, 'Modules\\')) {
        $rel = 'Modules/' . str_replace('\\', '/', substr($fqcn, 8)) . '.php';
    } else {
        return null; // vendor / framework — não é trait de escopo do projeto
    }

    $abs = MTS_ROOT . '/' . $rel;

    return is_file($abs) ? $abs : null;
}

/** true se a trait resolve pra arquivo do projeto QUE aplica escopo. */
function mtsTraitQualifica(string $fqcn): bool
{
    static $cache = [];

    if (array_key_exists($fqcn, $cache)) {
        return $cache[$fqcn];
    }

    $path = mtsTraitPath($fqcn);
    $src = $path !== null ? file_get_contents($path) : false;

    return $cache[$fqcn] = ($src !== false && mtsTraitAplicaEscopo($src));
}

/**
 * Analisa o fonte de UM arquivo PHP por TOKEN (comentário não conta como código).
 *
 * @return array{is_model: bool, fqcn: ?string, traits: array<int, string>}
 */
function mtsAnalisar(string $src): array
{
    $tokens = @token_get_all($src);
    $sig = array_values(array_filter(
        $tokens,
        fn ($t) => ! (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)),
    ));

    $namespace = null;
    $imports = [];      // short name (lower) => FQCN
    $className = null;
    $extendsModel = false;
    $classBodyAt = null;
    $traits = [];

    $texto = static fn ($t) => is_array($t) ? $t[1] : $t;

    for ($i = 0; $i < count($sig); $i++) {
        $t = $sig[$i];

        // namespace Foo\Bar;
        if (is_array($t) && $t[0] === T_NAMESPACE && $namespace === null) {
            $buf = '';
            for ($j = $i + 1; $j < count($sig) && $texto($sig[$j]) !== ';' && $texto($sig[$j]) !== '{'; $j++) {
                $buf .= $texto($sig[$j]);
            }
            $namespace = trim($buf);
            continue;
        }

        // use Foo\Bar; / use Foo\Bar as Baz;  (topo do arquivo — antes da classe)
        if (is_array($t) && $t[0] === T_USE && $className === null) {
            $buf = '';
            $j = $i + 1;
            for (; $j < count($sig) && $texto($sig[$j]) !== ';'; $j++) {
                $buf .= $texto($sig[$j]);
            }
            $i = $j;
            foreach (explode(',', $buf) as $parte) {
                $parte = trim($parte);
                if ($parte === '' || str_starts_with($parte, 'function') || str_starts_with($parte, 'const')) {
                    continue;
                }
                if (preg_match('/^(.+?)\s*as\s+(\w+)$/i', $parte, $m)) {
                    $imports[strtolower($m[2])] = ltrim(trim($m[1]), '\\');
                } else {
                    $short = substr($parte, (int) strrpos($parte, '\\') + 1);
                    $imports[strtolower($short)] = ltrim($parte, '\\');
                }
            }
            continue;
        }

        // class X extends Y   (ignora `::class` e classe anônima)
        if (is_array($t) && $t[0] === T_CLASS && $className === null) {
            $anterior = $i > 0 ? $sig[$i - 1] : null;
            if (is_array($anterior) && $anterior[0] === T_DOUBLE_COLON) {
                continue; // Foo::class
            }
            if (! isset($sig[$i + 1]) || ! is_array($sig[$i + 1]) || $sig[$i + 1][0] !== T_STRING) {
                continue; // new class (...) — anônima
            }
            $className = $sig[$i + 1][1];

            for ($j = $i + 2; $j < count($sig) && $texto($sig[$j]) !== '{'; $j++) {
                if (is_array($sig[$j]) && $sig[$j][0] === T_EXTENDS) {
                    $pai = '';
                    for ($k = $j + 1; $k < count($sig) && ! in_array($texto($sig[$k]), ['{', ','], true); $k++) {
                        if (is_array($sig[$k]) && $sig[$k][0] === T_IMPLEMENTS) {
                            break;
                        }
                        $pai .= $texto($sig[$k]);
                    }
                    $pai = trim($pai);
                    $curto = substr($pai, (int) strrpos($pai, '\\') + 1);
                    $extendsModel = ($curto === 'Model' || $curto === 'Pivot' || $curto === 'Authenticatable');
                }
                if ($texto($sig[$j]) === '{') {
                    break;
                }
            }
            for ($j = $i + 2; $j < count($sig); $j++) {
                if ($texto($sig[$j]) === '{') {
                    $classBodyAt = $j;
                    break;
                }
            }
            continue;
        }

        // use TraitA, TraitB;  dentro do corpo da classe
        if (is_array($t) && $t[0] === T_USE && $classBodyAt !== null && $i > $classBodyAt) {
            $prox = $sig[$i + 1] ?? null;
            $ehTrait = is_array($prox) && in_array($prox[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NS_SEPARATOR], true);
            if (! $ehTrait) {
                continue; // function () use ($x)
            }
            $buf = '';
            $j = $i + 1;
            for (; $j < count($sig) && ! in_array($texto($sig[$j]), [';', '{'], true); $j++) {
                $buf .= $texto($sig[$j]);
            }
            $i = $j;
            foreach (explode(',', $buf) as $nome) {
                $nome = ltrim(trim($nome), '\\');
                if ($nome === '') {
                    continue;
                }
                $primeiro = strtolower(explode('\\', $nome)[0]);
                if (isset($imports[$primeiro])) {
                    $resto = substr($nome, strlen(explode('\\', $nome)[0]));
                    $traits[] = $imports[$primeiro] . $resto;
                } elseif (str_contains($nome, '\\')) {
                    $traits[] = $nome;
                } else {
                    $traits[] = $namespace !== null ? "{$namespace}\\{$nome}" : $nome;
                }
            }
        }
    }

    return [
        'is_model' => $extendsModel && $className !== null,
        'fqcn' => $className !== null && $namespace !== null ? "{$namespace}\\{$className}" : $className,
        'traits' => array_values(array_unique($traits)),
    ];
}

/** Model tem escopo automático de tenant? */
function mtsTemEscopo(array $analise): bool
{
    foreach ($analise['traits'] as $trait) {
        if (mtsTraitQualifica($trait)) {
            return true;
        }
    }

    return false;
}

/** @return array<int, string> caminhos relativos de Model sob Modules/<X>/{Entities,Models} */
function mtsColetarArquivos(): array
{
    $files = [];

    foreach (glob(MTS_ROOT . '/Modules/*', GLOB_ONLYDIR) ?: [] as $mod) {
        foreach (['Entities', 'Models'] as $dir) {
            $raiz = "{$mod}/{$dir}";
            if (! is_dir($raiz)) {
                continue;
            }
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $arq) {
                if ($arq->isFile() && $arq->getExtension() === 'php') {
                    $files[] = str_replace('\\', '/', str_replace(MTS_ROOT . '/', '', $arq->getPathname()));
                }
            }
        }
    }

    sort($files);

    return $files;
}

/** @return array<int, string> Models sem escopo (caminhos relativos) */
function mtsInfratores(): array
{
    $out = [];

    foreach (mtsColetarArquivos() as $rel) {
        $src = file_get_contents(MTS_ROOT . '/' . $rel);
        if ($src === false) {
            continue;
        }
        $analise = mtsAnalisar($src);
        if (! $analise['is_model']) {
            continue;
        }
        if (! mtsTemEscopo($analise)) {
            $out[] = $rel;
        }
    }

    return $out;
}

function mtsBaseline(): array
{
    if (! is_file(MTS_BASELINE_PATH)) {
        return ['grandfathered' => [], 'allowlist' => []];
    }
    $j = json_decode((string) file_get_contents(MTS_BASELINE_PATH), true);

    return [
        'grandfathered' => (array) ($j['grandfathered'] ?? []),
        'allowlist' => (array) ($j['allowlist'] ?? []),
    ];
}

describe('Arquitetura — Model de módulo com escopo automático por business (Tier 0)', function () {

    it('coleciona Models suficientes pra auditoria (sanity — instrumento vivo)', function () {
        // Controle positivo: se o scan devolver pouco, o instrumento quebrou
        // (case de pasta, glob, PSR-4) e o verde abaixo seria não-execução.
        expect(count(mtsColetarArquivos()))->toBeGreaterThan(150);
    });

    it('nenhum Model NOVO sem escopo automático de tenant', function () {
        $baseline = mtsBaseline();
        $isentos = array_merge($baseline['grandfathered'], $baseline['allowlist']);
        $novos = array_values(array_diff(mtsInfratores(), $isentos));

        if ($novos !== []) {
            $this->fail(
                'Model sem escopo automático de business (Tier 0 ADR 0093) em ' . count($novos) . " arquivo(s):\n  - "
                . implode("\n  - ", $novos)
                . "\n\nAção: aplicar `use App\\Concerns\\HasBusinessScope;` (ou "
                . "`BelongsToBusinessViaParent`, ou uma trait de escopo do próprio módulo) no Model.\n"
                . 'Se o Model for LEGITIMAMENTE global (catálogo read-only, system-wide), declarar em '
                . "governance/multi-tenant-scope-baseline.json > allowlist COM razão.\n"
                . 'NÃO adicionar em `grandfathered` — esse campo é dívida datada, só desce.',
            );
        }

        expect(true)->toBeTrue();
    });

    it('a dívida grandfathered só desce (catraca)', function () {
        $baseline = mtsBaseline();
        $infratores = mtsInfratores();
        $curados = array_values(array_diff($baseline['grandfathered'], $infratores));

        // Não reprova: curar é o objetivo. Mas avisa alto pra baseline encolher.
        if ($curados !== []) {
            fwrite(STDERR, "\n[multi-tenant-scope] " . count($curados)
                . " arquivo(s) do baseline JÁ FORAM CURADOS — regenere o baseline removendo:\n  - "
                . implode("\n  - ", $curados) . "\n");
        }

        expect(array_values(array_diff($baseline['grandfathered'], mtsColetarArquivos())))
            ->toBe([], 'baseline cita arquivo que não existe mais — regenerar');
    });

    // ── BITE-TEST: prova que o analisador MORDE, e que não morde o legítimo ──

    it('MORDE: Model sem trait de escopo é detectado', function () {
        $src = <<<'PHP'
        <?php
        namespace Modules\Fake\Entities;
        use Illuminate\Database\Eloquent\Model;
        class Vazado extends Model { protected $table = 'fake'; }
        PHP;
        $a = mtsAnalisar($src);
        expect($a['is_model'])->toBeTrue();
        expect(mtsTemEscopo($a))->toBeFalse();
    });

    it('NÃO morde: Model com a trait canônica passa', function () {
        $src = <<<'PHP'
        <?php
        namespace Modules\Fake\Entities;
        use App\Concerns\HasBusinessScope;
        use Illuminate\Database\Eloquent\Model;
        class Ok extends Model { use HasBusinessScope; }
        PHP;
        $a = mtsAnalisar($src);
        expect($a['is_model'])->toBeTrue();
        expect($a['traits'])->toContain('App\Concerns\HasBusinessScope');
        expect(mtsTemEscopo($a))->toBeTrue();
    });

    it('NÃO morde: Model com trait de escopo LOCAL do módulo passa (FP #2 e #3 reais)', function () {
        // Titulo e KbNode eram flagados pelo checker antigo por não usarem uma
        // das 2 traits canônicas — mas aplicam escopo por trait do próprio módulo.
        foreach (['Modules/Financeiro/Models/Titulo.php', 'Modules/KB/Entities/KbNode.php'] as $rel) {
            $a = mtsAnalisar((string) file_get_contents(MTS_ROOT . '/' . $rel));
            expect($a['is_model'])->toBeTrue("{$rel} deveria ser reconhecido como Model");
            expect(mtsTemEscopo($a))->toBeTrue("{$rel} aplica escopo por trait local — não pode ser flagado");
        }
    });

    it('NÃO morde: trait NÃO é Model, mesmo com `class X extends Model` no docblock (FP #1 real)', function () {
        // Modules/Financeiro/Models/Concerns/BusinessScope.php é uma trait cujo
        // exemplo de uso no docblock contém a frase que o regex antigo casava.
        $a = mtsAnalisar((string) file_get_contents(MTS_ROOT . '/Modules/Financeiro/Models/Concerns/BusinessScope.php'));
        expect($a['is_model'])->toBeFalse('trait com exemplo no docblock não pode contar como Model');
    });

    it('NÃO morde: `use ($x)` de closure não vira trait', function () {
        $src = <<<'PHP'
        <?php
        namespace Modules\Fake\Entities;
        use App\Concerns\HasBusinessScope;
        use Illuminate\Database\Eloquent\Model;
        class Ok extends Model {
            use HasBusinessScope;
            public function f() { $x = 1; return function () use ($x) { return $x; }; }
        }
        PHP;
        expect(mtsAnalisar($src)['traits'])->toBe(['App\Concerns\HasBusinessScope']);
    });

    it('trait só qualifica se aplicar escopo de verdade (estrutural, não por nome)', function () {
        expect(mtsTraitAplicaEscopo('trait T { public function f() { $q->addGlobalScope("business_id"); } }'))->toBeTrue();
        expect(mtsTraitAplicaEscopo('trait T { public $business_id; }'))->toBeFalse();      // cita, não aplica
        expect(mtsTraitAplicaEscopo('trait T { static::addGlobalScope($s); }'))->toBeFalse(); // aplica, mas não de tenant
    });
});

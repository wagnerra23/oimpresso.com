<?php

declare(strict_types=1);

use App\Console\Commands\UiLintCommand;

/**
 * Gate R7 (PT-04) — `ui:lint` proíbe página aplicar no `className` o wrapper de
 * um bundle CSS de módulo ALHEIO (cross-module borrow · ilha paralela).
 *
 * Ancora: PT-04-Dashboard.md L80 (❌ "Bundle CSS paralelo escopado
 * `.sells-cowork`/`.vd-insights-*` pra estilizar dashboard … contamina o Jana
 * :276") + ADR UI-0013 (camadas: módulo herda das Fundações/Shell, não veste a
 * ilha CSS de outro módulo). Origem: sessão 2026-07-20 — o `Jana/Dashboard.tsx`
 * pega `.sells-cowork` emprestado; a lei existia (PT-04) mas sem catraca, então
 * qualquer um podia reincidir. R7 é a catraca (ADR 0256: derivado+enforçado).
 *
 * Controle-negativo (morde) + controle-positivo (NÃO morde a tela-dona), no
 * padrão fixture boa/ruim da cultura de gates. Testa a LÓGICA (checkR7 via
 * reflection) — estável mesmo depois que o Jana/Dashboard for migrado.
 *
 * @see app/Console/Commands/UiLintCommand.php checkR7 + BUNDLE_WRAPPERS
 */
function r7(string $relPath, string $content): array
{
    $cmd = new UiLintCommand;
    $ref = new ReflectionMethod($cmd, 'checkR7');
    $ref->setAccessible(true);

    return $ref->invoke($cmd, $relPath, $content);
}

it('MORDE: página de módulo alheio aplicando .sells-cowork (o pecado do Jana)', function () {
    $hits = r7(
        'resources/js/Pages/Jana/Dashboard.tsx',
        '<div className="sells-cowork px-6 pt-6 shrink-0">',
    );

    expect($hits)->toHaveCount(1);
    expect($hits[0]['rule'])->toBe('R7');
    expect($hits[0]['match'])->toBe('sells-cowork');
});

it('MORDE cross-module para qualquer bundle alheio (Jana aplicando .fin-cowork)', function () {
    $hits = r7('resources/js/Pages/Jana/Dashboard.tsx', '<div className="fin-cowork">');

    expect($hits)->toHaveCount(1);
    expect($hits[0]['match'])->toBe('fin-cowork');
});

it('NÃO morde a tela-DONA usando o próprio bundle (Sells + .sells-cowork)', function () {
    expect(r7('resources/js/Pages/Sells/Index.tsx', '<div className="sells-cowork">'))->toBeEmpty();
});

it('NÃO morde a tela-DONA do Financeiro (.fin-cowork / .fin-curadoria)', function () {
    expect(r7('resources/js/Pages/Financeiro/Unificado.tsx', '<div className="fin-cowork">'))->toBeEmpty();
    expect(r7('resources/js/Pages/Financeiro/Curadoria.tsx', '<div className="fin-curadoria">'))->toBeEmpty();
});

it('NÃO confunde menção em comentário/import com aplicação em className', function () {
    // O wrapper aparece só em comentário — não é className aplicado → 0 hit
    // (o próprio Jana/Dashboard.tsx cita `.sells-cowork` em comentário além do className real).
    $hits = r7(
        'resources/js/Pages/Jana/Dashboard.tsx',
        "// Wrapper .sells-cowork porque os tokens .vd-insights-* estão escopados\nconst x = 1;",
    );

    expect($hits)->toBeEmpty();
});

it('só vale pra Pages/ (arquivo fora de Pages não dispara)', function () {
    expect(r7('resources/js/Components/board/Foo.tsx', '<div className="sells-cowork">'))->toBeEmpty();
});

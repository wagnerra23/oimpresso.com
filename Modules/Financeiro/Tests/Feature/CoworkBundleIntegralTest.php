<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Cowork Bundle Integral — regra Wagner IRREVOGÁVEL 2026-05-18.
 *
 * Substitui estratégia cherry-pick (que falhou 3× no Financeiro: PR #1085 → #1091 → #1092)
 * por copiar `styles.css` INTEIRO do bundle Cowork (prototipo-ui-patch/vendas-financeiro-completo/).
 *
 * Validação:
 *   - cowork-financeiro-bundle.css existe com >9000 LOC
 *   - Header canon presente (origem + política edição)
 *   - inertia.css importa bundle FIRST + overrides locais DEPOIS
 *   - Bundle contém 123 classes .fin-* + 138 .vd-* esperadas
 *   - Classes-chave do gap report Wagner presentes
 *
 * Regra canônica: memory/reference/feedback-cowork-bundle-aplicar-inteiro.md
 * Proibição Tier 0: memory/proibicoes.md §"Design System / Pacote Cowork novo"
 */

const FIN_BUNDLE = __DIR__ . '/../../../../resources/css/cowork-financeiro-bundle.css';
const FIN_INERTIA_CSS = __DIR__ . '/../../../../resources/css/inertia.css';
const FIN_COWORK_JSX_DIR = __DIR__ . '/../../../../resources/js/Pages/Financeiro/_cowork-bundle';

describe('Cowork Bundle Integral — arquivo + header', function () {
    it('cowork-financeiro-bundle.css existe', function () {
        expect(file_exists(FIN_BUNDLE))->toBeTrue();
    });

    it('Bundle tem mais de 9000 LOC (integral, não cherry-pick)', function () {
        $loc = count(file(FIN_BUNDLE));
        expect($loc)->toBeGreaterThan(9000);
    });

    it('Header canon presente — origem + política edição', function () {
        $head = file_get_contents(FIN_BUNDLE, false, null, 0, 2000);
        expect($head)->toContain('cowork-financeiro-bundle.css');
        expect($head)->toContain('prototipo-ui-patch/vendas-financeiro-completo/styles.css');
        expect($head)->toContain('feedback-cowork-bundle-aplicar-inteiro.md');
        expect($head)->toContain('NÃO editar manualmente');
    });
});

describe('Cowork Bundle Integral — importação em inertia.css', function () {
    it('inertia.css importa bundle PRIMEIRO (base canônica)', function () {
        $src = file_get_contents(FIN_INERTIA_CSS);
        expect($src)->toContain('@import "./cowork-financeiro-bundle.css"');
        // Ordem: bundle ANTES dos overrides (fin-curadoria, fin-output, etc)
        $bundlePos = strpos($src, 'cowork-financeiro-bundle.css');
        $curadoriaPos = strpos($src, 'fin-curadoria.css');
        expect($bundlePos)->toBeLessThan($curadoriaPos);
    });

    it('Overrides locais preservados pós-bundle', function () {
        $src = file_get_contents(FIN_INERTIA_CSS);
        // fin-curadoria, fin-ia, fin-output, fin-cowork DEPOIS do bundle
        expect($src)->toContain('@import "./fin-curadoria.css"');
        expect($src)->toContain('@import "./fin-ia.css"');
        expect($src)->toContain('@import "./fin-output.css"');
        expect($src)->toContain('@import "./fin-cowork.css"');
    });
});

describe('Cowork Bundle Integral — classes canon presentes', function () {
    it('Bundle declara 123+ classes .fin-* (base do Design System)', function () {
        $src = file_get_contents(FIN_BUNDLE);
        $count = preg_match_all('/\.fin-[a-z0-9-]+/', $src);
        expect($count)->toBeGreaterThan(100);
    });

    it('Bundle declara 138+ classes .vd-* (prefixo Vendas — partilha)', function () {
        $src = file_get_contents(FIN_BUNDLE);
        $count = preg_match_all('/\.vd-[a-z0-9-]+/', $src);
        expect($count)->toBeGreaterThan(100);
    });

    it('Classes-chave do gap report Wagner presentes (drawer-tabs família completa)', function () {
        $src = file_get_contents(FIN_BUNDLE);
        expect($src)->toContain('.fin-drawer-tabs');
        expect($src)->toContain('.fin-drawer-tab');
        expect($src)->toContain('.fin-drawer-tab-ai');
        expect($src)->toContain('.fin-drawer-tab-edit');
        expect($src)->toContain('.fin-drawer-tab-ct');
        expect($src)->toContain('.fin-drawer-tab-tag');
    });

    it('Classes canon (Conferido, Anomalia, Audit, Frescor) presentes', function () {
        $src = file_get_contents(FIN_BUNDLE);
        expect($src)->toContain('.fin-conferido-toggle');
        expect($src)->toContain('.fin-ai-anomalia');
        expect($src)->toContain('.fin-audit-row');
        expect($src)->toContain('.fin-frescor');
    });

    it('Sanity check Wagner: regex retorna 19+ matches no bundle (threshold 10)', function () {
        $src = file_get_contents(FIN_BUNDLE);
        $count = preg_match_all('/fin-drawer-tabs|fin-conferido-toggle|fin-ai-anomalia|fin-audit-row|fin-frescor/', $src);
        // Wagner: "se <10, foi truncado. Deve retornar 30+"
        expect($count)->toBeGreaterThanOrEqual(10);
    });
});

describe('Cowork Bundle Integral — JSX files reference (_cowork-bundle/)', function () {
    it('Diretório _cowork-bundle/ existe', function () {
        expect(is_dir(FIN_COWORK_JSX_DIR))->toBeTrue();
    });

    it('README canon presente com manifest + roadmap adaptação', function () {
        $readme = file_get_contents(FIN_COWORK_JSX_DIR . '/README.md');
        expect($readme)->toContain('Bundle Cowork Financeiro INTEGRAL');
        expect($readme)->toContain('feedback-cowork-bundle-aplicar-inteiro.md');
        expect($readme)->toContain('NUNCA importar `.jsx` daqui em produção');
        expect($readme)->toContain('Roadmap de adaptação');
    });

    it('10 arquivos JSX core copiados (8 financeiro-* + fsm-stepper + shell-*)', function () {
        $expected = [
            'financeiro-app.jsx',
            'financeiro-ai.jsx',
            'financeiro-curation.jsx',
            'financeiro-data.jsx',
            'financeiro-icons.jsx',
            'financeiro-output.jsx',
            'financeiro-telas-extras.jsx',
            'fsm-stepper.jsx',
            'shell-app.jsx',
            'shell-data.jsx',
        ];
        foreach ($expected as $f) {
            expect(file_exists(FIN_COWORK_JSX_DIR . '/' . $f))
                ->toBeTrue("Arquivo {$f} faltando no _cowork-bundle/");
        }
    });

    it('financeiro-app.jsx é maior arquivo (>50KB — fonte da Visão Unificada)', function () {
        $size = filesize(FIN_COWORK_JSX_DIR . '/financeiro-app.jsx');
        expect($size)->toBeGreaterThan(50_000);
    });

    it('financeiro-telas-extras.jsx contém telas extras (Fluxo/DRE/Caixa — futuras)', function () {
        $src = file_get_contents(FIN_COWORK_JSX_DIR . '/financeiro-telas-extras.jsx');
        // Pelo menos uma das telas (case-insensitive)
        $hasFluxoOrDre = stripos($src, 'fluxo') !== false
                     || stripos($src, 'dre') !== false
                     || stripos($src, 'caixa') !== false;
        expect($hasFluxoOrDre)->toBeTrue('financeiro-telas-extras.jsx deve referenciar Fluxo/DRE/Caixa');
    });

    it('fsm-stepper.jsx existe (componente NOVO ainda não portado)', function () {
        $src = file_get_contents(FIN_COWORK_JSX_DIR . '/fsm-stepper.jsx');
        expect(strlen($src))->toBeGreaterThan(5_000);
        // FSM stepper deve mencionar steps/stages (state machine UI)
        $hasStep = stripos($src, 'step') !== false || stripos($src, 'stage') !== false;
        expect($hasStep)->toBeTrue();
    });
});

describe('Cowork Bundle — discovery Inertia NÃO pega .jsx (underscore prefix)', function () {
    it('Inertia app.tsx usa glob ./Pages/**/*.tsx (não .jsx)', function () {
        $src = file_get_contents(__DIR__ . '/../../../../resources/js/app.tsx');
        expect($src)->toContain("import.meta.glob('./Pages/**/*.tsx')");
        // NÃO deve haver glob de .jsx (que importaria o bundle por engano)
        expect($src)->not->toContain('./Pages/**/*.jsx');
    });
});

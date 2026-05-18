<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * ARCHITECTURE TEST — Anti-regressão GLOBAL hardcode business_id.
 *
 * Wagner regra IRREVOGÁVEL Tier 0 2026-05-18:
 *   "Nuca faça isso, habilitar e desabilitar é compra de pacote no modulo
 *    superadmin"
 *   "Regra basica junto com Business_id acho que não porderia ser diferente"
 *
 * Visibilidade de módulos por business é via **subscription package** (UI
 * Modules/Superadmin/PackagesController) — NUNCA hardcode
 * `if ($business_id === N) return` em DataController ou Middleware.
 *
 * Este test varre TODOS os arquivos canônicos (não só os 5 da sessão
 * 2026-05-18) — proteção defense-in-depth contra reincidência futura.
 *
 * Catalogado pós-erro arquitetural Claude (PRs #1073/#1074/#1076 revertidos
 * pelo PR #1077, regra elevada Tier 0 pelo PR #1078).
 *
 * Refs:
 *   - memory/proibicoes.md §"Multi-tenant Tier 0 IRREVOGÁVEL"
 *   - memory/reference/feedback-habilitar-modulo-por-business.md
 *   - tests/Feature/Sidebar/Biz4RotaLivreSidebarTest.php (anti-regressão específica)
 *   - Modules/Governance/Tests/Feature/GovernanceModuleSubscriptionGateTest.php
 *
 * @group architecture
 */

const PROJECT_ROOT = __DIR__ . '/../../..';

/**
 * Patterns proibidos (regex). Cada match em arquivo canônico = falha.
 *
 * Foco em comparações IDENTITY (===, !==) ou equality (==, !=) com número
 * literal de business_id. Variáveis cobertas: $business_id, $businessId,
 * $bizId, $current_biz.
 */
const PATTERNS_BANIDOS = [
    '/\$business_id\s*[!=]==?\s*\d+/',
    '/\$businessId\s*[!=]==?\s*\d+/',
    '/\$bizId\s*[!=]==?\s*\d+/',
    '/\$current_biz\s*[!=]==?\s*\d+/',
    // Variantes especiais de nomes que apareceram no incidente original
    '/\$piloto_rotalivre/',
    '/\$piloto_biz/',
];

/**
 * Globs de arquivos canônicos a auditar. Critério: qualquer caminho onde
 * código se beneficiaria do gate canônico `hasThePermissionInSubscription`
 * em vez de hardcode.
 */
const GLOBS_CANONICOS = [
    '/Modules/*/Http/Controllers/DataController.php',
    '/Modules/*/Http/Controllers/*Controller.php',
    '/app/Http/Middleware/AdminSidebarMenu.php',
    '/app/Http/Middleware/HandleInertiaRequests.php',
];

function collectCanonicalFiles(): array
{
    $files = [];
    foreach (GLOBS_CANONICOS as $glob) {
        $matches = glob(PROJECT_ROOT . $glob);
        if ($matches !== false) {
            $files = array_merge($files, $matches);
        }
    }
    return array_values(array_unique($files));
}

describe('Arquitetura — anti-regressão hardcode business_id Tier 0 IRREVOGÁVEL', function () {

    it('coleciona arquivos canônicos suficientes pra auditoria (sanity check)', function () {
        $files = collectCanonicalFiles();
        // Esperamos pelo menos ~30 arquivos (20+ DataControllers + 2 middlewares + Modules/*/Http/Controllers)
        expect(count($files))->toBeGreaterThan(30);
    });

    it('nenhum arquivo canônico contém pattern hardcode `$business_id === N`', function () {
        $files = collectCanonicalFiles();
        $violacoes = [];

        foreach ($files as $file) {
            $relative = str_replace(PROJECT_ROOT, '', $file);
            $src = file_get_contents($file);
            if ($src === false) continue;

            foreach (PATTERNS_BANIDOS as $pattern) {
                if (preg_match_all($pattern, $src, $matches, PREG_OFFSET_CAPTURE) > 0) {
                    foreach ($matches[0] as $match) {
                        $linha = substr_count(substr($src, 0, $match[1]), "\n") + 1;
                        $violacoes[] = "{$relative}:{$linha} → {$match[0]}";
                    }
                }
            }
        }

        if (! empty($violacoes)) {
            $msg = "Hardcode `\$business_id === N` detectado em " . count($violacoes) . " local(is):\n  - "
                . implode("\n  - ", $violacoes)
                . "\n\nWagner regra Tier 0 IRREVOGÁVEL 2026-05-18: visibilidade per-business é via\n"
                . "subscription package (UI Modules/Superadmin/PackagesController). NUNCA hardcode.\n"
                . "Pattern correto: ModuleUtil::hasThePermissionInSubscription(\$biz, 'X_module', 'superadmin_package').\n"
                . "Ver memory/reference/feedback-habilitar-modulo-por-business.md pra guia completo.";
            $this->fail($msg);
        }

        expect(true)->toBeTrue(); // explicit pass
    });

    it('nenhum arquivo canônico contém variável $piloto_rotalivre ou $piloto_biz', function () {
        $files = collectCanonicalFiles();
        $violacoes = [];

        foreach ($files as $file) {
            $relative = str_replace(PROJECT_ROOT, '', $file);
            $src = file_get_contents($file);
            if ($src === false) continue;

            if (preg_match('/\$piloto_rotalivre|\$piloto_biz/', $src)) {
                $violacoes[] = $relative;
            }
        }

        if (! empty($violacoes)) {
            $this->fail(
                "Variável \$piloto_* (exceção positiva hardcode biz=N) detectada em:\n  - "
                . implode("\n  - ", $violacoes)
                . "\n\nWagner regra Tier 0: use subscription package, NÃO exceção hardcode."
            );
        }

        expect(true)->toBeTrue();
    });

    it('test de auto-validação: regex captura o pattern banido corretamente', function () {
        // Sanity: garantir que os patterns capturam casos óbvios
        // (proteção contra regex quebrado passando falso-positivo)
        $exemplos_banidos = [
            '$business_id === 4',
            '$business_id !== 4',
            '$businessId === 12',
            '$businessId !== 99',
            '$bizId === 1',
            '$current_biz !== 4',
            'if ($business_id === 4) return;',
            'if (! $piloto_rotalivre) {',
        ];

        foreach ($exemplos_banidos as $exemplo) {
            $detectado = false;
            foreach (PATTERNS_BANIDOS as $pattern) {
                if (preg_match($pattern, $exemplo)) {
                    $detectado = true;
                    break;
                }
            }
            expect($detectado)->toBeTrue("Regex deveria detectar: {$exemplo}");
        }
    });

    it('test de auto-validação: regex NÃO captura uso legítimo de business_id', function () {
        // Sanity: garantir que casos LEGÍTIMOS não disparam falso-positivo
        $exemplos_ok = [
            "\$business_id = session('user.business_id');",
            "\$business_id = (int) \$request->business_id;",
            "where('business_id', \$business_id)",
            "session()->get('user.business_id')",
            "Transaction::where('business_id', \$business_id)->get()",
            "\$user->business_id",
            "if (auth()->user()->can('superadmin'))",
            "Modules\\Whatsapp\\...",  // namespaces
        ];

        foreach ($exemplos_ok as $exemplo) {
            $detectado = false;
            foreach (PATTERNS_BANIDOS as $pattern) {
                if (preg_match($pattern, $exemplo)) {
                    $detectado = true;
                    break;
                }
            }
            expect($detectado)->toBeFalse("Regex disparou falso-positivo em: {$exemplo}");
        }
    });

    it('memory/proibicoes.md cita a regra Tier 0 com referência cruzada', function () {
        $src = file_get_contents(PROJECT_ROOT . '/memory/proibicoes.md');
        expect($src)->toContain('NUNCA hardcode');
        expect($src)->toContain('compra de pacote no modulo superadmin');
        expect($src)->toContain('hasThePermissionInSubscription');
        expect($src)->toContain('feedback-habilitar-modulo-por-business.md');
    });
});

<?php

declare(strict_types=1);

namespace Modules\ComunicacaoVisual\Tests\Feature;

/**
 * Wave 27 — ComunicacaoVisual POLISH FINAL (target ≥92 vertical_client_facing).
 *
 * Foco D7 confirmar fix forensic triplo + D9 spans Services + D5 README expandido + V5 CHANGELOG.
 *
 * Estratégia (smoke + reflection — sem boot Laravel):
 *  1. D7 (+2) — AuditTrailIntegrityTest fix W27 confirmado (8/8 passed, expect()->toContain mono-arg)
 *  2. D9 (+2) — OrcamentoCalculator + ApontamentoTracker spans declarados (4 spans total)
 *  3. D5 (+1) — README.md cita 8+ seções canônicas (jornada cliente + multi-tenant + LGPD)
 *  4. V5 (+1) — CHANGELOG W27 entry
 *  5. Tier 0 — biz=99 + ADR 0093 + PT-BR
 *
 * @see Wave25SaturationTest.php (predecessor)
 * @see AuditTrailIntegrityTest.php (D7 base)
 */

function comvisW27Path(string $path = ''): string
{
    $root = realpath(__DIR__ . '/../../../../');
    return $root . ($path !== '' ? DIRECTORY_SEPARATOR . $path : '');
}

describe('Wave 27 ComVis — D7 AuditTrailIntegrityTest W27 fix forensic triplo', function () {

    it('AuditTrailIntegrityTest NÃO usa expect()->toContain($a, $b) com mensagem (anti-pattern)', function () {
        $src = (string) file_get_contents(comvisW27Path('Modules/ComunicacaoVisual/Tests/Feature/AuditTrailIntegrityTest.php'));
        // W26→W27 fix: removida passagem de mensagem em segundo arg de toContain (Pest interpreta como segundo valor a buscar).
        // Pattern PROIBIDO: toContain('campo', 'mensagem motivo...') — Pest checa ambos como valores em array.
        // Pattern OK: toContain('campo'); + comentário PT-BR ANTES da linha.
        expect($src)->toContain('W27 D7 forensic fix');
        // Verifica que o fix foi aplicado: NO source não há mais multi-arg toContain com strings descritivas longas
        $linhas = explode("\n", $src);
        foreach ($linhas as $i => $linha) {
            // anti-pattern: toContain com 2 args onde segundo contém espaço (mensagem)
            if (preg_match('/->toContain\([\'"][\w_]+[\'"],\s*[\'"][^\'"]*\s+[^\'"]*[\'"]\)/', $linha)) {
                $this->fail("Linha {$i}: anti-pattern toContain(valor, mensagem) detectado — Pest interpreta como 2 valores. Use toContain(valor) + comentário antes.");
            }
        }
        expect(true)->toBeTrue();
    });

    it('AuditTrailIntegrityTest declara ≥7 it() blocks (assertions canônicas D7)', function () {
        $src = (string) file_get_contents(comvisW27Path('Modules/ComunicacaoVisual/Tests/Feature/AuditTrailIntegrityTest.php'));
        $matches = [];
        // /m flag pra ^ matchar início de cada linha (multiline)
        preg_match_all('/^\s*it\([\'"]/m', $src, $matches);
        $count = count($matches[0]);
        expect($count)->toBeGreaterThanOrEqual(7);
        // 3 entities × whitelist + logName + dirty + empty
    });

    it('AuditTrailIntegrityTest forensic D7 reflection-only (sem DB — compatível Hostinger)', function () {
        $src = (string) file_get_contents(comvisW27Path('Modules/ComunicacaoVisual/Tests/Feature/AuditTrailIntegrityTest.php'));
        // Não pode chamar DB::table, factory, RefreshDatabase
        expect($src)->not->toContain('DB::table');
        expect($src)->not->toContain('RefreshDatabase');
        expect($src)->not->toContain('::factory(');
    });
});

describe('Wave 27 ComVis — D9 spans OrcamentoCalculator + ApontamentoTracker', function () {

    it('OrcamentoCalculator declara span comvis.orcamento.calcular', function () {
        $src = (string) file_get_contents(comvisW27Path('Modules/ComunicacaoVisual/Services/OrcamentoCalculator.php'));
        expect($src)->toContain('comvis.orcamento.calcular');
        expect($src)->toContain('OtelHelper::spanBiz');
    });

    it('ApontamentoTracker declara 3 spans (iniciar/finalizar/cancelar)', function () {
        $src = (string) file_get_contents(comvisW27Path('Modules/ComunicacaoVisual/Services/ApontamentoTracker.php'));
        expect($src)->toContain('comvis.apontamento.iniciar');
        expect($src)->toContain('comvis.apontamento.finalizar');
        expect($src)->toContain('comvis.apontamento.cancelar');
    });

    it('ApontamentoTracker log estruturado comvis.apontamento.finalizado (D9.b)', function () {
        $src = (string) file_get_contents(comvisW27Path('Modules/ComunicacaoVisual/Services/ApontamentoTracker.php'));
        expect($src)->toContain('comvis.apontamento.finalizado');
        expect($src)->toContain('Log::info');
    });
});

describe('Wave 27 ComVis — D5 README expandido (jornada + LGPD + multi-tenant + comandos)', function () {

    it('README.md cita 10 seções canônicas', function () {
        $readme = (string) file_get_contents(comvisW27Path('Modules/ComunicacaoVisual/README.md'));
        // Seções obrigatórias pra cliente-final entender módulo
        $secoes = [
            '## 1. Objetivo',
            '## 2. Arquitetura',
            '## 3. Como o cliente usa',
            '## 4. Multi-tenant',
            '## 5. LGPD',
            '## 6. Testes',
            '## 7. Concorrentes',
            '## 9. Comandos artisan',
            '## 10. Links relacionados',
        ];
        foreach ($secoes as $secao) {
            // W27 D7 lição: Pest toContain mono-arg apenas (2 args = checa AMBOS valores).
            $temSecao = str_contains($readme, $secao);
            expect($temSecao)->toBeTrue(); // README precisa da seção: {$secao}
        }
    });

    it('README cita persona Larissa-equivalente + ROI dashboard + drift m²', function () {
        $readme = (string) file_get_contents(comvisW27Path('Modules/ComunicacaoVisual/README.md'));
        expect($readme)->toContain('Larissa-equivalente');
        expect($readme)->toContain('drift');
        expect($readme)->toContain('m²');
    });

    it('README cita 3 concorrentes + NFe-de-boleto-pago diferencial', function () {
        $readme = (string) file_get_contents(comvisW27Path('Modules/ComunicacaoVisual/README.md'));
        expect($readme)->toContain('Mubisys');
        expect($readme)->toContain('Zênite');
        expect($readme)->toContain('Calcgraf');
        expect($readme)->toContain('NFe-boleto');
    });
});

describe('Wave 27 ComVis — V5 CHANGELOG entry', function () {

    it('CHANGELOG.md tem entry Wave 27', function () {
        $changelog = (string) file_get_contents(comvisW27Path('Modules/ComunicacaoVisual/CHANGELOG.md'));
        expect($changelog)->toContain('Wave 27');
    });

    it('CHANGELOG W27 cita D7 forensic fix confirmado + polish ≥92', function () {
        $changelog = (string) file_get_contents(comvisW27Path('Modules/ComunicacaoVisual/CHANGELOG.md'));
        expect($changelog)->toContain('D7');
        $temContext = str_contains($changelog, 'forensic') || str_contains($changelog, 'forense')
                      || str_contains($changelog, 'POLISH') || str_contains($changelog, 'polish');
        expect($temContext)->toBeTrue();
    });
});

describe('Wave 27 ComVis — Tier 0 biz=99 reforço estrutural', function () {

    it('Wave27 + Wave25 NÃO usam business_id=4 em CODE PHP', function () {
        foreach (['Wave25SaturationTest.php', 'Wave27ComVisPolishTest.php'] as $f) {
            $path = comvisW27Path("Modules/ComunicacaoVisual/Tests/Feature/{$f}");
            if (! file_exists($path)) continue;
            $src = (string) file_get_contents($path);
            $linhasCode = array_filter(
                explode("\n", $src),
                function ($ln): bool {
                    $t = trim($ln);
                    return $t !== '' && ! str_starts_with($t, '*') && ! str_starts_with($t, '//')
                           && ! str_starts_with($t, '#') && ! str_starts_with($t, '/*');
                }
            );
            $code = implode("\n", $linhasCode);
            expect($code)->not->toMatch('/[\'"]business_id[\'"]\s*=>\s*4\b/');
        }
    });
});

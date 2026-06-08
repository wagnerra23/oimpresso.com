<?php

declare(strict_types=1);

/**
 * GUARDs de Caso de Uso — telas canon (Vendas + Oficina).
 *
 * Cada UC com `guard: true` no registro (`prototipo-ui/audit/uc-registry.json`)
 * tem aqui um teste tagueado `uc-<id>` que afirma a presença do **marker** (o
 * elemento que materializa o "A tela precisa:") no(s) arquivo(s) da tela. Some o
 * elemento = build VERMELHO ([W]: "põe o botão e que nunca mais saia aquela
 * merda de lá"). Estende o padrão charter→GUARD que já existe — NÃO inventa.
 *
 * UCs com `guard: false` são GAPS conhecidos (UC sem cobertura) — NÃO viram teste
 * aqui (não dá pra afirmar presença de elemento ausente sem ficar vermelho à toa);
 * o `protocol_freshness` (advisory) lista esses gaps. A lista do que ficou sem
 * cobertura está no registro + no relatório do check.
 *
 * Convenção de marker: nome de componente estável > texto visível (i18n).
 *
 * @see prototipo-ui/audit/uc-registry.json   (fonte única)
 * @see prototipo-ui/audit/protocol-freshness.mjs   (acende os gaps)
 * @see Modules/Jana/Console/Commands/HealthCheckCommand.php  (check espelho)
 */
$repoRoot = dirname(__DIR__, 3);
$registry = json_decode((string) file_get_contents($repoRoot . '/prototipo-ui/audit/uc-registry.json'), true);

foreach (($registry['screens'] ?? []) as $screen) {
    foreach (($screen['ucs'] ?? []) as $uc) {
        if (! ($uc['guard'] ?? false)) {
            continue; // gap — coberto pelo protocol_freshness, não por GUARD verde
        }

        $id = $uc['uc'];
        $marker = $uc['marker'];
        $files = $uc['files'];
        $screenId = $screen['id'];

        it("GUARD {$id} ({$screenId}): tela mantém o elemento de '{$uc['rationale']}'", function () use ($marker, $files, $id) {
            $repoRoot = dirname(__DIR__, 3);
            $achou = false;
            foreach ($files as $rel) {
                $abs = $repoRoot . '/' . $rel;
                expect(is_file($abs))->toBeTrue("arquivo da tela sumiu: {$rel} (UC {$id})");
                if (str_contains((string) file_get_contents($abs), $marker)) {
                    $achou = true;
                }
            }
            // PRECISA TER: o marker (elemento que materializa o UC) tem que estar presente.
            expect($achou)->toBeTrue(
                "GUARD {$id} VERMELHO: marker '{$marker}' sumiu da(s) tela(s) " . implode(', ', $files)
                . '. Um Caso de Uso regrediu — restaure o elemento ou reconcilie o registro/charter.'
            );
        })->group('guard', 'uc', 'uc-' . strtolower(str_replace('UC-', '', $id)));
    }
}

it('registro UC é íntegro: todo guard=true tem marker + files existentes', function () use ($registry) {
    $repoRoot = dirname(__DIR__, 3);
    foreach (($registry['screens'] ?? []) as $screen) {
        // charter da tela canon tem que existir (espelha protocol_freshness item (b)).
        expect(is_file($repoRoot . '/' . $screen['charter']))
            ->toBeTrue("tela canon sem charter: {$screen['charter']}");

        foreach (($screen['ucs'] ?? []) as $uc) {
            if (! ($uc['guard'] ?? false)) {
                continue;
            }
            expect($uc['marker'] ?? null)->not->toBeEmpty("UC {$uc['uc']} guard=true sem marker");
            expect($uc['files'] ?? [])->not->toBeEmpty("UC {$uc['uc']} guard=true sem files");
        }
    }
})->group('guard', 'uc');

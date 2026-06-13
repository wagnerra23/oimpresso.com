<?php

declare(strict_types=1);

/**
 * Onda M1 (maturidade do DS) · sub-item (a): mata a autocontradição da camada canônica.
 *
 * O sênior achou a "arma fumegante": `ui/badge.tsx` + `shared/KpiCard.tsx` +
 * `shared/EmptyState.tsx` hardcodavam paleta crua (emerald/amber/rose/sky/blue-NNN)
 * — exatamente o que a regra `ds/no-adhoc-status-text` proíbe nas Pages. A camada
 * canônica exigia das telas um DS que ela mesma NÃO consumia.
 *
 * Fix: variantes de STATUS passam a consumir os pares semânticos do `inertia.css`
 * (`-soft/-fg` pra pills soft do badge; base `success/warning/info` + opacidade pros
 * tints do KpiCard/EmptyState). O token carrega light+dark → sem `dark:` cru.
 *
 * Canon: structural guard. Prova visual (pills idênticas) = smoke pós-deploy.
 * NÃO cobre StatusBadge.tsx (mapa sólido + nuance orange) nem VendaDerivadaCard.tsx
 * (card autoral) — ondas seguintes de M1, escopo deliberadamente separado.
 */

$ui = __DIR__ . '/../../../resources/js/Components/ui';
$shared = __DIR__ . '/../../../resources/js/Components/shared';

test('badge — pills de status consomem tokens -soft/-fg (não paleta crua)', function () use ($ui) {
    $src = file_get_contents($ui . '/badge.tsx');
    expect($src)
        ->toContain('bg-success-soft text-success-fg border-success/20')
        ->toContain('bg-warning-soft text-warning-fg border-warning/20')
        ->toContain('bg-destructive-soft text-destructive-fg border-destructive/20')
        ->toContain('bg-info-soft text-info-fg border-info/20')
        // a paleta crua antiga saiu por completo das variantes de status
        ->not->toContain('bg-emerald-50')
        ->not->toContain('bg-amber-50')
        ->not->toContain('bg-rose-50')
        ->not->toContain('bg-sky-50');
});

test('KpiCard — tones e ícone consomem tokens semânticos (não emerald/amber/blue)', function () use ($shared) {
    $src = file_get_contents($shared . '/KpiCard.tsx');
    expect($src)
        ->toContain('border-success/20 bg-success/5')
        ->toContain('border-warning/20 bg-warning/5')
        ->toContain('border-info/20 bg-info/5')
        ->toContain('bg-success/10 text-success')
        ->toContain('bg-info/10 text-info')
        // delta positivo via token
        ->toContain("? 'text-success'")
        ->not->toContain('emerald-500')
        ->not->toContain('amber-500')
        ->not->toContain('blue-500');
});

test('EmptyState — variantes search/success consomem info/success token', function () use ($shared) {
    $src = file_get_contents($shared . '/EmptyState.tsx');
    expect($src)
        ->toContain("icon: 'text-info'")
        ->toContain("iconBg: 'bg-info/10'")
        ->toContain("icon: 'text-success'")
        ->toContain("iconBg: 'bg-success/10'")
        ->not->toContain('text-blue-600')
        ->not->toContain('text-emerald-600');
});

test('StatusBadge — mapa de status app-wide consome success/warning/info (não emerald/amber/blue/orange)', function () use ($shared) {
    $src = file_get_contents($shared . '/StatusBadge.tsx');
    expect($src)
        ->toContain('bg-success text-success-foreground hover:bg-success/90')
        ->toContain('bg-warning text-warning-foreground hover:bg-warning/90')
        ->toContain('bg-info text-info-foreground hover:bg-info/90')
        // ramp de severidade: orange "Risco Alto" virou destructive; Crítico pulsa (ápice distinto)
        ->toContain("Alto:    { variant: 'destructive', label: 'Risco Alto' }")
        ->toContain("Crítico: { variant: 'destructive', label: 'Risco Crítico', className: 'animate-pulse' }")
        ->not->toContain('bg-orange-600')
        ->not->toContain('bg-emerald-600')
        ->not->toContain('bg-amber-600')
        ->not->toContain('bg-blue-600');
});

test('camada canônica de status — zero paleta crua nos 4 arquivos', function () use ($ui, $shared) {
    $files = [$ui . '/badge.tsx', $shared . '/KpiCard.tsx', $shared . '/EmptyState.tsx', $shared . '/StatusBadge.tsx'];
    foreach ($files as $f) {
        $src = file_get_contents($f);
        // shades numéricos de paleta crua (emerald-500, sky-50, blue-600…) = 0
        expect(preg_match('/(emerald|amber|rose|sky|blue|red|green|yellow|orange|teal|lime|indigo|violet)-(50|100|200|300|400|500|600|700|800|900|950)/', $src))
            ->toBe(0, "paleta crua encontrada em " . basename($f));
    }
});

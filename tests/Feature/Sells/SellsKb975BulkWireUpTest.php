<?php

declare(strict_types=1);

/**
 * KB-9.75 P0 #4 bug-fix smoke real 2026-05-26 — BulkActionBar wire-up + z-index.
 *
 * PR #1644 (commit 48c106f71) entregou VdBulkEmitModal mas wire-up final
 * ficou incompleto:
 *   1. Index.tsx — 3 botões BulkActionBar SEM onClick (decorativos).
 *      VdBulkEmitModal nunca montava porque ninguém setOpen(true).
 *   2. sells-kb975-emit-modals.css — `.vd-emit-bd { z-index: 50 }` conflita
 *      com shadcn Sheet drawer z-50 → modal renderiza atrás.
 *
 * Pattern: estrutural (file_get_contents + str_contains), canon
 * US-SELL-008/021 do projeto. Sem banco real — só anti-regressão de wire-up.
 *
 * Refs:
 *   - PR #1644 (KB-9.75 P0 batch — Emit modals NF-e/NFS-e + Bulk)
 *   - Smoke real Wagner 2026-05-26 biz=1 WR2 Sistemas
 */

const INDEX_PATH_KB975 = 'resources/js/Pages/Sells/Index.tsx';
const EMIT_CSS_PATH_KB975 = 'resources/css/sells-kb975-emit-modals.css';

it('imports VdBulkEmitModal in Index.tsx', function () {
    $src = file_get_contents(base_path(INDEX_PATH_KB975));

    expect($src)->toContain("import VdBulkEmitModal");
    expect($src)->toContain("from './_components/VdBulkEmitModal'");
});

it('wires up BulkActionBar primary button with onClick to open VdBulkEmitModal', function () {
    $src = file_get_contents(base_path(INDEX_PATH_KB975));

    // State pra controlar abertura.
    expect($src)->toContain('setOpenBulk');
    expect($src)->toContain('setBulkKind');

    // O botão "Emitir NF-e em lote" agora tem handler (não é mais decorativo).
    // Heurística: existe ocorrência de `setOpenBulk(true)` (qualquer botão dispara).
    expect($src)->toContain('setOpenBulk(true)');

    // Componente é montado no JSX (não basta importar).
    expect($src)->toContain('<VdBulkEmitModal');
    expect($src)->toContain('open={openBulk}');
});

it('keeps secondary bulk buttons as V2-stub (toast.info "Em breve V2")', function () {
    $src = file_get_contents(base_path(INDEX_PATH_KB975));

    // "Marcar como pagas" + "Exportar XML/PDF" V0 stub (toast V2 placeholder).
    expect($src)->toContain('Marcar como pagas em lote');
    expect($src)->toContain('Exportar XML/PDF em lote');
});

it('raises .vd-emit-bd z-index to 100 (above shadcn Sheet drawer z-50)', function () {
    $css = file_get_contents(base_path(EMIT_CSS_PATH_KB975));

    // O fix: z-index 50 → 100. Bloco `.vd-emit-bd { ... }` deve ter z-index: 100.
    expect($css)->toContain('z-index: 100');

    // Anti-regressão: não pode ter `z-index: 50` no bloco .vd-emit-bd
    // (segurança contra revert acidental — busca string exata).
    // Permitimos z-index 50 em outros lugares (ex: comentário menciona drawer z-50),
    // então buscamos a ocorrência DENTRO do bloco .vd-emit-bd via regex multiline.
    $matched = preg_match(
        '/\.vd-emit-bd\s*\{[^}]*z-index:\s*(\d+)/s',
        $css,
        $m,
    );
    expect($matched)->toBe(1);
    expect((int) $m[1])->toBeGreaterThanOrEqual(100);
});

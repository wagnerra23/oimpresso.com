<?php

declare(strict_types=1);

use Modules\Arquivos\Concerns\HasArquivos;
use Modules\Whatsapp\Entities\Message;

/**
 * R-ARQ-WA-TRAIT — anti-regressão Sprint 1 ADR 0214.
 *
 * Garante que Message implementa trait HasArquivos do backbone Arquivos
 * (ADR 0123 §4 polimorfismo). Sem este trait, msg WhatsApp não pode
 * anexar mídia via Arquivos backbone — fica isolada do DMS canon.
 *
 * Smoke real do dual-write (attachArquivo durante DownloadMediaJob)
 * fica em test E2E separado pós Sprint 0.4 (MinIO live).
 */

it('R-ARQ-WA-TRAIT-001 — Message usa trait HasArquivos', function () {
    $traits = class_uses_recursive(Message::class);
    expect($traits)->toContain(HasArquivos::class);
});

it('R-ARQ-WA-TRAIT-002 — Message tem método arquivos() (relação polimórfica)', function () {
    expect(method_exists(Message::class, 'arquivos'))->toBeTrue();
});

it('R-ARQ-WA-TRAIT-003 — Message tem método attachArquivo()', function () {
    expect(method_exists(Message::class, 'attachArquivo'))->toBeTrue();
});

it('R-ARQ-WA-TRAIT-004 — Message tem método arquivosClassificados() (filtro bucket)', function () {
    expect(method_exists(Message::class, 'arquivosClassificados'))->toBeTrue();
});

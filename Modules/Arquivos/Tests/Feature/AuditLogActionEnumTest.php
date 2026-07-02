<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Contrato do ENUM `arquivos_audit_log.action` (ADR 0123 §8 — audit integral).
 *
 * Guarda a CLASSE de bug do code review 2026-07-02: um call-site gravar uma action
 * que o enum não aceita → INSERT falha em MySQL strict mode → audit engolido pelo
 * try/catch. Contrato: toda action escrita por código-vivo é membro do enum.
 *
 * MySQL-only: introspection de ENUM via SHOW COLUMNS (SQLite não tem enum real).
 *
 * @see Modules/Arquivos/Http/Controllers/DownloadController.php
 * @see Modules/Arquivos/Services/ArquivosService.php
 * @see Modules/Arquivos/Console/Commands/RetentionCleanupCommand.php
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite não introspecta ENUM — contrato roda em MySQL (CT 100).');
    }
    if (! Schema::hasTable('arquivos_audit_log')) {
        $this->markTestSkipped('arquivos_audit_log ausente — rodar migrate primeiro.');
    }
});

/**
 * Valores do ENUM `action` extraídos direto do schema MySQL.
 *
 * @return list<string>
 */
function arquivosAuditEnumValues(): array
{
    $col = DB::selectOne("SHOW COLUMNS FROM arquivos_audit_log LIKE 'action'");
    preg_match_all("/'([^']+)'/", (string) $col->Type, $m);

    return $m[1];
}

it('enum action contém signed_url_consumed (regressão bug 2026-07-02)', function () {
    expect(arquivosAuditEnumValues())->toContain('signed_url_consumed');
});

it('toda action gravada por call-site é membro do enum (contrato no-orphan)', function () {
    // Actions realmente gravadas hoje (grep `$this->audit(` + `'action' =>`):
    //   upload / reclassify / signed_url_issued / soft_delete / restore → ArquivosService
    //   hard_delete                                                     → RetentionCleanupCommand
    //   signed_url_consumed                                             → DownloadController
    // (Os valores `download` e `classify` existem no enum mas NÃO têm call-site —
    //  reservados/legado; não fazem parte do contrato no-orphan, apenas os vivos.)
    $liveActions = [
        'upload', 'reclassify', 'signed_url_issued', 'soft_delete',
        'restore', 'hard_delete', 'signed_url_consumed',
    ];

    $enum = arquivosAuditEnumValues();

    foreach ($liveActions as $action) {
        expect($enum)->toContain($action);
    }
});

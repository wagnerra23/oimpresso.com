<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Tests\Support\WithSeededTenant;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use WithSeededTenant;

    protected function setUp(): void
    {
        parent::setUp();

        // US-GOV-018 Frente A (A.2) — FK-off no nightly full-suite, e SO no nightly.
        // No nightly a suite roda contra MySQL PERSISTENTE sem rollback por teste; ~210
        // testes era-sqlite fazem Schema::dropIfExists() em beforeEach/setUp. Tabelas
        // referenciadoras (ex whatsapp_jana_correcoes -> messages) sobrevivem entre
        // testes, e dropar a tabela-pai estoura errno 3730 "Cannot drop ... referenced
        // by FK" (508 ocorrencias no run 20260613-003042). Desligar FK por-conexao
        // (var de SESSAO MySQL, morre com a conexao) torna o teardown DDL FK-safe —
        // restaura o comportamento historico (sqlite ignora FK por default).
        //
        // Escopado ao nightly via env FULLSUITE_FK_OFF=1 (setado em ct100-fullsuite.sh)
        // de proposito: os gates de CI required NAO setam a flag, entao FK segue ON la
        // e NENHUM bug de FK fica mascarado nos testes que importam. No-op em sqlite.
        if (getenv('FULLSUITE_FK_OFF') === '1') {
            $conn = DB::connection();
            if (in_array($conn->getDriverName(), ['mysql', 'mariadb'], true)) {
                $conn->statement('SET SESSION FOREIGN_KEY_CHECKS=0');
            }
        }
    }
}

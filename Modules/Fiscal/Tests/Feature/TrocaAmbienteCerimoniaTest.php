<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * UC-FCFG-07 — a cerimônia da troca de ambiente SEFAZ.
 *
 * ÂNCORA DE CONTRATO (não deriva do código): charter `Fiscal/Config`, decisão [W]
 * 2026-08-24 — "trocar ambiente exige duas provas: o nome do destino digitado à
 * mão E um motivo de 15+ caracteres" + "toda troca vira evento de auditoria".
 *
 * POR QUE A CERIMÔNIA EXISTE: o ambiente decide se a nota tem valor fiscal.
 * Empresa que passa a emitir em homologação sem perceber produz dias de nota sem
 * valor. Confirmação de uma palavra genérica ("sim", "ok") não segura isso.
 *
 * O QUE ESTE ARQUIVO NÃO CONFUNDE: recusar não basta. Um endpoint quebrado que
 * NUNCA troca passaria em todos os casos negativos. Por isso o caso positivo
 * prova a troca REAL, com antes→depois lido do banco.
 *
 * TENANT: biz=1, como os irmãos deste diretório (`GatesPermissaoFiscalTest`,
 * `ConfigControllerTest`). A ADR 0358 aponta o tenant fictício 98 como canônico,
 * mas o seed das lanes provisiona biz=1/biz=2 — migrar o tenant deste módulo é
 * intent próprio, não carona neste PR. biz=4 (cliente real) segue proibido.
 *
 * ONDE RODA: `Modules/Fiscal/Tests` na matrix do modules-pest.yml (SQLite — SKIPa
 * aqui) + suíte noturna CT 100 (MySQL real). Não bloqueia merge.
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('business') || ! Schema::hasColumn('business', 'ambiente')) {
        $this->markTestSkipped('business.ambiente ausente');
    }
});

/** Superadmin biz=1 — passa o gate `fiscal.config.ambiente` (PR 2/3). */
function usuarioQuePassaNoGateAmbiente(): \App\User
{
    $u = \App\User::factory()->create(['business_id' => 1]);
    $u->givePermissionTo('superadmin');

    return $u;
}

function ambienteGravado(): int
{
    return (int) (DB::table('business')->where('id', 1)->value('ambiente') ?? 2);
}

it('UC-FCFG-07 · sem confirmação e sem motivo, o ambiente NÃO muda', function () {
    $this->actingAs(usuarioQuePassaNoGateAmbiente());
    session(['business.id' => 1, 'user.business_id' => 1]);

    $antes = ambienteGravado();
    $destino = $antes === 1 ? 2 : 1;

    $this->post('/nfe-brasil/configuracao/certificado/ambiente', ['ambiente' => $destino])
        ->assertSessionHasErrors(['motivo', 'confirmacao']);

    expect(ambienteGravado())->toBe($antes, 'pedido sem cerimônia não pode ter mexido no ambiente');
});

it('UC-FCFG-07 · confirmação que não bate deixa o ambiente inalterado', function () {
    $this->actingAs(usuarioQuePassaNoGateAmbiente());
    session(['business.id' => 1, 'user.business_id' => 1]);

    $antes = ambienteGravado();
    $destino = $antes === 1 ? 2 : 1;

    // "sim" é exatamente a confirmação genérica que a cerimônia existe pra recusar.
    $this->post('/nfe-brasil/configuracao/certificado/ambiente', [
        'ambiente'    => $destino,
        'motivo'      => 'motivo suficientemente longo para passar no minimo',
        'confirmacao' => 'sim',
    ])->assertSessionHasErrors('confirmacao');

    expect(ambienteGravado())->toBe($antes, 'confirmação errada NÃO pode trocar o ambiente');
});

it('UC-FCFG-07 · motivo curto demais deixa o ambiente inalterado', function () {
    $this->actingAs(usuarioQuePassaNoGateAmbiente());
    session(['business.id' => 1, 'user.business_id' => 1]);

    $antes = ambienteGravado();
    $destino = $antes === 1 ? 2 : 1;
    $rotulo = $destino === 1 ? 'PRODUCAO' : 'HOMOLOGACAO';

    $this->post('/nfe-brasil/configuracao/certificado/ambiente', [
        'ambiente'    => $destino,
        'motivo'      => 'teste',   // 5 caracteres
        'confirmacao' => $rotulo,
    ])->assertSessionHasErrors('motivo');

    expect(ambienteGravado())->toBe($antes, 'motivo curto NÃO pode trocar o ambiente');
});

it('UC-FCFG-07 · com as duas provas a troca acontece E vira evento com o motivo', function () {
    $this->actingAs(usuarioQuePassaNoGateAmbiente());
    session(['business.id' => 1, 'user.business_id' => 1]);

    $antes = ambienteGravado();
    $destino = $antes === 1 ? 2 : 1;
    // Sem acento de propósito: o servidor normaliza (a fricção é ESCREVER a
    // palavra, não acertar o cedilha). Este caso também prova essa tolerância.
    $rotulo = $destino === 1 ? 'producao' : 'homologacao';
    $motivo = 'fim dos testes de homologacao — liberado pelo contador';

    try {
        $this->post('/nfe-brasil/configuracao/certificado/ambiente', [
            'ambiente'    => $destino,
            'motivo'      => $motivo,
            'confirmacao' => $rotulo,
        ])->assertSessionHasNoErrors();

        // CONTROLE POSITIVO — sem ele, os 3 casos negativos acima passariam
        // igualzinho num endpoint quebrado que nunca troca nada.
        expect(ambienteGravado())->toBe($destino, 'a troca legítima TEM de acontecer');

        // A trilha responde "quem, quando e POR QUÊ". Sem o motivo no evento,
        // ninguém explica depois por que a nota de terça não tem valor fiscal.
        if (Schema::hasTable('activity_log')) {
            $evento = DB::table('activity_log')
                ->where('log_name', 'nfe.certificado')
                ->where('description', 'certificado.ambiente_alterado')
                ->orderByDesc('id')
                ->first();

            expect($evento)->not->toBeNull('a troca tem de deixar evento de auditoria');

            $props = json_decode((string) $evento->properties, true) ?: [];
            expect($props['ambiente_de'] ?? null)->toBe($antes)
                ->and($props['ambiente_para'] ?? null)->toBe($destino)
                ->and($props['motivo'] ?? null)->toBe($motivo);
            expect($evento->causer_id)->not->toBeNull('o evento tem de saber QUEM trocou');
        }
    } finally {
        // O banco do CT 100 PERSISTE entre corridas — deixar biz=1 no ambiente
        // trocado contaminaria a próxima suíte. Restaura mesmo se algo acima falhar.
        DB::table('business')->where('id', 1)->update(['ambiente' => $antes]);
    }

    expect(ambienteGravado())->toBe($antes, 'o teste não pode deixar resíduo');
});

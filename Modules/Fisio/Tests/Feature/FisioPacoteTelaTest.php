<?php

declare(strict_types=1);
// @covers-us US-FISIO-021
// Cobre UC-FPK-01..06 e UC-FPK-10 (Fisio/Pacientes/Pacote.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Financeiro\Models\Titulo;
use Modules\Fisio\Entities\Pacote;
use Modules\Fisio\Services\Pacotes\SaldoDoPacote;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Aba "Pacote e cobrança" (US-FISIO-021). Sessões são do Fisio; título é LEITURA do Financeiro
 * pela venda ligada ao pacote. Tenant 98 contra 99 (ADR 0358). ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_packages', 'fisio_package_ledger', 'fin_titulos'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    F::pacote([$this->b98, $this->b99]);
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->leitor98 = $this->usuarioComPermissoes([F::PERM_ACESSO], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    $this->pac = F::paciente($this->b98, $this->u98->id);
});

/** Título a receber de uma venda, gravado direto: o teste só confere a LEITURA. */
function fptTitulo(int $biz, int $userId, int $transacao, string $numero): int
{
    // SUPERADMIN: fixture grava em duas empresas; o escopo é provado nos asserts.
    return (int) Titulo::withoutGlobalScopes()->create([
        'business_id' => $biz, 'numero' => $numero, 'tipo' => 'receber', 'status' => 'quitado',
        'valor_total' => 100, 'valor_aberto' => 0, 'moeda' => 'BRL', 'emissao' => now()->toDateString(),
        'vencimento' => now()->toDateString(), 'competencia_mes' => now()->format('Y-m'),
        'origem' => 'venda', 'origem_id' => $transacao, 'created_by' => $userId,
    ])->id;
}

it('UC-FPK-01 · abre a tela e mostra o saldo derivado do ledger', function () {
    F::como($this, $this->u98);
    $pacote = app(SaldoDoPacote::class)->abrir($this->pac, '10 sessões', 10, null, 2, $this->u98->id);
    app(SaldoDoPacote::class)->consumir($pacote, $this->u98->id, 'agendamento:1');

    $this->get("/fisio/pacientes/{$this->pac->id}/pacote")->assertOk()
        ->assertInertia(fn ($p) => $p->component('Fisio/Pacientes/Pacote', false)->where('paciente.id', $this->pac->id));

    $prop = F::props($this, $this->u98, "/fisio/pacientes/{$this->pac->id}/pacote", 'Fisio/Pacientes/Pacote', 'pacote');
    expect($prop['saldo'])->toBe(9)->and($prop['sessions_total'])->toBe(10)->and(count($prop['sessoes']))->toBe(1);
});

it('UC-FPK-02 · abrir pacote e registrar sessão pela tela; sem saldo, erro e nada lançado', function () {
    F::como($this, $this->u98);
    $this->post("/fisio/pacientes/{$this->pac->id}/pacotes", ['title' => 'Avulsa', 'sessions_total' => 1])->assertRedirect();
    $pacote = Pacote::query()->where('patient_id', $this->pac->id)->firstOrFail();

    $this->post("/fisio/pacotes/{$pacote->id}/consumir")->assertRedirect()->assertSessionHasNoErrors();
    $this->post("/fisio/pacotes/{$pacote->id}/consumir")->assertSessionHasErrors('pacote');

    expect(app(SaldoDoPacote::class)->saldo($pacote))->toBe(0);
});

it('UC-FPK-03 · sem fisio.editar: vê a tela, mas não abre pacote nem registra sessão (403)', function () {
    F::como($this, $this->leitor98);
    $this->get("/fisio/pacientes/{$this->pac->id}/pacote")->assertOk();
    $this->post("/fisio/pacientes/{$this->pac->id}/pacotes", ['title' => 'X', 'sessions_total' => 5])->assertForbidden();

    expect(Pacote::query()->where('patient_id', $this->pac->id)->count())->toBe(0);
});

it('UC-FPK-04 · 98 contra 99: paciente e pacote da 99 dão 404 para a 98', function () {
    F::como($this, $this->u99);
    $pac99 = F::paciente($this->b99, $this->u99->id);
    $pacote99 = app(SaldoDoPacote::class)->abrir($pac99, 'Da 99', 4, null, null, $this->u99->id);

    F::como($this, $this->u98);
    $this->get("/fisio/pacientes/{$pac99->id}/pacote")->assertNotFound();
    $this->post("/fisio/pacotes/{$pacote99->id}/consumir")->assertNotFound();

    // Controle negativo: a 99 enxerga o próprio pacote, com o saldo intacto.
    F::como($this, $this->u99);
    $this->get("/fisio/pacientes/{$pac99->id}/pacote")->assertOk();
    expect(app(SaldoDoPacote::class)->saldo($pacote99))->toBe(4);
});

it('UC-FPK-05 · cobrança lê os títulos da venda do pacote, só da empresa, e a tela não grava título', function () {
    F::como($this, $this->u98);
    $pacote = app(SaldoDoPacote::class)->abrir($this->pac, '10 sessões', 10, null, null, $this->u98->id);
    $transacao = 900000 + random_int(1, 99999);
    $pacote->update(['transaction_id' => $transacao]);
    $meu = fptTitulo($this->b98, $this->u98->id, $transacao, 'FPT-98-'.uniqid());
    fptTitulo($this->b99, $this->u99->id, $transacao, 'FPT-99-'.uniqid());
    $antes = DB::table('fin_titulos')->count();

    $titulos = F::props($this, $this->u98, "/fisio/pacientes/{$this->pac->id}/pacote", 'Fisio/Pacientes/Pacote', 'cobranca');

    expect(array_column($titulos, 'id'))->toBe([$meu])
        ->and(DB::table('fin_titulos')->count())->toBe($antes);
});

it('UC-FPK-06 · pacote sem venda ligada: cobrança vazia e sem nota, sem erro', function () {
    F::como($this, $this->u98);
    app(SaldoDoPacote::class)->abrir($this->pac, '5 sessões', 5, null, null, $this->u98->id);

    expect(F::props($this, $this->u98, "/fisio/pacientes/{$this->pac->id}/pacote", 'Fisio/Pacientes/Pacote', 'cobranca'))->toBe([]);
});

it('UC-FPK-10 · comprovante do pacote em PDF: só sessões, sem valor; quem vê imprime; outra empresa 404 sem log', function () {
    $saldo = app(\Modules\Fisio\Services\Pacotes\SaldoDoPacote::class);
    $pacote = $saldo->abrir($this->pac, '10 sessões de fisioterapia', 10, now()->addMonths(2)->toDateString(), 2, $this->u98->id);
    $saldo->consumir($pacote, $this->u98->id, 'agendamento:1');
    $devolver = $saldo->consumir($pacote, $this->u98->id, 'agendamento:2');
    $saldo->estornar($devolver, $this->u98->id, 'faltou com aviso');
    $saldo->consumir($pacote, $this->u98->id, 'agendamento:3');

    F::como($this, $this->u98);
    $d = app(\Modules\Fisio\Services\Pacotes\ComprovantePacote::class)->montar($pacote->fresh(), $this->b98);
    expect($d['contratadas'])->toBe(10)->and($d['usadas'])->toBe(2)->and($d['saldo'])->toBe(8)
        ->and(array_column($d['sessoes'], 'devolvida'))->toBe([false, true, false])
        ->and($d['venda'])->toBeNull()->and($d['vencido'])->toBeFalse()->and($d['encerrado'])->toBeFalse();
    $html = view('fisio::pdf.comprovante-pacote', $d)->render();
    expect($html)->toContain('10 sessões de fisioterapia')
        ->and($html)->toContain('Não é recibo de pagamento nem nota fiscal')
        ->and($html)->toContain('Pacote nº '.$pacote->id)
        ->and($html)->toContain('devolvida ao pacote')
        ->and($html)->not->toContain('R$');

    // Quem só vê a ficha (fisio.access) imprime: a rota devolve o PDF e registra só ids.
    F::como($this, $this->leitor98);
    $r = $this->get("/fisio/pacotes/{$pacote->id}/comprovante.pdf")->assertOk();
    expect($r->headers->get('Content-Type'))->toContain('application/pdf')
        ->and(substr((string) $r->getContent(), 0, 5))->toBe('%PDF-');
    $log = DB::table('activity_log')->where('description', 'pacote_comprovante_impresso')->where('subject_id', $this->pac->id)->sole();
    expect(json_decode((string) $log->properties, true))->toEqual(['business_id' => $this->b98, 'patient_id' => $this->pac->id, 'package_id' => $pacote->id]);

    // A 99 não imprime o pacote da 98, e nada vai ao log.
    F::como($this, $this->u99);
    $this->get("/fisio/pacotes/{$pacote->id}/comprovante.pdf")->assertNotFound();
    expect(DB::table('activity_log')->where('description', 'pacote_comprovante_impresso')->where('subject_id', $this->pac->id)->count())->toBe(1);
});

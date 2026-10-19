<?php

declare(strict_types=1);
// @covers-us US-FISIO-007
// Cobre UC-FPP-19, UC-FPP-20 (Fisio/Public/Portal.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Anvisa — não virar SaMD (ADR 0437, handoff §Regras duras): o software NÃO decide conduta
 * para o paciente. Com dor ≥ limiar do programa (padrão 7) ou sinal de alerta, a resposta do
 * portal traz SÓ a `stop_instruction` escrita pelo profissional. Gráfico/série de dor é só do
 * profissional — o payload do portal não carrega histórico de dor.
 *
 * Tenant 98. ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip()) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    Carbon::setTestNow('2026-10-14 19:00:00');
    $this->b98 = (int) $this->seededTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    F::pacote([$this->b98]);
    $this->p = F::paciente($this->b98, $this->u98->id);
    $this->instrucao = 'Interrompa os exercícios hoje. Gelo 15 min. Me chame no WhatsApp da clínica.';
    $this->prog = F::programa($this->p, [F::exercicio(null, 'Ponte')], $this->u98->id, ['stop_instruction' => $this->instrucao]);
    $this->token = F::portalPronto($this, $this->p);
});

afterEach(fn () => Carbon::setTestNow());

function fpsCheckin($t, ?int $dor, array $flags = [])
{
    return $t->postJson('/fisio/p/'.$t->token.'/checkin', [
        'done_item_ids' => F::itens($t->prog), 'pain' => $dor, 'effort' => 'adequado', 'red_flags' => $flags,
    ]);
}

/** A resposta em modo pausa traz a instrução do profissional e NADA calculado pelo software. */
function fpsSoInstrucao($resposta, string $instrucao): void
{
    $json = $resposta->json();
    expect($json['modo'])->toBe('pausa')
        ->and($json['instrucao'])->toBe($instrucao)
        ->and(array_keys($json))->toEqualCanonicalizing(['modo', 'instrucao', 'checkin_id'])
        ->and(json_encode($json))->not->toMatch('/troque|reduza|sugerimos|recomendamos/i');
}

dataset('gatilhos de pausa', [
    'dor igual ao limiar (7)' => [7, []],
    'dor máxima' => [10, []],
    'dor baixa com sinal de alerta' => [2, ['dormencia_progressiva']],
    'sem dor informada com sinal de alerta' => [null, ['queda']],
]);

it('UC-FPP-19 · dor ≥ limiar ou sinal de alerta → só a instrução escrita pelo profissional', function (?int $dor, array $flags) {
    fpsSoInstrucao(fpsCheckin($this, $dor, $flags)->assertCreated(), $this->instrucao);

    $props = F::portalProps($this, $this->token);
    expect($props['modo'])->toBe('pausa')
        ->and($props['instrucao'])->toBe($this->instrucao)
        ->and($props)->not->toHaveKey('hoje');   // não oferece "faça mais" no dia de pausa
})->with('gatilhos de pausa');

it('UC-FPP-19 · abaixo do limiar e sem sinal: modo normal, sem instrução de parada (controle negativo)', function () {
    $r = fpsCheckin($this, 6)->assertCreated();

    expect($r->json('modo'))->toBe('normal')
        ->and($r->json())->not->toHaveKey('instrucao')
        ->and(F::portalProps($this, $this->token)['modo'])->toBe('normal');
});

it('UC-FPP-19 · o limiar é o do programa quando o profissional define outro', function () {
    $this->prog->update(['pain_alert_threshold' => 5]);

    fpsSoInstrucao(fpsCheckin($this, 5)->assertCreated(), $this->instrucao);
});

it('UC-FPP-20 · o portal não expõe série histórica de dor (gráfico é só do profissional)', function () {
    foreach (['2026-10-08' => 6, '2026-10-10' => 4, '2026-10-12' => 8] as $d => $dor) {
        F::checkin($this->p, $this->prog, $d, F::itens($this->prog), $dor);
    }
    fpsCheckin($this, 3)->assertCreated();

    $props = F::portalProps($this, $this->token);
    $plano = json_encode($props);
    expect($props)->not->toHaveKey('dor')->not->toHaveKey('historico_dor')->not->toHaveKey('serie_dor')
        ->and($plano)->not->toContain('"pain"')
        ->and($plano)->not->toContain('2026-10-08')
        ->and($plano)->not->toContain('2026-10-12');

    // Controle negativo: o profissional vê a série na ficha.
    $serie = F::props($this, $this->u98, "/fisio/pacientes/{$this->p->id}", 'Fisio/Pacientes/Show', 'serie_dor');
    expect(collect($serie)->pluck('pain')->map('intval')->all())->toBe([6, 4, 8, 3]);
});

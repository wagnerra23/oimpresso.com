<?php

declare(strict_types=1);

// @covers-us R-FISC-040 — fechamento do mês: notas fecham e rejeitadas pendentes com ação (thread 28).
// @covers-us R-FISC-041 — sublimite avisa a 90% (LC 123/2006 art. 13-A) e a segregação separa a ST (art. 18 §4º-A, I).
// @covers-us R-FISC-042 — parcial: entrada de outra UF pede classificação (a destinação ainda não é registrada).

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Services\Tributacao\FechamentoContadorService as F;

uses(Tests\TestCase::class);

/**
 * Casos derivados do playbook Fiscal thread 28 (aceites R-FISC-040/041/042) e da bateria TR_MES
 * do protótipo (`fiscal-tributacao.jsx`), não do service. Tenant 98, vizinho 99 (ADR 0358).
 * Números 9_280_xxx em `nfe_emissoes` reservados a este arquivo.
 */

/** Medidas de um mês sem nada, para mexer só no que o caso precisa. */
function fchMedidas(array $sobre = []): array
{
    return array_replace([
        'notas' => ['autorizadas' => 0, 'canceladas' => 0, 'denegadas' => 0, 'rejeitadas' => 0, 'inutilizadas' => 0, 'em_andamento' => 0],
        'receita12m_centavos' => 0,
        'receita12m_de' => '2025-10-01',
        'receita12m_ate' => '2026-09-30',
        'receita_mes_centavos' => 0,
        'segregacao' => ['linhas' => [F::SEG_REVENDA => 0, F::SEG_REVENDA_ST => 0, F::SEG_PRODUCAO => 0, F::SEG_SERVICOS => 0], 'notas_sem_xml' => 0],
        'fator_r' => ['razao' => 0.3, 'anexo' => 'III', 'sem_dado' => false, 'folha_parcial' => false],
        'entradas' => ['total' => 0, 'interestaduais' => 0, 'sem_manifestacao' => 0],
        'nfse_com_retencao' => 0,
        'sem_aceite' => 0,
    ], $sobre);
}

function fchCt(array $conferencias, string $id): array
{
    $c = collect($conferencias)->firstWhere('id', $id);
    expect($c)->not->toBeNull();

    return $c;
}

function fchSvc(): F
{
    return app(F::class);
}

it('devolve as 10 conferências, CT01 a CT10, na ordem', function () {
    $ids = array_column(fchSvc()->conferir(fchMedidas()), 'id');

    expect($ids)->toBe(['CT01', 'CT02', 'CT03', 'CT04', 'CT05', 'CT06', 'CT07', 'CT08', 'CT09', 'CT10']);
});

it('R-FISC-040 · 398 autorizadas + 9 canceladas + 1 denegada + 4 rejeitadas = 412, e 4 rejeitadas pendentes com ação', function () {
    $c = fchSvc()->conferir(fchMedidas(['notas' => [
        'autorizadas' => 398, 'canceladas' => 9, 'denegadas' => 1, 'rejeitadas' => 4, 'inutilizadas' => 0, 'em_andamento' => 0,
    ]]));

    $ct01 = fchCt($c, 'CT01');
    expect($ct01['situacao'])->toBe(F::OK);
    expect(str_contains($ct01['obtido'], '= 412 emitidas'))->toBeTrue();

    $ct02 = fchCt($c, 'CT02');
    expect($ct02['situacao'])->toBe(F::PENDENTE);
    expect(str_starts_with($ct02['obtido'], '4 notas rejeitadas'))->toBeTrue();
    // D-REJEITADA: pendência com ação, não bloqueio.
    expect($ct02['acao'])->toBe('Resolver ou inutilizar a numeração.');
});

it('R-FISC-040 · controle positivo: mês sem rejeitada mostra ok', function () {
    $c = fchSvc()->conferir(fchMedidas(['notas' => [
        'autorizadas' => 10, 'canceladas' => 0, 'denegadas' => 0, 'rejeitadas' => 0, 'inutilizadas' => 0, 'em_andamento' => 0,
    ]]));

    expect(fchCt($c, 'CT02')['situacao'])->toBe(F::OK);
    expect(fchCt($c, 'CT02')['acao'])->toBeNull();
});

it('R-FISC-040 · nota ainda sem resposta da SEFAZ deixa o CT01 pendente', function () {
    $c = fchSvc()->conferir(fchMedidas(['notas' => [
        'autorizadas' => 10, 'canceladas' => 0, 'denegadas' => 0, 'rejeitadas' => 0, 'inutilizadas' => 0, 'em_andamento' => 2,
    ]]));

    expect(fchCt($c, 'CT01')['situacao'])->toBe(F::PENDENTE);
});

it('R-FISC-041 · receita 12m de R$ 3,48 mi = 96,7% do sublimite → atenção', function () {
    $ct03 = fchCt(fchSvc()->conferir(fchMedidas(['receita12m_centavos' => 348_000_000])), 'CT03');

    expect($ct03['situacao'])->toBe(F::ATENCAO);
    expect(str_contains($ct03['obtido'], '96,7% do sublimite de R$ 3.600.000,00'))->toBeTrue();
});

it('R-FISC-041 · fronteira dos 90% em centavos inteiros, abaixo ok, acima do sublimite pendente', function () {
    $sit = fn (int $c) => fchCt(fchSvc()->conferir(fchMedidas(['receita12m_centavos' => $c])), 'CT03')['situacao'];

    expect($sit(324_000_000))->toBe(F::ATENCAO); // exatamente 90%
    expect($sit(323_999_999))->toBe(F::OK);       // 1 centavo abaixo (controle positivo)
    expect($sit(360_000_000))->toBe(F::ATENCAO);  // no sublimite ainda não passou
    expect($sit(360_000_001))->toBe(F::PENDENTE); // passou
});

it('R-FISC-041 · receita com CSOSN 500 sai numa linha própria e a soma das linhas = receita do mês', function () {
    $xml = <<<'XML'
<nfeProc xmlns="http://www.portalfiscal.inf.br/nfe"><NFe><infNFe>
<det nItem="1"><prod><CFOP>5102</CFOP><vProd>612.00</vProd></prod><imposto><ICMS><ICMSSN102><CSOSN>102</CSOSN></ICMSSN102></ICMS></imposto></det>
<det nItem="2"><prod><CFOP>5405</CFOP><vProd>200.00</vProd><vDesc>16.00</vDesc></prod><imposto><ICMS><ICMSSN500><CSOSN>500</CSOSN></ICMSSN500></ICMS></imposto></det>
<det nItem="3"><prod><CFOP>5101</CFOP><vProd>1428.00</vProd></prod><imposto><ICMS><ICMSSN101><CSOSN>101</CSOSN></ICMSSN101></ICMS></imposto></det>
</infNFe></NFe></nfeProc>
XML;
    $linhas = F::segregarXml($xml);

    expect($linhas[F::SEG_REVENDA])->toBe(61_200);
    expect($linhas[F::SEG_REVENDA_ST])->toBe(18_400); // 200,00 − 16,00 de desconto
    expect($linhas[F::SEG_PRODUCAO])->toBe(142_800);

    $seg = ['linhas' => $linhas + [F::SEG_SERVICOS => 38_600], 'notas_sem_xml' => 0];
    $ok = fchCt(fchSvc()->conferir(fchMedidas(['segregacao' => $seg, 'receita_mes_centavos' => 261_000])), 'CT04');
    expect($ok['situacao'])->toBe(F::OK);
    expect(str_contains($ok['obtido'], 'Revenda com ST já retida R$ 184,00'))->toBeTrue();
    expect(str_ends_with($ok['obtido'], '= R$ 2.610,00'))->toBeTrue();

    // Soma diferente da receita do Financeiro: atenção, mostrando os dois números.
    $dif = fchCt(fchSvc()->conferir(fchMedidas(['segregacao' => $seg, 'receita_mes_centavos' => 300_000])), 'CT04');
    expect($dif['situacao'])->toBe(F::ATENCAO);
    expect(str_contains($dif['obtido'], 'receita do mês no Financeiro R$ 3.000,00'))->toBeTrue();
});

it('R-FISC-041 · nota autorizada sem XML não deixa a segregação dizer ok', function () {
    $seg = ['linhas' => [F::SEG_REVENDA => 100, F::SEG_REVENDA_ST => 0, F::SEG_PRODUCAO => 0, F::SEG_SERVICOS => 0], 'notas_sem_xml' => 1];
    $c = fchCt(fchSvc()->conferir(fchMedidas(['segregacao' => $seg, 'receita_mes_centavos' => 100])), 'CT04');

    expect($c['situacao'])->toBe(F::ATENCAO);
    expect(str_contains($c['obtido'], '1 notas autorizadas sem XML'))->toBeTrue();
});

it('CT05 · folha só do ERP avisa que falta o § 24; usa o anexo devolvido', function () {
    $c = fchCt(fchSvc()->conferir(fchMedidas(['fator_r' => ['razao' => 0.28, 'anexo' => 'V', 'sem_dado' => false, 'folha_parcial' => true]])), 'CT05');

    expect($c['situacao'])->toBe(F::ATENCAO);
    expect(str_starts_with($c['obtido'], '28,00% → Anexo V'))->toBeTrue();
});

it('R-FISC-042 (parcial) · entrada de outra UF pede classificação; só da mesma UF é ok; sem entrada nenhuma não diz ok', function () {
    $sit = fn (array $e) => fchCt(fchSvc()->conferir(fchMedidas(['entradas' => $e])), 'CT06')['situacao'];

    expect($sit(['total' => 4, 'interestaduais' => 3, 'sem_manifestacao' => 0]))->toBe(F::ATENCAO);
    expect($sit(['total' => 2, 'interestaduais' => 0, 'sem_manifestacao' => 0]))->toBe(F::OK);
    expect($sit(['total' => 0, 'interestaduais' => 0, 'sem_manifestacao' => 0]))->toBe(F::ATENCAO);
});

it('CT08 e CT09 dizem "sem fonte" em vez de ok', function () {
    $c = fchSvc()->conferir(fchMedidas());

    expect(fchCt($c, 'CT08')['situacao'])->toBe(F::SEM_FONTE);
    expect(fchCt($c, 'CT09')['situacao'])->toBe(F::SEM_FONTE);
});

// ---------------------------------------------------------------- banco (MySQL · tenant 98 × 99)

function fchLimpar(): void
{
    DB::table('nfe_emissoes')->whereBetween('numero', [9_280_000, 9_280_999])->delete();
}

function fchNota(int $biz, int $numero, string $status, string $quando): void
{
    DB::table('nfe_emissoes')->insert([
        'business_id' => $biz, 'modelo' => '55', 'serie' => '928', 'numero' => $numero,
        'status' => $status, 'valor_total' => 10.00,
        'emitido_em' => in_array($status, ['autorizada', 'cancelada', 'denegada'], true) ? $quando : null,
        'created_at' => $quando, 'updated_at' => $quando,
    ]);
}

it('R-FISC-040 · conta as notas do mês do próprio business, não as do vizinho nem as de outro mês', function () {
    if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('nfe_emissoes')) {
        $this->markTestSkipped('precisa do schema MySQL real de nfe_emissoes');
    }
    $a = $this->seededTenant()->id;
    $b = $this->seededSupportClientTenant()->id;
    fchLimpar();

    try {
        $n = 9_280_000;
        foreach (['autorizada', 'autorizada', 'cancelada', 'denegada', 'rejeitada', 'rejeitada'] as $s) {
            fchNota($a, $n++, $s, '2026-09-15 10:00:00');
        }
        fchNota($a, $n++, 'rejeitada', '2026-08-31 23:59:59'); // outro mês
        fchNota($b, $n++, 'rejeitada', '2026-09-15 10:00:00'); // vizinho

        $notas = fchSvc()->medir($a, CarbonImmutable::create(2026, 9, 1))['notas'];

        expect($notas)->toBe([
            'autorizadas' => 2, 'canceladas' => 1, 'denegadas' => 1, 'rejeitadas' => 2, 'inutilizadas' => 0, 'em_andamento' => 0,
        ]);
    } finally {
        fchLimpar();
    }
});

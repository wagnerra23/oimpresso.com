<?php

declare(strict_types=1);

// @covers-us US-NFE-010 — tabela ICMS/FCP por UF curada (D-UF · lei 4 do módulo).
// @covers R-NFE-021 — UF sem alíquota interna cadastrada não inventa valor (thread 09 do playbook Fiscal).

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Database\Seeders\NfeIcmsUfSeeder;
use Modules\NfeBrasil\Exceptions\AliquotaInternaNaoCadastradaException;
use Modules\NfeBrasil\Models\NfeIcmsUf;

uses(Tests\TestCase::class);

/**
 * MySQL-only · tenant fictício 98 e vizinho 99 (ADR 0358).
 *
 * Regra mestre de valor — o seed é conferido por um ORÁCULO escrito aqui, direto do texto da
 * Resolução do Senado nº 22/1989, com as regiões montadas de novo (não as constantes do seeder), e
 * por contagens feitas à mão: 27 UFs → 702 pares interestaduais; origens Sul+Sudeste = 7, destinos
 * N+NE+CO+ES = 21 → 7 × 21 = 147 pares, menos ES→ES (interno) = 146 pares a 7%; 702 − 146 = 556 a 12%.
 */

function icmsBiz(): int
{
    return test()->seededTenant()->id;
}

function icmsLimpar(): void
{
    DB::table('nfe_icms_uf')->whereIn('business_id', [icmsBiz(), test()->seededSupportClientTenant()->id])->delete();
}

/** Oráculo independente: regiões do IBGE escritas por extenso, regra lida da resolução. */
function icmsOraculo(string $origem, string $destino): ?float
{
    $regiao = [
        'Norte'        => ['RO', 'AC', 'AM', 'RR', 'PA', 'AP', 'TO'],
        'Nordeste'     => ['MA', 'PI', 'CE', 'RN', 'PB', 'PE', 'AL', 'SE', 'BA'],
        'Sudeste'      => ['MG', 'ES', 'RJ', 'SP'],
        'Sul'          => ['PR', 'SC', 'RS'],
        'Centro-Oeste' => ['MS', 'MT', 'GO', 'DF'],
    ];
    $de = fn (string $uf) => array_key_first(array_filter($regiao, fn ($ufs) => in_array($uf, $ufs, true)));

    if ($origem === $destino) {
        return null;
    }
    $origemSulSudeste = in_array($de($origem), ['Sul', 'Sudeste'], true);
    $destinoAlvo = in_array($de($destino), ['Norte', 'Nordeste', 'Centro-Oeste'], true) || $destino === 'ES';

    return $origemSulSudeste && $destinoAlvo ? 0.07 : 0.12;
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('MySQL-only: tabela nfe_icms_uf vem da migração 2026_10_07_000003.');
    }
    if (! Schema::hasTable('nfe_icms_uf')) {
        $this->markTestSkipped('Migração 2026_10_07_000003 não rodou — rode as migrations do módulo.');
    }
    icmsLimpar();
});

afterEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite' && Schema::hasTable('nfe_icms_uf')) {
        icmsLimpar();
    }
});

it('R-NFE-021 · UF sem interna não inventa', function () {
    (new NfeIcmsUfSeeder())->semear(icmsBiz());

    // A interestadual existe (SP→RJ é 12%), a interna de RJ não: erro com a UF no texto, nunca 0.
    expect(NfeIcmsUf::aliquotaInterestadual(icmsBiz(), 'SP', 'RJ'))->toBe(0.12);
    expect(fn () => NfeIcmsUf::aliquotaInterna(icmsBiz(), 'SP', 'RJ'))
        ->toThrow(AliquotaInternaNaoCadastradaException::class, 'Alíquota interna de RJ não cadastrada');

    // A outra empresa não lê o cadastro desta: sem linha própria, também é erro.
    expect(fn () => NfeIcmsUf::aliquotaInterna(test()->seededSupportClientTenant()->id, 'SP', 'RJ'))
        ->toThrow(AliquotaInternaNaoCadastradaException::class);

    // CONTROLE POSITIVO — com a interna preenchida pelo contador, devolve o número cadastrado.
    DB::table('nfe_icms_uf')->where('business_id', icmsBiz())
        ->where('uf_origem', 'SP')->where('uf_destino', 'RJ')->update(['aliquota_interna' => 0.20]);
    expect(NfeIcmsUf::aliquotaInterna(icmsBiz(), 'SP', 'RJ'))->toBe(0.2);
});

it('R-NFE-021 · seed bate com a Resolução do Senado 22/1989 em todos os pares', function () {
    expect((new NfeIcmsUfSeeder())->semear(icmsBiz()))->toBe(729); // 27 × 27

    $linhas = DB::table('nfe_icms_uf')->where('business_id', icmsBiz())->get();
    $divergentes = [];
    $sete = $doze = $internas = 0;
    foreach ($linhas as $l) {
        $esperado = icmsOraculo($l->uf_origem, $l->uf_destino);
        $semeado  = $l->aliquota_interestadual === null ? null : (float) $l->aliquota_interestadual;
        if ($semeado !== $esperado) {
            $divergentes[] = "{$l->uf_origem}→{$l->uf_destino}";
        }
        $semeado === 0.07 ? $sete++ : ($semeado === 0.12 ? $doze++ : $internas++);
        // Lei 4: nada de interna nem FCP no seed.
        expect($l->aliquota_interna)->toBeNull()->and($l->fcp)->toBeNull();
    }

    expect($divergentes)->toBe([])
        ->and($sete)->toBe(146)
        ->and($doze)->toBe(556)
        ->and($internas)->toBe(27);

    // Pontos da resolução, um a um.
    $par = fn (string $o, string $d) => NfeIcmsUf::aliquotaInterestadual(icmsBiz(), $o, $d);
    expect($par('SP', 'BA'))->toBe(0.07)   // Sudeste → Nordeste
        ->and($par('BA', 'SP'))->toBe(0.12) // Nordeste → Sudeste: regra geral
        ->and($par('SP', 'RJ'))->toBe(0.12) // Sudeste → Sudeste
        ->and($par('SP', 'ES'))->toBe(0.07) // "e ao Estado do Espírito Santo"
        ->and($par('ES', 'SP'))->toBe(0.12)
        ->and($par('RS', 'MT'))->toBe(0.07) // Sul → Centro-Oeste
        ->and($par('SC', 'SC'))->toBeNull(); // interna
});

it('R-NFE-021 · seed idempotente e append-only', function () {
    $seeder = new NfeIcmsUfSeeder();
    expect($seeder->semear(icmsBiz()))->toBe(729);

    // O contador preenche a interna de RJ.
    DB::table('nfe_icms_uf')->where('business_id', icmsBiz())
        ->where('uf_origem', 'SP')->where('uf_destino', 'RJ')->update(['aliquota_interna' => 0.20]);
    $antes = DB::table('nfe_icms_uf')->where('business_id', icmsBiz())->orderBy('id')->get()->toArray();

    // Re-seed: nada novo, e nenhuma linha existente muda (nem a interna que o contador gravou).
    expect($seeder->semear(icmsBiz()))->toBe(0);
    expect(DB::table('nfe_icms_uf')->where('business_id', icmsBiz())->orderBy('id')->get()->toArray())->toEqual($antes);
    expect(NfeIcmsUf::aliquotaInterna(icmsBiz(), 'SP', 'RJ'))->toBe(0.2);
});

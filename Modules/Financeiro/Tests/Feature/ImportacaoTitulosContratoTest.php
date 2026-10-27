<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Modules\Financeiro\Services\ImportacaoTitulosService;

uses(Tests\TestCase::class);

/**
 * Importação de títulos por planilha (US-FIN-034) — contrato em
 * resources/js/Pages/Financeiro/ImportacaoTitulos/Index.casos.md (UC-IMPT-01..05).
 *
 * Regra mestre de valor: a prévia não grava; o total gravado é conferido contra a soma
 * independente do banco (dois caminhos). Tenant 98 (ADR 0358), vencimentos em 2031-04,
 * transação desfeita no fim.
 *
 * @covers-us US-FIN-034
 */

const IMPT_CAB = ['Tipo', 'Contraparte', 'Documento', 'Emissão', 'Vencimento', 'Valor', 'Plano de contas', 'Observação'];

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema MySQL do Financeiro (JSON_EXTRACT em metadata).');
    }
    $this->bizId = (int) $this->seededTenant()->id;
    $this->userId = (int) (User::where('business_id', $this->bizId)->value('id') ?? 0);
    if ($this->userId === 0) {
        $this->markTestSkipped('Sem usuário no tenant de teste.');
    }
    session(['user.business_id' => $this->bizId, 'business.id' => $this->bizId]);
    DB::beginTransaction();

    DB::table('fin_planos_conta')->insert([
        'business_id' => $this->bizId, 'codigo' => '9.9.34.001', 'nome' => 'ZZ IMPT receita', 'tipo' => 'receita',
        'nivel' => 4, 'natureza' => 'credito', 'created_at' => now(), 'updated_at' => now(),
    ]);
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

function imptContar(int $biz): int
{
    return DB::table('fin_titulos')->where('business_id', $biz)->where('cliente_descricao', 'like', 'ZZ IMPT%')->count();
}

function imptPlanilha(): array
{
    return [
        IMPT_CAB,
        ['Receber', 'ZZ IMPT Alfa', 'NF 1021', '01/04/2031', '10/04/2031', '1.840,50', '9.9.34.001', ''],
        ['Pagar', 'ZZ IMPT Beta', 'Boleto 88', '', '15/04/2031', 612.5, '', 'conta de papel'],
        ['Pagar', 'ZZ IMPT Gama', 'Boleto 89', '', '31/02/2031', '100,00', '', ''],
        ['Receber', '', 'NF 1022', '', '12/04/2031', '75,00', '', ''],
        ['Pagar', 'ZZ IMPT Delta', 'NF 553', '', '20/04/2031', '-120,00', '', ''],
        ['Receber', 'ZZ IMPT Eta', 'NF 1023', '', '22/04/2031', '30,00', '0.0.0', ''],
        ['', '', '', '', '', '', '', ''],
    ];
}

it('UC-IMPT-01: a prévia classifica linha a linha e não grava nada', function () {
    $antes = imptContar($this->bizId);
    $p = app(ImportacaoTitulosService::class)->previa($this->bizId, imptPlanilha());

    $porLinha = collect($p['linhas'])->mapWithKeys(fn ($l) => [$l['n'] => $l['status'].'|'.($l['motivo'] ?? '')])->all();

    expect(imptContar($this->bizId))->toBe($antes)
        ->and($p['resumo']['ok'])->toBe(2)
        ->and($p['resumo']['erro'])->toBe(4)
        ->and($porLinha[4])->toBe('erro|Vencimento inválido (31/02/2031)')
        ->and($porLinha[5])->toBe('erro|Contraparte em branco')
        ->and($porLinha[6])->toBe('erro|Valor deve ser maior que zero')
        ->and($porLinha[7])->toBe('erro|Plano de contas 0.0.0 não existe')
        ->and(array_key_exists(8, $porLinha))->toBeFalse()
        // "1.840,50" (texto pt-BR) e 612.5 (número da planilha) viram centavos exatos
        ->and($p['resumo']['receber_centavos'])->toBe(184050)
        ->and($p['resumo']['pagar_centavos'])->toBe(61250);
});

it('UC-IMPT-02: gravar com o token grava só as válidas e o total fecha por dois caminhos', function () {
    $svc = app(ImportacaoTitulosService::class);
    $p = $svc->previa($this->bizId, imptPlanilha());
    $r = $svc->gravar($this->bizId, $this->userId, $p['token']);

    $doBanco = (int) round((float) DB::table('fin_titulos')->where('business_id', $this->bizId)
        ->where('cliente_descricao', 'like', 'ZZ IMPT%')->sum('valor_total') * 100);
    $alfa = DB::table('fin_titulos')->where('business_id', $this->bizId)->where('cliente_descricao', 'ZZ IMPT Alfa')->first();

    expect($r['gravados'])->toBe(2)
        ->and(imptContar($this->bizId))->toBe(2)
        ->and($r['receber_centavos'] + $r['pagar_centavos'])->toBe(245300)
        ->and($doBanco)->toBe(245300)
        ->and($alfa->tipo)->toBe('receber')
        ->and($alfa->status)->toBe('aberto')
        ->and((string) $alfa->vencimento)->toStartWith('2031-04-10')
        ->and($alfa->numero)->toStartWith('R-');
});

it('UC-IMPT-03: o que já existe, no banco ou repetido no arquivo, não entra de novo', function () {
    $svc = app(ImportacaoTitulosService::class);
    $svc->gravar($this->bizId, $this->userId, $svc->previa($this->bizId, imptPlanilha())['token']);

    $planilha = [IMPT_CAB,
        ['Receber', 'ZZ IMPT Alfa', 'NF 1021', '', '10/04/2031', '1840,50', '', ''],
        ['Receber', 'ZZ IMPT Novo', 'NF 2000', '', '28/04/2031', '10,00', '', ''],
        ['Receber', 'ZZ IMPT Novo', 'NF 2000', '', '28/04/2031', '10,00', '', ''],
    ];
    $p = $svc->previa($this->bizId, $planilha);

    expect(collect($p['linhas'])->pluck('status')->all())->toBe(['existe', 'ok', 'existe'])
        ->and($p['linhas'][0]['motivo'])->toStartWith('Já existe: título R-')
        ->and($p['linhas'][2]['motivo'])->toBe('Repetida na linha 3 do arquivo');
});

it('UC-IMPT-04: o mesmo token não grava duas vezes', function () {
    $svc = app(ImportacaoTitulosService::class);
    $token = $svc->previa($this->bizId, imptPlanilha())['token'];
    $svc->gravar($this->bizId, $this->userId, $token);

    expect(fn () => $svc->gravar($this->bizId, $this->userId, $token))->toThrow(InvalidArgumentException::class)
        ->and(imptContar($this->bizId))->toBe(2);
});

it('UC-IMPT-05: token da prévia de uma empresa não grava em outra', function () {
    $svc = app(ImportacaoTitulosService::class);
    $token = $svc->previa($this->bizId, imptPlanilha())['token'];
    $outro = (int) DB::table('business')->where('id', '!=', $this->bizId)->value('id');

    expect(fn () => $svc->gravar($outro, $this->userId, $token))->toThrow(InvalidArgumentException::class)
        ->and(DB::table('fin_titulos')->where('business_id', $outro)->where('cliente_descricao', 'like', 'ZZ IMPT%')->count())->toBe(0);
});

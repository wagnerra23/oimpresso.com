<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Services\ExportacaoFolhaService;
use Modules\Ponto\Tests\Feature\PontoTestCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(PontoTestCase::class);

/**
 * Contrato da exportação do ponto para a folha e a contabilidade — US-PONTO-019.
 *
 * Âncora (não o código): os três cards do catálogo de relatórios (Horas Extras, Banco de Horas,
 * Atrasos e Faltas) e o que a folha precisa receber do ponto — horas por natureza, por
 * colaborador e por competência. O valor em dinheiro é da folha, não daqui.
 *
 * Cada número esperado é montado AQUI a partir das linhas de apuração que o teste grava
 * (soma à mão), não lido do serviço.
 *
 * Tier 0 (ADR 0093/0358): tenant fictício 98 vs adversário 99. NUNCA biz=4, nunca biz=1.
 * Tudo em transação revertida no fim — sem `RefreshDatabase` (a lane `ponto-pest` proíbe).
 *
 * @covers-us US-PONTO-019
 */

const RFOL_BIZ = 98;
const RFOL_MES = '2099-08'; // mês sintético: não colide com dado real nem com o RLEG (2099-07)

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (ADR 0358).');
    }
    foreach (['ponto_colaborador_config', 'ponto_apuracao_dia', 'ponto_banco_horas_saldo'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Tabela {$t} ausente.");
        }
    }
    if (! DB::table('business')->where('id', RFOL_BIZ)->exists()) {
        $this->markTestSkipped('Tenant fictício 98 não semeado.');
    }

    DB::beginTransaction();
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

/** Colaborador com user próprio (`user_id` é NOT NULL e único). */
function rfolColaborador(int $bizId, string $matricula, string $nome): Colaborador
{
    $userId = DB::table('users')->insertGetId([
        'first_name' => $nome, 'last_name' => '', 'username' => 'rfol_' . uniqid(), 'password' => 'x',
        'business_id' => $bizId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $id = DB::table('ponto_colaborador_config')->insertGetId([
        'business_id' => $bizId, 'user_id' => $userId, 'matricula' => $matricula,
        'controla_ponto' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return Colaborador::withoutGlobalScopes()->findOrFail($id); // SUPERADMIN: fixture de teste, tenant explícito
}

/** @param array<string,int> $minutos */
function rfolDia(Colaborador $c, string $dia, array $minutos): void
{
    DB::table('ponto_apuracao_dia')->insert(array_merge([
        'business_id' => $c->business_id, 'colaborador_config_id' => $c->id,
        'data' => RFOL_MES . '-' . $dia, 'estado' => 'CALCULADO',
        'prevista_carga_minutos' => 480, 'realizada_trabalhada_minutos' => 480,
        'created_at' => now(), 'updated_at' => now(),
    ], $minutos));
}

/** @return array<string, array<string,string>> matrícula => coluna => valor */
function rfolPorMatricula(array $linhas): array
{
    $cab = array_shift($linhas);
    $out = [];
    foreach ($linhas as $l) {
        $out[$l[0]] = array_combine($cab, $l);
    }

    return $out;
}

it('UC-RELIDX-13 · Horas Extras: soma a HE de cada natureza no mês, em hh:mm, um colaborador por linha', function () {
    $ana = rfolColaborador(RFOL_BIZ, 'RFOL-001', 'Ana');
    rfolDia($ana, '04', ['he_diurna_minutos' => 90, 'he_noturna_minutos' => 30, 'adicional_noturno_minutos' => 30, 'dsr_repercussao_minutos' => 20]);
    rfolDia($ana, '05', ['he_diurna_minutos' => 45, 'he_feriado_minutos' => 480]);
    // fora do mês: não entra
    DB::table('ponto_apuracao_dia')->insert(['business_id' => RFOL_BIZ, 'colaborador_config_id' => $ana->id,
        'data' => '2099-09-01', 'estado' => 'CALCULADO', 'he_diurna_minutos' => 600, 'created_at' => now(), 'updated_at' => now()]);

    $linhas = app(ExportacaoFolhaService::class)->linhas('he', RFOL_BIZ,
        \Carbon\Carbon::parse(RFOL_MES . '-01'), \Carbon\Carbon::parse(RFOL_MES . '-01')->endOfMonth());

    $this->assertSame(['Matrícula', 'Colaborador', 'HE 50% diurna', 'HE noturna', 'HE feriado (100%)',
        'Adicional noturno', 'DSR sobre HE', 'Dias apurados'], $linhas[0]);
    $ana = rfolPorMatricula($linhas)['RFOL-001'];
    // 90 + 45 = 135 min = 02:15 · feriado 480 = 08:00 · 2 dias no mês
    $this->assertSame(['02:15', '00:30', '08:00', '00:30', '00:20', '2'],
        [$ana['HE 50% diurna'], $ana['HE noturna'], $ana['HE feriado (100%)'], $ana['Adicional noturno'], $ana['DSR sobre HE'], $ana['Dias apurados']]);
});

it('UC-RELIDX-14 · Atrasos e Faltas: falta só conta como dia quando não houve trabalho e a falta é cheia', function () {
    $bia = rfolColaborador(RFOL_BIZ, 'RFOL-002', 'Bia');
    rfolDia($bia, '04', ['atraso_minutos' => 15]);
    rfolDia($bia, '05', ['saida_antecipada_minutos' => 30]);
    rfolDia($bia, '06', ['realizada_trabalhada_minutos' => 0, 'falta_minutos' => 480]);
    rfolDia($bia, '07', ['realizada_trabalhada_minutos' => 240, 'falta_minutos' => 240]); // meio dia: horas sim, dia não

    $linhas = app(ExportacaoFolhaService::class)->linhas('atrasos', RFOL_BIZ,
        \Carbon\Carbon::parse(RFOL_MES . '-01'), \Carbon\Carbon::parse(RFOL_MES . '-01')->endOfMonth());

    $bia = rfolPorMatricula($linhas)['RFOL-002'];
    $this->assertSame(['00:15', '00:30', '12:00', '1', '4'],
        [$bia['Atrasos'], $bia['Saídas antecipadas'], $bia['Faltas (horas)'], $bia['Faltas (dias)'], $bia['Dias apurados']]);
});

it('UC-RELIDX-15 · Banco de Horas: crédito e débito do mês e o saldo atual, devedor com sinal', function () {
    $caio = rfolColaborador(RFOL_BIZ, 'RFOL-003', 'Caio');
    rfolDia($caio, '04', ['banco_horas_credito_minutos' => 60]);
    rfolDia($caio, '05', ['banco_horas_debito_minutos' => 150]);
    DB::table('ponto_banco_horas_saldo')->insert(['business_id' => RFOL_BIZ, 'colaborador_config_id' => $caio->id,
        'saldo_minutos' => -90, 'created_at' => now(), 'updated_at' => now()]);

    $linhas = app(ExportacaoFolhaService::class)->linhas('banco-horas', RFOL_BIZ,
        \Carbon\Carbon::parse(RFOL_MES . '-01'), \Carbon\Carbon::parse(RFOL_MES . '-01')->endOfMonth());

    $caio = rfolPorMatricula($linhas)['RFOL-003'];
    $this->assertSame(['01:00', '02:30', '-01:30'], [$caio['Crédito no mês'], $caio['Débito no mês'], $caio['Saldo atual']]);
});

it('UC-RELIDX-16 · GET da exportação traz só o empregador da sessão, como CSV para download; os 3 cards ficam disponíveis', function () {
    $u = User::query()->where('business_id', RFOL_BIZ)->first() ?? $this->markTestSkipped('Nenhum user no biz 98.');
    Permission::firstOrCreate(['name' => 'ponto.access', 'guard_name' => 'web']);
    $u->givePermissionTo('ponto.access');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    session(['user.business_id' => RFOL_BIZ, 'business.id' => RFOL_BIZ]);
    $this->actingAs($u);

    $meu = rfolColaborador(RFOL_BIZ, 'RFOL-MEU', 'Meu');
    rfolDia($meu, '04', ['he_diurna_minutos' => 60]);
    $alheio = rfolColaborador($this->garantirBizAlheio(), 'RFOL-ALHEIO', 'Alheio');
    rfolDia($alheio, '04', ['he_diurna_minutos' => 60]);

    $resp = $this->get('/ponto/relatorios/he?periodo=' . RFOL_MES);
    $resp->assertStatus(200);
    $corpo = (string) $resp->getContent();
    $this->assertStringContainsString('RFOL-MEU;', $corpo, 'Controle positivo: o colaborador do próprio empregador sai.');
    $this->assertStringNotContainsString('RFOL-ALHEIO', $corpo, 'Vazamento cross-tenant na exportação (ADR 0093).');
    $this->assertStringContainsString('attachment', (string) $resp->headers->get('Content-Disposition'));
    $this->assertStringContainsString('text/csv', (string) $resp->headers->get('Content-Type'));

    $catalogo = collect($this->inertiaGet('/ponto/relatorios')->json('props.relatorios') ?? [])->keyBy('chave');
    foreach (['he', 'banco-horas', 'atrasos'] as $chave) {
        $this->assertTrue($catalogo[$chave]['disponivel'] ?? false, "O card '{$chave}' tem gerador e tem de estar disponível.");
    }
});

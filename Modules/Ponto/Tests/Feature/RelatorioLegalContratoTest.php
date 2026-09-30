<?php

declare(strict_types=1);

use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Entities\Marcacao;
use Modules\Ponto\Services\MarcacaoService;
use Modules\Ponto\Services\ReportService;
use Modules\Ponto\Tests\Feature\PontoTestCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(PontoTestCase::class);

/**
 * Contrato dos relatórios LEGAIS do catálogo — hoje, o AFD (ADR 0413 W7: AFD → AEJ).
 *
 * Âncora (não o código): Portaria MTP 671/2021 + "Leiaute do Arquivo Fonte de Dados - AFD"
 * (gov.br/trabalho-e-emprego, versão "004"), com as posições que o AfdLeiaute671ContratoTest
 * já ancora:
 *   tipo 1 = 302 posições (CNPJ 012-025 · razão social 040-189 · INPI 190-206 · datas 207-226 ·
 *            DH geração 227-250 · versão 251-253 · CNPJ desenvolvedor 255-268 · CRC-16 299-302)
 *   tipo 7 = NSR · "7" · DH marcação · CPF · DH gravação · coletor · on/off · SHA-256 (137)
 *   trailer = "999999999" + contadores dos tipos 2..7 + "9" (64) · última linha = assinatura.
 * Escopo [W] 2026-09-30 (thread 12): REP-P, POR COLABORADOR, sem .p7s, falha fechada sem a
 * identidade do REP-P. O "golden" é montado AQUI, campo a campo — CRC e hash com implementação
 * própria do teste, não com o serviço (2 caminhos independentes).
 *
 * Tier 0: tenant fictício 98 × adversário 99 (ADR 0358). Transação revertida por caso:
 * `ponto_marcacoes` é append-only por lei e nada pode sobrar no banco do CT 100.
 *
 * @covers-us US-PONTO-006
 */

const RLEG_BIZ   = 98;
const RLEG_MES   = '2099-07';
const RLEG_INPI  = 'BR512099000000001';
const RLEG_CNPJ_DEV = '44555666000199';
const RLEG_CNPJ_EMP = '11222333000181';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Triggers append-only de ponto_marcacoes exigem MySQL.');
    }
    foreach (['ponto_marcacoes', 'ponto_colaborador_config'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Tabela {$t} ausente.");
        }
    }
    if (! DB::table('business')->where('id', RLEG_BIZ)->exists()) {
        $this->markTestSkipped('Tenant fictício 98 não semeado.');
    }

    DB::beginTransaction();

    config()->set('ponto_afd.rep_p_inpi', RLEG_INPI);
    config()->set('ponto_afd.desenvolvedor_cnpj', RLEG_CNPJ_DEV);
    config()->set('ponto_afd.ptrp_nome', 'oimpresso ponto');
    config()->set('ponto_afd.ptrp_versao', '1.0');
    config()->set('ponto_afd.ptrp_razao', 'Desenvolvedor Ficticio');
    config()->set('ponto_afd.ptrp_email', 'dev@example.test');
    DB::table('business')->where('id', RLEG_BIZ)->update(['tax_number_1' => RLEG_CNPJ_EMP]);
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

/** 11 dígitos com DV válido, base aleatória — nunca CPF literal no fonte (PII scan). */
function rlegCpf(): string
{
    $d = [];
    for ($i = 0; $i < 9; $i++) {
        $d[] = random_int(0, 9);
    }
    foreach ([10, 11] as $peso) {
        $soma = 0;
        foreach ($d as $i => $v) {
            $soma += $v * ($peso - $i);
        }
        $d[] = ($soma * 10) % 11 % 10;
    }

    return implode('', $d);
}

/** Colaborador com user próprio (`user_id` é unique) e CPF gravado com máscara. */
function rlegColaborador(int $bizId, string $cpf): Colaborador
{
    $userId = DB::table('users')->insertGetId([
        'first_name' => 'RLEG teste', 'username' => 'rleg_' . uniqid(), 'password' => 'x',
        'business_id' => $bizId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $id = DB::table('ponto_colaborador_config')->insertGetId([
        'business_id' => $bizId, 'user_id' => $userId, 'matricula' => 'RLEG-' . uniqid(),
        'cpf' => vsprintf('%s.%s.%s-%s', str_split($cpf, 3)), 'controla_ponto' => true,
        'usa_banco_horas' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return Colaborador::withoutGlobalScopes()->findOrFail($id); // SUPERADMIN: fixture de teste, tenant explícito
}

function rlegMarcar(Colaborador $c, string $momento, string $origem = Marcacao::ORIGEM_REP_P, string $tipo = Marcacao::TIPO_ENTRADA): Marcacao
{
    return app(MarcacaoService::class)->registrar([
        'business_id' => (int) $c->business_id, 'colaborador_config_id' => (int) $c->id,
        'rep_id' => null, 'momento' => Carbon::parse($momento), 'origem' => $origem,
        'tipo' => $tipo, 'usuario_criador_id' => (int) $c->user_id,
        'dispositivo_id' => 'mobile:rleg',
    ]);
}

/** CRC-16/KERMIT — implementação do teste, conferida pelo valor de verificação "123456789" = 2189. */
function rlegCrc(string $dados): string
{
    $crc = 0;
    foreach (str_split($dados) as $ch) {
        $crc ^= ord($ch);
        for ($b = 0; $b < 8; $b++) {
            $crc = ($crc & 1) ? (($crc >> 1) ^ 0x8408) : ($crc >> 1);
        }
    }

    return sprintf('%04X', $crc);
}

function rlegDh(Carbon $c): string
{
    return $c->format('Y-m-d') . 'T' . $c->format('H:i') . ':00' . $c->format('O');
}

/** Tipo 7 esperado, montado campo a campo. */
function rlegTipo7(Marcacao $m, string $cpf, string $hashAnterior): string
{
    $m = Marcacao::withoutGlobalScopes()->findOrFail($m->id); // SUPERADMIN: relê created_at do banco
    $dados = str_pad((string) $m->nsr, 9, '0', STR_PAD_LEFT) . '7' . rlegDh($m->momento)
        . '0' . $cpf . rlegDh($m->created_at) . '01' . '0';

    return $dados . hash('sha256', $dados . $hashAnterior);
}

function rlegLinhas(string $arquivo): array
{
    expect(str_ends_with($arquivo, "\r\n"))->toBeTrue();

    return explode("\r\n", substr($arquivo, 0, -2));
}

function rlegGerar(Colaborador $c): string
{
    $inicio = Carbon::parse(RLEG_MES . '-01')->startOfDay();

    return app(ReportService::class)->afd($c, $inicio, $inicio->copy()->endOfMonth());
}

it('UC-RELIDX-06 · AFD sai no leiaute 671: cabeçalho com INPI e CRC, um tipo 7 por marcação REP-P, trailer e linha da assinatura', function () {
    expect(rlegCrc('123456789'))->toBe('2189'); // o CRC do teste confere com a norma

    $cpf = rlegCpf();
    $c = rlegColaborador(RLEG_BIZ, $cpf);
    $m1 = rlegMarcar($c, RLEG_MES . '-10 08:00:41');
    $m2 = rlegMarcar($c, RLEG_MES . '-10 17:02:00');
    rlegMarcar($c, RLEG_MES . '-11 08:00:00', Marcacao::ORIGEM_MANUAL); // não é REP-P
    rlegMarcar(rlegColaborador(RLEG_BIZ, rlegCpf()), RLEG_MES . '-10 09:00:00'); // outra cadeia

    $linhas = rlegLinhas(rlegGerar($c));

    expect($linhas)->toHaveCount(5);

    $h = $linhas[0];
    expect(strlen($h))->toBe(302)
        ->and(substr($h, 0, 11))->toBe('000000000' . '1' . '1')
        ->and(substr($h, 11, 14))->toBe(RLEG_CNPJ_EMP)
        ->and(substr($h, 189, 17))->toBe(RLEG_INPI)
        ->and(substr($h, 206, 20))->toBe(RLEG_MES . '-01' . RLEG_MES . '-31')
        ->and(substr($h, 250, 3))->toBe('004')
        ->and(substr($h, 254, 14))->toBe(RLEG_CNPJ_DEV)
        ->and(substr($h, 298, 4))->toBe(rlegCrc(substr($h, 0, 298)));

    $t1 = rlegTipo7($m1, $cpf, '');
    expect($linhas[1])->toBe($t1)
        ->and($linhas[2])->toBe(rlegTipo7($m2, $cpf, substr($t1, 73)))
        ->and(strlen($linhas[1]))->toBe(137)
        ->and(substr($linhas[1], 10, 24))->toEndWith(':00' . Carbon::parse(RLEG_MES . '-10')->format('O'));

    expect($linhas[3])->toBe('999999999' . str_repeat('0', 45) . '000000002' . '9')
        ->and($linhas[4])->toBe(str_pad('ASSINATURA_DIGITAL_EM_ARQUIVO_P7S', 100)); // leiaute: 100 posições
});

it('UC-RELIDX-07 · marcação fora do período não entra, mas o hash do 1º registro do mês encadeia com a anterior', function () {
    $cpf = rlegCpf();
    $c = rlegColaborador(RLEG_BIZ, $cpf);
    $antes = rlegMarcar($c, '2099-06-30 17:00:00');
    $dentro = rlegMarcar($c, RLEG_MES . '-01 08:00:00');

    $linhas = rlegLinhas(rlegGerar($c));

    expect($linhas)->toHaveCount(4)
        ->and($linhas[1])->toBe(rlegTipo7($dentro, $cpf, substr(rlegTipo7($antes, $cpf, ''), 73)))
        ->and(substr($linhas[2], 54, 9))->toBe('000000001');
});

it('UC-RELIDX-08 · sem a identidade do REP-P configurada o AFD não sai: o gerador recusa e o catálogo o marca indisponível', function () {
    config()->set('ponto_afd.rep_p_inpi', null);
    $c = rlegColaborador(RLEG_BIZ, rlegCpf());

    expect(fn () => rlegGerar($c))->toThrow(DomainException::class, 'INPI');
    expect(app(ReportService::class)->afdConfigurado())->toBeFalse();
});

it('UC-RELIDX-09 · GET do AFD: o próprio colaborador baixa o arquivo; o de outro empregador é 404; o AFDT não está no catálogo', function () {
    $u = User::query()->where('business_id', RLEG_BIZ)->first() ?? $this->markTestSkipped('Nenhum user no biz 98.');
    Permission::firstOrCreate(['name' => 'ponto.access', 'guard_name' => 'web']);
    $u->givePermissionTo('ponto.access');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    session(['user.business_id' => RLEG_BIZ, 'business.id' => RLEG_BIZ]);
    $this->actingAs($u);

    $cpf = rlegCpf();
    $meu = rlegColaborador(RLEG_BIZ, $cpf);
    rlegMarcar($meu, RLEG_MES . '-10 08:00:00');

    $resp = $this->get("/ponto/relatorios/afd?colaborador={$meu->id}&periodo=" . RLEG_MES);
    $resp->assertStatus(200);
    $disp = (string) $resp->headers->get('Content-Disposition');
    expect($disp)->toContain('attachment')
        ->and($disp)->toContain('AFD' . RLEG_INPI . RLEG_CNPJ_EMP . 'REP_P') // leiaute item 10.3
        ->and(str_contains($disp, $cpf))->toBeFalse(); // LGPD: nome do arquivo sem CPF do empregado
    expect(substr_count((string) $resp->getContent(), "\r\n"))->toBe(4);

    $alheio = rlegColaborador($this->garantirBizAlheio(), rlegCpf());
    $this->get("/ponto/relatorios/afd?colaborador={$alheio->id}&periodo=" . RLEG_MES)->assertStatus(404);

    $catalogo = collect($this->inertiaGet('/ponto/relatorios')->json('props.relatorios') ?? []);
    expect($catalogo)->not->toBeEmpty()
        ->and($catalogo->pluck('chave')->all())->not->toContain('afdt')
        ->and($catalogo->firstWhere('chave', 'afd')['disponivel'] ?? null)->toBeTrue();
});

// ─── AEJ (ADR 0420) — leiaute AEJ versão "002", MTE ───────────────────────────────────────────

function rlegAej(): array
{
    $inicio = Carbon::parse(RLEG_MES . '-01');

    return rlegLinhas(app(ReportService::class)->aej(RLEG_BIZ, $inicio, $inicio->copy()->endOfMonth()));
}

/** Escala com turno no dia da semana de $data: 08:00-12:00 / 13:00-17:00 (480 min). */
function rlegEscala(Colaborador $c, string $data): int
{
    $escala = DB::table('ponto_escalas')->insertGetId([
        'business_id' => RLEG_BIZ, 'nome' => 'RLEG escala', 'tipo' => 'FIXA',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('ponto_escala_turnos')->insert([
        'escala_id' => $escala, 'dia_semana' => Carbon::parse($data)->dayOfWeek,
        'hora_entrada' => '08:00:00', 'hora_almoco_inicio' => '12:00:00',
        'hora_almoco_fim' => '13:00:00', 'hora_saida' => '17:00:00',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('ponto_colaborador_config')->where('id', $c->id)->update(['escala_atual_id' => $escala]);

    return $escala;
}

it('UC-RELIDX-10 · AEJ sai no leiaute 002: vínculo, REP-P, horário contratual, pares E/S, desconsiderada com motivo, falta e banco de horas', function () {
    $cpf = rlegCpf();
    $c = rlegColaborador(RLEG_BIZ, $cpf);
    $d13 = RLEG_MES . '-13';
    $escala = rlegEscala($c, $d13);
    foreach ([['08:00', 'ENTRADA'], ['12:00', 'ALMOCO_INICIO'], ['13:00', 'ALMOCO_FIM'], ['17:00', 'SAIDA']] as [$h, $t]) {
        rlegMarcar($c, "{$d13} {$h}:00", Marcacao::ORIGEM_REP_P, $t);
    }
    rlegMarcar($c, RLEG_MES . '-14 08:00:00')->anular((int) $c->user_id, 'Batida em duplicidade');
    DB::table('ponto_apuracao_dia')->insert([
        'business_id' => RLEG_BIZ, 'colaborador_config_id' => $c->id, 'data' => RLEG_MES . '-15',
        'falta_minutos' => 480, 'estado' => 'CALCULADO', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('ponto_banco_horas_movimentos')->insert([
        'id' => (string) \Illuminate\Support\Str::uuid(), 'business_id' => RLEG_BIZ, 'colaborador_config_id' => $c->id,
        'data_referencia' => $d13, 'tipo' => 'CREDITO', 'minutos' => 60, 'saldo_posterior_minutos' => 60,
        'usuario_id' => $c->user_id,
    ]);

    $l = rlegAej();
    $dh = fn (string $m) => rlegDh(Carbon::parse($m));
    $cod = 'E' . $escala . 'D' . Carbon::parse($d13)->dayOfWeek;
    $r01 = explode('|', $l[0]);

    expect(array_slice($r01, 0, 3))->toBe(['01', '1', RLEG_CNPJ_EMP])
        ->and(array_slice($r01, 6, 2))->toBe([RLEG_MES . '-01', RLEG_MES . '-31'])
        ->and($r01[9])->toBe('002')
        ->and(array_slice($l, 1, 12))->toBe([
            '02|1|3|' . RLEG_INPI,
            '03|1|' . $cpf . '|RLEG teste',
            '04|' . $cod . '|480|0800|1200|1300|1700',
            '05|1|' . $dh("{$d13} 08:00") . '|1|E|1|O|' . $cod . '|',
            '05|1|' . $dh("{$d13} 12:00") . '|1|S|1|O||',
            '05|1|' . $dh("{$d13} 13:00") . '|1|E|2|O||',
            '05|1|' . $dh("{$d13} 17:00") . '|1|S|2|O||',
            '05|1|' . $dh(RLEG_MES . '-14 08:00') . '|1|D|1|O||Batida em duplicidade',
            '07|1|2|' . RLEG_MES . '-15||',
            '07|1|3|' . $d13 . '|60|1',
            '08|oimpresso ponto|1.0|1|' . RLEG_CNPJ_DEV . '|Desenvolvedor Ficticio|dev@example.test',
            '99|1|1|1|1|5|0|2|1',
        ])
        ->and($l[13])->toBe(str_pad('ASSINATURA_DIGITAL_EM_ARQUIVO_P7S', 100))
        ->and($l)->toHaveCount(14);
});

it('UC-RELIDX-11 · AEJ com dado legal ausente é recusado com a contagem, nunca preenchido: manual sem motivo, CPF ausente, PTRP vazio', function () {
    $c = rlegColaborador(RLEG_BIZ, rlegCpf());
    rlegEscala($c, RLEG_MES . '-13');
    rlegMarcar($c, RLEG_MES . '-13 08:00:00', Marcacao::ORIGEM_MANUAL);
    expect(fn () => rlegAej())->toThrow(DomainException::class, '1 marcação manual sem motivo gravado');

    $semCpf = rlegColaborador(RLEG_BIZ, rlegCpf());
    DB::table('ponto_colaborador_config')->where('id', $semCpf->id)->update(['cpf' => null]);
    rlegMarcar($semCpf, RLEG_MES . '-13 09:00:00');
    expect(fn () => rlegAej())->toThrow(DomainException::class, '1 colaborador sem CPF');

    config()->set('ponto_afd.ptrp_email', null);
    expect(fn () => rlegAej())->toThrow(DomainException::class, 'PTRP');
    expect(app(\Modules\Ponto\Services\AejService::class)->configurado())->toBeFalse();
});

it('UC-RELIDX-12 · GET do AEJ traz só o empregador da sessão; o do outro empregador não entra (Tier 0)', function () {
    $u = User::query()->where('business_id', RLEG_BIZ)->first() ?? $this->markTestSkipped('Nenhum user no biz 98.');
    Permission::firstOrCreate(['name' => 'ponto.access', 'guard_name' => 'web']);
    $u->givePermissionTo('ponto.access');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    session(['user.business_id' => RLEG_BIZ, 'business.id' => RLEG_BIZ]);
    $this->actingAs($u);

    $cpf = rlegCpf();
    $meu = rlegColaborador(RLEG_BIZ, $cpf);
    rlegEscala($meu, RLEG_MES . '-13');
    rlegMarcar($meu, RLEG_MES . '-13 08:00:00');
    $cpfAlheio = rlegCpf();
    rlegMarcar(rlegColaborador($this->garantirBizAlheio(), $cpfAlheio), RLEG_MES . '-13 08:00:00');

    $resp = $this->get('/ponto/relatorios/aej?periodo=' . RLEG_MES);
    $resp->assertStatus(200);
    $corpo = (string) $resp->getContent();
    expect(str_contains($corpo, '03|1|' . $cpf))->toBeTrue()
        ->and(str_contains($corpo, $cpfAlheio))->toBeFalse()
        ->and(str_contains((string) $resp->headers->get('Content-Disposition'), 'attachment'))->toBeTrue();

    $catalogo = collect($this->inertiaGet('/ponto/relatorios')->json('props.relatorios') ?? []);
    expect($catalogo->firstWhere('chave', 'aej')['disponivel'] ?? null)->toBeTrue();
});

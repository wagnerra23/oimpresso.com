<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Essentials\Entities\EssentialsHoliday;
use Modules\Ponto\Entities\ApuracaoDia;
use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Services\ApuracaoService;
use Modules\Ponto\Tests\Feature\PontoTestCase;

uses(PontoTestCase::class);

/**
 * Contrato de `ApuracaoService::aplicarFeriado()` — D5 de [W] em 2026-09-29 (emenda da ADR 0014),
 * issue #8200: o Ponto lê o feriado cadastrado no HRM (`essentials_holidays`).
 *
 * Num feriado não há jornada prevista (sem falta, sem atraso), e o que for trabalhado é HE de
 * feriado, paga em dobro (Lei 605/49 Art. 9 + Súmula 146 TST) — fora da HE de 50% e fora do banco
 * de horas.
 *
 * Tier 0 (ADR 0093): tenant fictício 98 vs adversário 99 (ADR 0358). NUNCA biz=4, nunca biz=1.
 * Sem `RefreshDatabase` — a lane `ponto-pest` proíbe. Limpeza por id coletado e por marcador.
 *
 * @see \Modules\Ponto\Services\ApuracaoService::aplicarFeriado
 */

const FER_MARCADOR = 'ISSUE8200-FERIADO';

$GLOBALS['fer_ids_criados'] = [];

function ferColaborador(int $bizId, int $userId): Colaborador
{
    return Colaborador::forceCreate([
        'business_id'    => $bizId,
        'user_id'        => $userId,
        'matricula'      => FER_MARCADOR . '-' . uniqid(),
        'controla_ponto' => true,
        'admissao'       => now()->subYear()->toDateString(),
    ]);
}

function ferCriarFeriado(int $bizId, string $inicio, string $fim, ?int $localId = null): int
{
    // INSERT não passa pelo global scope: `business_id` explícito monta o caso cross-tenant.
    $id = (int) EssentialsHoliday::create([
        'name'        => FER_MARCADOR,
        'business_id' => $bizId,
        'start_date'  => $inicio,
        'end_date'    => $fim,
        'location_id' => $localId,
    ])->id;

    $GLOBALS['fer_ids_criados'][] = $id;

    return $id;
}

/** Estado que a tolerância deixa num dia previsto SEM batida: falta cheia + divergência. */
function ferApuracaoComFalta(Colaborador $c, string $data, int $carga = 480): ApuracaoDia
{
    $a = new ApuracaoDia();
    $a->business_id              = $c->business_id;
    $a->colaborador_config_id    = $c->id;
    $a->data                     = $data;
    $a->prevista_carga_minutos   = $carga;
    $a->qtd_marcacoes            = 0;
    $a->realizada_trabalhada_minutos = 0;
    $a->falta_minutos            = $carga;
    $a->atraso_minutos           = 0;
    $a->saida_antecipada_minutos = 0;
    $a->he_diurna_minutos        = 0;
    $a->he_noturna_minutos       = 0;
    $a->dsr_repercussao_minutos  = 0;
    $a->he_feriado_minutos       = 0;
    $a->banco_horas_credito_minutos = 0;
    $a->banco_horas_debito_minutos  = 0;
    $a->divergencias             = [
        ['chave' => 'falta', 'mensagem' => 'Sem marcações em dia previsto de trabalho.'],
    ];

    return $a;
}

/** Estado de quem trabalhou 600min num dia de carga 480: 120min de HE de 50%. */
function ferApuracaoTrabalhada(Colaborador $c, string $data): ApuracaoDia
{
    $a = ferApuracaoComFalta($c, $data);
    $a->qtd_marcacoes                = 4;
    $a->realizada_trabalhada_minutos = 600;
    $a->falta_minutos                = 0;
    $a->he_diurna_minutos            = 120;
    $a->dsr_repercussao_minutos      = 120;
    $a->divergencias                 = [];

    return $a;
}

function ferChaves(ApuracaoDia $a): array
{
    return array_map(
        static fn ($d) => is_array($d) ? ($d['chave'] ?? '') : '',
        is_array($a->divergencias) ? $a->divergencias : []
    );
}

afterEach(function () {
    if (! empty($GLOBALS['fer_ids_criados'])) {
        // SUPERADMIN: a limpeza tem de alcançar o feriado gravado no tenant adversário (UC-FER-04).
        EssentialsHoliday::withoutGlobalScopes()
            ->whereIn('id', $GLOBALS['fer_ids_criados'])
            ->delete();
        $GLOBALS['fer_ids_criados'] = [];
    }

    if (Schema::hasTable('ponto_colaborador_config')) {
        DB::table('ponto_colaborador_config')
            ->where('matricula', 'like', FER_MARCADOR . '%')
            ->delete();
    }
});

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (ADR 0358).');
    }
    foreach (['ponto_colaborador_config', 'essentials_holidays', 'ponto_apuracao_dia'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Tabela {$t} ausente — schema não migrado nesta lane.");
        }
    }

    $this->tenant     = $this->seededTenant();
    $this->adversario = $this->seededSupportClientTenant();

    $user = \App\User::where('business_id', $this->tenant->id)->first();
    if (! $user) {
        $this->markTestSkipped('Sem user no tenant canônico — seed mínimo não rodou.');
    }
    $this->user    = $user;
    $this->service = app(ApuracaoService::class);
    $this->dia     = '2026-11-02';
});

it('UC-FER-01: feriado sem local zera a falta e tira a divergência "falta"', function () {
    $colab = ferColaborador($this->tenant->id, $this->user->id);
    $id = ferCriarFeriado($this->tenant->id, $this->dia, $this->dia);

    $a = ferApuracaoComFalta($colab, $this->dia);
    expect($a->falta_minutos)->toBe(480);

    $this->service->aplicarFeriado($a, $colab, \Carbon\Carbon::parse($this->dia));

    expect($a->falta_minutos)->toBe(0);
    expect($a->feriado_id)->toBe($id);
    expect(ferChaves($a))->not->toContain('falta');
    // Feriado sem trabalho é folga normal: nenhuma divergência nova.
    expect(ferChaves($a))->toBe([]);
});

it('UC-FER-02: trabalho em feriado vai para he_feriado_minutos e sai da HE de 50%', function () {
    $colab = ferColaborador($this->tenant->id, $this->user->id);
    ferCriarFeriado($this->tenant->id, $this->dia, $this->dia);

    $a = ferApuracaoTrabalhada($colab, $this->dia);
    $this->service->aplicarFeriado($a, $colab, \Carbon\Carbon::parse($this->dia));

    // Todo o trabalhado é HE de feriado (600), não só o excedente sobre a carga (120).
    expect($a->he_feriado_minutos)->toBe(600);
    expect($a->he_diurna_minutos)->toBe(0);
    expect($a->he_noturna_minutos)->toBe(0);
    expect($a->dsr_repercussao_minutos)->toBe(0);
    expect(ferChaves($a))->toContain('trabalho_em_feriado');
});

it('UC-FER-03: feriado de OUTRO local não se aplica ao colaborador', function () {
    $colab = ferColaborador($this->tenant->id, $this->user->id);
    $localDoUser = (int) (DB::table('users')->where('id', $this->user->id)->value('location_id') ?? 0);
    ferCriarFeriado($this->tenant->id, $this->dia, $this->dia, $localDoUser + 1000000);

    $a = ferApuracaoComFalta($colab, $this->dia);
    $this->service->aplicarFeriado($a, $colab, \Carbon\Carbon::parse($this->dia));

    expect($a->falta_minutos)->toBe(480);
    expect($a->feriado_id)->toBeNull();
});

it('UC-FER-04: feriado do tenant ADVERSÁRIO não se aplica (ADR 0093)', function () {
    $colab = ferColaborador($this->tenant->id, $this->user->id);
    ferCriarFeriado($this->adversario->id, $this->dia, $this->dia);

    $a = ferApuracaoComFalta($colab, $this->dia);
    $this->service->aplicarFeriado($a, $colab, \Carbon\Carbon::parse($this->dia));

    expect($a->falta_minutos)->toBe(480);
    expect($a->feriado_id)->toBeNull();
});

it('UC-FER-05: dia fora do intervalo do feriado não é feriado', function () {
    $colab = ferColaborador($this->tenant->id, $this->user->id);
    ferCriarFeriado($this->tenant->id, '2026-10-30', '2026-11-01');

    $a = ferApuracaoComFalta($colab, $this->dia);
    $this->service->aplicarFeriado($a, $colab, \Carbon\Carbon::parse($this->dia));

    expect($a->falta_minutos)->toBe(480);
});

it('UC-FER-06: HE de feriado não vira crédito de banco de horas, e falta não vira débito', function () {
    $colab = ferColaborador($this->tenant->id, $this->user->id);
    $colab->usa_banco_horas = true;
    ferCriarFeriado($this->tenant->id, $this->dia, $this->dia);

    $a = ferApuracaoTrabalhada($colab, $this->dia);
    $this->service->aplicarFeriado($a, $colab, \Carbon\Carbon::parse($this->dia));
    $this->service->calcularBancoHoras($a, $colab);

    expect($a->banco_horas_credito_minutos)->toBe(0);
    expect($a->banco_horas_debito_minutos)->toBe(0);
});

it('UC-FER-07: colaborador sem user vinculado pega o feriado sem local', function () {
    // `ponto_colaborador_config.user_id` é NOT NULL: o vínculo nulo só existe em memória
    // (mesmo idioma do UC-LIC-10). O ramo `empty($c->user_id)` do serviço é defensivo.
    $colab = ferColaborador($this->tenant->id, $this->user->id);
    $colab->user_id = null;
    ferCriarFeriado($this->tenant->id, $this->dia, $this->dia);

    $a = ferApuracaoComFalta($colab, $this->dia);
    $this->service->aplicarFeriado($a, $colab, \Carbon\Carbon::parse($this->dia));

    expect($a->falta_minutos)->toBe(0);
});

it('UC-FER-08: aplicarFeriado roda DEPOIS da tolerância e do DSR e ANTES do banco de horas', function () {
    $src = file_get_contents((new ReflectionClass(ApuracaoService::class))->getFileName());

    $posTolerancia = strpos($src, '$self->aplicarRegraTolerancia(');
    $posDsr        = strpos($src, '$self->aplicarRegraDsr(');
    $posFeriado    = strpos($src, '$self->aplicarFeriado(');
    $posBanco      = strpos($src, '$self->calcularBancoHoras(');

    expect($posFeriado)->not->toBeFalse();
    expect($posFeriado)->toBeGreaterThan($posTolerancia);
    expect($posFeriado)->toBeGreaterThan($posDsr);
    expect($posFeriado)->toBeLessThan($posBanco);
});

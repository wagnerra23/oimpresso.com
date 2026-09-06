<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Essentials\Entities\EssentialsLeave;
use Modules\Ponto\Entities\ApuracaoDia;
use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Services\ApuracaoService;
use Modules\Ponto\Tests\Feature\PontoTestCase;

uses(PontoTestCase::class);

/**
 * Contrato de `ApuracaoService::aplicarLicencas()` — HRM-O6 PR-7 / Onda 2 do plano
 * de integração Ponto × Essentials (`memory/sessions/2026-09-05-como-integrar-ponto-hrm.md`).
 *
 * A regra vem da EMENDA DE D3 por [W] em 2026-09-05 (`PEDIDO-CL-hrm.md`): licença aprovada
 * **não bloqueia** a marcação — ela **sinaliza** divergência e **sai da conta de ausência**.
 * A metade "bloqueia" da D3 caiu; a metade "sai da ausência" ficou, e é o que este arquivo trava.
 *
 * UC-LIC-06 é o caso que o desenho original erraria: o plano pedia a chamada ANTES de
 * `aplicarIntercorrencias()`, mas quem SETA `falta_minutos` é `aplicarRegraTolerancia()`,
 * que roda DEPOIS — o zero seria desfeito no caso principal (licença sem batida nenhuma).
 *
 * Tier 0 (ADR 0093): tenant fictício 98 vs adversário 99 (ADR 0358). NUNCA biz=4, nunca biz=1.
 * Sem `RefreshDatabase` — a lane `ponto-pest` proíbe (dropa o schema e limpa o seed).
 *
 * @see \Modules\Ponto\Services\ApuracaoService::aplicarLicencas
 */

const LIC_MARCADOR = 'PR7-LICENCA-ABONA';

function licPrecisaDe(array $tabelas): void
{
    foreach ($tabelas as $t) {
        if (! Schema::hasTable($t)) {
            test()->markTestSkipped("Tabela {$t} ausente — schema não migrado nesta lane.");
        }
    }
}

/** Colaborador do tenant dado, ligado a um user real (a ponte para `essentials_leaves`). */
function licColaborador(int $bizId, int $userId): Colaborador
{
    return Colaborador::forceCreate([
        'business_id'    => $bizId,
        'user_id'        => $userId,
        'matricula'      => LIC_MARCADOR . '-' . uniqid(),
        'controla_ponto' => true,
        'admissao'       => now()->subYear()->toDateString(),
    ]);
}

/**
 * Ids das licenças criadas nesta execução.
 *
 * `essentials_leaves` não tem coluna livre para marcador textual, e a lane do Ponto NÃO
 * usa transação (o `PontoTestCase` explica: 100+ migrations + triggers do UltimatePOS).
 * Então a limpeza é por id coletado — nunca por `where` em data ou tenant, que poderia
 * varrer licença legítima de outra suíte rodando no mesmo banco.
 */
$GLOBALS['lic_ids_criados'] = [];

/** Licença no HRM. `status` e datas são o contrato lido pelo Ponto. */
function licCriarLicenca(int $bizId, int $userId, string $inicio, string $fim, string $status = 'approved'): int
{
    // Entity e não `DB::table`: mesma razão do Service (o `catalog-graph` conta os dois eixos,
    // e manter um só par `Ponto>Essentials` — o de import — é mais honesto que ter dois).
    // INSERT não é filtrado pelo global scope, então `business_id` explícito permite montar
    // a fixture cross-tenant de que o UC-LIC-05 precisa.
    $id = (int) EssentialsLeave::create([
        'business_id'                => $bizId,
        'user_id'                    => $userId,
        'essentials_leave_type_id'   => null,
        'start_date'                 => $inicio,
        'end_date'                   => $fim,
        'status'                     => $status,
    ])->id;

    $GLOBALS['lic_ids_criados'][] = $id;

    return $id;
}

afterEach(function () {
    if (! empty($GLOBALS['lic_ids_criados'])) {
        // SUPERADMIN: a limpeza precisa alcançar a licença que o UC-LIC-05 gravou no tenant
        // adversário; com o global scope ligado ela ficaria para trás e sujaria o banco da lane.
        EssentialsLeave::withoutGlobalScopes()
            ->whereIn('id', $GLOBALS['lic_ids_criados'])
            ->delete();
        $GLOBALS['lic_ids_criados'] = [];
    }

    if (Schema::hasTable('ponto_colaborador_config')) {
        DB::table('ponto_colaborador_config')
            ->where('matricula', 'like', LIC_MARCADOR . '%')
            ->delete();
    }
});

/**
 * ApuracaoDia em memória no estado que `aplicarRegraTolerancia()` deixa num dia SEM batida:
 * falta cheia + divergência 'falta'. É esse estado que a licença tem de desfazer.
 */
function licApuracaoComFalta(Colaborador $c, string $data, int $cargaMinutos = 480): ApuracaoDia
{
    $a = new ApuracaoDia();
    $a->business_id             = $c->business_id;
    $a->colaborador_config_id   = $c->id;
    $a->data                    = $data;
    $a->prevista_carga_minutos  = $cargaMinutos;
    $a->qtd_marcacoes           = 0;
    $a->falta_minutos           = $cargaMinutos;
    $a->atraso_minutos          = 0;
    $a->saida_antecipada_minutos = 0;
    $a->divergencias            = [
        ['chave' => 'falta', 'mensagem' => 'Sem marcações em dia previsto de trabalho.'],
    ];

    return $a;
}

/** @return string[] as chaves de divergência da apuração */
function licChaves(ApuracaoDia $a): array
{
    return array_map(
        static fn ($d) => is_array($d) ? ($d['chave'] ?? '') : '',
        is_array($a->divergencias) ? $a->divergencias : []
    );
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (ADR 0358).');
    }
    licPrecisaDe(['ponto_colaborador_config', 'essentials_leaves']);

    $this->tenant = $this->seededTenant();
    $this->adversario = $this->seededSupportClientTenant();

    $user = \App\User::where('business_id', $this->tenant->id)->first();
    if (! $user) {
        $this->markTestSkipped('Sem user no tenant canônico — seed mínimo não rodou.');
    }
    $this->user = $user;
    $this->service = app(ApuracaoService::class);
    $this->dia = '2026-03-10';
});

// ── A metade que a emenda de D3 PRESERVA: sai da conta de ausência ──────────────

it('UC-LIC-01: dia coberto por licença aprovada deixa de contar falta', function () {
    $colab = licColaborador($this->tenant->id, $this->user->id);
    licCriarLicenca($this->tenant->id, $this->user->id, $this->dia, $this->dia);

    $a = licApuracaoComFalta($colab, $this->dia);
    expect($a->falta_minutos)->toBe(480); // estado que a tolerância deixou

    $this->service->aplicarLicencas($a, $colab, \Carbon\Carbon::parse($this->dia));

    expect($a->falta_minutos)->toBe(0);
    expect(licChaves($a))->toContain('licenca_abonou_dia');
});

it('UC-LIC-02: a divergência "falta" some — num dia de licença ela é falso-positivo', function () {
    $colab = licColaborador($this->tenant->id, $this->user->id);
    licCriarLicenca($this->tenant->id, $this->user->id, $this->dia, $this->dia);

    $a = licApuracaoComFalta($colab, $this->dia);
    expect(licChaves($a))->toContain('falta');

    $this->service->aplicarLicencas($a, $colab, \Carbon\Carbon::parse($this->dia));

    // O RH não pode ver "Sem marcações em dia previsto" num dia legitimamente abonado.
    expect(licChaves($a))->not->toContain('falta');
});

it('UC-LIC-03: licença NÃO aprovada não abona — só `approved` conta', function () {
    $colab = licColaborador($this->tenant->id, $this->user->id);
    licCriarLicenca($this->tenant->id, $this->user->id, $this->dia, $this->dia, 'pending');

    $a = licApuracaoComFalta($colab, $this->dia);
    $this->service->aplicarLicencas($a, $colab, \Carbon\Carbon::parse($this->dia));

    expect($a->falta_minutos)->toBe(480);
    expect(licChaves($a))->toContain('falta');
});

it('UC-LIC-04: dia FORA do intervalo da licença não é abonado', function () {
    $colab = licColaborador($this->tenant->id, $this->user->id);
    // licença termina na véspera
    licCriarLicenca($this->tenant->id, $this->user->id, '2026-03-01', '2026-03-09');

    $a = licApuracaoComFalta($colab, $this->dia);
    $this->service->aplicarLicencas($a, $colab, \Carbon\Carbon::parse($this->dia));

    expect($a->falta_minutos)->toBe(480);
});

// ── Tier 0 ──────────────────────────────────────────────────────────────────────

it('UC-LIC-05: licença do tenant ADVERSÁRIO não abona o dia (ADR 0093)', function () {
    $colab = licColaborador($this->tenant->id, $this->user->id);

    // Mesmo user_id e mesma data, mas gravada no business do vizinho. Não há FK que
    // impeça a linha de existir — por isso o filtro por business_id na query é o que
    // separa os dois tenants, e é exatamente isso que este caso trava.
    licCriarLicenca($this->adversario->id, $this->user->id, $this->dia, $this->dia);

    $a = licApuracaoComFalta($colab, $this->dia);
    $this->service->aplicarLicencas($a, $colab, \Carbon\Carbon::parse($this->dia));

    expect($a->falta_minutos)->toBe(480);
    expect(licChaves($a))->not->toContain('licenca_abonou_dia');
});

// ── A ORDEM: o achado que o desenho original erraria ────────────────────────────

it('UC-LIC-06: aplicarLicencas roda DEPOIS da tolerância e ANTES do banco de horas', function () {
    $src = file_get_contents(
        (new ReflectionClass(ApuracaoService::class))->getFileName()
    );

    $posTolerancia = strpos($src, '$self->aplicarRegraTolerancia(');
    $posLicencas   = strpos($src, '$self->aplicarLicencas(');
    $posBanco      = strpos($src, '$self->calcularBancoHoras(');

    expect($posTolerancia)->not->toBeFalse();
    expect($posLicencas)->not->toBeFalse();
    expect($posBanco)->not->toBeFalse();

    // Quem SETA falta_minutos é a tolerância (`qtd_marcacoes === 0` → falta = carga).
    // Chamar aplicarLicencas antes dela deixaria o zero ser desfeito no caso principal.
    expect($posLicencas)->toBeGreaterThan($posTolerancia);
    // E calcularBancoHoras converte falta em DÉBITO — tem de ver a falta já zerada.
    expect($posLicencas)->toBeLessThan($posBanco);
});

it('UC-LIC-07: dia de licença não gera débito de banco de horas', function () {
    $colab = licColaborador($this->tenant->id, $this->user->id);
    licCriarLicenca($this->tenant->id, $this->user->id, $this->dia, $this->dia);

    $a = licApuracaoComFalta($colab, $this->dia);
    $a->banco_horas_debito_minutos = 0;

    $this->service->aplicarLicencas($a, $colab, \Carbon\Carbon::parse($this->dia));
    // calcularBancoHoras() debita `falta_minutos + saida_antecipada_minutos`; com os dois
    // zerados pela licença, o débito do dia é zero — o colaborador não fica devendo horas
    // por um dia que o RH já aprovou.
    $this->service->calcularBancoHoras($a, $colab);

    expect($a->banco_horas_debito_minutos)->toBe(0);
});

// ── A metade que a emenda de D3 SUBSTITUI: sinaliza, não bloqueia ───────────────

it('UC-LIC-08: bateu ponto em dia de licença → sinaliza divergência, não recusa', function () {
    $colab = licColaborador($this->tenant->id, $this->user->id);
    $id = licCriarLicenca($this->tenant->id, $this->user->id, $this->dia, $this->dia);

    $a = licApuracaoComFalta($colab, $this->dia);
    $a->qtd_marcacoes = 2;   // a batida existe — veio do REP e está no arquivo fiscal
    $a->falta_minutos = 0;

    $this->service->aplicarLicencas($a, $colab, \Carbon\Carbon::parse($this->dia));

    // [W] 2026-09-05: nenhuma origem é recusada. A marcação entra marcada e o gestor trata.
    expect(licChaves($a))->toContain('marcacao_em_dia_de_licenca');
    expect(licChaves($a))->toContain('licenca_abonou_dia');
});

it('UC-LIC-09: sem batida, NÃO sinaliza marcação em dia de licença', function () {
    $colab = licColaborador($this->tenant->id, $this->user->id);
    licCriarLicenca($this->tenant->id, $this->user->id, $this->dia, $this->dia);

    $a = licApuracaoComFalta($colab, $this->dia); // qtd_marcacoes = 0
    $this->service->aplicarLicencas($a, $colab, \Carbon\Carbon::parse($this->dia));

    expect(licChaves($a))->not->toContain('marcacao_em_dia_de_licenca');
});

it('UC-LIC-10: colaborador sem user_id vinculado não quebra a apuração', function () {
    $colab = licColaborador($this->tenant->id, $this->user->id);
    $colab->user_id = null;

    $a = licApuracaoComFalta($colab, $this->dia);
    $this->service->aplicarLicencas($a, $colab, \Carbon\Carbon::parse($this->dia));

    expect($a->falta_minutos)->toBe(480); // sem vínculo, nada a abonar — e sem exceção
});

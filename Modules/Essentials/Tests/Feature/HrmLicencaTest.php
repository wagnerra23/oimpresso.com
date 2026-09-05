<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Essentials\Entities\EssentialsLeave;
use Modules\Essentials\Entities\EssentialsLeaveType;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * HRM · Licenças — prova mínima do trio (PR-1 da onda HRM-O5).
 *
 * PROVENIÊNCIA: os casos UC-HRM-23/02/15/05/03/09/18 vieram do PR #6800, que os
 * escreveu como PR-1. Aquele PR foi reduzido a documentação e deixou de aterrissar
 * este arquivo, então ele desce aqui — com dois casos acrescentados no fim, que
 * cobrem o que o PR-2/PR-3 fecha e não estava coberto.
 *
 * Dono do tema: prototipo-ui/design-docs/cowork-inbox/hrm/PEDIDO-CL-hrm.md
 * Trio: Modules/Essentials/Resources/js/Pages/Hrm/Licencas/{Index.charter.md,Index.casos.md}
 *
 * ────────────────────────────────────────────────────────────────────────────
 * NASCE VERMELHO DE PROPÓSITO — e o vermelho é a ENTREGA, não o defeito.
 * ────────────────────────────────────────────────────────────────────────────
 * Seis casos provam achados lidos no controller: A2 (store sem FormRequest),
 * A3 (max_leave_count nunca aplicado) e A4 (destroy de tipo com corpo vazio).
 * Ficam verdes com os PRs 2, 3 e 5 do pedido. NÃO remover da allowlist do
 * .github/workflows/essentials-pest.yml pra a lane ficar verde — conserta-se o
 * servidor, que é o ponto.
 *
 * ⚠️ O QUE O TESTE ASSERTA, E POR QUE ASSIM (2026-09-04):
 * O `store()` devolve ARRAY e o try/catch engole exceção → HTTP 200 mesmo em
 * erro. Então `assertStatus(422)` sozinho não distingue "o servidor não valida"
 * (o achado) de "o gate de assinatura barrou com 403" (ambiente). Por isso cada
 * caso vermelho asserta DUAS coisas — o status desejado E o efeito no banco — e
 * existe o UC-HRM-23, um CONTROLE POSITIVO: se ele falhar, o diagnóstico é de
 * ambiente, não de achado (§5 2026-08-01 — controle positivo antes de confiar
 * no resultado).
 *
 * Tier 0 (ADR 0093 + ADR 0358): tenant canônico de teste é o 98 (fictício), com
 * o 2 como tenant alheio — os dois são semeados por .github/actions/pest-mysql-setup.
 * NUNCA biz=4 (ROTA LIVRE, cliente real).
 *
 * COMPLEMENTA, não duplica, o MultiTenantLeaveTest: aquele prova isolamento na
 * QUERY Eloquent (listagem/show/update/destroy scoped); este prova o eixo que
 * falta — o WRITE por HTTP com id cru de outro tenant no corpo (LC-19).
 *
 * DatabaseTransactions (nunca RefreshDatabase): a lane compartilha o seed e o
 * RefreshDatabase dropa o schema, envenenando as outras suítes.
 *
 * @group hrm
 */
const HRML_BIZ = 98;         // tenant canônico de teste (ADR 0358)
const HRML_BIZ_ALHEIO = 2;   // segundo tenant do seed — Tier 0 cross-tenant

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (lane essentials-pest).');
    }
    foreach (['essentials_leaves', 'essentials_leave_types', 'business', 'users'] as $tbl) {
        if (! Schema::hasTable($tbl)) {
            $this->markTestSkipped("Tabela {$tbl} ausente — rode o migrate do módulo Essentials.");
        }
    }

    $user = User::where('business_id', HRML_BIZ)->first();
    if (! $user) {
        $this->markTestSkipped('Sem user em business_id=98 — o seed canônico (pest-mysql-setup) não rodou.');
    }
    $this->hUser = $user;

    // Admin#98 → Gate::before autoriza as cláusulas de permissão do controller
    // (crud_all_leave / approve_leave), isolando o teste na VALIDAÇÃO, não na
    // permissão. Idempotente; rollback via DatabaseTransactions.
    $role = Role::firstOrCreate(
        ['name' => 'Admin#'.HRML_BIZ, 'guard_name' => 'web'],
        ['business_id' => HRML_BIZ]
    );
    if (! $user->hasRole($role->name)) {
        $user->assignRole($role);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // NÃO setar session manualmente: as rotas /hrm rodam SetSessionData DEPOIS
    // do auth e reconstroem user.business_id a partir do usuário autenticado.
    // (Padrão comprovado em SalesTargetShiftCrossTenantTest.)
    session()->flush();
    $this->actingAs($user);
});

/**
 * Data no formato que o controller espera. `ModuleUtil::uf_date` lê
 * `session('business.date_format')` (default 'm/d/Y' no schema baseline) — fixar
 * literal no teste quebra quando o seed muda, e '21/09/2026' em m/d/Y é MÊS 21.
 */
function hrmlData(string $iso): string
{
    return Carbon::parse($iso)->format(session('business.date_format') ?: 'm/d/Y');
}

function hrmlTipo(int $biz, ?int $max = null, ?string $intervalo = null): EssentialsLeaveType
{
    // INSERT não é filtrado pelo global scope; business_id explícito cria no
    // tenant desejado (inclusive no alheio, de propósito, no UC-HRM-05).
    return EssentialsLeaveType::create([
        'business_id' => $biz,
        'leave_type' => 'HRML-'.$biz.'-'.uniqid(),
        'max_leave_count' => $max,
        'leave_count_interval' => $intervalo,
    ]);
}

function hrmlLicenca(int $biz, int $userId, int $tipoId, string $inicioIso, string $fimIso, string $status = 'approved'): EssentialsLeave
{
    return EssentialsLeave::create([
        'business_id' => $biz,
        'user_id' => $userId,
        'essentials_leave_type_id' => $tipoId,
        'ref_no' => 'HRML-'.uniqid(),
        'start_date' => $inicioIso,
        'end_date' => $fimIso,
        'reason' => 'fixture do HrmLicencaTest',
        'status' => $status,
    ]);
}

/** Conta sem o global scope — o teste precisa enxergar os dois tenants. */
function hrmlContaLicencas(int $biz): int
{
    return EssentialsLeave::withoutGlobalScopes()->where('business_id', $biz)->count();
}

// ─────────────────────────────────────────────────────────────────────────────
// UC-HRM-23 · CONTROLE POSITIVO (canário). Deve passar HOJE.
// ─────────────────────────────────────────────────────────────────────────────
it('UC-HRM-23 · canário: pedido VÁLIDO cria a licença (prova que o caminho funciona)', function () {
    $tipo = hrmlTipo(HRML_BIZ);
    $antes = hrmlContaLicencas(HRML_BIZ);

    $resp = $this->post('/hrm/leave', [
        'essentials_leave_type_id' => $tipo->id,
        'start_date' => hrmlData('2026-09-07'),
        'end_date' => hrmlData('2026-09-09'),
        'reason' => 'canário — pedido válido',
    ]);

    // Se ESTE caso falhar, o diagnóstico NÃO é "o servidor valida agora": é
    // ambiente. 403 = gate de assinatura (essentials_module) barrou; 500 =
    // date_format/sessão. Nesse caso os vermelhos abaixo não provam nada.
    expect($resp->status())->toBe(200);
    expect(hrmlContaLicencas(HRML_BIZ))->toBe($antes + 1);
});

// ─────────────────────────────────────────────────────────────────────────────
// A2 · store() sem FormRequest — UC-HRM-02, UC-HRM-15, UC-HRM-05
// ─────────────────────────────────────────────────────────────────────────────
it('UC-HRM-02 · recusa licença com fim ANTES do início [nasce vermelho · A2]', function () {
    $tipo = hrmlTipo(HRML_BIZ);
    $antes = hrmlContaLicencas(HRML_BIZ);

    $resp = $this->post('/hrm/leave', [
        'essentials_leave_type_id' => $tipo->id,
        'start_date' => hrmlData('2026-09-10'),
        'end_date' => hrmlData('2026-09-01'),
        'reason' => 'período invertido',
    ]);

    expect($resp->status())->toBe(422);
    expect(hrmlContaLicencas(HRML_BIZ))->toBe($antes);
});

it('UC-HRM-15 · recusa licença sem motivo e sem tipo [nasce vermelho · A2]', function () {
    $antes = hrmlContaLicencas(HRML_BIZ);

    $resp = $this->post('/hrm/leave', [
        'start_date' => hrmlData('2026-09-01'),
        'end_date' => hrmlData('2026-09-02'),
        'reason' => '',
    ]);

    expect($resp->status())->toBe(422);
    expect(hrmlContaLicencas(HRML_BIZ))->toBe($antes);
});

it('UC-HRM-05 · Tier 0: tipo de licença de OUTRO negócio é recusado [nasce vermelho · A2 · ADR 0093]', function () {
    // O tipo existe de verdade, só que no tenant alheio — é o caso perigoso:
    // `exists` sem escopo de business aceitaria este id.
    $tipoAlheio = hrmlTipo(HRML_BIZ_ALHEIO);
    $antes = hrmlContaLicencas(HRML_BIZ);

    $resp = $this->post('/hrm/leave', [
        'essentials_leave_type_id' => $tipoAlheio->id,
        'start_date' => hrmlData('2026-09-01'),
        'end_date' => hrmlData('2026-09-02'),
        'reason' => 'tipo de outro tenant',
    ]);

    expect($resp->status())->toBe(422);
    expect(hrmlContaLicencas(HRML_BIZ))->toBe($antes);

    // O que dói de verdade: a licença do tenant 98 apontando pro tipo do 2.
    $vazou = EssentialsLeave::withoutGlobalScopes()
        ->where('business_id', HRML_BIZ)
        ->where('essentials_leave_type_id', $tipoAlheio->id)
        ->count();
    expect($vazou)->toBe(0);
});

// ─────────────────────────────────────────────────────────────────────────────
// A3 · max_leave_count nunca aplicado — UC-HRM-03 (pedir) e UC-HRM-09 (aprovar)
// ─────────────────────────────────────────────────────────────────────────────
it('UC-HRM-03 · recusa pedido que estoura o limite do tipo [nasce vermelho · A3]', function () {
    $tipo = hrmlTipo(HRML_BIZ, 30, 'year');
    // 22 dias já aprovados (06/01 a 27/01 = diffInDays + 1, R6).
    hrmlLicenca(HRML_BIZ, $this->hUser->id, $tipo->id, '2026-01-06', '2026-01-27');
    $antes = hrmlContaLicencas(HRML_BIZ);

    // +15 dias (07/09 a 21/09) = 37 > 30.
    $resp = $this->post('/hrm/leave', [
        'essentials_leave_type_id' => $tipo->id,
        'start_date' => hrmlData('2026-09-07'),
        'end_date' => hrmlData('2026-09-21'),
        'reason' => 'férias que estouram o limite',
    ]);

    expect($resp->status())->toBe(422);
    expect(hrmlContaLicencas(HRML_BIZ))->toBe($antes);
});

it('UC-HRM-09 · recusa APROVAR licença que estoura o limite do tipo [nasce vermelho · A3]', function () {
    $tipo = hrmlTipo(HRML_BIZ, 30, 'year');
    hrmlLicenca(HRML_BIZ, $this->hUser->id, $tipo->id, '2026-01-06', '2026-01-27'); // 22 aprovados
    $pendente = hrmlLicenca(HRML_BIZ, $this->hUser->id, $tipo->id, '2026-09-07', '2026-09-21', 'pending'); // +15

    $resp = $this->post('/hrm/change-status', [
        'leave_id' => $pendente->id,
        'status' => 'approved',
        'status_note' => 'aprovando além do limite',
    ]);

    expect($resp->status())->toBe(422);
    // O efeito é o que importa: a licença NÃO pode ter virado aprovada.
    $depois = EssentialsLeave::withoutGlobalScopes()->find($pendente->id);
    expect($depois->status)->toBe('pending');
});

// ─────────────────────────────────────────────────────────────────────────────
// A4 · EssentialsLeaveTypeController::destroy() com corpo vazio — UC-HRM-18
// ─────────────────────────────────────────────────────────────────────────────
it('UC-HRM-18 · recusa excluir tipo de licença EM USO, dizendo o motivo [nasce vermelho · A4]', function () {
    $tipo = hrmlTipo(HRML_BIZ);
    hrmlLicenca(HRML_BIZ, $this->hUser->id, $tipo->id, '2026-03-02', '2026-03-04');

    $resp = $this->delete('/hrm/leave-type/'.$tipo->id);

    // Hoje o método é `public function destroy() {}` — responde 200 e não faz
    // nada. O PR-5 implementa a guarda de uso: 422 dizendo quantas licenças travam.
    expect($resp->status())->toBe(422);
    // E o tipo continua de pé nos dois mundos (o de hoje e o do PR-5) — o que
    // separa os dois é o STATUS, não o efeito. Assertar só isto seria carimbo.
    expect(EssentialsLeaveType::withoutGlobalScopes()->find($tipo->id))->not->toBeNull();
});

// ─────────────────────────────────────────────────────────────────────────────
// Acrescentados pelo PR-2/PR-3 — cobrem o que o conserto fecha e não estava aqui.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Tier 0 (ADR 0093) — `employees[]` com id de OUTRO negócio.
 *
 * Eixo distinto do UC-HRM-05: lá o id alheio é o do TIPO, aqui é o do
 * COLABORADOR, e ele entrava direto no `foreach` do `store()`. O global scope
 * filtra SELECT e não impede INSERT, então sem o gate nascia licença no
 * colaborador de outro tenant.
 */
it('Tier 0 · employees[] com colaborador de OUTRO negócio é recusado [A2 · ADR 0093]', function () {
    $tipo = hrmlTipo(HRML_BIZ);

    $alheio = User::where('business_id', HRML_BIZ_ALHEIO)->first();
    if ($alheio === null) {
        $this->markTestSkipped('Sem usuário no tenant '.HRML_BIZ_ALHEIO.' — seed mínimo não rodou.');
    }

    $antesAlheio = hrmlContaLicencas(HRML_BIZ_ALHEIO);

    $this->post('/hrm/leave', [
        'essentials_leave_type_id' => $tipo->id,
        'start_date' => hrmlData('2099-09-01'),
        'end_date' => hrmlData('2099-09-02'),
        'reason' => 'colaborador de outro negócio',
        'employees' => [$alheio->id],
    ])->assertStatus(422);

    expect(hrmlContaLicencas(HRML_BIZ_ALHEIO))->toBe($antesAlheio);
});

/**
 * UC-HRM-19 — limite 0 é "sem limite", não "zero dias permitidos".
 *
 * O casos.md diz que um tipo com limite 0 mostra "sem limite" na lista e no
 * saldo; a regra do servidor tem de concordar, senão o tipo fica inutilizável.
 */
it('UC-HRM-19 · tipo com limite 0 não bloqueia (0 = sem limite)', function () {
    $tipo = hrmlTipo(HRML_BIZ, 0, 'year');

    hrmlLicenca(HRML_BIZ, (int) $this->hUser->id, (int) $tipo->id, '2099-01-06', '2099-03-27');
    $antes = hrmlContaLicencas(HRML_BIZ);

    $this->post('/hrm/leave', [
        'essentials_leave_type_id' => $tipo->id,
        'start_date' => hrmlData('2099-09-07'),
        'end_date' => hrmlData('2099-09-21'),
        'reason' => 'tipo sem limite',
    ])->assertStatus(200);

    expect(hrmlContaLicencas(HRML_BIZ))->toBe($antes + 1);
});

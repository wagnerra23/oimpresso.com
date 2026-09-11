<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Essentials\Entities\EssentialsAttendance;
use Modules\Essentials\Entities\Shift;
use Modules\Essentials\Services\AttendanceImportService;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * HRM-O6 / PR-6 — import de presença (achado A7).
 *
 * O que estes testes provam, na ordem do risco:
 *
 *  1. **Tier 0 (ADR 0093)** — linha cujo e-mail pertence a colaborador de OUTRO negócio é
 *     RECUSADA, e nenhuma marcação nasce para aquele usuário. Idem para turno de outro
 *     negócio. É o teste que o pedido exige explicitamente.
 *  2. **Relatório em vez de rollback total** — o legado dava `break` no primeiro defeito e
 *     desfazia o lote inteiro. Aqui as linhas boas entram e as ruins voltam com número da
 *     linha e motivo.
 *  3. **A checagem de sobreposição do formulário agora vale no import** — contra o banco e
 *     contra as outras linhas do próprio arquivo.
 *
 * Tenants (ADR 0358, supersede a 0101): 98 = tenant canônico de teste (empresa FICTÍCIA);
 * 99 = a outra empresa fictícia, usada aqui como adversário cross-tenant. NUNCA biz=4.
 *
 * @see Modules\Essentials\Services\AttendanceImportService
 * @see prototipo-ui/design-docs/cowork-inbox/hrm/PEDIDO-CL-hrm.md (PR-6)
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (ADR 0358).');
    }

    foreach (['business', 'users', 'essentials_attendances', 'essentials_shifts'] as $tabela) {
        if (! Schema::hasTable($tabela)) {
            $this->markTestSkipped("Tabela {$tabela} ausente — rode migrate Modules/Essentials.");
        }
    }

    $this->tenant = $this->seededTenant();
    $this->adversario = $this->seededSupportClientTenant();

    if ((int) $this->tenant->id === (int) $this->adversario->id) {
        $this->markTestSkipped('Tenant e adversário colidiram — o seed mínimo (biz=98/99) não rodou.');
    }

    // Usuários criados aqui (e não reaproveitados do seed) porque o teste precisa de
    // e-mail conhecido dos DOIS lados: é o e-mail que o import resolve, e o seed do CI
    // cria usuário sem e-mail. DatabaseTransactions desfaz tudo no fim.
    $this->emailProprio = 'import-presenca-proprio-'.uniqid().'@example.test';
    $this->emailEstranho = 'import-presenca-estranho-'.uniqid().'@example.test';

    $this->userProprio = criarColaborador((int) $this->tenant->id, $this->emailProprio);
    $this->userEstranho = criarColaborador((int) $this->adversario->id, $this->emailEstranho);

    $this->service = app(AttendanceImportService::class);
});

/** Cria um colaborador mínimo no business informado e devolve o id. */
function criarColaborador(int $businessId, string $email): int
{
    return (int) DB::table('users')->insertGetId([
        'business_id' => $businessId,
        'first_name' => 'Colab '.$businessId,
        'username' => 'imp_'.$businessId.'_'.uniqid(),
        'email' => $email,
        'password' => bcrypt('ci'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** Monta uma linha crua na ordem do template (7 colunas). */
function linhaPresenca(?string $email, ?string $entrada, ?string $saida = null, ?string $turno = null): array
{
    return [$email, $entrada, $saida, $turno, null, null, null];
}

/** Conta marcações de um usuário sem o global scope (o teste roda sem sessão de business). */
function marcacoesDe(int $userId): int
{
    return EssentialsAttendance::withoutGlobalScopes()->where('user_id', $userId)->count();
}

it('Tier 0: linha com e-mail de colaborador de OUTRO negócio é recusada e não grava marcação', function () {
    $antes = marcacoesDe($this->userEstranho);

    $relatorio = $this->service->importar((int) $this->tenant->id, [
        linhaPresenca($this->emailEstranho, '2026-09-01 08:00:00', '2026-09-01 12:00:00'),
    ]);

    expect($relatorio['inseridas'])->toBe(0)
        ->and($relatorio['recusadas'])->toHaveCount(1)
        ->and($relatorio['recusadas'][0]['linha'])->toBe(2);

    // A prova Tier 0: nada nasceu para o colaborador do outro negócio.
    expect(marcacoesDe($this->userEstranho))->toBe($antes);

    // E nada nasceu "solto" dentro do tenant importador com aquele user_id.
    expect(EssentialsAttendance::withoutGlobalScopes()
        ->where('business_id', $this->tenant->id)
        ->where('user_id', $this->userEstranho)
        ->count())->toBe(0);
});

it('Tier 0: turno de OUTRO negócio é recusado — não vira marcação sem turno nem com o turno alheio', function () {
    $turnoAlheio = Shift::withoutGlobalScopes()->create([
        'business_id' => $this->adversario->id,
        'name' => 'Turno Alheio '.uniqid(),
        'type' => 'fixed_shift',
        'start_time' => '08:00:00',
        'end_time' => '17:00:00',
    ]);

    $relatorio = $this->service->importar((int) $this->tenant->id, [
        linhaPresenca($this->emailProprio, '2026-09-02 08:00:00', '2026-09-02 12:00:00', $turnoAlheio->name),
    ]);

    expect($relatorio['inseridas'])->toBe(0)
        ->and($relatorio['recusadas'])->toHaveCount(1);

    expect(marcacoesDe($this->userProprio))->toBe(0);
});

it('relatório em vez de rollback: as linhas boas entram e só as ruins voltam, com o número da linha', function () {
    $relatorio = $this->service->importar((int) $this->tenant->id, [
        linhaPresenca($this->emailProprio, '2026-09-03 08:00:00', '2026-09-03 12:00:00'), // linha 2 — boa
        linhaPresenca('nao-existe-'.uniqid().'@example.test', '2026-09-03 08:00:00'),     // linha 3 — ruim
        linhaPresenca($this->emailProprio, '2026-09-03 13:00:00', '2026-09-03 17:00:00'), // linha 4 — boa
    ]);

    // O legado descartaria as três (break + rollback). Aqui duas entram.
    expect($relatorio['total'])->toBe(3)
        ->and($relatorio['inseridas'])->toBe(2)
        ->and($relatorio['recusadas'])->toHaveCount(1)
        ->and($relatorio['recusadas'][0]['linha'])->toBe(3)
        ->and($relatorio['recusadas'][0]['motivo'])->not->toBe('');

    expect(marcacoesDe($this->userProprio))->toBe(2);
});

it('sobreposição com marcação JÁ existente no banco é recusada (a checagem do formulário agora vale no import)', function () {
    EssentialsAttendance::withoutGlobalScopes()->create([
        'business_id' => $this->tenant->id,
        'user_id' => $this->userProprio,
        'clock_in_time' => '2026-09-04 08:00:00',
        'clock_out_time' => '2026-09-04 12:00:00',
    ]);

    $relatorio = $this->service->importar((int) $this->tenant->id, [
        linhaPresenca($this->emailProprio, '2026-09-04 09:00:00', '2026-09-04 10:00:00'),
    ]);

    expect($relatorio['inseridas'])->toBe(0)
        ->and($relatorio['recusadas'])->toHaveCount(1);

    // Continua só a marcação original.
    expect(marcacoesDe($this->userProprio))->toBe(1);
});

it('sobreposição entre duas linhas do MESMO arquivo: a primeira entra, a segunda é recusada', function () {
    $relatorio = $this->service->importar((int) $this->tenant->id, [
        linhaPresenca($this->emailProprio, '2026-09-05 08:00:00', '2026-09-05 12:00:00'),
        linhaPresenca($this->emailProprio, '2026-09-05 11:00:00', '2026-09-05 15:00:00'),
    ]);

    expect($relatorio['inseridas'])->toBe(1)
        ->and($relatorio['recusadas'])->toHaveCount(1)
        ->and($relatorio['recusadas'][0]['linha'])->toBe(3);

    expect(marcacoesDe($this->userProprio))->toBe(1);
});

it('data ilegível é recusada em vez de virar datetime lixo no banco', function () {
    $relatorio = $this->service->importar((int) $this->tenant->id, [
        linhaPresenca($this->emailProprio, 'ontem de manhã'),
        linhaPresenca($this->emailProprio, '2026-09-06 08:00:00', 'qualquer coisa'),
        linhaPresenca($this->emailProprio, '2026-09-06 12:00:00', '2026-09-06 08:00:00'), // saída antes da entrada
        linhaPresenca(null, '2026-09-06 08:00:00'),                                        // e-mail vazio
    ]);

    expect($relatorio['inseridas'])->toBe(0)
        ->and($relatorio['recusadas'])->toHaveCount(4);

    expect(marcacoesDe($this->userProprio))->toBe(0);
});

it('entrada sem saída entra (marcação aberta é válida) e não colide com outro horário do dia', function () {
    $relatorio = $this->service->importar((int) $this->tenant->id, [
        linhaPresenca($this->emailProprio, '2026-09-07 08:00:00'),
        linhaPresenca($this->emailProprio, '2026-09-07 14:00:00', '2026-09-07 18:00:00'),
    ]);

    expect($relatorio['inseridas'])->toBe(2)
        ->and($relatorio['recusadas'])->toBeEmpty();

    expect(marcacoesDe($this->userProprio))->toBe(2);
});

it('ponta a ponta pelo POST /hrm/import-attendance: linha boa entra, linha cross-tenant volta no relatório', function () {
    Storage::fake('local');

    // Admin#{tenant} → passa o gate de permissão do controller, isolando o teste no
    // comportamento do import (mesmo padrão do SalesTargetShiftCrossTenantTest).
    $papel = Role::firstOrCreate(
        ['name' => 'Admin#'.$this->tenant->id, 'guard_name' => 'web'],
        ['business_id' => $this->tenant->id]
    );
    // `App\User` não tem global scope de business (medido), então não há o que escapar.
    $ator = App\User::findOrFail($this->userProprio);
    if (! $ator->hasRole($papel->name)) {
        $ator->assignRole($papel);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    session()->flush();
    $this->actingAs($ator);

    $csv = implode("\n", [
        'Email,Clock-in Time,Clock-out Time,Shift,Clock-in note,Clock-out note,IP address',
        $this->emailProprio.',2026-09-08 08:00:00,2026-09-08 12:00:00,,,,',
        $this->emailEstranho.',2026-09-08 08:00:00,2026-09-08 12:00:00,,,,',
    ])."\n";

    $resposta = $this->post('/hrm/import-attendance', [
        'attendance' => UploadedFile::fake()->createWithContent('presenca.csv', $csv),
    ]);

    $resposta->assertRedirect();

    // Com QUEUE_CONNECTION=sync (o do CI) o Job roda inline e o relatório volta na sessão.
    $relatorio = session('import_presenca_relatorio');

    if ($relatorio === null) {
        // Conexão assíncrona: o teste não pode afirmar sobre o resultado sem worker.
        $this->markTestSkipped('QUEUE_CONNECTION assíncrona — o Job não roda inline neste ambiente.');
    }

    expect($relatorio['inseridas'])->toBe(1)
        ->and($relatorio['recusadas'])->toHaveCount(1);

    expect(marcacoesDe($this->userProprio))->toBe(1);
    // A prova Tier 0 no caminho HTTP real.
    expect(marcacoesDe($this->userEstranho))->toBe(0);
});

it('serial numerico degenerado (0 / negativo) e recusado em vez de virar marcacao em 1970', function () {
    // Medido no PhpSpreadsheet: serial 0 devolve 1970-01-01 e -5 devolve 1969-12-27 —
    // datas plausíveis o bastante pra passar despercebidas numa planilha de jornada.
    $relatorio = $this->service->importar((int) $this->tenant->id, [
        [$this->emailProprio, 0, null, null, null, null, null],
        [$this->emailProprio, -5, null, null, null, null, null],
    ]);

    expect($relatorio['inseridas'])->toBe(0)
        ->and($relatorio['recusadas'])->toHaveCount(2);

    expect(marcacoesDe($this->userProprio))->toBe(0);
});

it('serial numerico VALIDO do Excel continua entrando (o guard nao recusa data real)', function () {
    // 46266.354166667 => 2026-09-01 08:30:00 (medido). Controle positivo do guard acima:
    // sem ele, o teste anterior passaria mesmo que o caminho numérico estivesse quebrado.
    $relatorio = $this->service->importar((int) $this->tenant->id, [
        [$this->emailProprio, 46266.354166667, null, null, null, null, null],
    ]);

    expect($relatorio['inseridas'])->toBe(1)
        ->and($relatorio['recusadas'])->toBeEmpty();

    $marcacao = EssentialsAttendance::withoutGlobalScopes()
        ->where('user_id', $this->userProprio)
        ->firstOrFail();

    expect((string) $marcacao->clock_in_time)->toContain('2026-09-01 08:30:00');
});

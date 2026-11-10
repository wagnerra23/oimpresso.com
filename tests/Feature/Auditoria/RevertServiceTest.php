<?php

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Auditoria\Services\RevertCheck;
use Modules\Auditoria\Services\RevertService;
use Spatie\Activitylog\Models\Activity;

/**
 * US-AUDIT-008 — RevertService whitelist UNREVERTIBLE + niveis permissao.
 *
 * Valida (per SPEC + ADR 0127):
 *   1. Activity de outro business -> deny (Tier 0)
 *   2. Subject_type Marcacao -> deny ('Portaria 671 append-only')
 *   3. Cada categoria irreversivel, com a classe real e linha real -> deny
 *   4. Sem permissao auditoria.revert.* -> deny
 *   5. Janela 24h expirada com so revert.own -> deny
 *   6. Reason < 10 chars -> InvalidArgumentException
 *   7. Activity sem properties.old -> DomainException
 *
 * Skip-graceful em sqlite memory (CI). Validacao real com mysql dev pre-merge.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    try {
        $hasColumn = Schema::hasColumn('activity_log', 'causer_kind');
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema activity_log incompleto — rode migrations primeiro (US-AUDIT-005).');
    }
    if (! $hasColumn) {
        $this->markTestSkipped('Coluna causer_kind nao existe.');
    }

    $this->business = $this->seededTenant(); // biz=1 canônico (ADR 0101) — skip acionável se o seed faltar

    $this->user = \App\User::where('business_id', $this->business->id)->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business.');
    }

    $this->service = new RevertService();
});

it('cenario 1: RevertCheck deny() retorna allowed=false com reason', function () {
    $check = RevertCheck::deny('Motivo qualquer.');
    expect($check->allowed)->toBeFalse();
    expect($check->reason)->toBe('Motivo qualquer.');
});

it('cenario 2: RevertCheck allow() retorna allowed=true sem reason', function () {
    $check = RevertCheck::allow();
    expect($check->allowed)->toBeTrue();
    expect($check->reason)->toBeNull();
});

/**
 * Insere uma linha real preenchendo só as colunas obrigatórias sem default, para o
 * teste não depender do schema inteiro de cada módulo. FK desligada só no INSERT: o
 * que se mede é a trava do canRevert, não a árvore de cadastros de cada tabela.
 */
function revertLinha(string $tabela, array $valores): mixed
{
    foreach (Schema::getColumns($tabela) as $c) {
        if (array_key_exists($c['name'], $valores) || $c['nullable'] || $c['default'] !== null
            || $c['auto_increment'] || $c['generation'] !== null) {
            continue;
        }
        $tipo = strtolower($c['type_name']);
        preg_match("/^enum\('([^']*)'/", (string) $c['type'], $enum);
        preg_match('/\((\d+)\)/', (string) $c['type'], $tam);
        $valores[$c['name']] = match (true) {
            $tipo === 'enum' => $enum[1],
            in_array($tipo, ['tinyint', 'smallint', 'bit'], true) => 1,
            in_array($tipo, ['int', 'bigint', 'mediumint'], true) => random_int(1000000, 9999999),
            in_array($tipo, ['decimal', 'float', 'double'], true) => 0,
            $tipo === 'date' => '2026-01-15',
            in_array($tipo, ['datetime', 'timestamp'], true) => '2026-01-15 10:00:00',
            $tipo === 'time' => '10:00:00',
            $tipo === 'json' => '{}',
            str_contains($tipo, 'text') || str_contains($tipo, 'blob') => '',
            default => substr(md5(uniqid('', true)), 0, min((int) ($tam[1] ?? 32), 32)),
        };
    }

    Schema::disableForeignKeyConstraints();
    try {
        if (array_key_exists('id', $valores)) {
            DB::table($tabela)->insert($valores);

            return $valores['id'];
        }

        return DB::table($tabela)->insertGetId($valores);
    } finally {
        Schema::enableForeignKeyConstraints();
    }
}

function revertLog(string $classe, mixed $id, int $businessId, array $old = ['campo' => 'antes']): Activity
{
    $log = new Activity();
    $log->business_id = $businessId;
    $log->subject_type = $classe;
    $log->subject_id = $id;
    $log->properties = ['old' => $old];
    $log->created_at = now()->subMinute();

    return $log;
}

it('cenario 3: canRevert nega cada categoria sempre irreversivel, pela trava da classe real', function (string $classe, string $tabela, string $trechoMotivo, bool $idUuid) {
    $valores = ['business_id' => $this->business->id];
    if ($idUuid) {
        $valores['id'] = (string) Illuminate\Support\Str::uuid();
    }
    $id = revertLinha($tabela, $valores);

    $check = $this->service->canRevert(
        revertLog($classe, $id, $this->business->id),
        $this->usuarioComPermissoes(['auditoria.revert.unlimited'], $this->business),
    );

    expect($check->allowed)->toBeFalse();
    expect($check->modelClass)->toBe($classe);
    expect($check->reason)->toContain($trechoMotivo);
})->with([
    'marcacao de ponto'   => [\Modules\Ponto\Entities\Marcacao::class, 'ponto_marcacoes', 'Portaria MTP 671/2021', true],
    'banco de horas'      => [\Modules\Ponto\Entities\BancoHorasMovimento::class, 'ponto_banco_horas_movimentos', 'append-only', true],
    'NFe emitida'         => [\Modules\NfeBrasil\Models\NfeEmissao::class, 'nfe_emissoes', 'SEFAZ', false],
    'evento NFe'          => [\Modules\NfeBrasil\Models\NfeEvento::class, 'nfe_eventos', 'SEFAZ', false],
    'inutilizacao NFe'    => [\Modules\NfeBrasil\Models\NfeInutilizacao::class, 'nfe_inutilizacoes', 'SEFAZ', false],
    'NFSe (modulo NFSe)'  => [\Modules\NFSe\Models\NfseEmissao::class, 'nfse_emissoes', 'prefeitura', false],
    'NFSe (NfeBrasil)'    => [\Modules\NfeBrasil\Models\NfseEmissao::class, 'nfse_emissoes', 'prefeitura', false],
    'baixa financeira'    => [\Modules\Financeiro\Models\TituloBaixa::class, 'fin_titulo_baixas', 'Estornar do Financeiro', false],
]);

it('cenario 3b: OS com NFSe emitida e negada; a mesma OS com nota cancelada e liberada', function () {
    $os = revertLinha('repair_job_sheets', ['business_id' => $this->business->id]);
    $venda = revertLinha('transactions', ['business_id' => $this->business->id, 'repair_job_sheet_id' => $os]);
    $nota = revertLinha('nfse_emissoes', ['business_id' => $this->business->id, 'transaction_id' => $venda, 'status' => 'emitida']);
    $user = $this->usuarioComPermissoes(['auditoria.revert.unlimited'], $this->business);
    $log = revertLog(\Modules\Repair\Entities\JobSheet::class, $os, $this->business->id, ['defects' => 'antes']);

    $negado = $this->service->canRevert($log, $user);
    expect($negado->allowed)->toBeFalse();
    expect($negado->modelClass)->toBe(\Modules\Repair\Entities\JobSheet::class);
    expect($negado->reason)->toContain('NFSe');

    DB::table('nfse_emissoes')->where('id', $nota)->update(['status' => 'cancelada']);

    expect($this->service->canRevert($log, $user)->allowed)->toBeTrue();
});

it('cenario 3c: venda com pagamento depois do log e negada; sem pagamento posterior e liberada', function () {
    $venda = revertLinha('transactions', ['business_id' => $this->business->id]);
    $user = $this->usuarioComPermissoes(['auditoria.revert.unlimited'], $this->business);
    $log = revertLog(\App\Transaction::class, $venda, $this->business->id, ['additional_notes' => 'antes']);

    expect($this->service->canRevert($log, $user)->allowed)->toBeTrue();

    revertLinha('transaction_payments', ['transaction_id' => $venda, 'business_id' => $this->business->id, 'created_at' => now()]);

    $negado = $this->service->canRevert($log, $user);
    expect($negado->allowed)->toBeFalse();
    expect($negado->modelClass)->toBe(\App\Transaction::class);
    expect($negado->reason)->toContain('pagamento');
});

it('cenario 3d: mudanca de etapa FSM e negada; a mesma OS em campo comum e liberada', function () {
    $os = revertLinha('repair_job_sheets', ['business_id' => $this->business->id]);
    $user = $this->usuarioComPermissoes(['auditoria.revert.unlimited'], $this->business);

    $negado = $this->service->canRevert(
        revertLog(\Modules\Repair\Entities\JobSheet::class, $os, $this->business->id, ['current_stage_id' => 7]),
        $user,
    );
    expect($negado->allowed)->toBeFalse();
    expect($negado->modelClass)->toBe(\App\Domain\Fsm\Concerns\GuardsFsmTransitions::class);
    expect($negado->reason)->toContain('pipeline');

    $comum = revertLog(\Modules\Repair\Entities\JobSheet::class, $os, $this->business->id, ['defects' => 'antes']);
    expect($this->service->canRevert($comum, $user)->allowed)->toBeTrue();
});

it('cenario 4: canRevert deny quando Activity de outro business (Tier 0)', function () {
    $log = new Activity();
    $log->business_id = 99999; // diferente do user

    $check = $this->service->canRevert($log, $this->user);
    expect($check->allowed)->toBeFalse();
    expect($check->reason)->toContain('Tier 0');
});

it('cenario 5: canRevert deny quando subject_type sem subject_id', function () {
    $log = new Activity();
    $log->business_id  = $this->business->id;
    $log->subject_type = \App\Transaction::class;
    $log->subject_id   = null;

    $check = $this->service->canRevert($log, $this->user);
    expect($check->allowed)->toBeFalse();
});

/** Contato fictício do tenant, com o e-mail dado, editado para gerar um log 'updated' real. */
function revertContatoEditado(int $businessId, int $criadoPor, string $emailAntes): array
{
    $id = DB::table('contacts')->insertGetId([
        'business_id' => $businessId, 'type' => 'customer', 'name' => 'Contato Revert', 'mobile' => '',
        'email' => $emailAntes, 'contact_id' => 'AUD-REV-'.uniqid(), 'created_by' => $criadoPor,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $contato = \App\Contact::query()->withoutGlobalScopes()->findOrFail($id); // SUPERADMIN: fixture do teste
    $contato->email = 'depois@example.test';
    $contato->save();

    $log = Activity::query()->where('subject_type', \App\Contact::class)->where('subject_id', $id)
        ->where('event', 'updated')->latest('id')->firstOrFail();

    return [$contato, $log];
}

it('cenario 5b: log do tenant 98 apontando para registro do 99 e negado', function () {
    $biz99 = $this->seededSupportClientTenant();
    $id99 = DB::table('contacts')->insertGetId([
        'business_id' => $biz99->id, 'type' => 'customer', 'name' => 'Contato 99', 'mobile' => '',
        'contact_id' => 'AUD-REV-'.uniqid(), 'created_by' => $biz99->owner_id,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $user98 = $this->usuarioComPermissoes(['auditoria.revert.unlimited'], $this->business);

    // A linha de log diz 98 (o tenant da sessão, como antes do PR #8384); o registro é do 99.
    $log = new Activity();
    $log->business_id = $this->business->id;
    $log->subject_type = \App\Contact::class;
    $log->subject_id = $id99;
    $log->properties = ['old' => ['name' => 'antes']];

    $check = $this->service->canRevert($log, $user98);

    expect($check->allowed)->toBeFalse();
    expect($check->reason)->toContain('Registro pertence a outro business');
});

it('cenario 5c: revert restaura o valor, e a segunda reversao da mesma Activity e negada', function () {
    [$contato, $log] = revertContatoEditado($this->business->id, $this->business->owner_id, 'antes@example.test');
    $user = $this->usuarioComPermissoes(['auditoria.revert.unlimited'], $this->business);

    $this->service->revert($log, $user, 'motivo com tamanho valido');

    expect($contato->fresh()->email)->toBe('antes@example.test');
    expect($log->fresh()->reverted_at)->not->toBeNull();
    expect(fn () => $this->service->revert($log->fresh(), $user, 'motivo com tamanho valido'))
        ->toThrow(\DomainException::class, 'ja foi revertida');
});

it('cenario 5d: instancia velha da Activity nao fura a trava, porque revert rele o banco', function () {
    [$contato, $log] = revertContatoEditado($this->business->id, $this->business->owner_id, 'antes@example.test');
    $user = $this->usuarioComPermissoes(['auditoria.revert.unlimited'], $this->business);
    $velha = Activity::query()->findOrFail($log->id);

    $this->service->revert($log, $user, 'motivo com tamanho valido');

    expect($velha->reverted_at)->toBeNull();
    expect(fn () => $this->service->revert($velha, $user, 'motivo com tamanho valido'))
        ->toThrow(\DomainException::class, 'ja foi revertida');
});

it('cenario 5e: revert le a Activity e o registro com lock dentro da transacao', function () {
    [, $log] = revertContatoEditado($this->business->id, $this->business->owner_id, 'antes@example.test');
    $user = $this->usuarioComPermissoes(['auditoria.revert.unlimited'], $this->business);
    $sqls = [];
    DB::listen(function ($q) use (&$sqls) {
        $sqls[] = strtolower($q->sql);
    });

    $this->service->revert($log, $user, 'motivo com tamanho valido');

    $travadas = array_values(array_filter($sqls, fn ($s) => str_contains($s, 'for update')));
    expect(implode(' | ', $travadas))->toContain('`activity_log`');
    expect(implode(' | ', $travadas))->toContain('`contacts`');
});

it('cenario 5f: entry updated do save e entry reverted saem no mesmo batch_uuid', function () {
    [$contato, $log] = revertContatoEditado($this->business->id, $this->business->owner_id, 'antes@example.test');
    $user = $this->usuarioComPermissoes(['auditoria.revert.unlimited'], $this->business);

    $revertida = $this->service->revert($log, $user, 'motivo com tamanho valido');

    $updated = Activity::query()->where('subject_type', \App\Contact::class)->where('subject_id', $contato->id)
        ->where('event', 'updated')->where('id', '>', $log->id)->latest('id')->firstOrFail();
    expect($revertida->batch_uuid)->not->toBeEmpty();
    expect($updated->batch_uuid)->toBe($revertida->batch_uuid);
});

it('cenario 5g: restored_attributes nao copia dado pessoal, e o registro recebe o valor real', function () {
    $email = 'titular.ficticio@example.test';
    [$contato, $log] = revertContatoEditado($this->business->id, $this->business->owner_id, $email);
    $user = $this->usuarioComPermissoes(['auditoria.revert.unlimited'], $this->business);

    $revertida = $this->service->revert($log, $user, 'motivo com tamanho valido');

    expect(json_encode($revertida->properties['restored_attributes']))->not->toContain($email);
    expect($revertida->properties['restored_attributes'])->toHaveKey('email');
    expect($contato->fresh()->email)->toBe($email);
});

it('cenario 6: revert() lanca InvalidArgumentException se reason < 10 chars', function () {
    // Fixture PRÓPRIA (2026-10-01). Antes este caso pegava "qualquer activity do
    // business" e pulava porque NENHUMA tinha business_id (o trait LogsActivity não
    // gravava o tenant). Com o tenant gravado ele passou a rodar e mostrou que nunca
    // exercia o que diz: canRevert() roda ANTES do tamanho do motivo, e o usuário do
    // seed não tem auditoria.revert.* — o DomainException de permissão chegava primeiro.
    // Agora: log revertível do próprio tenant + permissão concedida na transação.
    $contato = \App\Contact::query()->where('business_id', $this->business->id)
        ->where('type', '!=', 'lead')->first();
    if (! $contato) {
        $this->markTestSkipped('Sem contact no business.');
    }
    $contato->name = $contato->name.' [revert-6]';
    $contato->save();

    $log = Activity::query()
        ->where('subject_type', \App\Contact::class)
        ->where('subject_id', $contato->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();
    expect($log)->not->toBeNull();
    expect((int) $log->business_id)->toBe((int) $this->business->id);

    \Spatie\Permission\Models\Permission::findOrCreate('auditoria.revert.unlimited', 'web');
    $this->user->givePermissionTo('auditoria.revert.unlimited');
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    expect($this->service->canRevert($log, $this->user->fresh())->allowed)->toBeTrue();

    expect(fn () => $this->service->revert($log, $this->user->fresh(), 'curto'))
        ->toThrow(\InvalidArgumentException::class, 'no minimo 10 caracteres');
});

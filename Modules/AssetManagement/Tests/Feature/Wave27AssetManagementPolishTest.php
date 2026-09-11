<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\AssetManagement\Services\AssetWarrantyService;

uses(Tests\TestCase::class);

/**
 * Helper — testes que tocam DB precisam skip em SQLite (schema MySQL UltimatePOS).
 */
function w27AssetManagementNeedsMysql(): bool
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        return true;
    }
    if (! Schema::hasTable('assets')) {
        return true;
    }
    return false;
}

/**
 * Wave 27 — POLISH ≥88 AssetManagement (2026-05-17).
 *
 * Cobre incrementos polish:
 *  - D9.a: spans novos AssetWarrantyService::contagemAtivas / contagemExpiradas
 *  - D5: README "como cliente usa" criado (sanity check existence)
 *  - D2 expand: cross-tenant biz=99 nos contadores warranty (Tier 0)
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL ({@see ADR 0093}). Nunca biz=4 cliente ({@see ADR 0101}).
 *
 * @see Modules\AssetManagement\Services\AssetWarrantyService
 * @see Modules\AssetManagement\README.md (D5 customer journey)
 */

it('W27 D9.a: AssetWarrantyService::contagemAtivas tem span assetmanagement.warranty.count_active', function () {
    $src = file_get_contents(base_path('Modules/AssetManagement/Services/AssetWarrantyService.php'));
    expect($src)->toContain("OtelHelper::spanBiz('assetmanagement.warranty.count_active'");
    expect($src)->toContain('public function contagemAtivas');
});

it('W27 D9.a: AssetWarrantyService::contagemExpiradas tem span assetmanagement.warranty.count_expired', function () {
    $src = file_get_contents(base_path('Modules/AssetManagement/Services/AssetWarrantyService.php'));
    expect($src)->toContain("OtelHelper::spanBiz('assetmanagement.warranty.count_expired'");
    expect($src)->toContain('public function contagemExpiradas');
});

it('W27 D9.a: AssetWarrantyService importa OtelHelper canônico (App\Util\OtelHelper)', function () {
    $src = file_get_contents(base_path('Modules/AssetManagement/Services/AssetWarrantyService.php'));
    expect($src)->toContain('use App\Util\OtelHelper;');
    expect($src)->not->toContain('OpenTelemetry\\API\\Trace');
});

it('W27 D9.a: AssetWarrantyService total 5 spanBiz invocations (3 originais + 2 W27)', function () {
    $src = file_get_contents(base_path('Modules/AssetManagement/Services/AssetWarrantyService.php'));
    $count = substr_count($src, 'OtelHelper::spanBiz(');
    expect($count)->toBeGreaterThanOrEqual(5);
});

it('W27 D5: README.md "como cliente usa" criado com cenários canônicos', function () {
    $readme = base_path('memory/requisitos/AssetManagement/README.md');
    expect(file_exists($readme))->toBeTrue('README W27 D5 não criado');

    $content = file_get_contents($readme);
    expect($content)->toContain('Cenário A');
    expect($content)->toContain('Cenário B');
    expect($content)->toContain('Cenário C');
    expect($content)->toContain('Cenário D');
    expect($content)->toContain('Multi-tenant Tier 0');
    expect($content)->toContain('ADR 0093');
});

it('W27 D5: README documenta 5 spans AssetWarrantyService', function () {
    $content = file_get_contents(base_path('memory/requisitos/AssetManagement/README.md'));
    expect($content)->toContain('contagemAtivas');
    expect($content)->toContain('contagemExpiradas');
});

it('W27 D2 Tier 0: contagemAtivas biz=99 com asset inexistente retorna 0', function () {
    if (w27AssetManagementNeedsMysql()) {
        $this->markTestSkipped('Requer MySQL UltimatePOS (ADR 0101).');
    }
    /** @var AssetWarrantyService $svc */
    $svc = app(AssetWarrantyService::class);

    // Asset ID muito alto + biz=99 — não existe combinação real
    // Service deve retornar 0 (find() → null, NÃO findOrFail → 404 quebraria contract)
    $count = $svc->contagemAtivas(999999, 99);
    expect($count)->toBe(0);
});

it('W27 D2 Tier 0: contagemExpiradas biz=99 com asset inexistente retorna 0', function () {
    if (w27AssetManagementNeedsMysql()) {
        $this->markTestSkipped('Requer MySQL UltimatePOS (ADR 0101).');
    }
    /** @var AssetWarrantyService $svc */
    $svc = app(AssetWarrantyService::class);

    $count = $svc->contagemExpiradas(999999, 99);
    expect($count)->toBe(0);
});

it('W27 D9.a: contagemAtivas retorna int (contract sanity)', function () {
    if (w27AssetManagementNeedsMysql()) {
        $this->markTestSkipped('Requer MySQL UltimatePOS (ADR 0101).');
    }
    /** @var AssetWarrantyService $svc */
    $svc = app(AssetWarrantyService::class);

    $count = $svc->contagemAtivas(999999, 1);
    expect($count)->toBeInt();
});

/* ─────────────────────────────────────────────────────────────────────────────
 * THREAD 02 — trava de saldo na alocação
 *
 * `criar()` gravava sem consultar o saldo: dava pra alocar 10 unidades de um bem
 * que tem 3. Não havia erro — o rastro de responsabilidade nascia falso, que é
 * exatamente o que o módulo existe pra entregar.
 *
 * Os testes exercitam o CAMINHO VIVO (`AssetAllocationService::criar`), nunca o
 * `rules()` do `StoreAssetAllocationRequest`: ele é ÓRFÃO — o controller recebe
 * `Illuminate\Http\Request` cru e tem 0 chamadas de validação, então regra escrita
 * lá passa no CI e é inerte em produção (`_saida-04.md §5`).
 *
 * Tenant FICTÍCIO 98 (ADR 0358). Nunca biz=1 (WR2, real) nem biz=4 (ROTA LIVRE).
 * ───────────────────────────────────────────────────────────────────────────── */

/** `created_by` é NOT NULL e FK→`users.id`: fixture sem ele estoura na FK antes do assert. */
function w27TravaBem(int $businessId, int $ownerId, float $quantidade): \Modules\AssetManagement\Entities\Asset
{
    return \Modules\AssetManagement\Entities\Asset::create([
        'business_id' => $businessId,
        'name' => 'Bem da trava de saldo',
        'asset_code' => 'TRAVA-'.uniqid(),
        'quantity' => $quantidade,
        'unit_price' => 10.00,
        'is_allocatable' => 1,
        'purchase_type' => 'owned',
        'created_by' => $ownerId,
    ]);
}

function w27TravaUsuario(int $businessId): \App\User
{
    return \App\User::factory()->create([
        'business_id' => $businessId,
        'username' => 'w27_trava_'.uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);
}

/**
 * Monta o Request como o controller o entrega ao Service — `$request->only(...)`.
 *
 * ⚠️ A quantidade vai como NÚMERO. `Util::num_uf` lê "2.500" como 2500 (um ponto
 * seguido de exatamente 3 dígitos é separador de milhar pt-BR) — o vetor do
 * incidente de 2026-06-05. Passar string formatada aqui mediria o parser, não a trava.
 *
 * ⚠️ A SESSÃO é obrigatória, e descobri isso quebrando: `normalizarCampos` chama
 * `Util::uf_date($data, true)`, que monta o formato a partir de
 * `session('business.date_format')`. SEM sessão e com `$time=true`, ele concatena
 * `null.' H:i'` = `' H:i'` — que NÃO é vazio, escapa do guard `! empty($date_format)`
 * e faz o `Carbon::createFromFormat` estourar `InvalidFormatException`. A data tem de
 * vir no MESMO formato que a sessão declara, senão o teste mede o parser de data em
 * vez da trava de saldo.
 */
function w27TravaRequest(int $assetId, int $receiverId, float $quantidade): \Illuminate\Http\Request
{
    session(['business.date_format' => 'd/m/Y', 'business.time_format' => 24]);

    return new \Illuminate\Http\Request([
        'asset_id' => $assetId,
        'quantity' => $quantidade,
        'receiver' => $receiverId,
        'transaction_datetime' => now()->format('d/m/Y H:i'),
        // `ref_no` EXPLÍCITO, e não é atalho: quando ele vem vazio, `criar()` chama
        // `Util::setAndGetReferenceCount`, que grava contador na sessão do business e
        // estoura fora de uma request HTTP real. O formulário legado
        // (`asset_allocation/create.blade.php`) também manda este campo, então o caminho
        // exercitado continua sendo o de produção. MEDIDO: sem isto, o cenário de RECUSA
        // passava (a trava barra antes do contador) e os de SUCESSO quebravam em
        // `Util.php:450` — o que mediria a sessão, não o saldo.
        'ref_no' => 'TRAVA-REF-'.uniqid(),
    ]);
}

function w27TravaLimpar(): void
{
    $bens = \Modules\AssetManagement\Entities\Asset::where('asset_code', 'like', 'TRAVA-%')->get();
    foreach ($bens as $bem) {
        \Modules\AssetManagement\Entities\AssetTransaction::where('asset_id', $bem->id)->delete();
        $bem->forceDelete();
    }
    $users = \App\User::withTrashed()->where('username', 'like', 'w27_trava_%')->get();
    foreach ($users as $u) {
        DB::table('model_has_roles')->where('model_id', $u->id)->delete();
        $u->forceDelete();
    }
}

it('T02: alocar ACIMA do saldo é recusado — bem com 3, pedido de 4', function () {
    if (w27AssetManagementNeedsMysql()) {
        $this->markTestSkipped('Requer MySQL UltimatePOS (ADR 0358).');
    }

    $biz = $this->seededTenant();
    $user = w27TravaUsuario((int) $biz->id);

    try {
        $bem = w27TravaBem((int) $biz->id, (int) $user->id, 3.0);
        $svc = app(\Modules\AssetManagement\Services\AssetAllocationService::class);

        expect(fn () => $svc->criar(
            w27TravaRequest((int) $bem->id, (int) $user->id, 4.0),
            (int) $biz->id,
            (int) $user->id
        ))->toThrow(\Modules\AssetManagement\Exceptions\SaldoInsuficienteException::class);

        // A recusa não pode deixar rastro: se a transação fosse gravada e só depois
        // rejeitada, o saldo já teria mentido. Conta as linhas — não confia na exceção.
        expect(\Modules\AssetManagement\Entities\AssetTransaction::where('asset_id', $bem->id)->count())->toBe(0);
    } finally {
        w27TravaLimpar();
    }
});

it('T02: alocar DENTRO do saldo passa — bem com 3, pedido de 3', function () {
    if (w27AssetManagementNeedsMysql()) {
        $this->markTestSkipped('Requer MySQL UltimatePOS (ADR 0358).');
    }

    $biz = $this->seededTenant();
    $user = w27TravaUsuario((int) $biz->id);

    try {
        $bem = w27TravaBem((int) $biz->id, (int) $user->id, 3.0);
        $svc = app(\Modules\AssetManagement\Services\AssetAllocationService::class);

        // O par da recusa: sem ele, uma trava que recusasse TUDO passaria no teste acima
        // e o alarme seria indistinguível de um gate quebrado.
        $trans = $svc->criar(
            w27TravaRequest((int) $bem->id, (int) $user->id, 3.0),
            (int) $biz->id,
            (int) $user->id
        );

        expect((float) $trans->quantity)->toBe(3.0);
        expect(\Modules\AssetManagement\Entities\AssetTransaction::where('asset_id', $bem->id)->count())->toBe(1);
    } finally {
        w27TravaLimpar();
    }
});

it('T02: o saldo desconta o que já saiu — 3 no bem, 2 alocados, novo pedido de 2 recusa', function () {
    if (w27AssetManagementNeedsMysql()) {
        $this->markTestSkipped('Requer MySQL UltimatePOS (ADR 0358).');
    }

    $biz = $this->seededTenant();
    $user = w27TravaUsuario((int) $biz->id);

    try {
        $bem = w27TravaBem((int) $biz->id, (int) $user->id, 3.0);
        $svc = app(\Modules\AssetManagement\Services\AssetAllocationService::class);

        // 2 de 3 saem: sobra 1.
        $svc->criar(w27TravaRequest((int) $bem->id, (int) $user->id, 2.0), (int) $biz->id, (int) $user->id);

        // Pedir 2 agora excede — este é o caso que o teste de "3 e 3" NÃO cobre, porque lá
        // o bem estava intocado. É o que prova que a trava lê o ESTADO, não só o cadastro.
        expect(fn () => $svc->criar(
            w27TravaRequest((int) $bem->id, (int) $user->id, 2.0),
            (int) $biz->id,
            (int) $user->id
        ))->toThrow(\Modules\AssetManagement\Exceptions\SaldoInsuficienteException::class);

        // E 1 ainda passa — o saldo é exatamente o que sobrou, não zero.
        $ok = $svc->criar(w27TravaRequest((int) $bem->id, (int) $user->id, 1.0), (int) $biz->id, (int) $user->id);
        expect((float) $ok->quantity)->toBe(1.0);
    } finally {
        w27TravaLimpar();
    }
});

it('T02: devolução libera o saldo — 3 alocados, 3 devolvidos, novo pedido de 3 passa', function () {
    if (w27AssetManagementNeedsMysql()) {
        $this->markTestSkipped('Requer MySQL UltimatePOS (ADR 0358).');
    }

    $biz = $this->seededTenant();
    $user = w27TravaUsuario((int) $biz->id);

    try {
        $bem = w27TravaBem((int) $biz->id, (int) $user->id, 3.0);
        $svc = app(\Modules\AssetManagement\Services\AssetAllocationService::class);

        $aloc = $svc->criar(w27TravaRequest((int) $bem->id, (int) $user->id, 3.0), (int) $biz->id, (int) $user->id);

        // Devolução: linha filha ligada pelo `parent_id` — é assim que o revoke nasce.
        \Modules\AssetManagement\Entities\AssetTransaction::create([
            'business_id' => $biz->id,
            'asset_id' => $bem->id,
            'transaction_type' => 'revoke',
            'ref_no' => 'TRAVA-REV-'.uniqid(),
            'receiver' => $user->id,
            'quantity' => 3.0,
            'transaction_datetime' => now(),
            'parent_id' => $aloc->id,
            'created_by' => $user->id,
        ]);

        // Se a trava contasse só `allocate` e ignorasse `revoke`, o bem ficaria travado pra
        // sempre depois da primeira alocação. Este caso é o que impede esse desenho.
        $novo = $svc->criar(w27TravaRequest((int) $bem->id, (int) $user->id, 3.0), (int) $biz->id, (int) $user->id);
        expect((float) $novo->quantity)->toBe(3.0);
    } finally {
        w27TravaLimpar();
    }
});

it('T02 Tier 0: o saldo NÃO conta alocação de outro business', function () {
    if (w27AssetManagementNeedsMysql()) {
        $this->markTestSkipped('Requer MySQL UltimatePOS (ADR 0358).');
    }

    $dono = $this->seededTenant();
    $adversario = $this->seededSupportClientTenant();

    $userDono = w27TravaUsuario((int) $dono->id);
    $userAdv = w27TravaUsuario((int) $adversario->id);

    try {
        $bemDono = w27TravaBem((int) $dono->id, (int) $userDono->id, 3.0);
        $svc = app(\Modules\AssetManagement\Services\AssetAllocationService::class);

        // Linha do ADVERSÁRIO apontando pro bem do dono: é dado cruzado, e é exatamente o
        // que uma contagem sem predicado de tenant somaria. Se a trava a contasse, o saldo
        // do dono cairia pra 0 e o pedido legítimo abaixo seria recusado.
        \Modules\AssetManagement\Entities\AssetTransaction::create([
            'business_id' => $adversario->id,
            'asset_id' => $bemDono->id,
            'transaction_type' => 'allocate',
            'ref_no' => 'TRAVA-ADV-'.uniqid(),
            'receiver' => $userAdv->id,
            'quantity' => 3.0,
            'transaction_datetime' => now(),
            'created_by' => $userAdv->id,
        ]);

        $ok = $svc->criar(
            w27TravaRequest((int) $bemDono->id, (int) $userDono->id, 3.0),
            (int) $dono->id,
            (int) $userDono->id
        );

        expect((float) $ok->quantity)->toBe(3.0);
    } finally {
        w27TravaLimpar();
    }
});

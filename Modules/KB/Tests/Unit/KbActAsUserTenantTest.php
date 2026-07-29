<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Tests\Support\WithSeededTenant;

/**
 * Contrato do helper `kbActAsUser` — o tenant do usuário autenticado tem que ser o
 * `$bizId` PEDIDO, mesmo quando o `$userId` já existe de uma chamada anterior.
 *
 * ## O defeito (flaky 403 da lane KB, catalogado 2026-07-29)
 *
 * `kbActAsUser` resolvia o usuário com `find($userId)` e só setava `business_id` no ramo
 * de CRIAÇÃO. A tabela `users` é CORE e **não** é resetada pelo `kbTeardownSchema`, então
 * o usuário sobrevive entre testes: a partir da 2ª chamada ele ficava preso ao tenant da
 * PRIMEIRA, enquanto a `session()` passava a afirmar o tenant NOVO. Usuário pertencendo a
 * um business e sessão dizendo outro — que o `can:` do KbController resolve como **403**.
 *
 * Vazava porque o `$userId` default é 42 e há chamadas com tenants diferentes espalhadas
 * por arquivos distintos. Sob `executionOrder="random"` (phpunit.xml:7) a contaminação
 * dependia da ordem, então a falha caía em testes DIFERENTES a cada run (V4 num, V7 em
 * outro), sempre com o mesmo `403 ≠ 200` e o mesmo placar — assinatura de flaky, não de
 * regressão. Por isso sobreviveu aos BLOQUEADORES 1 e 2 do helper, que tratam o registry
 * de PERMISSÕES e nunca alcançaram o tenant do próprio usuário.
 *
 * ## Medição (CT 100, MySQL real, 2026-07-29) — suíte `Modules/KB/Tests` inteira
 *
 * | | failed | passed |
 * |---|---:|---:|
 * | sem o fix | 32 | 174 |
 * | com o fix | **25** | **181** |
 *
 * 7 consertados, **0 regressões**. Além dos 2 bites abaixo, o fix destrava
 * `CrossTenantIsolationTest > bridge job` (um teste de ISOLAMENTO que estava vermelho pela
 * própria contaminação) e 4 do `KbBridgeFromMcpJobTest`.
 *
 * ## Vocabulário de tenant ([ADR 0358](../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md))
 *
 * Usa `SEEDED_TENANT_ID` (98, canônico fictício) e `SUPPORT_CLIENT_TENANT_ID` (99, a outra
 * fictícia / adversário cross-tenant) — **nunca `biz=1`**, que é a WR2 Sistemas, empresa
 * REAL, nem `biz=4` (ROTA LIVRE). Constantes, não hardcode.
 */
// NÃO usar `uses(Tests\TestCase::class, …)` aqui: o `Pest.php` já aplica o TestCase a esta
// pasta e repetir aborta a suíte inteira ("The folder already uses the test case").

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite: rodar no CT 100 (MySQL). ADR 0062 / 0358.');
    }
    kbBootstrapSchema();
});

afterEach(fn () => kbTeardownSchema());

/** Lê o business_id do usuário direto do banco (sem cache de model). */
function tenantDoUsuario(int $userId): ?int
{
    $v = DB::table('users')->where('id', $userId)->value('business_id');

    return $v === null ? null : (int) $v;
}

/**
 * Os ids vêm das constantes do `WithSeededTenant` — a fonte única da ADR 0358, nunca
 * hardcode. Duas armadilhas do PHP/Pest no caminho, ambas medidas aqui:
 *   - `WithSeededTenant::SEEDED_TENANT_ID` → *"Cannot access trait constant directly"*:
 *     constante de TRAIT só é legível pela classe que o usa.
 *   - `test()::SEEDED_TENANT_ID` → *"Undefined constant HigherOrderTapProxy::…"*: o `test()`
 *     do Pest devolve um proxy, não a instância do TestCase.
 * Reflection sobre o trait resolve os dois e mantém a fonte única.
 */
function tenantIdCanon(string $const): int
{
    return (int) (new ReflectionClass(WithSeededTenant::class))->getConstant($const);
}

function tenantCanonico(): int
{
    return tenantIdCanon('SEEDED_TENANT_ID');          // 98 — fictício, default de todo teste
}

function tenantAdversario(): int
{
    return tenantIdCanon('SUPPORT_CLIENT_TENANT_ID');  // 99 — a outra fictícia (cross-tenant)
}

it('BITE: mesmo userId reutilizado em outro tenant adota o tenant NOVO (99 → 98)', function () {
    // Reproduz a ordem que quebrava: um teste cross-tenant roda primeiro...
    kbActAsUser(bizId: tenantAdversario(), permissions: []);
    expect(tenantDoUsuario(42))->toBe(tenantAdversario());

    // ...e em seguida um teste do tenant canônico pega o MESMO userId default.
    kbActAsUser(bizId: tenantCanonico(), permissions: []);

    // Sem o fix, o usuário continuava no tenant anterior enquanto a sessão dizia o novo.
    expect(tenantDoUsuario(42))->toBe(tenantCanonico());
});

it('BITE: a direção inversa também vale (98 → 99)', function () {
    kbActAsUser(bizId: tenantCanonico(), permissions: []);
    expect(tenantDoUsuario(42))->toBe(tenantCanonico());

    kbActAsUser(bizId: tenantAdversario(), permissions: []);
    expect(tenantDoUsuario(42))->toBe(tenantAdversario());
});

it('o usuário autenticado e a sessão concordam sobre o tenant', function () {
    kbActAsUser(bizId: tenantAdversario(), permissions: []);
    $user = kbActAsUser(bizId: tenantCanonico(), permissions: []);

    // As duas fontes que o middleware/authz consulta não podem divergir.
    expect((int) $user->business_id)->toBe(tenantCanonico())
        ->and((int) session('user.business_id'))->toBe(tenantCanonico())
        ->and((int) session('business.id'))->toBe(tenantCanonico());
});

it('CONTROLE NEGATIVO: userIds distintos não interferem entre si', function () {
    kbActAsUser(bizId: tenantAdversario(), userId: 77, permissions: []);
    kbActAsUser(bizId: tenantCanonico(), userId: 42, permissions: []);

    // Cada um mantém o seu — o fix não pode "corrigir" quem nunca esteve errado.
    expect(tenantDoUsuario(77))->toBe(tenantAdversario())
        ->and(tenantDoUsuario(42))->toBe(tenantCanonico());
});

it('CONTROLE NEGATIVO: chamar 2× com o MESMO tenant é no-op (idempotente)', function () {
    kbActAsUser(bizId: tenantCanonico(), permissions: []);
    $antes = tenantDoUsuario(42);

    kbActAsUser(bizId: tenantCanonico(), permissions: []);

    expect(tenantDoUsuario(42))->toBe($antes)->toBe(tenantCanonico());
});

it('os dois papéis de tenant são ids DISTINTOS (premissa da ADR 0358)', function () {
    // Se um dia colidirem, os BITEs acima viram tautologia silenciosa — passariam
    // comparando o mesmo número consigo mesmo, sem provar sincronização nenhuma.
    expect(tenantCanonico())
        ->not->toBe(tenantAdversario());
});

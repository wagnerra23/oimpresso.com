<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\Support\WithSeededTenant;

/**
 * Contrato executável da tela de status de OS — /repair/status (listagem).
 *
 * Os aceites derivam do `Status/Index.charter.md` e do `RepairStatusController`,
 * nunca do `.tsx` (proibicoes.md §5 2026-06-05).
 *
 * ESTE ARQUIVO CUMPRE UMA PROMESSA QUE ESTAVA VAZIA. O charter declarava, sob
 * "Métricas vivas (Pest GUARD — completar em F1.5)", três testes:
 * `RepairStatusCharterTest::it_does_not_mutate_state`, `::it_does_not_emit_emails` e
 * `::it_isolates_by_business_id`. Nenhum existia — `git grep RepairStatusCharterTest`
 * em 2026-09-05 devolvia UM hit, o próprio charter. Promessa de Pest GUARD sem teste
 * é instrução ativa para a próxima sessão confiar em cobertura inexistente; a regra é
 * grep antes de confiar, e então cumprir ou revogar. Aqui se cumpre: os três anti-hooks
 * viram UC-RSTIDX-03 (isolamento), UC-RSTIDX-04 (não muta) e UC-RSTIDX-05 (não emite),
 * e o charter passa a apontar para os nomes reais.
 *
 * Tenant: `seededTenant()` = 98 (fictício, canônico). biz=4 é PROIBIDO sem exceção e
 * biz=1 é empresa real — no CT 100 a base é clone de prod e não se limpa entre runs.
 *
 * @see resources/js/Pages/Repair/Status/Index.charter.md
 * @see Modules/Repair/Http/Controllers/RepairStatusController.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md
 */
uses(Tests\TestCase::class, WithSeededTenant::class);

/** Marcador dos registros deste arquivo — o banco do CT 100 PERSISTE entre runs. */
const RST_TAG = '[rst-contrato]';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: o schema UltimatePOS de `business` exige MySQL (ADR 0358).');
    }
    foreach (['business', 'users', 'permissions', 'repair_statuses'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente; rode migrate + seed mínimo.");
        }
    }
    rstLimpa();
});

afterEach(function () {
    rstLimpa();
    config(['mwart.repair_status_index.enabled' => false, 'mwart.repair_status_index.business_ids' => []]);
});

/** Apaga só o que este arquivo cria — sem global scope, em qualquer tenant. */
function rstLimpa(): void
{
    DB::table('repair_statuses')->where('name', 'like', '%'.RST_TAG.'%')->delete();
}

/** Status cru no tenant pedido. Sem auth o global scope não filtra (ScopeByBusiness). */
function rstStatus(int $businessId, string $nome, string $cor, int $ordem, int $completo = 0): int
{
    return (int) DB::table('repair_statuses')->insertGetId([
        'business_id' => $businessId,
        'name' => $nome.' '.RST_TAG,
        'color' => $cor,
        'sort_order' => $ordem,
        'is_completed_status' => $completo,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * User do tenant. `$superadmin = false` produz um usuário que NÃO passa o gate:
 * o controller exige `superadmin` OU (`repair_module` E `repair_status.access`), e a
 * permission `repair_status.access` não existe na base (medido em 2026-09-05), de modo
 * que o segundo ramo nunca fecha nem para quem tem a assinatura do módulo.
 */
function rstUser(int $businessId, bool $superadmin = true): User
{
    $user = User::factory()->create([
        'business_id' => $businessId,
        'username' => 'rst_contrato_'.uniqid(),
    ]);

    if ($superadmin) {
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']));
    }

    return $user;
}

/** Sessão mínima: o layout Blade legado lê `currency['code']` sem coalescência. */
function rstSessao(int $businessId, int $userId): void
{
    session([
        'user.business_id' => $businessId,
        'user.id' => $userId,
        'business.id' => $businessId,
        'business.currency_symbol_placement' => 'before',
        'currency' => ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
    ]);
}

/** Liga a tela Inertia para o tenant corrente. */
function rstFlagLigada(): void
{
    config(['mwart.repair_status_index.enabled' => true, 'mwart.repair_status_index.business_ids' => []]);
}

/**
 * Só as linhas que ESTE arquivo criou, na ordem em que a tela as entregou.
 *
 * Por que filtrar em vez de comparar a lista inteira: o catálogo do tenant não começa
 * vazio. No CT 100 a base persiste entre runs e é compartilhada por várias sessões — em
 * 2026-09-05, no meio deste trabalho, `repair_statuses` ganhou 35 linhas de outra sessão
 * (nomes sem a tag deste arquivo, `sort_order` nulo). Um assert sobre a lista inteira
 * mediria o que os vizinhos fizeram, não o contrato desta tela: passava sozinho e
 * quebrava em conjunto, que é o modo de falha mais caro de diagnosticar. O contrato é a
 * ordem RELATIVA das linhas desta tela, e é isso que se afirma.
 */
function rstSoOsMeus(array $props): \Illuminate\Support\Collection
{
    return collect($props)->filter(fn ($s) => str_contains((string) ($s['name'] ?? ''), RST_TAG))->values();
}

// ─────────────────────────────────────────────────────────────────────────────

it('UC-RSTIDX-01: a lista chega na ordem de exibição configurada, não na ordem de cadastro', function () {
    $biz = $this->seededTenant();
    // Cadastrados fora de ordem de propósito: se o controller devolvesse pela ordem de
    // inserção, a tela mostraria o fluxo da oficina embaralhado e ninguém veria erro.
    rstStatus((int) $biz->id, 'Entrega', '#00ff00', 30);
    rstStatus((int) $biz->id, 'Recebido', '#ff0000', 10);
    rstStatus((int) $biz->id, 'Diagnostico', '#0000ff', 20);
    $user = rstUser((int) $biz->id);
    rstSessao((int) $biz->id, (int) $user->id);
    rstFlagLigada();

    $r = $this->actingAs($user)->get('/repair/status');

    expect($r->status())->toBe(200);
    $r->assertInertia(fn (AssertableInertia $p) => $p->component('Repair/Status/Index')->has('statuses'));

    $meus = rstSoOsMeus($r->viewData('page')['props']['statuses']);
    expect($meus)->toHaveCount(3);
    expect($meus->pluck('sort_order')->all())->toBe([10, 20, 30]);
    // E os nomes acompanham a ordem — prova que ordenou a linha inteira, não só a coluna.
    expect($meus->pluck('name')->map(fn ($n) => explode(' ', $n)[0])->all())
        ->toBe(['Recebido', 'Diagnostico', 'Entrega']);
});

it('UC-RSTIDX-02: a cor de cada status chega à tela exatamente como está no banco', function () {
    $biz = $this->seededTenant();
    // A tela pinta o swatch com `style={{ backgroundColor }}` a partir deste valor: é
    // dado do tenant, não cor de design. Normalizar (caixa, formato, token) mudaria a
    // identidade visual que o operador cadastrou.
    rstStatus((int) $biz->id, 'Aguardando', '#A1B2C3', 10);
    $user = rstUser((int) $biz->id);
    rstSessao((int) $biz->id, (int) $user->id);
    rstFlagLigada();

    $r = $this->actingAs($user)->get('/repair/status');

    $linha = rstSoOsMeus($r->viewData('page')['props']['statuses'])->first();
    expect($linha)->not->toBeNull();
    expect($linha['color'])->toBe('#A1B2C3');
});

it('UC-RSTIDX-03: status de outro tenant não aparece na lista (Tier 0 · ADR 0093)', function () {
    $biz = $this->seededTenant();
    $vizinho = $this->seededSupportClientTenant();
    rstStatus((int) $biz->id, 'Meu-Status', '#111111', 10);
    rstStatus((int) $vizinho->id, 'Status-Alheio', '#222222', 10);
    $user = rstUser((int) $biz->id);
    rstSessao((int) $biz->id, (int) $user->id);
    rstFlagLigada();

    $r = $this->actingAs($user)->get('/repair/status');

    $nomes = collect($r->viewData('page')['props']['statuses'])->pluck('name')->implode(' | ');
    expect($nomes)->toContain('Meu-Status')
        ->and($nomes)->not->toContain('Status-Alheio');
});

it('UC-RSTIDX-04: abrir a tela não escreve nada no catálogo (read-only puro)', function () {
    $biz = $this->seededTenant();
    rstStatus((int) $biz->id, 'Imutavel', '#333333', 10);
    $user = rstUser((int) $biz->id);
    rstSessao((int) $biz->id, (int) $user->id);
    rstFlagLigada();

    // Escopado às linhas deste arquivo: a base do CT 100 é compartilhada, e um snapshot
    // da tabela inteira mediria a escrita de outra sessão em vez desta tela.
    $snapshot = fn () => DB::table('repair_statuses')
        ->where('name', 'like', '%'.RST_TAG.'%')
        ->orderBy('id')->get()->toJson();

    $antes = $snapshot();

    $this->actingAs($user)->get('/repair/status');

    expect($snapshot())->toBe($antes);
});

it('UC-RSTIDX-05: abrir a tela não dispara e-mail nem notificação', function () {
    Mail::fake();
    Notification::fake();

    $biz = $this->seededTenant();
    // Os status carregam `sms_template`/`email_subject`/`email_body`: é a tela de
    // CONFIGURAÇÃO desses avisos, e listá-los não pode disparar nenhum deles.
    rstStatus((int) $biz->id, 'Pronto-para-retirada', '#444444', 10, 1);
    $user = rstUser((int) $biz->id);
    rstSessao((int) $biz->id, (int) $user->id);
    rstFlagLigada();

    $this->actingAs($user)->get('/repair/status');

    Mail::assertNothingSent();
    Notification::assertNothingSent();
});

it('UC-RSTIDX-06: sem a permissão de status a tela responde 403, mesmo com o módulo assinado', function () {
    $biz = $this->seededTenant();
    $user = rstUser((int) $biz->id, superadmin: false);
    rstSessao((int) $biz->id, (int) $user->id);
    rstFlagLigada();

    $r = $this->actingAs($user)->get('/repair/status');

    // Contraste medido com o catálogo de modelos: lá o gate é `superadmin` OU assinatura
    // do `repair_module`, e o biz=98 TEM a assinatura — então um usuário comum entra.
    // Aqui o segundo ramo exige também `repair_status.access`, que não existe na base,
    // então quem não é superadmin fica de fora. As duas telas do mesmo hub de
    // configuração têm porta de entrada diferente, e isso é contrato, não acaso.
    expect($r->status())->toBe(403);
});

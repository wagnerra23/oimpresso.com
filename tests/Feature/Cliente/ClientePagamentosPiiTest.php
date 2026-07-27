<?php

declare(strict_types=1);
// Cobre UC-CSHW-03 (Show.casos.md) - G-2 rastreabilidade caso-teste.
// @covers-us US-CRM-063

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contrato COMPORTAMENTAL do endpoint que alimenta a aba "Pagamentos" do drawer 760
 * (e do Show legado): GET /cliente/{id}/payments-json — ContactController::paymentsJson.
 *
 * POR QUE ESTE TESTE EXISTE (SDD Cliente §5.4.2 · CU-CLI-10)
 * ─────────────────────────────────────────────────────────────────────────────
 * A aba Pagamentos era o item `[BACKLOG]` mais antigo do Show.casos.md ("self-fetch
 * AJAX", sem id, sem teste). O endpoint é o ÚNICO ponto do módulo Cliente onde há
 * REDAÇÃO de PII de verdade (número de conta -> '****' + 4 últimos dígitos,
 * ContactController::paymentsJson) — todo o resto do módulo chama `maskTaxNumber`,
 * que só FORMATA (o próprio docblock do ClienteAutosaveController::maskTaxNumber
 * admite: "mantem digitos visiveis ... nao redact").
 *
 * Os 4 testes de PII que já existiam (`ClienteDrawerRowsCanonBrPayloadTest`,
 * `ClienteListagemTurbinadaTest`, `Wave1IndexBaselineTest`) asseguram por
 * `file_get_contents` que a CHAMADA a maskTaxNumber está escrita no Controller —
 * presença de chamada, não efeito no payload (L-24 "presença != correção").
 * Este é o primeiro que exercita o RUNTIME.
 *
 * ANTI-VÁCUO (proibicoes §5 2026-07-24): asserção de ausência ("não vaza") passa
 * trivialmente quando a operação não aconteceu (lista vazia / 404 / rota morta).
 * Por isso TODA asserção de ausência aqui é precedida da prova de que o pagamento
 * REALMENTE viajou no payload.
 *
 * ASSERÇÃO ACOPLADA A COMPORTAMENTO, NÃO A CHAVE (ADR 0351 Fase 2.2): o contrato é
 * "o número da conta não sai do servidor", não "a chave se chama bank_account_number".
 * Por isso a varredura é sobre o JSON CRU inteiro — renomear a chave não faz o
 * vazamento passar.
 *
 * Tier 0 (ADR 0093): `App\Contact` NÃO tem global scope (verificado — 0 ocorrências
 * de addGlobalScope em app/Contact.php). O isolamento aqui repousa no
 * `Contact::where('business_id', ...)->findOrFail($id)` manual do Controller; é
 * exatamente por isso que ele precisa de teste (US-CRM-080).
 *
 * Testes biz=1 (ADR 0101) vs biz=2 do seed da lane — NUNCA biz=4 (ROTA LIVRE).
 * CT 100 / CI apenas (ADR 0062) — nunca local, nunca Hostinger.
 * PII: nenhum documento/conta real; todos os dígitos abaixo são sintéticos.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('contacts') || ! Schema::hasTable('transaction_payments')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant(); // biz=1 canônico (ADR 0101)
    $this->user = \App\User::where('business_id', $this->business->id)->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business canônico.');
    }

    $this->location = DB::table('business_locations')->where('business_id', $this->business->id)->first();
    if (! $this->location) {
        $this->markTestSkipped('Sem business_location no seed.');
    }

    $this->actingAs($this->user);
    session([
        'user.business_id' => $this->business->id,
        'user.id' => $this->user->id,
        'business.id' => $this->business->id,
    ]);
});

/** Contato limpo do tenant (isola o payload deste teste dos dados do seed). */
function cppCriarContato(int $businessId, int $userId, string $type = 'customer'): int
{
    return (int) DB::table('contacts')->insertGetId([
        'business_id' => $businessId,
        'type' => $type,
        'name' => 'CPP-' . uniqid(),
        'mobile' => '',
        'created_by' => $userId,
        'created_at' => Carbon::now()->toDateTimeString(),
        'updated_at' => Carbon::now()->toDateTimeString(),
    ]);
}

/**
 * Venda + pagamento com número de conta sintético. `withoutEvents` evita o
 * TransactionObserver do Financeiro (SELECT ... FOR UPDATE -> deadlock sob
 * DatabaseTransactions) — este teste caracteriza o PAYLOAD, não os side-effects.
 */
function cppCriarPagamento(
    int $businessId,
    int $locationId,
    int $contactId,
    int $userId,
    float $valor,
    string $contaBancaria
): int {
    $tx = \App\Transaction::withoutEvents(fn () => \App\Transaction::create([
        'business_id' => $businessId,
        'location_id' => $locationId,
        'type' => 'sell',
        'status' => 'final',
        'payment_status' => 'paid',
        'contact_id' => $contactId,
        'transaction_date' => Carbon::now()->toDateTimeString(),
        'final_total' => $valor,
        'total_remaining_amount' => 0,
        'created_by' => $userId,
        'invoice_no' => 'CPP-' . uniqid(),
    ]));

    return (int) DB::table('transaction_payments')->insertGetId([
        'transaction_id' => $tx->id,
        'payment_for' => $contactId,
        'business_id' => $businessId,
        'amount' => $valor,
        'method' => 'bank_transfer',
        'is_return' => 0,
        'bank_account_number' => $contaBancaria,
        'paid_on' => Carbon::now()->toDateTimeString(),
        'created_by' => $userId,
        'created_at' => Carbon::now()->toDateTimeString(),
        'updated_at' => Carbon::now()->toDateTimeString(),
    ]);
}

test('UC-CSHW-03 — o endpoint entrega os pagamentos do contato (pré-condição anti-vácuo)', function () {
    $contato = cppCriarContato((int) $this->business->id, (int) $this->user->id);
    $pagamentoId = cppCriarPagamento(
        (int) $this->business->id,
        (int) $this->location->id,
        $contato,
        (int) $this->user->id,
        227.90,
        '00012345678'
    );

    $res = $this->getJson("/cliente/{$contato}/payments-json");
    $res->assertStatus(200);

    $pagamentos = $res->json('payments');

    // A operação ACONTECEU — sem isto, as asserções de ausência abaixo passariam
    // por lista vazia (verde por não-execução, proibicoes §5 2026-07-24).
    expect($pagamentos)->toBeArray()->not->toBeEmpty();
    expect(collect($pagamentos)->pluck('id')->all())->toContain($pagamentoId);
    expect(collect($pagamentos)->firstWhere('id', $pagamentoId)['amount'])->toEqual(227.90);
});

test('UC-CSHW-03 — [T0] o número da conta bancária não sai do servidor em nenhum ponto do payload', function () {
    $contaSintetica = '00098765432';

    $contato = cppCriarContato((int) $this->business->id, (int) $this->user->id);
    $pagamentoId = cppCriarPagamento(
        (int) $this->business->id,
        (int) $this->location->id,
        $contato,
        (int) $this->user->id,
        150.00,
        $contaSintetica
    );

    $res = $this->getJson("/cliente/{$contato}/payments-json");
    $res->assertStatus(200);

    // Pré-condição anti-vácuo: o pagamento que carrega a conta ESTÁ no payload.
    expect(collect($res->json('payments'))->pluck('id')->all())->toContain($pagamentoId);

    // Contrato = "a conta não vaza", NÃO "a chave se chama bank_account_number".
    // Varre o JSON CRU inteiro: renomear a chave não faz o vazamento passar.
    $corpoCru = $res->getContent();
    expect($corpoCru)->not->toContain($contaSintetica);

    // E o que sobra tem que ser reconhecível como redigido + os 4 últimos dígitos —
    // o operador precisa conferir o pagamento sem receber a conta inteira.
    $linha = collect($res->json('payments'))->firstWhere('id', $pagamentoId);
    $conta = collect($linha)->first(fn ($v) => is_string($v) && str_contains($v, '****'));
    expect($conta)->not->toBeNull();
    expect($conta)->toEndWith(substr($contaSintetica, -4));
});

test('UC-CSHW-03 — [T0] pagamento de OUTRO contato do mesmo tenant não entra na aba', function () {
    $meuContato = cppCriarContato((int) $this->business->id, (int) $this->user->id);
    $outroContato = cppCriarContato((int) $this->business->id, (int) $this->user->id);

    $meuPagamento = cppCriarPagamento(
        (int) $this->business->id,
        (int) $this->location->id,
        $meuContato,
        (int) $this->user->id,
        10.00,
        '00011112222'
    );
    $pagamentoAlheio = cppCriarPagamento(
        (int) $this->business->id,
        (int) $this->location->id,
        $outroContato,
        (int) $this->user->id,
        99.00,
        '00033334444'
    );

    $res = $this->getJson("/cliente/{$meuContato}/payments-json");
    $res->assertStatus(200);

    $ids = collect($res->json('payments'))->pluck('id')->all();
    expect($ids)->toContain($meuPagamento);       // anti-vácuo
    expect($ids)->not->toContain($pagamentoAlheio);
});

test('UC-CSHW-03 — [T0] contato de OUTRO business responde 404, nunca 403 nem 200', function () {
    $outroBusiness = DB::table('business')->where('id', '!=', $this->business->id)->first();
    if (! $outroBusiness) {
        $this->markTestSkipped('Lane sem 2º tenant semeado (biz=2) — ver .github/actions/pest-mysql-setup.');
    }

    $usuarioAlheio = \App\User::where('business_id', $outroBusiness->id)->first();
    $contatoAlheio = cppCriarContato(
        (int) $outroBusiness->id,
        (int) ($usuarioAlheio->id ?? $this->user->id)
    );

    // Pré-condição anti-vácuo: o contato existe de fato — o 404 abaixo prova
    // ISOLAMENTO, não ausência de registro.
    expect(DB::table('contacts')->where('id', $contatoAlheio)->exists())->toBeTrue();

    $this->getJson("/cliente/{$contatoAlheio}/payments-json")->assertStatus(404);
});

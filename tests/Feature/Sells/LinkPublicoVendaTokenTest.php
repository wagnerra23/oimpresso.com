<?php

declare(strict_types=1);

use App\Utils\Util;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Link público da venda (/invoice/{token}, /quote/{token}, /pay/{token}) — o token é a única
 * credencial do link. Contrato:
 *  - token novo tem 64 caracteres alfanuméricos e não se repete;
 *  - a migration de rotação apaga os tokens no formato antigo (32) e preserva os novos (64);
 *  - token apagado não acha mais a venda; token novo acha (controle positivo).
 *
 * Tier 0 (ADR 0093): o link é público, então a força do token é o que separa uma empresa da outra.
 */
uses(DatabaseTransactions::class);

it('gera token de 64 caracteres alfanuméricos, sem repetição', function () {
    $util = app(Util::class);
    $tokens = [];
    for ($i = 0; $i < 200; $i++) {
        $t = $util->generateToken();
        expect($t)->toMatch('/^[A-Za-z0-9]{64}$/');
        $tokens[] = $t;
    }
    expect(array_unique($tokens))->toHaveCount(200);
});

it('rotação apaga só tokens antigos; token antigo não acha a venda e o novo acha', function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasColumn('transactions', 'invoice_token')) {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS (ADR 0358).');
    }
    $biz = (int) $this->seededTenant()->id;
    $criador = (int) DB::table('users')->where('business_id', $biz)->value('id');
    $loc = (int) DB::table('business_locations')->where('business_id', $biz)->value('id');
    if (! $criador || ! $loc) {
        $this->markTestSkipped('Tenant semeado sem usuário/local.');
    }

    $antigo = md5('legado-' . uniqid());
    $novo = app(Util::class)->generateToken();
    $venda = fn (string $token) => (int) DB::table('transactions')->insertGetId([
        'business_id' => $biz, 'location_id' => $loc, 'type' => 'sell', 'status' => 'final',
        'payment_status' => 'due', 'transaction_date' => now(), 'final_total' => 10,
        'total_before_tax' => 10, 'created_by' => $criador, 'invoice_token' => $token,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $idAntigo = $venda($antigo);
    $idNovo = $venda($novo);

    (require base_path('database/migrations/2026_11_15_120000_rotate_legacy_invoice_tokens.php'))->up();

    expect(DB::table('transactions')->where('id', $idAntigo)->value('invoice_token'))->toBeNull()
        ->and(DB::table('transactions')->where('id', $idNovo)->value('invoice_token'))->toBe($novo);

    // Controle negativo: o token antigo não acha mais a venda.
    expect(DB::table('transactions')->where('invoice_token', $antigo)->exists())->toBeFalse();
    // Controle positivo: o token novo continua achando a venda certa.
    expect((int) DB::table('transactions')->where('invoice_token', $novo)->value('id'))->toBe($idNovo);
});

<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Models\BoletoRemessa;
use Modules\Financeiro\Models\CaixaMovimento;
use Modules\Financeiro\Models\Categoria;
use Modules\Financeiro\Models\ConciliacaoRegra;
use Modules\Financeiro\Models\TituloAnexo;
use Modules\Financeiro\Models\TituloComment;

// TestCase já vem de tests/Pest.php para tests/Feature; aqui só a transação.
uses(DatabaseTransactions::class);

/**
 * Isolamento entre empresas das 6 tabelas fin_* que não tinham prova rodando
 * (auditoria do Financeiro 2026-11-05, D5): fin_titulo_comments,
 * fin_boleto_remessas, fin_caixa_movimentos, fin_categorias,
 * fin_conciliacao_regras e fin_titulo_anexos.
 *
 * Para cada tabela: uma linha na empresa fictícia 98 e outra na 99 (ADR 0358),
 * com o mesmo marcador. Logado em cada empresa, o model enxerga só a própria
 * linha (controle positivo: a da empresa logada aparece) e `find()` da linha
 * alheia devolve null. Tudo dentro de transação: some no fim.
 *
 * O que isto prova é o escopo global BusinessScope de cada model. Não prova
 * rota nem controller — esses têm os testes próprios das telas.
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Precisa do schema MySQL da lane do Financeiro (fin_*).');
    }
    foreach ([98, 99] as $biz) {
        if (! DB::table('business')->where('id', $biz)->exists()) {
            $this->markTestSkipped("Tenant fictício {$biz} ausente — seed .github/actions/pest-mysql-setup.");
        }
    }
    $this->marca = 'ISO-'.bin2hex(random_bytes(4));
    $this->uid = [
        98 => (int) DB::table('users')->where('business_id', 98)->value('id'),
        99 => (int) DB::table('users')->where('business_id', 99)->value('id'),
    ];
    if (in_array(0, $this->uid, true)) {
        $this->markTestSkipped('Sem usuário nos tenants 98/99.');
    }
});

afterEach(function () {
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
});

/** Título mínimo da empresa (pai dos comentários, anexos e remessas). */
function isoTitulo(int $biz, int $uid, string $marca): int
{
    return DB::table('fin_titulos')->insertGetId([
        'business_id' => $biz, 'numero' => substr($marca, 0, 12).'-'.$biz, 'tipo' => 'receber', 'status' => 'aberto',
        'cliente_descricao' => $marca, 'valor_total' => 10, 'valor_aberto' => 10, 'moeda' => 'BRL',
        'emissao' => now()->toDateString(), 'vencimento' => now()->toDateString(),
        'competencia_mes' => now()->format('Y-m'), 'origem' => 'manual', 'created_by' => $uid,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Insere 1 linha por empresa e devolve [98 => id, 99 => id]. */
function isoSemeia(string $tabela, array $uid, string $marca): array
{
    if (! Schema::hasTable($tabela)) {
        test()->markTestSkipped("{$tabela} ausente — rode migrate.");
    }
    // FK de conta/account fictícias: o que está em teste é o escopo, não a integridade.
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    $ids = [];
    foreach ([98, 99] as $biz) {
        $ids[$biz] = DB::table($tabela)->insertGetId(isoLinha($tabela, $biz, $uid[$biz], $marca) + ['business_id' => $biz]);
    }
    DB::statement('SET FOREIGN_KEY_CHECKS=1');

    return $ids;
}

/** Linha mínima de cada tabela para a empresa $biz (colunas NOT NULL do schema). */
function isoLinha(string $tabela, int $biz, int $uid, string $m): array
{
    return match ($tabela) {
        'fin_titulo_comments' => [
            'titulo_id' => isoTitulo($biz, $uid, $m), 'user_id' => $uid, 'body' => $m, 'created_at' => now(),
        ],
        'fin_boleto_remessas' => [
            'titulo_id' => isoTitulo($biz, $uid, $m), 'conta_bancaria_id' => 990000 + $biz, 'nosso_numero' => $m,
            'linha_digitavel' => str_repeat('0', 47), 'codigo_barras' => str_repeat('0', 44), 'valor_total' => 10,
            'vencimento' => now()->toDateString(), 'strategy' => 'mock', 'idempotency_key' => $m.'-'.$biz,
            'created_at' => now(), 'updated_at' => now(),
        ],
        'fin_caixa_movimentos' => [
            'tipo' => 'entrada', 'valor' => 10, 'data' => now()->toDateString(), 'saldo_apos' => 10,
            'descricao' => $m, 'created_by' => $uid, 'created_at' => now(),
        ],
        'fin_categorias' => ['nome' => $m, 'created_at' => now(), 'updated_at' => now()],
        'fin_conciliacao_regras' => ['nome' => $m, 'created_by' => $uid, 'created_at' => now(), 'updated_at' => now()],
        'fin_titulo_anexos' => [
            'titulo_id' => isoTitulo($biz, $uid, $m), 'nome' => $m, 'path' => 'iso/'.$m.'.pdf',
            'uploaded_by' => $uid, 'created_at' => now(), 'updated_at' => now(),
        ],
    };
}

dataset('tabelas_fin_sem_prova', [
    'fin_titulo_comments'    => [TituloComment::class, 'fin_titulo_comments', 'body'],
    'fin_boleto_remessas'    => [BoletoRemessa::class, 'fin_boleto_remessas', 'nosso_numero'],
    'fin_caixa_movimentos'   => [CaixaMovimento::class, 'fin_caixa_movimentos', 'descricao'],
    'fin_categorias'         => [Categoria::class, 'fin_categorias', 'nome'],
    'fin_conciliacao_regras' => [ConciliacaoRegra::class, 'fin_conciliacao_regras', 'nome'],
    'fin_titulo_anexos'      => [TituloAnexo::class, 'fin_titulo_anexos', 'nome'],
]);

it('Tier 0 · cada empresa só enxerga a própria linha da tabela', function (string $model, string $tabela, string $coluna) {
    $ids = isoSemeia($tabela, $this->uid, $this->marca);

    foreach ([98 => 99, 99 => 98] as $logada => $alheia) {
        session(['user.business_id' => $logada]);

        $vistas = $model::query()->where($coluna, $this->marca)->pluck('business_id')->map(fn ($v) => (int) $v)->all();
        expect($vistas)->toBe([$logada], "{$tabela}: logado na empresa {$logada} deveria ver só a própria linha.");

        expect($model::query()->find($ids[$alheia]))->toBeNull("{$tabela}: find() da linha da empresa {$alheia} vazou para a {$logada}.");
        expect($model::query()->find($ids[$logada]))->not->toBeNull("{$tabela}: a linha da própria empresa {$logada} sumiu.");
    }
})->with('tabelas_fin_sem_prova');

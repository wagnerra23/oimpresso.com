<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Schema;
use Modules\Fiscal\Http\Controllers\AcoesController;
use Modules\Fiscal\Http\Controllers\SpedController;
use Modules\Fiscal\Services\SpedIcmsIpiGeneratorService;
use Modules\NfeBrasil\Services\Manifestacao\ManifestacaoService;
use Modules\NfeBrasil\Services\NfeCartaCorrecaoService;
use Modules\NfeBrasil\Services\NfeService;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Erro técnico não chega à tela (auditoria Fiscal 2026-11-05, lote 1.4).
 *
 * ÂNCORA (regra escrita, não o código): memory/proibicoes.md §Multi-tenant — "PII reais
 * NUNCA em log"/tela, e o princípio 7 (Transparência) da Constituição v2 aplicado ao
 * usuário: a mensagem diz o que falhou e o que fazer; o detalhe técnico vai pro log.
 * Antes deste lote, os `catch` de AcoesController e SpedController devolviam
 * `$e->getMessage()` cru — SQL, caminho de arquivo, resposta da SEFAZ.
 *
 * Cada caso faz o serviço estourar com um texto técnico marcado e prova três coisas:
 * a resposta NÃO contém o texto · contém a mensagem em PT-BR · a exceção foi reportada.
 *
 * Tenant 98 (fictício, ADR 0358). Nunca biz=4. Dados numa transação revertida.
 */
const ERRO_TEC_SEGREDO = "SQLSTATE[42S22]: Column not found: 1054 coluna_x_secreta in /var/www/html/app.php";

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: NfeBrasil requer schema MySQL (ADR 0358)');
    }
    if (! Schema::hasTable('nfe_emissoes') || ! Schema::hasTable('nfe_dfe_recebidos')) {
        $this->markTestSkipped('Tabelas NfeBrasil ausentes — rode as migrations do NfeBrasil');
    }

    // Usuário que passa em todo gate: o que se prova aqui é o `catch`, não a permissão.
    Auth::setUser(new class extends User
    {
        public function can($abilities, $arguments = []): bool
        {
            return true;
        }
    });

    $this->biz = (int) $this->seededTenant()->id;
    session(['user.business_id' => $this->biz, 'business.id' => $this->biz]);

    Exceptions::fake();
});

function erroTecNota(int $businessId): int
{
    return DB::table('nfe_emissoes')->insertGetId([
        'business_id' => $businessId, 'modelo' => '55', 'serie' => '953', 'numero' => random_int(9_530_000, 9_539_999),
        'status' => 'autorizada', 'valor_total' => 10, 'emitido_em' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function erroTecSemVazamento(?string $mensagem, string $acao): void
{
    expect((string) $mensagem)->not->toContain('SQLSTATE')
        ->and((string) $mensagem)->not->toContain('coluna_x_secreta')
        ->and((string) $mensagem)->not->toContain('/var/www')
        ->and((string) $mensagem)->toContain("Não foi possível {$acao}");

    Exceptions::assertReported(RuntimeException::class);
}

it('cancelar NF-e: o erro técnico do serviço não aparece na mensagem', function () {
    $nota = erroTecNota($this->biz);
    $nfe = Mockery::mock(NfeService::class);
    $nfe->shouldReceive('cancelar')->andThrow(new RuntimeException(ERRO_TEC_SEGREDO));

    (new AcoesController())->cancelarNfe(
        Request::create('/x', 'POST', ['motivo' => 'Cliente desistiu da compra inteira']),
        $nfe,
        $nota,
    );

    erroTecSemVazamento(session('error'), 'cancelar a NF-e');
});

it('carta de correção: o erro técnico do serviço não aparece na mensagem', function () {
    $nota = erroTecNota($this->biz);
    $cce = Mockery::mock(NfeCartaCorrecaoService::class);
    $cce->shouldReceive('aplicar')->andThrow(new RuntimeException(ERRO_TEC_SEGREDO));

    (new AcoesController())->cartaCorrecao(
        Request::create('/x', 'POST', ['texto_correcao' => 'Corrige o endereço do destinatário', 'n_seq_evento' => 1]),
        $cce,
        $nota,
    );

    erroTecSemVazamento(session('error'), 'aplicar a carta de correção');
});

it('manifestação em lote: a linha da nota que falhou não traz o erro técnico', function () {
    $dfe = DB::table('nfe_dfe_recebidos')->insertGetId([
        'business_id' => $this->biz,
        'chave_44' => str_pad((string) random_int(1, PHP_INT_MAX), 44, '3', STR_PAD_LEFT),
        'nsu' => random_int(1, 999_999_999), 'cnpj_emitente' => '00000000000191',
        'nome_emitente' => 'Fornecedor teste erro técnico', 'data_emissao' => now(),
        'status_manifestacao' => 'pendente', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $servico = Mockery::mock(ManifestacaoService::class);
    $servico->shouldReceive('cienciar')->andThrow(new RuntimeException(ERRO_TEC_SEGREDO));

    $resposta = (new AcoesController())->manifestarDfeLote(
        Request::create('/x', 'POST', ['ids' => [$dfe], 'acao' => 'cienciar']),
        $servico,
    );

    $relatorio = (array) $resposta->getSession()->get('fiscal.dfe.lote');
    $falhas = collect($relatorio['falhas'] ?? [])->keyBy('id');

    expect($falhas->has($dfe))->toBeTrue('a nota que estourou precisa aparecer nomeada no relatório');
    expect((string) $falhas[$dfe]['erro'])->not->toContain('SQLSTATE')
        ->and((string) $falhas[$dfe]['erro'])->not->toContain('coluna_x_secreta')
        ->and((string) $falhas[$dfe]['erro'])->toContain('Não foi possível manifestar');

    Exceptions::assertReported(RuntimeException::class);
});

it('SPED: a resposta 500 não traz o erro técnico do gerador', function () {
    $gerador = Mockery::mock(SpedIcmsIpiGeneratorService::class);
    $gerador->shouldReceive('gerar')->andThrow(new RuntimeException(ERRO_TEC_SEGREDO));

    $resposta = app(SpedController::class)->gerar($gerador, 2026, 1);

    expect($resposta->getStatusCode())->toBe(500);
    erroTecSemVazamento($resposta->getContent(), 'gerar o arquivo SPED');
});

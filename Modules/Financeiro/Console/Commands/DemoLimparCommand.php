<?php

declare(strict_types=1);

namespace Modules\Financeiro\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Financeiro\Database\Seeders\FinanceiroDemoSeeder;

/**
 * Remove os dados fictícios que o FinanceiroDemoSeeder criou numa empresa.
 *
 * Só toca no que o seeder marca:
 *   - fin_titulo_baixas  com observacoes LIKE 'SEEDER_DEMO%'   (apagadas, como o próprio seeder faz)
 *   - fin_titulos        com observacoes LIKE 'SEEDER_DEMO%'   (soft delete)
 *   - contacts           com contact_id LIKE 'DEMO-FIN-%'      (soft delete)
 *   - fin_categorias     com nome LIKE '[TESTE] %'             (soft delete)
 *   - fin_contas_bancarias / accounts da conta '[TESTE] Conta fictícia - NÃO USAR' (soft delete)
 *
 * Dry-run por padrão: mostra quantos registros sairiam de cada tabela. --force aplica.
 * Recusa se algum desses contatos ou a conta fictícia estiverem em uso fora do
 * seeder (título não-demo, venda, baixa não-demo), para não apagar nada real.
 */
class DemoLimparCommand extends Command
{
    protected $signature = 'financeiro:demo-limpar
                            {--business= : empresa (obrigatório)}
                            {--force : apaga de fato (o padrão é dry-run)}';

    protected $description = 'Remove os dados fictícios do FinanceiroDemoSeeder ([TESTE]) de uma empresa. Dry-run por padrão.';

    public function handle(): int
    {
        $biz = (int) $this->option('business');
        if ($biz <= 0) {
            $this->error('Informe --business=<id>.');

            return self::INVALID;
        }

        $tag = FinanceiroDemoSeeder::TAG.'%';
        $contatoIds = DB::table('contacts')->where('business_id', $biz)
            ->where('contact_id', 'LIKE', FinanceiroDemoSeeder::CONTATO_PREFIXO.'%')
            ->whereNull('deleted_at')->pluck('id')->all();
        $contas = DB::table('fin_contas_bancarias')->where('business_id', $biz)
            ->where('beneficiario_razao_social', FinanceiroDemoSeeder::CONTA_NOME)
            ->whereNull('deleted_at')->get(['id', 'account_id']);
        $contaIds = $contas->pluck('id')->all();
        $accountIds = $contas->pluck('account_id')->all();

        // Trava: nada real pode estar apoiado nos registros de teste.
        $usoReal = [
            'títulos reais com contato de teste' => DB::table('fin_titulos')->where('business_id', $biz)
                ->whereIn('cliente_id', $contatoIds ?: [0])->where(fn ($q) => $q->whereNull('observacoes')
                    ->orWhere('observacoes', 'NOT LIKE', $tag))->whereNull('deleted_at')->count(),
            'vendas/compras com contato de teste' => DB::table('transactions')->where('business_id', $biz)
                ->whereIn('contact_id', $contatoIds ?: [0])->count(),
            'baixas reais na conta de teste' => DB::table('fin_titulo_baixas')->where('business_id', $biz)
                ->whereIn('conta_bancaria_id', $contaIds ?: [0])->where(fn ($q) => $q->whereNull('observacoes')
                    ->orWhere('observacoes', 'NOT LIKE', $tag))->count(),
            'movimentos na conta de teste' => DB::table('account_transactions')
                ->whereIn('account_id', $accountIds ?: [0])->count(),
        ];

        $plano = [
            'fin_titulo_baixas (apagar)' => DB::table('fin_titulo_baixas')->where('business_id', $biz)
                ->where('observacoes', 'LIKE', $tag)->count(),
            'fin_titulos (soft delete)' => DB::table('fin_titulos')->where('business_id', $biz)
                ->where('observacoes', 'LIKE', $tag)->whereNull('deleted_at')->count(),
            'contacts (soft delete)' => count($contatoIds),
            'fin_categorias (soft delete)' => DB::table('fin_categorias')->where('business_id', $biz)
                ->where('nome', 'LIKE', FinanceiroDemoSeeder::PREFIXO.'%')->whereNull('deleted_at')->count(),
            'fin_contas_bancarias (soft delete)' => count($contaIds),
            'accounts (soft delete)' => count($accountIds),
        ];

        $this->info("financeiro:demo-limpar — business_id={$biz} — ".($this->option('force') ? 'APLICANDO' : 'DRY-RUN (nada apagado)'));
        foreach ($plano as $tabela => $n) {
            $this->line("  {$tabela}: {$n}");
        }

        $bloqueios = array_filter($usoReal);
        if ($bloqueios) {
            foreach ($bloqueios as $o => $n) {
                $this->error("  BLOQUEADO: {$o} = {$n}");
            }
            $this->error('Nada foi apagado. Os registros de teste estão em uso por dado real.');

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->warn('Dry-run. Use --force para apagar.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($biz, $tag, $contatoIds, $contaIds, $accountIds) {
            $agora = now();
            // Baixas de demo: o seeder também as apaga assim (append-only vale para baixa real).
            DB::table('fin_titulo_baixas')->where('business_id', $biz)->where('observacoes', 'LIKE', $tag)->delete();
            DB::table('fin_titulos')->where('business_id', $biz)->where('observacoes', 'LIKE', $tag)
                ->whereNull('deleted_at')->update(['deleted_at' => $agora]);
            DB::table('contacts')->where('business_id', $biz)->whereIn('id', $contatoIds ?: [0])
                ->update(['deleted_at' => $agora]);
            DB::table('fin_categorias')->where('business_id', $biz)
                ->where('nome', 'LIKE', FinanceiroDemoSeeder::PREFIXO.'%')->whereNull('deleted_at')
                ->update(['deleted_at' => $agora]);
            DB::table('fin_contas_bancarias')->where('business_id', $biz)->whereIn('id', $contaIds ?: [0])
                ->update(['deleted_at' => $agora]);
            DB::table('accounts')->where('business_id', $biz)->whereIn('id', $accountIds ?: [0])
                ->update(['deleted_at' => $agora]);
        });

        $this->info('Feito.');

        return self::SUCCESS;
    }
}

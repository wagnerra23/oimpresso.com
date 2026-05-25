<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0183 PR A — Ponte cash_registers (core UPOS) → fin_titulos (Modules/Financeiro).
 *
 * 3 mudanças schema + 1 seed:
 *
 *  1. ALTER fin_titulos.origem ENUM: ADD 'caixa'
 *     Permite distinguir títulos vindos de fechamento de caixa físico
 *     (cash_registers.id em origem_id) de outros origens.
 *     UNIQUE (business_id, origem, origem_id, parcela_numero) já existente
 *     garante idempotência sem colidir com PaymentGateway (origem='manual').
 *
 *  2. ALTER fin_contas_bancarias ADD `tipo_conta` VARCHAR(50):
 *     'banco' (default), 'caixa', 'gateway', 'aplicacao'.
 *     Conta-mãe consolidadora cash_registers tem tipo_conta='caixa'.
 *
 *  3. SEED — pra cada business:
 *     a. Cria `accounts` (core UPOS) com name='Caixa Loja', account_type=NULL
 *        (UPOS enum aceita 'saving_current'|'capital'|NULL; caixa não bate em
 *        nenhum dos 2 — NULL é semanticamente correto).
 *     b. Cria `fin_contas_bancarias` linkado a essa accounts.id com:
 *          tipo_conta='caixa'
 *          ativo_para_boleto=false (nunca emite boleto)
 *          banco_codigo='000' / agencia='0' / carteira='-' (dummy)
 *          beneficiario_razao_social=business.name (real, pra audit)
 *          beneficiario_documento=business.tax_number OU dummy
 *
 * Reversibilidade (down):
 *   - Reverte enum origem (bloqueia se houver fin_titulos.origem='caixa' existentes)
 *   - Drop coluna tipo_conta + índice
 *   - NÃO deleta accounts/fin_contas_bancarias seeded (preserva histórico — Wagner
 *     deleta manualmente se quiser limpar tudo)
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL): business_id scope em todos seeds.
 *
 * Pegadinhas mitigadas:
 *   P8 — firstOrCreate race: usamos check `exists()` antes do INSERT
 *   P15 — conta-mãe deletada: ContaBancariaController valida `tipo_conta='caixa'`
 *         antes de delete (PR C reforça isso)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ============================================================
        // 1. ALTER enum origem em fin_titulos — adicionar 'caixa'
        // ============================================================
        DB::statement("
            ALTER TABLE fin_titulos
            MODIFY COLUMN origem ENUM('manual', 'venda', 'compra', 'despesa', 'recurring', 'folha', 'caixa')
            NOT NULL
        ");

        // ============================================================
        // 2. ALTER fin_contas_bancarias — ADD tipo_conta
        // ============================================================
        if (! Schema::hasColumn('fin_contas_bancarias', 'tipo_conta')) {
            Schema::table('fin_contas_bancarias', function (Blueprint $table) {
                $table->string('tipo_conta', 50)
                    ->default('banco')
                    ->after('id')
                    ->comment('banco | caixa | gateway | aplicacao — caixa = consolidadora cash_registers');

                $table->index(['business_id', 'tipo_conta'], 'idx_fin_contas_business_tipo');
            });
        }

        // ============================================================
        // 3. SEED — conta-mãe 'Caixa Loja' por business
        //    (idempotente — skip se já existir)
        // ============================================================
        $businesses = DB::table('business')
            ->select('id', 'name', 'tax_number_1')
            ->get();

        foreach ($businesses as $biz) {
            // Skip se business já tem conta 'caixa'
            $jaExiste = DB::table('fin_contas_bancarias')
                ->where('business_id', $biz->id)
                ->where('tipo_conta', 'caixa')
                ->exists();

            if ($jaExiste) {
                continue;
            }

            // 3a. Cria accounts dummy (core UPOS) — pra satisfazer FK 1-1
            // Schema real UPOS (oimpresso): coluna é `account_type_id` FK pra `account_types` (nullable),
            // NÃO `account_type` enum como assumia versão anterior desta migration (fix 2026-05-25).
            $accountId = DB::table('accounts')->insertGetId([
                'business_id'     => $biz->id,
                'name'            => 'Caixa Loja',
                'account_number'  => 'CAIXA-' . $biz->id,
                'account_type_id' => null, // FK opcional — caixa não bate em saving/capital tipos
                'note'            => 'Conta-mãe consolidadora de fechamentos de caixa físico (ADR 0183)',
                'created_by'      => 1, // System / Wagner-default
                'is_closed'       => 0,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            // 3b. Cria fin_contas_bancarias linkada a essa accounts
            //     Campos boleto-only com valores dummy (ativo_para_boleto=false impede emissão)
            DB::table('fin_contas_bancarias')->insert([
                'business_id'                => $biz->id,
                'account_id'                 => $accountId,
                'tipo_conta'                 => 'caixa',
                'banco_codigo'               => '000', // dummy — não é banco real
                'agencia'                    => '0',
                'carteira'                   => '-',
                'beneficiario_documento'     => $biz->tax_number_1 ?? '00.000.000/0000-00',
                'beneficiario_razao_social'  => $biz->name ?? 'Caixa Loja',
                'ativo_para_boleto'          => false, // P15 — nunca emite boleto da conta caixa
                'metadata'                   => json_encode([
                    'source' => 'adr_0183_caixa_bridge_seed',
                    'criada_em' => now()->toDateTimeString(),
                ]),
                'created_at'                 => now(),
                'updated_at'                 => now(),
            ]);
        }
    }

    public function down(): void
    {
        // 1. Bloqueia revert se houver títulos origem='caixa' (preserva histórico)
        $count = DB::table('fin_titulos')->where('origem', 'caixa')->count();
        if ($count > 0) {
            throw new \RuntimeException(
                "Migration down bloqueada: {$count} fin_titulos com origem='caixa' ainda existem. " .
                "Cancele/migre antes de reverter."
            );
        }

        // 1a. Reverte enum origem
        DB::statement("
            ALTER TABLE fin_titulos
            MODIFY COLUMN origem ENUM('manual', 'venda', 'compra', 'despesa', 'recurring', 'folha')
            NOT NULL
        ");

        // 2. Drop tipo_conta + index
        if (Schema::hasColumn('fin_contas_bancarias', 'tipo_conta')) {
            Schema::table('fin_contas_bancarias', function (Blueprint $table) {
                $table->dropIndex('idx_fin_contas_business_tipo');
                $table->dropColumn('tipo_conta');
            });
        }

        // 3. NÃO deletamos accounts/fin_contas_bancarias seeded (preserva histórico).
        //    Wagner limpa manualmente se quiser:
        //      DELETE FROM fin_contas_bancarias WHERE tipo_conta = 'caixa';
        //      DELETE FROM accounts WHERE account_number LIKE 'CAIXA-%';
    }
};

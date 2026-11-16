<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Naturezas de operação, onda 2 (RUNBOOK-tributacao §Naturezas): os campos que a tela aprovada
 * guarda e `nfe_operacoes_fiscais` ainda não tinha.
 *
 * Só ACRESCENTA colunas, e cada uma nasce com o valor que reproduz o comportamento de hoje.
 * Nenhuma linha existente muda (append-only, ADR 0093 G8), e o motor ainda não lê estas
 * colunas — quem passa a ler é a onda 4, com prova de valor.
 *
 * - `tipo`: saida · entrada. NULL = decide pelo 1º dígito do `cfop`, como hoje.
 * - `ativa`: desligada fica guardada e sai do lançamento. Default true: toda operação de hoje segue ativa.
 * - `pede_nota_referenciada`: a nota exige a chave da nota de origem (devolução, garantia).
 * - `cfop_interestadual` / `cfop_exterior`: o `cfop` existente segue sendo o de dentro do estado.
 *   NULL = usa o `cfop` com a troca do "?" pelo destino (R-NFE-020c), que é a regra de hoje.
 *   Existem porque há pares com sufixo diferente (5405 / 6404) que o "?" não cobre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nfe_operacoes_fiscais', function (Blueprint $table) {
            if (! Schema::hasColumn('nfe_operacoes_fiscais', 'tipo')) {
                $table->string('tipo', 7)->nullable()->after('nome')
                    ->comment('saida · entrada · NULL = pelo 1º dígito do cfop');
            }
            if (! Schema::hasColumn('nfe_operacoes_fiscais', 'ativa')) {
                $table->boolean('ativa')->default(true)->after('padrao');
            }
            if (! Schema::hasColumn('nfe_operacoes_fiscais', 'pede_nota_referenciada')) {
                $table->boolean('pede_nota_referenciada')->default(false)->after('finalidade');
            }
            if (! Schema::hasColumn('nfe_operacoes_fiscais', 'cfop_interestadual')) {
                $table->char('cfop_interestadual', 4)->nullable()->after('cfop')
                    ->comment('NULL = cfop com "?" resolvido pelo destino');
            }
            if (! Schema::hasColumn('nfe_operacoes_fiscais', 'cfop_exterior')) {
                $table->char('cfop_exterior', 4)->nullable()->after('cfop_interestadual')
                    ->comment('NULL = cfop com "?" resolvido pelo destino');
            }
        });
    }

    public function down(): void
    {
        Schema::table('nfe_operacoes_fiscais', function (Blueprint $table) {
            foreach (['tipo', 'ativa', 'pede_nota_referenciada', 'cfop_interestadual', 'cfop_exterior'] as $coluna) {
                if (Schema::hasColumn('nfe_operacoes_fiscais', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};

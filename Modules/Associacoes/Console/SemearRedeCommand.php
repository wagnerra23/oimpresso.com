<?php

namespace Modules\Associacoes\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dados iniciais da rede (ADR 0451 · data readiness Uniafibro, RUNBOOK-data-readiness-uniafibro).
 *
 * Grava as associações-membro que existiam quando a rede entrou no sistema. Regras:
 *  - `--business` é obrigatório: CLI não tem sessão, então o escopo global do Model é inerte
 *    (App\Scopes\ScopeByBusiness) e a empresa vem SEMPRE do parâmetro (ADR 0093).
 *  - Sem `--aplicar` é só simulação: lista o que faria e não grava nada.
 *  - Idempotente: associação já cadastrada (mesmo nome na mesma empresa) é pulada, nunca
 *    sobrescrita — o que a diretoria editou pela tela vale mais que esta lista.
 *  - Só associações. Associada é dado pessoal com consentimento (D5) e entra pela tela, nunca por
 *    seed. Finalidades de consentimento não são linhas: vivem em config('associacoes.consentimento_versoes').
 *
 * Nome, CNPJ e representante ficam para a diretoria completar pela tela (onda 3): enquanto o
 * estatuto de cada uma não for conferido, o nome gravado é a sigla.
 */
class SemearRedeCommand extends Command
{
    protected $signature = 'associacoes:semear-rede
        {--business= : ID da empresa da rede (obrigatório)}
        {--aplicar : grava de verdade; sem isto é só simulação}';

    protected $description = 'Associações: grava as associações-membro iniciais da rede (simulação por padrão)';

    /** Rede em 2026-11-13 (ADR 0451, Contexto). Região = mesorregião IBGE. */
    public const REDE_INICIAL = [
        ['nome' => 'AGFESC', 'sigla' => 'AGFESC', 'cidade' => 'Criciúma', 'regiao' => 'Sul Catarinense'],
        ['nome' => 'AFIBROMAR', 'sigla' => 'AFIBROMAR', 'cidade' => 'Maravilha', 'regiao' => 'Oeste Catarinense'],
    ];

    public function handle(): int
    {
        $businessId = (int) $this->option('business');
        if ($businessId <= 0) {
            $this->error('Informe --business=<id da empresa da rede>.');

            return self::FAILURE;
        }
        if (! Schema::hasTable('assoc_associacoes')) {
            $this->error('Tabela assoc_associacoes ausente: rode as migrations do Modules/Associacoes antes.');

            return self::FAILURE;
        }
        $empresa = DB::table('business')->where('id', $businessId)->value('name');
        if ($empresa === null) {
            $this->error("Empresa {$businessId} não existe.");

            return self::FAILURE;
        }

        $aplicar = (bool) $this->option('aplicar');
        $this->info(($aplicar ? 'APLICANDO' : 'SIMULAÇÃO (nada será gravado)')." · empresa {$businessId} · {$empresa}");

        $criadas = 0;
        foreach (self::REDE_INICIAL as $assoc) {
            $existe = DB::table('assoc_associacoes')
                ->where('business_id', $businessId)
                ->where('nome', $assoc['nome'])
                ->exists();
            if ($existe) {
                $this->line("  = {$assoc['nome']} ({$assoc['cidade']}) já cadastrada — pulada");

                continue;
            }
            $this->line("  + {$assoc['nome']} ({$assoc['cidade']})");
            if ($aplicar) {
                DB::table('assoc_associacoes')->insert($assoc + [
                    'business_id' => $businessId,
                    'situacao' => 'ativa',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $criadas++;
        }

        $this->info($aplicar ? "{$criadas} associação(ões) gravada(s)." : "{$criadas} associação(ões) seriam gravadas. Rode de novo com --aplicar.");

        return self::SUCCESS;
    }
}

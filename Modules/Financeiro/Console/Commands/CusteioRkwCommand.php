<?php

declare(strict_types=1);

namespace Modules\Financeiro\Console\Commands;

use DomainException;
use Illuminate\Console\Command;
use Modules\Financeiro\Services\CusteioRkwService;

/**
 * Fechamento do mês — custeio RKW e ponto de equilíbrio (US-FIN-070 · UC-FLX-20).
 *
 * SÓ LÊ, sempre: mostra o custo-hora sugerido de cada posto e o ponto de equilíbrio.
 * Gravar o custo-hora no posto é ação explícita na tela (POST .../custeio-rkw/aplicar),
 * nunca deste comando. `--dry-run` é aceito por clareza e não muda nada.
 * `--business` obrigatório (Tier 0, ADR 0093).
 */
class CusteioRkwCommand extends Command
{
    protected $signature = 'financeiro:custeio-rkw {--business= : business_id (obrigatório)} {--mes= : AAAA-MM} {--dry-run : só leitura (sempre é)}';

    protected $description = 'Custeio RKW do mês: rateio das despesas fixas por hora apontada e ponto de equilíbrio (só leitura)';

    public function handle(CusteioRkwService $service): int
    {
        $biz = (int) $this->option('business');
        $mes = (string) ($this->option('mes') ?: now()->subMonthNoOverflow()->format('Y-m'));
        if ($biz <= 0) {
            $this->error('--business é obrigatório.');

            return self::INVALID;
        }

        try {
            $r = $service->calcular($biz, $mes);
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::INVALID;
        }

        $this->info("Custeio RKW {$r['mes']} — business {$biz} (só leitura)");
        $this->line("Despesas fixas: {$r['despesas_fixas']} · custos variáveis: {$r['custos_variaveis']} · receita: {$r['receita']} · horas: {$r['horas']}");
        $this->table(['Posto', 'Horas', 'Rateio fixo', 'Custo-hora atual', 'Parcela fixa/h', 'Custo-hora RKW'],
            array_map(fn ($p) => [$p['codigo'].' '.$p['nome'], $p['horas'], $p['rateio_fixo'], $p['custo_hora_atual'],
                $p['parcela_fixa_por_hora'], $p['custo_hora_rkw']], $r['postos']));
        $this->line('Margem de contribuição: '.($r['margem_contribuicao_pct'] ?? '—').'% · ponto de equilíbrio: '.($r['ponto_equilibrio'] ?? '—'));
        foreach ($r['avisos'] as $aviso) {
            $this->warn($aviso);
        }

        return self::SUCCESS;
    }
}

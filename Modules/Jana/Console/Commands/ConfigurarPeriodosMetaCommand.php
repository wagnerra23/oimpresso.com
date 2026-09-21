<?php

namespace Modules\Jana\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Jana\Entities\Meta;
use Modules\Jana\Entities\MetaPeriodo;

/**
 * Configura o `MetaPeriodo` das metas — a janela e o ALVO contra o qual o realizado é
 * comparado.
 *
 * POR QUE EXISTE (medido em produção, 2026-09-21): as 5 metas de biz=1 estavam sem
 * período. Sem período, `ApuracaoService::projecao()` devolve `null` e o farol vira
 * `cinza` — os cards mostram o realizado e nada de "% do alvo", projeção ou farol,
 * mesmo com a apuração toda gravada.
 *
 * ── O ALVO NÃO É DERIVÁVEL, E POR ISSO É OBRIGATÓRIO ─────────────────────────
 * `valor_alvo` é `decimal(15,2) NOT NULL` — não existe período sem alvo. E alvo é
 * decisão de negócio: quanto a empresa QUER faturar não se lê do histórico. Derivar do
 * passado (média dos últimos meses, por exemplo) produziria um alvo que a empresa nunca
 * escolheu, contra o qual um farol vermelho não significaria nada.
 *
 * Então este comando **não inventa alvo**. Sem `--alvo-*` para uma meta, ele PULA e
 * mostra o histórico medido daquela métrica, para quem decide ter número na mão.
 *
 * ── A JANELA, ESSA SIM, É DERIVADA ───────────────────────────────────────────
 * `tipo_periodo=mes`, `data_ini`=início do mês corrente, `data_fim`=fim do mês corrente.
 * É o que casa com `ApuracaoService::apurar()`, que calcula `startOfMonth(ref) → ref`.
 *
 * Uso:
 *   php artisan jana:metas:configurar-periodos --business=1 --historico
 *   php artisan jana:metas:configurar-periodos --business=1 --alvo-faturamento=5000 --dry-run
 *   php artisan jana:metas:configurar-periodos --business=1 --alvo-faturamento=5000 --alvo-vendas=20
 *
 * Idempotente: `updateOrCreate` por (`meta_id`, `data_ini`).
 */
class ConfigurarPeriodosMetaCommand extends Command
{
    protected $signature = 'jana:metas:configurar-periodos
                            {--business= : ID do business (OBRIGATÓRIO — Tier 0, o CLI não tem session())}
                            {--alvo-faturamento= : Alvo de "Faturamento mensal" (R$) no mês}
                            {--alvo-ticket= : Alvo de "Ticket médio" (R$)}
                            {--alvo-vendas= : Alvo de "Vendas no mês" (quantidade)}
                            {--alvo-clientes= : Alvo de "Clientes atendidos" (quantidade)}
                            {--trajetoria=linear : linear|sazonal|exponencial|manual}
                            {--historico : Só mostra o histórico medido por métrica, sem tocar em nada}
                            {--dry-run : Mostra o que faria, sem gravar}';

    protected $description = 'Configura o MetaPeriodo (janela + alvo) das metas de venda de um business';

    /** nome normalizado da meta → opção de alvo + expressão que mede o histórico. */
    private function definicoes(): array
    {
        return [
            'faturamento mensal' => [
                'opcao'  => 'alvo-faturamento',
                'select' => 'COALESCE(SUM(final_total), 0)',
                'fmt'    => fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.'),
            ],
            'ticket medio' => [
                'opcao'  => 'alvo-ticket',
                'select' => 'COALESCE(SUM(final_total) / NULLIF(COUNT(*), 0), 0)',
                'fmt'    => fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.'),
            ],
            'vendas no mes' => [
                'opcao'  => 'alvo-vendas',
                'select' => 'COUNT(*)',
                'fmt'    => fn ($v) => (string) (int) $v,
            ],
            'clientes atendidos' => [
                'opcao'  => 'alvo-clientes',
                'select' => 'COUNT(DISTINCT contact_id)',
                'fmt'    => fn ($v) => (string) (int) $v,
            ],
        ];
    }

    public function handle(): int
    {
        $bizOpt = $this->option('business');

        if ($bizOpt === null || $bizOpt === '' || ! ctype_digit((string) $bizOpt)) {
            $this->error('--business=<id> é obrigatório (inteiro). O CLI não tem session(), então o escopo vem daqui.');

            return self::FAILURE;
        }

        $businessId = (int) $bizOpt;
        $dry        = (bool) $this->option('dry-run');
        $trajetoria = (string) $this->option('trajetoria');

        if (! in_array($trajetoria, ['linear', 'sazonal', 'exponencial', 'manual'], true)) {
            $this->error("--trajetoria inválida: {$trajetoria}. Use linear|sazonal|exponencial|manual.");

            return self::FAILURE;
        }

        $defs = $this->definicoes();

        if ($this->option('historico')) {
            return $this->mostrarHistorico($businessId, $defs);
        }

        // SUPERADMIN: comando de manutenção fora de request; o escopo é reimposto no where.
        $metas = Meta::withoutGlobalScopes()->where('business_id', $businessId)->orderBy('id')->get();

        if ($metas->isEmpty()) {
            $this->warn("Nenhuma meta para business_id={$businessId}.");

            return self::SUCCESS;
        }

        $ini = Carbon::today()->startOfMonth();
        $fim = Carbon::today()->endOfMonth();

        $this->info(sprintf(
            '%s — business_id=%d · janela %s → %s · trajetória=%s',
            $dry ? 'DRY-RUN (nada gravado)' : 'APLICANDO',
            $businessId, $ini->toDateString(), $fim->toDateString(), $trajetoria,
        ));
        $this->newLine();

        $linhas   = [];
        $gravados = 0;
        $semAlvo  = [];

        foreach ($metas as $meta) {
            $chave = $this->normalizar((string) $meta->nome);
            $atual = MetaPeriodo::withoutGlobalScopes()
                ->where('meta_id', $meta->id)
                ->whereDate('data_ini', $ini->toDateString())
                ->first();

            if (! isset($defs[$chave])) {
                $linhas[] = ["#{$meta->id} {$meta->nome}", $atual ? (string) $atual->valor_alvo : '—', 'PULADA — sem definição canônica'];

                continue;
            }

            $opcao = $defs[$chave]['opcao'];
            $valor = $this->option($opcao);

            if ($valor === null || $valor === '') {
                $semAlvo[] = ['meta' => $meta->nome, 'opcao' => '--' . $opcao];
                $linhas[]  = ["#{$meta->id} {$meta->nome}", $atual ? (string) $atual->valor_alvo : '—', "PULADA — falta --{$opcao}"];

                continue;
            }

            if (! is_numeric($valor) || (float) $valor <= 0) {
                $this->error("--{$opcao} precisa ser número > 0. Recebido: {$valor}");

                return self::FAILURE;
            }

            $linhas[] = [
                "#{$meta->id} {$meta->nome}",
                $atual ? (string) $atual->valor_alvo : '—',
                ($atual ? 'atualiza para ' : 'cria com alvo ') . number_format((float) $valor, 2, ',', '.'),
            ];

            if (! $dry) {
                MetaPeriodo::withoutGlobalScopes()->updateOrCreate(
                    ['meta_id' => $meta->id, 'data_ini' => $ini->toDateString()],
                    [
                        'tipo_periodo' => 'mes',
                        'data_fim'     => $fim->toDateString(),
                        'valor_alvo'   => (float) $valor,
                        'trajetoria'   => $trajetoria,
                    ],
                );
                $gravados++;
            }
        }

        $this->table(['Meta', 'Alvo antes', 'Ação'], $linhas);
        $this->newLine();
        $this->info(sprintf(
            '%s · %d gravado(s) · %d sem alvo',
            $dry ? 'DRY-RUN — NADA foi gravado' : 'APLICADO',
            $gravados,
            count($semAlvo),
        ));

        if ($semAlvo !== []) {
            $this->newLine();
            $this->warn('Metas sem alvo NÃO foram configuradas — e isso é deliberado: `valor_alvo` é');
            $this->warn('NOT NULL, e alvo é decisão de negócio. Derivar do histórico produziria um alvo');
            $this->warn('que ninguém escolheu, e um farol vermelho contra ele não significaria nada.');
            $this->newLine();
            foreach ($semAlvo as $s) {
                $this->line("  faltou <fg=yellow>{$s['opcao']}=<valor></> para \"{$s['meta']}\"");
            }
            $this->newLine();
            $this->line('Para ver o histórico medido e decidir: <fg=cyan>--historico</>');
        }

        return self::SUCCESS;
    }

    /**
     * Histórico real por métrica — para quem decide o alvo ter número na mão em vez
     * de chutar. NÃO é sugestão de alvo: é o que aconteceu.
     */
    private function mostrarHistorico(int $businessId, array $defs): int
    {
        $this->info("Histórico medido — business_id={$businessId} · recorte canônico (sell/final) · últimos 6 meses");
        $this->newLine();

        $meses = [];
        for ($i = 5; $i >= 0; $i--) {
            $ini = Carbon::today()->copy()->subMonthsNoOverflow($i)->startOfMonth();
            $fim = $i === 0 ? Carbon::today()->copy() : $ini->copy()->endOfMonth();
            $meses[] = [$ini, $fim];
        }

        $linhas = [];

        foreach ($defs as $nome => $def) {
            $valores = [];
            foreach ($meses as [$ini, $fim]) {
                $row = DB::selectOne(
                    "SELECT {$def['select']} AS v FROM transactions
                     WHERE business_id = ? AND type = 'sell' AND status = 'final'
                       AND transaction_date >= ? AND transaction_date < DATE_ADD(?, INTERVAL 1 DAY)",
                    [$businessId, $ini->toDateString(), $fim->toDateString()],
                );
                $valores[] = (float) ($row->v ?? 0);
            }

            $comDado = array_values(array_filter($valores, fn ($v) => $v > 0));

            $linhas[] = [
                $nome,
                '--' . $def['opcao'],
                $def['fmt'](max($valores)),
                $comDado !== [] ? $def['fmt'](array_sum($comDado) / count($comDado)) : '—',
                count($comDado) . '/' . count($valores),
            ];
        }

        $this->table(
            ['Métrica', 'Opção de alvo', 'Melhor mês', 'Média (só meses com venda)', 'Meses com venda'],
            $linhas,
        );

        $this->newLine();
        $this->warn('Isto é o que ACONTECEU, não sugestão de alvo. Alvo é o que se QUER — decisão de negócio.');

        return self::SUCCESS;
    }

    private function normalizar(string $s): string
    {
        $s  = mb_strtolower(trim($s));
        $de = ['á','à','ã','â','ä','é','ê','ë','í','ï','ó','õ','ô','ö','ú','ü','ç'];
        $pa = ['a','a','a','a','a','e','e','e','i','i','o','o','o','o','u','u','c'];

        return str_replace($de, $pa, $s);
    }
}

<?php

namespace Modules\Jana\Console\Commands;

use Illuminate\Console\Command;
use Modules\Jana\Entities\Meta;
use Modules\Jana\Entities\MetaFonte;

/**
 * Configura a `MetaFonte` das metas padrão de vendas — a query que o
 * `ApuracaoService` executa para apurar cada meta.
 *
 * POR QUE EXISTE (medido em produção, 2026-09-21): biz=1 tinha 5 metas ativas e as 5
 * SEM fonte e SEM período. Sem fonte não há query, e o Service lança "Meta #N não tem
 * MetaFonte configurada" — que foi exatamente o que o backfill reportou.
 *
 * ── O RECORTE É CANÔNICO, NÃO INVENTADO ──────────────────────────────────────
 * `type='sell' AND status='final' AND business_id=N AND transaction_date na janela` é
 * o mesmo recorte de `SellsCockpitAggregator::buildCoworkAggregates()` e do
 * `CopilotoDatabaseSeeder`. Ticket médio é `SUM(final_total)/COUNT(*)`, como em
 * `SellsCockpitAggregator:370`. Nenhuma fórmula nova foi criada aqui.
 *
 * ── A JANELA USA `< data_fim + 1 dia`, NÃO `BETWEEN` ─────────────────────────
 * `transaction_date` é DATETIME. `BETWEEN '2026-09-01' AND '2026-09-21'` compara contra
 * `2026-09-21 00:00:00` e **descarta todas as vendas do próprio dia 21**. O seeder
 * canônico tem esse defeito; aqui não se replica. `>= :data_ini AND < :data_fim + 1d`
 * inclui o dia inteiro e ainda usa índice (diferente de `DATE(transaction_date)`).
 *
 * ── O QUE ESTE COMANDO NÃO FAZ, E É DELIBERADO ───────────────────────────────
 * Não configura "Margem de contribuição". Margem exige o CUSTO do que foi vendido, e
 * medido no schema de produção: `transaction_sell_lines` não tem nenhuma coluna de
 * custo. O que existe é `variations.default_purchase_price` — o custo ATUAL do cadastro,
 * não o custo NO MOMENTO DA VENDA. Usá-lo para apurar margem histórica produziria um
 * número que parece certo e está errado, e o dono tomaria decisão em cima dele.
 * Configurar essa meta exige decidir a fórmula, que é decisão de negócio ([W]).
 *
 * Uso:
 *   php artisan jana:metas:configurar-fontes --business=1 --dry-run
 *   php artisan jana:metas:configurar-fontes --business=1
 *
 * Idempotente: `updateOrCreate` por `meta_id`. Rodar duas vezes não duplica nem altera.
 */
class ConfigurarFontesMetaCommand extends Command
{
    protected $signature = 'jana:metas:configurar-fontes
                            {--business= : ID do business (OBRIGATÓRIO — Tier 0, o CLI não tem session())}
                            {--dry-run : Mostra o que faria, sem gravar}';

    protected $description = 'Configura a MetaFonte (query de apuração) das metas padrão de vendas de um business';

    /**
     * Recorte canônico, idêntico ao do `SellsCockpitAggregator`. O `:business_id` é
     * obrigatório: o `SqlDriver::validarQuery` recusa query de meta com business que
     * não o referencie — é a garantia de isolamento Tier 0 dentro da própria query.
     */
    private const FILTRO = "business_id = :business_id
              AND type = 'sell'
              AND status = 'final'
              AND transaction_date >= :data_ini
              AND transaction_date < DATE_ADD(:data_fim, INTERVAL 1 DAY)";

    /**
     * Mapa slug-de-nome → expressão agregadora. A chave casa pelo NOME da meta em
     * minúsculas sem acento, porque o `slug` das metas de produção não segue padrão
     * previsível (foram criadas pela UI).
     */
    private function definicoes(): array
    {
        return [
            'faturamento mensal' => [
                'rotulo' => 'SUM(final_total)',
                'select' => 'COALESCE(SUM(final_total), 0)',
            ],
            'ticket medio' => [
                'rotulo' => 'SUM(final_total) / COUNT(*)',
                'select' => 'COALESCE(SUM(final_total) / NULLIF(COUNT(*), 0), 0)',
            ],
            'vendas no mes' => [
                'rotulo' => 'COUNT(*)',
                'select' => 'COUNT(*)',
            ],
            'clientes atendidos' => [
                'rotulo' => 'COUNT(DISTINCT contact_id)',
                'select' => 'COUNT(DISTINCT contact_id)',
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

        // SUPERADMIN: comando de manutenção fora de request — sem session() o global
        // scope não resolveria. O escopo é reimposto no where, que é mais restritivo.
        $metas = Meta::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->orderBy('id')
            ->get();

        if ($metas->isEmpty()) {
            $this->warn("Nenhuma meta para business_id={$businessId}.");

            return self::SUCCESS;
        }

        $defs = $this->definicoes();

        $this->info(sprintf(
            '%s — business_id=%d · %d meta(s)',
            $dry ? 'DRY-RUN (nada gravado)' : 'APLICANDO',
            $businessId,
            $metas->count(),
        ));
        $this->newLine();

        $linhas       = [];
        $configuradas = 0;

        foreach ($metas as $meta) {
            $chave = $this->normalizar((string) $meta->nome);
            $atual = MetaFonte::withoutGlobalScopes()->where('meta_id', $meta->id)->first();

            if (! isset($defs[$chave])) {
                $linhas[] = [
                    "#{$meta->id} {$meta->nome}",
                    $atual ? 'já tem' : 'AUSENTE',
                    'PULADA — sem definição canônica',
                ];

                continue;
            }

            $def   = $defs[$chave];
            $query = "SELECT {$def['select']} AS valor\n"
                . "        FROM transactions\n"
                . '        WHERE ' . self::FILTRO;

            $linhas[] = [
                "#{$meta->id} {$meta->nome}",
                $atual ? 'já tem' : 'AUSENTE',
                ($atual ? 'sobrescreve com ' : 'cria ') . $def['rotulo'],
            ];

            if (! $dry) {
                MetaFonte::withoutGlobalScopes()->updateOrCreate(
                    ['meta_id' => $meta->id],
                    [
                        'driver'      => 'sql',
                        'config_json' => ['query' => $query, 'binds_extra' => []],
                        'cadencia'    => 'diaria',
                    ],
                );
                $configuradas++;
            }
        }

        $this->table(['Meta', 'Fonte antes', 'Ação'], $linhas);
        $this->newLine();

        $puladas = count(array_filter($linhas, fn ($l) => str_contains($l[2], 'PULADA')));

        $this->info(sprintf(
            '%s · %d configurável(is) · %d pulada(s) · %d gravada(s)',
            $dry ? 'DRY-RUN — NADA foi gravado' : 'APLICADO',
            count($linhas) - $puladas,
            $puladas,
            $configuradas,
        ));

        if ($puladas > 0) {
            $this->newLine();
            $this->warn('Meta pulada não tem definição canônica aqui. "Margem de contribuição" é o caso');
            $this->warn('conhecido: exige o CUSTO do que foi vendido, e `transaction_sell_lines` não tem');
            $this->warn('coluna de custo. `variations.default_purchase_price` é o custo de HOJE, não o da');
            $this->warn('venda — usá-lo para margem histórica dá número errado com cara de certo.');
        }

        if ($dry) {
            $this->newLine();
            $this->line('<fg=yellow>Para aplicar, repita sem --dry-run. Depois rode jana:metas:backfill-apuracoes.</>');
        }

        return self::SUCCESS;
    }

    /** minúsculas, sem acento — o nome vem da UI e varia em acentuação. */
    private function normalizar(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $de = ['á','à','ã','â','ä','é','ê','ë','í','ï','ó','õ','ô','ö','ú','ü','ç'];
        $pa = ['a','a','a','a','a','e','e','e','i','i','o','o','o','o','u','u','c'];

        return str_replace($de, $pa, $s);
    }
}

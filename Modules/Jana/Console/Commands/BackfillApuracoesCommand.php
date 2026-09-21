<?php

namespace Modules\Jana\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Jana\Entities\Meta;
use Modules\Jana\Entities\MetaApuracao;
use Modules\Jana\Services\ApuracaoService;

/**
 * Backfill de apurações de meta — preenche N janelas PASSADAS para que a série
 * temporal do Painel tenha o que desenhar.
 *
 * POR QUE EXISTE (medido em produção, 2026-09-21): biz=1 tinha 5 metas ativas e
 * as 5 em "Aguardando apuração…" — zero apuração gravada. Os dois caminhos que
 * existiam não resolvem:
 *
 *  - `POST /ia/metas/{id}/reapurar` apura **só a janela de hoje**, e o docblock do
 *    `MetasController::reapurar` declara que reapurar um intervalo "exige contrato
 *    de rota novo: decisão [W]". Uma apuração = um ponto, e o `Sparkline` do card
 *    (`Index.tsx:99`) exige **>= 2** — com um ponto o card mostra "Sem histórico";
 *  - o `ApurarMetaJob` é `ShouldQueue` e cai na fila `default`, cujo worker está
 *    atrás de `config('queue.backlog_worker_enabled')` (default FALSE, e o comentário
 *    do `Kernel.php` explica por que ligar sem purgar antes é perigoso). Medido: o
 *    job disparado ficou represado e a tela não mudou.
 *
 * Este comando roda **síncrono**, sem fila, e reusa `ApuracaoService::apurar()` —
 * o dono do cálculo. Não reimplementa driver, binds nem hash.
 *
 * Idempotente por construção: o Service persiste com `updateOrCreate` na chave
 * (`meta_id`, `data_ref`, `fonte_query_hash`). Rodar duas vezes não duplica linha.
 *
 * Uso:
 *   php artisan jana:metas:backfill-apuracoes --business=1 --dry-run
 *   php artisan jana:metas:backfill-apuracoes --business=1 --janelas=12 --detail
 *   php artisan jana:metas:backfill-apuracoes --business=1 --meta=3
 *
 * ⚠️ `--dry-run` NÃO é simulação por estimativa: ele executa o cálculo real dentro
 * de uma transação e dá **rollback**. O número que ele mostra é o número que seria
 * gravado, não uma aproximação — e é por isso que ele reusa o Service em vez de
 * recalcular por fora.
 */
class BackfillApuracoesCommand extends Command
{
    protected $signature = 'jana:metas:backfill-apuracoes
                            {--business= : ID do business (OBRIGATÓRIO — Tier 0, o CLI não tem session())}
                            {--meta= : ID de UMA meta (default: todas as ativas do business)}
                            {--janelas=12 : Quantas janelas mensais apurar, contando a corrente}
                            {--dry-run : Calcula de verdade e faz rollback — mostra antes→depois sem gravar}
                            {--detail : Lista cada janela, não só o resumo por meta}';

    protected $description = 'Apura N janelas mensais passadas das metas de um business, para a série do Painel ter dado';

    public function handle(ApuracaoService $service): int
    {
        $bizOpt = $this->option('business');

        // Tier 0 (ADR 0093): sem business explícito o comando não roda. O CLI não
        // tem `session()`, então o global scope não protegeria nada aqui — a barreira
        // tem de ser o argumento.
        if ($bizOpt === null || $bizOpt === '' || ! ctype_digit((string) $bizOpt)) {
            $this->error('--business=<id> é obrigatório (inteiro). O CLI não tem session(), então o escopo vem daqui.');

            return self::FAILURE;
        }

        $businessId = (int) $bizOpt;
        $janelas    = max(1, (int) $this->option('janelas'));
        $dry        = (bool) $this->option('dry-run');
        $detail     = (bool) $this->option('detail');
        $metaOpt    = $this->option('meta');

        // SUPERADMIN: comando de manutenção roda fora de request, sem session() para o
        // global scope resolver. O escopo é reimposto explicitamente no where abaixo —
        // é mais restritivo que o scope, porque exige o id em vez de inferi-lo.
        //
        // As relações vêm carregadas COM `withoutGlobalScopes` de propósito: `MetaFonte` e
        // `MetaPeriodo` são multi-tenant VIA PARENT, e o `loadMissing(['fonte',
        // 'periodoAtual'])` que o `ApuracaoService` faz por dentro aplicaria o scope — que
        // sem `session()` não resolve e devolve vazio. O Service então lançaria
        // "Meta #N não tem MetaFonte configurada" para metas que TÊM fonte. Como o
        // `loadMissing` não recarrega o que já está carregado, pré-carregar aqui é o que
        // faz o comando funcionar no CLI. Medido no CT 100: sem isto, 10 de 10 janelas
        // falhavam com essa mensagem.
        // SUPERADMIN: closure aplicada às relações do eager-load. `MetaFonte` e `MetaPeriodo`
        // são multi-tenant VIA PARENT — o escopo delas resolve pelo business da Meta, que o
        // where abaixo já fixou. Sem isto o CLI carrega relação vazia (ver bloco acima).
        $semEscopo = fn ($q) => $q->withoutGlobalScopes();

        // SUPERADMIN: o `where('business_id', $businessId)` da linha seguinte é MAIS
        // restritivo que o scope — exige o id explícito em vez de inferi-lo da sessão, que
        // no CLI não existe.
        $query = Meta::withoutGlobalScopes()
            ->with(['fonte' => $semEscopo, 'periodoAtual' => $semEscopo])
            ->where('business_id', $businessId);

        if ($metaOpt !== null && $metaOpt !== '') {
            $query->where('id', (int) $metaOpt);
        }

        $metas = $query->orderBy('id')->get();

        if ($metas->isEmpty()) {
            $this->warn("Nenhuma meta encontrada para business_id={$businessId}.");

            return self::SUCCESS;
        }

        $datas = $this->janelas($janelas);

        $this->info(sprintf(
            '%s — business_id=%d · %d meta(s) · %d janela(s): %s → %s',
            $dry ? 'DRY-RUN (rollback ao fim, nada gravado)' : 'APLICANDO',
            $businessId,
            $metas->count(),
            count($datas),
            $datas[0]->toDateString(),
            end($datas)->toDateString(),
        ));

        $linhas   = [];
        $erros    = 0;
        $gravadas = 0;

        foreach ($metas as $meta) {
            foreach ($datas as $dataRef) {
                // ANTES: o que já está gravado para esta (meta, data), se houver.
                // SUPERADMIN: leitura do valor ANTES, para o antes→depois. Filtrada por
                // `meta_id` de uma Meta que o where do business já validou — o escopo não
                // acrescentaria isolamento, e no CLI ele devolveria vazio.
                $antes = MetaApuracao::withoutGlobalScopes()
                    ->where('meta_id', $meta->id)
                    ->whereDate('data_ref', $dataRef->toDateString())
                    ->value('valor_realizado');

                try {
                    // O cálculo REAL acontece nos dois modos. O que muda é o commit.
                    DB::beginTransaction();
                    $ap = $service->apurar($meta, $dataRef->copy());
                    $depois = $ap->valor_realizado;

                    if ($dry) {
                        DB::rollBack();
                    } else {
                        DB::commit();
                        $gravadas++;
                    }
                } catch (\Throwable $e) {
                    DB::rollBack();
                    $erros++;
                    $this->line(sprintf(
                        '  <fg=red>x</> meta #%d %s — %s',
                        $meta->id,
                        $dataRef->toDateString(),
                        mb_substr($e->getMessage(), 0, 120),
                    ));

                    continue;
                }

                $mudou = $antes === null
                    || abs(((float) $antes) - ((float) $depois)) > 0.0000001;

                $linhas[] = [
                    'meta'   => "#{$meta->id} {$meta->nome}",
                    'data'   => $dataRef->toDateString(),
                    'antes'  => $antes === null ? '—' : $this->fmt($antes),
                    'depois' => $this->fmt($depois),
                    'mudou'  => $mudou ? 'sim' : 'não',
                ];

                if ($detail) {
                    $this->line(sprintf(
                        '  %s meta #%d %s: %s → %s',
                        $mudou ? '<fg=yellow>~</>' : '<fg=gray>=</>',
                        $meta->id,
                        $dataRef->toDateString(),
                        $antes === null ? '—' : $this->fmt($antes),
                        $this->fmt($depois),
                    ));
                }
            }
        }

        if ($linhas !== []) {
            $mudaram = array_values(array_filter($linhas, fn ($l) => $l['mudou'] === 'sim'));

            // A tabela mostra só o que MUDA — listar 60 linhas iguais esconde as que importam.
            $this->newLine();
            if ($mudaram !== []) {
                $this->table(['Meta', 'Data ref', 'Antes', 'Depois', 'Mudou'], $mudaram);
            } else {
                $this->line('<fg=gray>Nenhuma janela muda de valor — tudo já estava apurado com o mesmo número.</>');
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%s · %d janela(s) calculada(s) · %d com mudança · %d gravada(s) · %d erro(s)',
            $dry ? 'DRY-RUN — NADA foi gravado' : 'APLICADO',
            count($linhas),
            count(array_filter($linhas, fn ($l) => $l['mudou'] === 'sim')),
            $gravadas,
            $erros,
        ));

        if ($dry) {
            $this->line('<fg=yellow>Para aplicar, repita o comando sem --dry-run.</>');
        }

        return $erros > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * As datas de referência, da mais antiga para a mais nova.
     *
     * `ApuracaoService::apurar()` calcula a janela como `startOfMonth($dataRef) → $dataRef`,
     * então uma data por mês produz uma janela mensal fechada. O mês corrente usa HOJE como
     * ponta, não o fim do mês: apurar até uma data futura mediria um intervalo que ainda
     * não aconteceu e gravaria um ponto que só cresceria depois.
     *
     * @return list<Carbon>
     */
    private function janelas(int $n): array
    {
        $hoje  = Carbon::today();
        $datas = [];

        for ($i = $n - 1; $i >= 0; $i--) {
            // subMonthsNoOverflow: 31/03 menos 1 mês é 28/02, não 03/03.
            $ref = $hoje->copy()->subMonthsNoOverflow($i);
            $datas[] = $i === 0 ? $hoje->copy() : $ref->endOfMonth()->startOfDay();
        }

        return $datas;
    }

    private function fmt(mixed $v): string
    {
        return number_format((float) $v, 2, ',', '.');
    }
}
